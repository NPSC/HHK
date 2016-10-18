<?php
/**
 * RoleMember.php
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of RoleMember
 *
 * @author Eric
 */
abstract class RoleMember extends IndivMember {

    protected abstract function getMyMemberType();

    protected function getLanguages(\PDO $dbh, $nid) {

        $nlangRs = new Name_LanguageRS();
        $nlangRs->idName->setStoredVal($nid);

        $rows = EditRS::select($dbh, $nlangRs, array($nlangRs->idName));

        foreach ($rows as $r) {
            $nlangRs = new Name_LanguageRS();
            EditRS::loadRow($r, $nlangRs);
            $this->languageRSs[$nlangRs->Language_Id->getStoredVal()] = $nlangRs;
        }
    }


    protected function getInsurance(\PDO $dbh, $nid) {

        $nInsRs = new Name_InsuranceRS();
        $nInsRs->idName->setStoredVal($nid);

        $rows = EditRS::select($dbh, $nInsRs, array($nInsRs->idName));

        foreach ($rows as $r) {
            $nInsRs = new Name_InsuranceRS();
            EditRS::loadRow($r, $nInsRs);
            $this->insuranceRSs[$nInsRs->Insurance_Id->getStoredVal()] = $nInsRs;
        }
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
     * @param string $vcat Volunteer Category
     * @param string $vcode Volunteer Code
     * @return boolean
     */
    public function saveMemberType(PDO $dbh, $uname) {

        if ($this->get_idName() === 0) {
            return FALSE;
        }

        $vcode = $this->getMyMemberType();
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

    public function createMarkupTable($patientRelationship = '', $lockRelChooser = FALSE, $hideRelChooser = FALSE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getIdPrefix();

        $table = new HTMLTable();
        $table->addHeaderTr(
                HTMLTable::makeTh('Id')
                .HTMLTable::makeTh('Prefix')
                . HTMLTable::makeTh('First Name')
                . HTMLTable::makeTh('Middle')
                . HTMLTable::makeTh('Last Name')
                .HTMLTable::makeTh('Suffix')
                . HTMLTable::makeTh('Nickname')
                . ($hideRelChooser === FALSE ? HTMLTable::makeTh('Relationship to Patient') : '')
                );

        // Id
        $tr = HTMLTable::makeTd(HTMLInput::generateMarkup(($this->nameRS->idName->getStoredVal() == 0 ? '' : $this->nameRS->idName->getStoredVal())
                        , array('name'=>$idPrefix.'idName', 'size'=>'3', 'readonly'=>'readonly', 'class'=>'ignrSave', 'style'=>'border:none;')));

        // Prefix
        $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup(
                        $uS->nameLookups[GL_TableNames::NamePrefix],
                        $this->nameRS->Name_Prefix->getstoredVal(), TRUE),
                array('name'=>$idPrefix.'selPrefix')));

        // First Name
        $tr .= HTMLTable::makeTd(
                HTMLInput::generateMarkup($this->nameRS->Name_First->getstoredVal(), array('name'=>$idPrefix.'txtFirstName', 'data-prefix'=>$idPrefix, 'class'=>'hhk-firstname', 'autofocus'=>'autofocus')));

        // Middle Name
        $tr .= HTMLTable::makeTd(
                HTMLInput::generateMarkup($this->nameRS->Name_Middle->getstoredVal(), array('name'=>$idPrefix.'txtMiddleName', 'size'=>'5')));

        // Last Name
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Last->getstoredVal(), array('name'=>$idPrefix.'txtLastName', 'data-prefix'=>$idPrefix, 'class'=>'hhk-lastname')));

        // Suffix
        $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GL_TableNames::NameSuffix],
                        $this->nameRS->Name_Suffix->getstoredVal(), TRUE), array('name'=>$idPrefix.'selSuffix')));

        // Nick Name
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Nickname->getstoredVal(), array('name'=>$idPrefix.'txtNickname', 'data-prefix'=>$idPrefix, 'size'=>'10')));

        if ($hideRelChooser === FALSE) {
            $parray = $uS->guestLookups[GL_TableNames::PatientRel];

            // freeze control if patient is self.
            if ($lockRelChooser) {
                if ($patientRelationship == RelLinkType::Self) {
                    $parray = array($patientRelationship => $uS->guestLookups[GL_TableNames::PatientRel][$patientRelationship]);
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
                     HTMLSelector::doOptionsMkup($parray, $patientRelationship, $allowEmpty), array('name'=>$idPrefix . 'selPatRel', 'data-prefix'=>$idPrefix, 'class'=>'patientRelch')));
        }

        $table->addBodyTr($tr);

        return $table->generateMarkup();
    }

    public function additionalNameMarkup() {

        $uS = Session::getInstance();
        $table = new HTMLTable();

        // Newsletter attributes
        $newsAttrs = array('name'=>$this->getIdPrefix() .'cbnewsltr', 'type'=>'checkbox');
        if ($this->demogRS->Newsletter->getStoredVal() == 1) {
            $newsAttrs['checked'] = 'checked';
        }

        // Media source & Newsletter
        $table->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('span', "How did you hear of us?"))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($uS->nameLookups['Media_Source'], $this->demogRS->Media_Source->getStoredVal()),
                        array('name'=>$this->getIdPrefix() .'selMedia')))
            . HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Newsletter', array('for'=>'cbNews', 'style'=>'margin-left:25px;')))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', $newsAttrs))
             );

        return $table->generateMarkup();
    }

    public function birthDateMarkup() {

        $table = new HTMLTable();
        $bd = '';

        if ($this->nameRS->BirthDate->getStoredVal() != '') {
            $bd = date('M j, Y', strtotime($this->nameRS->BirthDate->getStoredVal()));
        }

        $table->addBodyTr(
            HTMLTable::makeTd('Birth Date', array('class'=>'tdlabel'))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($bd, array('name'=>$this->getIdPrefix().'txtBirthDate', 'class'=>'ckbdate')))
             );

        return $table->generateMarkup();

    }

    public function getPsgObj(PDO $dbh) {
        return PSG::instantiateFromGuestId($dbh, $this->get_idName());
    }


}



class PatientMember extends RoleMember {

    public function __construct(PDO $dbh, $defaultMemberBasis, $nid = 0, NameRS $nRS = NULL) {

        parent::__construct($dbh, $defaultMemberBasis, $nid, $nRS);

        $uS = Session::getInstance();

       if ($uS->LangChooser && $nid > 0) {
            $this->getLanguages($dbh, $nid);
        }

        if ($uS->InsuranceChooser && $nid > 0) {
            $this->getInsurance($dbh, $nid);
        }

    }


    protected function getMyMemberType() {
        return VolMemberType::Patient;
    }


    public function getPsgObj(PDO $dbh) {
        return new Psg($dbh, 0, $this->get_idName());
    }

    public function createMarkupTable($patientEditable = TRUE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getIdPrefix();

        $table = new HTMLTable();
        $table->addHeaderTr(
                HTMLTable::makeTh('Id')
                . HTMLTable::makeTh('Prefix')
                . HTMLTable::makeTh('First Name')
                . HTMLTable::makeTh('Middle')
                . HTMLTable::makeTh('Last Name')
                .HTMLTable::makeTh('Suffix')
                . HTMLTable::makeTh('Nickname')
                .($uS->PatientBirthDate ? HTMLTable::makeTh('Birth Date') : '')
                );


        // Id
        $tr = HTMLTable::makeTd(HTMLInput::generateMarkup(($this->nameRS->idName->getStoredVal() == 0 ? '' : $this->nameRS->idName->getStoredVal())
                        , array('name'=>$idPrefix.'idName', 'size'=>'3', 'readonly'=>'readonly', 'style'=>'border:none;')));

        // Prefix
        if ($patientEditable) {
            $tr .= HTMLContainer::generateMarkup('td', HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GL_TableNames::NamePrefix],
                        $this->nameRS->Name_Prefix->getstoredVal(), TRUE), array('name'=>$idPrefix.'selPrefix')));
        } else if ($this->nameRS->Name_Prefix->getstoredVal() != '') {
            $tr .= HTMLTable::makeTd($uS->nameLookups[GL_TableNames::NamePrefix][$this->nameRS->Name_Prefix->getstoredVal()][1]);
        } else {
            $tr .= HTMLTable::makeTd('');
        }

        $fnArray = array('name'=>$idPrefix.'txtFirstName', 'class'=>'hhk-firstname', 'data-prefix'=>$idPrefix);
        // First Name
        if (!$patientEditable) {
            $fnArray['readonly'] = 'readonly';
            $fnArray['style'] = 'border:none;';
        }

        $tr .= HTMLContainer::generateMarkup('td',
                HTMLInput::generateMarkup($this->nameRS->Name_First->getStoredVal(), $fnArray));


        // Middle Name
        $mnArray = array('name'=>$idPrefix.'txtMiddleName', 'size'=>'5');

        if (!$patientEditable) {
            $mnArray['readonly'] = 'readonly';
            $mnArray['style'] = 'border:none;';
        }

        $tr .= HTMLTable::makeTd(
                HTMLInput::generateMarkup($this->nameRS->Name_Middle->getstoredVal(), $mnArray));


        // Last Name
        $lnArray = array('name'=>$idPrefix.'txtLastName', 'class'=>'hhk-lastname', 'data-prefix'=>$idPrefix);

        if (!$patientEditable) {
            $lnArray['readonly'] = 'readonly';
            $lnArray['style'] = 'border:none;';
        }

        $tr .= HTMLContainer::generateMarkup('td',
                HTMLInput::generateMarkup($this->nameRS->Name_Last->getStoredVal(), $lnArray));


        // Suffix
        if ($patientEditable) {
            $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($uS->nameLookups[GL_TableNames::NameSuffix],
                        $this->nameRS->Name_Suffix->getstoredVal(), TRUE), array('name'=>$idPrefix.'selSuffix')));
        } else if ($this->nameRS->Name_Suffix->getstoredVal() != '') {
            $tr .= HTMLTable::makeTd($uS->nameLookups[GL_TableNames::NameSuffix][$this->nameRS->Name_Suffix->getstoredVal()][1]);
        } else {
            $tr .= HTMLTable::makeTd('');
        }

        // Nick Name
        $nnArray = array('name'=>$idPrefix.'txtNickname', 'size'=>'10');

        if (!$patientEditable) {
            $nnArray['readonly'] = 'readonly';
            $nnArray['style'] = 'border:none;';
        }
        $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($this->nameRS->Name_Nickname->getstoredVal(), $nnArray));



        // Birth Date
        if ($uS->PatientBirthDate) {

            $bd = '';

            if ($this->nameRS->BirthDate->getStoredVal() != '') {
                $bd = date('M j, Y', strtotime($this->nameRS->BirthDate->getStoredVal()));
            }

            if ($patientEditable) {
                $tr .= HTMLTable::makeTd(
                    HTMLInput::generateMarkup($bd, array('name'=>$idPrefix.'txtBirthDate', 'class'=>'ckbdate')));
            } else {
                $tr .= HTMLTable::makeTd($bd);
            }
        }



        $table->addBodyTr($tr);
        return $table->generateMarkup();
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

