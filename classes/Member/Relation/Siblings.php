<?php

namespace HHK\Member\Relation;

use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\SysConst\RelLinkType;

/**
 * Siblings.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Siblings extends AbstractRelation {


    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Sibling, 'Description'=>'Sibling'));

    }

    protected function getPdoStmt(\PDO $dbh) {

        $query = "Select v.Id, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, r . *
from relationship r
        join
    vmember_listing v ON r.idName = v.Id
        join
    relationship r1 ON r.Group_Code = r1.Group_Code and r1.idName = :id
where
    r1.Relation_Type = '". $this->relCode->getCode() ."' and r.Status = 'a' and r.idName <> :idw;";

        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);
        $stmt->bindParam(':idw', $idVar);

        return $stmt;
    }

    protected function getHtmlId() {
        return "Sibling";
    }

    protected function createNewEntry() {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Sibling', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
    }

    public function addRelationship(\PDO $dbh, $rId, $user) {

        // get my group code if any...
        $my_gc = 0;
        $ur_gc = 0;
        $RelCode = $this->relCode->getCode();
        $id = $this->getIdName();

        $query = "Select idName, Group_Code from relationship
            where Status = 'a' and Relation_Type = '$RelCode' and (idName = :id or idName = :relId);";
        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR=> \PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':id'=>$id, ':relId'=>$rId));
        $rws = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rws as $r) {
            if ($r['idName'] == $id) {
                $my_gc = $r['Group_Code'];
            } else if ($r['idName'] == $rId) {
                $ur_gc = $r['Group_Code'];
            }
        }


        $q = "";
        // Compare group codes, Check each case because they all require different processing
        if ($my_gc != 0 && $ur_gc != 0 && $my_gc != $ur_gc) {
            // we each have our own group code.  covert ur's to mine
            $q = "update relationship set Group_Code = $my_gc where Group_Code = $ur_gc and Relation_Type = '$RelCode'";
            $dbh->exec($q);
        } else if ($my_gc != 0 && $ur_gc == 0) {
            // add ur to my group code
            $q = "Insert into relationship (idName, Group_Code, Relation_Type, Status, Date_Added, Updated_By)
                values ($rId, $my_gc, '$RelCode', 'a', now(), '" . $user . "');";
            $dbh->exec($q);
        } else if ($my_gc == 0 && $ur_gc != 0) {
            // add me to ur group code
            $q = "Insert into relationship (idName, Group_Code, Relation_Type, Status, Date_Added, Updated_By)
                values ($id, $ur_gc, '$RelCode', 'a', now(), '" . $user . "');";
            $dbh->exec($q);
        } else if ($my_gc == 0 && $ur_gc == 0) {
            // Get a new group code.
            $dbh->query("CALL IncrementCounter('relationship', @num);");
            foreach ($dbh->query("SELECT @num") as $row) {
                $relCtr = $row[0];
            }
            if ($relCtr == 0) {
                return array("error" => "Event Relationship counter not set up.");
            }

            // Insert 2 new records.
            $q = "Insert into relationship (idName, Group_Code, Relation_Type, Status, Date_Added, Updated_By)
                values ($id, $relCtr, '$RelCode', 'a', now(), '" . $user . "');";
            $dbh->exec($q);
            $q = "Insert into relationship (idName, Group_Code, Relation_Type, Status, Date_Added, Updated_By)
                values ($rId, $relCtr, '$RelCode', 'a', now(), '" . $user . "');";
            $dbh->exec($q);
        }

        //
        if ($q != "") {
            $message = "Sibling/Relative Added. ";
        } else {
            $message = "Error: Sibling/Relative was already assigned. ";
        }
        return $message;
    }

    public function removeRelationship(\PDO $dbh, $rId) {
        $qq = "Delete from relationship where Relation_Type='". $this->relCode->getCode() ."' and idName=:rId ";
        $stmt = $dbh->prepare($qq);
        $stmt->execute(array(':rId'=>$rId));
        return "Sibling/Relative Deleted.  ";

    }

}
?>