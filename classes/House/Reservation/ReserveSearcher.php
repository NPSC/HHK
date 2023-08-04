<?php

namespace HHK\House\Reservation;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\House\PSG;
use HHK\SysConst\MemStatus;

/**
 * Description of ReserveSearcher
 *
 * @author Eric
 */

class ReserveSearcher extends ActiveReservation {

    /**
     * Summary of createMarkup
     * @param \PDO $dbh
     * @return array
     */
    public function createMarkup(\PDO $dbh) {

        $data = $this->resvChooserMarkup($dbh);

        if (is_array($data)) {
            return $data;
        }

        return $this->reserveData->toArray();

    }

    /**
     * Summary of addPerson
     * @param \PDO $dbh
     * @return array
     */
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

    /**
     * Summary of save
     * @param \PDO $dbh
     * @return ActiveReservation
     */
    public function save(\PDO $dbh) {

        $newResv = new ActiveReservation($this->reserveData, $this->reservRs, $this->family);
        $newResv->save($dbh);
        return $newResv;

    }


    /**
     * Summary of resvChooserMarkup
     * @param \PDO $dbh
     * @return array|null
     */
    protected function resvChooserMarkup(\PDO $dbh) {
        $ngRss = [];

        // Search for a PSG
        if ($this->reserveData->getIdPsg() == 0) {
            // idPsg not set

            // Does this guest have a PSG?
            $ngRss = PSG::getNameGuests($dbh, $this->reserveData->getId());

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

    /**
     * Summary of psgChooserMkup
     * @param \PDO $dbh
     * @param array $ngRss
     * @param mixed $offerNew
     * @return string
     */
    protected function psgChooserMkup(\PDO $dbh, array $ngRss, $offerNew = TRUE) {

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Who is the ' . $this->reserveData->getPatLabel() . '?', array('colspan'=>'2')));

        $firstOne = TRUE;

        foreach ($ngRss as $n) {

            $psg = new PSG($dbh, $n->idPsg->getStoredVal());

            $patientStatus = $psg->getPatientStatus($dbh);

            $attrs = array('type'=>'radio', 'value'=>$psg->getIdPsg(), 'name'=>'cbselpsg', 'id'=>$psg->getIdPsg().'cbselpsg');
            if ($firstOne && $patientStatus != MemStatus::Deceased) {
                $attrs['checked'] = 'checked';
                $firstOne = FALSE;
            }

            //is deceased?
            if($patientStatus == MemStatus::Deceased){
                $trAttrs = array('style'=>'background-color: #ffc4c4');
                $labelAttrs = array();
                $status = " [DECEASED]";
            }else{
                $labelAttrs = array('class'=>'tdlabel');
                $trAttrs = array();
                $status = "";
            }

            $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', $psg->getPatientName($dbh) . $status, array('for'=>$psg->getIdPsg().'cbselpsg')), $labelAttrs)
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', $attrs)), $trAttrs);

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
?>