<?php

/**
 * Relation.php
 *
 * @package Hospitality HouseKeeper
 * @category  Site
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class RelationCode {

    private $code;
    private $title;

    public function __construct(array $codeArray) {
        $this->code = $codeArray["Code"];
        $this->title = $codeArray["Description"];
    }

    public function getCode() {
        return $this->code;
    }

    public function getTitle() {
        return $this->title;
    }

}


abstract class Relation {

    /** @var array Holds an iTable for each relation of this type */
    protected $relNames = array();

    /** @var int */
    protected $id;

    /** @var HTML control id attribute prefix - optional */
    protected $idPrefex;

    /** @var RelationCode  */
    protected $relCode;

    /**
     *
     * @param PDO $dbh
     * @param Member $name
     * @param string $idPrefix
     */
    public function __construct(PDO $dbh, $idName) {

        $this->id = $idName;
        $this->relCode = $this->loadRelCode();

        $this->relNames = $this->loadRecords($dbh);
    }

    public static function instantiateRelation(PDO $dbh, $relCode, $idName) {

        switch ($relCode) {
            case RelLinkType::Child:
                return new Children($dbh, $idName);
                break;

            case RelLinkType::Parnt:
                return new Parents($dbh, $idName);
                break;

            case RelLinkType::Sibling:
                return new Siblings($dbh, $idName);
                break;

            case RelLinkType::Relative:
                return new Relatives($dbh, $idName);
                break;

            case RelLinkType::Spouse:
                return new Partner($dbh, $idName);
                break;

            // Individuals link to one organization.
            case RelLinkType::Company:
                return new Company($dbh, $idName);
                break;

            // Organizations link to employees.
            case RelLinkType::Employee:
                return new Employees($dbh, $idName);
                break;

            case 'stu':
                // Student
                return new StudentSupporter($dbh, $idName);


            case 'm':
                // Student
                return new Student($dbh, $idName);


            default:
                break;
        }

        return null;
    }

    public function getRelNames() {
        return $this->relNames;
    }

    protected abstract function loadRelCode();

    /**
     *
     * @param PDO $dbh
     * @return \relationsRS
     */
    protected function loadRecords(PDO $dbh) {
        $rels = array();

        //$stmt = $dbh->prepare($this->getQuery(), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        //$stmt->execute(array(":id" => $this->getIdName(), ":idw" => $this->getIdName()));
        $stmt = $this->getPdoStmt($dbh);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rs = new relationsRS();
                EditRS::loadRow($r, $rs);
                $rels[] = array('Id' => $r["Id"], 'Name' => $r["Name"], 'Status'=>$r['MemStatus'], 'rs' => $rs);
            }
        }
        return $rels;
    }

    protected abstract function getPdoStmt(PDO $dbh);

    protected abstract function getHtmlId();

    public function savePost(PDO $dbh, array $post, $user) {

        if (isset($post["sel" . $this->relCode->getCode()])) {

            $item = $post["sel" . $this->relCode->getCode()];

            // Add any relations
            if (substr($item, 0, 2) == "n_") {

                // caught a new ID
                $rId = intval(filter_var(substr($item, 2), FILTER_SANITIZE_NUMBER_INT));

                if ($rId > 0 && $rId <> $this->name->get_idName()) {
                    // Ok, record new relationship
                    $message .= $this->addRelationship($dbh, $this->name->get_idName(), $rId, $user);
                }
            }


            // Delete any relations?
            if (isset($post["x" . $this->relCode->getCode()])) {
                $dRel = filter_var($post["x" . $this->relCode->getCode()], FILTER_VALIDATE_BOOLEAN);
                if ($dRel) {
                    // One of the delete checkboxes are checked
                    $rId = intval(filter_var($item, FILTER_SANITIZE_NUMBER_INT));

                    if ($rId > 0) {
                        // Delete a relationship
                        $this->removeRelationship($dbh, $this->name->get_idName(), $rId);
                    }
                }
            }
        }
    }

    public abstract function addRelationship(PDO $dbh, $rId, $user);

    public abstract function removeRelationship(PDO $dbh, $rId);

    public function getIdName() {
        return $this->id;
    }

    public function createMarkup($page = 'NameEdit.php') {

        $table = new HTMLTable();
        $trash = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash', 'title'=>'Delete Link', 'style'=>'float: left; margin-right:.3em;'));

        $table->addHeaderTr(HTMLTable::makeTh($this->relCode->getTitle(), array('colspan'=>'2')));

        if (count($this->relNames) > 0) {

            foreach ($this->relNames as $rName) {

                $deceasedClass = '';
                if ($rName['Status'] == MemStatus::Deceased) {
                    $deceasedClass = ' hhk-deceased';
                }

                $table->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $rName["Name"], array('href'=>$page.'?id='.$rName['Id'], 'class'=>$deceasedClass, 'title'=>'Click to Edit this Member')), array('class'=>'hhk-rel-td'))
                    .HTMLTable::makeTd($trash, array('name'=>$rName['Id'], 'class'=>'hhk-rel-td hhk-deletelink', 'title'=>'Delete ' . $this->relCode->getTitle() . ' Link to ' . $rName["Name"])));
        }
        }

        $table->addBody($this->createNewEntry());

        return HTMLContainer::generateMarkup('div', $table->generateMarkup(), array('id'=>'acm'.$this->relCode->getCode(), 'name'=>$this->relCode->getCode(), 'class'=>'hhk-relations'));
    }

    protected abstract function createNewEntry();

}



class Siblings extends Relation {


    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Sibling, 'Description'=>'Sibling'));

    }

    protected function getPdoStmt(PDO $dbh) {

        $query = "Select v.Id, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, r . *
from relationship r
        join
    vmember_listing v ON r.idName = v.Id
        join
    relationship r1 ON r.Group_Code = r1.Group_Code and r1.idName = :id
where
    r1.Relation_Type = '". $this->relCode->getCode() ."' and r.Status = 'a' and r.idName <> :idw;";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
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

    public function addRelationship(PDO $dbh, $rId, $user) {

        // get my group code if any...
        $my_gc = 0;
        $ur_gc = 0;
        $RelCode = $this->relCode->getCode();
        $id = $this->getIdName();

        $query = "Select idName, Group_Code from relationship
            where Status = 'a' and Relation_Type = '$RelCode' and (idName = :id or idName = :relId);";
        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR=> PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':id'=>$id, ':relId'=>$rId));
        $rws = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    public function removeRelationship(PDO $dbh, $rId) {
        $qq = "Delete from relationship where Relation_Type='". $this->relCode->getCode() ."' and idName=:rId ";
        $stmt = $dbh->prepare($qq);
        $stmt->execute(array(':rId'=>$rId));
        return "Sibling/Relative Deleted.  ";

    }


}

class Relatives extends Siblings {

    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Relative, 'Description'=>'Relative'));

    }

        protected function createNewEntry() {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Relative', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
    }

    protected function getHtmlId() {
        return "Relative";
    }

}



class Parents extends Relation {

    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Parnt, 'Description'=>'Parent'));

    }

    protected function getPdoStmt(PDO $dbh) {

        $query = "Select v.Id, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, r.*
from relationship r join vmember_listing v ON r.Target_Id = v.Id
where r.Relation_Type='". RelLinkType::Parnt ."' and r.Status='a' and r.idName = :id;";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
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

    public function addRelationship(PDO $dbh, $rId, $user) {

        $id = $this->getIdName();
        // Im the child.  I can only have two parents.
        $query = "select Target_Id, idName from relationship where Status = 'a' and Relation_Type = :rcode
            and ( (idName = :id and Target_Id = :relId) or (Target_Id = :id2 and idName = :relId2) );";
        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':id'=>$id, ':id2'=>$id, ':relId'=>$rId, ':relId2'=>$rId, ':rcode'=>$this->relCode->getCode()));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                $relRS = new relationsRS();

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

    public function removeRelationship(PDO $dbh, $rId) {
        $qq = "Delete from relationship where Relation_Type='". $this->relCode->getCode() ."' and (idName=:id or Target_Id=:id2 ) and (idName=:rId or Target_Id=:rId2 )";
        $stmt = $dbh->prepare($qq);
        $idw = $this->getIdName();
        $stmt->execute(array(':id'=>$idw, ':rId'=>$rId, ':id2'=>$idw, ':rId2'=>$rId));

        return "Parent Deleted.  ";

    }

}



class Children extends Relation {


    protected function loadRelCode() {
        return new RelationCode(array('Code'=>RelLinkType::Child, 'Description'=>'Child'));
    }

    protected function getPdoStmt(PDO $dbh) {

        $query = "Select v.Id, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, r.*
from relationship r join vmember_listing v ON r.idName = v.Id
where r.Relation_Type='". RelLinkType::Parnt ."' and r.Status='a' and r.Target_Id = :id;";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);

        return $stmt;
    }

    protected function getHtmlId() {
        return "Child";
    }

    protected function createNewEntry() {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Child', array('class'=>'hhk-newlink', 'title'=>'Click to link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
    }

    public function addRelationship(PDO $dbh, $rId, $user) {

        $id = $this->getIdName();

        // I'm the parent.  The child can only have 2 parents, sorry.
        $query = "select Target_Id, idName from relationship where Status = 'a' and Relation_Type = :rcode
            and idName = :rid or Target_Id = :rid2";
        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR=>PDO::CURSOR_FWDONLY));
        $stmt->execute(array(':rid'=>$rId, ':rid2'=>$rId, ':rcode'=>RelLinkType::Parnt));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $numParents = 0;

        foreach($rows as $rw) {

            // Am I already a child of this parent?
            if ($rw['Target_Id'] == $id) {
                $sameId = TRUE;
                return "BUT: I am already this child's parent!!! ";
            }

            // Am I a parent of this 'parent'?
            if ($rw['idName'] == $id) {
                $sameId = TRUE;
                return "BUT: this 'Child' is my parent!! ";
            }

            if ($rw['idName'] == $rId) {
                $numParents++;
            }
        }

        if ($numParents <= 1) {

            $relRS = new relationsRS();

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

    public function removeRelationship(PDO $dbh, $rId) {
        $qq = "Delete from relationship where Relation_Type=:rcode and (idName=:id or Target_Id=:id2 ) and (idName=:rId or Target_Id=:rId2 )";
        $stmt = $dbh->prepare($qq);
        $idw = $this->getIdName();
        $stmt->execute(array(':rcode'=>RelLinkType::Parnt, ':id'=>$idw, ':rId'=>$rId, ':id2'=>$idw, ':rId2'=>$rId));

        return "Child link removed.  ";

    }

}



class Partner extends Relation {

    protected $statusTitle;
    protected $status;

//    protected function loadRecords(PDO $dbh) {
//
//        $rels = array();
//
//        $stmt = $dbh->prepare($this->getQuery(), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
//        $stmt->execute(array(":id" => $this->getIdName(), ":tpe" => $this->relCode->getCode()));
//
//        if ($stmt->rowCount() > 0) {
//
//            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
//                $rs = new relationsRS();
//                EditRS::loadRow($r, $rs);
//                $rels[] = array('Id' => $r["Id"], 'Name' => $r["Name"], 'rs' => $rs);
//                $this->statusTitle = $r['Status_Text'];
//                $this->status = $r['sp_Status'];
//            }
//        }
//        return $rels;
//    }

    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Spouse, 'Description'=>'Partner'));

    }

    protected function getPdoStmt(PDO $dbh) {

        $query = "Select
    v.Id,
    concat(v.Name_First, ' ', v.Name_Last) as `Name`,
    v.MemberStatus as `MemStatus`,
    g.Description as `Status_Text`,
    r . * from relationship r join vmember_listing v ON (r.Target_Id = v.Id or r.idName = v.Id) and v.Id <> :id1
    left join gen_lookups g ON g.Table_Name = 'mem_status' and g.Code = v.MemberStatus
    where r.Relation_Type = '".RelLinkType::Spouse."' and r.Status = 'a' and (r.idName = :id or r.Target_Id = :idw);";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
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


    public function addRelationship(PDO $dbh, $myspId, $user) {
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

    public function removeRelationship(PDO $dbh, $rId) {

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


class Company extends Relation {

    protected function loadRelCode() {

        return new RelationCode(array('Code'=>RelLinkType::Company, 'Description'=>'Company'));

    }

    protected function getPdoStmt(PDO $dbh) {

        $query = "Select v.Company_Id as `Id`, v.Company as `Name`, v.MemberStatus as `MemStatus` from vmember_listing v where v.Id = :id and v.Company_Id > 0;";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
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


    public function addRelationship(PDO $dbh, $rId, $user) {

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

    public function removeRelationship(PDO $dbh, $rId) {

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


class Employees extends Relation {

    protected function loadRelCode() {
        return new RelationCode(array('Code'=>RelLinkType::Employee, 'Description'=>'Employee'));
    }

    protected function getPdoStmt(PDO $dbh) {
        $query = "Select v.Id as `Id`, concat(v.Name_First, ' ', v.Name_Last) as `Name`, v.MemberStatus as `MemStatus`, v.Company_CareOf
from vmember_listing v join vmember_listing c on v.Company_Id = c.Id
where c.Id = :id;";

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $idVar = $this->getIdName();
        $stmt->bindParam(':id', $idVar);

        return $stmt;
    }

    protected function loadRecords(PDO $dbh) {
        $rels = array();

//        $stmt = $dbh->prepare($this->getQuery(), array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
//        $idVar = $this->getIdName();
//        $stmt->execute(array(":id" => $idVar));
        $stmt = $this->getPdoStmt($dbh);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rs = new relationsRS();
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
        $trash = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash', 'style'=>'float: left; margin-right:.3em;'));
        $careof = HTMLContainer::generateMarkup('span', '', array('name'=>'delcareof', 'class'=>'ui-icon ui-icon-mail-closed', 'style'=>'float: left; margin-right:.3em;'));
        $addCareof = HTMLContainer::generateMarkup('span', '', array('name'=>'addcareof', 'class'=>'ui-icon ui-icon-plus', 'style'=>'float: left; margin-right:.3em;'));

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
                    HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $rName["Name"], array('href'=>$page.'?id='.$rName['Id'], 'class'=>$deceasedClass, 'title'=>'Click to Edit this Member')))
                    .HTMLTable::makeTd($trash, array('name'=>$rName['Id'], 'class'=>'hhk-deletelink', 'title'=>'Delete ' . $this->relCode->getTitle() . ' Link to ' . $rName["Name"]))
                    .HTMLTable::makeTd($cof ? $careof : $addCareof, array('name'=>$rName['Id'], 'class'=>'hhk-careoflink', 'title'=>$cof ? 'Remove Care-Of for ' . $rName["Name"] : 'Make ' . $rName["Name"] . ' a Care/Of'))
                    );
            }
        }

        $table->addBody($this->createNewEntry());

        return HTMLContainer::generateMarkup('div', $table->generateMarkup(), array('id'=>'acm'.$this->relCode->getCode(), 'name'=>$this->relCode->getCode(), 'class'=>'hhk-relations'));
    }



    public function addRelationship(PDO $dbh, $rId, $user) {

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

    public function removeRelationship(PDO $dbh, $rId) {

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

    public function setCareOf(PDO $dbh, $rId, $flag, $user) {

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
