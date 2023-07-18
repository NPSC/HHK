<?php

namespace HHK\House\Reservation;
use HHK\Exception\RuntimeException;
use HHK\House\Constraint\ConstraintsReservation;
use HHK\House\Constraint\ConstraintsVisit;
use HHK\House\Hospital\HospitalStay;
use HHK\House\Registration;
use HHK\House\ReserveData\ReserveData;
use HHK\House\Room\RoomChooser;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\RateChooser;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\SysConst\ReservationStatus;
use HHK\Tables\EditRS;
use HHK\Tables\Reservation\ReservationRS;
use HHK\Tables\Reservation\Reservation_GuestRS;
use HHK\Tables\Reservation\Reservation_MultipleRS;


class RepeatReservations {

    const WK_INDEX = 'P7D';
    const BI_WK_INDEX = 'P14D';
    const MONTH_INDEX = 'P1M';

    const MAX_REPEATS = '10';

    protected $errorArray;

    protected $children;

    /**
     * Summary of createMultiResvMarkup
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @return string
     */
    public function createMultiResvMarkup(\PDO $dbh, Reservation_1 $resv) {

        $markup = '';

        $stmt = $dbh->query("call multiple_reservations(" . $resv->getIdReservation() . ");");

        if ($stmt->rowCount() > 0) {

            $tbl = new HTMLTable();
            $tbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Status') . HTMLTable::makeTh('Room') . HTMLTable::makeTh('Arrival') . HTMLTable::makeTh('Departure'));


            // set up table
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $attr = ['href' => 'Reserve.php?rid=' . $r['idReservation']];

                if ($r['idReservation'] == $resv->getIdReservation()) {
                    $attr['class'] = 'ui-state-highlight';
                }

                $tbl->addBodyTr(
                    HTMLTable::makeTd(HTMLContainer::generateMarkup('a', 'Resv', $attr))
                    . HTMLTable::makeTd($r['Status'])
                    .HTMLTable::makeTd($r['Title'])
                    . HTMLTable::makeTd(date('D M j', strtotime($r['Arrival'])))
                    . HTMLTable::makeTd(date('D M j', strtotime($r['Departure'])))
                );

            }
            $stmt->nextRowset();

            $markup .= $tbl->generateMarkup();

        } else {

        // Set up empty host markup

        $days = $resv->getExpectedDays();

        // disable controls if this reservation is too long.
        $wkAttr = ['id'=>'mrweek', 'type'=>'radio', 'name'=>'mrInterval[' .self::WK_INDEX . ']'];
        if ($days > 6) {
            $wkAttr['disabled'] = 'disabled';
            $wkAttr['title'] = 'Reservation lasts too long.';
        }
        $biAttr = ['id'=>'mrbiweek', 'type'=>'radio', 'name'=>'mrInterval[' .self::BI_WK_INDEX . ']'];
        if ($days > 13) {
            $biAttr['disabled'] = 'disabled';
            $biAttr['title'] = 'Reservation lasts too long.';
        }
        $mAttr = ['id'=>'mrmonth', 'type'=>'radio', 'name'=>'mrInterval[' .self::MONTH_INDEX . ']'];
        if ($days > 26) {
            $mAttr['disabled'] = 'disabled';
            $mAttr['title'] = 'Reservation lasts too long.';
        }

        $tbl = new HTMLTable();
        $tbl->addBodyTr(
            HTMLTable::makeTh('Interval', array('rowspan'=>'2'))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Weekly', ['for'=>'mrweek']))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Bi-Weekly', ['for'=>'mrbiweek']))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Monthly', ['for'=>'mrmonth']))
        );

        // create radio button controls
        $tds = HTMLTable::makeTd(HTMLInput::generateMarkup('', $wkAttr), ['style'=>'text-align:center;']);
        $tds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $biAttr), ['style'=>'text-align:center;']);
        $tds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $mAttr), ['style'=>'text-align:center;']);
        $tbl->addBodyTr($tds);

        $tbl->addBodyTr(
            HTMLTable::makeTh('Create')
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['id'=>'mrnumresv', 'name'=>'mrnumresv', 'type'=>'number', 'min'=>'1', 'max'=> self::MAX_REPEATS, 'size'=>'4', 'style'=>'margin-right:.5em;'])
            . 'More Reservations', array('colspan'=>'5'))
        );

        $markup .= HTMLContainer::generateMarkup('div',
            $tbl->generateMarkup()
            , ['id'=>'divMultiResv']);

    }
        $mk1 = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Multiple Reservations', ['style'=>'font-weight:bold;'])
            . HTMLContainer::generateMarkup('p', '', ['id'=>'multiResvValidate', 'style'=>'color:red;'])
            . $markup, ['class'=>'hhk-panel']),
            ['style'=>'display: inline-block', 'class'=>'mr-3']);

        return $mk1;
    }

    /**
     * Summary of getHostChildren
     * @param \PDO $dbh
     * @param mixed $idResvHost
     * @return array
     */
    public static function getHostChildren(\PDO $dbh, $idResvHost) {

        $children = [];

        if ($idResvHost > 0) {

            $multipleRs = new Reservation_MultipleRS();
            $multipleRs->Host_Id->setStoredVal($idResvHost);

            $rows = EditRS::select($dbh, $multipleRs, [$multipleRs->Host_Id]);

            foreach ($rows as $r) {
                $children[$r['Child_Id']] = $r['Host_Id'];
            }
        }

        return $children;
    }

    /**
     * Summary of isRepeatHost
     * @param \PDO $dbh
     * @param mixed $idResvHost
     * @return bool
     */
    public static function isRepeatHost(\PDO $dbh, $idResvHost) {

        $children = self::getHostChildren($dbh, $idResvHost);
        if (count($children) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Summary of saveRepeats
     * @param \PDO $dbh
     * @param ReservationRS $reserveRS
     * @return void
     */
    public function saveRepeats(\PDO $dbh, $reserveRS) {

        $uS = Session::getInstance();
        $this->errorArray = [];
        $recurrencies = 0;
        $interval = '';

        $args = [
            'mrInterval' => [
                                'filter'=>FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                                'flags' => FILTER_REQUIRE_ARRAY
                            ],
            'mrnumresv'  => FILTER_SANITIZE_NUMBER_INT
        ];

        $inputs = filter_input_array(INPUT_POST, $args);

        // Verify inputs
        if (isset($inputs['mrnumresv']) && isset($inputs['mrInterval'])) {

            $recurrencies = intval($inputs['mrnumresv'], 10);
            $keys = array_keys($inputs['mrInterval']);
            $interval = $keys[0];

        } else {
            return;
        }

        // Inputs unset?
        if ($recurrencies < 1 || $interval == '') {
            return;
        }

        if ($recurrencies > self::MAX_REPEATS) {
            $this->errorArray[] = "The maximum number of repeating reservations (". self::MAX_REPEATS . ") is exceeded.";
        }

        $resv1 = new Reservation_1($reserveRS);
        $days = $resv1->getExpectedDays();

        $intervals = [self::WK_INDEX=>7, self::BI_WK_INDEX=>14, self::MONTH_INDEX=>27];

        // Check reserv length in days with interval value
        if (isset($intervals[$interval]) === false || $days >= $intervals[$interval]) {
            $this->errorArray[] = 'Reservation duration in days is greater than the requested Interval';
            return;
        }

        // Check for extended repeat - reservation already a host or child?



        // Create the reservations

        // guests
        $guests = [];
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setStoredVal($resv1->getIdReservation());
        $rgRows = EditRS::select($dbh, $rgRs, array($rgRs->idReservation));

        foreach ($rgRows as $g) {
            $guests[$g['idGuest']] = $g['Primary_Guest'];
        }

        // Dates
        $startDT = new \DateTimeImmutable($resv1->getArrival());
        $dateInterval = new \DateInterval($interval);
        $period = new \DatePeriod($startDT, $dateInterval, $recurrencies, \DatePeriod::EXCLUDE_START_DATE);

        $duration = new \DateInterval('P' . $days . 'D');

        foreach ($period as $dateDT) {

            $idResource = 0;
            $status = ReservationStatus::Waitlist;

            // Room available
            if ($resv1->getIdResource() > 0) {

                $resv1->getConstraints($dbh, true);
                $roomChooser = new RoomChooser($dbh, $resv1, 1, new \DateTime($resv1->getExpectedArrival()), new \DateTime($resv1->getExpectedDeparture()));
                $resources = $roomChooser->findResources($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

                // Does the resource fit the requirements?
                if (isset($resources[$resv1->getIdResource()])) {

                    // This room works.
                    $idResource = $resv1->getIdResource();
                    $status = $uS->InitResvStatus;

                }
            }

            $idResv = $this->makeNewReservation($dbh, $resv1, $dateDT, $dateDT->add($duration), $idResource, $status, $guests);

            if ($idResv > 0) {
                // record new child
                $numRows = $dbh->exec("Insert into reservation_multiple (`Host_Id`, `Child_Id`, `Status`) VALUES(" . $resv1->getIdReservation() . ", $idResv, 'a')");
                if ($numRows != 1) {
                    throw new RuntimeException('Insert faild: reservtion_multiple');
                }
            }
        }


    }

     /**
      * Summary of makeNewReservation
      * @param \PDO $dbh
      * @param \HHK\House\Reservation\Reservation_1 $protoResv
      * @param mixed $ckinDT
      * @param mixed $ckoutDT
      * @param int $idResource
      * @param string $status
      * @param mixed $guests
      * @return mixed
      */
    protected function makeNewReservation(\PDO $dbh, Reservation_1 $protoResv, $ckinDT, $ckoutDT, $idResource, $status, $guests) {

	    $uS = Session::getInstance();

	    // Room Rate
	    $rateChooser = new RateChooser($dbh);

	    // Room Rate category
        $rateCategory = $protoResv->getRoomRateCategory();
	    $rateRs = $rateChooser->getPriceModel()->getCategoryRateRs(0, $rateCategory);

        // Registration
	    $reg = new Registration($dbh, $protoResv->getIdPsg($dbh));

	    // Define the reservation.
        $resv = Reservation_1::instantiateFromIdReserv($dbh, 0);

        $resv->setExpectedArrival($ckinDT->format('Y-m-d'))
            ->setExpectedDeparture($ckoutDT->format('Y-m-d'))
            ->setIdGuest($protoResv->getIdGuest())
            ->setStatus($status)
            ->setIdHospitalStay($protoResv->getIdHospitalStay())
            ->setNumberGuests(count($guests))
            ->setIdResource($idResource)
            ->setRoomRateCategory($rateCategory)
            ->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());

        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $resv->getIdReservation());

        // Reservation Constraints
        $constraints = $protoResv->getConstraints($dbh);
        $myConstraints = new ConstraintsReservation($dbh, $resv->getIdReservation());
        $myConstraints->saveConstraints($dbh, $constraints->getActiveConstraintsArray());

        // Visit Constraints
        $constraints = $protoResv->getVisitConstraints($dbh);
        $myConstraints = new ConstraintsVisit($dbh, $resv->getIdReservation());
        $myConstraints->saveConstraints($dbh, $constraints->getActiveConstraintsArray());


        // Save Reservtaion guests - patient
        foreach ($guests as $g => $priGuestFlag) {

            $rgRs = new Reservation_GuestRS();
            $rgRs->idReservation->setNewVal($resv->getIdReservation());
            $rgRs->idGuest->setNewVal($g);
            $rgRs->Primary_Guest->setNewVal($priGuestFlag);

            EditRS::insert($dbh, $rgRs);

        }

        return $resv->getIdReservation();

	}


	/**
	 * @return mixed
	 */
	public function getErrorArray() {
		return $this->errorArray;
	}

    public function getChildren() {
        return $this->children;
    }
}