<?php

namespace HHK\Member\Relation;

use HHK\SysConst\MemStatus;
use HHK\SysConst\{RelLinkType};
use HHK\Tables\Name\{NameRS, RelationsRS};
use HHK\Tables\EditRS;
use HHK\sec\Session;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\AuditLog\NameLog;

/**
 * Employees.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Employees extends AbstractRelation {

    protected function loadRelCode() {
        return new RelationCode(array('Code'=>RelLinkType::Employee, 'Description'=>'Employee'));
    }

    protected function getPdoStmt(\PDO $dbh) {
        $query = "Select v.Id as `Id`, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, v.Company_CareOf
from vmember_listing v join vmember_listing c on v.Company_Id = c.Id
where c.Id = :id;";

        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);

        return $stmt;
    }

    protected function loadRecords(\PDO $dbh) {
        $rels = array();

//        $stmt = $dbh->prepare($this->getQuery(), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
//        $idVar = $this->getIdName();
//        $stmt->execute(array(":id" => $idVar));
        $stmt = $this->getPdoStmt($dbh);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $rs = new RelationsRS();
                EditRS::loadRow($r, $rs);
                $rels[] = array('Id' => $r["Id"], 'Name' => $r["Name"], 'Status'=>$r['MemStatus'], 'rs' => $rs, 'CareOf'=>$r["Company_CareOf"]);
            }
        }
        return $rels;
    }


    protected function getHtmlId() {
        return "Employee";
    }

    protected function createNewEntry() {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Employee', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'3', 'style'=>'text-align: center;')));
    }

    public function createMarkup($page = 'NameEdit.php') {

        $table = new HTMLTable();

        $htmlId = $this->idPrefex . $this->getHtmlId();

        $table->addHeaderTr(HTMLTable::makeTh('Employees', array('colspan'=>'3')));

        if (count($this->relNames) > 0) {

            foreach ($this->relNames as $rName) {

                if (isset($rName['CareOf']) && $rName['CareOf'] == 'y') {
                    $cof = TRUE;
                } else {
                    $cof = FALSE;
                }

                $deceasedClass = '';
                if ($rName['Status'] == MemStatus::Deceased) {
                    $deceasedClass = ' hhk-deceased';
                }



                $table->addBodyTr(
                    HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $rName["Name"], array('href'=>$page.'?id='.$rName['Id'], 'class'=>$deceasedClass, 'title'=>'Click to Edit ' . $rName["Name"], 'style'=>'padding-left: .3em;padding-right:.3em;')))
                    .HTMLTable::makeTd($this->trash, array('name'=>$rName['Id'], 'class'=>'ui-widget-header hhk-deletelink', 'title'=>'Delete ' . $this->relCode->getTitle() . ' Link to ' . $rName["Name"]))
                    .HTMLTable::makeTd($cof ? $this->careof : $this->addCareof, array('name'=>$rName['Id'], 'class'=>'hhk-careoflink', 'title'=>$cof ? 'Remove Care-Of for ' . $rName["Name"] : 'Make ' . $rName["Name"] . ' a Care/Of'))
                    );
            }
        }

        $table->addBody($this->createNewEntry());

        return HTMLContainer::generateMarkup('div', $table->generateMarkup(), array('id'=>'acm'.$this->relCode->getCode(), 'name'=>$this->relCode->getCode(), 'class'=>'hhk-relations'));
    }



    public function addRelationship(\PDO $dbh, $rId, $user) {

        $rId = intval($rId);

        if ($rId > 0 && $rId <> $this->getIdName()) {
            // Update employee record
            $query = "update  name n, name c set n.Company_Id = :id, n.Company = c.Company
                where n.idName = :empId and n.Record_Member = 1 and c.idName = :id2 and c.Record_Company = 1 and n.Company_Id <> :id3;";
            $stmt = $dbh->prepare($query);
            $idVar = $this->getIdName();
            $stmt->execute(array(':id'=>$idVar, ':id3'=>$idVar, ':id2'=>$idVar, ':empId'=>$rId));

            $nRS = new NameRS();
            $nRS->idName->setStoredVal($rId);
            $nRS->Company_Id->setNewVal($this->getIdName());
            NameLog::writeUpdate($dbh, $nRS, $rId, $user);

            return "Employee Added.  ";
        }

    }

    public function removeRelationship(\PDO $dbh, $rId) {

        $uS = Session::getInstance();

        $rId = intval($rId);

        $query = "update name n set n.Company_Id = 0, n.Company = '', n.Company_CareOf = '' where n.idName = :rId;";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':rId'=>$rId));

        $nRS = new NameRS();
        $nRS->idName->setStoredVal($rId);
        $nRS->Company_Id->setNewVal(0);
        NameLog::writeUpdate($dbh, $nRS, $rId, $uS->username);

        return "Employee removed.";
    }

    public function setCareOf(\PDO $dbh, $rId, $flag, $user) {

       $rId = intval($rId);
       if ($rId == 0) {
           return "Bad Employee Id";
       }

       if ($flag === TRUE) {
            $query = "update name n set n.Company_CareOf = 'y' where n.idName = :rId and n.Company_Id > 0;";
       } else {
           $query = "update name n set n.Company_CareOf = '' where n.idName = :rId;";
       }
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':rId'=>$rId));

        $nRS = new NameRS();
        $nRS->idName->setStoredVal($rId);
        $nRS->Company_CareOf->setNewVal($flag ? 'y' : '');
        NameLog::writeUpdate($dbh, $nRS, $rId, $user);

        return "Care-Of is updated.  ";
    }

}
?>