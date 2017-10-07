<?php

/**
 * Description of Reservation
 *
 * @author Eric
 */
abstract class Reservation {


    /**
     *
     * @var ReserveData
     */
    protected $reserveData;
    protected $reservRs;

    function __construct(ReserveData $reserveData, ReservationRS $reservRs) {

        $this->reserveData = $reserveData;
        $this->reservRs = $reservRs;
    }

    public static function reservationFactoy(\PDO $dbh, $post) {

        $rData = new ReserveData($post);

        // idPsg < 0
        if ($rData->getForceNewPsg()) {

            // Force new PSG, also implies new reservation
            $rData->setIdResv(0);

            return new BlankReservation($rData, new ReservationRS());

        // idResv < 0
        } else if ($rData->getForceNewResv() && $rData->getIdPsg() > 0) {

            // Force New Resv for existing PSG
            return new ActiveReservation($rData, new ReservationRS());

        // undetermined resv and psg, look at guest id
        } else if ($rData->getIdResv() == 0 && $rData->getIdPsg() == 0) {

            // Depends on GUest Id
            if ($rData->getId() > 0) {
                // Search
                return new ReserveSearcher($rData, new ReservationRS());
            }

            // New resv, new psg, new guest
            return new BlankReservation($rData, new ReservationRS());


        // Guest, PSG, no reservation specified.
        } else if ($rData->getIdPsg() > 0 && $rData->getIdResv() == 0) {

            return new ReserveSearcher($rData, new ReservationRS());

        // Got a defined resv.
        } else if ($rData->getIdResv() > 0) {

            // Load reservation
            $stmt = $dbh->query("Select r.*, rg.idPsg from reservation r left join registration rg on r.idRegistration = rg.idRegistration where r.idReservation = " . $rData->getIdResv());
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) != 1) {
                throw new Hk_Exception_Runtime("Reservation Id not found.  ");
            }

            $rRs = new ReservationRS();
            EditRS::loadRow($rows[0], $rRs);

            $rData->setIdPsg($rows[0]['idPsg']);

            if (Reservation_1::isActiveStatus($rRs->Status->getStoredVal())) {
                return new ActiveReservation($rData, $rRs);
            }

            if ($rRs->Status->getStoredVal() == ReservationStatus::Staying) {
                return new StayingReservation($rData, $rRs);
            }

            return new StaticReservation($rData, $rRs);
        }

        // invalid parameters
        throw new Hk_Exception_Runtime("Reservation parameters are invalid.  ");

    }

    protected function notesMarkup($notes) {

        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', $this->reserveData->getNotesLabel())
                    . Notes::markupShell($notes, 'txtRnotes'),
                    array('class'=>'hhk-panel')));

    }

    public abstract function createMarkup(\PDO $dbh);

    public function save(\PDO $dbh, $post) {

        return array();
    }

    public function addperson(\PDO $dbh) {

        return array();
    }

    protected function createExpDatesControl($prefix = '') {

        $cidAttr = array('name'=>$prefix.'gstDate', 'readonly'=>'readonly', 'size'=>'14' );

        if ($this->reservRs->Expected_Arrival->getStoredVal() != '' && $this->reservRs->Expected_Departure->getStoredVal() != '') {

            $nowDT = new \DateTime();
            $nowDT->setTime(0, 0, 0);

            $expArrDT = new \DateTime($this->reservRs->Expected_Arrival->getStoredVal());
            $expDepDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());

            $this->reserveData
                    ->setArrivalDateStr($expArrDT->format('M j, Y'))
                    ->setDepartureDateStr($expDepDT->format('M j, Y'));

            if (is_null($expArrDT) === FALSE && $expArrDT < $nowDT) {
                $cidAttr['class'] = ' ui-state-highlight';
            }
        }

        return HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', 'Arrival: '.
                    HTMLInput::generateMarkup(($this->reserveData->getArrivalDateStr()), $cidAttr))
                .HTMLContainer::generateMarkup('span', 'Expected Departure: '.
                    HTMLInput::generateMarkup(($this->reserveData->getDepartureDateStr()), array('name'=>$prefix.'gstCoDate', 'readonly'=>'readonly', 'size'=>'14'))
                    , array('style'=>'margin-left:.7em;'))
                , array('style'=>'float:left; font-size:.9em;', 'id'=>$prefix.'spnRangePicker'));

    }

    protected function createResvMarkup(\PDO $dbh, $labels, $prefix = '', $isAuthorized = TRUE) {

        $uS = Session::getInstance();

        $resv = new Reservation_1($this->reservRs);

        // Count guests
        $numGuests = 0;
        foreach($this->reserveData->getPsgMembers() as $m) {
            if ($m->getStay() == '1') {
                $numGuests++;
            }
        }

        $roomChooser = new RoomChooser($dbh, $resv, $numGuests, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
        //$roomChooser->setOldResvId($oldResvId);
        $dataArray['rChooser'] = $roomChooser->CreateResvMarkup($dbh, $isAuthorized);

        $showPayWith = TRUE;

        // Rate Chooser
        if ($uS->RoomPriceModel != ItemPriceCode::None) {

            $rateChooser = new RateChooser($dbh);

            $dataArray['rate'] = $rateChooser->createResvMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));
            // Array with amount calculated for each rate.
            $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));
            // Array with key deposit info
            $dataArray['rooms'] = $rateChooser->makeRoomsArray($roomChooser, $uS->guestLookups['Static_Room_Rate'], $uS->guestLookups[GL_TableNames::KeyDepositCode]);

            if ($uS->VisitFee) {
                // Visit Fee Array
                $dataArray['vfee'] = $rateChooser::makeVisitFeeArray($dbh);
            }

//            $dataArray['pay'] =
//                    PaymentChooser::createResvMarkup($dbh, $guest->getIdName(), $reg, removeOptionGroups($uS->nameLookups[GL_TableNames::PayType]), $resv->getExpectedPayType(), $uS->ccgw);

        } else {
            // Price Model - NONE
            $showPayWith = FALSE;
        }


        // Reservation Data
        $dataArray['rstat'] = ReservationSvcs::createStatusChooser(
                $resv,
                $resv->getChooserStatuses($uS->guestLookups['ReservStatus']),
                $uS->nameLookups[GL_TableNames::PayType],
                $labels,
                $showPayWith,
                Registration::loadLodgingBalance($dbh, $resv->getIdRegistration()));

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'notesLabel', 'Reservation Notes'), array('style'=>'font-weight:bold;'))
                        . Notes::markupShell($resv->getNotes(), 'txtRnotes'), array('style'=>'float:left; width:50%;', 'class'=>'hhk-panel'));

        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
        }

        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', ($resv->isNew() ? 'New ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') : $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' - '))
                .HTMLContainer::generateMarkup('span', ($resv->isNew() ? '' : $resv->getStatusTitle()), array('id'=>$prefix.'spnResvStatus', 'style'=>'margin-right: 1em;'))
//                .$this->createExpDatesControl()
                , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    protected function vehicleMarkup(\PDO $dbh) {

        $regId = $this->reservRs->idRegistration->getStoredVal();

        $reg = new Registration($dbh, 0, $regId);

        $noVeh = $reg->getNoVehicle();

        if ($reg->isNew()) {
            $noVeh = '1';
        }

        return Vehicle::createVehicleMarkup($dbh, $reg->getIdRegistration(), $noVeh);

    }

    protected function reservationChooser(\PDO $dbh, $idResv = 0) {

        $uS = Session::getInstance();

        $reservStatuses = $uS->guestLookups['ReservStatus'];

        $mrkup = '';

        $stmt = $dbh->query("select * from vresv_patient "
            . "where Status in ('".ReservationStatus::Staying."','".ReservationStatus::Committed."','".ReservationStatus::Imediate."','".ReservationStatus::UnCommitted."','".ReservationStatus::Waitlist."') "
            . "and idPsg= " . $this->reserveData->getIdPsg() . " order by `Expected_Arrival`");


        $trs = array();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($idResv != 0 && $idResv == $r['idReservation']) {
                continue;
            }

            $resvRs = new ReservationRS();
            EditRS::loadRow($r, $resvRs);

            $checkinNow = HTMLContainer::generateMarkup('a',
                        HTMLInput::generateMarkup('Open ' . $this->reserveData->getResvTitle(), array('type'=>'button', 'style'=>'margin-bottom:.3em;'))
                        , array('style'=>'text-decoration:none;margin-right:.3em;', 'href'=>'Reserve.php?idPsg='.$r['idPsg'] . '&rid='.$resvRs->idReservation->getStoredVal().'&id='.$this->reserveData->getId()));

            $expArrDT = new \DateTime($resvRs->Expected_Arrival->getStoredVal());
            $expArrDT->setTime(0, 0, 0);

            if ($resvRs->Status->getStoredVal() == ReservationStatus::Staying) {
                $checkinNow = HTMLInput::generateMarkup('Add Guest', array('type'=>'button', 'class'=>'hhk-checkinNow', 'data-rid'=>$resvRs->idReservation->getStoredVal()));
            } else if ($expArrDT->diff($today, TRUE)->days == 0) {
                $checkinNow .= HTMLInput::generateMarkup('Check-in Now', array('type'=>'button', 'class'=>'hhk-checkinNow', 'data-rid'=>$resvRs->idReservation->getStoredVal()));
            } else if ($expArrDT->diff($today, TRUE)->days <= $this->reserveData->getResvEarlyArrDays()) {
                $checkinNow .= HTMLInput::generateMarkup('Check-in Early', array('type'=>'button', 'class'=>'hhk-checkinNow', 'data-rid'=>$resvRs->idReservation->getStoredVal()));
            }


            $trs[] = HTMLTable::makeTd($checkinNow)
                    .HTMLTable::makeTd($reservStatuses[$resvRs->Status->getStoredVal()][1])
                    .HTMLTable::makeTd($r['Title'])
                    .HTMLTable::makeTd($r['Patient_Name'])
                    .HTMLTable::makeTd($expArrDT->format('M j, Y'))
                    .HTMLTable::makeTd(date('M j, Y', strtotime($resvRs->Expected_Departure->getStoredVal())))
                    .HTMLTable::makeTd($resvRs->Number_Guests->getStoredVal());
        }


        if (count($trs) > 0) {

            // Caught some
            $tbl = new HTMLTable();
            foreach ($trs as $tr) {
                $tbl->addBodyTr($tr);
            }

            $tbl->addHeaderTr(
                    HTMLTable::makeTh('')
                    .HTMLTable::makeTh('Status')
                    .HTMLTable::makeTh('Room')
                    .HTMLTable::makeTh($this->reserveData->getPatLabel())
                    .HTMLTable::makeTh('Expected Arrival')
                    .HTMLTable::makeTh('Expected Departure')
                    .HTMLTable::makeTh('# Guests'));

            $mrkup .= $tbl->generateMarkup();

        }

        return $mrkup;
    }

    protected function setRoomRate(\PDO $dbh, Registration $reg, Reservation_1 &$resv, array $post) {

        $uS = Session::getInstance();

        // Room Rate
        $rateChooser = new RateChooser($dbh);

        // Default Room Rate category
        if ($uS->RoomPriceModel == ItemPriceCode::Basic) {
            $rateCategory = RoomRateCategorys::Fixed_Rate_Category;
        } else if ($uS->RoomRateDefault != '') {
            $rateCategory = $uS->RoomRateDefault;
        } else {
            $rateCategory = Default_Settings::Rate_Category;
        }


        // Get the rate category
        if (isset($post['selRateCategory']) && (SecurityComponent::is_Authorized("guestadmin") || $uS->RateChangeAuth === FALSE)) {

            $rateCat = filter_var($post['selRateCategory'], FILTER_SANITIZE_STRING);

            if ($rateChooser->validateCategory($rateCat) === TRUE) {
                $rateCategory = $rateCat;
            }

        } else {
            // Look for an approved rate
            if ($reg->getIdRegistration() > 0 && $uS->IncomeRated) {

                $fin = new FinAssistance($dbh, $reg->getIdRegistration());

                if ($fin->hasApplied() && $fin->getFaCategory() != '') {
                    $rateCategory = $fin->getFaCategory();
                }
            }
        }

        // Only assign the rate id if the category changes
        if ($resv->getRoomRateCategory() != $rateCategory) {
            $rateRs = $rateChooser->getPriceModel()->getCategoryRateRs(0, $rateCategory);
            $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());
        }

        $resv->setRoomRateCategory($rateCategory);

        // Fixed Rate and Rate Adjust Amount
        if ($rateCategory == RoomRateCategorys::Fixed_Rate_Category) {

            // Check for rate setting amount.
            if (isset($post['txtFixedRate']) && (SecurityComponent::is_Authorized("guestadmin") || $uS->RateChangeAuth === FALSE)) {

                if ($post['txtFixedRate'] === '0' ||$post['txtFixedRate'] === '') {
                    $fixedRate = 0;
                } else {
                    $fixedRate = floatval(filter_var($post['txtFixedRate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                }

                if ($fixedRate < 0) {
                    $fixedRate = 0;
                }

                $resv->setFixedRoomRate($fixedRate);
                $resv->setRateAdjust(0);
            }

        } else if (isset($post['txtadjAmount']) && (SecurityComponent::is_Authorized("guestadmin") || $uS->RateChangeAuth === FALSE)) {

            // Save rate adjustment
            if ($post['txtadjAmount'] === '0' || $post['txtadjAmount'] === '') {
                $rateAdjust = 0;
            } else {
                $rateAdjust = floatval(filter_var($post['txtadjAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            }

            $resv->setRateAdjust($rateAdjust);

        }

        if (isset($post['selVisitFee']) && $uS->VisitFee) {

            $visitFeeOption = filter_var($post['selVisitFee'], FILTER_SANITIZE_STRING);

            $vFees = RateChooser::makeVisitFeeArray($dbh);

            if (isset($vFees[$visitFeeOption])) {
                $resv->setVisitFee($vFees[$visitFeeOption][2]);
            } else {
                $resv->setVisitFee($vFees[$uS->DefaultVisitFee][2]);
            }

        } else if ($resv->isNew() && $uS->VisitFee) {

            $vFees = RateChooser::makeVisitFeeArray($dbh);
            $resv->setVisitFee($vFees[$uS->DefaultVisitFee][2]);
        }

    }

    protected function setRoomChoice(\PDO $dbh, Reservation_1 &$resv, $idRescPosted) {

        $uS = Session::getInstance();

        $roomChooser = new RoomChooser($dbh, $resv, $resv->getNumberGuests(), new \DateTime($resv->getExpectedArrival()), new \DateTime($resv->getExpectedDeparture()));

        // Process reservation
        if ($resv->getStatus() == ReservationStatus::Pending || $resv->isActive()) {

            $roomChooser->findResources($dbh, SecurityComponent::is_Authorized("guestadmin"));

            ReservationSvcs::processReservation($dbh, $resv, $idRescPosted, $resv->getFixedRoomRate(), $resv->getNumberGuests(), $resv->getExpectedArrival(), $resv->getExpectedDeparture(), SecurityComponent::is_Authorized("guestadmin"), $uS->username, $uS->InitResvStatus);

        }
    }

    public function saveReservationGuests(\PDO $dbh) {

        if ($this->reserveData->getIdResv() < 1) {
            return FALSE;
        }

        // Save reservation-guest
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setStoredVal($this->reserveData->getIdResv());

        $rgs = EditRS::select($dbh, $rgRs, array($rgRs->idReservation));


        if (count($rgs) == 0) {
            // Load staying members.
            foreach ($this->reserveData->getPsgMembers() as $g) {

                if ($g->getStay() == '1') {

                    $rgRs = new Reservation_GuestRS();
                    $rgRs->idReservation->setNewVal($this->reserveData->getIdResv());
                    $rgRs->idGuest->setNewVal($g->getId());

                    EditRS::insert($dbh, $rgRs);
                }
            }

        } else {

            // Update who is staying or not.
            foreach ($this->reserveData->getPsgMembers() as $g) {

                $isListed = FALSE;

                foreach ($rgs as $r) {

                    if ($r['idGuest'] == $g->getId()) {
                        $isListed = TRUE;

                        if ($g->getStay() != '1') {
                            // Delete record
                            $dbh->exec("Delete from reservation_guest where idReservation = " . $this->reserveData->getIdResv() . " and idGuest = " . $g->getId());
                        }
                        break;
                    }
                }

                if ($isListed === FALSE && $g->getStay() == '1') {

                    $rgRs = new Reservation_GuestRS();
                    $rgRs->idReservation->setNewVal($this->reserveData->getIdResv());
                    $rgRs->idGuest->setNewVal($g->getId());

                    EditRS::insert($dbh, $rgRs);
                }
            }
        }

        return TRUE;
    }

    public function saveDates($post, &$arrivalDT, &$departDT) {

        // Arrival and Departure dates
        $departure = '';
        $arrival = '';

        if (isset($post['gstDate'])) {
            $arrival = filter_var($post['gstDate'], FILTER_SANITIZE_STRING);
        }
        if (isset($post['gstCoDate'])) {
            $departure = filter_var($post['gstCoDate'], FILTER_SANITIZE_STRING);
        }

        if ($arrival == '' || $departure == '') {
            throw new Hk_Exception_Runtime('Reservation dates not set.  ');
        }

        try {
            $arrivalDT = new\DateTime($arrival);
            $departDT = new \DateTime($departure);
        } catch (Exception $ex) {
            throw new Hk_Exception_Runtime('Something is wrong with one of the dates: ' . $ex->getMessage());
        }

    }
}


class ActiveReservation extends BlankReservation {

    public function createMarkup(\PDO $dbh) {

        if ($this->reservRs->Status->getStoredVal() == '') {
            $this->reservRs->Status->setStoredVal(ReservationStatus::Waitlist);
        }

        $data = parent::createMarkup($dbh);

        // Add the reservation section.
        $data['resv'] = $this->createResvMarkup($dbh, new Config_Lite(LABEL_FILE));

        return $data;

    }

    public function save(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $family = new Family($this->reserveData);

        // Save members, psg, hospital
        $this->reserveData = $family->save($dbh, $post);


        // Arrival and Departure dates
        try {
            $arrivalDT = new\DateTime();
            $departDT = new \DateTime();
            $this->saveDates($post, $arrivalDT, $departDT);
        } catch (Hk_Exception_Runtime $hex) {
            return array('error'=>$hex->getMessage());
        }


        // Is anyone already in a visit?


        // Check each guest for existing reservations
        if (($mk = $this->reservationChooser($dbh, $this->reserveData->getIdResv())) !== '') {
            $this->reserveData->setResvChooser($mk);
            return $this->reserveData->toArray();
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


        // Create the reservation instance
        $resv = new Reservation_1($this->reservRs);

        $resv->setHospitalStay($family->getHospStay());
        $resv->setExpectedArrival($arrivalDT->format('Y-m-d 16:00:00'));
        $resv->setExpectedDeparture($departDT->format('Y-m-d 10:00:00'));

        // Collect the room rates
        $this->setRoomRate($dbh, $reg, $resv, $post);

        // Notes
        if (isset($post['txtRnotes'])) {
            $resv->setNotes(filter_var($post['txtRnotes'], FILTER_SANITIZE_STRING), $uS->username);
        }

        // Payment Type
        if (isset($post['selPayType'])) {
            $resv->setExpectedPayType(filter_var($post['selPayType'], FILTER_SANITIZE_STRING));
        }

        // Verbal Confirmation Flag
        if (isset($post['cbVerbalConf']) && $resv->getVerbalConfirm() != 'v') {
            $resv->setVerbalConfirm('v');
            $resv->setNotes('Verbal confirmation set', $uS->username);
        } else {
            $resv->setVerbalConfirm('');
        }

        // Check-in notes (to be put on the registration form. ALternatively, use as waitlist notes.
        if (isset($post['taCkinNotes'])) {
            $resv->setCheckinNotes(filter_var($post['taCkinNotes'], FILTER_SANITIZE_STRING));
        }

        // Room number chosen
        $idRescPosted = 0;
        if (isset($post['selResource'])) {
            $idRescPosted = intval(filter_Var($post['selResource'], FILTER_SANITIZE_NUMBER_INT), 10);
        }


        // DetermineReservation Status
        $reservStatus = ReservationStatus::Waitlist;

        if (isset($post['selResvStatus'])) {

            $rStat = filter_var($post['selResvStatus'], FILTER_SANITIZE_STRING);

            if ($rStat != '') {
                $reservStatus = $rStat;
            }

        } else if ($resv->isNew() === FALSE && $resv->getStatus() != '') {
            $reservStatus = $resv->getStatus();
        }

        // Set reservation status
        $resv->setStatus($reservStatus);

        // remove room if reservation is in waitlist
        if ($reservStatus == ReservationStatus::Waitlist) {
            $resv->setIdResource(0);
        }

        // Switch to waitlist status if room is 0
        if ($resv->isActionStatus($reservStatus) && $idRescPosted == 0) {
            $resv->setStatus(ReservationStatus::Waitlist);
        }

        // Save reservation
        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);
        $this->saveReservationGuests($dbh);
        $resv->saveConstraints($dbh, $post);

        // Room Chooser


        // Payments



    }

}

class StaticReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

    }


}

class StayingReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

    }


}

class BlankReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

        $family = new Family($this->reserveData);

        $family->initMembers($dbh);
        $this->reserveData->setFamilySection($family->createFamilyMarkup($dbh, $this->reservRs));

        $data = $this->reserveData->toArray();

        // Resv Expected dates
        $data['expDates'] = $this->createExpDatesControl();

        // Hospital
        $hospitalStay = new HospitalStay($dbh, $family->getPatientId());

        $data['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);

        return $data;
    }

    public function save(\PDO $dbh, $post) {


        $family = new Family($this->reserveData);

        $this->reserveData = $family->save($dbh, $post);

        $newResv = new ActiveReservation($this->reserveData, $this->reservRs);

        return $newResv->createMarkup($dbh);

    }

    public function addperson(\PDO $dbh) {

        $family = new Family($this->reserveData);
        $family->initMembers($dbh);

        return array('addPerson' => $family->CreateAddPersonMu($dbh));
    }

}

class ReserveSearcher extends ActiveReservation {

    public function createMarkup(\PDO $dbh) {

        $ngRss = array();

        // Search for a PSG
        if ($this->reserveData->getIdPsg() == 0) {
            // idPsg not set

            // Does this guest have a PSG?
            $ngRss = Psg::getNameGuests($dbh, $this->reserveData->getId());

            $this->reserveData->setPsgChooser($this->psgChooserMkup($dbh, $ngRss));

            if (count($ngRss) == 1) {
                // Add a reservation chooser
                $ngRs = $ngRss[0];
                $this->reserveData->setIdPsg($ngRs->idPsg->getStoredVal());
                $this->reserveData->setResvChooser($this->reservationChooser($dbh));
            }

        } else {
            // idPsg is set

            if (($mk = $this->reservationChooser($dbh)) === '') {
                // No reservations, set up for new reservation.
                return parent::createMarkup($dbh);
            }

            $this->reserveData->setResvChooser($mk);
        }

        return $this->reserveData->toArray();

    }


    protected function psgChooserMkup(\PDO $dbh, array $ngRss, $offerNew = TRUE) {

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Who is the ' . $this->reserveData->getPatLabel() . '?', array('colspan'=>'2')));

        $firstOne = TRUE;

        foreach ($ngRss as $n) {

            $psg = new Psg($dbh, $n->idPsg->getStoredVal());

            $attrs = array('type'=>'radio', 'value'=>$psg->getIdPsg(), 'name'=>'cbselpsg', 'id'=>$psg->getIdPsg().'cbselpsg');
            if ($firstOne) {
                $attrs['checked'] = 'checked';
                $firstOne = FALSE;
            }

            $tbl->addBodyTr(
                    HTMLTable::makeTd(HTMLContainer::generateMarkup('label', $psg->getPatientName($dbh), array('for'=>$psg->getIdPsg().'cbselpsg')), array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup('', $attrs)));

        }

        // Add new PSG choice
        if ($offerNew) {
            $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'New ' . $this->reserveData->getPatLabel(), array('for'=>'1_cbselpsg')), array('class'=>'tdlabel'))
               .HTMLTable::makeTd(HTMLInput::generateMarkup('-1', array('type'=>'radio', 'name'=>'cbselpsg', 'id'=>'1_cbselpsg', 'data-pid'=>'0', 'data-ngid'=>'0'))));
        }


        return $tbl->generateMarkup();
    }

}


