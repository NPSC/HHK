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


    /**
     * Summary of loadRelCode
     * @return RelationCode
     */
    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Sibling, 'Description'=>'Sibling'));

    }

    /**
     * Summary of getPdoStmt
     * @param \PDO $dbh
     * @return \PDOStatement|bool
     */
    protected function getPdoStmt(\PDO $dbh): \PDOStatement|bool {

        $query = "SELECT `v`.`Id`, CONCAT(`v`.`Name_First`, ' ', `v`.`Name_Last`) AS `Name`, `v`.`MemberStatus` AS `MemStatus`, `r`.*
FROM `relationship` `r`
        JOIN
    `vmember_listing` `v` ON `r`.`idName` = `v`.`Id`
        JOIN
    `relationship` `r1` ON `r`.`Group_Code` = `r1`.`Group_Code` AND `r1`.`idName` = :id
WHERE
    `r1`.`Relation_Type` = :relType AND `r`.`Status` = 'a' AND `r`.`idName` <> :idw;";

        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $relTypeVar = $this->relCode->getCode();
        $stmt->bindParam(':id', $idVar);
        $stmt->bindParam(':idw', $idVar);
        $stmt->bindParam(':relType', $relTypeVar);

        return $stmt;
    }

    /**
     * Summary of getHtmlId
     * @return string
     */
    protected function getHtmlId() {
        return "Sibling";
    }

    /**
     * Summary of createNewEntry
     * @return string
     */
    protected function createNewEntry() {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Sibling', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
    }

    /**
     * Summary of addRelationship
     * @param \PDO $dbh
     * @param int $rId
     * @param string $user
     * @return string
     */
    public function addRelationship(\PDO $dbh, $rId, $user) {

        // get my group code if any...
        $my_gc = 0;
        $ur_gc = 0;
        $RelCode = $this->relCode->getCode();
        $id = $this->getIdName();

        $query = "SELECT `idName`, `Group_Code` FROM `relationship`
            WHERE `Status` = 'a' AND `Relation_Type` = :relType AND (`idName` = :id OR `idName` = :relId);";
        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR=> \PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':relType' => $RelCode, ':id'=>$id, ':relId'=>$rId));
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
            $q = "UPDATE `relationship` SET `Group_Code` = :myGc WHERE `Group_Code` = :urGc AND `Relation_Type` = :relType";
            $updStmt = $dbh->prepare($q);
            $updStmt->execute([':myGc' => $my_gc, ':urGc' => $ur_gc, ':relType' => $RelCode]);
        } else if ($my_gc != 0 && $ur_gc == 0) {
            // add ur to my group code
            $q = "INSERT INTO `relationship` (`idName`, `Group_Code`, `Relation_Type`, `Status`, `Date_Added`, `Updated_By`)
                VALUES (:rId, :myGc, :relType, 'a', NOW(), :user);";
            $insStmt = $dbh->prepare($q);
            $insStmt->execute([':rId' => $rId, ':myGc' => $my_gc, ':relType' => $RelCode, ':user' => $user]);
        } else if ($my_gc == 0 && $ur_gc != 0) {
            // add me to ur group code
            $q = "INSERT INTO `relationship` (`idName`, `Group_Code`, `Relation_Type`, `Status`, `Date_Added`, `Updated_By`)
                VALUES (:id, :urGc, :relType, 'a', NOW(), :user);";
            $insStmt = $dbh->prepare($q);
            $insStmt->execute([':id' => $id, ':urGc' => $ur_gc, ':relType' => $RelCode, ':user' => $user]);
        } else if ($my_gc == 0 && $ur_gc == 0) {

            // Get a new group code.
            $relCtr = 0;
            $dbh->prepare("CALL IncrementCounter('relationship', @num);")->execute();
            $numStmt = $dbh->prepare("SELECT @num");
            $numStmt->execute();
            foreach ($numStmt as $row) {
                $relCtr = $row[0];
            }
            if ($relCtr == 0) {
                return "error : Event Relationship counter not set up.";
            }

            // Insert 2 new records.
            $q = "INSERT INTO `relationship` (`idName`, `Group_Code`, `Relation_Type`, `Status`, `Date_Added`, `Updated_By`)
                VALUES (:id, :relCtr, :relType, 'a', NOW(), :user);";
            $insStmt1 = $dbh->prepare($q);
            $insStmt1->execute([':id' => $id, ':relCtr' => $relCtr, ':relType' => $RelCode, ':user' => $user]);
            $q = "INSERT INTO `relationship` (`idName`, `Group_Code`, `Relation_Type`, `Status`, `Date_Added`, `Updated_By`)
                VALUES (:rId, :relCtr, :relType, 'a', NOW(), :user);";
            $insStmt2 = $dbh->prepare($q);
            $insStmt2->execute([':rId' => $rId, ':relCtr' => $relCtr, ':relType' => $RelCode, ':user' => $user]);
        }

        //
        if ($q != "") {
            $message = "Sibling/Relative Added. ";
        } else {
            $message = "Error: Sibling/Relative was already assigned. ";
        }
        return $message;
    }

    /**
     * Summary of removeRelationship
     * @param \PDO $dbh
     * @param int $rId
     * @return string
     */
    public function removeRelationship(\PDO $dbh, $rId) {
        $qq = "DELETE FROM `relationship` WHERE `Relation_Type` = :relType AND `idName` = :rId ";
        $stmt = $dbh->prepare($qq);
        $stmt->execute(array(':relType' => $this->relCode->getCode(), ':rId'=>$rId));
        return "Sibling/Relative Deleted.  ";

    }

}
?>