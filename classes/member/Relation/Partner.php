<?php

namespace HHK\Member\Relation;

use HHK\SysConst\{RelLinkType};
use HHK\AuditLog\NameLog;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\Tables\EditRS;
use HHK\Tables\Name\RelationsRS;
use HHK\sec\Session;

/**
 * Partner.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Partner extends AbstractRelation {

    protected $statusTitle;
    protected $status;

    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Spouse, 'Description'=>'Partner'));

    }

    protected function getPdoStmt(\PDO $dbh) {

        $query = "Select
    v.Id,
    concat(v.Name_First, ' ', v.Name_Last) as `Name`,
    v.MemberStatus as `MemStatus`,
    g.Description as `Status_Text`,
    r . * from relationship r join vmember_listing v ON (r.Target_Id = v.Id or r.idName = v.Id) and v.Id <> :id1
    left join gen_lookups g ON g.Table_Name = 'mem_status' and g.Code = v.MemberStatus
    where r.Relation_Type = '".RelLinkType::Spouse."' and r.Status = 'a' and (r.idName = :id or r.Target_Id = :idw);";

        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);
        $stmt->bindParam(':idw', $idVar);
        $stmt->bindParam(':id1', $idVar);

        return $stmt;
    }

    protected function getHtmlId() {
        return "Partner";
    }

    protected function createNewEntry() {
        if (count($this->relNames)  == 0) {
            return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Partner', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
        }
        return "";
    }


    public function addRelationship(\PDO $dbh, $myspId, $user) {
        $resultMessage = "";

        if (count($this->relNames) > 0) {
            return "Already Married.  ";
        }
        $id = $this->getIdName();

        if ($myspId > 0) {

            $relRS = new relationsRS();

            $relRS->Date_Added->setNewVal(date("Y-m-d H:i:s"));
            $relRS->idName->setNewVal($id);
            $relRS->Target_Id->setNewVal($myspId);
            $relRS->Relation_Type->setNewVal($this->relCode->getCode());
            $relRS->Status->setNewVal('a');
            $relRS->Updated_By->setNewVal($user);

            EditRS::insert($dbh, $relRS);


            // insert name_log
            NameLog::writeInsert($dbh, $relRS, $id, $user, 'Partner');
            NameLog::writeInsert($dbh, $relRS, $myspId, $user, 'Partner');

            $resultMessage = "Partner link added. ";
        }
        return $resultMessage;

    }

    public function removeRelationship(\PDO $dbh, $rId) {

        if (count($this->relNames) == 0) {
            return "";
        }
        $id = $this->getIdName();
        $uS = Session::getInstance();

        $qq = "Delete from relationship where Relation_Type='" . $this->relCode->getCode() . "' and (idName=:id or Target_Id=:id2 )";
        $stmt = $dbh->prepare($qq);
        $stmt->execute(array(':id'=>$id, ':id2'=>$id));

        $relRS = new relationsRS();
        $relRS->Relation_Type->setStoredVal($this->relCode->getCode());
        $relRS->Relation_Type->setNewVal('');

        // insert name_log
        NameLog::writeDelete($dbh, $relRS, $id, $uS->username, 'Partner');
        NameLog::writeDelete($dbh, $relRS, $rId, $uS->username, 'Partner');

        return "Partner link removed. ";

    }
}
?>