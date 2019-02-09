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

    public function createThinMarkup(PSGMember $mem, $lockRelChooser) {

        $uS = Session::getInstance();

        $td = $this->createStayMarkup($mem);

        // Phone
        $ph = HTMLTable::makeTd($this->getPhonesObj()->get_Data()['Phone_Num']);

        $mu =  $td . $this->roleMember->createThinMarkupRow($this->patientRelationshipCode, FALSE, $lockRelChooser) . $ph;


        if ($uS->PatientAddr || ($uS->PatientAsGuest && $uS->GuestAddr)) {
            // Address
            $mu .= HTMLTable::makeTd(
                    HTMLContainer::generateMarkup('ul'
                        , HTMLContainer::generateMarkup('li',
                                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check'))
                                , array('class'=>'ui-state-highlight ui-corner-all hhk-AddrFlag', 'data-pref'=>$this->getRoleMember()->getIdPrefix(), 'id'=>$this->getRoleMember()->getIdPrefix().'liaddrflag'))
                        . HTMLContainer::generateMarkup('li',
                                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-circle-triangle-n'))
                                , array('class'=>'ui-state-default ui-corner-all hhk-togAddr', 'data-pref'=>$this->getRoleMember()->getIdPrefix(), 'id'=>$this->getRoleMember()->getIdPrefix().'toggleAddr', 'title'=>'Open - Close Address Section'))
                        , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'))
                    , array('style'=>'text-align:center;min-width:50px;', 'class'=>'hhk-ui-icons')
            );

        } else {
            $mu .= HTMLTable::makeTd('');
        }

        return $mu;
    }

    public function createStayMarkup(PSGMember $stay) {

        $uS = Session::getInstance();
        $td = '';

        // Staying button
        if ($this->getNoReturn() != '') {

            // Set for no return
            $td = HTMLTable::makeTd('No Return', array('title'=>$this->getNoReturn() . ';  Id: ' . $this->getIdName()), array('colspan'=>'2'));

        } else {

            $stBtn = '';
            if ($uS->PatientAsGuest) {
                $stBtn = $stay->getStayObj()->createStayButton($this->getRoleMember()->getIdPrefix());
            }

            $td = HTMLTable::makeTd($stBtn
                    , array('title'=>'Id: ' . $this->getIdName(), 'id'=>'sb' . $this->getRoleMember()->getIdPrefix()))
                . HTMLTable::makeTd($stay->createPrimaryGuestRadioBtn($this->getRoleMember()->getIdPrefix()));
        }

        return $td;
    }

}

