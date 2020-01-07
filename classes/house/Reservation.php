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
    protected $payResult;
    protected $cofResult;


    function __construct(ReserveData $reserveData, $reservRs, $family) {

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
        $stmt = $dbh->query("SELECT r.*, rg.idPsg, ifnull(v.idVisit, 0) as idVisit, ifnull(v.`Status`, '') as `SpanStatus`, ifnull(v.Span_Start, '') as `SpanStart`, ifnull(v.Span_End, datedefaultnow(v.Expected_Departure)) as `SpanEnd`
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
        $rData->setIdVisit($rows[0]['idVisit'])
            ->setSpanStatus($rows[0]['SpanStatus'])
            ->setSpanStartDT($rows[0]['SpanStart'])
            ->setSpanEndDT($rows[0]['SpanEnd']);

        if (Reservation_1::isActiveStatus($rRs->Status->getStoredVal())) {
            return new ActiveReservation($rData, $rRs, new Family($dbh, $rData));
        }

        if ($rRs->Status->getStoredVal() == ReservationStatus::Staying) {
            return new StayingReservation($rData, $rRs, new FamilyAddGuest($dbh, $rData, TRUE));
        }

        if ($rRs->Status->getStoredVal() == ReservationStatus::Checkedout) {
            return new CheckedoutReservation($rData, $rRs, new Family($dbh, $rData));
        }

        // Turned away, cancelled, etc.
        return new StaticReservation($rData, $rRs, new Family($dbh, $rData));

    }

    protected function checkVisitDates($checkinHour) {

        $today = new \DateTime();
        $hourNow = intval($today->format('H'));
        $minuteNow = intval($today->format('i'));

        $today->setTime(0, 0, 0);

        $tonight = new DateTime();
        $tonight->setTime(23, 59, 50);


        // Edit checkin date for later hour of checkin if posting the check in late.
        $tCkinDT = new \DateTime($this->reserveData->getArrivalDT()->format('Y-m-d 00:00:00'));

        if ($today > $tCkinDT) {
            $this->reserveData->getArrivalDT()->setTime(intval($checkinHour),0,0);
        } else {
            $this->reserveData->getArrivalDT()->setTime($hourNow, $minuteNow, 0);
        }

        // Date Order
        if ($this->reserveData->getArrivalDT() > $this->reserveData->getDepartureDT()) {
            $this->reserveData->addError('A check-in date cannot be AFTER the checkout date.  ');
            return;
        }

        // Cannot check in early
        if ($this->reserveData->getArrivalDT() > $tonight) {
            $this->reserveData->addError('Cannot check into the future.  ');
            return;
        }
    }

    protected function processCOF(\PDO $dbh, $idGuest, $idReg, $post, $postbackPage) {

        $uS = Session::getInstance();

        // Delete any credit cards on file
        $keys = array_keys($post);

        foreach ($keys as $k) {

            $parts = explode('_', $k);

            if (count($parts) > 1 && $parts[0] == 'crdel') {

                $idGt = intval(filter_var($parts[1], FILTER_SANITIZE_NUMBER_INT), 10);

                if ($idGt > 0) {
                    $dbh->exec("update guest_token set Token = '' where idGuest_token = " . $idGt);
                }
            }
        }

        // Adding a new card?
        if (isset($post['cbNewCard'])) {

            $selGw = '';
            $newCardHolderName = '';
            $manualKey = FALSE;

            if (isset($post['txtNewCardName']) && isset($post['cbKeyNumber'])) {
                $newCardHolderName = strtoupper(filter_var($post['txtNewCardName'], FILTER_SANITIZE_STRING));
                $manualKey = TRUE;
            }

            if (isset($post['selccgw'])) {
                $selGw = strtolower(filter_var($post['selccgw'], FILTER_SANITIZE_STRING));
            }

            try {
                // Payment Gateway
                $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $selGw);

                $this->cofResult = $gateway->initCardOnFile($dbh, $uS->siteName, $idGuest, $idReg, $manualKey, $newCardHolderName, $postbackPage);

            } catch (Hk_Exception_Payment $ex) {

                $this->reserveData->addError($ex->getMessage());
            }
        }
    }

    public function createMarkup(\PDO $dbh) {

        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh);
        $this->createFamilyMarkup($dbh);

        return $this->reserveData->toArray();

    }

    protected function createFamilyMarkup(\PDO $dbh) {

        $this->family->setGuestsStaying($dbh, $this->reserveData, $this->reservRs->idGuest->getstoredVal());

        // Arrival and Departure dates
        if ($this->reserveData->getIdResv() > 0) {

            try {
                $arrivalDT = new\DateTime($this->reservRs->Expected_Arrival->getStoredVal());
                $departDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());

                $psgMembers = $this->reserveData->getPsgMembers();

                $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $arrivalDT, $departDT, $this->reserveData->getResvTitle()));
                $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $arrivalDT, $this->reserveData->getIdPsg(), $departDT, $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

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
        if ($this->family->save($dbh, $post, $this->reserveData, $uS->username) === FALSE) {
            return;
        }

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
        $this->reserveData->addConcurrentRooms(self::findConflictingStays($dbh, $psgMems, $this->reserveData->getArrivalDT(), $this->reserveData->getIdPsg(), $this->reserveData->getDepartureDT()));
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
        $this->reserveData->addConcurrentRooms(self::findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMems2, $this->reserveData->getArrivalDT(), $this->reserveData->getDepartureDT(), $this->reserveData->getResvTitle()));
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
        if ($this->reserveData->getIdResv() == 0 && $this->reserveData->getConcurrentRooms() >= $uS->RoomsPerPatient) {
            // Too many
            $this->reserveData->addError('This reservation violates your House\'s maximum number of simutaneous rooms per patient (' .$uS->RoomsPerPatient . ').  ');
            return;
        }
    }

    public function save(\PDO $dbh, $post) {

        $newResv = new ActiveReservation($this->reserveData, $this->reservRs, $this->family);
        $newResv->save($dbh, $post);
        return $newResv;

    }

    public function addPerson(\PDO $dbh) {

        $psgMembers = $this->reserveData->getPsgMembers();

        $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT(), $this->reserveData->getResvTitle()));
        $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getIdPsg(), $this->reserveData->getSpanEndDT(), $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

        $this->reserveData->setPsgMembers($psgMembers);

        $this->reserveData->setAddPerson($this->family->createAddPersonMu($dbh, $this->reserveData));
        return $this->reserveData->toArray();
    }

    public static function updateAgenda(\PDO $dbh, $post) {

        // decifer posts
        if (isset($post['dt1']) && isset($post['dt2']) && isset($post['mems'])) {

            $labels = new Config_Lite(LABEL_FILE);
            $psgMembers = array();
            $idVisit = 0;
            $span = 0;

            if (isset($post['idVisit'])) {
                $idVisit = intval(filter_var($post['idVisit'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            if (isset($post['span'])) {
                $span = intval(filter_var($post['span'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $idPsg = intval(filter_var($post['idPsg'], FILTER_SANITIZE_NUMBER_INT), 10);
            $idResv = intval(filter_var($post['idResv'], FILTER_SANITIZE_NUMBER_INT), 10);
            $postMems = filter_var_array($post['mems'], FILTER_SANITIZE_STRING);

            try {
                $arrivalDT = new DateTime(filter_var($post['dt1'], FILTER_SANITIZE_STRING));
                $departDT = new DateTime(filter_var($post['dt2'], FILTER_SANITIZE_STRING));
            } catch(Exception $ex) {
                return array('error'=>'Bad dates: ' . $ex->getMessage());
            }


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

                $psgMembers[$prefix] = new PSGMember($id, $prefix, $role, $priGuest, new PSGMemStay($stay));
            }

            // Create new stay controls for each member
            self::findConflictingReservations($dbh, $idPsg, $idResv, $psgMembers, $arrivalDT, $departDT, $labels->getString('guestEdit', 'reservationTitle', 'Reservation'));
            self::findConflictingStays($dbh, $psgMembers, $arrivalDT, $idPsg, $departDT, $idVisit, $span);

            $events = [];

            foreach ($psgMembers as $m) {

                $events[$m->getPrefix()] = array('ctrl'=>$m->getStayObj()->createStayButton($m->getPrefix()), 'stay'=>$m->getStay());
            }

            return array('stayCtrl'=>$events);

        }
    }

    protected function createExpDatesControl($updateOnChange = TRUE, $startDate = FALSE, $endDate = FALSE) {

        $uS = Session::getInstance();
        $nowDT = new \DateTime();
        $nowDT->setTime(0, 0, 0);

        $days = '';
        $prefix = '';

        $cidAttr = array('name'=>$prefix.'gstDate', 'readonly'=>'readonly', 'size'=>'14' );

        if (is_null($this->reserveData->getArrivalDT()) === FALSE && $this->reserveData->getArrivalDT() < $nowDT) {
            $cidAttr['class'] = ' ui-state-highlight';
        }

        if (is_null($this->reserveData->getArrivalDT()) === FALSE && is_null($this->reserveData->getDepartureDT()) === FALSE) {
            $days = $this->reserveData->getDepartureDT()->diff($this->reserveData->getArrivalDT(), TRUE)->days;
            if ($this->reserveData->getSpanStatus() == '' || $this->reserveData->getSpanStatus() == VisitStatus::CheckedIn) {
                $days++;
            }
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

        return array('mu'=>$mkup, 'defdays'=>$uS->DefaultDays, 'daysEle'=>$prefix.'gstDays', 'updateOnChange'=>$updateOnChange, 'startDate'=>$startDate, 'endDate'=>$endDate);

    }

    protected function createResvMarkup(\PDO $dbh, $oldResv, $prefix = '') {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);

        $resv = new Reservation_1($this->reservRs);
        $showPayWith = FALSE;
        $statusText = $resv->getStatusTitle();
        $hideCheckinButton = TRUE;

        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());

        // active reservations
        if ($resv->isNew() === FALSE && $resv->isActive()) {

            // Allow reservations to have many guests.
            $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
            $rateChooser = new RateChooser($dbh);

            $dataArray['rChooser'] = $roomChooser->CreateResvMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

            // Rooms array
            $dataArray['rooms'] = $roomChooser->makeRoomsArray();

            // Rate Chooser
            if ($uS->RoomPriceModel != ItemPriceCode::None) {

                $showPayWith = TRUE;


                $dataArray['rate'] = $rateChooser->createResvMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'), $reg->getIdRegistration());

                // Card on file
                if ($uS->PaymentGateway != '') {

                    $dataArray['cof'] = HTMLcontainer::generateMarkup('div' ,HTMLContainer::generateMarkup('fieldset',
                            HTMLContainer::generateMarkup('legend', 'Credit Cards on File', array('style'=>'font-weight:bold;'))
                            . HouseServices::viewCreditTable($dbh, $resv->getIdRegistration(), $resv->getIdGuest())
                        ,array('style'=>'float:left;', 'class'=>'hhk-panel')));
                }
            }

            // Array with amount calculated for each rate.
            $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));

            if ($uS->VisitFee) {
                // Visit Fee Array
                $dataArray['vfee'] = $rateChooser->makeVisitFeeArray($dbh, $resv->getVisitFee());
            }

            // Vehicles
            if ($uS->TrackAuto) {
                $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
            }

            // Add room title to status title
            if ($resv->getStatus() == ReservationStatus::Committed) {
                $statusText .= ' for Room ' . $resv->getRoomTitle($dbh);
                $hideCheckinButton = FALSE;
            }

            // Reservation Data
            $dataArray['rstat'] = $this->createStatusChooser(
                    $resv,
                    $resv->getChooserStatuses($uS->guestLookups['ReservStatus']),
                    $uS->nameLookups[GL_TableNames::PayType],
                    $labels,
                    $showPayWith,
                    Registration::loadLodgingBalance($dbh, $resv->getIdRegistration()));


        } else if ($resv->isNew()) {

            // Allow reservations to have many guests.
            $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
            $roomChooser->setOldResvId($oldResv);

            $dataArray['rChooser'] = $roomChooser->CreateResvMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        } else if ($resv->getStatus() == ReservationStatus::Staying || $resv->getStatus() == ReservationStatus::Checkedout) {

            // Staying or checked out - cannot change resv status.
            $dataArray['rstat'] = '';

        } else {
            // Cancelled.

            // Allow to change reserv status.
            $dataArray['rstat'] = $this->createStatusChooser(
                    $resv,
                    $resv->getChooserStatuses($uS->guestLookups['ReservStatus']),
                    $uS->nameLookups[GL_TableNames::PayType],
                    $labels,
                    $showPayWith,
                    Registration::loadLodgingBalance($dbh, $resv->getIdRegistration()));
        }

        // Reservation status title
        $dataArray['rStatTitle'] = $statusText;
        $dataArray['hideCiNowBtn'] = $hideCheckinButton;

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'notesLabel', 'Reservation Notes'), array('style'=>'font-weight:bold;'))
                , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%; font-size:0.9em;', 'class'=>'hhk-panel'));


        $dataArray['wlnotes'] = '';

        // Waitlist notes?
        if ($uS->UseWLnotes && $resv->getStatus() == ReservationStatus::Waitlist) {

            $dataArray['wlnotes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $this->reserveData->getWlNotesLabel(), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $resv->getCheckinNotes(), array('name'=>'taCkinNotes', 'rows'=>'2', 'style'=>'width:100%'))
                , array('class'=>'hhk-panel', 'style'=>'float:left; width:50%;'));
        }

        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', ($resv->isNew() ? 'New ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') : $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' - '))
                .HTMLContainer::generateMarkup('span', ($resv->isNew() ? '' : $statusText), array('id'=>$prefix.'spnResvStatus', 'style'=>'margin-right: 1em;'))
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

    public function createStatusChooser(Reservation_1 $resv, array $limResvStatuses, array $payTypes, \Config_Lite $labels, $showPayWith, $moaBal) {

        $uS = Session::getInstance();
        $tbl2 = new HTMLTable();

        // Pay option, verbal confirmation
        $attr = array('name'=>'cbVerbalConf', 'type'=>'checkbox');

        if ($resv->getVerbalConfirm() == 'v') {
            $attr['checked'] = 'checked';
        }

        $moaHeader = '';
        $moaData = '';
        if ($moaBal > 0) {
            $moaHeader = HTMLTable::makeTh('MOA Balance', array('title'=>'MOA = Money on Account'));
            $moaData = HTMLTable::makeTd('$' . number_format($moaBal, 2), array('style'=>'text-align:center'));
        }

        $tbl2->addBodyTr(
                ($showPayWith ? HTMLTable::makeTh('Pay With') . $moaHeader : '')
                .HTMLTable::makeTh('Verbal Affirmation')
                .($resv->getStatus() == ReservationStatus::UnCommitted ? HTMLTable::makeTh('Status', array('class'=>'ui-state-highlight')) : HTMLTable::makeTh('Status'))
                );

        $tbl2->addBodyTr(
                ($showPayWith ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($payTypes), $resv->getExpectedPayType()), array('name'=>'selPayType')))
                . $moaData : '')
                .($resv->isActive() ? HTMLTable::makeTd(HTMLInput::generateMarkup('', $attr), array('style'=>'text-align:center;')) : HTMLTable::makeTd(''))
                .HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($limResvStatuses, $resv->getStatus(), FALSE), array('name'=>'selResvStatus', 'style'=>'float:left;margin-right:.4em;'))
                        .HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-comment hhk-viewResvActivity', 'data-rid'=>$resv->getIdReservation(), 'title'=>'View Activity Log', 'style'=>'cursor:pointer;float:right;')))
                );


        if ($uS->UseWLnotes === FALSE && $resv->isActive()) {
            $tbl2->addBodyTr(HTMLTable::makeTd('Registration Note:',array('class'=>'tdlabel')).HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea',$resv->getCheckinNotes(), array('name'=>'taCkinNotes', 'rows'=>'1', 'cols'=>'40')),array('colspan'=>'3')));
        }

        // Confirmation button
        $mk2 = '';
        if ($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::Waitlist) {
            $mk2 .= HTMLInput::generateMarkup('Create Confirmation...', array('type'=>'button', 'id'=>'btnShowCnfrm', 'style'=>'margin:.3em;float:right;', 'data-rid'=>$resv->getIdReservation()));
        }

        // fieldset wrapper
        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'statusLabel', 'Reservation Status'), array('style'=>'font-weight:bold;'))
                    . $tbl2->generateMarkup() . $mk2,
                    array('class'=>'hhk-panel'))
            , array('style'=>'clear:left; float:left;'));

    }

    protected static function findConflictingStays(\PDO $dbh, array &$psgMembers, $arrivalDT, $idPsg, $departureDT, $idVisit = 0, $idSpan = -1) {

        $whStays = '';
        $rooms = array();

        // Dates correct?
        if (is_null($arrivalDT)) {
            return 0;
        }

        if (is_null($departureDT)) {
            $departureDT = new \DateTime($arrivalDT->format('Y-m-d H:i:s'));
            $departureDT->add(new DateInterval('P1D'));
        }

        // Collect member ids
        foreach ($psgMembers as $m) {
            if ($m->getId() != 0 && $m->isBlocked() === FALSE) {
                $whStays .= ',' . $m->getId();
            }
        }

        // Find any visits.
        if ($whStays != '') {

            // Check ongoing visits
            $vstmt = $dbh->query("SELECT
    s.`idName`,
    s.`idVisit`,
    s.`Visit_Span`,
    s.`idRoom`,
    s.`Status` as `Status`,
    r.`idPsg`,
    rm.`Title`,
    v.`idPrimaryGuest`
FROM
    stays s
        JOIN
    visit v ON s.idVisit = v.idVisit
        AND s.Visit_Span = v.Span
        JOIN
    room rm ON s.idRoom = rm.idRoom
        JOIN
    registration r ON v.idRegistration = r.idRegistration
WHERE
    DATEDIFF(DATE(s.Span_Start_Date), DATE(ifnull(s.Span_End_Date, '2500-01-01'))) != 0
    and DATE(ifnull(s.Span_End_Date, DATE_ADD(datedefaultnow(s.Expected_Co_Date),INTERVAL 1 DAY))) > DATE('" . $arrivalDT->format('Y-m-d') . "')
    and DATE(s.Span_Start_Date) < DATE('" . $departureDT->format('Y-m-d') . "')
    and s.idName in (" . substr($whStays, 1) . ") "
                    . " order by s.idVisit, s.Visit_Span");

            while ($s = $vstmt->fetch(\PDO::FETCH_ASSOC)) {
                // These guests are already staying somewhere

                if ($s['idVisit'] == $idVisit && $s['Visit_Span'] == $idSpan) {
                    // My visit
                    $memVisit = new PSGMemVisit(array());

                } else {
                    // Not my visit
                    $memVisit = new PSGMemVisit(array('idVisit'=>$s['idVisit'], 'Visit_Span'=>$s['Visit_Span'], 'room'=>$s['Title'], 'status'=>$s['Status']));
                }

                // Set visit id and Check primary guest
                foreach ($psgMembers as $m) {

                    if ($m->getId() == $s['idName']) {

                        $psgMembers[$m->getPrefix()]->setStayObj($memVisit);

                        if ($m->getId() == $s['idPrimaryGuest'] && $s['idVisit'] == $idVisit) {
                            $psgMembers[$m->getPrefix()]->setPrimaryGuest(TRUE);
                        }
                   }
                }

                // Count different rooms
                if ($s['idPsg'] == $idPsg) {
                    $rooms[$s['idRoom']] = '1';
                }
            }
        }

        // Return number of rooms being used by this psg.
        return count($rooms);
    }

    protected static function findConflictingReservations(\PDO $dbh, $idPsg, $idResv, array &$psgMembers, $arrivalDT, $departDT, $resvPrompt = 'Reservation') {

        // Check reservations
        $whResv = '';
        $rescs = array();

        if (is_null($arrivalDT)) {
            return 0;
        }

        // Dates correct?
        if (is_null($departDT)) {
            $departDT = new \DateTime($arrivalDT->format('Y-m-d H:i:s'));
            $departDT->add(new DateInterval('P1D'));
        }

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
                        $psgMembers[$m->getPrefix()]->setStayObj(new PSGMemResv(array('idReservation'=>$r['idReservation'], 'idGuest'=>$r['idGuest'], 'idPsg'=>$r['idPsg'], 'label'=>$resvPrompt)));
                    }
                }

                // Count different rooms
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

        if ($resv->isActive() === FALSE) {
            return;
        }

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

            //  No.
            $this->reserveData->addError('Chosen Room is unavailable.  ');
            $resv->setIdResource(0);
            $resv->setStatus(ReservationStatus::Waitlist);

        } else {

            $resv->setIdResource($idRescPosted);

            // Update Status.
            if ($resv->getStatus() != ReservationStatus::Committed && $resv->getStatus() != ReservationStatus::UnCommitted) {
                $resv->setStatus($uS->InitResvStatus);
            }
        }

        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);

    }

    public function changeRoom(\PDO $dbh, $idResv, $idResc) {
        return array('error'=>"Changing this reservation's room is not allowed.");
    }

    protected function copyOldReservation(\PDO $dbh) {

        // Pick up the old ReservationRS, if it exists.
        // Use it to fill in the visit requirements.
        $oldResvId = 0;

        if ($this->reserveData->getIdResv() > 0 || $this->reserveData->getIdPsg() < 1) {
            return $oldResvId;
        }

        $stmt = $dbh->query("SELECT  MAX(r.idReservation), r.idGuest "
                . "FROM reservation r LEFT JOIN registration rg ON r.idRegistration = rg.idRegistration "
                . "WHERE rg.idPsg = " . $this->reserveData->getIdPsg());

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($rows) > 0) {

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
                $rgRs->Primary_Guest->setNewVal($g->isPrimaryGuest() ? '1' : '');

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
                    $rgRs->Primary_Guest->setNewVal($g->isPrimaryGuest() ? '1' : '');

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

    protected $gotoCheckingIn = '';

    public function createMarkup(\PDO $dbh) {

        // COF?
        if ($this->cofResult !== NULL) {

            if (count($this->cofResult) > 0) {
                $this->cofResult['resvTitle'] = $this->reserveData->getResvTitle();

                return $this->cofResult;
            }
        }

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

        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh);
        $this->createFamilyMarkup($dbh);

        // Add the reservation section.
        $this->reserveData->setResvSection($this->createResvMarkup($dbh, $oldResvId));

        return $this->reserveData->toArray();

    }

    public function save(\PDO $dbh, $post) {

        return $this->saveResv($dbh, $post, 'Reserve.php?rid=' . $this->reserveData->getIdResv());
    }

    protected function saveResv(\PDO $dbh, $post, $cofPostbackPage) {

        $uS = Session::getInstance();

        $this->initialSave($dbh, $post);

        if ($this->reserveData->hasError()) {
            return $this;
        }

        // Return a goto checkingin page?
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

        if (isset($post['selResvStatus'])) {

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
            LinkNote::save($dbh, 'Verbal Confirmation is Set.', $resv->getIdReservation(), Note::ResvLink, $uS->username, $uS->ConcatVisitNotes);
        } else {
            $resv->setVerbalConfirm('');
        }

        // Check-in notes (to be put on the registration form. ALternatively, use as waitlist notes.
        if (isset($post['taCkinNotes'])) {
            $resv->setCheckinNotes(filter_var($post['taCkinNotes'], FILTER_SANITIZE_STRING));
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
                LinkNote::save($dbh, $noteText, $resv->getIdReservation(), Note::ResvLink, $uS->username, $uS->ConcatVisitNotes);
            }
        }

        // Room Choice
        $this->setRoomChoice($dbh, $resv, $idRescPosted);


        $this->processCOF($dbh, $resv->getIdGuest(), $reg->getIdRegistration(), $post, $cofPostbackPage);

        return $this;
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

            if ($this->reserveData->getErrors() != '') {
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
        return $this->createMarkup($dbh);
    }
}



class CheckingIn extends ActiveReservation {

    protected $visit;
    protected $resc;
    protected $errors;

    public static function reservationFactoy(\PDO $dbh, $post) {

        $rData = new ReserveData($post, 'Check-in');

        if ($rData->getIdResv() > 0) {
            $rData->setSaveButtonLabel('Check-in');
            return CheckingIn::loadReservation($dbh, $rData);
        }

        throw new Hk_Exception_Runtime('Reservation Id not defined.');

    }

    public static function loadReservation(\PDO $dbh, ReserveData $rData) {

        // Load reservation and visit
        $stmt = $dbh->query("SELECT r.*, rg.idPsg, ifnull(v.`idVisit`, 0) as `idVisit`, ifnull(v.`Status`, '') as `SpanStatus`, ifnull(v.Span_Start, '') as `SpanStart`, ifnull(v.Span_End, datedefaultnow(v.Expected_Departure)) as `SpanEnd`
FROM reservation r
        LEFT JOIN
    registration rg ON r.idRegistration = rg.idRegistration
	LEFT JOIN
    visit v on v.idReservation = r.idReservation and v.Span = " . $rData->getSpan() .
" WHERE r.idReservation = " . $rData->getIdResv());

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) != 1) {
            throw new Hk_Exception_Runtime("Reservation Id not found.  ");
        }

        $rRs = new ReservationRS();
        EditRS::loadRow($rows[0], $rRs);

        $rData->setIdPsg($rows[0]['idPsg']);
        $rData->setIdVisit($rows[0]['idVisit'])
            ->setSpanStatus($rows[0]['SpanStatus'])
            ->setSpanStartDT($rows[0]['SpanStart'])
            ->setSpanEndDT($rows[0]['SpanEnd']);

        // Reservation status determines which class to use.

        // Uncommitted cannot check in.
        if ($rRs->Status->getStoredVal() == ReservationStatus::UnCommitted) {
            $rData->setSaveButtonLabel('Save');
            return new ActiveReservation($rData, $rRs, new Family($dbh, $rData, TRUE));
        }

        // check for additional visit span guest
        if ($rData->getSpanStatus() == VisitStatus::ChangeRate || $rData->getSpanStatus() == VisitStatus::NewSpan || $rData->getSpanStatus() == VisitStatus::CheckedOut) {
            return new CheckedoutReservation($rData, $rRs, new Family($dbh, $rData));
        }

        // Staying resv - add guests
        if ($rRs->Status->getStoredVal() == ReservationStatus::Staying) {
            return new StayingReservation($rData, $rRs, new FamilyAddGuest($dbh, $rData, TRUE));
        }

        // Otherwise we can check in.
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

            // Payment Chooser
            if ($uS->PayAtCkin) {

                $checkinCharges = new CheckinCharges(0, $resv->getVisitFee(), $roomKeyDeps);
                $checkinCharges->sumPayments($dbh);

                // select gateway
                if ($resv->getIdResource() > 0) {
                    // Get gateway merchant
                    $gwStmt = $dbh->query("SELECT ifnull(l.Merchant, '') as `Merchant`, ifnull(l.idLocation, 0) as idLocation FROM location l join room r on l.idLocation = r.idLocation
                    join resource_room rr on r.idRoom = rr.idRoom where l.Status = 'a' and rr.idResource = " . $resv->getIdResource());

                } else {
                    $gwStmt = $dbh->query("SELECT DISTINCT ifnull(l.Merchant, '') as `Merchant`, ifnull(l.idLocation, 0) as idLocation FROM room rm LEFT JOIN location l  on l.idLocation = rm.idLocation
                    where l.`Status` = 'a' or l.`Status` is null;");
                }

                $rows = $gwStmt->fetchAll(PDO::FETCH_ASSOC);
                $merchants = array();

                if (count($rows) > 0) {

                    foreach ($rows as $r) {
                        $merchants[$r['idLocation']] = $r['Merchant'];
                    }
                }

                $paymentGateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $merchants);

                $dataArray['pay'] = HTMLContainer::generateMarkup('div',
                        PaymentChooser::createMarkup($dbh, $resv->getIdGuest(), $reg->getIdRegistration(), $checkinCharges, $paymentGateway, $resv->getExpectedPayType(), $uS->KeyDeposit, FALSE, $uS->DefaultVisitFee, $reg->getPreferredTokenId())
                        , array('style'=>'clear:left; float:left;'));

                // Card on file
                if ($uS->ccgw != '') {

                    $dataArray['cof'] = HTMLcontainer::generateMarkup('div' ,HTMLContainer::generateMarkup('fieldset',
                            HTMLContainer::generateMarkup('legend', 'Credit Cards', array('style'=>'font-weight:bold;'))
                            . HouseServices::viewCreditTable($dbh, $resv->getIdRegistration(), $resv->getIdGuest())
                        ,array('style'=>'float:left;', 'class'=>'hhk-panel')));
                }
            }

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
        parent::saveResv($dbh, $post, 'CheckedIn.php?rid=' . $this->reserveData->getIdResv());

        if ($this->reserveData->hasError() === FALSE) {
            $this->saveCheckIn($dbh, $post);
        }

        return $this;

    }

    protected function saveCheckIn(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $resv = new Reservation_1($this->reservRs);

        $stmt = $dbh->query("Select idVisit from visit where idReservation = " . $resv->getIdReservation() . " limit 1;");

        if ($stmt->rowCount() > 0) {
            throw new Hk_Exception_Runtime('Visit already exists for reservation Id ' . $resv->getIdReservation());
        }

        $this->checkVisitDates($uS->CheckInTime);

        if ($this->reserveData->hasError()) {
            return;
        }

        // Is resource specified?
        if ($resv->getIdResource() == 0) {
            $this->reserveData->addError('A room was not specified.  ');
            return;
        }

        $resources = $resv->findGradedResources($dbh, $this->reserveData->getArrivalDT()->format('Y-m-d'), $this->reserveData->getDepartureDT()->format('Y-m-d'), 1, array('room', 'rmtroom', 'part'), TRUE);

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

        // create visit ( -1 forces a new visit)
        $visit = new Visit($dbh, $resv->getIdRegistration(), -1, $this->reserveData->getArrivalDT(), $this->reserveData->getDepartureDT(), $resc, $uS->username);

        // Add guests
        foreach ($this->getStayingMembers() as $m) {

            if ($uS->PatientAsGuest === FALSE && $m->isPatient()) {
                $this->reserveData->addError('Patients cannot stay  .');
                return;
            }

            $visit->addGuestStay($m->getId(), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getDepartureDT()->format('Y-m-d 10:00:00'));

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
        $this->payResult = HouseServices::processPayments($dbh, $paymentManager, $visit, 'ShowRegForm.php', $visit->getPrimaryGuestId());

        $this->resc = $resc;
        $this->visit = $visit;

        return;
    }

    public function checkedinMarkup(\PDO $dbh) {

        $creditCheckOut = array();

        if ($this->reserveData->hasError()) {
            return $this->createMarkup($dbh);
        }

        // Checking In?
        if ($this->gotoCheckingIn === 'yes' && $this->reserveData->getIdResv() > 0) {
            return array('gotopage'=>'CheckingIn.php?rid=' . $this->reserveData->getIdResv());
        }

        $uS = Session::getInstance();
        $reply = '';
        $payId = 0;
        $invNumber = '';

        if ($this->payResult !== NULL) {
            // Payment processed
            $reply .= $this->payResult->getReplyMessage();

            if ($this->payResult->getStatus() == PaymentResult::FORWARDED) {
                $creditCheckOut = $this->payResult->getForwardHostedPayment();
            }

            // Payment Id
            $payId = $this->payResult->getIdPayment();
            Registration::updatePrefTokenId($dbh, $this->visit->getIdRegistration(), $this->payResult->getIdToken());

            // New Invoice
            if (is_null($this->payResult->getInvoiceNumber()) === FALSE && $this->payResult->getInvoiceNumber() != '') {
                $invNumber = $this->payResult->getInvoiceNumber();
            }

        } else if ($this->cofResult !== NULL) {
            // Process card on file
            if (count($this->cofResult) > 0) {
                $this->cofResult['resvTitle'] = $this->reserveData->getResvTitle();
                $creditCheckOut = $this->cofResult;
            }
        }

        // email the form
        if ($uS->adminEmailAddr != '' && $uS->noreplyAddr != '') {

            // Generate Reg form
            $reservArray = ReservationSvcs::generateCkinDoc($dbh, 0, $this->visit->getIdVisit(), $this->visit->getSpan(), $uS->resourceURL . 'images/receiptlogo.png');

            try {

                $mail = prepareEmail();

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

                $mail->msgHTML($reservArray['docs'][0]['doc'] . $reservArray['docs'][0]['style']);
                $mail->send();

            } catch (Exception $ex) {
                $reply .= $ex->getMessage();
            }
        }


        // Credit payment?
        if (count($creditCheckOut) > 0) {
            return $creditCheckOut;
        }

        $dataArray['payId'] = $payId;
        $dataArray['invoiceNumber'] = $invNumber;
        $dataArray['vid'] = $this->visit->getIdVisit();
        $dataArray['success'] = "Checked-In.  " . $reply;
        $dataArray['regid'] = $this->visit->getIdRegistration();

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

        if ($this->reserveData->getIdPsg() < 1 && $this->reserveData->getId() > 0) {

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
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Different ' . $this->reserveData->getPatLabel(), array('for'=>'1_cbselpsg')), array('class'=>'tdlabel'))
               .HTMLTable::makeTd(HTMLInput::generateMarkup('-1', array('type'=>'radio', 'name'=>'cbselpsg', 'id'=>'1_cbselpsg', 'data-pid'=>'0', 'data-ngid'=>'0'))));
        }


        return $tbl->generateMarkup();
    }

}



class StayingReservation extends CheckingIn {


    public function createMarkup(\PDO $dbh) {

        if ($this->reserveData->getIdVisit() < 1 || $this->reserveData->getSpan() < 0) {
            throw new Hk_Exception_Runtime('The visit is not defined.');
        }

        $this->createFamilyMarkup($dbh);

        $this->reserveData->setResvSection($this->createAddGuestMarkup($dbh));

        return $this->reserveData->toArray();

    }

    public function save(\PDO $dbh, $post) {

        // Check for new room
        if (isset($post['cbNewRoom'])) {
            // New Room
            $this->reserveData->setIdResv(0);
            $this->reserveData->setIdVisit(0);
            $this->reserveData->setSpan(0);
            $post['rid'] = 0;
            $post['vid'] = 0;
            $post['span'] = 0;
            $post['rbPriGuest'] = 0;
            $post['resvCkinNow'] = 'yes';

            $checkingIn = new ActiveReservation($this->reserveData, new ReservationRS(), new Family($dbh, $this->reserveData, TRUE));
            $checkingIn->save($dbh, $post);
            return $checkingIn;

        } else {
            // Same room, just add the guests.
            $this->addGuestStay($dbh, $post);
        }

        return $this;
    }

    protected function createFamilyMarkup(\PDO $dbh) {

        $psgMembers = $this->reserveData->getPsgMembers();

        $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT(), $this->reserveData->getResvPrompt()));
        $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getIdPsg(), $this->reserveData->getSpanEndDT(), $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

        $this->reserveData->setPsgMembers($psgMembers);

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData));

    }

    protected function createAddGuestMarkup(\PDO $dbh) {

        $uS = Session::getInstance();

        $resvSectionHeaderPrompt = 'Add Guests:';

        $nowDT = new \DateTime();
        $nowDT->setTime(intval($uS->CheckInTime), 0, 0);

        $resv = new Reservation_1($this->reservRs);

        // Dates
        $this->reserveData->setArrivalDT($nowDT);


        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
        $dataArray['rChooser'] = $roomChooser->createAddGuestMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        $dataArray = array_merge($dataArray, $this->createExpDatesControl(TRUE, $this->reserveData->getSpanStartDT()->format('M j, Y')));

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
                HTMLContainer::generateMarkup('span', $resvSectionHeaderPrompt, array('style'=>'float:left;font-size:.9em; margin-right: 1em;'))
                 . HTMLContainer::generateMarkup('span', '', array('id'=>'addGuestHeader', 'style'=>'float:left;'))
                , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    protected function addGuestStay(\PDO $dbh, $post) {

        $uS = Session::getInstance();
        $visitRs = new VisitRs();


        if ($this->reserveData->hasError()) {
            return $this;
        }

        // Does visit exist
        $stmt = $dbh->query("Select * from visit where Status = '" . VisitStatus::CheckedIn . "' and idReservation = " . $this->reserveData->getIdResv() . ";");

        if ($stmt->rowCount() == 0) {
            throw new Hk_Exception_Runtime('Visit not found for reservation Id ' . $this->reserveData->getIdResv());
        }

        $vrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        EditRS::loadRow($vrows[0], $visitRs);

        // Checking the new guest stay dates.
        $this->checkVisitDates($uS->CheckInTime);

        if ($this->reserveData->hasError()) {
            return;
        }

        // Get the resource
        $resc = null;
        if ($visitRs->idResource->getStoredVal() > 0) {
            $resc = Resource::getResourceObj($dbh, $visitRs->idResource->getStoredVal());
        } else {
            $this->reserveData->addError('Room not found.  ');
            return;
        }

        // Maximym occupants...
        $numOccupants = $resc->getCurrantOccupants($dbh) + count($this->getStayingMembers());

        if ($numOccupants > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }

        // Save any new guests now.  (after error checking)
        $this->initialSave($dbh, $post);

        // open visit
        $visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        // Add guests
        foreach ($this->getStayingMembers() as $m) {

            if ($uS->PatientAsGuest === FALSE && $m->isPatient()) {
                $this->reserveData->addError('Patients cannot stay.  ');
                continue;
            }

            $visit->addGuestStay($m->getId(), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getDepartureDT()->format('Y-m-d H:i:s'));
        }


        //
        // Checkin  Saves visit
        //
        $visit->checkin($dbh, $uS->username);

        $this->payResult = NULL;

        $this->resc = $resc;
        $this->visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        return;
    }

}


class CheckedoutReservation extends CheckingIn {

    public function createMarkup(\PDO $dbh) {

        if ($this->reserveData->getIdVisit() < 1 || $this->reserveData->getSpan() < 0) {
            throw new Hk_Exception_Runtime('The visit is not defined.');
        }


        $this->createFamilyMarkup($dbh);

        $this->reserveData->setResvSection($this->createAddGuestMarkup($dbh));

        return $this->reserveData->toArray();

    }

    public function save(\PDO $dbh, $post) {

        // Same room, just add the guests.
        $this->addGuestStay($dbh, $post);

        return $this;
    }

    protected function createFamilyMarkup(\PDO $dbh) {

        $psgMembers = $this->reserveData->getPsgMembers();

        $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT(), $this->reserveData->getResvTitle()));
        $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getIdPsg(), $this->reserveData->getSpanEndDT(), $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

        $this->reserveData->setPsgMembers($psgMembers);

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData));

    }

    protected function createAddGuestMarkup(\PDO $dbh) {

        $uS = Session::getInstance();

        $nowDT = new \DateTime();
        $nowDT->setTime(intval($uS->CheckInTime), 0, 0);

        $resv = new Reservation_1($this->reservRs);

        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 0, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT());

        $resvSectionHeaderPrompt = 'Add Guests; ';

        // Calculate room occupation
        $occs = 0;
        foreach ($this->reserveData->getPsgMembers() as $s) {
            if ($s->getStayObj()->getStay() == 'r') {
                $occs++;
            }
        }

        if ($occs < $roomChooser->getSelectedResource()->getMaxOccupants()) {

            $this->reserveData
                    ->setArrivalDT($this->reserveData->getSpanStartDT())
                    ->setDepartureDT($this->reserveData->getSpanEndDT());

            $dataArray = $this->createExpDatesControl(TRUE, $this->reserveData->getSpanStartDT()->format('M j, Y'), $this->reserveData->getSpanEndDT()->format('M j, Y'));

        } else {
            $resvSectionHeaderPrompt = 'This room is already at its maximum occupancy.';
            $dataArray['hideCkinBtn'] = TRUE;
        }

        // Room Chooser
        $dataArray['rChooser'] = $roomChooser->createAddGuestMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN), $this->reserveData->getSpanStatus(), $occs);


        // Reservation status title
        $dataArray['rStatTitle'] = 'Checked Out - Adding Guests';

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Visit Notes', array('style'=>'font-weight:bold;'))
                , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%; font-size: 0.9em;', 'class'=>'hhk-panel'));


        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', $resvSectionHeaderPrompt, array('style'=>'float:left;font-size:.9em; margin-right: 1em;'))
                 . HTMLContainer::generateMarkup('span', '', array('id'=>'addGuestHeader', 'style'=>'float:left;'))
                , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }


    protected function addGuestStay(\PDO $dbh, $post) {

        $uS = Session::getInstance();
        $visitRs = new VisitRs();

        $this->initialSave($dbh, $post);

        if ($this->reserveData->hasError()) {
            return $this;
        }

        // Modified added guest arrival and depture dates.
        $guestArrDT = new \DateTime($this->reserveData->getArrivalDateStr('Y-m-d 10:0:0'));
        $guestDepDT = new \DateTime($this->reserveData->getDepartureDateStr('Y-m-d 10:0:0'));

        if ($guestArrDT > $guestDepDT) {
            // dates reversed...
            $this->reserveData->addError('Dates are reversed.  ');
            return;
        }

        // GEt visit record
        $stmt = $dbh->query("Select * from visit where idVisit = " . $this->reserveData->getIdVisit() . " and Span = " . $this->reserveData->getSpan());

        if ($stmt->rowCount() == 0) {
            throw new Hk_Exception_Runtime('Visit not found for reservation Id ' . $this->reserveData->getIdResv());
        }

        $vrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        EditRS::loadRow($vrows[0], $visitRs);

        // Set up comparison dates.
        $spanArrDT = new \DateTime($visitRs->Span_Start->getStoredVal());
        $spanArrDT->setTime(10, 0, 0);

        $spanDepDT = new \DateTime($visitRs->Span_End->getStoredVal());
        $spanDepDT->setTime(10, 0, 0);

        // Checking the stay arrival date
        if ($guestArrDT < $spanArrDT || $guestArrDT > $spanDepDT) {
            // Bad arrival Date
            $this->reserveData->setArrivalDateStr($visitRs->Arrival_Date->getStoredVal());
        }

        // Departure dates.
        if ($guestDepDT < $spanArrDT || $guestDepDT > $spanDepDT) {
            // Bad Departure Date
            $this->reserveData->setDepartureDateStr($visitRs->Span_End->getStoredVal());
        }


        // get the stays
        $stmts = $dbh->query("Select idName, idRoom, Span_Start_Date, Span_End_Date from stays where idVisit = " . $this->reserveData->getIdVisit() . " and Visit_Span = " . $this->reserveData->getSpan());

        // Count people staying during the new guest's time.
        $stays = array();

        while ($s = $stmts->fetch(\PDO::FETCH_ASSOC)) {

            $stayArrDT = new \DateTime($s['Span_Start_Date']);
            $stayArrDT->setTime(10, 0, 0);

            $stayDepDT = new \DateTime($s['Span_End_Date']);
            $stayDepDT->setTime(10, 0, 0);

            // Checking the dates
            if ($guestArrDT > $stayDepDT || $guestDepDT < $stayArrDT) {
                // out of bounds
                continue;
            }

            $stays[$s['idName']] = 1;
            $roomId = $s['idRoom'];
        }



        $addingMembers = array();

        // Guest aleady present?
        foreach ($this->getStayingMembers() as $m) {

            if (isset($stays[$m->getId()]) === FALSE) {

                // Ok to add
                $stayRS = new StaysRS();

                $stayRS->idName->setNewVal($m->getId());
                $stayRS->idVisit->setNewVal($visitRs->idVisit->getStoredVal());
                $stayRS->Visit_Span->setNewVal($visitRs->Span->getStoredVal());
                $stayRS->idRoom->setNewVal($roomId);
                $stayRS->Checkin_Date->setNewVal($this->reserveData->getArrivalDateStr());
                $stayRS->Checkout_Date->setNewVal($this->reserveData->getDepartureDateStr());
                $stayRS->Span_Start_Date->setNewVal($this->reserveData->getArrivalDateStr());
                $stayRS->Span_End_Date->setNewVal($this->reserveData->getDepartureDateStr());
                $stayRS->Expected_Co_Date->setNewVal($this->reserveData->getDepartureDateStr());
                $stayRS->Status->setNewVal($visitRs->Status->getStoredVal());
                $stayRS->Updated_By->setNewVal($uS->username);
                $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $addingMembers[] = $stayRS;
            }
        }

        // Amyone left?
        if (count($addingMembers) < 1) {
            $this->reserveData->addError('No one left to stay.  ');
            return;
        }

        // Get the resource
        $resc = null;
        if ($visitRs->idResource->getStoredVal() > 0) {
            $resc = Resource::getResourceObj($dbh, $visitRs->idResource->getStoredVal());
        } else {
            $this->reserveData->addError('Room not found.  ');
            return;
        }

        // Maximym occupants...
        $numOccupants = count($addingMembers) + count($stays);

        if ($numOccupants > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }

        // I guess that's it.
        foreach ($addingMembers as $a) {
            EditRS::insert($dbh, $a);
        }

        $this->resc = $resc;
        $this->visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        return;
    }

    protected function initialSave(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        // Save members, psg, hospital
        if ($this->family->save($dbh, $post, $this->reserveData, $uS->username) === FALSE) {
            return;
        }

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

    }
}



class StaticReservation extends ActiveReservation {

    public function addPerson(\PDO $dbh) {
        return array();
    }
}
