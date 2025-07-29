<?php

namespace HHK\House\Reservation;

use HHK\Checklist;
use HHK\House\Family\Family;
use HHK\House\Registration;
use HHK\House\Vehicle;
use HHK\House\ReserveData\ReserveData;
use HHK\Note\{LinkNote, Note};
use HHK\sec\Labels;
use HHK\SysConst\{ReservationStatus};
use HHK\sec\Session;
use HHK\Document\FormDocument;
use HHK\Purchase\PaymentChooser;
use HHK\Payment\PaymentManager\ResvPaymentManager;
use HHK\Payment\PaymentResult\PaymentResult;
use HHK\House\HouseServices;
use HHK\HTMLControls\HTMLContainer;
use HHK\SysConst\ChecklistType;
use HHK\SysConst\ExcessPay;
use HHK\SysConst\InvoiceLineType;
use HHK\SysConst\ItemId;
use HHK\Tables\EditRS;
use HHK\Tables\Reservation\Reservation_GuestRS;
use HHK\Tables\Reservation\ReservationRS;



/**
 * Description of ActiveReservation
 *
 * @author Eric
 */

class ActiveReservation extends Reservation {


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
        if ($this->getGotoCheckingIn() === 'yes' && $this->reserveData->getIdResv() > 0) {
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
            'selPayType' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,     // Reservation Pay With Selector, not paying today payment type selector.
            'taCkinNotes' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtRibbonNote' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selResource' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'taNewNote' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'cbRebook' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'newGstDate' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selexcpay' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'cbRS'  =>  [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ];

        //$post = filter_input_array(INPUT_POST, $args);
        $post = filter_var_array($this->reserveData->getRawPost(), $args);

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
        $resv->setIdReferralDoc(null); //decouple online referral doc after initial save

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

        $oldResvStatus = $resv->getStatus();
        // Set reservation status
        $resv->setStatus($reservStatus);
        $this->reserveData->setResvStatusType($reservStatuses[$reservStatus]['Type']);


        // Cancel reservation?
        if ($resv->isRemovedStatus($reservStatus, $reservStatuses)) {
            if($uS->UseRebook && isset($post['cbRebook'])){
                $newIdResv = $this->rebookReservation($dbh, $resv, $oldResvStatus, $post);
                if($newIdResv > 0){
                    $this->reserveData->addMsg( Labels::getString("MemberType","Guest", "Guest") . "s rebooked for " . $post['newGstDate']);
                }else{
                    return $this;
                }
            }
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
            Vehicle::saveVehicle($dbh, $reg->getIdRegistration(), $this->reservRs->idReservation->getStoredVal());
        }

        // Save Checklists
        Checklist::saveChecklist($dbh, $reg->getIdPsg(), ChecklistType::PSG);

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

    protected function rebookReservation(\PDO $dbh, Reservation_1 $resv, $oldResvStatus, array $post){
        $uS = Session::getInstance();

        //check dates
        if(isset($post['newGstDate'])){
            $newArrival = new \DateTime($post['newGstDate']);
            $departure = new \DateTime($resv->getDeparture());
            if($newArrival < $departure && $newArrival->diff($departure)->days > 0){
                $guests = [];
                $rgRs = new Reservation_GuestRS();
                $rgRs->idReservation->setStoredVal($resv->getIdReservation());
                $rgRows = EditRS::select($dbh, $rgRs, array($rgRs->idReservation));

                foreach ($rgRows as $g) {
                    $guests[$g['idGuest']] = $g['Primary_Guest'];
                }

                $newIdResv = RepeatReservations::makeNewReservation($dbh, $resv, $newArrival, $departure, $resv->getIdResource(), $oldResvStatus, $guests);

                if($uS->AcceptResvPaymt && $resv->getIdReservation() > 0 && $newIdResv > 0 && isset($post["selexcpay"]) && $post["selexcpay"] == ExcessPay::MoveToResv){
                    $dbh->exec("UPDATE `reservation_invoice_line` set `Reservation_Id` = " . $newIdResv . " where `Reservation_Id` = " . $resv->getIdReservation());
                }

                return $newIdResv;

            }else{
                $this->reserveData->addError("Error rebooking: New arrival date must be before departure date");
            }
        }else{
            $this->reserveData->addMsg("Error rebooking: New arrival date is required");
        }


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
        // if ($this->reserveData->getDeleteChildReservations() === true) {

        //     $children = RepeatReservations::getHostChildren($dbh, $this->reserveData->getIdResv());

        //     // Delete each child reservation not yet checked-in.
        //     foreach ($children as $c => $h) {

        //         $resv = Reservation_1::instantiateFromIdReserv($dbh, $c);

        //         if ($resv->getStatus() != ReservationStatus::Staying && $resv->getStatus() != ReservationStatus::Checkedout) {

        //             // Okay to delete
        //             if ($resv->deleteMe($dbh, $uS->username)) {
        //                 $numberDeleted++;
        //             };
        //         }
        //     }
        // }

        // Delete it
        $dataArray = parent::delete($dbh);
    //    $dataArray['childDeleted'] = $numberDeleted;


        if ($this->payResult !== NULL) {
            // Payment processed

            if ($this->payResult->getStatus() == PaymentResult::FORWARDED) {

                $creditCheckOut = $this->payResult->getForwardHostedPayment();

                // Credit payment?
                if (count($creditCheckOut) > 0) {
                    if(isset($creditCheckOut['hpfToken'])){
                        $dataArray['deluxehpf'] = $creditCheckOut;
                    }else{
                        return $creditCheckOut;
                    }
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
        $dataArray = [];

        if ($idResv < 1) {
            return ['error' => 'Reservation Id is not set.'];
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

            $this->gotoCheckingIn = 'no';
            $dataArray = $this->createMarkup($dbh);

            if ($this->payResult->getStatus() == PaymentResult::FORWARDED) {

                $creditCheckOut = $this->payResult->getForwardHostedPayment();

                // Credit payment?
                if (count($creditCheckOut) > 0) {
                    if(isset($creditCheckOut['hpfToken'])){
                        $dataArray['deluxehpf'] = $creditCheckOut;
                    }else{
                        return $creditCheckOut;
                    }
                }

            } else {

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

        $pmp = PaymentChooser::readPostedPayment($dbh, $this->reserveData->getRawPost());  // Returns PaymentManagerPayment.
        $resv = new Reservation_1($this->reservRs);

        if (is_null($pmp) === FALSE && ($pmp->getTotalPayment() != 0 || $pmp->getOverPayment() != 0)) {

            $post = $this->reserveData->getRawPost();
            $reservStatuses = readLookups($dbh, "reservStatus", "Code");
            $resvIsActive = $resv->isActive($reservStatuses);

            $resvPaymentManager = new ResvPaymentManager($pmp);

            $this->payResult = HouseServices::processPayments($dbh, $resvPaymentManager, $resv, 'Reserve.php?rid=' . $resv->getIdReservation(), $resv->getIdGuest());

            $excpay = $post["selexcpay"];

            // Relate Invoice to Reservation
            if (! is_Null($this->payResult) && $this->payResult->getIdInvoice() > 0 && $resv->getIdReservation() > 0) {
                if(isset($post["selexcpay"]) && $post["selexcpay"] == ExcessPay::Hold){
                    $dbh->exec("insert ignore into `reservation_invoice_line` select '".$resv->getIdReservation()."', il.idInvoice_Line from invoice_line il where il.Invoice_Id = " .$this->payResult->getIdInvoice() . " and il.Item_Id = '" . ItemId::LodgingMOA . "' and il.Type_Id = '" . InvoiceLineType::Reimburse . "'");
                }else{
                    $dbh->exec("insert ignore into `reservation_invoice_line` select '".$resv->getIdReservation()."', il.idInvoice_Line from invoice_line il where il.Invoice_Id = " .$this->payResult->getIdInvoice() . " and il.Item_Id = '" . ItemId::LodgingMOA . "'");
                }
            }

        }else{
            $this->payResult = NULL;
        }

    }

}