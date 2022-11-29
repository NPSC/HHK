<?php

namespace HHK\House\Reservation;

use HHK\House\Registration;
use HHK\House\Vehicle;
use HHK\House\ReserveData\ReserveData;
use HHK\Note\{LinkNote, Note};
use HHK\SysConst\{ReservationStatus};
use HHK\sec\Session;
use HHK\Document\FormDocument;
use HHK\Purchase\PaymentChooser;
use HHK\Payment\PaymentManager\ResvPaymentManager;
use HHK\Payment\PaymentResult\PaymentResult;
use HHK\House\HouseServices;



/**
 * Description of ActiveReservation
 *
 * @author Eric
 */

class ActiveReservation extends Reservation {

    protected $gotoCheckingIn = '';

    public function createMarkup(\PDO $dbh) {

        // Checking In?
        if ($this->gotoCheckingIn === 'yes' && $this->reserveData->getIdResv() > 0) {
            return array('gotopage'=>'CheckingIn.php?rid=' . $this->reserveData->getIdResv());
        }

        // Verify reserve status.
        if ($this->reservRs->Status->getStoredVal() == '') {
            $this->reservRs->Status->setStoredVal(ReservationStatus::Waitlist);
        }

        // Get any previous settings and set primary guest if blank.
        $oldResvId = $this->copyOldReservation($dbh);

        $formUserData = [];

        // Remote user Referral
        if ($this->reserveData->getIdReferralDoc() > 0) {

            $formDoc = new FormDocument();

            if ($formDoc->loadDocument($dbh, $this->reserveData->getIdReferralDoc())) {
                $formUserData = $formDoc->getUserData();
            }
        }


        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh, (isset($formUserData['hospital'])?$formUserData['hospital']:[]));
        $this->createFamilyMarkup($dbh, $formUserData);

        // Add the reservation section.
        $this->reserveData->setResvSection($this->createResvMarkup($dbh, $oldResvId, '', (isset($formUserData['vehicle'])?$formUserData['vehicle']:[])));

        return $this->reserveData->toArray();

    }

    public function save(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $this->saveResv($dbh, $post);

        if ($uS->AcceptResvPaymt) {
            $this->savePrePayment($dbh, $post);
        }

        return $this;

    }

    /**
     * @param \PDO $dbh
     * @param array $post
     * @return \HHK\House\Reservation\ActiveReservation|\HHK\House\Reservation\StaticReservation
     */
    protected function saveResv(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $this->initialSave($dbh, $post);

        if ($this->reserveData->hasError()) {
            return $this;
        }

        // Set goto checkingin page?
        if (isset($post['resvCkinNow'])) {
            $this->gotoCheckingIn = filter_var($post['resvCkinNow'], FILTER_SANITIZE_STRING);
        }

        // Open Reservation
        $resv = new Reservation_1($this->reservRs);

        $resv->setExpectedPayType($uS->DefaultPayType);
        $resv->setHospitalStay($this->family->getHospStay());
        $resv->setExpectedArrival($this->reserveData->getArrivalDT()->format('Y-m-d ' . $uS->CheckInTime . ':00:00'));
        $resv->setExpectedDeparture($this->reserveData->getDepartureDT()->format('Y-m-d ' . $uS->CheckOutTime . ':00:00'));

        // Determine Reservation Status
        $reservStatus = ReservationStatus::Waitlist;

        if (isset($post['selResvStatus']) && $post['selResvStatus'] != '') {

            $rStat = filter_var($post['selResvStatus'], FILTER_SANITIZE_STRING);

            if ($rStat != ''  && isset($uS->guestLookups['ReservStatus'][$rStat])) {
                $reservStatus = $rStat;
            }

        } else if ($resv->isNew() === FALSE && $resv->getStatus() != '') {
            $reservStatus = $resv->getStatus();
        }

        // Set reservation status
        $resv->setStatus($reservStatus);

        // Cancel reservation?
        if (Reservation_1::isRemovedStatus($reservStatus)) {
            $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
            return new StaticReservation($this->reserveData, $this->reservRs, $this->family);
        }

        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());
        if ($uS->TrackAuto) {
            $reg->extractVehicleFlag($post);
        }

        $reg->saveRegistrationRs($dbh, $this->reserveData->getIdPsg(), $uS->username);

        // Save any vehicles
        if ($uS->TrackAuto && $reg->getNoVehicle() == 0) {
            Vehicle::saveVehicle($dbh, $post, $reg->getIdRegistration());
        }

        // Find any staying people.
        $stayingMembers = $this->getStayingMembers();
        $resv->setNumberGuests(count($stayingMembers));

        // Primary guest must be staying or now checking in.
        $idPriGuest = $this->reserveData->findPrimaryGuestId();

        if (isset($stayingMembers[$idPriGuest])) {
            $resv->setIdGuest($idPriGuest);
        } else {
            // Find first staying member.
            foreach ($this->reserveData->getPsgMembers() as $m) {

                if ($m->isStaying()) {

                    $m->setPrimaryGuest(TRUE);
                    $this->reserveData->setMember($m);
                    $resv->setIdGuest($m->getId());
                    break;
                }
            }
        }

        // Collect the room rates
        $this->setRoomRate($dbh, $reg, $resv, $post);

        // Reservation anticipated Payment Type
        if (isset($post['selPayType'])) {
            $resv->setExpectedPayType(filter_var($post['selPayType'], FILTER_SANITIZE_STRING));
        }

        // Verbal Confirmation Flag
        if (isset($post['cbVerbalConf']) && $resv->getVerbalConfirm() != 'v') {
            $resv->setVerbalConfirm('v');
            LinkNote::save($dbh, 'Verbal Confirmation is Set.', $resv->getIdReservation(), Note::ResvLink, '', $uS->username, $uS->ConcatVisitNotes);
        } else {
            $resv->setVerbalConfirm('');
        }

        // Check-in notes (to be put on the registration form. ALternatively, use as waitlist notes.
        if (isset($post['taCkinNotes'])) {
            $resv->setCheckinNotes(filter_var($post['taCkinNotes'], FILTER_SANITIZE_STRING));
        }

        // Ribbon Note
        if (isset($post['txtRibbonNote'])){
            $resv->setNotes(filter_var($post['txtRibbonNote'], FILTER_SANITIZE_STRING));
        }

        // remove room if reservation is in waitlist
        if ($reservStatus == ReservationStatus::Waitlist) {
            $resv->setIdResource(0);
        }

        // Room number chosen
        $idRescPosted = 0;
        if (isset($post['selResource'])) {
            $idRescPosted = intval(filter_Var($post['selResource'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        // Switch to waitlist status if room is 0
        if ($resv->isActiveStatus($reservStatus) && $idRescPosted == 0) {
            $resv->setStatus(ReservationStatus::Waitlist);
        }

        // Save reservation
        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);

        $this->reserveData->setIdResv($resv->getIdReservation());

        $this->saveReservationGuests($dbh);
        $resv->saveConstraints($dbh, $post);

        // Notes
        if (isset($post['taNewNote'])) {

            $noteText = filter_var($post['taNewNote'], FILTER_SANITIZE_STRING);

            if ($noteText != '') {
                LinkNote::save($dbh, $noteText, $resv->getIdReservation(), Note::ResvLink, '', $uS->username, $uS->ConcatVisitNotes);
            }
        }

        // Room Choice
        $this->setRoomChoice($dbh, $resv, $idRescPosted);

    }

    public function delete(\PDO $dbh, $post = []) {

        $uS = Session::getInstance();

        // Check for pre-payment return target.
        if ($uS->AcceptResvPaymt) {
            $this->savePrePayment($dbh, $post);
        }

        // Delete it
        $dataArray = parent::delete($dbh);


        if ($this->payResult !== NULL) {
            // Payment processed

            if ($this->payResult->getStatus() == PaymentResult::FORWARDED) {

                $creditCheckOut = $this->payResult->getForwardHostedPayment();

                // Credit payment?
                if (count($creditCheckOut) > 0) {
                    return $creditCheckOut;
                }

            } else {

                $dataArray['receiptMarkup'] = $this->payResult->getReceiptMarkup();

            }
        }

        return $dataArray;
    }


    public function changeRoom(\PDO $dbh, $idResv, $idResc) {

        $uS = Session::getInstance();
        $dataArray = array();

        if ($idResv < 1) {
            return array('error'=>'Reservation Id is not set.');
        }

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);

        if ($resv->isActive()) {

            $this->setRoomChoice($dbh, $resv, $idResc);

            if ($this->reserveData->hasError()) {
                $dataArray[ReserveData::WARNING] = $this->reserveData->getErrors();
            } else {
                $dataArray['msg'] = 'Reservation Changed Rooms.';
            }

            // New resservation lists
            $dataArray['reservs'] = 'y';
            $dataArray['waitlist'] = 'y';

            if ($uS->ShowUncfrmdStatusTab) {
                $dataArray['unreserv'] = 'y';
            }

        }

        return $dataArray;
    }

    public function checkedinMarkup(\PDO $dbh) {

        // This runs after a save.  Not actually checked in.
        $dataArray = [];

        if ($this->payResult !== NULL) {
            // Payment processed

            $creditCheckOut = [];

            if ($this->payResult->getStatus() == PaymentResult::FORWARDED) {

                $creditCheckOut = $this->payResult->getForwardHostedPayment();

                // Credit payment?
                if (count($creditCheckOut) > 0) {
                    return $creditCheckOut;
                }

            } else {

                $this->gotoCheckingIn = 'no';
                $dataArray = $this->createMarkup($dbh);
                $dataArray['receiptMarkup'] = $this->payResult->getReceiptMarkup();

            }

        } else {
            // No payments
            $dataArray = $this->createMarkup($dbh);
        }

        return $dataArray;
    }

    public function savePrePayment(\PDO $dbh, $post) {

        $pmp = PaymentChooser::readPostedPayment($dbh, $post);  // Returns PaymentManagerPayment.

        if (is_null($pmp) === FALSE && ($pmp->getTotalPayment() != 0 || $pmp->getOverPayment() != 0)) {

            $resv = new Reservation_1($this->reservRs);
            $resvPaymentManager = new ResvPaymentManager($pmp);

            $this->payResult = HouseServices::processPayments($dbh, $resvPaymentManager, $resv, 'Reserve.php?rid=' . $resv->getIdReservation(), $resv->getIdGuest());

            // Relate Invoice to Reservation
            if (! is_Null($this->payResult) && $this->payResult->getIdInvoice() > 0 && $resv->getIdReservation() > 0) {
                $dbh->exec("insert ignore into `reservation_invoice` Values(".$resv->getIdReservation()."," .$this->payResult->getIdInvoice() . ")");
            }

        } else {
            $this->payResult = NULL;
        }
    }
}
?>