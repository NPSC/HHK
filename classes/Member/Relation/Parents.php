<?php

namespace HHK\Member\Relation;

use HHK\SysConst\{RelLinkType};
use HHK\Tables\EditRS;
use HHK\Tables\Name\RelationsRS;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};

/**
 * Parents.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Parents extends AbstractRelation {
    
    protected function loadRelCode() {
        
        return new RelationCode(array('Code'=>RelLinkType::Parnt, 'Description'=>'Parent'));
        
    }
    
    protected function getPdoStmt(\PDO $dbh) {
        
        $query = "Select v.Id, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, r.*
from relationship r join vmember_listing v ON r.Target_Id = v.Id
where r.Relation_Type='". RelLinkType::Parnt ."' and r.Status='a' and r.idName = :id;";
        
        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);
        
        return $stmt;
    }
    
    protected function getHtmlId() {
        return "Parent";
    }
    
    protected function createNewEntry() {
        if (count($this->relNames)  < 2) {
            return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Parent', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
        } else
            return "";
    }
    
    public function getSelectLinkOption() {
        if (count($this->relNames) <= 2) {
            return HTMLContainer::generateMarkup('option', $this->relCode->getTitle(), array('value' => $this->relCode->getCode()));
        } else {
            return "";
        }
    }
    
    public function addRelationship(\PDO $dbh, $rId, $user) {
        
        $id = $this->getIdName();
        // Im the child.  I can only have two parents.
        $query = "select Target_Id, idName from relationship where Status = 'a' and Relation_Type = :rcode
            and ( (idName = :id and Target_Id = :relId) or (Target_Id = :id2 and idName = :relId2) );";
        $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR=>\PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':id'=>$id, ':id2'=>$id, ':relId'=>$rId, ':relId2'=>$rId, ':rcode'=>$this->relCode->getCode()));
        
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (count($rows) <= 1) {
            
            $sameId = FALSE;
            
            foreach($rows as $rw) {
                
                // Am I already a child of this parent?
                if ($rw['Target_Id'] == $rId) {
                    $sameId = TRUE;
                    return "BUT: I am already this parent's child!!! ";
                    
                }
                
                // Am I a parent of this 'parent'?
                if ($rw['idName'] == $rId) {
                    $sameId = TRUE;
                    return "BUT: this 'parent' is my child!!! ";
                    
                }
            }
            
            // If I am not already a parent, add me.
            if ($sameId === FALSE) {
                $relRS = new RelationsRS();
                
                $relRS->Date_Added->setNewVal(date("Y-m-d H:i:s"));
                $relRS->idName->setNewVal($id);
                $relRS->Target_Id->setNewVal($rId);
                $relRS->Relation_Type->setNewVal($this->relCode->getCode());
                $relRS->Status->setNewVal('a');
                $relRS->Updated_By->setNewVal($user);
                
                EditRS::insert($dbh, $relRS);
                $relRS = NULL;
                $message = "Parent Added. ";
            }
        } else {
            $message = "BUT: This child already has 2 parents!!! ";
        }
        return $message;
    }
    
    public function removeRelationship(\PDO $dbh, $rId) {
        $qq = "Delete from relationship where Relation_Type='". $this->relCode->getCode() ."' and (idName=:id or Target_Id=:id2 ) and (idName=:rId or Target_Id=:rId2 )";
        $stmt = $dbh->prepare($qq);
        $idw = $this->getIdName();
        $stmt->execute(array(':id'=>$idw, ':rId'=>$rId, ':id2'=>$idw, ':rId2'=>$rId));
        
        return "Parent Deleted.  ";
        
    }
    
}
?>