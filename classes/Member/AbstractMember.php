<?php

namespace HHK\Member;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\Member\Address\AbstractContactPoint;
use HHK\SysConst\{GLTableNames, MemDesignation, MemStatus};
use HHK\Tables\EditRS;
use HHK\Tables\Name\{NameDemogRS, NameRS};
use HHK\sec\Session;
use HHK\AuditLog\NameLog;
use HHK\Exception\{RuntimeException, MemberException, UnexpectedValueException, InvalidArgumentException};
use HHK\Neon\TransferMembers;

/**
 * AbstractMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of AbstractMember
 *
 * @author Eric Crane
 */

abstract class AbstractMember {

    /**
     *
     * @var NameRS
     */
    protected $nameRS;

    /**
     *
     * @var NameDemogRS
     */
    protected $demogRS;

    protected $idPrefix = '';
    private $message = '';
    private $newMember;
    const CODE = 0;
    const DESC = 1;
    const SUBT = 2;

    /**
     *
     * @param \PDO $dbh
     * @param string $defaultMemberBasis
     * @param int $nid
     * @param NameRS $nRS
     * @throws RuntimeException::
     */
    public function __construct(\PDO $dbh, $defaultMemberBasis, $nid = 0, NameRS $nRS = null) {

        $uS = Session::getInstance();

        if ($nid < 0) {
            throw new RuntimeException('The member Id cannot be negative');
        }

        if (is_null($nRS)) {
            $this->nameRS = $this->loadNameRS($dbh, $nid);
        } else {
            $this->nameRS = $nRS;
        }

        $this->demogRS = new NameDemogRS();

        $this->newMember = FALSE;

        if ($nid == 0) {

            if (isset($uS->nameLookups[GLTableNames::MemberBasis][$defaultMemberBasis])) {

                // preset special fields for new member
                $this->nameRS->Member_Status->setStoredVal(MemStatus::Active);
                $this->nameRS->Member_Type->setStoredVal($defaultMemberBasis);
                $this->newMember = TRUE;

            } else {
                throw new MemberException("Undefined member Basis for new member: " . $defaultMemberBasis);
            }

        } else {

            // Existing member.  Make sure he is one of mine
            if (isset($uS->nameLookups[GLTableNames::MemberBasis][$this->nameRS->Member_Type->getStoredVal()])) {

                if ($this->getMemberDesignation() != $uS->nameLookups[GLTableNames::MemberBasis][$this->nameRS->Member_Type->getStoredVal()][AbstractMember::SUBT]) {
                    throw new MemberException("Wrong member Designation for this member object.  idName = " . $nid);
                }

            } else {
                throw new MemberException("Undefined member Basis for idName = " . $nid);
            }

            // Get demography data
            $this->demogRS->idName->setStoredVal($nid);
            $rows = EditRS::select($dbh, $this->demogRS, array($this->demogRS->idName));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $this->demogRS);
            } else {
                $demoRS = new NameDemogRS();
                $demoRS->idName->setNewVal($nid);
                EditRS::insert($dbh, $demoRS);
            }

        }
    }



    public function isNew() {
        return $this->newMember;
    }

    /**
     * Returns a member object, either indiv or organization.  Use this to create member objects.
     * @param \PDO $dbh
     * @param int $nid
     * @param MemDesignation $defaultMemberBasis
     * @return IndivMember|OrgMember
     * @throws UnexpectedValueException
     */
    public static function GetDesignatedMember(\PDO $dbh, $nid, $defaultMemberBasis) {

        $uS = Session::getInstance();
        $desig = "";

        $nRS = AbstractMember::loadNameRS($dbh, $nid);

        if ($nid > 0) {
            // Existing member
            if (isset($uS->nameLookups[GLTableNames::MemberBasis][$nRS->Member_Type->getStoredVal()])) {

                $desig = $uS->nameLookups[GLTableNames::MemberBasis][$nRS->Member_Type->getStoredVal()][AbstractMember::SUBT];

            } else {
                throw new RuntimeException("This member (" . $nid . ") has an Undefined Member Basis: " . $nRS->Member_Type->getStoredVal() . ".");
            }

        } else {
            // New member
            if (isset($uS->nameLookups[GLTableNames::MemberBasis][$defaultMemberBasis])) {

                $desig = $uS->nameLookups[GLTableNames::MemberBasis][$defaultMemberBasis][AbstractMember::SUBT];

            } else {
                // defaulut member type is undefined
                throw new InvalidArgumentException("Supplied default Member Basis is undefined: " . $defaultMemberBasis . ".");
            }
        }

        // Return the proper object
        if ($desig == MemDesignation::Individual) {
            return new IndivMember($dbh, $defaultMemberBasis, $nid, $nRS);
        } else if ($desig == MemDesignation::Organization) {
            return new OrgMember($dbh, $defaultMemberBasis, $nid, $nRS);
        }
        return null;
    }

    /**
     *
     * @param \PDO $dbh
     * @param int $nid
     * @return NameRS
     * @throws RuntimeException::
     */
    protected static function loadNameRS(\PDO $dbh, $nid) {
        $nRS = new NameRS();

        if ($nid > 0) {

            $nRS->idName->setStoredVal($nid);
            $rows = EditRS::select($dbh, $nRS, array($nRS->idName));

            if (count($rows) == 1) {

                EditRS::loadRow($rows[0], $nRS);
            } else {
                // Error, id > 0 and no record
                throw new RuntimeException("There is no record for Member Id = $nid");
            }
        }
        return $nRS;
    }

    protected abstract function getDefaultMemBasis();

    public function getIdPrefix() {
        return $this->idPrefix;
    }

    public function setIdPrefix($idPrefix) {
        $this->idPrefix = $idPrefix;
        return $this;
    }

    public abstract function createMarkupTable();

    public function genNotesMarkup(array $volNotesMkup = array(), $showSearchButton = TRUE) {

        $searchMarkup = "";
        $idPrefix = $this->getIdPrefix();


        if ($showSearchButton) {
            // Search button and txt field
            $table = new HTMLTable();
            $table->addBodyTr(
                    $table->makeTd(
                            HTMLInput::generateMarkup("", array('class'=>"ignrSave",'id'=>$idPrefix.'txtSearchNotes'))
                            . HTMLInput::generateMarkup("Search Notes", array('type'=>'button', 'class'=>'ignrSave', 'id'=>$idPrefix.'btnNoteSch'))
                            )
                    );
            $searchMarkup = $table->generateMarkup();
        }

        $nTable = new HTMLTable();

        // General Notes
        $nTable->addBodyTr(
                $nTable->makeTh("General Notes", array('style'=>'min-width:150px;'))
                . $nTable->makeTd("Last Updated: " . is_null($this->demogRS->Last_Updated->getStoredVal()) ? '' : date('M j, Y', strtotime($this->demogRS->Last_Updated->getStoredVal())))
                . $nTable->makeTd("By: " . $this->demogRS->Updated_By->getStoredVal(), array('style'=>'padding-right: 20px;'))
                . $nTable->makeTd("Contact Date:", array('class'=>'tdlabel'))
                . $nTable->makeTd(HTMLInput::generateMarkup(($this->get_contactDate() != "" ? date('M j, Y', strtotime($this->get_contactDate())) : ""), array('class'=>'ckdate', 'name'=>$idPrefix.'genCkDate')))
                );
        $nTable->addBodyTr(
                $nTable->makeTd(
                        HTMLContainer::generateMarkup(
                                'textarea',
                                $this->get_genNotes(),
                                array('name'=>$idPrefix.'gen_Notes', 'class'=>'noteTA', 'rows'=>'2', 'style'=>'width:100%;')
                                ),
                        array('colspan'=>'5')
                        )
                );

        // Volunteer category notes - just the ones with text.
        foreach ($volNotesMkup as $catName => $notes) {

            if (!empty($notes)) {

                $nTable->addBodyTr(
                        $nTable->makeTh($catName)
                        . $nTable->makeTh('Notes', array('colspan'=>'5')));

                foreach ($notes as $noteMarkup) {
                    $nTable->addBody($noteMarkup);
                }
            }
        }

        return $searchMarkup . $nTable->generateMarkup();
    }

    public abstract function createDemographicsPanel(\PDO $dbh);

    public function getDemographicsEntry($tableName) {

        $val = '';
        if ($tableName == 'Gender') {
            $val = $this->nameRS->Gender->getStoredVal();
        } else {

            foreach ($this->demogRS as $k => $v) {

                if ($k == $tableName) {
                    $val = $v->getStoredVal();
                }
            }
        }

        return $val;
    }

    public function getDemographicField($tableName) {

        if ($tableName == 'Gender') {
            return $this->nameRS->Gender;
        } else {

            foreach ($this->demogRS as $k => $v) {

                if ($k == $tableName) {
                    return $v;
                }
            }
        }

        return NULL;
    }

    public function createAdminPanel() {

        $table = new HTMLTable();

        $table->addBodyTr(
                HTMLTable::makeTd('Last Updated:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->get_lastUpdated()), array('style'=>'display:table-cell;'))
                );

        $table->addBodyTr(
                HTMLTable::makeTd('Updated By:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->get_updatedBy()), array('style'=>'display:table-cell;'))
                );

        $table->addBodyTr(
                HTMLTable::makeTd('Date Added:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->get_dateAdded()), array('style'=>'display:table-cell;'))
                );

        return $table->generateMarkup();
    }

    public function createExcludesPanel() {

        $uS = Session::getInstance();
        $tabIcon = "";
        $insertTabIcon = FALSE;

        $table = new HTMLTable();

        // Exclude Directory
        $exDirAttr = array('name'=>'exDir', 'type'=>'checkbox', 'class'=>'hhk-ex', 'title'=>'Check to hide this member from the directory');
        if ($this->get_exclDirectory()) {
            $exDirAttr['checked'] = 'checked';
            $insertTabIcon = TRUE;
        }
        $table->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Exclude Directory', array('for'=>'exDir')) , array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                '',
                                $exDirAttr
                                ),
                        array('style'=>'vertical-align: middle;display:table-cell;')
                        )
                );

        // Exclude Mail
        $exMailAttr = array('name'=>'exMail', 'type'=>'checkbox', 'class'=>'hhk-ex', 'title'=>'Check to exclude from mailing lists');
        if ($this->get_exclMail()) {
            $exMailAttr['checked'] = 'checked';
            $insertTabIcon = TRUE;
        }
        $table->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Exclude Mail', array('for'=>'exMail')), array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                '',
                                $exMailAttr
                                ),
                        array('style'=>'vertical-align: middle;display:table-cell;')
                        )
                );

        // Exclude EMail
        $exEmailAttr = array('name'=>'exEmail', 'type'=>'checkbox', 'class'=>'hhk-ex', 'title'=>'Check to exclude Email addresses from lists');
        if ($this->get_exclEmail()) {
            $exEmailAttr['checked'] = 'checked';
            $insertTabIcon = TRUE;
        }
        $table->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Exclude Email', array('for'=>'exEmail')), array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                '',
                                $exEmailAttr
                                ),
                        array('style'=>'vertical-align: middle;display:table-cell;')
                        )
                );

        // Exclude Phone
        $exPhoneAttr = array('name'=>'exPhone', 'type'=>'checkbox', 'class'=>'hhk-ex', 'title'=>'Check to exclude Phone numbers from lists');
        if ($this->get_exclPhone()) {
            $exPhoneAttr['checked'] = 'checked';
            $insertTabIcon = TRUE;
        }
        $table->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Exclude Phone', array('for'=>'exPhone')), array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                '',
                                $exPhoneAttr
                                ),
                        array('style'=>'vertical-align: middle;display:table-cell;')
                        )
                );

        // Exclude Neon
        if ($uS->ContactManager == 'neon') {
            $exNeonAttr = array('name'=>'exNeon', 'type'=>'checkbox', 'class'=>'hhk-ex', 'title'=>'Check to exclude Neon Transfers');
            if ($this->get_ExternalId() == TransferMembers::EXCLUDE_TERM) {
                $exNeonAttr['checked'] = 'checked';
                $insertTabIcon = TRUE;
            }
            $table->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Exclude Neon', array('for'=>'exNeon')), array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                    HTMLInput::generateMarkup(
                        '',
                        $exNeonAttr
                        ),
                    array('style'=>'vertical-align: middle;display:table-cell;')
                    )
                );
        }

        $table->addBodyTr(
                HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                'Check All',
                                array('type'=>'button', 'id'=>'exAll', 'class'=>'hhk-check-button ignrSave'))
                        . HTMLInput::generateMarkup(
                                'Uncheck All',
                                array('type'=>'button', 'id'=>'exNone', 'class'=>'hhk-check-button ignrSave', 'style'=>'margin-left:0.4em;'))
                        . HTMLInput::generateMarkup('yes', array('type'=>'hidden', 'name'=>'excludesHere')),
                        array('colspan'=>'2'))
             );

    // <span class='ui-icon ui-icon-check' style='float: left; margin-right: .3em;'></span>

        if ($insertTabIcon === TRUE) {
            $tabIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check', 'style'=>'float:left; margin-right: 0.3em;'));
        }

        return array("markup"=> $table->generateMarkup(), "tabIcon"=>$tabIcon);

    }

    public abstract function createRelationsTabs(array $rel, $page = "NameEdit.php");

    public abstract function loadRealtionships(\PDO $dbh);

    public abstract function createMiscTabsMarkup(\PDO $dbh);

    /**
     *
     * @param \DateTime $dt
     * @param string $style  CSS Style parameter and value
     * @return string  HTML Span element.
     */
    public function getContactLastUpdatedMU(\DateTime $dt, $contactType = 'Address', $style = 'font-size:.8em; color:#656577') {

        $arry = array();

        if ($style != '') {
            $arry['style'] = $style;
        }

        if ($contactType != '') {
            $contactType .= ' ';
        }

        return HTMLContainer::generateMarkup('span', $contactType . 'Last Updated: ' . $dt->format('M j, Y'), $arry);
    }

    /**
     *
     * @param \PDO $dbh
     * @param array $post
     * @return string
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    public function saveChanges(\PDO $dbh, array $post) {

        // Convenience var
        $n = $this->nameRS;
        $uS = Session::getInstance();
        $user = $uS->username;

        // Process common
        $this->processCommon($post);


        // New Record?
        if ($n->idName->getStoredVal() == 0) {
            // New member

            // Set Member Designation
            if (isset($uS->nameLookups[GLTableNames::MemberBasis][$n->Member_Type->getNewVal()])) {

                if ($uS->nameLookups[GLTableNames::MemberBasis][$n->Member_Type->getNewVal()][AbstractMember::SUBT] == MemDesignation::Individual) {
                    $n->Record_Member->setNewVal(1);
                } else if ($uS->nameLookups[GLTableNames::MemberBasis][$n->Member_Type->getNewVal()][AbstractMember::SUBT] == MemDesignation::Organization) {
                    $n->Record_Company->setNewVal(1);
                } else {
                    throw new UnexpectedValueException("Member Designation not determined.");
                }
            } else {
                throw new UnexpectedValueException("No DB match for Member Basis.");
            }

            // Verify Member Status
            if (isset($uS->nameLookups[GLTableNames::MemberStatus][$n->Member_Status->getNewVal()]) === FALSE) {
                throw new UnexpectedValueException("No DB match for Member Status.");
            }

            // Additional info for new members
            $n->Date_Added->setNewVal(date('Y-m-d H:i:s'));
            $n->Member_Status_Date->setNewVal(date('Y-m-d'));

        } else {
            // Existing Record

            // Does the member type(basis) match the member's designation?


            // Disallow Member Designation changes
            if (is_null($n->Member_Type->getNewVal()) === FALSE && $n->Member_Type->getNewVal() !=  $n->Member_Type->getStoredVal()) {

                if ($uS->nameLookups[GLTableNames::MemberBasis][$n->Member_Type->getNewVal()][AbstractMember::SUBT] !=
                        $uS->nameLookups[GLTableNames::MemberBasis][$n->Member_Type->getStoredVal()][AbstractMember::SUBT]) {

                    throw new RuntimeException("Cannot change member designation");
                }
            }

            // Has the Status Changed to:
            if (is_null($n->Member_Status->getNewVal()) === FALSE && $n->Member_Status->getNewVal() != $n->Member_Status->getStoredVal()) {

                switch ($n->Member_Status->getNewVal()) {

                    //  - Duplicate:  Disallow
                    case MemStatus::Duplicate:
                        throw new RuntimeException("Cannot directly change member status to Duplicate.");
                        break;

                    //  - ToBeDeleted:  Check for donations or guest stays
                    case MemStatus::ToBeDeleted:

                        $result = $this->isMemberDeletable($dbh);
                        if ($result != '') {
                            throw new RuntimeException("Cannot Delete this member: " . $result);
                        }
                        break;

                    case MemStatus::Deceased:
                        $this->retireAllVolunteer($dbh, $user);
                        $this->deleteWebLogin($dbh, $user);
                        break;

                    case MemStatus::Inactive:
                        $this->retireAllVolunteer($dbh, $user);
                        break;

                    default:


                }
            }
        }


        // Process member
        $message = $this->processMember($dbh, $post);


        $n->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $n->Updated_By->setNewVal($user);

        $this->demogRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $this->demogRS->Updated_By->setNewVal($user);

        // database
        if ($n->idName->getStoredVal() == 0) {

            // Insert new member
            $nId = EditRS::insert($dbh, $n);
            $n->idName->setNewVal($nId);

             if ($nId > 0) {
                 $n->idName->setStoredVal($nId);
                 NameLog::writeInsert($dbh, $n, $nId, $user);
                 $message .= 'Member Name Inserted.  Id = ' . $nId;

                 // insert new demog record
                 $this->demogRS->idName->setNewVal($nId);
                 EditRS::insert($dbh, $this->demogRS);
                 NameLog::writeInsert($dbh, $this->demogRS, $nId, $user);

             }

        } else {

            // Update existing member
            $numRows = EditRS::update($dbh, $n, array($n->idName));
            $demogRows = EditRS::update($dbh, $this->demogRS, array($this->demogRS->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $n, $n->idName->getStoredVal(), $user);
                $message .= "Member Name Record Updated.  ";
            }
            if ($demogRows > 0) {
                NameLog::writeUpdate($dbh, $this->demogRS, $n->idName->getStoredVal(), $user);
                $message .= "Member Demographics Updated.  ";
            }
        }

        // update the recordset
        EditRS::updateStoredVals($n);
        EditRS::updateStoredVals($this->demogRS);

        return $message;
    }

    protected abstract function processMember(\PDO $dbh, array $post);

    /**
     *
     * @param array $post
     */
    protected function processCommon(array $post) {

        // Convenience var
        $n = $this->nameRS;
        $uS = Session::getInstance();
        $idPrefix = $this->getIdPrefix();

        //  Basis
        if (isset($post[$idPrefix."selMbrType"])) {

            $mt = filter_var($post[$idPrefix.'selMbrType'], FILTER_SANITIZE_STRING);

            if (isset($uS->nameLookups['Member_Basis'][$mt])) {

                $n->Member_Type->setNewVal($mt);
                // additional info if changed
                if ($n->Member_Type->getStoredVal() != $n->Member_Type->getNewVal()) {
                    $n->Prev_MT_Change_Date->setNewVal(date("Y-m-d H:i:s"));
                    $n->Previous_Member_Type->setNewVal($n->Member_Type->getStoredVal());
                }
            }
        } else {
            $n->Member_Type->setNewVal($n->Member_Type->getStoredVal());
        }

        //  Status
        if (isset($post[$idPrefix.'selStatus'])) {

            $mt = filter_var($post[$idPrefix.'selStatus'], FILTER_SANITIZE_STRING);

            if (isset($uS->nameLookups['mem_status'][$mt]) === TRUE) {
                $n->Member_Status->setNewVal($mt);

                // Aditional Info if changed
                if ($n->Member_Status->getStoredVal() != $n->Member_Status->getNewVal()) {
                    $n->Member_Status_Date->setNewVal(date("Y-m-d H:i:s"));
                }
            }

        } else {
            $n->Member_Status->setNewVal($n->Member_Status->getStoredVal());
        }

        // Deceased status checkbox
        if (isset($post[$idPrefix.'cbMarker_deceased'])) {

            if (isset($post[$idPrefix.'cbdeceased'])) {
                // Member deceased
                $n->Member_Status->setNewVal(MemStatus::Deceased);

                if (isset($post[$idPrefix.'txtDeathDate']) && $post[$idPrefix.'txtDeathDate'] != '') {
                    $ddec = filter_var($post[$idPrefix.'txtDeathDate'], FILTER_SANITIZE_STRING);
                    $n->Date_Deceased->setNewVal($ddec);
                }

                if ($n->Member_Status->getStoredVal() != $n->Member_Status->getNewVal()) {
                    $n->Member_Status_Date->setNewVal(date("Y-m-d H:i:s"));
                }

            } else {

                if ($n->Member_Status->getStoredVal() == MemStatus::Deceased) {
                    $n->Member_Status->setNewVal(MemStatus::Active);
                    $n->Date_Deceased->setNewVal('');
                }

                if ($n->Member_Status->getStoredVal() != $n->Member_Status->getNewVal()) {
                    $n->Member_Status_Date->setNewVal(date("Y-m-d H:i:s"));
                }
            }
        }


        // Background Check checkbox
        if (isset($post[$idPrefix.'cbMarker_background_check'])) {

            if (isset($post[$idPrefix.'cbbackgroundcheck'])) {
                // Member background check completed
                if (isset($post[$idPrefix.'txtBackgroundCheckDate']) && $post[$idPrefix.'txtBackgroundCheckDate'] != '' && $this->demogRS->Background_Check_Date->getStoredVal() != $post[$idPrefix.'txtBackgroundCheckDate']) {
                    $dbc = filter_var($post[$idPrefix.'txtBackgroundCheckDate'], FILTER_SANITIZE_STRING);
                    $this->demogRS->Background_Check_Date->setNewVal($dbc);
                }

            } else {

                if ($this->demogRS->Background_Check_Date->getStoredVal() != NULL) {
                    $this->demogRS->Background_Check_Date->setNewVal('');
                }
            }
        }


        //  Excludes
        // Excludes section includes a hidden input that we check to see if the excludes are included on the page.
        if (isset($post[$idPrefix."excludesHere"])) {

            $n->Exclude_Directory->setNewVal(isset($post[$idPrefix."exDir"]) ? 1 : 0);
            $n->Exclude_Email->setNewVal(isset($post[$idPrefix."exEmail"]) ? 1 : 0);
            $n->Exclude_Mail->setNewVal(isset($post[$idPrefix."exMail"]) ? 1 : 0);
            $n->Exclude_Phone->setNewVal(isset($post[$idPrefix."exPhone"]) ? 1 : 0);
            $n->External_Id->setNewVal(isset($post[$idPrefix."exNeon"]) ? TransferMembers::EXCLUDE_TERM : '');
        }

        //  Prefered Mail Address
        if (isset($post[$idPrefix.'rbPrefMail'])) {
            foreach ($uS->nameLookups[GLTableNames::AddrPurpose] as $code) {
                if ($code[AbstractMember::CODE] == $post[$idPrefix.'rbPrefMail']) {
                    $n->Preferred_Mail_Address->setNewVal(filter_var($post[$idPrefix.'rbPrefMail'], FILTER_SANITIZE_STRING));
                    break;
                }
            }
        }

        // Preferred Email address
        if (isset($post[$idPrefix.'rbEmPref'])) {
            foreach ($uS->nameLookups[GLTableNames::EmailPurpose] as $code) {
                if ($code[AbstractMember::CODE] == $post[$idPrefix.'rbEmPref']) {
                    $n->Preferred_Email->setNewVal(filter_var($post[$idPrefix.'rbEmPref'], FILTER_SANITIZE_STRING));
                    break;
                }
            }
        }

        // Preferred Phone number
        if (isset($post[$idPrefix.'rbPhPref'])) {
            foreach ($uS->nameLookups[GLTableNames::PhonePurpose] as $code) {
                if ($code[AbstractMember::CODE] == $post[$idPrefix.'rbPhPref']) {
                    $n->Preferred_Phone->setNewVal(filter_var($post[$idPrefix.'rbPhPref'], FILTER_SANITIZE_STRING));
                    break;
                }
            }
        }

        // Orientation Date
        if (isset($post[$idPrefix."txtOrienDate"])) {
            $this->demogRS->Orientation_Date->setNewVal(filter_var($post[$idPrefix.'txtOrienDate'], FILTER_SANITIZE_STRING));
        }

        // General Notes gen_Notes
        if (isset($post[$idPrefix."gen_Notes"])) {
            $this->demogRS->Gen_Notes->setNewVal(filter_var($post[$idPrefix.'gen_Notes'], FILTER_SANITIZE_STRING));
        }

        // General Contact Date
        if (isset($post[$idPrefix."genCkDate"])) {
            $this->demogRS->Contact_Date->setNewVal(filter_var($post[$idPrefix.'genCkDate'], FILTER_SANITIZE_STRING));
        }


    }


    public function verifyPreferredAddress(\PDO $dbh, AbstractContactPoint $cp, $uname) {

        $msg = "";
        $foundOne = FALSE;

        if ($cp->get_preferredCode() == "" || $cp->isRecordSetDefined($cp->get_preferredCode()) === FALSE) {
            // None Preferred.  Is there a defined address?
            foreach ($cp->get_CodeArray() as $code) {

                if ($cp->isRecordSetDefined($code[0])) {
                    $cp->setPreferredCode($code[0]);
                    EditRS::update($dbh, $this->nameRS, array($this->nameRS->idName));
                    NameLog::writeUpdate($dbh, $this->nameRS, $this->nameRS->idName->getStoredVal(), $uname);
                    EditRS::updateStoredVals($this->nameRS);
                    $msg .= "Preferred " . $cp->getTitle() . " updated.  ";
                    $foundOne = TRUE;
                    break;
                }
            }

            if ($foundOne === FALSE && $cp->get_preferredCode() != "") {
                $cp->setPreferredCode("");
                EditRS::update($dbh, $this->nameRS, array($this->nameRS->idName));
                NameLog::writeUpdate($dbh, $this->nameRS, $this->nameRS->idName->getStoredVal(), $uname);
                EditRS::updateStoredVals($this->nameRS);

                $msg .= "Preferred " . $cp->getTitle() . " removed.  ";

            }
        }
        return $msg;
    }

    public function setAllExcludes($setting = 'off') {

        $ex = 1;
        if ($setting == 'off') {
            $ex = 0;
        }

        $this->nameRS->Exclude_Directory->setNewVal($ex);
        $this->nameRS->Exclude_Email->setNewVal($ex);
        $this->nameRS->Exclude_Mail->setNewVal($ex);
        $this->nameRS->Exclude_Phone->setNewVal($ex);

    }

    /**
     *
     * @param \PDO $dbh
     * @return string
     */
    protected function isMemberDeletable(\PDO $dbh) {

        $result = "";   // Set $result with a text message to decline the status change


        // Is the member a guest?
        /** @todo Determine if the member is a guest or otherwise undeletable */


        // Check that there are no active donation records
        $query = "Select SUM(Amount) from donations where Status='a' and Donor_Id = :id;";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':id'=>$this->nameRS->idName));

        if ($stmt->rowCount() > 0) {
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
            if ($rows[0][0] > 0) {
                $result = "Member shows $" . number_format($rows[0][0], 2) . " in donations.";
            }
        }
        return $result;
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $user
     * @return bool
     */
    public function retireAllVolunteer(\PDO $dbh, $user) {

        // Retire all volunteer stints
        $query = "update name_volunteer2 set Vol_Status = 'i', Vol_End = now(), Updated_By=:byuser, Last_Updated=now()
            where idName = :id and Vol_Status <> 'i' and Vol_Category <> 'Vol_Type';";
        $stmt = $dbh->prepare($query);

        return $stmt->execute(array(':id'=>$this->nameRS->idName, ':byuser'=>$user));
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $user
     * @return bool
     */
    public function deleteWebLogin(\PDO $dbh, $user) {

        // delete web login
        $query = "call del_webuser(:id, :byuser);";
        $stmt = $dbh->prepare($query);

        return $stmt->execute(array(':id'=>$this->nameRS->idName, ':byuser'=>$user));
    }

    public abstract function getMemberDesignation();

    public abstract function getMemberName();

    public abstract function getAssocDonorLabel();

    public abstract function getAssocDonorList(array $rel);

    // return the default donor for the donor list
    public function getDefaultDonor(array $rel) {
        return '';
    }

    public function get_ExternalId() {
        return $this->nameRS->External_Id->getStoredVal();
    }


    public function get_nameRS() {
        return $this->nameRS;
    }

    public function get_demogRS() {
        return $this->demogRS;
    }

    protected function set_message($v) {
        $this->message = $v;
    }

    public function get_message() {
        return $this->message;
    }

    public function get_errorMessage() {
        return $this->message;
    }

    public function set_idName($idName) {
        $this->nameRS->idName->setNewVal($idName);
    }

    public function get_idName() {
        return $this->nameRS->idName->getStoredVal();
    }

    public function set_firstName($firstName) {
        $this->nameRS->Name_First->setNewVal($firstName);
    }

    public function get_firstName() {
        return $this->nameRS->Name_First->getStoredVal();
    }

    public function set_middleName($middleName) {
        $this->nameRS->Name_Middle->setNewVal($middleName);
    }

    public function get_middleName() {
        return $this->nameRS->Name_Middle->getStoredVal();
    }

    public function set_lastName($lastName) {
        $this->nameRS->Name_Last->setNewVal($lastName);
    }

    public function get_lastName() {
        return $this->nameRS->Name_Last->getStoredVal();
    }

    public function set_lastFirst($lastFirst) {
        $this->nameRS->Name_Last_First->setNewVal($lastFirst);
    }

    public function get_lastFirst() {
        return $this->nameRS->Name_Last_First->getStoredVal();
    }

    public function set_nickName($nickName) {
        $this->nameRS->Name_Nickname->setNewVal($nickName);
    }

    public function get_nickName() {
        return $this->nameRS->Name_Nickname->getStoredVal();
    }

    public function set_fullName($fullName) {
        $this->nameRS->Name_Full->setNewVal($fullName);
    }

    public function get_fullName() {
        return $this->nameRS->Name_Full->getStoredVal();
    }

    public function set_previousName($previousName) {
        $this->nameRS->Name_Previous->setNewVal($previousName);
    }

    public function get_previousName() {
        return $this->nameRS->Name_Previous->getStoredVal();
    }

    public function set_type($type) {
        $this->nameRS->Member_Type->setNewVal($type);
    }

    public function get_type() {
        return $this->nameRS->Member_Type->getStoredVal();
    }

    public function set_status($status) {
        $this->nameRS->Member_Status->setNewVal($status);
    }

    public function get_status() {
        return $this->nameRS->Member_Status->getStoredVal();
    }

    public function set_webSite($webSite) {
        $this->nameRS->Web_Site->setNewVal($webSite);
    }

    public function get_webSite() {
        return $this->nameRS->Web_Site->getStoredVal();
    }

    public function set_updatedBy($updatedBy) {
        $this->nameRS->Updated_By->setNewVal($updatedBy);
    }

    public function get_updatedBy() {
        return $this->nameRS->Updated_By->getStoredVal();
    }

    public function set_exclDirectory($v) {
        $this->nameRS->Exclude_Directory->setNewVal($v);
    }

    public function get_exclDirectory() {
        if ($this->nameRS->Exclude_Directory->getStoredVal() == '1' || ord($this->nameRS->Exclude_Directory->getStoredVal()) == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function set_exclMail($v) {
        $this->nameRS->Exclude_Mail->setNewVal($v);
    }

    public function get_exclMail() {
        if ($this->nameRS->Exclude_Mail->getStoredVal() == '1' || ord($this->nameRS->Exclude_Mail->getStoredVal()) == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function set_exclPhone($v) {
        $this->nameRS->Exclude_Phone->setNewVal($v);
    }

    public function get_exclPhone() {
        if ($this->nameRS->Exclude_Phone->getStoredVal() == '1' || ord($this->nameRS->Exclude_Phone->getStoredVal()) == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function set_exclEmail($v) {
        $this->nameRS->Exclude_Email->setNewVal($v);
    }

    public function get_exclEmail() {
        if ($this->nameRS->Exclude_Email->getStoredVal() == '1' || ord($this->nameRS->Exclude_Email->getStoredVal()) == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function set_dateAdded($dateAdded) {
        $this->nameRS->Date_Added->setNewVal($dateAdded);
    }

    public function get_dateAdded() {
        if ($this->nameRS->Date_Added->getStoredVal() != '') {
            return date('M j, Y', strtotime($this->nameRS->Date_Added->getStoredVal()));
        } else {
            return '';
        }
    }

    public function set_orientationDate($v) {
        $this->demogRS->Orientation_Date->setNewVal($v);
    }

    public function get_orientationDate() {
        return $this->demogRS->Orientation_Date->getStoredVal();
    }

    public function set_contactDate($v) {
        $this->demogRS->Contact_Date->setNewVal($v);
    }

    public function get_contactDate() {
        return $this->demogRS->Contact_Date->getStoredVal();
    }

    public function set_confirmedDate($v) {
        $this->demogRS->Confirmed_Date->setNewVal($v);
    }

    public function get_confirmedDate() {
        return $this->demogRS->Confirmed_Date->getStoredVal();
    }

    public function set_genNotes($v) {
        $this->demogRS->Gen_Notes->setNewVal($v);
    }

    public function get_genNotes() {
        return $this->demogRS->Gen_Notes->getStoredVal();
    }

    public function set_bmonth($bmonth) {
        if (is_null($bmonth) || $bmonth < 0 || $bmonth > 12) {
            $bmonth = 0;
        }
        $this->nameRS->Birth_Month->setNewVal($bmonth);
    }

    public function get_bmonth() {
        if ($this->nameRS->BirthDate->getStoredVal() == '') {
            return $this->nameRS->Birth_Month->getStoredVal();
        } else {
            return date('m', strtotime($this->nameRS->BirthDate->getStoredVal()));
        }
    }

    public function set_birthDate($v) {
        $this->nameRS->BirthDate->setNewVal($v);
    }

    public function get_birthDate() {
        return $this->nameRS->BirthDate->getStoredVal();
    }

    public function set_DateDeceased($v) {
        $this->nameRS->Date_Deceased->setNewVal($v);
    }

    public function get_DateDeceased() {
        return $this->nameRS->Date_Deceased->getStoredVal();
    }

    public function get_DateBackgroundCheck() {
        return $this->demogRS->Background_Check_Date->getStoredVal();
    }

    public function set_sex($sex) {
        $this->nameRS->Gender->setNewVal($sex);
    }

    public function get_sex() {
        return $this->nameRS->Gender->getStoredVal();
    }

    public function set_suffix($suffix) {
        $this->nameRS->Name_Suffix->setNewVal($suffix);
    }

    public function get_suffix() {
        return $this->nameRS->Name_Suffix->getStoredVal();
    }

    public function set_prefix($prefix) {
        $this->nameRS->Name_Prefix->setNewVal($prefix);
    }

    public function get_prefix() {
        return $this->nameRS->Name_Prefix->getStoredVal();
    }

    public function set_since($since) {
        $this->nameRS->Member_Since->setNewVal($since);
    }

    public function get_since() {
        return date('M j, Y', strtotime($this->nameRS->Member_Since->getStoredVal()));
    }

    public function set_preferredMailAddr($preferredMailAddr) {
        $this->nameRS->Preferred_Mail_Address->setNewVal($preferredMailAddr);
    }

    public function get_preferredMailAddr() {
        return (is_null($this->nameRS->Preferred_Mail_Address->getStoredVal()) ? "" : $this->nameRS->Preferred_Mail_Address->getStoredVal());
    }

    public function set_preferredEmail($preferredEmail) {
        $this->nameRS->Preferred_Email->setNewVal($preferredEmail);
    }

    public function get_preferredEmail() {
        return (is_null($this->nameRS->Preferred_Email->getStoredVal()) ? "" : $this->nameRS->Preferred_Email->getStoredVal());
    }

    public function set_preferredPhone($preferredPhone) {
        $this->nameRS->Preferred_Phone->setNewVal($preferredPhone);
    }

    public function get_preferredPhone() {
        return (is_null($this->nameRS->Preferred_Phone->getStoredVal()) ? "" : $this->nameRS->Preferred_Phone->getStoredVal());
    }

    public function set_orgCode($orgCode) {
        $this->nameRS->Organization_Code->setNewVal($orgCode);
    }

    public function get_orgCode() {
        return $this->nameRS->Organization_Code->getStoredVal();
    }

    public function set_company($company) {
        $this->nameRS->Company->setNewVal($company);
    }

    public function get_company() {
        return $this->nameRS->Company->getStoredVal();
    }

    public function set_companyId($companyId) {
        $this->nameRS->Company_Id->setNewVal($companyId);
    }

    public function get_companyId() {
        return $this->nameRS->Company_Id->getStoredVal();
    }

    public function set_companyCareOf($v) {
        $this->nameRS->Company_CareOf->setnewVal($v);
    }

    public function get_companyCareOf() {
        return $this->nameRS->Company_CareOf->getStoredVal();
    }

    public function set_title($title) {
        $this->nameRS->Title->setNewVal($title);
    }

    public function get_title() {
        return $this->nameRS->Title->getStoredVal();
    }

    public function set_memberRcrd($v) {
        $this->nameRS->Record_Member->setNewVal($v);
    }

    public function get_memberRcrd() {
        if ($this->nameRS->Record_Member->getStoredVal() == '1' || ord($this->nameRS->Record_Member->getStoredVal()) == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function set_companyRcrd($v) {
        $this->nameRS->Record_Company->setNewVal($v);
    }

    public function get_companyRcrd() {
        if ($this->nameRS->Record_Company->getStoredVal() == '1' || ord($this->nameRS->Record_Company->getStoredVal()) == 1) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function set_lastUpdated($lastUpdated) {
        $this->nameRS->Last_Updated->setNewVal($lastUpdated);
    }

    public function get_lastUpdated() {
        if ($this->nameRS->Last_Updated->getStoredVal() != '') {
            return date('M j, Y', strtotime($this->nameRS->Last_Updated->getStoredVal()));
        } else {
            return '';
        }
    }

    public function set_ExternalId($v) {
        $id = intval($v, 10);
        $this->nameRS->External_Id->setNewVal($id);
    }
}
?>