<?php

namespace HHK\Member\Relation;

use HHK\SysConst\{RelLinkType};
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\Tables\EditRS;
use HHK\Tables\Name\RelationsRS;

/**
 * Children.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017, 2018-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Children extends AbstractRelation {


    /**
     * Summary of loadRelCode
     * @return RelationCode
     */
    protected function loadRelCode() {
        return new RelationCode(array('Code'=>RelLinkType::Child, 'Description'=>'Child'));
    }

    /**
     * Summary of getPdoStmt
     * @param \PDO $dbh
     * @return \PDOStatement|bool
     */
    protected function getPdoStmt(\PDO $dbh) {

        $query = "Select v.Id, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, r.*
from relationship r join vmember_listing v ON r.idName = v.Id
where r.Relation_Type='". RelLinkType::Parnt ."' and r.Status='a' and r.Target_Id = :id;";

        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);

        return $stmt;
    }

    /**
     * Summary of getHtmlId
     * @return string
     */
    protected function getHtmlId() {
        return "Child";
    }

    /**
     * Summary of createNewEntry
     * @return string
     */
    protected function createNewEntry():string {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Child', array('class'=>'hhk-newlink', 'title'=>'Click to link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
    }

    /**
     * Summary of addRelationship
     * @param \PDO $dbh
     * @param int $rId
     * @param string $user
     * @return string
     */
    public function addRelationship(\PDO $dbh, $rId, $user):string {

        $id = $this->getIdName();

        // I'm the parent.  The child can only have 2 parents, sorry.
        $query = "select Target_Id, idName from relationship where Status = 'a' and Relation_Type = :rcode
            and idName = :rid or Target_Id = :rid2";
        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR=>\PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':rid'=>$rId, ':rid2'=>$rId, ':rcode'=>RelLinkType::Parnt));

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $numParents = 0;

        foreach($rows as $rw) {

            // Am I already a child of this parent?
            if ($rw['Target_Id'] == $id) {
                return "BUT: I am already this child's parent!!! ";
            }

            // Am I a parent of this 'parent'?
            if ($rw['idName'] == $id) {
                return "BUT: this 'Child' is my parent!! ";
            }

            if ($rw['idName'] == $rId) {
                $numParents++;
            }
        }

        if ($numParents <= 1) {

            $relRS = new RelationsRS();

            $relRS->Date_Added->setNewVal(date("Y-m-d H:i:s"));
            $relRS->idName->setNewVal($rId);
            $relRS->Target_Id->setNewVal($id);
            $relRS->Relation_Type->setNewVal(RelLinkType::Parnt);
            $relRS->Status->setNewVal('a');
            $relRS->Updated_By->setNewVal($user);

            EditRS::insert($dbh, $relRS);

            $message = "Child link added. ";

        } else {
            $message = "BUT: The Child already has 2 parents!!!!  ";
        }

        return $message;

    }

    /**
     * Summary of removeRelationship
     * @param \PDO $dbh
     * @param int $rId
     * @return string
     */
    public function removeRelationship(\PDO $dbh, $rId):string {
        $qq = "Delete from relationship where Relation_Type=:rcode and (idName=:id or Target_Id=:id2 ) and (idName=:rId or Target_Id=:rId2 )";
        $stmt = $dbh->prepare($qq);
        $idw = $this->getIdName();

        $stmt->execute(array(':rcode'=>RelLinkType::Parnt, ':id'=>$idw, ':rId'=>$rId, ':id2'=>$idw, ':rId2'=>$rId));

        return "Child link removed.  ";

    }

}
?>