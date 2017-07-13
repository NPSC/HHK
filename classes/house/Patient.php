<?php
/**
 * Patient.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Patient
 * @package name
 * @author Eric
 */
class Patient extends Role {

    protected function factory(PDO $dbh, $id) {
        $this->title = 'Patient';
        $this->patientPsg = NULL;
        $this->setPatientRelationshipCode(RelLinkType::Self);

        return new PatientMember($dbh, MemBasis::Indivual, $id);
    }

    public function getPatientPsg(PDO $dbh) {

        if (is_null($this->patientPsg)) {
            $this->patientPsg = new Psg($dbh, 0, $this->getIdName());
        }

        return $this->patientPsg;
    }


    protected function createNameMU() {

        // Build name.
        $tbl = new HTMLTable();
        $tbl->addHeaderTr($this->name->createMarkupHdr());
        $tbl->addBodyTr($this->name->createMarkupRow());

        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $this->title.' Name', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup()
                        . HTMLContainer::generateMarkup('div', $this->name->getContactLastUpdatedMU(new DateTime ($this->name->get_lastUpdated()), 'Name'), array('style'=>'float:right;'))
                        , array('class'=>'hhk-panel'))
                , array('style'=>'float:left; margin-right:.5em;margin-bottom:.4em; font-size:.9em;'));

        return $mk1;
    }


    public function createReservationMarkup($lockRelChooser = FALSE) {

        $uS = Session::getInstance();

        // Name
        $mk1 = $this->createNameMU();

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        if ($uS->PatientAddr) {
            $mk1 .= $this->createAddsBLock();
            $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));
        }

        return HTMLContainer::generateMarkup('div', $mk1, array('class'=>'ui-widget ui-widget-content ui-corner-bottom hhk-panel hhk-tdbox'));
    }

    public function createMarkup() {

        return $this->createReservationMarkup();
    }
}

