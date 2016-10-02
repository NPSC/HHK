<?php
/**
 * psg.php
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
/**
 * Description of psg:  Class for patient support group
 *
 * @author Eric Crane
 */
class CheckInGroup {
    public $patient;
    public $newGuests;
    public $psg;
    public $hospitalStay;
    protected $patientCkgIn;

    function __construct() {

        $this->newGuests = array();
        $this->patientCkgIn= FALSE;
    }


    public function saveMembers(PDO $dbh, $idHospitalStay, $post) {

        $uS = Session::getInstance();
        $this->patient = NULL;
        $this->psg = null;
        $this->patientCkgIn = FALSE;

        // Identify & save the guests
        foreach ($post["mbrs"] as $prefix) {

            if (isset($post[$prefix . 'idName']) === FALSE) {
                throw new Hk_Exception_InvalidArguement("Member Id value is missing (CheckInGroup).");
            }

            $idg = intval(filter_var($post[$prefix . 'idName'], FILTER_SANITIZE_NUMBER_INT), 10);

            if ($prefix != 'h_') {

                //Save guest
                $guest = new Guest($dbh, $prefix, $idg);

                $name = $guest->getNameObj();
                if ($name->get_status() == MemStatus::Deceased) {
                     throw new Hk_Exception_UnexpectedValue('This Guest is marked as "Deceased".');
                }

                $guest->save($dbh, $post, $uS->username);

                if ($guest->isCurrentlyStaying() === FALSE) {
                    $this->newGuests[$guest->getIdName()] = $guest;
                }

                if ($guest->getPatientRelationshipCode() == RelLinkType::Self && $uS->PatientAsGuest) {

                    // Guest is also the patient - get psg
                    $this->psg = new Psg($dbh, 0, $guest->getIdName());
                    $this->psg->setNewMember($guest->getIdName(), 0, RelLinkType::Self);

                    if ($guest->isCurrentlyStaying()) {

                        // guest is the patient of a current visit
                        $this->patient = new Patient($dbh, 'h_', $guest->getIdName());

                        // Patient not checking in
                        $this->patientCkgIn = FALSE;

                    } else {
                        $this->patient = $guest;
                        $this->patientCkgIn = TRUE;
                    }
                }
            }
        }

        // Found the patient?
        if (is_null($this->patient) === FALSE) {
            // Yes
            return;
        }

        // Search for explicit patient?
        if (isset($post['h_idName'])) {

            // Found a patient
            $idp = intval(filter_var($post['h_idName'], FILTER_SANITIZE_NUMBER_INT), 10);

            // Don't allow if the patient is already set up as a guest
            if (array_key_exists($idp, $this->newGuests) && $uS->PatientAsGuest === FALSE) {
                throw new Hk_Exception_Runtime('A Patient cannot stay at this House.  ');
            }

            $this->patient = new Patient($dbh, 'h_', $idp);
            $this->patient->save($dbh, $post, $uS->username);

            $this->psg = $this->patient->getPsgObj($dbh);

        } else if ($idHospitalStay > 0) {

            $hospStay = new HospitalStay($dbh, 0, $idHospitalStay);

            $this->patient = new Patient($dbh, 'h_', $hospStay->getIdPatient());
            $this->psg = $this->patient->getPsgObj($dbh);

        } else {

            if (isset($post['psgId'])) {
                $idPsg = intval(filter_var($post['psgId'], FILTER_SANITIZE_NUMBER_INT), 10);
                $this->psg = new Psg($dbh, $idPsg);
                $this->patient = new Patient($dbh, 'h_', $this->psg->getIdPatient());
            }
        }

        return;
    }


    public function savePsg(PDO $dbh, $notes, $username) {

        if (is_null($this->psg)) {
            throw new Hk_Exception_Runtime('The PSG must be defined (CheckInGroup).  ');
        }

        foreach ($this->newGuests as $guest) {

            $this->psg->setNewMember($guest->getIdName(), 0, $guest->getPatientRelationshipCode());
        }

        $this->psg->savePSG($dbh, $this->patient->getIdName(), $username, $notes);

        return $this->psg;
    }

    public function isPatientCkgIn() {
        return $this->patientCkgIn;
    }

}





class Psg {

    public $psgRS;
    public $psgMembers = array();

    public function __construct(PDO $dbh, $idPsg = 0, $idPatient = 0) {

        $this->psgRS = new PSG_RS();
        $fldArray = array();

        if ($idPsg > 0) {
            $this->psgRS->idPsg->setStoredVal($idPsg);
            $fldArray[] = $this->psgRS->idPsg;
        } else if ($idPatient > 0) {
            $this->psgRS->idPatient->setStoredVal($idPatient);
            $fldArray[] = $this->psgRS->idPatient;
        } else {
            return;
        }

        $psgRows = EditRS::select($dbh, $this->psgRS, $fldArray);

        if (count($psgRows) > 0) {
            EditRS::loadRow($psgRows[0], $this->psgRS);
            $this->loadExistingMembers($dbh);
        }

    }

    public function setNewMember($idGuest, $legalCustody = 0, $relationshipCode = '') {

        if ($idGuest > 0) {

            if (isset($this->psgMembers[$idGuest])) {

                $this->psgMembers[$idGuest]->Relationship_Code->setNewVal($relationshipCode);
                //$this->psgMembers[$idGuest]->Legal_Custody->setNewVal($legalCustody);

            } else {

                $ngRs = new Name_GuestRS();
                $ngRs->idName->setNewVal($idGuest);
                $ngRs->Relationship_Code->setNewVal($relationshipCode);
                //$ngRs->Legal_Custody->setNewVal($legalCustody);
                $ngRs->Status->setNewVal(NameGuestStatus::Active);
                $this->psgMembers[$idGuest] = $ngRs;
            }
        }
    }

    protected function loadExistingMembers(PDO $dbh) {

        $ngRS = new Name_GuestRS();
        $this->psgMembers = array();

        if ($this->getIdPsg() > 0) {

            $ngRS->idPsg->setStoredVal($this->getIdPsg());
            $rows = EditRS::select($dbh, $ngRS, array($ngRS->idPsg));

            foreach ($rows as $r) {

                $ngRS = new Name_GuestRS();
                EditRS::loadRow($r, $ngRS);
                $this->psgMembers[$ngRS->idName->getStoredVal()] = $ngRS;
            }
        }
    }

    public static function instantiateFromGuestId(PDO $dbh, $idGuest) {

        $ngRS = new Name_GuestRS();
        $idPsg = 0;

        if ($idGuest > 0) {
            $ngRS->idName->setStoredVal($idGuest);
            $rows = EditRS::select($dbh, $ngRS, array($ngRS->idName));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $ngRS);
                $idPsg = $ngRS->idPsg->getStoredVal();
            }
        }

        return new Psg($dbh, $idPsg);

    }

    public static function getNameGuests(PDO $dbh, $idGuest) {

        $ngRS = new Name_GuestRS();
        $ngs = array();

        if ($idGuest > 0) {
            $ngRS->idName->setStoredVal($idGuest);
            $rows = EditRS::select($dbh, $ngRS, array($ngRS->idName));

            foreach ($rows as $r) {
                $ngRS = new Name_GuestRS();
                EditRS::loadRow($r, $ngRS);
                $ngs[] = $ngRS;
            }
        }
        return $ngs;
    }

    public function removeGuest(PDO $dbh, $idGuest, $uname) {

        $ngRs = $this->psgMembers[$idGuest];

        if ($ngRs->Relationship_Code->getStoredVal() != RelLinkType::Self && Guest::checkCurrentStay($dbh, $idGuest) === FALSE) {

            $count = EditRS::delete($dbh, $ngRs, array($ngRs->idName, $ngRs->idPsg));

            if ($count == 1) {
                $logText = VisitLog::getDeleteText($ngRs, $idGuest);
                VisitLog::logNameGuest($dbh, $this->getIdPsg(), $idGuest, $logText, "delete", $uname);

                unset($this->psgMembers[$idGuest]);
            }
        }
    }

    public static function loadViews(PDO $dbh, $Guest_Id, $idPsg = 0) {

        if ($Guest_Id > 0) {

            // Get PSG Data
            $query = "Select * from vpsg_guest
                where idGuest = :idName;";

            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(":idName" => $Guest_Id));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($stmt->rowCount() >= 1) {

                return $rows[0];
            }
        } else if ($idPsg > 0) {
            // Get PSG Data
            $query = "Select * from vpsg_guest
                where idPsg = :idP order by isPatient DESC;";

            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(":idP" => $idPsg));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $rows;

        }
        return array();
    }

    public function createEditMarkup(PDO $dbh, $relList, $labels, $pageName = 'GuestEdit.php', $id = 0, $shoChgLog = FALSE) {

        // Edit Div
        $hArray = Hospital::createReferralMarkup($dbh, new HospitalStay($dbh, $this->getIdPatient()));
        $table = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend','Hospital', array('style'=>'font-weight:bold;'))
                        . $hArray['div'],
                        array('class'=>'hhk-panel')),
                array('style'=>'float:left;', 'id'=>'hospitalSection'));

        $pTable = '';


        // Notes section
        $nTable = new HTMLTable();
        $nTable->addBodyTr(HTMLTable::makeTh('Patient Support Group Notes'));
        $nTable->addBodyTr(HTMLTable::makeTd(Notes::markupShell($this->psgRS->Notes->getStoredVal(), 'txtPSGNotes')));

        // Members section
        $mTable = new HTMLTable();
        $mTable->addHeaderTr(HTMLTable::makeTh('Remove').HTMLTable::makeTh('Id').HTMLTable::makeTh('Name').HTMLTable::makeTh('Relationship to Patient').HTMLTable::makeTh('Guardian').HTMLTable::makeTh('Phone'));
        $rows = $this->loadViews($dbh, 0, $this->getIdPsg());

        $relListLessSlf = $relList;
        unset($relListLessSlf[RelLinkType::Self]);


        foreach ($rows as $r) {

            if ($r['idGuest'] == $id) {
                $ent = HTMLContainer::generateMarkup('a', $r['idGuest'], array('href'=>$pageName.'?id='.$r['idGuest'].'&psg='.$this->getIdPsg(), 'class'=>'ui-state-highlight'));
            } else {
                $ent = HTMLContainer::generateMarkup('a', $r['idGuest'], array('href'=>$pageName.'?id='.$r['idGuest'].'&psg='.$this->getIdPsg()));
            }

            if ($r['Legal_Custody'] > 0) {
                $grd = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbLegalCust['.$r['idGuest'].']', 'checked'=>'checked'));
            } else {
                $grd = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbLegalCust['.$r['idGuest'].']'));
            }

            if ($r['Relationship_Code'] == RelLinkType::Self) {

                $rem = '';
                $rel = HTMLContainer::generateMarkup('span', $relList[RelLinkType::Self][1], array('style'=>'font-weight:bold;'));
                $nme = HTMLContainer::generateMarkup('span', $r['Name_First'] . ' ' . $r['Name_Last'], array('style'=>'font-weight:bold;'));

            } else {

                $rem = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'delpMem['.$r['idGuest'].']'));
                $rel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($relListLessSlf, $r['Relationship_Code'], FALSE), array('name'=>'selPrel['.$r['idGuest'].']'));
                $nme = $r['Name_First'] . ' ' . $r['Name_Last'];
            }

            $mTable->addBodyTr(
                    HTMLTable::makeTd($rem, array('style'=>'text-align:center;'))
                    .HTMLTable::makeTd($ent)
                    .HTMLTable::makeTd($nme)
                    .HTMLTable::makeTd($rel)
                    .HTMLTable::makeTd($grd, array('style'=>'text-align:center;'))
                    .HTMLTable::makeTd($r['Preferred_Phone'])
                    );
        }

        $memMkup =  HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend','Members', array('style'=>'font-weight:bold;'))
                        . $mTable->generateMarkup(),
                        array('class'=>'hhk-panel')),
                array('style'=>'float:left;'));

        $lastConfDate = $this->psgRS->Info_Last_Confirmed->getStoredVal();
        if ($lastConfDate != '') {
            $lcdDT = new DateTime($lastConfDate);
            $lastConfDate = $lcdDT->format('M j, Y');
        }

        $lastConfirmed =  HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $labels->getString('guestEdit', 'psgTab', 'Patient Support Group').' Info Last Confirmed', array('style'=>'font-weight:bold;'))
                        . HTMLContainer::generateMarkup('label', 'Update:', array('for'=>'cbLastConfirmed'))
                        . HTMLInput::generateMarkup('', array('name'=>'cbLastConfirmed', 'type'=>'checkbox','style'=>'margin-left:.3em;'))
                        . HTMLInput::generateMarkup($lastConfDate, array('name'=>'txtLastConfirmed', 'class'=>'ckdate','style'=>'margin-left:1em;'))
                        , array('class'=>'hhk-panel')),
                array('style'=>'float:left;'));

        $c = '';
        $v = '';
        if ($shoChgLog) {
            // Change Log Section
            $lTable = new HTMLTable();
            $visLogRS = new Visit_LogRS();
            if ($this->psgRS->idPsg->getStoredVal() > 0) {
                $visLogRS->idPsg->setStoredVal($this->psgRS->idPsg->getStoredVal());
                $rows = EditRS::select($dbh, $visLogRS, array($visLogRS->idPsg));

                foreach ($rows as $r) {
                    $vlRS = new Visit_LogRS();
                    EditRS::loadRow($r, $vlRS);
                    if ($vlRS->idName->getStoredVal() == $id) {
                        // my id
                        $ent = HTMLContainer::generateMarkup('span', $id, array('class'=>'ui-state-highlight'));
                    } else {
                        $ent = $vlRS->idName->getStoredVal();
                    }
                    $lTable->addBodyTr(
                            HTMLTable::makeTd(date('m/d/Y', strtotime($vlRS->Timestamp->getStoredVal())))
                        .HTMLTable::makeTd($vlRS->Log_Type->getStoredVal())
                        .HTMLTable::makeTd($vlRS->Sub_Type->getStoredVal())
                        .HTMLTable::makeTd($vlRS->User_Name->getStoredVal())
                        .HTMLTable::makeTd($ent)
                        .HTMLTable::makeTd(VisitLog::parseLogText($vlRS->Log_Text->getStoredVal()))
                        );
                }
            }

            $lTable->addHeaderTr(HTMLTable::makeTh('Date').HTMLTable::makeTh('Table'). HTMLTable::makeTh('Operation') . HTMLTable::makeTh('User') . HTMLTable::makeTh('Id') . HTMLTable::makeTh('Message'));

            $c = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('span', '(Show Log)'), array('id'=>'hideLog','style'=>'margin-top:.5em;'));
            $v = HTMLContainer::generateMarkup('div', $lTable->generateMarkup(), array('id'=>'divPsgLog', 'style'=>'display:none; clear:left;'));
        }

        $editDiv = $pTable
                .$table
                . $memMkup
                . $lastConfirmed
                . $nTable->generateMarkup(array('style'=>'clear:left;width:700px;float:left;'))
                . $c . $v;

        return $editDiv;

    }


    protected function saveMembers(PDO $dbh, $uname) {

        if ($this->getIdPsg() == 0) {
            return;
        }

        foreach ($this->psgMembers as $ngRS) {

            $ngRS->idPsg->setNewVal($this->getIdPsg());
            $ngRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $ngRS->Updated_By->setNewVal($uname);

            if ($ngRS->idPsg->getStoredVal() !== 0) {

                EditRS::update($dbh, $ngRS, array($ngRS->idName, $ngRS->idPsg));
                $logText = VisitLog::getUpdateText($ngRS);
                VisitLog::logNameGuest($dbh, $this->getIdPsg(), $ngRS->idName->getStoredVal(), $logText, "update", $uname);

            } else {

                EditRS::insert($dbh, $ngRS);

                $logText = VisitLog::getInsertText($ngRS);
                VisitLog::logNameGuest($dbh, $this->getIdPsg(), $ngRS->idName->getStoredVal(), $logText, "insert", $uname);
            }

            EditRS::updateStoredVals($ngRS);
        }

        return;

    }

    public function savePSG(PDO $dbh, $idPatient, $uname, $notes = '') {

        if ($idPatient == 0) {
            return;
        }

        $sanNotes = filter_var($notes, FILTER_SANITIZE_STRING);

        if ($uname != '') {
            $uname =  ', ' . $uname;
        }

        if ($sanNotes != '') {
            $oldNotes = (is_null($this->psgRS->Notes->getStoredVal()) ? '' : $this->psgRS->Notes->getStoredVal());
            $this->psgRS->Notes->setNewVal($oldNotes . "\r\n" . date('m-d-Y') . $uname . ' - ' . $sanNotes);
        }


        $this->psgRS->idPatient->setNewVal($idPatient);
        $this->psgRS->Status->setNewVal('a');
        $this->psgRS->Updated_By->setNewVal($uname);
        $this->psgRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));


        if ($this->psgRS->idPsg->getStoredVal() === 0) {

            $idPsg = EditRS::insert($dbh, $this->psgRS);

            $this->psgRS->idPsg->setNewVal($idPsg);

            $logText = VisitLog::getInsertText($this->psgRS);
            VisitLog::logPsg($dbh, $idPsg, $idPatient, $logText, "insert", $uname);

            $this->setNewMember($idPatient, 0, RelLinkType::Self);

        } else {

            EditRS::update($dbh, $this->psgRS, array($this->psgRS->idPsg));

            $logText = VisitLog::getUpdateText($this->psgRS);
            VisitLog::logPsg($dbh, $this->psgRS->idPsg->getStoredVal(), $idPatient, $logText, "update", $uname);

        }

        EditRS::updateStoredVals($this->psgRS);

        $this->saveMembers($dbh, $uname);

        return;
    }

    public function countCurrentGuests(PDO $dbh) {

        $query = "select count(*) "
                . " from stays s join visit v on s.idVisit = v.idVisit"
                . " join registration r on v.idRegistration = r.idRegistration"
                . " where r.idPsg = :psg and s.Status='" . VisitStatus::CheckedIn . "'";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':psg' => $this->getIdPsg()));
        $cnt = $stmt->fetchAll(PDO::FETCH_NUM);

        return $cnt[0][0];
    }

    public function setLegalCustody($idGuest, $custody) {

        if (isset($this->psgMembers[$idGuest])) {

            $cust = 0;
            if ($custody === TRUE || $custody == 1) {
                $cust = 1;
            }

            $this->psgMembers[$idGuest]->Legal_Custody->setNewVal($cust);
        }
    }

    public function setLastConfirmed($strDate) {
        $this->psgRS->Info_Last_Confirmed->setNewVal($strDate);
    }

    public function setPrimaryLanguage($languageId, $notes = '', $uname = '') {

        $this->psgRS->Primany_Language->setNewVal($languageId);

        $sanNotes = filter_var($notes, FILTER_SANITIZE_STRING);

        if ($uname != '') {
            $uname =  ', ' . $uname;
        }

        if ($sanNotes != '') {
            $oldNotes = (is_null($this->psgRS->Language_Notes->getStoredVal()) ? '' : $this->psgRS->Language_Notes->getStoredVal());
            $this->psgRS->Language_Notes->setNewVal($oldNotes . "\r\n" . date('m-d-Y') . $uname . ' - ' . $sanNotes);
        }
        return $this;
    }

    public function isLegalCustodian($idGuest) {

        $stat = 0;

        if (isset($this->psgMembers[$idGuest])) {

            if (is_null($this->psgMembers[$idGuest]->Legal_Custody->getNewVal())) {
                $stat = $this->psgMembers[$idGuest]->Legal_Custody->getStoredVal();
            } else {
                $stat = $this->psgMembers[$idGuest]->Legal_Custody->getNewVal();
            }

            if ($stat == 1) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public function getIdPatient() {
        return $this->psgRS->idPatient->getStoredVal();
    }

    public function getGuestRelationship($idGuest) {

        if (isset($this->psgMembers[$idGuest])) {

            if (is_null($this->psgMembers[$idGuest]->Relationship_Code->getNewVal())) {
                return $this->psgMembers[$idGuest]->Relationship_Code->getStoredVal();
            } else {
                return $this->psgMembers[$idGuest]->Relationship_Code->getNewVal();
            }
        }

        return '';
    }

    public function getIdPsg() {
        return $this->psgRS->idPsg->getStoredVal();
    }

    public function getPatientName(PDO $dbh) {

        $uS = Session::getInstance();
        $nameRS = new NameRS();

        if ($this->getIdPatient() > 0) {
            $nameRS->idName->setStoredVal($this->getIdPatient());
            $rows = EditRS::select($dbh, $nameRS, array($nameRS->idName));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $nameRS);
            }
        }

        return $nameRS->Name_First->getStoredVal() . ' ' . $nameRS->Name_Last->getStoredVal() . ($nameRS->Name_Suffix->getStoredVal() == '' ? '' : ' ' . $uS->nameLookups['Name_Suffix'][$nameRS->Name_Suffix->getStoredVal()][1]);
    }
}

