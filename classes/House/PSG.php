<?php

namespace HHK\House;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\Member\Role\Guest;
use HHK\SysConst\{NameGuestStatus, RelLinkType, VisitStatus};
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\Visit_LogRS;
use HHK\Tables\Name\{Name_GuestRS, NameRS};
use HHK\Tables\Registration\PSG_RS;
use HHK\sec\{Session, Labels};
use HHK\Exception\RuntimeException;

/**
 * PSG.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 * Description of psg:  Class for patient support group
 *
 * @author Eric Crane
 */

class PSG {

    public $psgRS;
    public $psgMembers = array();

    public function __construct(\PDO $dbh, $idPsg = 0, $idPatient = 0) {

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

    public function setNewMember($idGuest, $relationshipCode) {

        if ($idGuest > 0 && $relationshipCode != '') {

            if (isset($this->psgMembers[$idGuest])) {

                $this->psgMembers[$idGuest]->Relationship_Code->setNewVal($relationshipCode);

            } else {

                $ngRs = new Name_GuestRS();
                $ngRs->idName->setNewVal($idGuest);
                $ngRs->Relationship_Code->setNewVal($relationshipCode);
                $ngRs->Status->setNewVal(NameGuestStatus::Active);
                $this->psgMembers[$idGuest] = $ngRs;
            }
        }
    }

    protected function loadExistingMembers(\PDO $dbh) {

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

    public static function getNameGuests(\PDO $dbh, $idGuest) {

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

    public function removeGuest(\PDO $dbh, $idGuest, $uname) {

        $ngRs = $this->psgMembers[$idGuest];

        if ($ngRs->Relationship_Code->getStoredVal() != RelLinkType::Self && Guest::checkPsgStays($dbh, $idGuest, $this->getIdPsg(), TRUE) === FALSE) {

            $count = EditRS::delete($dbh, $ngRs, array($ngRs->idName, $ngRs->idPsg));

            if ($count == 1) {
                $logText = VisitLog::getDeleteText($ngRs, $idGuest);
                VisitLog::logNameGuest($dbh, $this->getIdPsg(), $idGuest, $logText, "delete", $uname);

                unset($this->psgMembers[$idGuest]);

                // Remove guest from 0-day stays
                //get stays
                $query = "select s.idStays, s.idVisit, s.Visit_Span, (select count(*) from stays where stays.idVisit = s.idVisit and stays.Visit_Span = s.Visit_Span) as 'VisitStayCount'
from stays s join visit v on s.idVisit = v.idVisit
	left join registration r on v.idRegistration = r.idRegistration
where r.idPsg = :idPsg and s.idName = :idGuest and DATEDIFF(s.Span_End_Date, s.Span_Start_Date) = 0";

                $stmt = $dbh->prepare($query);
                $stmt->execute(array(':idPsg'=>$this->getIdPsg(), ':idGuest'=>$idGuest));
                $stays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach($stays as $stay){
                    if($stay['VisitStayCount'] == 1){
                        $query = "delete from visit v where idVisit = :idVisit and Visit_Span = :visitSpan";
                        $stmt = $dbh->prepare($query);
                        $stmt->execute(array(':idVisit'=>$stay['idVisit'], ':visitSpan'=>$stay['Visit_Span']));
                    }
                    $query = "delete from stays where idStays = :idStays";
                    $stmt = $dbh->prepare($query);
                    $stmt->execute(array(':idStays'=>$stay['idStays']));
                }

                return TRUE;
            }
        }

        return FALSE;
    }

    public function createEditMarkup(\PDO $dbh, $relList, Labels $labels, $pageName = 'GuestEdit.php', $id = 0, $shoChgLog = FALSE) {

        $pTable = '';

        // Notes section
        $notesContainer = HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', $labels->getString('guestEdit', 'psgTab', 'Patient Support Group').' Notes', array('style'=>'font-weight:bold;'))
            , array('id'=>'psgNoteViewer', 'style'=>'clear:left; float:left; width:90%;', 'class'=>'hhk-panel'));

        // Members section
        $relListLessSlf = $relList;
        unset($relListLessSlf[RelLinkType::Self]);
        $relListLessSlf = removeOptionGroups($relListLessSlf);


        $mTable = new HTMLTable();
        $mTable->addHeaderTr(HTMLTable::makeTh('Remove').HTMLTable::makeTh('Id').HTMLTable::makeTh('Name').HTMLTable::makeTh('Relationship to '.$labels->getString('MemberType', 'patient', 'Patient')).HTMLTable::makeTh('Guardian').HTMLTable::makeTh('Phone'));

        $stmt = $dbh->query("SELECT
            ng.idName AS `idGuest`,
            ng.Relationship_Code,
            ng.Legal_Custody,
            IFNULL(n.Name_Full, '') AS `Name_Full`,
            IFNULL(np.Phone_Num, '') AS `Preferred_Phone`
        FROM
            name_guest ng
                JOIN
            psg p ON ng.idPsg = p.idPsg
                JOIN
            name n ON n.idName = ng.idName
                LEFT JOIN
            name_phone np ON np.idName = n.idName
                AND n.Preferred_Phone = np.Phone_Code
        WHERE
            ng.idPsg = " . $this->getIdPsg() . " ;");


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

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
                $nme = HTMLContainer::generateMarkup('span', $r['Name_Full'], array('style'=>'font-weight:bold;'));

            } else {

                $rem = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'delpMem['.$r['idGuest'].']'));
                $rel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($relListLessSlf, $r['Relationship_Code'], FALSE), array('name'=>'selPrel['.$r['idGuest'].']'));
                $nme = $r['Name_Full'];
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
            $lcdDT = new \DateTime($lastConfDate);
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


        // Change log
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
               // .$table
                . $memMkup
                . $lastConfirmed
                . $notesContainer //$nTable->generateMarkup(array('style'=>'clear:left;width:700px;float:left;'))
                . $c . $v;

        return $editDiv;

    }

    protected function verifyUniquePatient(\PDO $dbh, $idPatient) {

        if ($idPatient < 1) {
            return;
        }

        // Only one PSG per patient.
        $psgRs = new PSG_RS();
        $psgRs->idPatient->setStoredVal($idPatient);
        $patRows = EditRS::select($dbh, $psgRs, array($psgRs->idPatient));

        if (count($patRows) > 0) {
            EditRS::loadRow($patRows[0], $psgRs);

            if ($this->psgRS->idPsg->getStoredVal() != 0 && $psgRs->idPsg->getStoredVal() != $this->psgRS->idPsg->getStoredVal()) {
                throw new RuntimeException('Patient already has a PSG. ');
            }
        }
    }

    public function saveMembers(\PDO $dbh, $uname) {

        if ($this->getIdPsg() == 0) {
            return;
        }

        $foundPatient = FALSE;

        // Check for just one patient
        foreach ($this->psgMembers as $ngRS) {

            if (($ngRS->Relationship_Code->getStoredVal() == RelLinkType::Self || $ngRS->Relationship_Code->getNewVal() == RelLinkType::Self) && $foundPatient) {
                // Second patient defined.
                throw new RuntimeException('PSG already has a patient.');
            } else if (($ngRS->Relationship_Code->getStoredVal() == RelLinkType::Self || $ngRS->Relationship_Code->getNewVal() == RelLinkType::Self)
                    && ($this->getIdPatient() == $ngRS->idName->getStoredVal() || $this->getIdPatient() == $ngRS->idName->getNewVal())) {
                $foundPatient = TRUE;
            }
        }

        // Check for at least one patient.
        if ($foundPatient === FALSE) {
            throw new RuntimeException('A Patient is undefined for this PSG.');
        }

        // Save each member
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
                VisitLog::logNameGuest($dbh, $this->getIdPsg(), $ngRS->idName->getNewVal(), $logText, "insert", $uname);
            }

            EditRS::updateStoredVals($ngRS);
        }

        return;
    }

    public function savePSG(\PDO $dbh, $idPatient, $uname, $notes = '') {

        if ($idPatient < 1) {
            return;
        }

        $this->verifyUniquePatient($dbh, $idPatient);

        $sanNotes = filter_var($notes, FILTER_SANITIZE_STRING);

        if ($sanNotes != '') {
            $oldNotes = (is_null($this->psgRS->Notes->getStoredVal()) ? '' : $this->psgRS->Notes->getStoredVal());
            $this->psgRS->Notes->setNewVal($oldNotes . "\r\n" . date('m-d-Y') . ($uname != '' ? ', '.$uname : $uname) . ' - ' . $sanNotes);
        }


        $this->psgRS->Updated_By->setNewVal($uname);
        $this->psgRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));


        if ($this->psgRS->idPsg->getStoredVal() === 0) {

            // New PSG
            $this->psgRS->idPatient->setNewVal($idPatient);
            $this->psgRS->Status->setNewVal('a');
            $idPsg = EditRS::insert($dbh, $this->psgRS);

            $this->psgRS->idPsg->setNewVal($idPsg);

            $logText = VisitLog::getInsertText($this->psgRS);
            VisitLog::logPsg($dbh, $idPsg, $idPatient, $logText, "insert", $uname);

            $this->setNewMember($idPatient, RelLinkType::Self);

        } else {

            EditRS::update($dbh, $this->psgRS, array($this->psgRS->idPsg));

            $logText = VisitLog::getUpdateText($this->psgRS);
            VisitLog::logPsg($dbh, $this->psgRS->idPsg->getStoredVal(), $idPatient, $logText, "update", $uname);

        }

        EditRS::updateStoredVals($this->psgRS);

        $this->saveMembers($dbh, $uname);

        return;
    }

    public function countCurrentGuests(\PDO $dbh) {

        $query = "select count(*) "
                . " from stays s join visit v on s.idVisit = v.idVisit"
                . " join registration r on v.idRegistration = r.idRegistration"
                . " where r.idPsg = '" . $this->getIdPsg() . "' and s.Status='" . VisitStatus::CheckedIn . "'";

        $stmt = $dbh->query($query);
        $cnt = $stmt->fetchAll(\PDO::FETCH_NUM);

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

    public function getPatientName(\PDO $dbh) {

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

    public function getPatientStatus(\PDO $dbh) {

        $nameRS = new NameRS();

        if ($this->getIdPatient() > 0) {
            $nameRS->idName->setStoredVal($this->getIdPatient());
            $rows = EditRS::select($dbh, $nameRS, array($nameRS->idName));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $nameRS);
            }
        }

        return $nameRS->Member_Status->getStoredVal();
    }
}
?>