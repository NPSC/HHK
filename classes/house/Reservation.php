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

        $roomChooser = new RoomChooser($dbh, $resv, $resv->getNumberGuests(), $resv->getExpectedArrival(), $resv->getExpectedDeparture());
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
                HTMLContainer::generateMarkup('span', 'Reservation - ')
                .HTMLContainer::generateMarkup('span', $resv->getStatusTitle(), array('id'=>$prefix.'spnResvStatus', 'style'=>'margin-right: 1em;'))
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

        $family = new Family($this->reserveData);

        $this->reserveData = $family->save($dbh, $post);

        // Room number chosen
        $idRescPosted = 0;
        if (isset($post['selResource'])) {
            $idRescPosted = intval(filter_Var($post['selResource'], FILTER_SANITIZE_NUMBER_INT), 10);
        }


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
    
    public function addperson(\PDO $dbh) {
        return $this->createMarkup($dbh);
    }


    protected function reservationChooser(\PDO $dbh) {

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

            $resvRs = new ReservationRS();
            EditRS::loadRow($r, $resvRs);

            $checkinNow = HTMLContainer::generateMarkup('a',
                        HTMLInput::generateMarkup('Open ' . $this->reserveData->getResvTitle(), array('type'=>'button', 'style'=>'margin-bottom:.3em;'))
                        , array('style'=>'text-decoration:none;margin-right:.3em;', 'href'=>'Reserve.php?rid='.$resvRs->idReservation->getStoredVal().'&id='.$this->reserveData->getId()));

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

            $tbl->addHeaderTr(HTMLTable::makeTh('').HTMLTable::makeTh('Status').HTMLTable::makeTh('Room').HTMLTable::makeTh($this->reserveData->getPatLabel()).HTMLTable::makeTh('Expected Arrival').HTMLTable::makeTh('Expected Departure')
                    .HTMLTable::makeTh('# Guests'));

            $mrkup .= $tbl->generateMarkup();

        }

        return $mrkup;
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


