<?php

namespace HHK\House;
use HHK\House\Hospital\HospitalStay;
use HHK\House\Reservation\Reservation_1;
use HHK\House\ReserveData\ReserveData;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\RateChooser;
use HHK\sec\Session;
use HHK\SysConst\DefaultSettings;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\RoomRateCategories;
use HHK\Tables\EditRS;
use HHK\Tables\Reservation\Reservation_GuestRS;
use HHK\Tables\Reservation\ReservationRS;


class RepeatReservations {

    const WK_INDEX = 'w';
    const BI_WK_INDEX = 'bw';

    const DAY_30_INDEX = '30';

    const MONTH_INDEX = 'm';

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
        $mrStmt = $dbh->query("Select * from reservation_multiple where Host_Id = " . $resv->getIdReservation() . " OR Child_Id = " . $resv->getIdReservation());
        $rows = $mrStmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0) {

            foreach ($rows as $r) {
                $child[$r['Child_Id']] = $r['Host_Id'];
            }

            if (isset($child[$resv->getIdReservation()])) {
                // I'm a child
                $markup = HTMLContainer::generateMarkup('div',
                'This is a Repeated Reservation.'
                , array('id'=>'divMultiResv'));

            } else {
                $markup = HTMLContainer::generateMarkup('div',
                'This Reservation repeats ' . count($child) . ' times.'
                , array('id'=>'divMultiResv'));

            }

        } else {
            // Set up empty host markup

            $days = $resv->getExpectedDays();

            // disable controls if this reservation is too long.
            $wkAttr = array('id'=>'mrweek', 'type'=>'radio', 'name'=>'mrInterval[' .self::WK_INDEX . ']');
            if ($days > 6) {
                $wkAttr['disabled'] = 'disabled';
                $wkAttr['title'] = 'Reservation lasts too long.';
            }
            $biAttr = array('id'=>'mrbiweek', 'type'=>'radio', 'name'=>'mrInterval[' .self::BI_WK_INDEX . ']');
            if ($days > 13) {
                $biAttr['disabled'] = 'disabled';
                $biAttr['title'] = 'Reservation lasts too long.';
            }
            $d30Attr = array('id'=>'mr30days', 'type'=>'radio', 'name'=>'mrInterval[' .self::DAY_30_INDEX . ']');
            if ($days > 28) {
                $d30Attr['disabled'] = 'disabled';
                $d30Attr['title'] = 'Reservation lasts too long.';
            }
            $mAttr = array('id'=>'mrmonth', 'type'=>'radio', 'name'=>'mrInterval[' .self::MONTH_INDEX . ']');
            if ($days > 26) {
                $mAttr['disabled'] = 'disabled';
                $mAttr['title'] = 'Reservation lasts too long.';
            }

            $tbl = new HTMLTable();
            $tbl->addBodyTr(
                HTMLTable::makeTh('Interval', array('rowspan'=>'2'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Weekly', array('for'=>'mrweek')))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Bi-Weekly', array('for'=>'mrbiweek')))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('label', '30 Days', array('for'=>'mr30days')))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Monthly', array('for'=>'mrmonth')))
            );

            // create radio button controls
            $tds = HTMLTable::makeTd(HTMLInput::generateMarkup('', $wkAttr), array('style'=>'text-align:center;'));
            $tds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $biAttr), array('style'=>'text-align:center;'));
            $tds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $d30Attr), array('style'=>'text-align:center;'));
            $tds .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $mAttr), array('style'=>'text-align:center;'));
            $tbl->addBodyTr($tds);

            $tbl->addBodyTr(
                HTMLTable::makeTh('Create')
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id'=>'mrnumresv', 'name'=>'mrnumresv', 'type'=>'number', 'min'=>'1', 'max'=>'10', 'size'=>'4', 'style'=>'margin-right:.5em;')) . 'Reservations', array('colspan'=>'5'))
            );

            $markup = HTMLContainer::generateMarkup('div',
                $tbl->generateMarkup()
                , array('id'=>'divMultiResv'));

        }

        $mk1 = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Multiple Reservations', array('style'=>'font-weight:bold;'))
            . HTMLContainer::generateMarkup('p', '', array('id'=>'multiResvValidate', 'style'=>'color:red;'))
            . $markup, array('class'=>'hhk-panel')),
            array('style'=>'display: inline-block', 'class'=>'mr-3'));

        return $mk1;
    }

    /**
     * Summary of saveRepeats
     * @param \PDO $dbh
     * @param ReservationRS $reserveRS
     * @return void
     */
    public static function saveRepeats(\PDO $dbh, $reserveRS) {

        $args = array(
            'mrInterval' => array(
                                'filter'=>FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                                'flags' => FILTER_REQUIRE_ARRAY
                            ),
            'mrnumresv'  => FILTER_SANITIZE_NUMBER_INT
        );

        $inputs = filter_input_array(INPUT_POST, $args);

        if (isset($inputs['mrnumresv']) && isset($inputs['mrInterval'])) {
            $quantity = intval($inputs['mrnumresv'], 10);
            $keys = array_keys($inputs['mrInterval']);
        } else {
            return;
        }

        $resv1 = new Reservation_1($reserveRS);


    }

    protected function makeNewReservation(\PDO $dbh, PSG $psg, $ckinDT, $ckoutDT, array $guests) {

	    $uS = Session::getInstance();

	    // Reservation Checkin set?
	    if (is_null($ckinDT)) {
	        return 0;
	    }

	    // Replace missing checkout date with check-in + default days.
	    if (is_null($ckoutDT)) {

	    }

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

	    $rateRs = $rateChooser->getPriceModel()->getCategoryRateRs(0, $rateCategory);


	    $reg = new Registration($dbh, $psg->getIdPsg());
	    $hospStay = new HospitalStay($dbh, $psg->getIdPatient());

	    // Define the reservation.
        $resv = Reservation_1::instantiateFromIdReserv($dbh, 0);

        $resv->setExpectedArrival($ckinDT->format('Y-m-d'))
            ->setExpectedDeparture($ckoutDT->format('Y-m-d'))
            ->setIdGuest($psg->getIdPatient())
            ->setStatus(ReservationStatus::Waitlist)
            ->setIdHospitalStay($hospStay->getIdHospital_Stay())
            ->setNumberGuests(count($guests)+1)
            ->setIdResource(0)
            ->setRoomRateCategory($rateCategory)
            ->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());

        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);
        $resv->saveConstraints($dbh, array());

        // Save Reservtaion guests - patient
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setNewVal($resv->getIdReservation());
        $rgRs->idGuest->setNewVal($psg->getIdPatient());
        $rgRs->Primary_Guest->setNewVal('1');
        EditRS::insert($dbh, $rgRs);

        foreach ($guests as $g) {

            $rgRs = new Reservation_GuestRS();
            $rgRs->idReservation->setNewVal($resv->getIdReservation());
            $rgRs->idGuest->setNewVal($g);
            $rgRs->Primary_Guest->setNewVal('');
            EditRS::insert($dbh, $rgRs);
        }

        return $resv->getIdReservation();

	}

}