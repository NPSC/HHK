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
 * extends AbstractRelation
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017, 2018-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Employees extends AbstractRelation {

    /**
     * Summary of loadRelCode
     * @return RelationCode
     */
    protected function loadRelCode() {
        return new RelationCode(array('Code'=>RelLinkType::Employee, 'Description'=>'Employee'));
    }

    /**
     * Summary of getPdoStmt
     * @param \PDO $dbh
     * @return \PDOStatement|bool
     */
    protected function getPdoStmt(\PDO $dbh): \PDOStatement|bool {
        $query = "Select v.Id as `Id`, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, v.Company_CareOf
from vmember_listing v join vmember_listing c on v.Company_Id = c.Id
where c.Id = :id;";

        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);

        return $stmt;
    }

    /**
     * Summary of loadRecords
     * @param \PDO $dbh
     * @return array
     */
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


    /**
     * Summary of getHtmlId
     * @return string
     */
    protected function getHtmlId() {
        return "Employee";
    }

    /**
     * Summary of createNewEntry
     * @return string
     */
    protected function createNewEntry() {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Employee', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'3', 'style'=>'text-align: center;')));
    }

    /**
     * Summary of createMarkup
     * @param string $page
     * @return string
     */
    public function createMarkup($page = 'NameEdit.php') {

        $table = new HTMLTable();

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



    /**
     * Summary of addRelationship
     * @param \PDO $dbh
     * @param int $rId
     * @param string $user
     * @return string
     */
    public function addRelationship(\PDO $dbh, $rId, $user) {

        $rId = intval($rId);

        if ($rId > 0 && $rId <> $this->getIdName()) {
            // Update employee record
            self::linkCompany($dbh, $rId, $this->getIdName());

            $nRS = new NameRS();
            $nRS->idName->setStoredVal($rId);
            $nRS->Company_Id->setNewVal($this->getIdName());
            NameLog::writeUpdate($dbh, $nRS, $rId, $user);

            return "Employee Added.  ";
        }
        return '';

    }

    /**
     * Summary of removeRelationship
     * @param \PDO $dbh
     * @param int $rId
     * @return string
     */
    public function removeRelationship(\PDO $dbh, $rId) {

        $uS = Session::getInstance();

        $rId = intval($rId);

        self::unlinkCompany($dbh, $rId);

        $nRS = new NameRS();
        $nRS->idName->setStoredVal($rId);
        $nRS->Company_Id->setNewVal(0);
        NameLog::writeUpdate($dbh, $nRS, $rId, $uS->username);

        return "Employee removed.";
    }

    /**
     * Summary of setCareOf
     * @param \PDO $dbh
     * @param mixed $rId
     * @param mixed $flag
     * @param mixed $user
     * @return string
     */
    public function setCareOf(\PDO $dbh, $rId, $flag, $user) {

       $rId = intval($rId);
       if ($rId == 0) {
           return "Bad Employee Id";
       }

        $empRs = new NameRS();
        $empRs->idName->setStoredVal($rId);
        $rows = EditRS::select($dbh, $empRs, array($empRs->idName));

        // care-of only applies to an employee with a company
        if (count($rows) == 1) {
            EditRS::loadRow($rows[0], $empRs);

            if ($flag !== TRUE || $empRs->Company_Id->getStoredVal() > 0) {
                $empRs->Company_CareOf->setNewVal($flag === TRUE ? 'y' : '');
                EditRS::update($dbh, $empRs, array($empRs->idName));
            }
        }

        $nRS = new NameRS();
        $nRS->idName->setStoredVal($rId);
        $nRS->Company_CareOf->setNewVal($flag ? 'y' : '');
        NameLog::writeUpdate($dbh, $nRS, $rId, $user);

        return "Care-Of is updated.  ";
    }

}
?>