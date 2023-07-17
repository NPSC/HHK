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
use HHK\HTMLControls\HTMLContainer;



/**
 * Description of ActiveReservation
 *
 * @author Eric
 */

class ActiveReservation extends Reservation {

    /**
     * Summary of gotoCheckingIn
     * @var string
     */
    protected $gotoCheckingIn = '';

    /**
     * Summary of repeatResvErrors
     * @var array
     */
    protected $repeatResvErrors = [];

    /**
     * Summary of createMarkup
     * @param \PDO $dbh
     * @return array
     */
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

    /**
     * Summary of save
     * @param \PDO $dbh
     * @param array $post
     * @return ActiveReservation
     */
    public function save(\PDO $dbh) {

        $uS = Session::getInstance();

        $this->saveResv($dbh);

        if ($uS->AcceptResvPaymt) {
            $this->savePrePayment($dbh);
        }

        if ($uS->UseRepeatResv) {
            $repeatResv = new RepeatReservations();
            $repeatResv->saveRepeats($dbh, $this->reservRs);
            $this->repeatResvErrors = $repeatResv->getErrorArray();
        }

        return $this;

    }

    /**
     * @param \PDO $dbh
     * @return \HHK\House\Reservation\ActiveReservation|\HHK\House\Reservation\StaticReservation
     */
    protected function saveResv(\PDO $dbh) {

        $uS = Session::getInstance();

        $this->initialSave($dbh);

        if ($this->reserveData->hasError()) {
            return $this;
        }

        $args = [
            'resvCkinNow' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selResvStatus' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selPayType' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'taCkinNotes' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtRibbonNote' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selResource' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'taNewNote' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        ];

        $post = filter_input_array(INPUT_POST, $args);

        // Set goto checkingin page?
        if (isset($post['resvCkinNow'])) {
            $this->gotoCheckingIn = $post['resvCkinNow'];
        }

        // Open Reservation
        $resv = new Reservation_1($this->reservRs);

        $resv->setExpectedPayType($uS->DefaultPayType);
        $resv->setHospitalStay($this->family->getHospStay());
        $resv->setExpectedArrival($this->reserveData->getArrivalDT()->format('Y-m-d ' . $uS->CheckInTime . ':00:00'));
        $resv->setExpectedDeparture($this->reserveData->getDepartureDT()->format('Y-m-d ' . $uS->CheckOutTime . ':00:00'));

        // Determine Reservation Status
        $reservStatus = ReservationStatus::Waitlist;

        $reservStatuses = readLookups($dbh, "reservStatus", "Code");

        if (isset($post['selResvStatus']) && $post['selResvStatus'] != '') {

            if ($post['selResvStatus'] != ''  && isset($reservStatuses[$post['selResvStatus']])) {
                $reservStatus = $post['selResvStatus'];
            }

        } else if ($resv->isNew() === FALSE && $resv->getStatus() != '') {
            $reservStatus = $resv->getStatus();
        }

        // Set reservation status
        $resv->setStatus($reservStatus);
        $this->reserveData->setResvStatusType($reservStatuses[$reservStatus]['Type']);

        // Cancel reservation?
        if ($resv->isRemovedStatus($reservStatus, $reservStatuses)) {
            $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
            return new StaticReservation($this->reserveData, $this->reservRs, $this->family);
        }

        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());
        if ($uS->TrackAuto) {
            $reg->extractVehicleFlag();
        }

        $reg->saveRegistrationRs($dbh, $this->reserveData->getIdPsg(), $uS->username);

        // Save any vehicles
        if ($uS->TrackAuto && $reg->getNoVehicle() == 0) {
            Vehicle::saveVehicle($dbh, $reg->getIdRegistration());
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
        $this->setRoomRate($dbh, $reg, $resv);

        // Reservation anticipated Payment Type
        if (isset($post['selPayType'])) {
            $resv->setExpectedPayType(filter_var($post['selPayType'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        // Verbal Confirmation Flag
        if (isset($_POST['cbVerbalConf']) && $resv->getVerbalConfirm() != 'v') {
            $resv->setVerbalConfirm('v');
            LinkNote::save($dbh, 'Verbal Confirmation is Set.', $resv->getIdReservation(), Note::ResvLink, '', $uS->username, $uS->ConcatVisitNotes);
        } else {
            $resv->setVerbalConfirm('');
        }

        // Check-in notes (to be put on the registration form. ALternatively, use as waitlist notes.
        if (isset($post['taCkinNotes'])) {
            $resv->setCheckinNotes(filter_var($post['taCkinNotes'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        // Ribbon Note
        if (isset($post['txtRibbonNote'])){
            $resv->setNotes(filter_var($post['txtRibbonNote'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
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
        if ($resv->isActiveStatus($reservStatus, $reservStatuses) && $idRescPosted == 0) {
            $resv->setStatus(ReservationStatus::Waitlist);
        }

        // Save reservation
        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);

        $this->reserveData->setIdResv($resv->getIdReservation());

        $this->saveReservationGuests($dbh);
        $resv->saveConstraints($dbh, $post);

        // Notes
        if (isset($post['taNewNote'])) {

            $noteText = filter_var($post['taNewNote'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($noteText != '') {
                LinkNote::save($dbh, $noteText, $resv->getIdReservation(), Note::ResvLink, '', $uS->username, $uS->ConcatVisitNotes);
            }
        }

        // Room Choice
        $this->setRoomChoice($dbh, $resv, $idRescPosted, $reservStatuses);
        $this->reservRs = $resv->getReservationRS();

        return $this;
    }

    /**
     * Summary of delete
     * @param \PDO $dbh
     * @return array
     */
    public function delete(\PDO $dbh) {

        $uS = Session::getInstance();
        $numberDeleted = 0;

        // Check for pre-payment return target.
        if ($uS->AcceptResvPaymt) {
            $this->savePrePayment($dbh);
        }

        // check for delete children
        if ($this->reserveData->getDeleteChildReservations() === true) {

            $children = RepeatReservations::getHostChildren($dbh, $this->reserveData->getIdResv());

            // Delete each child reservation not yet checked-in.
            foreach ($children as $c => $h) {

                $resv = Reservation_1::instantiateFromIdReserv($dbh, $c);

                if ($resv->getStatus() != ReservationStatus::Staying && $resv->getStatus() != ReservationStatus::Checkedout) {

                    // Okay to delete
                    if ($resv->deleteMe($dbh, $uS->username)) {
                        $numberDeleted++;
                    };
                }
            }
        }

        // Delete it
        $dataArray = parent::delete($dbh);
        $dataArray['childDeleted'] = $numberDeleted;


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


    /**
     * Summary of changeRoom
     * @param \PDO $dbh
     * @param int $idResv
     * @param int $idResc
     * @return array<string>
     */
    public function changeRoom(\PDO $dbh, $idResv, $idResc) {

        $uS = Session::getInstance();
        $dataArray = array();

        if ($idResv < 1) {
            return array('error'=>'Reservation Id is not set.');
        }

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);
        $reservStatuses = readLookups($dbh, "reservStatus", "Code");

        if ($resv->isActive($reservStatuses)) {

            $result = $this->setRoomChoice($dbh, $resv, $idResc, $reservStatuses);

            if ($this->reserveData->hasError()) {
                $dataArray[ReserveData::WARNING] = $this->reserveData->getErrors();
            } else if ($result == ''){
                $dataArray[ReserveData::SUCCESS] = 'Reservation Changed Rooms.';
            } else {
                $dataArray['msg'] = 'Reservation Changed Rooms. ' . ($result == '' ? '' : ' WARNING: ' . $result);
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

    /**
     * Summary of checkedinMarkup
     * @param \PDO $dbh
     * @return array
     */
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

                if ($this->payResult->getReceiptMarkup() == '') {

                    $dataArray['receiptMarkup'] = HTMLContainer::generateMarkup('div', $this->payResult->getReplyMessage());
                } else {

                    $dataArray['receiptMarkup'] = $this->payResult->getReceiptMarkup();
                }

            }

        } else {
            // No payments
            $dataArray = $this->createMarkup($dbh);
        }

        return $dataArray;
    }

    /**
     * Summary of savePrePayment
     * @param \PDO $dbh
     * @return void
     */
    public function savePrePayment(\PDO $dbh) {

        $pmp = PaymentChooser::readPostedPayment($dbh);  // Returns PaymentManagerPayment.

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