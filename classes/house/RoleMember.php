<?php
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
abstract class RoleMember extends IndivMember {

    protected $showBirthDate;

    protected abstract function getMyMemberType();

    public function createMarkupHdr($labels = NULL, $hideRelChooser = TRUE) {

        $tr =
            HTMLTable::makeTh('Id')
            .HTMLTable::makeTh('Prefix')
            . HTMLTable::makeTh('First Name')
            . HTMLTable::makeTh('Middle')
            . HTMLTable::makeTh('Last Name')
            .HTMLTable::makeTh('Suffix')
            . HTMLTable::makeTh('Nickname')
            . ($this->showBirthDate ? HTMLTable::makeTh('Birth Date') : '');


        if ($hideRelChooser === FALSE) {

            $patTitle = 'Patient';

            if (is_string($labels)) {
                $patTitle = $labels;
            } else if (is_a($labels, 'Config_Lite')) {
                $patTitle = $labels->getString('MemberType', 'patient', 'Patient');
            }

            $tr .= HTMLTable::makeTh('Relationship to ' . $patTitle);
        }

        return $tr;
    }

    public function createMarkupRow() {

        $uS = Session::getInstance();
        $idPrefix = $this->getIdPrefix();

        // Id
        $tr = HTMLTable::makeTd(HTMLInput::generateMarkup(($this->nameRS->idName->getStoredVal() == 0 ? '' : $this->nameRS->idName->getStoredVal())
                        , array('name'=>$idPrefix.'idName', 'size'=>'7', 'readonly'=>'readonly', 'class'=>'ignrSave', 'style'=>'border:none;')));

        $attrs = array('data-prefix'=>$idPrefix);


        $attrs['name'] = $idPrefix.'selPrefix';
        $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup(
                        $uS->nameLookups[GL_TableNames::NamePrefix],
                        $this->nameRS->Name_Prefix->getstoredVal(), TRUE),
                $attrs));

        // First Name
        $attrs['name'] = $idPrefix.'txtFirstName';
        $attrs['class'] = 'hhk-firstname';
        $attrs['autofocus'] = 'autofocus';
        $tr .= HTMLTable::makeTd(
                HTMLInput::generateMarkup($this->nameRS->Name_First->getstoredVal(),
                        $attrs));

        // Middle Name
        $attrs['name'] = $idPrefix.'txtMiddleName';
        $attrs['size'] = '5';
        unset($attrs['class']);
        unset($attrs['autofocus']);
        $tr .= HTMLTable::makeTd(
                HTMLInput::generateMarkup($this->nameRS->Name_Middle->getstoredVal(),
                        $attrs));

        // Last Name
        $attrs['name'] = $idPrefix.'txtLastName';
        $attrs['class'] = 'hhk-lastname';
        unset($attrs['size']);
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Last->getstoredVal(),
               $attrs));

        // Suffix
        $attrs['name'] = $idPrefix.'selSuffix';
        unset($attrs['class']);
        $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GL_TableNames::NameSuffix],
                        $this->nameRS->Name_Suffix->getstoredVal(), TRUE),
               $attrs));

        // Nick Name
        $attrs['name'] = $idPrefix.'txtNickname';
        $attrs['size'] = '12';
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Nickname->getstoredVal(),
                $attrs));

        // Birth Date
        if ($this->showBirthDate) {

            $bd = '';

            if ($this->nameRS->BirthDate->getStoredVal() != '') {
                $bd = date('M j, Y', strtotime($this->nameRS->BirthDate->getStoredVal()));
            }

            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($bd, array('name'=>$idPrefix.'txtBirthDate', 'class'=>'ckbdate')));

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
     * @param PDO $dbh
     * @param string $uname
     * @return boolean
     */
    public function saveMemberType(PDO $dbh, $uname, $memberType = '') {

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

}



class GuestMember extends RoleMember {

    public function __construct(PDO $dbh, $defaultMemberBasis, $nid = 0, NameRS $nRS = NULL) {

        parent::__construct($dbh, $defaultMemberBasis, $nid, $nRS);

        $uS = Session::getInstance();

        if ($uS->LangChooser && $nid > 0) {
            $this->getLanguages($dbh, $nid);
        }

        if ($uS->InsuranceChooser && $nid > 0) {
            $this->getInsurance($dbh, $nid);
        }

        $this->showBirthDate = $uS->PatientBirthDate;

    }

    public function saveChanges(\PDO $dbh, array $post) {

        $msg = parent::saveChanges($dbh, $post);

        $uS = Session::getInstance();

        if ($uS->LangChooser && $this->get_idName() > 0) {
            $this->getLanguages($dbh, $this->get_idName());
        }

        if ($uS->InsuranceChooser && $this->get_idName() > 0) {
            $this->getInsurance($dbh, $this->get_idName());
        }

        return $msg;
    }

    protected function getMyMemberType() {
        return VolMemberType::Guest;
    }

    public function createMarkupHdr( $labels = NULL, $hideRelChooser = FALSE) {

        return parent::createMarkupHdr($labels, $hideRelChooser);

    }

    public function createMarkupRow($patientRelationship = '', $hideRelChooser = FALSE, $lockRelChooser = FALSE) {

        $tr = parent::createMarkupRow();

        if ($hideRelChooser === FALSE) {

            $uS = Session::getInstance();

            $parray = removeOptionGroups($uS->guestLookups[GL_TableNames::PatientRel]);

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

}


class PatientMember extends RoleMember {

    public function __construct(PDO $dbh, $defaultMemberBasis, $nid = 0, NameRS $nRS = NULL) {

        parent::__construct($dbh, $defaultMemberBasis, $nid, $nRS);

        $uS = Session::getInstance();

        $this->showBirthDate = $uS->PatientBirthDate;

    }

    protected function getMyMemberType() {
        return VolMemberType::Patient;
    }

}


class AgentMember extends RoleMember {

    protected function getMyMemberType() {
        return VolMemberType::ReferralAgent;
    }

}



class DoctorMember extends RoleMember {

    protected function getMyMemberType() {
        return VolMemberType::Doctor;
    }

}

