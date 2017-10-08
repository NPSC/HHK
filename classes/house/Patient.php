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

    public function __construct(\PDO $dbh, $idPrefix, $id, $title = 'Patient') {

        $this->currentlyStaying = NULL;
        $this->idVisit = NULL;
        $this->emergContact = NULL;
        $this->title = $title;
        $this->patientPsg = NULL;
        $this->setPatientRelationshipCode(RelLinkType::Self);

        $this->roleMember = new PatientMember($dbh, MemBasis::Indivual, $id);
        $this->roleMember->setIdPrefix($idPrefix);

        if ($this->roleMember->getMemberDesignation() != MemDesignation::Individual) {
            throw new Hk_Exception_Runtime("Must be individuals, not organizations");
        }

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
        $tbl->addHeaderTr($this->roleMember->createMarkupHdr(NULL, TRUE));
        $tbl->addBodyTr($this->roleMember->createMarkupRow('', TRUE));

        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $this->title.' Name', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup()
                        . HTMLContainer::generateMarkup('div', $this->roleMember->getContactLastUpdatedMU(new DateTime ($this->roleMember->get_lastUpdated()), 'Name'), array('style'=>'float:right;'))
                        , array('class'=>'hhk-panel'))
                , array('style'=>'float:left; margin-right:.5em;margin-bottom:.4em; font-size:.9em;'));

        return $mk1;
    }

    public function createThinMarkup($staying, $lockRelChooser) {

        $uS = Session::getInstance();

        $mu = parent::createThinMarkup($staying, TRUE);


        if ($uS->PatientAddr) {
            // Address
            $mu .= HTMLTable::makeTd(
                    HTMLContainer::generateMarkup('ul'
                            , HTMLContainer::generateMarkup('li',
                                    HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check'))
                                    , array('class'=>'ui-widget-header ui-corner-all hhk-AddrFlag ui-state-highlight', 'id'=>$this->getRoleMember()->getIdPrefix().'liaddrflag', 'style'=>'display:inline-block;cursor:pointer;')
                                    )
                            . HTMLContainer::generateMarkup('li',
                                    HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-circle-triangle-n'))
                                    , array('class'=>'ui-widget-header ui-corner-all hhk-togAddr', 'style'=>'display:inline-block;margin-left:5px;cursor:pointer;', 'title'=>'Open - Close Address Section')
                                    )
                            , array('data-pref'=>$this->getRoleMember()->getIdPrefix(), 'style'=>'padding-top:1px;list-style-type:none;', 'class'=>'ui-widget')
                            )
                    , array('style'=>'text-align:center;min-width:50px;')
                    );

        } else {
            $mu .= HTMLTable::makeTd('');
        }

        return $mu;
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

