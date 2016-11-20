<?php
/**
 * Patient.php
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of Patient
 * @package name
 * @author Eric
 */
class Patient extends Role {

    protected function factory(PDO $dbh, $id) {
        $this->title = 'Patient';
        $this->patientPsg = new Psg($dbh, 0, $id);

        return new PatientMember($dbh, MemBasis::Indivual, $id);
    }


    protected function createNameMU($patientEditable = TRUE) {

        // Build name.
        $tbl = new HTMLTable();
        $tbl->addHeaderTr($this->name->createMarkupHdr());
        $tbl->addHeaderTr($this->name->createMarkupRow($patientEditable));

        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $this->title.' Name', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup()
                        . HTMLContainer::generateMarkup('div', $this->name->getContactLastUpdatedMU(new DateTime ($this->name->get_lastUpdated()), 'Name'), array('style'=>'float:right;'))
                        , array('class'=>'hhk-panel'))
                , array('style'=>'float:left; margin-right:.5em; font-size:.9em;'));

        return $mk1;
    }

    public function createReservationMarkup($patientEditable = TRUE) {


        // Name
        $mk1 = $this->createNameMU($patientEditable);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        $mk1 .= $this->createAddsBLock();

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('id'=>'patStayContainer'));

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));


        return HTMLContainer::generateMarkup('div', $mk1, array('class'=>'ui-widget ui-widget-content ui-corner-bottom hhk-panel hhk-tdbox'));
    }

    public function createMarkup($patientEditable = TRUE) {

        return $this->createReservationMarkup($patientEditable);
    }
}

