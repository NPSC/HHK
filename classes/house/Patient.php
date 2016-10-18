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

    protected $psg;


    protected function factory(PDO $dbh, $id) {
        $this->title = 'Patient';
        return new PatientMember($dbh, MemBasis::Indivual, $id);
    }


    public function getPsgObj(PDO $dbh) {
        if (is_null($this->psg)) {
            $this->psg = new Psg($dbh, 0, $this->getIdName());
        }
        return $this->psg;
    }


    protected function createNameMU($patientEditable = TRUE) {

        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $this->title.' Name', array('style'=>'font-weight:bold;'))
                        . $this->name->createMarkupTable($patientEditable)
                        . HTMLContainer::generateMarkup('div', $this->name->getContactLastUpdatedMU(new DateTime ($this->name->get_lastUpdated()), 'Name'), array('style'=>'float:right;'))
                        , array('class'=>'hhk-panel'))
                , array('style'=>'float:left; margin-right:.5em; font-size:.9em;'));

        return $mk1;
    }

    public function isCurrentlyStaying(PDO $dbh, $id) {

        if ($id > 0) {

            $query = "select count(*) from stays where `Status` = :stat and idName = :id;";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(":stat"=>  VisitStatus::CheckedIn, ":id"=>$id));
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if (isset($rows) && $rows[0][0] > 0) {
                return TRUE;
            }
        }
        return FALSE;
    }


    public function createReservationMarkup($patientEditable = TRUE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getNameObj()->getIdPrefix();

        // Name
        $mk1 = $this->createNameMU($patientEditable);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        if ($uS->PatientAddr) {
            // Home Address
            $mk1 .= $this->createMailAddrMU($idPrefix . 'hhk-addr-val', TRUE, $uS->county);

            // Phone and email
            $mk1 .= $this->createPhoneEmailMU($idPrefix);
        }

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('id'=>'patStayContainer'));

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));


        return HTMLContainer::generateMarkup('div', $mk1, array('class'=>'ui-widget ui-widget-content ui-corner-bottom hhk-panel hhk-tdbox'));
    }

    public function createMarkup($patientEditable = TRUE) {

        return $this->createReservationMarkup($patientEditable);
    }
}

