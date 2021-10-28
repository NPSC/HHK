<?php

namespace HHK\Member\RoleMember;

use HHK\AuditLog\VolunteerLog;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\Member\IndivMember;
use HHK\SysConst\{GLTableNames, RelLinkType, VolMemberType, VolRank, VolStatus};
use HHK\Tables\EditRS;
use HHK\Tables\Name\{NameRS, NameVolunteerRS};
use HHK\sec\Session;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\Labels;

/**
 * RoleMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoleMember
 *
 * @author Eric
 */


abstract class AbstractRoleMember extends IndivMember {

    protected $showBirthDate;
    protected $patientRelCode;

    public function __construct(\PDO $dbh, $defaultMemberBasis, $nid = 0, NameRS $nRS = NULL) {

        parent::__construct($dbh, $defaultMemberBasis, $nid, $nRS);

        $uS = Session::getInstance();

        if ($uS->LangChooser && $nid > 0) {
            $this->getLanguages($dbh, $nid);
        }

        if ($uS->InsuranceChooser && $nid > 0) {
            $this->getInsurance($dbh, $nid);
        }

        $this->showBirthDate = $uS->ShowBirthDate;

    }

    protected function translatePatRelTypes($patientRelationCodes) {
    	
    	$labels = Labels::getLabels();
    	$guestLabel = $labels->getString('MemberType', 'guest', 'Guest');
    	$relTypes = [];
    	
    	foreach ($patientRelationCodes as $tpe) {
    		
    		if ($tpe[0] != RelLinkType::Self) {
    			$tpe[2] = $guestLabel . 's';
    		}
    		
    		$relTypes[$tpe[0]] = $tpe;
    	}
    	
    	return $relTypes;
    }
    
    protected abstract function getMyMemberType();

    public static function createThinMarkupHdr($labels = NULL, $hideRelChooser = TRUE, $showBirthDate = TRUE, $showCopyDown = TRUE) {

        $lnCopyDownIcon = 'Last Name';

        if ($showCopyDown) {
            $lnCopyDownIcon = HTMLContainer::generateMarkup('ul'
                ,HTMLContainer::generateMarkup('li',
                   HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-arrowthick-1-s'))
                   , array('class'=>'ui-state-default ui-corner-all', 'id'=>'lnCopy', 'style'=>'float:right;', 'title'=>'Copy top last name down to any blank last names.'))
                .HTMLContainer::generateMarkup('span', 'Last Name', array('style'=>'float:right;margin-top:5px;margin-right:.4em;'))
            , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'));
        }

        $tr =
             HTMLTable::makeTh('Prefix')
            . HTMLTable::makeTh('First Name')
            . HTMLTable::makeTh('Middle')
            . HTMLTable::makeTh($lnCopyDownIcon)
            .HTMLTable::makeTh('Suffix')
            . HTMLTable::makeTh('Nickname')
            . ($showBirthDate ? HTMLTable::makeTh('Birth Date') : '');


        if ($hideRelChooser === FALSE) {

            $patTitle = 'Patient';

            if (is_string($labels)) {
                $patTitle = $labels;
            } else if ($labels instanceof Config_Lite) {
                $patTitle = $labels->getString('MemberType', 'patient', 'Patient');
            }

            $tr .= HTMLTable::makeTh('Relationship to ' . $patTitle, array('style'=>'font-size:.9em;'));
        }

        return $tr;
    }

    public function createMarkupHdr($labels = NULL, $hideRelChooser = TRUE) {

        return HTMLTable::makeTh('Id') . $this->createThinMarkupHdr($labels, $hideRelChooser, $this->showBirthDate, FALSE);

    }

    public function createMarkupRow($patientRelationship = '', $hideRelChooser = TRUE, $lockRelChooser = FALSE) {

        $uS = Session::getInstance();

        // Id
        $tr = HTMLTable::makeTd($this->get_idName() == 0 ? '' : $this->get_idName());

        // Name Prefix
        $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup(
                        $uS->nameLookups[GLTableNames::NamePrefix],
                        $this->nameRS->Name_Prefix->getstoredVal(), TRUE),
                array('data-prefix'=>$this->getIdPrefix(), 'name' => $this->getIdPrefix().'selPrefix')));

        $attrs = array('data-prefix'=>$this->getIdPrefix());

        // First Name
        $attrs['name'] = $this->getIdPrefix().'txtFirstName';
        $attrs['class'] = 'hhk-firstname';
        $attrs['autofocus'] = 'autofocus';
        $tr .= HTMLTable::makeTd(
                HTMLInput::generateMarkup(($this->get_idName() == 0 ? '' : $this->get_idName())
                        , array('name'=>$this->getIdPrefix().'idName', 'type'=>'hidden', 'class'=>'ignrSave'))
                .HTMLInput::generateMarkup($this->nameRS->Name_First->getstoredVal(), $attrs));

        // Middle Name
        $attrs['name'] = $this->getIdPrefix().'txtMiddleName';
        $attrs['size'] = '5';
        unset($attrs['class']);
        unset($attrs['autofocus']);
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Middle->getstoredVal(), $attrs));

        // Last Name
        $attrs['name'] = $this->getIdPrefix().'txtLastName';
        $attrs['class'] = 'hhk-lastname';
        unset($attrs['size']);
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Last->getstoredVal(), $attrs));

        // Suffix
        $attrs['name'] = $this->getIdPrefix().'selSuffix';
        unset($attrs['class']);
        $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GLTableNames::NameSuffix],
                        $this->nameRS->Name_Suffix->getstoredVal(), TRUE),
               $attrs));

        // Nick Name
        $attrs['name'] = $this->getIdPrefix().'txtNickname';
        $attrs['size'] = '12';
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Nickname->getstoredVal(), $attrs));

        // Birth Date
        if ($this->showBirthDate) {

            $bd = '';

            if ($this->nameRS->BirthDate->getStoredVal() != '') {
                $bd = date('M j, Y', strtotime($this->nameRS->BirthDate->getStoredVal()));
            }

            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($bd, array('name'=>$this->getIdPrefix().'txtBirthDate', 'class'=>'ckbdate')));

        }

        if ($hideRelChooser === FALSE) {

            $uS = Session::getInstance();

            $parray = $uS->guestLookups[GLTableNames::PatientRel]; // removeOptionGroups($uS->guestLookups[GL_TableNames::PatientRel]);

            // freeze control if patient is self.
            if ($lockRelChooser) {

                if ($patientRelationship == RelLinkType::Self) {
                    $parray = array($patientRelationship => $parray[$patientRelationship]);
                } else {
                    unset($parray[RelLinkType::Self]);
                }

                if ($patientRelationship == '') {
                    $allowEmpty = TRUE;
                } else {
                    $allowEmpty = FALSE;
                }
            } else {

                $allowEmpty = TRUE;
                if ($uS->PatientAsGuest === FALSE) {
                    unset($parray[RelLinkType::Self]);
                }
            }

            // Patient relationship
            $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                     HTMLSelector::doOptionsMkup($parray, $patientRelationship, $allowEmpty), array('name'=>$this->getIdPrefix() . 'selPatRel', 'data-prefix'=>$this->getIdPrefix(), 'class'=>'patientRelch')));
        }

        return $tr;
    }

    public function createThinMarkupRow() {

        $uS = Session::getInstance();

                // Name Prefix
        $tr = HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GLTableNames::NamePrefix],
                        $this->nameRS->Name_Prefix->getstoredVal(), TRUE),
                array('data-prefix'=>$this->getIdPrefix(), 'name' => $this->getIdPrefix().'selPrefix')));

        $attrs = array('data-prefix'=>$this->getIdPrefix());

        // First Name
        $attrs['name'] = $this->getIdPrefix().'txtFirstName';
        $attrs['class'] = 'hhk-firstname';
        $attrs['size'] = '16';
        $tr .= HTMLTable::makeTd(
                HTMLInput::generateMarkup(($this->get_idName() == 0 ? '' : $this->get_idName())
                        , array('name'=>$this->getIdPrefix().'idName', 'type'=>'hidden', 'class'=>'ignrSave'))
                .HTMLInput::generateMarkup($this->nameRS->Name_First->getstoredVal(), $attrs));

        // Middle Name
        $attrs['name'] = $this->getIdPrefix().'txtMiddleName';
        $attrs['size'] = '5';
        unset($attrs['class']);
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Middle->getstoredVal(), $attrs));

        // Last Name
        $attrs['name'] = $this->getIdPrefix().'txtLastName';
        $attrs['class'] = 'hhk-lastname';
        $attrs['size'] = '20';
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Last->getstoredVal(), $attrs));

        // Suffix
        $attrs['name'] = $this->getIdPrefix().'selSuffix';
        unset($attrs['class']);
        unset($attrs['size']);
        $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GLTableNames::NameSuffix],
                        $this->nameRS->Name_Suffix->getstoredVal(), TRUE),
               $attrs));

        // Nick Name
        $attrs['name'] = $this->getIdPrefix().'txtNickname';
        $attrs['size'] = '12';
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Nickname->getstoredVal(), $attrs));

        // Birth Date
        if ($this->showBirthDate) {

            $bd = '';

            if ($this->nameRS->BirthDate->getStoredVal() != '') {
                $bd = date('M j, Y', strtotime($this->nameRS->BirthDate->getStoredVal()));
            }

            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($bd, array('name'=>$this->getIdPrefix().'txtBirthDate', 'class'=>'ckbdate')));

        }

        return $tr;
    }

    public function saveChanges(\PDO $dbh, array $post) {

        $msg = '';
        $uS = Session::getInstance();

        $msg .= parent::saveChanges($dbh, $post);

        $this->saveMemberType($dbh, $uS->username);

        return $msg;
    }

    /**
     * Save the member type - 'vol_Types'
     *
     * @param \PDO $dbh
     * @param string $uname
     * @return boolean
     */
    public function saveMemberType(\PDO $dbh, $uname, $memberType = '') {

        if ($this->get_idName() === 0) {
            return FALSE;
        }

        if ($memberType != '') {
            $vcode = $memberType;
        } else {
            $vcode = $this->getMyMemberType();
        }

        $vcat = VolMemberType::VolCategoryCode;

        if ($vcat == "" || $vcode == "") {
            return FALSE;
        }

        $nvRS = new NameVolunteerRS();
        $nvRS->idName->setStoredVal($this->get_idName());
        $nvRS->Vol_Category->setStoredVal($vcat);
        $nvRS->Vol_Code->setStoredVal($vcode);

        $rows = EditRS::select($dbh, $nvRS, array($nvRS->idName, $nvRS->Vol_Category, $nvRS->Vol_Code));

        if (count($rows) > 0) {
            // exists
            EditRS::loadRow($rows[0], $nvRS);
            if ($nvRS->Vol_Status->getStoredVal() != VolStatus::Active) {
                // update status
                $nvRS->Vol_Begin->setNewVal(date('Y-m-d'));
                $nvRS->Vol_Status->setNewVal(VolStatus::Active);

                $rc = EditRS::update($dbh, $nvRS, array($nvRS->idName,$nvRS->Vol_Category,$nvRS->Vol_Code));
                if ($rc > 0) {
                    VolunteerLog::writeUpdate($dbh, $nvRS, $this->get_idName(), $uname);
                }
            }
        } else {
            // create
            $nvRS = new NameVolunteerRS();
            $nvRS->idName->setNewVal($this->get_idName());
            $nvRS->Vol_Category->setNewVal($vcat);
            $nvRS->Vol_Code->setNewVal($vcode);
            $nvRS->Vol_Rank->setNewVal(VolRank::Member);
            $nvRS->Vol_Begin->setNewVal(date('Y-m-d'));
            $nvRS->Vol_Status->setNewVal(VolStatus::Active);

            EditRS::insert($dbh, $nvRS);
            VolunteerLog::writeInsert($dbh, $nvRS, $this->get_idName(), $uname);
        }

        return TRUE;
    }

    public function setPatientRelCode($v) {
        $this->patientRelCode = $v;
    }

    public function getPatientRelCode() {
        return $this->patientRelCode;
    }

}

?>