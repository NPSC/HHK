<?php

namespace HHK\House\Reservation;

use HHK\House\Hospital\{Hospital, HospitalStay};
use HHK\House\Family\{Family, FamilyAddGuest, JoinNewFamily};
use HHK\House\HouseServices;
use HHK\House\Registration;
use HHK\House\RepeatReservations;
use HHK\House\ReserveData\ReserveData;
use HHK\House\ReserveData\PSGMember\{PSGMember, PSGMemStay, PSGMemVisit, PSGMemResv};
use HHK\House\Room\RoomChooser;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Purchase\{FinAssistance, CheckinCharges, PaymentChooser, RateChooser};
use HHK\House\Vehicle;
use HHK\HTMLControls\{HTMLContainer, HTMLSelector, HTMLTable, HTMLInput};
use HHK\SysConst\{GLTableNames, ItemPriceCode, ReservationStatus, RoomRateCategories, VisitStatus, DefaultSettings};
use HHK\Tables\EditRS;
use HHK\Tables\Reservation\{Reservation_GuestRS, ReservationRS};
use HHK\sec\{Labels, SecurityComponent, Session};
use HHK\Exception\{RuntimeException, NotFoundException};
use HHK\Payment\PaymentResult\PaymentResult;



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
    /**
     * Summary of reservRs
     * @var ReservationRS
     */
    protected $reservRs;

    /**
     * Summary of family
     * @var Family
     */
    protected $family;

    /**
     * Summary of payResult
     * @var PaymentResult
     */
    protected $payResult;

    /**
     * Summary of cofResult
     * @var
     */
    protected $cofResult;


    /**
     * Summary of __construct
     * @param \HHK\House\ReserveData\ReserveData $reserveData
     * @param mixed $reservRs
     * @param mixed $family
     */
    function __construct(ReserveData $reserveData, $reservRs, $family) {

        $this->reserveData = $reserveData;
        $this->reservRs = $reservRs;
        $this->family = $family;
    }

    /**
     * Summary of reservationFactoy
     * @param \PDO $dbh
     * @param array $post
     * @throws \HHK\Exception\RuntimeException
     * @return ActiveReservation|CheckedoutReservation|DeletedReservation|Reservation|ReserveSearcher|StaticReservation|StayingReservation
     */
    public static function reservationFactoy(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $rData = new ReserveData($post);

        // idPsg < 0
        if ($rData->getForceNewPsg()) {

            // Force new PSG, also implies new reservation
            $rData->setIdResv(0);

            return new Reservation($rData, new ReservationRS(), new JoinNewFamily($dbh, $rData, $uS->EmergContactReserv));
        }

        // idResv < 0
        if ($rData->getForceNewResv()) {

            if ($rData->getIdPsg() > 0) {
                // Force New Resv for existing PSG
                return new ActiveReservation($rData, new ReservationRS(), new Family($dbh, $rData, $uS->EmergContactReserv));

            } else {

                throw new RuntimeException("Reservation parameters are invalid.  ");
            }
        }

        // Resv > 0
        if ($rData->getIdResv() > 0) {
            return self::loadReservation($dbh, $rData);
        }



        // idResv = 0 ------------------------------

        $hasNameGuestRecord = FALSE;

        // if we have a member id, is them in the name_guest table?
        if ($rData->getId() > 0) {
        	$stmt = $dbh->query("Select count(*) from name_guest where idName = " . $rData->getId());
        	$rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        	if ($rows[0][0] > 0) {
        		$hasNameGuestRecord = TRUE;
        	}
        }

        // Guest has a name_guest record, which means they has one or more psg's
        if ($rData->getIdPsg() > 0 || $hasNameGuestRecord) {
            return new ReserveSearcher($rData, new ReservationRS(), new Family($dbh, $rData, $uS->EmergContactReserv));
        }


        // idPsg = 0; idResv = 0;
        return new Reservation($rData, new ReservationRS(), new Family($dbh, $rData, $uS->EmergContactReserv));

    }

    /**
     * Summary of loadReservation
     * @param \PDO $dbh
     * @param \HHK\House\ReserveData\ReserveData $rData
     * @throws \HHK\Exception\NotFoundException
     * @return ActiveReservation|CheckedoutReservation|DeletedReservation|StaticReservation|StayingReservation
     */
    public static function loadReservation(\PDO $dbh, ReserveData $rData) {

    	$uS = Session::getInstance();

    	// Load reservation
        $stmt = $dbh->query("SELECT r.*, rg.idPsg, ifnull(v.idVisit, 0) as idVisit, ifnull(v.`Status`, '') as `SpanStatus`, ifnull(v.Span_Start, '') as `SpanStart`,
            ifnull(v.Span_End, datedefaultnow(v.Expected_Departure)) as `SpanEnd`
FROM reservation r
        LEFT JOIN
    registration rg ON r.idRegistration = rg.idRegistration
	    LEFT JOIN
    visit v on v.idReservation = r.idReservation and v.Span = 0

WHERE r.idReservation = " . $rData->getIdResv());

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) != 1) {
            // Deleted?
            $stmt = $dbh->query("Select max(idReservation) from reservation;");
            $rows = $stmt->FetchAll(\PDO::FETCH_NUM);

            if ($rData->getIdResv() > 0 && count($rows) > 0 && $rows[0][0] > $rData->getIdResv()) {
                // Reserv has been deleted.
                return new DeletedReservation($rData, NULL, NULL);
            } else {
                // Something else borke.
                throw new NotFoundException("Reservation not found.  ");
            }
        }

        $rRs = new ReservationRS();
        EditRS::loadRow($rows[0], $rRs);

        $rData->setIdPsg($rows[0]['idPsg']);
        $rData->setIdVisit($rows[0]['idVisit'])
            ->setSpanStatus($rows[0]['SpanStatus'])
            ->setSpanStartDT($rows[0]['SpanStart'])
            ->setSpanEndDT($rows[0]['SpanEnd'])
            ->setIdHospital_Stay($rows[0]['idHospital_Stay'])
            ->setidReferralDoc($rows[0]['idReferralDoc'])
            ->setResvStatusCode($rows[0]['Status']);

        // Get Resv status codes
        $reservStatuses = readLookups($dbh, "ReservStatus", "Code");

        if (isset($reservStatuses[$rData->getResvStatusCode()])) {
            $rData->setResvStatusType($reservStatuses[$rData->getResvStatusCode()]['Type']);
        }

        if (Reservation_1::isActiveStatus($rData->getResvStatusCode(), $reservStatuses)) {
            return new ActiveReservation($rData, $rRs, new Family($dbh, $rData, $uS->EmergContactReserv));
        }

        if ($rData->getResvStatusCode() == ReservationStatus::Staying) {
        	$rData->setInsistCkinDemog($uS->InsistCkinDemog);
            return new StayingReservation($rData, $rRs, new FamilyAddGuest($dbh, $rData, TRUE));
        }

        if ($rData->getResvStatusCode() == ReservationStatus::Checkedout) {
            return new CheckedoutReservation($rData, $rRs, new Family($dbh, $rData));
        }

        // Turned away, cancelled, etc.
        return new StaticReservation($rData, $rRs, new Family($dbh, $rData, $uS->EmergContactReserv));

    }

    /**
     * Summary of checkVisitDates
     * @param mixed $checkinHour
     * @return void
     */
    protected function checkVisitDates($checkinHour) {

        $today = new \DateTime();
        $hourNow = intval($today->format('H'));
        $minuteNow = intval($today->format('i'));

        $today->setTime(0, 0, 0);

        $tonight = new \DateTime();
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

    /**
     * Summary of createMarkup
     * @param \PDO $dbh
     * @return array
     */
    public function createMarkup(\PDO $dbh) {

        // Add the family, hospital, etc sections.
        $this->createDatesMarkup();
        $this->createHospitalMarkup($dbh);
        $this->createFamilyMarkup($dbh);

        return $this->reserveData->toArray();

    }

    /**
     * Summary of createFamilyMarkup
     * @param \PDO $dbh
     * @param mixed $formUserData
     * @return array<string>|void
     */
    protected function createFamilyMarkup(\PDO $dbh, array $formUserData = []) {

        $this->family->setGuestsStaying($dbh, $this->reserveData, $this->reservRs->idGuest->getstoredVal());

        // Arrival and Departure dates
        if ($this->reserveData->getIdResv() > 0) {

            try {
                $arrivalDT = new \DateTime($this->reservRs->Expected_Arrival->getStoredVal());
                $departDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());

                $psgMembers = $this->reserveData->getPsgMembers();

                $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $arrivalDT, $departDT, $this->reserveData->getResvTitle()));
                $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $arrivalDT, $this->reserveData->getIdPsg(), $departDT, $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

                $this->reserveData->setPsgMembers($psgMembers);

            } catch (RuntimeException $hex) {
                return array('error'=>$hex->getMessage());
            }
        }

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData, $formUserData));

    }

    /**
     * Summary of createDatesMarkup
     * @param string $lastVisitMU
     * @return void
     */
    protected function createDatesMarkup($lastVisitMU = '') {

        if ($this->reservRs->Expected_Arrival->getStoredVal() != '' && $this->reservRs->Expected_Departure->getStoredVal() != '') {

            $expArrDT = new \DateTime($this->reservRs->Expected_Arrival->getStoredVal());
            $expDepDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());

            $this->reserveData
                    ->setArrivalDT($expArrDT)
                    ->setDepartureDT($expDepDT);

        } else if ($this->reservRs->Expected_Arrival->getStoredVal() == '') {

        	$uS = Session::getInstance();
        	$nowDT = new \DateTime();
        	$extendHours = intval($uS->ExtendToday);


        	if ($extendHours > 0 && $extendHours < 9 && intval($nowDT->format('H')) <= $extendHours) {
        		$nowDT->sub(new \DateInterval('P1D'));
        		$nowDT->setTime(16, 0);
        		$this->reserveData->setArrivalDT($nowDT);
        	}

        }

        // Resv Expected dates
        $this->reserveData->setExpectedDatesSection($this->createExpDatesControl(TRUE, FALSE, FALSE, $lastVisitMU));

    }

    /**
     * Summary of createHospitalMarkup
     * @param \PDO $dbh
     * @param mixed $refHospital
     * @return void
     */
    protected function createHospitalMarkup(\PDO $dbh, array $refHospital = []) {

        //get hospitalStay from reservation
        $hospitalStay = new HospitalStay($dbh, $this->family->getPatientId(), $this->reserveData->getIdHospital_Stay());

        $this->reserveData->setHospitalSection(Hospital::createReferralMarkup($dbh, $hospitalStay, TRUE, $refHospital));

    }

    /**
     * Summary of initialSave
     * @param \PDO $dbh
     * @param mixed $post
     * @return void
     */
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
        } catch (RuntimeException $hex) {
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

    /**
     * Summary of save
     * @param \PDO $dbh
     * @param mixed $post
     * @return ActiveReservation
     */
    public function save(\PDO $dbh, $post) {

        $newResv = new ActiveReservation($this->reserveData, $this->reservRs, $this->family);
        $newResv->save($dbh, $post);
        return $newResv;

    }

    /**
     * Summary of delete
     * @param \PDO $dbh
     * @return array<string>
     */
    public function delete(\PDO $dbh) {
        // Get labels
        $labels = Labels::getLabels();
        $uS = Session::getInstance();

        if ($this->reserveData->getIdResv() > 0) {

            $resv = Reservation_1::instantiateFromIdReserv($dbh, $this->reserveData->getIdResv());

            if ($resv->getStatus() == ReservationStatus::Staying || $resv->getStatus() == ReservationStatus::Checkedout) {

                $dataArray['warning'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' status "' . $resv->getStatusTitle($dbh) . '" cannot be deleted';
            } else {
                // Okay to delete
                $resv->deleteMe($dbh, $uS->username);

                $dataArray['deleted'] = 'This ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' is Deleted.';
            }
        } else {
            $dataArray['warning'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' Id is not valid.  ';
        }

        return $dataArray;
    }

    /**
     * Summary of addPerson
     * @param \PDO $dbh
     * @return array
     */
    public function addPerson(\PDO $dbh) {

        $psgMembers = $this->reserveData->getPsgMembers();

        $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT(), $this->reserveData->getResvTitle()));
        $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getIdPsg(), $this->reserveData->getSpanEndDT(), $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

        $this->reserveData->setPsgMembers($psgMembers);

        $this->reserveData->setAddPerson($this->family->createAddPersonMu($dbh, $this->reserveData));
        return $this->reserveData->toArray();
    }

    /**
     * Summary of updateAgenda
     * @param \PDO $dbh
     * @param mixed $post
     * @return array
     */
    public static function updateAgenda(\PDO $dbh, $post) {

        // decipher posts
        if (isset($post['dt1']) && isset($post['dt2']) && isset($post['mems'])) {

            $labels = Labels::getLabels();
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
            $postMems = filter_var_array($post['mems'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            try {
                $arrivalDT = new \DateTime(filter_var($post['dt1'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                $departDT = new \DateTime(filter_var($post['dt2'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            } catch(\Exception $ex) {
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

        return [];
    }

    /**
     * Summary of createExpDatesControl
     * @param mixed $updateOnChange
     * @param mixed $startDate
     * @param mixed $endDate
     * @param mixed $lastVisitMU
     * @return array
     */
    protected function createExpDatesControl($updateOnChange = TRUE, $startDate = FALSE, $endDate = FALSE, $lastVisitMU = '') {

        $uS = Session::getInstance();
        $nowDT = new \DateTime();
        $nowDT->setTime(0, 0, 0);

        $days = '';
        $prefix = '';
        $repetr = '';

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

        if ($uS->UseRepeatResv) {

        	$contents = HTMLContainer::generateMarkup('option', 'Week', array('value'=>'w'))
        			.HTMLContainer::generateMarkup('option', '2 Weeks', array('value'=>'2w'))
        			.HTMLContainer::generateMarkup('option', 'Month', array('value'=>'m'));

        	$repetr = HTMLContainer::generateMarkup('span', 'Repeat each '
        			. HTMLSelector::generateMarkup($contents, array('id'=>$prefix.'resvRepeatIndex', 'style'=>'display:inline;')) . ' for '
        			. HTMLInput::generateMarkup('', array('id'=>$prefix.'resvRepeatCycles', 'size'=>'4')) . ' cycles'
        			, array('style'=>'margin-left:1em;font-size:.9em;', 'id'=>$prefix.'resvRepeater'));
        }

        $mkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', 'Arrival: '.
                    HTMLInput::generateMarkup(($this->reserveData->getArrivalDateStr()), $cidAttr), array("class"=>"mb-2 mb-md-0 mr-3"))
                .HTMLContainer::generateMarkup('span', 'Expected Departure: '.
                    HTMLInput::generateMarkup(($this->reserveData->getDepartureDateStr()), array('name'=>$prefix.'gstCoDate', 'readonly'=>'readonly', 'size'=>'14'))
                    , array('class'=>'mb-2 mb-md-0 mr-3'))
                .HTMLContainer::generateMarkup('span', 'Expected Days: '.
                    HTMLInput::generateMarkup($days, array('name'=>$prefix.'gstDays', 'readonly'=>'readonly', 'size'=>'4', "style"=>"outline: none; border: none; background:none; font-weight:bold; color:#2e6e9e;"))
                    , array('class'=>'mb-2 mb-md-0 mr-3'))
        		.HTMLContainer::generateMarkup('span', $lastVisitMU, array('style'=>'font-size:.8em;'))
        		, array('style'=>'font-size:.9em; align-items: center;', 'id'=>$prefix.'spnRangePicker', "class"=>"hhk-flex hhk-flex-wrap"))
        		.$repetr;

        return array('mu'=>$mkup, 'defdays'=>$uS->DefaultDays, 'daysEle'=>$prefix.'gstDays', 'updateOnChange'=>$updateOnChange, 'startDate'=>$startDate, 'endDate'=>$endDate);

    }

    /**
     * Summary of createResvMarkup
     * @param \PDO $dbh
     * @param mixed $oldResv
     * @param mixed $prefix
     * @param mixed $refVehicle
     * @return array
     */
    protected function createResvMarkup(\PDO $dbh, $oldResv, $prefix = '', $refVehicle = []) {

        $uS = Session::getInstance();
        $labels = Labels::getLabels();

        $resv = new Reservation_1($this->reservRs);
        $showPayWith = FALSE;
        $statusText = $resv->getStatusTitle($dbh);
        $hideCheckinButton = TRUE;

        $reservStatuses = readLookups($dbh, "reservStatus", "Code");


        // Registration
        $reg = new Registration($dbh, $this->reserveData->getIdPsg());

        // active reservations
        if ($resv->isNew() === FALSE && $resv->isActive($reservStatuses)) {

            // Allow reservations to have many guests.
            $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
            $rateChooser = new RateChooser($dbh);

            $dataArray['rChooser'] = $roomChooser->CreateResvMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

            // Rooms array
            $dataArray['rooms'] = $roomChooser->makeRoomsArray();

            // Rate Chooser
            if ($uS->RoomPriceModel != ItemPriceCode::None || $uS->KeyDeposit || $uS->VisitFee) {

                $showPayWith = TRUE;

                // Room Rate Markup
                $dataArray['rate'] = $rateChooser->createResvMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'), $reg->getIdRegistration());

                // Making Reservation Pre-Payment
                if ($uS->AcceptResvPaymt && $resv->getIdReservation() > 0) {

                    // Pre-Payment Chooser

                    $checkinCharges = new CheckinCharges(0, $resv->getVisitFee(), 0);
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

                    $rows = $gwStmt->fetchAll(\PDO::FETCH_ASSOC);
                    $merchants = array();

                    if (count($rows) > 0) {

                        foreach ($rows as $r) {
                            $merchants[$r['idLocation']] = $r['Merchant'];
                        }
                    }

                    $paymentGateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $merchants);

                    // held amount.
                    $this->reserveData->setPrePayment(Reservation_1::getPrePayment($dbh, $resv->getIdReservation()));

                    $dataArray['pay'] = HTMLContainer::generateMarkup('div',
                        PaymentChooser::createPrePaymentMarkup($dbh, $resv->getIdGuest(), $resv->getIdReservation(), $reg->getIdRegistration(), $checkinCharges, $paymentGateway, $resv->getExpectedPayType(), $this->reserveData->getPrePayment(), $reg->getPreferredTokenId())
                        , array('style'=>'flex-basis: 100%;', 'name'=>'div-hhk-payments'));

                }

                // Credit card chooser

                $dataArray['cof'] = HTMLcontainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Credit Cards on File', array('style'=>'font-weight:bold;'))
                    . HouseServices::guestEditCreditTable($dbh, $reg->getIdRegistration(), $resv->getIdGuest(), 'g')
                    . HTMLInput::generateMarkup('Update Credit', array('type'=>'button','id'=>'btnUpdtCred', 'data-indx'=>'g', 'data-id'=>$resv->getIdGuest(), 'data-idreg'=>$reg->getIdRegistration(), 'style'=>'margin:5px;float:right;'))
                ,array('id'=>'upCreditfs', 'class'=>'hhk-panel ignrSave')), array('style'=>'display: inline-block', 'class'=>'mr-3'));


            }

            // Array with amount calculated for each rate.
            $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));

            if ($uS->VisitFee) {
                // Visit Fee Array
                $dataArray['vfee'] = $rateChooser->makeVisitFeeArray($dbh, $resv->getVisitFee());
            }

            // Vehicles
            if ($uS->TrackAuto) {
                $dataArray['vehicle'] = $this->vehicleMarkup($dbh, $refVehicle);
            }

            // Add room title to status title
            if ($resv->getStatus() == ReservationStatus::Committed) {
                $statusText .= ' for Room ' . $resv->getRoomTitle($dbh);
                $hideCheckinButton = FALSE;
            }

            $moaBalance = max(0, Registration::loadLodgingBalance($dbh, $resv->getIdRegistration()) - Registration::loadPrepayments($dbh, $resv->getIdRegistration()));

            // Reservation Data
            $dataArray['rstat'] = $this->createStatusChooser(
                $resv,
                $resv->getChooserStatuses($reservStatuses),
                $uS->nameLookups[GLTableNames::PayType],
                $labels,
                $showPayWith,
                $moaBalance);


            // Multiple reservations
            if ($uS->UseRepeatResv) {
                $dataArray['multiResv'] = RepeatReservations::createMultiResvMarkup($dbh, $resv);
            }

        } else if ($resv->isNew()) {

            // Allow reservations to have many guests.
            $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
            $roomChooser->setOldResvId($oldResv);

            $dataArray['rChooser'] = $roomChooser->CreateResvMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        } else if ($reservStatuses[$resv->getStatus()]['Type'] == '') {

            // Staying or checked out - cannot change resv status.
            $dataArray['rstat'] = '';

        } else {
            // Cancelled.

            // Allow to change reserv status.
            $dataArray['rstat'] = $this->createStatusChooser(
                $resv,
                $resv->getChooserStatuses($reservStatuses),
                $uS->nameLookups[GLTableNames::PayType],
                $labels,
                $showPayWith);
        }

        // Reservation status title
        $dataArray['rStatTitle'] = $statusText;
        $dataArray['hideCiNowBtn'] = $hideCheckinButton;

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'notesLabel', 'Reservation Notes'), array('style'=>'font-weight:bold;'))
                , array('id'=>'hhk-noteViewer', 'style'=>'width: 100%; font-size:0.9em;', 'class'=>'hhk-panel'));

        if ($uS->UseDocumentUpload) {
            // Reservation Docs
            $dataArray['docViewer'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', "Documents", array('style'=>'font-weight:bold;'))
                , array('id'=>'vDocs', 'style'=>'width: 100%; font-size:0.9em;', 'class'=>'hhk-panel'));
        }

        $dataArray['wlnotes'] = '';

        // Waitlist notes?
        if ($uS->UseWLnotes && $resv->getStatus() == ReservationStatus::Waitlist) {

            $dataArray['wlnotes'] = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $this->reserveData->getWlNotesLabel(), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $resv->getCheckinNotes(), array('name'=>'taCkinNotes', 'rows'=>'2', 'style'=>'width:100%'))
                , array('class'=>'hhk-panel', "style"=>"width:100%"));
        }

        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', ($resv->isNew() ? 'New ' : '') . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' - ')
                .HTMLContainer::generateMarkup('span', ($resv->isNew() ? '' : $statusText), array('id'=>$prefix.'spnResvStatus', 'style'=>'margin-right: 1em;'))
                , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    /**
     * Summary of vehicleMarkup
     * @param \PDO $dbh
     * @param mixed $refVehicle
     * @return string
     */
    protected function vehicleMarkup(\PDO $dbh, array $refVehicle = []) {

        $regId = $this->reservRs->idRegistration->getStoredVal();

        $reg = new Registration($dbh, 0, $regId);

        $noVeh = $reg->getNoVehicle();

        if ($reg->isNew()) {
            $noVeh = '1';
        }

        return Vehicle::createVehicleMarkup($dbh, $reg->getIdRegistration(), $noVeh, $refVehicle);

    }

    /**
     * Summary of reservationChooser
     * @param \PDO $dbh
     * @param mixed $idResv
     * @return string
     */
    protected function reservationChooser(\PDO $dbh, $idResv = 0) {

        $uS = Session::getInstance();

        $reservStatuses = $uS->guestLookups['ReservStatus'];

        $mrkup = '';

        $stmt = $dbh->query("select * from vresv_patient "
            . "where Status in ('".ReservationStatus::Staying."','".ReservationStatus::Committed."','".ReservationStatus::UnCommitted."','".ReservationStatus::Waitlist."') "
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
                $checkinNow = HTMLInput::generateMarkup('Add ' . Labels::getString('MemberType', 'visitor', 'Guest'), array('type'=>'button', 'class'=>'hhk-checkinNow', 'data-rid'=>$resvRs->idReservation->getStoredVal()));
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

            while ($g = $gstmt->fetch(\PDO::FETCH_ASSOC)) {

                if ($fst) {
                    $name = $g['Name_Full'];
                    $fst = FALSE;
                } else {
                    $name = ', ' . $g['Name_Full'];
                }
                if ($g['Primary_Guest'] == 1) {
                    $names .= HTMLContainer::generateMarkup('span', $name, array('style'=>'font-weight:bold;', 'title'=>Labels::getString('MemberType', 'primaryGuest', 'Primary Guest')));
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

    /**
     *
     * @param Reservation_1 $resv
     * @param array $limResvStatuses
     * @param array $payTypes
     * @param Labels $labels
     * @param bool $showPayWith
     * @return string
     */
    public function createStatusChooser(Reservation_1 $resv, array $resvStatuses, array $payTypes, $labels, $showPayWith, $moaBalance = 0) {

        $uS = Session::getInstance();
        $tbl2 = new HTMLTable();

        // Pay option, verbal confirmation
        $attr = array('name'=>'cbVerbalConf', 'type'=>'checkbox');

        if ($resv->getVerbalConfirm() == 'v') {
            $attr['checked'] = 'checked';
        }

        $allResvStatuses = $resvStatuses;

        // Limit reservation status chooser options
        if ($resv->getStatus() == ReservationStatus::Committed) {
            unset($resvStatuses[ReservationStatus::Waitlist]);
        } else if ($resv->getStatus() == ReservationStatus::Waitlist || $resv->isRemoved($resvStatuses)) {
            unset($resvStatuses[ReservationStatus::Committed]);
        }

        // Table headers
        $tbl2->addBodyTr(
                ($showPayWith ? HTMLTable::makeTh('Pay With') : '')
                .($moaBalance > 0 ? HTMLTable::makeTh('MOA Balance') : '')
                .HTMLTable::makeTh('Verbal Affirmation')
                .($resv->getStatus() == ReservationStatus::UnCommitted ? HTMLTable::makeTh('Status', array('class'=>'ui-state-highlight')) : HTMLTable::makeTh('Status'))
                );

        $tbl2->addBodyTr(
                ($showPayWith ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($payTypes), $resv->getExpectedPayType()), array('name'=>'selPayType'))) : '')
            .($moaBalance > 0 ? HTMLTable::makeTd('$'.number_format($moaBalance, 2), array('style'=>'text-align:center;')) : '')
            .($resv->isActive($allResvStatuses) ? HTMLTable::makeTd(HTMLInput::generateMarkup('', $attr), array('style'=>'text-align:center;')) : HTMLTable::makeTd(''))
                .HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resvStatuses, $resv->getStatus(), FALSE), array('name'=>'selResvStatus', 'style'=>'float:left;margin-right:.4em;'))
                        .HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-comment hhk-viewResvActivity', 'data-rid'=>$resv->getIdReservation(), 'title'=>'View Activity Log', 'style'=>'cursor:pointer;float:right;')))
                );


        if ($uS->UseWLnotes === FALSE && $resv->isActive($allResvStatuses)) {
            $tbl2->addBodyTr(HTMLTable::makeTd('Registration Note:',array('class'=>'tdlabel')).HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea',$resv->getCheckinNotes(), array('name'=>'taCkinNotes', 'rows'=>'1', 'cols'=>'40')),array('colspan'=>'3')));
        }

        //Ribbon Note
        $tbl2->addBodyTr(HTMLTable::makeTd('Ribbon Note:',array('class'=>'tdlabel')).HTMLTable::makeTd(HTMLInput::generateMarkup($resv->getNotes(), array('name'=>'txtRibbonNote', 'maxlength'=>'20')),array('colspan'=>'3')));

        // Confirmation button  updated 5/20/2023:  add uncommitted to allowable statuses. #815
        $mk2 = '';
        if ($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::Waitlist || $resv->getStatus() == ReservationStatus::UnCommitted) {
            $mk2 .= HTMLInput::generateMarkup('Send Confirmation...', array('type'=>'button', 'id'=>'btnShowCnfrm', 'style'=>'margin:.3em;float:right;', 'data-rid'=>$resv->getIdReservation()));
        }

        // fieldset wrapper
        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'statusLabel', 'Reservation Status'), array('style'=>'font-weight:bold;'))
                    . $tbl2->generateMarkup() . $mk2,
                    array('class'=>'hhk-panel'))
            , array('style'=>'display: inline-block', 'class'=>'mr-3'));

    }

    /**
     * Summary of findConflictingStays
     * @param \PDO $dbh
     * @param mixed $psgMembers
     * @param mixed $arrivalDT
     * @param mixed $idPsg
     * @param mixed $departureDT
     * @param mixed $idVisit
     * @param mixed $idSpan
     * @return int
     */
    protected static function findConflictingStays(\PDO $dbh, array &$psgMembers, ?\DateTimeInterface $arrivalDT, $idPsg, ?\DateTimeInterface $departureDT, $idVisit = 0, $idSpan = -1) {

        $whStays = '';
        $rooms = array();

        // Dates correct?
        if (is_null($arrivalDT)) {
            return 0;
        }

        if (is_null($departureDT)) {
            $departureDT = new \DateTime($arrivalDT->format('Y-m-d H:i:s'));
            $departureDT->add(new \DateInterval('P1D'));
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
    and DATE(ifnull(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) > DATE('" . $arrivalDT->format('Y-m-d') . "')
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

    /**
     * Summary of findConflictingReservations
     * @param \PDO $dbh
     * @param mixed $idPsg
     * @param mixed $idResv
     * @param mixed $psgMembers
     * @param mixed $arrivalDT
     * @param mixed $departDT
     * @param mixed $resvPrompt
     * @return int
     */
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
            $departDT->add(new \DateInterval('P1D'));
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

    /**
     * Summary of getStayingMembers
     * @return array
     */
    protected function getStayingMembers() {

        $stayMembers = array();
        foreach ($this->reserveData->getPsgMembers() as $m) {
            if ($m->isStaying()) {
                $stayMembers[$m->getId()] = $m;
            }
        }
        return $stayMembers;
    }

    /**
     * Summary of setRoomRate
     * @param \PDO $dbh
     * @param \HHK\House\Registration $reg
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @param mixed $post
     * @return void
     */
    protected function setRoomRate(\PDO $dbh, Registration $reg, Reservation_1 &$resv, array $post) {

        $uS = Session::getInstance();

        // Room Rate
        $rateChooser = new RateChooser($dbh);

        // Default Room Rate category
        if ($uS->RoomPriceModel == ItemPriceCode::Basic) {
            $rateCategory = RoomRateCategories::Fixed_Rate_Category;
        } else if ($uS->RoomRateDefault != '') {
            $rateCategory = $uS->RoomRateDefault;
        } else {
            $rateCategory = DefaultSettings::Rate_Category;
        }


        // Get the rate category
        if (isset($post['selRateCategory']) && (SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN) || $uS->RateChangeAuth === FALSE)) {

            $rateCat = filter_var($post['selRateCategory'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($rateChooser->validateCategory($rateCat) === TRUE) {
                $rateCategory = $rateCat;
            }

        }else if(empty($resv->getRoomRateCategory())){
            // Look for an approved rate
            if ($reg->getIdRegistration() > 0 && $uS->IncomeRated) {

                $fin = new FinAssistance($dbh, $reg->getIdRegistration());

                if ($fin->isApproved() && $fin->getFaCategory() != '') {
                    $rateCategory = $fin->getFaCategory();
                }
            }
        }else{
            $rateCategory = $resv->getRoomRateCategory();
        }

        // Only assign the rate id if the category changes
        if ($resv->getRoomRateCategory() != $rateCategory) {
            $rateRs = $rateChooser->getPriceModel()->getCategoryRateRs(0, $rateCategory);
            $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());
        }

        $resv->setRoomRateCategory($rateCategory);

        // Fixed Rate and Rate Adjust Amount
        if ($rateCategory == RoomRateCategories::Fixed_Rate_Category) {

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

        } else if (isset($post['seladjAmount']) && $post['seladjAmount'] != 'keyed' && $post['seladjAmount'] != $resv->getIdRateAdjust() && (SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN) || $uS->RateChangeAuth === FALSE)) {

            // Save rate adjustment
            if(isset($uS->guestLookups['Room_Rate_Adjustment'][$post['seladjAmount']])){
                $idRateAdjust = $post['seladjAmount'];
                $rateAdjust = $uS->guestLookups['Room_Rate_Adjustment'][$post['seladjAmount']][2];
            }else{
                $rateAdjust = 0;
                $idRateAdjust = 0;
            }

            $resv->setIdRateAdjust($idRateAdjust);
            $resv->setRateAdjust($rateAdjust);

        }

        if (isset($post['selVisitFee']) && $uS->VisitFee) {

            $visitFeeOption = filter_var($post['selVisitFee'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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

    /**
     * Summary of setRoomChoice
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @param int $idRescPosted
     * @param array $reservStatuses
     * @return string
     */
    protected function setRoomChoice(\PDO $dbh, Reservation_1 &$resv, $idRescPosted, $reservStatuses = []) {

        $uS = Session::getInstance();

        if (count($reservStatuses) == 0) {
            $reservStatuses = readLookups($dbh, "reservStatus", "Code");
        }

        // Only resverations in active status can change rooms
        if ($resv->isActive($reservStatuses) === FALSE) {
            $this->reserveData->addError('Reservation is Inactive. ');
            return '';
        }

        if ($idRescPosted == 0 || $idRescPosted == 9999) {

            // Waitlisting the Reservation.
            $resv->setIdResource(0);
            $resv->setStatus(ReservationStatus::Waitlist);
            $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
            return 'Waitlisted  ';
        }

        $resv->getConstraints($dbh, true);
        $roomChooser = new RoomChooser($dbh, $resv, 1, new \DateTime($resv->getExpectedArrival()), new \DateTime($resv->getExpectedDeparture()));
        $resources = $roomChooser->findResources($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        // Does the resource fit the requirements?
        if (($resv->isActive($reservStatuses)) && isset($resources[$idRescPosted]) === FALSE) {

            //  room is in use
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

        return $resources[$idRescPosted]->optGroup;

    }

    /**
     * Summary of changeRoom
     * @param \PDO $dbh
     * @param mixed $idResv
     * @param mixed $idResc
     * @return array<string>
     */
    public function changeRoom(\PDO $dbh, $idResv, $idResc) {
        return array('error'=>"Changing this reservation's room is not allowed.");
    }

    /**
     * Summary of copyOldReservation
     * @param \PDO $dbh
     * @return mixed
     */
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

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

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

    /**
     * Summary of findLastVisit
     * @param \PDO $dbh
     * @param mixed $class
     * @return string
     */
    protected function findLastVisit(\PDO $dbh, $class = '') {

    	if ($this->reserveData->getIdPsg() < 1) {
    		return '';
    	}

    	$stmt = $dbh->query("select vi.idVisit, vi.Span, vi.Span_Start, vi.Span_End, vi.`Status`, g.Description as `Status_Title`, vi.idPrimaryGuest, r.Title as `Room`
	from visit vi left join resource r on vi.idResource = r.idResource
    left join gen_lookups g on g.Table_Name = 'Visit_Status' and g.Code = vi.`Status`
 where vi.Status not in ('".VisitStatus::Cancelled."', '".VisitStatus::Pending."') and vi.Span_Start =
	(SELECT  MAX(v.Span_Start)
		FROM visit v LEFT JOIN registration rg ON v.idRegistration = rg.idRegistration
	WHERE vi.Status not in ('".VisitStatus::Cancelled."', '".VisitStatus::Pending."') and rg.idPsg = " . $this->reserveData->getIdPsg() .")");

    	$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    	$mkup = '';

    	if (count($rows) > 0) {

    		if ($rows[0]['Span_End'] == '') {
    			$mkup = "(Current Visit in room " . $rows[0]['Room'] . ')';
    		} else {
    			$mkup = "(Last Visit Ended " . date('M d, Y', strtotime($rows[0]['Span_End'])) . " in room " . $rows[0]['Room'] . ')';
    		}

    	} else {
    		$mkup = HTMLContainer::generateMarkup('span', 'First House Visit', array('class'=>'ui-state-active', 'style'=>'padding:2px;'));
    	}

    	return $mkup;
    }

    /**
     * Summary of saveReservationGuests
     * @param \PDO $dbh
     * @return bool
     */
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

    /**
     * Summary of setDates
     * @param mixed $post
     * @throws \HHK\Exception\RuntimeException
     * @return void
     */
    public function setDates($post) {

        // Arrival and Departure dates
        $departure = '';
        $arrival = '';

        if (isset($post['gstDate'])) {
            $arrival = filter_var($post['gstDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        if (isset($post['gstCoDate'])) {
            $departure = filter_var($post['gstCoDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if ($arrival == '' || $departure == '') {
            throw new RuntimeException('Reservation dates not set.  ');
        }

        try {
            $this->reserveData->setArrivalDT(new \DateTime($arrival));
            $this->reserveData->setDepartureDT(new \DateTime($departure));
        } catch (\Exception $ex) {
            throw new RuntimeException('Something is wrong with one of the dates: ' . $ex->getMessage() . '.  ');
        }

    }
}
?>