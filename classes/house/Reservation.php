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
            return self::loadReservation($dbh, $rData, $post);
        }



        // idResv = 0 ------------------------------


        if ($rData->getIdPsg() > 0 || $rData->getId() > 0) {

            return new ReserveSearcher($rData, new ReservationRS(), new Family($dbh, $rData));
        }


        // idPsg = 0; idResv = 0; idGuest = 0
        return new Reservation($rData, new ReservationRS(), new Family($dbh, $rData));

    }

    public static function loadReservation(\PDO $dbh, ReserveData $rData) {

        // Load reservation
        $stmt = $dbh->query("SELECT r.*, rg.idPsg, ifnull(v.idVisit, 0) as idVisit
FROM reservation r
        LEFT JOIN
    registration rg ON r.idRegistration = rg.idRegistration
	LEFT JOIN
    visit v on v.idReservation = r.idReservation and v.Span = 0
WHERE r.idReservation = " . $rData->getIdResv());

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) != 1) {
            throw new Hk_Exception_Runtime("Reservation Id not found.  ");
        }

        $rRs = new ReservationRS();
        EditRS::loadRow($rows[0], $rRs);

        $rData->setIdPsg($rows[0]['idPsg']);
        $rData->setIdVisit($rows[0]['idVisit']);

        if (Reservation_1::isActiveStatus($rRs->Status->getStoredVal())) {
            return new ActiveReservation($rData, $rRs, new Family($dbh, $rData));
        }

        if ($rRs->Status->getStoredVal() == ReservationStatus::Staying) {
            return new StayingReservation($rData, $rRs, new FamilyAddGuest($dbh, $rData, TRUE));
        }

        if ($rRs->Status->getStoredVal() == ReservationStatus::Checkedout) {
            return new CheckedoutReservation($rData, $rRs, new Family($dbh, $rData));
        }

        return new StaticReservation($rData, $rRs, new Family($dbh, $rData));

    }

    /**
     * Verify visit input dates
     *
     * @param \Reservation_1 $resv
     * @param \DateTime $chkinDT
     * @param \DateTime $chkoutDT
     * @param bool $autoResv
     * @throws Hk_Exception_Runtime
     */
    public static function verifyVisitDates(\Reservation_1 $resv, \DateTime $chkinDT, \DateTime $chkoutDT, $autoResv = FALSE) {

        $uS = Session::getInstance();

        $rCkinDT = new \DateTime(($resv->getActualArrival() == '' ? $resv->getExpectedArrival() : $resv->getActualArrival()));
        $rCkinDT->setTime(0, 0, 0);
        $rCkoutDT = new \DateTime($resv->getExpectedDeparture());
        $rCkoutDT->setTime(23, 59, 59);

        if ($resv->getStatus() == ReservationStatus::Committed && $rCkinDT->diff($chkinDT)->days > $uS->ResvEarlyArrDays) {
            throw new Hk_Exception_Runtime('Cannot check-in earlier than ' . $uS->ResvEarlyArrDays . ' days of the reservation expected arrival date of ' . $rCkinDT->format('M d, Y'));
        } else if ($resv->getStatus() == ReservationStatus::Committed && $chkoutDT > $rCkoutDT && $autoResv === FALSE) {
            throw new Hk_Exception_Runtime('Cannot check-out later than the reservation expected departure date of ' . $rCkoutDT->format('M d, Y'));
        }

        if ($chkinDT >= $chkoutDT) {
            throw new Hk_Exception_Runtime('A check-in date cannot be AFTER the check-out date.  (Silly Human)  ');
        }
    }

    public function createMarkup(\PDO $dbh) {

        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh);
        $this->createFamilyMarkup($dbh);

        return $this->reserveData->toArray();

    }

    protected function createFamilyMarkup(\PDO $dbh, $arrivalDT = NULL, $departDT = NULL) {

        $this->family->setGuestsStaying($dbh, $this->reserveData, $this->reservRs->idGuest->getstoredVal());

        // Arrival and Departure dates
        if ($this->reserveData->getIdResv() > 0) {

            try {
                if ($arrivalDT == NULL) {
                    $arrivalDT = new\DateTime($this->reservRs->Expected_Arrival->getStoredVal());
                }

                if ($departDT == NULL) {
                    $departDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());
                }

                $psgMembers = $this->reserveData->getPsgMembers();

                $this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $arrivalDT, $departDT, $this->reserveData->getResvTitle());
                $this->findConflictingStays($dbh, $psgMembers, $arrivalDT, $this->reserveData->getIdPsg());

                $this->reserveData->setPsgMembers($psgMembers);

            } catch (Hk_Exception_Runtime $hex) {
                return array('error'=>$hex->getMessage());
            }
        }

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData));

    }

    protected function createDatesMarkup() {

        if ($this->reservRs->Expected_Arrival->getStoredVal() != '' && $this->reservRs->Expected_Departure->getStoredVal() != '') {

            $expArrDT = new \DateTime($this->reservRs->Expected_Arrival->getStoredVal());
            $expDepDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());

            $this->reserveData
                    ->setArrivalDT($expArrDT)
                    ->setDepartureDT($expDepDT);

        }


        // Resv Expected dates
        $this->reserveData->setExpectedDatesSection($this->createExpDatesControl());

    }

    protected function createHospitalMarkup(\PDO $dbh) {

        // Hospital
        $hospitalStay = new HospitalStay($dbh, $this->family->getPatientId());

        $this->reserveData->setHospitalSection(Hospital::createReferralMarkup($dbh, $hospitalStay));

    }

    protected function initialSave(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        // Save members, psg, hospital
        $this->family->save($dbh, $post, $this->reserveData, $uS->username);

        if (count($this->getStayingMembers()) < 1) {
            // Nobody set to stay
            $this->reserveData->addError('Nobody is set to stay for this ' . $this->reserveData->getResvTitle() . '.  ');
            return;
        }

        // Arrival and Departure dates
        try {
            $this->setDates($post);
        } catch (Hk_Exception_Runtime $hex) {
            $this->reserveData->addError($hex->getMessage());
            return;
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

            $this->reserveData->addError('Everybody is already staying at the house for all or part of the specified duration.  ');
            return;
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

            $this->reserveData->addError('Everybody is already in a reservation for all or part of the specified duration.  ');
            return;
        }

        // verify number of simultaneous reservations/visits
        if ($this->reserveData->getIdResv() == 0 && $numRooms > $uS->RoomsPerPatient) {
            // Too many
            $this->reserveData->addError('This reservation violates your House\'s maximum number of simutaneous rooms per patient (' .$uS->RoomsPerPatient . '.  ');
            return;
        }
    }

    public function save(\PDO $dbh, $post) {

        $newResv = new ActiveReservation($this->reserveData, $this->reservRs, $this->family);
        $newResv->save($dbh, $post);
        return $newResv;

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

                $events[$m->getPrefix()] = array('ctrl'=>$m->getStayObj()->createStayButton($m->getPrefix()), 'stay'=>$m->getStay());
            }

            return array('stayCtrl'=>$events);

        }
    }

    protected function createExpDatesControl($prefix = '') {

        $uS = Session::getInstance();
        $nowDT = new \DateTime();
        $nowDT->setTime(0, 0, 0);

        $days = '';

        $cidAttr = array('name'=>$prefix.'gstDate', 'readonly'=>'readonly', 'size'=>'14' );

        if (is_null($this->reserveData->getArrivalDT()) === FALSE && $this->reserveData->getArrivalDT() < $nowDT) {
            $cidAttr['class'] = ' ui-state-highlight';
        }

        if (is_null($this->reserveData->getArrivalDT()) === FALSE && is_null($this->reserveData->getDepartureDT()) === FALSE) {
            $days = $this->reserveData->getDepartureDT()->diff($this->reserveData->getArrivalDT(), TRUE)->days + 1;
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

    protected function createResvMarkup(\PDO $dbh, $oldResv, $prefix = '') {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);

        $resv = new Reservation_1($this->reservRs);
        $showPayWith = FALSE;

        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());

        if ($resv->isNew() === FALSE && $resv->isActive()) {

            // Allow reservations to have many guests.
            $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
            $roomChooser->setOldResvId($oldResv);

            $dataArray['rChooser'] = $roomChooser->CreateResvMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

            // Rate Chooser
            if ($uS->RoomPriceModel != ItemPriceCode::None) {

                $showPayWith = TRUE;

                $rateChooser = new RateChooser($dbh);

                $dataArray['rate'] = $rateChooser->createResvMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'), $reg->getIdRegistration());
                // Array with amount calculated for each rate.
                $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));
                // Array with key deposit info
                $dataArray['rooms'] = $roomChooser->makeRoomsArray();

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
                , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%; font-size:0.9em;', 'class'=>'hhk-panel'));


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

    protected function makeRoomsArray(Reservation_1 $resv) {

        $uS = Session::getInstance();

        $resArray = array();

        foreach ($resv->getAvailableResources() as $rc) {

            if ($rc->getIdResource() == $resv->getIdResource()) {
                $assignedRate = $resv->getFixedRoomRate();
            } else {
                $assignedRate = $rc->getRate($uS->guestLookups['Static_Room_Rate']);
            }

            $resArray[$rc->getIdResource()] = array(
                "maxOcc" => $rc->getMaxOccupants(),
                "rate" => $assignedRate,
                "title" => $rc->getTitle(),
                'key' => $rc->getKeyDeposit($uS->guestLookups[GL_TableNames::KeyDepositCode]),
                'status' => 'a'
            );
        }

        // Blank
        $resArray['0'] = array(
            "maxOcc" => 0,
            "rate" => 0,
            "title" => '',
            'key' => 0,
            'status' => ''
        );

        return $resArray;

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
                    . " where s.`Status` = '" . VisitStatus::CheckedIn . "' and DATE(dateDefaultNow(s.Expected_Co_Date)) >= DATE('" . $arrivalDT->format('Y-m-d') . "') "
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
        if (isset($post['selRateCategory']) && (SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN) || $uS->RateChangeAuth === FALSE)) {

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
            if (isset($post['txtFixedRate']) && (SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN) || $uS->RateChangeAuth === FALSE)) {

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

        } else if (isset($post['txtadjAmount']) && (SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN) || $uS->RateChangeAuth === FALSE)) {

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

        if ($idRescPosted == 0) {

            // Waitlisting the Reservation.
            $resv->setIdResource(0);
            $resv->setStatus(ReservationStatus::Waitlist);
            $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
            return;
        }

        $roomChooser = new RoomChooser($dbh, $resv, 1, new \DateTime($resv->getExpectedArrival()), new \DateTime($resv->getExpectedDeparture()));

        $resources = $roomChooser->findResources($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        // Does the resource fit the requirements?
        if (($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::UnCommitted)
                && isset($resources[$idRescPosted]) === FALSE) {

            $this->reserveData->addError('Chosen Room is unavailable.  ');
            $resv->setIdResource(0);
            $resv->setStatus(ReservationStatus::Waitlist);

        } else {

            $resv->setIdResource($idRescPosted);

            // Don't change comitted to uncommitted.
            if ($resv->getStatus() != ReservationStatus::Committed) {
                $resv->setStatus($uS->InitResvStatus);
            }
        }

        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);

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

    public function setDates($post) {

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
            $this->reserveData->setArrivalDT(new\DateTime($arrival));
            $this->reserveData->setDepartureDT(new \DateTime($departure));
        } catch (Exception $ex) {
            throw new Hk_Exception_Runtime('Something is wrong with one of the dates: ' . $ex->getMessage() . '.  ');
        }

    }
}



class ActiveReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

        if ($this->reservRs->Status->getStoredVal() == '') {
            $this->reservRs->Status->setStoredVal(ReservationStatus::Waitlist);
        }

        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh);
        $this->createFamilyMarkup($dbh);


        // Get any previous settings and set primary guest if blank.
        $oldResvId = $this->copyOldReservation($dbh);

        // Add the reservation section.
        $this->reserveData->setResvSection($this->createResvMarkup($dbh, $oldResvId));

        return $this->reserveData->toArray();

    }

    public function save(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $this->initialSave($dbh, $post);

        if ($this->reserveData->hasError()) {
            return $this;
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
        $resv->setExpectedArrival($this->reserveData->getArrivalDT()->format('Y-m-d ' . $uS->CheckInTime . ':00:00'));
        $resv->setExpectedDeparture($this->reserveData->getDepartureDT()->format('Y-m-d ' . $uS->CheckOutTime . ':00:00'));
        $resv->setNumberGuests(count($this->getStayingMembers()));

        if (($idPriGuest = $this->reserveData->findPrimaryGuestId()) !== NULL) {
            $resv->setIdGuest($idPriGuest);
        }

        // Collect the room rates
        $this->setRoomRate($dbh, $reg, $resv, $post);

        // Payment Type
        if (isset($post['selPayType'])) {
            $resv->setExpectedPayType(filter_var($post['selPayType'], FILTER_SANITIZE_STRING));
        }

        // Verbal Confirmation Flag
        if (isset($post['cbVerbalConf']) && $resv->getVerbalConfirm() != 'v') {
            $resv->setVerbalConfirm('v');
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

        // Notes
        if (isset($post['taNewNote']) && $post['taNewNote'] != '') {
            $resv->saveNote($dbh, filter_var($post['taNewNote'], FILTER_SANITIZE_STRING), $uS->username);
        }

        // Room Chooser
        $this->setRoomChoice($dbh, $resv, $idRescPosted);

        // Payments
        $this->savePayments($dbh, $resv, $post);

        return $this;
    }
}



class CheckingIn extends ActiveReservation {

    protected $payResult;
    protected $visit;
    protected $resc;
    protected $errors;

    public static function reservationFactoy(\PDO $dbh, $post) {

        $rData = new ReserveData($post, 'Check-in');
        $rData->setSaveButtonLabel('Check-in');

        if ($rData->getIdResv() > 0) {
            return CheckingIn::loadReservation($dbh, $rData);
        }

        throw new Hk_Exception_Runtime('Reservation Id not defined.');

    }

    public static function loadReservation(\PDO $dbh, ReserveData $rData) {

        // Load reservation
        $stmt = $dbh->query("SELECT r.*, rg.idPsg, ifnull(v.idVisit, 0) as idVisit
FROM reservation r
        LEFT JOIN
    registration rg ON r.idRegistration = rg.idRegistration
	LEFT JOIN
    visit v on v.idReservation = r.idReservation and v.Span = 0
WHERE r.idReservation = " . $rData->getIdResv());

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) != 1) {
            throw new Hk_Exception_Runtime("Reservation Id not found.  ");
        }

        $rRs = new ReservationRS();
        EditRS::loadRow($rows[0], $rRs);

        $rData->setIdPsg($rows[0]['idPsg']);
        $rData->setIdVisit($rows[0]['idVisit']);

        // Reservation status determines which class to use.
        if ($rRs->Status->getStoredVal() == ReservationStatus::UnCommitted) {
            return new ActiveReservation($rData, $rRs, new Family($dbh, $rData, TRUE));
        }

        if ($rRs->Status->getStoredVal() == ReservationStatus::Staying) {
            return new StayingReservation($rData, $rRs, new FamilyAddGuest($dbh, $rData, TRUE));
        }

        if (Reservation_1::isActiveStatus($rRs->Status->getStoredVal())) {
            return new CheckingIn($rData, $rRs, new Family($dbh, $rData, TRUE));
        }

        // Default
        return new StaticReservation($rData, $rRs, new Family($dbh, $rData));

    }

    public function createMarkup(\PDO $dbh) {

        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh);
        $this->createFamilyMarkup($dbh);

        $this->reserveData->setResvSection($this->createCheckinMarkup($dbh));

        return $this->reserveData->toArray();

    }

    protected function createCheckinMarkup(\PDO $dbh) {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);

        $resv = new Reservation_1($this->reservRs);

        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());

        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());

        // Rate Chooser
        $rateChooser = new RateChooser($dbh);

        // Create rate chooser markup?
        if ($uS->RoomPriceModel != ItemPriceCode::None) {

            $resc = $roomChooser->getSelectedResource();

            if (is_null($resc)) {
                $roomKeyDeps = '';
            } else {
                $roomKeyDeps = $resc->getKeyDeposit($uS->guestLookups[GL_TableNames::KeyDepositCode]);
            }

            // Rate Chooser
            $dataArray['rate'] = $rateChooser->createCheckinMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));

        }

        // Payment Chooser
        if ($uS->PayAtCkin) {

            $checkinCharges = new CheckinCharges(0, $resv->getVisitFee(), $roomKeyDeps);
            $checkinCharges->sumPayments($dbh);

            $dataArray['pay'] = HTMLContainer::generateMarkup('div',
                    PaymentChooser::createMarkup($dbh, $resv->getIdGuest(), $reg->getIdRegistration(), $checkinCharges, $resv->getExpectedPayType(), $uS->KeyDeposit, FALSE, $uS->DefaultVisitFee, $reg->getPreferredTokenId())
                    , array('style'=>'clear:left; float:left;'));
        }

        // Room Chooser
        $dataArray['rChooser'] = $roomChooser->CreateCheckinMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN), FALSE, TRUE, 1);

        // Rates array with amount calculated for each rate.
        $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));

        // Rooms array with key deposit info
        $dataArray['rooms'] = $roomChooser->makeRoomsArray();

        if ($uS->VisitFee) {
            // Visit Fee Array
            $dataArray['vfee'] = $rateChooser->makeVisitFeeArray($dbh, $resv->getVisitFee());
        }

        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
        }

        // Reservation status title
        $dataArray['rStatTitle'] = '';

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Visit Notes', array('style'=>'font-weight:bold;'))
                , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%; font-size: 0.9em;', 'class'=>'hhk-panel'));


        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', 'Checking In')
                , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    public function save(\PDO $dbh, $post) {

        // Save family, rate, hospital, room.
        parent::save($dbh, $post);

        if ($this->reserveData->hasError() === FALSE) {
            $this->checkIn($dbh, $post);
        }

    }

    protected function checkIn(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $arrivalDT = NULL;
        $departDT = NULL;
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $tonight = new DateTime();
        $tonight->setTime(23, 59, 50);



        $resv = new Reservation_1($this->reservRs);

        $stmt = $dbh->query("Select idVisit from visit where idReservation = " . $resv->getIdReservation() . " limit 1;");

        if ($stmt->rowCount() > 0) {
            throw new Hk_Exception_Runtime('Visit already exists for reservation Id ' . $resv->getIdReservation());
        }

        // Arrival and Departure dates
        try {
            $this->setDates($post, $arrivalDT, $departDT);

            // Edit checkin date for later hour of checkin if posting the check in late.
            $tCkinDT = new \DateTime($arrivalDT->format('Y-m-d 00:00:00'));

                if ($arrivalDT->format('H') < $uS->CheckInTime && $today > $tCkinDT) {
                    $arrivalDT->setTime($uS->CheckInTime,0,0);
                }

                self::verifyVisitDates($resv, $arrivalDT, $departDT, $uS->OpenCheckin);


        } catch (Hk_Exception_Runtime $hex) {
            $this->reserveData->addError('Problem with ' . $this->reserveData->getResvTitle() . ' arrival and/or departure dates:  ' . $hex->getMessage() . '.  ');
            return;
        }


        // Cannot check in early
        if ($arrivalDT > $tonight) {
            $this->reserveData->addError('Cannot check into the future.  ');
            return;
        }

        // Is resource specified?
        if ($resv->getIdResource() == 0) {
            $this->reserveData->addError('A room was not specified.  ');
            return;
        }

        $resources = $resv->findGradedResources($dbh, $arrivalDT->format('Y-m-d'), $departDT->format('Y-m-d'), 1, array('room', 'rmtroom', 'part'), TRUE);


        // Does the resource still fit the requirements?
        if (isset($resources[$resv->getIdResource()]) === FALSE) {
            $this->reserveData->addError('The room is already in use.  ');
            return;
        }

        // Get our room.
        $resc = $resources[$resv->getIdResource()];
        unset($resources);

        // Only admins can pick an unsuitable room.
        if (SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN) === FALSE && $resc->optGroup != '') {
            $this->reserveData->addError('Room ' . $resc->getTitle() . ' is ' . $resc->optGroup . '.  ');
            return;
        }

        if (count($this->getStayingMembers()) > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }

        // create visit
        $visit = new Visit($dbh, $resv->getIdRegistration(), 0, $arrivalDT, $departDT, $resc, $uS->username);

        // Add guests
        foreach ($this->getStayingMembers() as $m) {

            if ($uS->PatientAsGuest === FALSE && $m->isPatient()) {
                $this->reserveData->addError('Patients cannot stay  .');
                return;
            }

            $visit->addGuestStay($m->getId(), $arrivalDT->format('Y-m-d H:i:s'), $arrivalDT->format('Y-m-d H:i:s'), $departDT->format('Y-m-d H:i:s'));

        }

        $visit->setRateCategory($resv->getRoomRateCategory());
        $visit->setIdRoomRate($resv->getIdRoomRate());
        $visit->setRateAdjust($resv->getRateAdjust());
        $visit->setPledgedRate($resv->getFixedRoomRate());

        // Rate Glide
        $visit->setRateGlideCredit(RateChooser::setRateGlideDays($dbh, $resv->getIdRegistration(), $uS->RateGlideExtend));

        // Primary guest
        $visit->setPrimaryGuestId($resv->getIdGuest());

        // Reservation Id
        $visit->setReservationId($resv->getIdReservation());

        // hospital stay id
        $visit->setIdHospital_stay($resv->getIdHospitalStay());

        //
        // Checkin  Saves visit
        //
        $visit->checkin($dbh, $uS->username);


        // Save new reservation status
        $resv->setStatus(ReservationStatus::Staying);
        $resv->setActualArrival($visit->getArrivalDate());
        $resv->setExpectedDeparture($visit->getExpectedDeparture());
        $resv->setNumberGuests(count($this->getStayingMembers()));
        $resv->setIdResource($resc->getIdResource());
        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);

        //
        // Payment
        //
        $pmp = PaymentChooser::readPostedPayment($dbh, $post);

        // Check for key deposit
        if ($uS->KeyDeposit && is_null($pmp) === FALSE) {

            $reg = new Registration($dbh, 0, $resv->getIdRegistration());

            $depCharge = $resc->getKeyDeposit($uS->guestLookups[GL_TableNames::KeyDepositCode]);
            $depBalance = $reg->getDepositBalance($dbh);

            if ($depCharge > 0 && $pmp->getKeyDepositPayment() == 0 && $depBalance > 0) {

                // Pay deposit with registration balance
                if ($depCharge <= $depBalance) {
                    $pmp->setKeyDepositPayment($depCharge);
                } else {
                    $pmp->setKeyDepositPayment($depBalance);
                }



            } else if ($pmp->getKeyDepositPayment() > 0) {

                $visit->visitRS->DepositPayType->setNewVal($pmp->getPayType());
            }

            // Update Pay type.
            $visit->updateVisitRecord($dbh, $uS->username);
        }

        $paymentManager = new PaymentManager($pmp);
        $this->payResult = HouseServices::processPayments($dbh, $paymentManager, $visit, 'CheckedIn.php', $visit->getPrimaryGuestId());

        $this->resc = $resc;
        $this->visit = $visit;

        return;
    }

    public function checkedinMarkup(\PDO $dbh) {

        $creditCheckOut = array();

        if ($this->reserveData->hasError()) {
            return $this->createMarkup($dbh);
        }

        $uS = Session::getInstance();
        $reply = '';

        if ($this->payResult !== NULL) {

            $reply .= $this->payResult->getReplyMessage();

            if ($this->payResult->getStatus() == PaymentResult::FORWARDED) {
                $creditCheckOut = $this->payResult->getForwardHostedPayment();
            }

            // Receipt
            if (is_null($this->payResult->getReceiptMarkup()) === FALSE && $this->payResult->getReceiptMarkup() != '') {
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $this->payResult->getReceiptMarkup());
                Registration::updatePrefTokenId($dbh, $this->visit->getIdRegistration(), $this->payResult->getIdToken());
            }

            // New Invoice
            if (is_null($this->payResult->getInvoiceNumber()) === FALSE && $this->payResult->getInvoiceNumber() != '') {
                $dataArray['invoiceNumber'] = $this->payResult->getInvoiceNumber();
            }
        }


        // Generate Reg form
        $reservArray = ReservationSvcs::generateCkinDoc($dbh, 0, $this->visit->getIdVisit(), $uS->resourceURL . 'images/receiptlogo.png');

        $dataArray['style'] = $reservArray['style'];
        $dataArray['regform'] = $reservArray['doc'];
        unset($reservArray);


        // email the form
        if ($uS->adminEmailAddr != '' && $uS->noreplyAddr != '') {

            try {

                $config = new Config_Lite(ciCFG_FILE);
                $mail = prepareEmail($config);

                $mail->From = $uS->noreplyAddr;
                $mail->FromName = $uS->siteName;

                $tos = explode(',', $uS->adminEmailAddr);
                foreach ($tos as $t) {
                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }

                $mail->addReplyTo($uS->noreplyAddr, $uS->siteName);
                $mail->isHTML(true);

                $mail->Subject = "New Check-In to " . $this->resc->getTitle() . " by " . $uS->username;

                $notes = '';  //HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h4', 'Notes') . nl2br($psg->psgRS->Notes->getStoredVal()));

                $mail->msgHTML($dataArray['style'] . $dataArray['regform'] . $notes);
                $mail->send();

            } catch (Exception $ex) {
                $reply .= $ex->getMessage();
            }
        }


        // Credit payment?
        if (count($creditCheckOut) > 0) {
            return $creditCheckOut;
        }

        // reload registration to reflect any new deposit payments.
        $reg = new Registration($dbh, 0, $this->visit->getIdRegistration());


        $dataArray['vid'] = $this->visit->getIdVisit();
        $dataArray['regDialog'] = HTMLContainer::generateMarkup('div', $reg->createRegMarkup($dbh, FALSE), array('class' => "ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"));
        $dataArray['success'] = "Checked-In.  " . $reply;
        $dataArray['reg'] = $reg->getIdRegistration();

        return $dataArray;

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

    public function save(\PDO $dbh, $post) {

        $newResv = new ActiveReservation($this->reserveData, $this->reservRs, $this->family);
        $newResv->save($dbh, $post);
        return $newResv;

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



class StayingReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

        $this->createFamilyMarkup($dbh);

        $this->reserveData->setResvSection($this->createAddGuestMarkup($dbh));

        return $this->reserveData->toArray();

    }

    public function save(\PDO $dbh, $post) {

        // Save family, rate, hospital, room.
        $this->initialSave($dbh, $post);

        if ($this->reserveData->hasError()) {
            return $this;
        }

    }


    protected function createAddGuestMarkup(\PDO $dbh) {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);

        $nowDT = new \DateTime();
        $nowDT->setTime(intval($uS->CheckInTime), 0, 0);

        $resv = new Reservation_1($this->reservRs);

        // Dates
        $this->reserveData->setArrivalDT($nowDT);
        $dataArray = $this->createExpDatesControl();

        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());

        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
        $dataArray['rChooser'] = $roomChooser->createAddGuestMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        // Rate Chooser
        $rateChooser = new RateChooser($dbh);

        // Create rate chooser markup?
        if ($uS->RoomPriceModel != ItemPriceCode::None) {

            $resc = $roomChooser->getSelectedResource();

            if (is_null($resc)) {
                $roomKeyDeps = '';
            } else {
                $roomKeyDeps = $resc->getKeyDeposit($uS->guestLookups[GL_TableNames::KeyDepositCode]);
            }

            // Rate Chooser
            $dataArray['rate'] = HTMLContainer::generateMarkup('div',
                    $rateChooser->createCheckinMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'), FALSE)
                    , array('style'=>'clear:left; float:left; display:none;', 'id'=>'divRateChooser'));
        }

        // Payment Chooser
        if ($uS->PayAtCkin) {

            $checkinCharges = new CheckinCharges(0, $resv->getVisitFee(), $roomKeyDeps);
            $checkinCharges->sumPayments($dbh);

            $dataArray['pay'] = HTMLContainer::generateMarkup('div',
                    PaymentChooser::createMarkup($dbh, $resv->getIdGuest(), $reg->getIdRegistration(), $checkinCharges, $resv->getExpectedPayType(), $uS->KeyDeposit, FALSE, $uS->DefaultVisitFee, $reg->getPreferredTokenId(), FALSE)
                    , array('style'=>'clear:left; float:left; display:none;', 'id'=>'divPayChooser'));
        }

        // Rates array with amount calculated for each rate.
        $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));

        // Rooms array with key deposit info
        $dataArray['rooms'] = $roomChooser->makeRoomsArray();

        if ($uS->VisitFee) {
            // Visit Fee Array
            $dataArray['vfee'] = $rateChooser->makeVisitFeeArray($dbh, $resv->getVisitFee());
        }

        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
        }

        // Reservation status title
        $dataArray['rStatTitle'] = 'Adding Guests';

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Visit Notes', array('style'=>'font-weight:bold;'))
                , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%; font-size: 0.9em;', 'class'=>'hhk-panel'));


        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', 'Add Guests:', array('style'=>'float:left;font-size:.9em; margin-right: 1em;'))
                 . HTMLContainer::generateMarkup('span', '', array('id'=>'addGuestHeader', 'style'=>'float:left;'))
                , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    protected function addGuest(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tonight = new DateTime();
        $tonight->setTime(23, 59, 50);

        $resv = new Reservation_1($this->reservRs);

        $stmt = $dbh->query("Select idVisit, max(Span) from visit where idReservation = " . $resv->getIdReservation() . ";");

        if ($stmt->rowCount() == 0) {
            throw new Hk_Exception_Runtime('Visit not found for reservation Id ' . $resv->getIdReservation());
        }

        $vrows = $stmt->fetchAll(PDO::FETCH_NUM);
        $idVisit = $vrows[0][0];
        $span = $vrows[0][1];

        // Arrival and Departure dates
        try {
            $arrivalDT = new\DateTime();
            $departDT = new \DateTime();
            $this->setDates($post, $arrivalDT, $departDT);

            // Edit checkin date for later hour of checkin if posting the check in late.
            $tCkinDT = new \DateTime($arrivalDT->format('Y-m-d 00:00:00'));

            if ($arrivalDT->format('H') < $uS->CheckInTime && $today > $tCkinDT) {
                $arrivalDT->setTime($uS->CheckInTime,0,0);
            }

            if ($arrivalDT > $departDT) {
                $this->reserveData->addError('A check-in date cannot be AFTER the check-out date.  ');
            }

        } catch (Hk_Exception_Runtime $hex) {
            $this->reserveData->addError($hex->getMessage());
            return;
        }

        // Cannot check in early
        if ($arrivalDT > $tonight) {
            $this->reserveData->addError('Cannot check into the future.  ');
            return;
        }

        // Is resource specified?
        if ($resv->getIdResource() == 0) {
            $this->reserveData->addError('A room was not specified.  ');
            return;
        }

        // Get our room.
        $resc = Resource::getResourceObj($dbh, $resv->getIdResource(), ResourceTypes::Room, FALSE);
        $numOccupants = $resc->getCurrantOccupants($dbh) + count($this->getStayingMembers());

        if ($numOccupants > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }

        // create visit
        $visit = new Visit($dbh, $resv->getIdRegistration(), $idVisit, $arrivalDT, $departDT, $resc, $uS->username, $span);

        // Add guests
        foreach ($this->getStayingMembers() as $m) {

            if ($uS->PatientAsGuest === FALSE && $m->isPatient()) {
                $this->reserveData->addError('Patients cannot stay.  ');
                return;
            }

            $visit->addGuestStay($m->getId(), $arrivalDT->format('Y-m-d H:i:s'), $arrivalDT->format('Y-m-d H:i:s'), $departDT->format('Y-m-d H:i:s'));

        }


        //
        // Checkin  Saves visit
        //
        $visit->checkin($dbh, $uS->username);

        $this->payResult = NULL;

        $this->resc = $resc;
        $this->visit = $visit;

        return;
    }

}

class CheckedoutReservation extends ActiveReservation {

    public function save(\PDO $dbh, $post) {
        return array('error'=>'Saving is not allowed.');
    }

    public function addPerson(\PDO $dbh) {
        return array('error'=>'Adding a person is not allowed.');
    }

}



class StaticReservation extends ActiveReservation {

    public function addPerson(\PDO $dbh) {
        return array();
    }
}

