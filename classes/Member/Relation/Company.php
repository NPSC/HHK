<?php

namespace HHK\Member\Relation;

use HHK\SysConst\{RelLinkType};
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\Tables\Name\NameRS;
use HHK\sec\Session;
use HHK\AuditLog\NameLog;

/**
 * Company.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Company extends AbstractRelation {

    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Company, 'Description'=>'Company'));

    }

    protected function getPdoStmt(\PDO $dbh) {

        $query = "Select v.Company_Id as `Id`, v.Company as `Name`, v.MemberStatus as `MemStatus` from vmember_listing v where v.Id = :id and v.Company_Id > 0;";

        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);

        return $stmt;
    }

    protected function getHtmlId() {
        return "Company";
    }

    protected function createNewEntry() {
        if (count($this->relNames)  == 0) {
            return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Company', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
        } else {
            return "";
        }
    }


    public function addRelationship(\PDO $dbh, $rId, $user) {

        $empId = intval($this->getIdName());

        if ($rId > 0 && $empId <> $rId) {
            // Update employee record
            $query = "update  name n, name c set n.Company_Id = :id, n.Company = c.Company
                where n.idName = :empId and n.Record_Member = 1 and c.idName = :id3 and c.Record_Company = 1 and n.Company_Id <> :id2;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':id'=>$rId, ':id2'=>$rId, ':id3'=>$rId, ':empId'=>$empId));

            $nRS = new NameRS();
            $nRS->idName->setStoredVal($empId);
            $nRS->Company_Id->setNewVal($rId);
            NameLog::writeUpdate($dbh, $nRS, $empId, $user);

            return 'Company link added.  ';
        }

        return '';
    }

    public function removeRelationship(\PDO $dbh, $rId) {

        $uS = Session::getInstance();

        $query = "update name n set n.Company_Id = 0, n.Company = '', n.Company_CareOf = '' where n.idName = :id;";
        $stmt = $dbh->prepare($query);
        $idw = $this->getIdName();
        $stmt->execute(array(':id'=> $idw));

        $nRS = new NameRS();
        $nRS->idName->setStoredVal($this->getIdName());
        $nRS->Company_Id->setNewVal(0);
        NameLog::writeUpdate($dbh, $nRS, $this->getIdName(), $uS->username, 'Company_Id');

        return 'Company link removed.  ';
    }
}
?>