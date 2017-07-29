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

        if ($rData->getIdResv() === 0) {
            // Reservation not yet defined

            if ($rData->getId() > 0) {
                // Person defined
                return new ReserveSearcher($rData, new ReservationRS());
            } else {
                return new BlankReservation($rData, new ReservationRS());
            }

        } else {
            // Reservation defined.

            $rRs = new ReservationRS();
            $rRs->idReservation->setStoredVal($rData->getIdResv());
            $rows = EditRS::select($dbh, $rRs, array($rRs->idReservation));

            if (count($rows) != 1) {
                throw new Hk_Exception_Runtime("Reservation Id not found.  ");
            }

            EditRS::loadRow($rows[0], $rRs);

            if (Reservation_1::isActiveStatus($rRs->Status->getStoredVal())) {
                return new ActiveReservation($rData, $rRs);
            }

            if ($rRs->Status->getStoredVal() == ReservationStatus::Staying) {
                return new StayingReservation($rData, $rRs);
            }

            return new StaticReservation($rData, $rRs);

        }
    }

    public abstract function createMarkup(\PDO $dbh);
}


class ActiveReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

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


class ReserveSearcher extends Reservation {

    public function createMarkup(\PDO $dbh) {

        $psgChooserMarkup = '';
        $resvMarkup = '';

        // Search for a PSG
        if ($this->reserveData->getidPsg() == 0) {

            $ngRss = Psg::getNameGuests($dbh, $this->reserveData->getId());

            if (count($ngRss) == 1) {
                // Select psg
                $ngRs = $ngRss[0];
                $this->reserveData->setIdPsg($ngRs->idPsg->getStoredVal());
                $psgChooserMarkup = $this->psgChooserMkup($dbh, $ngRss);
            }
       }

       // if PSG is defined, search reservations.
       if ($this->reserveData->getidPsg() > 0) {

            $resvMarkup = $this->reservationChooser($dbh);

       }

       $data = $this->reserveData->toArray();
       $data['resvChooser'] = $resvMarkup;
       $data['psgChooser'] = $psgChooserMarkup;

       return $data;
    }

    protected function reservationChooser(\PDO $dbh) {

        $uS = Session::getInstance();

        $reservStatuses = $uS->guestLookups['ReservStatus'];

        $idPatient = 0;
        $mrkup = '';

        $stmt = $dbh->query("select * from vresv_patient "
            . "where Status in ('".ReservationStatus::Staying."','".ReservationStatus::Committed."','".ReservationStatus::Imediate."','".ReservationStatus::UnCommitted."','".ReservationStatus::Waitlist."') "
            . "and idPsg= " . $this->reserveData->getidPsg() . " order by `Expected_Arrival`");


        $trs = array();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($r['idPatient'] > 0) {
                $idPatient = $r['idPatient'];
            }

            $resvRs = new ReservationRS();
            EditRS::loadRow($r, $resvRs);

            $checkinNow = HTMLContainer::generateMarkup('a',
                        HTMLInput::generateMarkup('Open ' . $this->reserveData->getResvTitle(), array('type'=>'button', 'style'=>'margin-bottom:.3em;'))
                        , array('style'=>'text-decoration:none;margin-right:.3em;', 'href'=>'Referral.php?rid='.$resvRs->idReservation->getStoredVal()));

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

            $tbl->addHeaderTr(HTMLTable::makeTh('').HTMLTable::makeTh('Status').HTMLTable::makeTh('Room').HTMLTable::makeTh('Patient').HTMLTable::makeTh('Expected Arrival').HTMLTable::makeTh('Expected Departure')
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

            $attrs = array('type'=>'radio', 'value'=>$psg->getIdPsg(), 'id'=>'cbselpsg');
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


class BlankReservation extends Reservation {

    public function createMarkup(\PDO $dbh) {

    }

}