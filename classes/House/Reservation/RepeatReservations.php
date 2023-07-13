<?php

namespace HHK\House\Reservation;
use HHK\Exception\RuntimeException;
use HHK\House\Constraint\ConstraintsReservation;
use HHK\House\Constraint\ConstraintsVisit;
use HHK\House\Hospital\HospitalStay;
use HHK\House\Registration;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\RateChooser;
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

    /**
     * Summary of createMultiResvMarkup
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @return string
     */
    public static function createMultiResvMarkup(\PDO $dbh, Reservation_1 $resv) {

        $child = [];
        $markup = '';

        // Child_Id is unique in the table
        $multipleRs = new Reservation_MultipleRS();
        $multipleRs->Host_Id->setStoredVal($resv->getIdReservation());
        $multipleRs->Child_Id->setStoredVal($resv->getIdReservation());
        $rows = EditRS::select($dbh, $multipleRs, [$multipleRs->Host_Id, $multipleRs->Child_Id], 'OR');

        if (count($rows) > 0) {

            foreach ($rows as $r) {
                $child[$r['Child_Id']] = $r['Host_Id'];
            }

            if (isset($child[$resv->getIdReservation()])) {
                // I'm a child

                $markup = HTMLContainer::generateMarkup('div',
                'This is a Repeated Reservation.'
                , ['id'=>'divMultiResv']);

            } else {
                // Host Reservation
                $markup = HTMLContainer::generateMarkup('div',
                'This Reservation repeats ' . count($child) . ' times.'
                , ['id'=>'divMultiResv']);

            }

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
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['id'=>'mrnumresv', 'name'=>'mrnumresv', 'type'=>'number', 'min'=>'1', 'max'=> self::MAX_REPEATS, 'size'=>'4', 'style'=>'margin-right:.5em;']) . 'Reservations', array('colspan'=>'5'))
            );

            $markup = HTMLContainer::generateMarkup('div',
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

    public static function isRepeatHost(\PDO $dbh, $idResv) {

        if ($idResv > 0) {

            $multipleRs = new Reservation_MultipleRS();
            $multipleRs->Host_Id->setStoredVal($idResv);

            $rows = EditRS::select($dbh, $multipleRs, [$multipleRs->Host_Id]);

            if (count($rows) > 0) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Summary of saveRepeats
     * @param \PDO $dbh
     * @param ReservationRS $reserveRS
     * @return void
     */
    protected function saveRepeats(\PDO $dbh, $reserveRS) {

        $this->errorArray = [];
        $recurrencies = 0;
        $interval = '';

        $args = array(
            'mrInterval' => array(
                                'filter'=>FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                                'flags' => FILTER_REQUIRE_ARRAY
                            ),
            'mrnumresv'  => FILTER_SANITIZE_NUMBER_INT
        );

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
        if (isset($intervals[$interval]) && $days < $intervals[$interval]) {

            // Create the reservations

            // guests
            $guests = [];
            $rgRs = new Reservation_GuestRS();
            $rgRs->idReservation->setStoredVal($resv1->getIdReservation());
            $rgRows = EditRS::select($dbh, $rgRs, array($rgRs->idReservation));

            foreach ($rgRows as $g) {
                $guests[$g['idGuest']] = $g['Primary_Guest'];
            }

            $startDT = new \DateTimeImmutable($resv1->getArrival());
            $dateInterval = new \DateInterval($interval);
            $period = new \DatePeriod($startDT, $dateInterval, $recurrencies, \DatePeriod::EXCLUDE_START_DATE);

            $duration = new \DateInterval('P' . $days . 'D');

            foreach ($period as $dateDT) {

                $idResv = $this->makeNewReservation($dbh, $resv1, $dateDT, $dateDT->add($duration), $guests);

                if ($idResv > 0) {
                    // record new child
                    $numRows = $dbh->exec("Insert into reservation_multiple (`Host_Id`, `Child_Id`, `Status`) VALUES(" . $resv1->getIdReservation() . ", $idResv, 'a')");
                    if ($numRows != 1) {
                        throw new RuntimeException('Insert faild: reservtion_multiple');
                    }
                }
            }


        } else if (isset($intervals[$interval]) && $days >= $intervals[$interval]) {
            $this->errorArray[] = 'Reservation duration in days is greater than the requested Interval';
            return;
        }

    }

     /**
      * Summary of makeNewReservation
      * @param \PDO $dbh
      * @param \HHK\House\Reservation\Reservation_1 $resv
      * @param \DateTimeImmutable $ckinDT
      * @param \DateTimeImmutable $ckoutDT
      * @param array $guests
      * @return mixed
      */
    protected function makeNewReservation(\PDO $dbh, Reservation_1 $protoResv, $ckinDT, $ckoutDT, $guests) {

	    $uS = Session::getInstance();

	    // Room Rate
	    $rateChooser = new RateChooser($dbh);

	    // Room Rate category
        $rateCategory = $protoResv->getRoomRateCategory();

	    $rateRs = $rateChooser->getPriceModel()->getCategoryRateRs(0, $rateCategory);


	    $reg = new Registration($dbh, $protoResv->getIdPsg($dbh));
	    $hospStay = new HospitalStay($dbh, 0, $protoResv->getIdHospitalStay());

	    // Define the reservation.
        $resv = Reservation_1::instantiateFromIdReserv($dbh, 0);

        $resv->setExpectedArrival($ckinDT->format('Y-m-d'))
            ->setExpectedDeparture($ckoutDT->format('Y-m-d'))
            ->setIdGuest($hospStay->getIdPatient())
            ->setStatus(ReservationStatus::Waitlist)
            ->setIdHospitalStay($hospStay->getIdHospital_Stay())
            ->setNumberGuests(count($guests)+1)
            ->setIdResource(0)
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
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setNewVal($resv->getIdReservation());
        $rgRs->idGuest->setNewVal($hospStay->getIdPatient());
        $rgRs->Primary_Guest->setNewVal('1');
        EditRS::insert($dbh, $rgRs);

        foreach ($guests as $g => $priGuestFlag) {

            if ($g != $hospStay->getIdPatient()) {
                $rgRs = new Reservation_GuestRS();
                $rgRs->idReservation->setNewVal($resv->getIdReservation());
                $rgRs->idGuest->setNewVal($g);
                $rgRs->Primary_Guest->setNewVal($priGuestFlag);
                EditRS::insert($dbh, $rgRs);
            }
        }

        return $resv->getIdReservation();

	}


	/**
	 * @return mixed
	 */
	public function getErrorArray() {
		return $this->errorArray;
	}
}