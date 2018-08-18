<?php

/**
 * Description of Reservation
 *
 * @author Eric
 */
class Reservation {


    /**
     *
     * @var ReserveData
     */
    protected $reserveData;
    protected $reservRs;
    protected $family;

    function __construct(ReserveData $reserveData, ReservationRS $reservRs, Family $family) {

        $this->reserveData = $reserveData;
        $this->reservRs = $reservRs;
        $this->family = $family;
    }

    public static function reservationFactoy(\PDO $dbh, $post) {

        $rData = new ReserveData($post);

        // idPsg < 0
        if ($rData->getForceNewPsg()) {

            // Force new PSG, also implies new reservation
            $rData->setIdResv(0);

            return new Reservation($rData, new ReservationRS(), new JoinNewFamily($dbh, $rData));
        }

        // idResv < 0
        if ($rData->getForceNewResv()) {

            if ($rData->getIdPsg() > 0) {
                // Force New Resv for existing PSG
                return new ActiveReservation($rData, new ReservationRS(), new Family($dbh, $rData));

            } else {

                throw new Hk_Exception_Runtime("Reservation parameters are invalid.  ");
            }
        }

        // Resv > 0
        if ($rData->getIdResv() > 0) {

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

                $family = new Family($dbh, $rData);
                return new ActiveReservation($rData, $rRs, $family);
            }

            if ($rRs->Status->getStoredVal() == ReservationStatus::Staying || $rRs->Status->getStoredVal() == ReservationStatus::Checkedout) {

                return new StayingReservation($rData, $rRs, new Family($dbh, $rData));

            }

            return new StaticReservation($rData, $rRs, new Family($dbh, $rData));
        }



        // idResv = 0 ------------------------------


        if ($rData->getIdPsg() > 0 || $rData->getId() > 0) {

            return new ReserveSearcher($rData, new ReservationRS(), new Family($dbh, $rData));
        }


        // idPsg = 0; idResv = 0; idGuest = 0
        return new Reservation($rData, new ReservationRS(), new Family($dbh, $rData));

    }

    public function createMarkup(\PDO $dbh) {

        $this->family->setGuestsStaying($dbh, $this->reserveData, $this->reservRs->idGuest->getstoredVal());

        // Arrival and Departure dates
        if ($this->reserveData->getIdResv() > 0) {
            try {
                $arrivalDT = new\DateTime($this->reservRs->Expected_Arrival->getStoredVal());
                $departDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());

                $psgMembers = $this->reserveData->getPsgMembers();

                $this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $arrivalDT, $departDT, $this->reserveData->getResvTitle());
                $this->findConflictingStays($dbh, $psgMembers, $arrivalDT, $this->reserveData->getIdPsg());

                $this->reserveData->setPsgMembers($psgMembers);

            } catch (Hk_Exception_Runtime $hex) {
                return array('error'=>$hex->getMessage());
            }
        }

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData));

        $data = $this->reserveData->toArray();

        // Resv Expected dates
        $data['expDates'] = $this->createExpDatesControl();

        // Hospital
        $hospitalStay = new HospitalStay($dbh, $this->family->getPatientId());

        $data['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);

        return $data;
    }

    public function save(\PDO $dbh, $post) {

        $newResv = new ActiveReservation($this->reserveData, $this->reservRs, $this->family);
        return $newResv->save($dbh, $post);

    }

    public function addPerson(\PDO $dbh) {

        $this->reserveData->setAddPerson($this->family->createAddPersonMu($dbh, $this->reserveData));
        return $this->reserveData->toArray();
    }

    public static function updateAgenda(\PDO $dbh, $post) {

        // decifer posts
        if (isset($post['dt1']) && isset($post['dt2']) && isset($post['mems'])) {

            $labels = new Config_Lite(LABEL_FILE);
            $psgMembers = array();

            $idPsg = intval(filter_var($post['idPsg'], FILTER_SANITIZE_NUMBER_INT), 10);
            $idResv = intval(filter_var($post['idResv'], FILTER_SANITIZE_NUMBER_INT), 10);
            $arrivalDT = new DateTime(filter_var($post['dt1'], FILTER_SANITIZE_STRING));
            $departDT = new DateTime(filter_var($post['dt2'], FILTER_SANITIZE_STRING));
            $postMems = filter_var_array($post['mems'], FILTER_SANITIZE_STRING);

            foreach ($postMems as $prefix => $memArray) {

                if ($prefix == '') {
                    continue;
                }

                $id = 0;
                $role = '';
                $stay = ReserveData::NOT_STAYING;
                $priGuest = 0;

                if (isset($memArray[ReserveData::ID])) {
                    $id = intval($memArray[ReserveData::ID], 10);
                }

                if (isset($memArray[ReserveData::ROLE])) {
                    $role = $memArray[ReserveData::ROLE];
                }

                if (isset($memArray[ReserveData::STAY])) {
                    $stay = $memArray[ReserveData::STAY];
                }

                if (isset($memArray[ReserveData::PRI])) {
                    $priGuest = intval($memArray[ReserveData::PRI], 10);
                }

                $psgMembers[$prefix] = new PSGMember($id, $prefix, $role, new PSGMemStay($stay, $priGuest));
            }

            // Create new stay controls for each member
            self::findConflictingReservations($dbh, $idPsg, $idResv, $psgMembers, $arrivalDT, $departDT, $labels->getString('guestEdit', 'reservationTitle', 'Reservation'));
            self::findConflictingStays($dbh, $psgMembers, $arrivalDT, $idPsg);

            $events = [];

            foreach ($psgMembers as $m) {

                $events[$m->getPrefix()] = $m->getStayObj()->createStayButton($m->getPrefix());
            }

            return array('stayCtrl'=>$events);

        }
    }

    protected function createExpDatesControl($prefix = '') {

        $uS = Session::getInstance();
        $cidAttr = array('name'=>$prefix.'gstDate', 'readonly'=>'readonly', 'size'=>'14' );
        $days = '';

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

            $days = $expDepDT->diff($expArrDT, TRUE)->days + 1;
        }

        $mkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', 'Arrival: '.
                    HTMLInput::generateMarkup(($this->reserveData->getArrivalDateStr()), $cidAttr))
                .HTMLContainer::generateMarkup('span', 'Expected Departure: '.
                    HTMLInput::generateMarkup(($this->reserveData->getDepartureDateStr()), array('name'=>$prefix.'gstCoDate', 'readonly'=>'readonly', 'size'=>'14'))
                    , array('style'=>'margin-left:.7em;'))
                .HTMLContainer::generateMarkup('span', 'Expected Days: '.
                    HTMLInput::generateMarkup($days, array('name'=>$prefix.'gstDays', 'readonly'=>'readonly', 'size'=>'4'))
                    , array('style'=>'margin-left:.7em;'))

                , array('style'=>'float:left;font-size:.9em;', 'id'=>$prefix.'spnRangePicker'));

        return array('mu'=>$mkup, 'defdays'=>$uS->DefaultDays, 'daysEle'=>$prefix.'gstDays');

    }

    protected function createResvMarkup(\PDO $dbh, $oldResv, $labels, $prefix = '', $isAuthorized = TRUE) {

        $uS = Session::getInstance();

        $resv = new Reservation_1($this->reservRs);
        $showPayWith = FALSE;

        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());

        if ($resv->isNew() === FALSE && $resv->isActive()) {

            // Allow reservations to have many guests.
            $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
            $roomChooser->setOldResvId($oldResv);

            $dataArray['rChooser'] = $roomChooser->CreateResvMarkup($dbh, $isAuthorized);

            // Rate Chooser
            if ($uS->RoomPriceModel != ItemPriceCode::None) {

                $showPayWith = TRUE;

                $rateChooser = new RateChooser($dbh);

                $dataArray['rate'] = $rateChooser->createResvMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'), $reg->getIdRegistration());
                // Array with amount calculated for each rate.
                $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));
                // Array with key deposit info
                $dataArray['rooms'] = $rateChooser->makeRoomsArray($roomChooser, $uS->guestLookups['Static_Room_Rate'], $uS->guestLookups[GL_TableNames::KeyDepositCode], 20);

                if ($uS->VisitFee) {
                    // Visit Fee Array
                    $dataArray['vfee'] = $rateChooser->makeVisitFeeArray($dbh, $resv->getVisitFee());
                }

        //            $dataArray['pay'] =
        //                    PaymentChooser::createResvMarkup($dbh, $guest->getIdName(), $reg, removeOptionGroups($uS->nameLookups[GL_TableNames::PayType]), $resv->getExpectedPayType(), $uS->ccgw);

            }

            // Vehicles
            if ($uS->TrackAuto) {
                $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
            }
        }

        if ($resv->isNew() || $resv->getStatus() == ReservationStatus::Staying || $resv->getStatus() == ReservationStatus::Checkedout) {

            $dataArray['rstat'] = '';

        } else {

            // Reservation Data
            $dataArray['rstat'] = ReservationSvcs::createStatusChooser(
                    $resv,
                    $resv->getChooserStatuses($uS->guestLookups['ReservStatus']),
                    $uS->nameLookups[GL_TableNames::PayType],
                    $labels,
                    $showPayWith,
                    Registration::loadLodgingBalance($dbh, $resv->getIdRegistration()));
        }

        // Reservation status title
        $dataArray['rStatTitle'] = $resv->getStatusTitle();

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'notesLabel', 'Reservation Notes'), array('style'=>'font-weight:bold;'))
                , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%;font-size:0.9em;', 'class'=>'hhk-panel'));


        $dataArray['wlnotes'] = '';

        // Waitlist notes?
        if ($uS->UseWLnotes && $resv->getStatus() == ReservationStatus::Waitlist) {

            $dataArray['wlnotes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $this->reserveData->getWlNotesLabel(), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $resv->getCheckinNotes(), array('name'=>'taCkinNotes', 'rows'=>'2', 'style'=>'width:100%')),
                array('class'=>'hhk-panel', 'style'=>'float:left; width:50%;'));
        }


        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', ($resv->isNew() ? 'New ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') : $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' - '))
                .HTMLContainer::generateMarkup('span', ($resv->isNew() ? '' : $resv->getStatusTitle()), array('id'=>$prefix.'spnResvStatus', 'style'=>'margin-right: 1em;'))
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


            // Get guest names
            $gstmt = $dbh->query("select n.Name_Full, rg.Primary_Guest
from reservation_guest rg join name n on rg.idGuest = n.idName
where rg.idReservation =" . $r['idReservation']);

            $names = '';
            $fst = TRUE;

            while ($g = $gstmt->fetch(PDO::FETCH_ASSOC)) {

                if ($fst) {
                    $name = $g['Name_Full'];
                    $fst = FALSE;
                } else {
                    $name = ', ' . $g['Name_Full'];
                }
                if ($g['Primary_Guest'] == 1) {
                    $names .= HTMLContainer::generateMarkup('span', $name, array('style'=>'font-weight:bold;', 'title'=>'Primary Guest'));
                } else {
                    $names .= HTMLContainer::generateMarkup('span', $name);
                }
            }

            $trs[] = HTMLTable::makeTd($checkinNow)
                    .HTMLTable::makeTd($reservStatuses[$resvRs->Status->getStoredVal()][1])
                    .HTMLTable::makeTd($r['Title'])
                    .HTMLTable::makeTd($r['Patient_Name'])
                    .HTMLTable::makeTd($expArrDT->format('M j, Y'))
                    .HTMLTable::makeTd(date('M j, Y', strtotime($resvRs->Expected_Departure->getStoredVal())))
                    .HTMLTable::makeTd($resvRs->Number_Guests->getStoredVal()
                    .HTMLTable::makeTd($names));
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
                    .HTMLTable::makeTh('#')
                    .HTMLTable::makeTh('Guests'));

            $mrkup .= $tbl->generateMarkup();

        }

        return $mrkup;
    }

    protected static function findConflictingStays(\PDO $dbh, array &$psgMembers, \DateTime $arrivalDT, $idPsg) {

        $whStays = '';
        $rooms = array();

        // Collect member ids
        foreach ($psgMembers as $m) {
            if ($m->getId() != 0 && $m->isBlocked() === FALSE) {
                $whStays .= ',' . $m->getId();
            }
        }

        // Find any visits.
        if ($whStays != '') {

            // Check ongoing visits
            $vstmt = $dbh->query("Select s.idName, s.idVisit, s.Visit_Span, s.idRoom, r.idPsg "
                    . " from stays s join visit v on s.idVisit = v.idVisit join registration r on v.idRegistration = r.idRegistration "
                    . " where s.`Status` = '" . VisitStatus::CheckedIn . "' and DATE(dateDefaultNow(s.Expected_Co_Date)) > DATE('" . $arrivalDT->format('Y-m-d') . "') "
                    . " and s.idName in (" . substr($whStays, 1) . ")");

            while ($s = $vstmt->fetch(\PDO::FETCH_ASSOC)) {
                // These guests are already staying

                foreach ($psgMembers as $m) {
                    if ($m->getId() == $s['idName']) {
                        $psgMembers[$m->getPrefix()]->setStayObj(new PSGMemVisit(array('idVisit'=>$s['idVisit'], 'Visit_Span'=>$s['Visit_Span'])));
                    }
                }

                // Count rooms
                if ($s['idPsg'] == $idPsg) {
                    $rooms[$s['idRoom']] = '1';
                }
            }
        }

        // Return number of rooms being used by this psg.
        return count($rooms);
    }

    protected static function findConflictingReservations(\PDO $dbh, $idPsg, $idResv, array &$psgMembers, \DateTime $arrivalDT, \DateTime $departDT, $resvTitle = 'Reservation') {

        // Check reservations
        $whResv = '';
        $rescs = array();

        foreach ($psgMembers as $m) {
            if ($m->getId() != 0 && $m->isBlocked() === FALSE) {
                $whResv .= ',' . $m->getId();
            }
        }

        if ($whResv != '') {

            $rStatus = " in ('" . ReservationStatus::Committed. "','" . ReservationStatus::UnCommitted. "','". ReservationStatus::Waitlist. "') ";

            $rstmt = $dbh->query("select rg.idReservation, reg.idPsg, rg.idGuest, r.idResource, r.`Status` "
                . "from reservation_guest rg  "
                . "join reservation r on r.idReservation = rg.idReservation "
                . "join registration reg on reg.idRegistration = r.idRegistration "
                . "where r.`Status` $rStatus and rg.idGuest in (" . substr($whResv, 1) . ") and rg.idReservation != " . $idResv
                . " and Date(r.Expected_Arrival) < DATE('".$departDT->format('Y-m-d') . "') and Date(r.Expected_Departure) > DATE('".$arrivalDT->format('Y-m-d') . "')");

            while ($r = $rstmt->fetch(\PDO::FETCH_ASSOC)) {

                foreach ($psgMembers as $m) {
                    if ($m->getId() == $r['idGuest'] && $r['Status'] != ReservationStatus::Staying) {
                        $psgMembers[$m->getPrefix()]->setStayObj(new PSGMemResv(array('idReservation'=>$r['idReservation'], 'idGuest'=>$r['idGuest'], 'idPsg'=>$r['idPsg'], 'label'=>$resvTitle)));
                    }
                }

                // Count rooms
                if ($r['idPsg'] == $idPsg) {
                    $rescs[$r['idResource']] = '1';
                }
            }
        }

        return count($rescs);
    }

    protected function getStayingMembers() {

        $stayMembers = array();
        foreach ($this->reserveData->getPsgMembers() as $m) {
            if ($m->isStaying()) {
                $stayMembers[$m->getId()] = $m;
            }
        }
        return $stayMembers;
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

            $vFees = $rateChooser->makeVisitFeeArray($dbh, $resv->getVisitFee());

            if (isset($vFees[$visitFeeOption])) {
                $resv->setVisitFee($vFees[$visitFeeOption][2]);
            } else {
                $resv->setVisitFee($vFees[$uS->DefaultVisitFee][2]);
            }

        } else if ($resv->isNew() && $uS->VisitFee) {

            $vFees = $rateChooser->makeVisitFeeArray($dbh);
            $resv->setVisitFee($vFees[$uS->DefaultVisitFee][2]);
        }

    }

    protected function setRoomChoice(\PDO $dbh, Reservation_1 &$resv, $idRescPosted) {

        $uS = Session::getInstance();

        $roomChooser = new RoomChooser($dbh, $resv, 1, new \DateTime($resv->getExpectedArrival()), new \DateTime($resv->getExpectedDeparture()));

        // Process reservation
        if ($resv->getStatus() == ReservationStatus::Pending || $resv->isActive()) {

            $roomChooser->findResources($dbh, SecurityComponent::is_Authorized("guestadmin"));

            ReservationSvcs::processReservation($dbh, $resv, $idRescPosted, $resv->getFixedRoomRate(), 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), SecurityComponent::is_Authorized("guestadmin"), $uS->username, $uS->InitResvStatus);

        }
    }

    protected function copyOldReservation(\PDO $dbh) {

        // Pick up the old ReservationRS, if it exists.
        // Use it to fill in the visit requirements.
        $oldResvId = 0;

        if ($this->reserveData->getIdResv() > 0 || $this->reserveData->getIdPsg() < 1) {
            return $oldResvId;
        }

        $stmt = $dbh->query("SELECT  r.idReservation, r.idGuest, MAX(r.Expected_Arrival) "
                . "FROM reservation r LEFT JOIN registration rg ON r.idRegistration = rg.idRegistration "
                . "WHERE rg.idPsg = " . $this->reserveData->getIdPsg() . " ORDER BY rg.idPsg");

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($rows > 0)) {

            $oldResvId = $rows[0][0];

            if ($this->reserveData->findPrimaryGuestId() === NULL) {

                $mem = $this->reserveData->findMemberById($rows[0][1]);

                if (is_null($mem) === FALSE) {
                    $mem->setPrimaryGuest(1);
                }
            }
        }

        return $oldResvId;
    }

    public function savePayments(\PDO $dbh, Reservation_1 &$resv, $post) {

        return;

    }

    public function saveReservationGuests(\PDO $dbh) {

        if ($this->reserveData->getIdResv() < 1) {
            return FALSE;
        }

        //
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setStoredVal($this->reserveData->getIdResv());
        $rgs = EditRS::select($dbh, $rgRs, array($rgRs->idReservation));

        // New Reservation
        if (count($rgs) == 0) {

            // Load staying members.
            foreach ($this->getStayingMembers() as $g) {

                $rgRs = new Reservation_GuestRS();
                $rgRs->idReservation->setNewVal($this->reserveData->getIdResv());
                $rgRs->idGuest->setNewVal($g->getId());
                $rgRs->Primary_Guest->setNewVal($g->getStayObj()->isPrimaryGuest() ? '1' : '');

                EditRS::insert($dbh, $rgRs);

            }

        } else {

            // Update who is staying or not.
            foreach ($this->reserveData->getPsgMembers() as $g) {

                $isListed = FALSE;

                foreach ($rgs as $r) {

                    if ($r['idGuest'] == $g->getId()) {
                        $isListed = TRUE;

                        // Still staying?
                        if ($g->isStaying() === FALSE) {
                            // Delete record
                            $dbh->exec("Delete from reservation_guest where idReservation = " . $this->reserveData->getIdResv() . " and idGuest = " . $g->getId());
                        }

                        // Is this the primary guest?
                        $priGuestFlag = '';
                        if ($g->isPrimaryGuest()) {
                            $priGuestFlag = '1';
                        }
                        $dbh->exec("update reservation_guest set Primary_Guest = '$priGuestFlag' where idReservation = " . $this->reserveData->getIdResv() . " and idGuest = " . $g->getId());

                        break;
                    }
                }

                if ($isListed === FALSE && $g->isStaying()) {

                    $rgRs = new Reservation_GuestRS();
                    $rgRs->idReservation->setNewVal($this->reserveData->getIdResv());
                    $rgRs->idGuest->setNewVal($g->getId());
                    $rgRs->Primary_Guest->setNewVal($g->getStayObj()->isPrimaryGuest() ? '1' : '');

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



class ActiveReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

        if ($this->reservRs->Status->getStoredVal() == '') {
            $this->reservRs->Status->setStoredVal(ReservationStatus::Waitlist);
        }

        // Get any previous settings and set primary guest if blank.
        $oldResvId = $this->copyOldReservation($dbh);

        // Add the family, hospital, etc sections.
        $data = parent::createMarkup($dbh);

        // Add the reservation section.
        $data['resv'] = $this->createResvMarkup($dbh, $oldResvId, new Config_Lite(LABEL_FILE));

        return $data;

    }

    public function save(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        // Save members, psg, hospital
        $this->family->save($dbh, $post, $this->reserveData);

        if (count($this->getStayingMembers()) < 1) {
            // Nobody set to stay
            $data['error'] = 'Nobody is set to stay for this ' . $this->reserveData->getResvTitle() . '.  ';
            return $data;
        }

        // Arrival and Departure dates
        try {
            $arrivalDT = new\DateTime();
            $departDT = new \DateTime();
            $this->saveDates($post, $arrivalDT, $departDT);
        } catch (Hk_Exception_Runtime $hex) {
            return array('error'=> 'Problem with ' . $this->reserveData->getResvTitle() . ' arrival and/or departure dates:  ' . $hex->getMessage());
        }


        // Is anyone already in a visit?
        $psgMems = $this->reserveData->getPsgMembers();
        self::findConflictingStays($dbh, $psgMems, $arrivalDT, $this->reserveData->getIdPsg());
        $this->reserveData->setPsgMembers($psgMems);

        if (count($this->getStayingMembers()) < 1) {

            // Nobody left in the reservation
            if ($this->reserveData->getId() == 0) {
                $this->reserveData->setId($this->family->getPatientId());
            }

            $data = $this->createMarkup($dbh);
            $data['error'] = 'Everybody is already staying at the house for all or part of the specified duration.  ';
            return $data;
        }

        // Get reservations for the specified time
        $psgMems2 = $this->reserveData->getPsgMembers();
        $numRooms = self::findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMems2, $arrivalDT, $departDT, $this->reserveData->getResvTitle());
        $this->reserveData->setPsgMembers($psgMems2);

        // Anybody left?
        if (count($this->getStayingMembers()) < 1) {

            // Nobody left in the reservation
            if ($this->reserveData->getId() == 0) {
                $this->reserveData->setId($this->family->getPatientId());
            }

            $data = $this->createMarkup($dbh);
            $data['error'] = 'Everybody is already in a reservation for all or part of the specified duration.  ';
            return $data;
        }

        // verify number of simultaneous reservations/visits
        if ($this->reserveData->getIdResv() == 0 && $numRooms > $uS->RoomsPerPatient) {
            // Too many
            $data['error'] = 'This reservation violates your House\'s maximum number of simutaneous rooms per patient (' .$uS->RoomsPerPatient . '.  ';
            return $data;
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

        $resv->setExpectedPayType($uS->DefaultPayType);
        $resv->setHospitalStay($this->family->getHospStay());
        $resv->setExpectedArrival($arrivalDT->format('Y-m-d 16:00:00'));
        $resv->setExpectedDeparture($departDT->format('Y-m-d 10:00:00'));
        $resv->setNumberGuests(count($this->getStayingMembers()));

        if (($idPriGuest = $this->reserveData->findPrimaryGuestId()) !== NULL) {
            $resv->setIdGuest($idPriGuest);
        }

        // Collect the room rates
        $this->setRoomRate($dbh, $reg, $resv, $post);

        // Notes
        if (isset($post['taNewNote']) && $post['taNewNote'] != '') {
            $resv->saveNote($dbh, filter_var($post['taNewNote'], FILTER_SANITIZE_STRING), $uS->username);
        }

        // Payment Type
        if (isset($post['selPayType'])) {
            $resv->setExpectedPayType(filter_var($post['selPayType'], FILTER_SANITIZE_STRING));
        }

        // Verbal Confirmation Flag
        if (isset($post['cbVerbalConf']) && $resv->getVerbalConfirm() != 'v') {
            $resv->setVerbalConfirm('v');
            $resv->saveNote($dbh, 'Verbal confirmation set', $uS->username);
        } else {
            $resv->setVerbalConfirm('');
        }

        // Check-in notes (to be put on the registration form. ALternatively, use as waitlist notes.
        if (isset($post['taCkinNotes'])) {
            $resv->setCheckinNotes(filter_var($post['taCkinNotes'], FILTER_SANITIZE_STRING));
        }


        // Determine Reservation Status
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

        // Room number chosen
        $idRescPosted = 0;
        if (isset($post['selResource'])) {
            $idRescPosted = intval(filter_Var($post['selResource'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        // Switch to waitlist status if room is 0
        if ($resv->isActionStatus($reservStatus) && $idRescPosted == 0) {
            $resv->setStatus(ReservationStatus::Waitlist);
        }

        // Save reservation
        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);

        $this->reserveData->setIdResv($resv->getIdReservation());

        $this->saveReservationGuests($dbh);
        $resv->saveConstraints($dbh, $post);

        // Save Notes
        if (isset($post['taNewNote']) && $post['taNewNote'] != '') {
            $resv->saveNote($dbh, filter_var($post['taNewNote'], FILTER_SANITIZE_STRING), $uS->username);
        }


        // Room Chooser
        $this->setRoomChoice($dbh, $resv, $idRescPosted);

        // Payments
        $this->savePayments($dbh, $resv, $post);

        // Reply
        $newResv = new ActiveReservation($this->reserveData, $resv->getReservationRS(), $this->family);
        return $newResv->createMarkup($dbh);

    }
}


class ReserveSearcher extends ActiveReservation {

    public function createMarkup(\PDO $dbh) {

        $data = $this->resvChooserMarkup($dbh);

        if (is_array($data)) {
            return $data;
        }

        return $this->reserveData->toArray();

    }

    public function addPerson(\PDO $dbh) {

        if ($this->reserveData->getId() > 0) {

            // patient?
            $stmt = $dbh->query("select count(*) from psg where idPatient = " . $this->reserveData->getId());
            $rows = $stmt->fetchAll();
            if ($rows[0][0] > 0) {
                return $this->createMarkup($dbh);
            }
        }

        return parent::addPerson($dbh);

    }

    protected function resvChooserMarkup(\PDO $dbh) {
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



class StaticReservation extends ActiveReservation {

    public function addPerson(\PDO $dbh) {
        return array();
    }
}


class StayingReservation extends ActiveReservation {

    public function save(\PDO $dbh, $post) {
        return array('error'=>'Saving is not allowed.');
    }

    public function addPerson(\PDO $dbh) {
        return array('error'=>'Adding a person is not allowed.');
    }

}
