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

    public static function reservationFactoy(\PDO $dbh, ReserveData $rData) {

        // idPsg < 0
        if ($rData->getidPsg() < 0) {

            //new Psg
            $rData->setIdPsg(0);
            //new Resv
            $rData->setIdResv(0);

            return new BlankReservation($rData, new ReservationRS());

        // idResv < 0
        } else if ($rData->getIdResv() < 0 && $rData->getidPsg() > 0) {

            // New Resv
            $rData->setIdResv(0);

            return new BlankReservation($rData, new ReservationRS());

        // undetermined resv and psg, look at guest id
        } else if ($rData->getIdResv() == 0 && $rData->getidPsg() == 0) {

            // Depends on GUest Id
            if ($rData->getId() > 0) {
                // Search
                return new ReserveSearcher($rData, new ReservationRS());
            }

            // New resv, new psg, new guest
            return new BlankReservation($rData, new ReservationRS());


        // Guest, PSG, no reservation specified.
        } else if ($rData->getidPsg() > 0 && $rData->getIdResv() == 0) {

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

    protected function createExpDatesControl() {

        $cidAttr = array('name'=>'gstDate', 'readonly'=>'readonly', 'size'=>'14' );

        if ($this->reservRs->Expected_Arrival->getStoredVal() != '') {

            $nowDT = new \DateTime();
            $nowDT->setTime(0, 0, 0);

            $expArrDT = new \DateTime($this->reservRs->Expected_Arrival->getStoredVal());
            $expDepDT = new \DateTime($this->reservRs->Expected_Departure->getStoredVal());

            if (is_null($expArrDT) === FALSE && $expArrDT < $nowDT) {
                $cidAttr['class'] = ' ui-state-highlight';
            }
        }

        return HTMLContainer::generateMarkup('span',
                HTMLContainer::generateMarkup('span', 'Expected Check In: '.
                    HTMLInput::generateMarkup(($this->reservRs->Expected_Arrival->getStoredVal() == '' ? '' : $expArrDT->format('M j, Y')), $cidAttr))
                .HTMLContainer::generateMarkup('span', 'Expected Departure: '.
                    HTMLInput::generateMarkup(($this->reservRs->Expected_Departure->getStoredVal() == '' ? '' : $expDepDT->format('M j, Y')), array('name'=>'gstCoDate', 'readonly'=>'readonly', 'size'=>'14'))
                    , array('style'=>'margin-left:.7em;'))
                , array('style'=>'float:left;', 'id'=>'spnRangePicker'));

    }
}


class ActiveReservation extends BlankReservation {

    public function createMarkup(\PDO $dbh) {

        $data = parent::createMarkup($dbh);



        return $data;

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

        $family = new Family($dbh, $this->reserveData);

        $this->reserveData->setFamilySection($family->createFamilyMarkup($this->reservRs));

        $data = $this->reserveData->toArray();

        // Resv Expected dates
        $data['expDates'] = $this->createExpDatesControl();

        // Hospital
        $hospitalStay = new HospitalStay($dbh, $family->getPatientId());

        $data['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);


        return $data;
    }

}

class ReserveSearcher extends BlankReservation {

    public function createMarkup(\PDO $dbh) {

        $ngRss = array();

        // Search for a PSG
        if ($this->reserveData->getidPsg() == 0) {
            // idPsg not set

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

    protected function reservationChooser(\PDO $dbh) {

        $uS = Session::getInstance();

        $reservStatuses = $uS->guestLookups['ReservStatus'];

        $mrkup = '';

        $stmt = $dbh->query("select * from vresv_patient "
            . "where Status in ('".ReservationStatus::Staying."','".ReservationStatus::Committed."','".ReservationStatus::Imediate."','".ReservationStatus::UnCommitted."','".ReservationStatus::Waitlist."') "
            . "and idPsg= " . $this->reserveData->getidPsg() . " order by `Expected_Arrival`");


        $trs = array();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $resvRs = new ReservationRS();
            EditRS::loadRow($r, $resvRs);

            $checkinNow = HTMLContainer::generateMarkup('a',
                        HTMLInput::generateMarkup('Open ' . $this->reserveData->getResvTitle(), array('type'=>'button', 'style'=>'margin-bottom:.3em;'))
                        , array('style'=>'text-decoration:none;margin-right:.3em;', 'href'=>'Reserve.php?rid='.$resvRs->idReservation->getStoredVal()));

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

            $attrs = array('type'=>'radio', 'value'=>$psg->getIdPsg(), 'name'=>'cbselpsg');
            if ($firstOne) {
                $attrs['checked'] = 'checked';
                $firstOne = FALSE;
            }

            $tbl->addBodyTr(
                    HTMLTable::makeTd($psg->getPatientName($dbh), array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup('', $attrs)));

        }

        // Add new PSG choice
        if ($offerNew) {
            $tbl->addBodyTr(
                HTMLTable::makeTd('New ' . $this->reserveData->getPatLabel(), array('class'=>'tdlabel'))
               .HTMLTable::makeTd(HTMLInput::generateMarkup('-1', array('type'=>'radio', 'name'=>'cbselpsg', 'data-pid'=>'0', 'data-ngid'=>'0'))));
        }


        return $tbl->generateMarkup();
    }


}


