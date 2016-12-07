<?php

/**
 * Constraint.php
 *
 *
 * @category  House
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of Constraint
 *
 * @author Eric
 */
class Constraints {

    /**
     *
     * @var array of type Constraint
     */
    protected $constraints;
    /**
     *
     * @var array Gen_Lookups array
     */
    protected $constraintTypes;


    /**
     *
     * @param \PDO $dbh
     */
    public function __construct(\PDO $dbh) {

        $this->loadConstraints($dbh);

        $this->constraintTypes = readGenLookupsPDO($dbh, 'Constraint_Type');

    }

    protected function loadConstraints(\PDO $dbh) {

        $this->constraints = array();

        $stmt = $dbh->query("Select * from `constraints` where `Status` = 'a' order by `Type`, `Title`");

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $constRs = new ConstraintRS();
            EditRS::loadRow($r, $constRs);
            $this->constraints[$r['idConstraint']] =  new Constraint($constRs);
        }

    }

    /**
     *
     * @return \array
     */
    public function getConstraints() {
        return $this->constraints;
    }

    /**
     *
     * @param string $type
     * @return array An array of constraint rows
     */
    public function getConstraintsByType($type) {

        $atypes = array();
        foreach ($this->constraints as $a) {
            if ($a->getType() == $type) {
                $atypes[$a->getId()] = $a;
            }
        }
        return $atypes;
    }

    /**
     *
     * @return array
     */
    public function getConstraintTypes() {
        return $this->constraintTypes;
    }

    /**
     *
     * @param \PDO $dbh
     * @return string HTML markup table displaying all constraints
     */
    public function createConstraintTable(\PDO $dbh) {

        $attribute = new Attributes($dbh);

        $rmAtrStmt = $dbh->query("Select idConstraint, idAttribute, `Type`, Operation from constraint_attribute");
        $roomAttrs = $rmAtrStmt->fetchAll(PDO::FETCH_ASSOC);

        $consMu = array();
        foreach ($this->constraints as $c) {

            $markup = array();

            $ra = array();
            foreach ($roomAttrs as $ras) {
                if ($ras['idConstraint'] == $c->getId()) {
                    $ra[$ras['idAttribute']] = $ras['Operation'];
                }
            }

            // put the edit at the beginning of the array.

            $markup['Edit'] = HTMLInput::generateMarkup('', array('id'=>$c->getId().'rsbtn', 'name'=>$c->getId(), 'type'=>'button', 'data-enty'=>'rs', 'class'=>'reEditBtn ui-icon ui-icon-pencil', 'style'=>'width:20px;height:20px;margin-right:.5em;display:inline;', 'title'=>'Edit This Constraint'))
                . HTMLInput::generateMarkup('', array('id'=>$c->getId().'rsDelete', 'name'=>$c->getId(), 'type'=>'button', 'data-enty'=>'rs', 'class'=>'reDelBtn ui-icon ui-icon-trash', 'style'=>'width:20px;height:20px;display:inline;', 'title'=>'Delete Constraint'));

            $markup['Id'] = $c->getId();
            $markup['Title'] = $c->getTitle();
            $markup['Type'] = $this->constraintTypes[$c->getType()][1];
            $markup['Category'] = $c->getCategory();


            foreach ($attribute->getAttributes() as $a) {
                $markup[$a['Title']] = (isset($ra[$a['idAttribute']]) ? HTMLContainer::generateMarkup('p', $ra[$a['idAttribute']] . ' ' . HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check')) . HTMLContainer::generateMarkup('span', 'x', array('class'=>'hhk-printmedia'))) : '');
            }

            $consMu[] = $markup;
        }

        // New Room
        $consMu[] = array('Edit' => HTMLInput::generateMarkup('New', array('id'=>'0btnrsNew', 'name'=>'0', 'type'=>'button', 'data-enty'=>'rs', 'class'=>'reNewBtn')));


        return HTMLContainer::generateMarkup('h3', 'Showing '.count($this->constraints) . ' Constraints') . CreateMarkupFromDB::generateHTML_Table($consMu, 'tblconst');

    }

    /**
     *
     * @param \PDO $dbh
     * @param int $id
     * @return string HTML markup of a single table row containing input controls
     */
    public function editMarkup(\PDO $dbh, $id) {

        $cls = 'reCon' . $id;

        if ($id > 0) {
            $constraint = $this->constraints[$id];
        } else {
            $id = 0;
            $constraint = new Constraint(new ConstraintRS());
        }

        $saveBtn = HTMLInput::generateMarkup('Save', array('id'=>'savebtn', 'data-id'=>$id, 'data-type'=>'rs', 'data-cls'=>$cls, 'type'=>'button'));

        $tr = HTMLTable::makeTd($saveBtn) . HTMLTable::makeTd($id)
                . HTMLTable::makeTd(HTMLInput::generateMarkup($constraint->getTitle(), array('id'=>'txtRsTitle', 'size'=>'15', 'class'=>$cls)), array('style'=>'padding-right:0;padding-left:0;'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($this->constraintTypes, $constraint->getType(), TRUE), array('id'=>'selRsType', 'class'=>$cls)))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($constraint->getCategory(), array('id'=>'txtRsCat', 'class'=>$cls, 'size'=>'10')), array('style'=>'padding-right:0;padding-left:0;'));


        $const = new ConstraintAttributes($dbh, $constraint);

        foreach ($const->getAttributes() as $a) {

            $parms = array('id'=>'cbat_'.$a['idAttribute'], 'type'=>'checkbox', 'data-idat'=>$a['idAttribute'], 'class'=>$cls);
            $notParms = array('id'=>'cbnat_'.$a['idAttribute'], 'type'=>'checkbox', 'data-idat'=>$a['idAttribute'], 'class'=>$cls);

            if ($a['isActive'] > 0) {
                $parms['checked'] = 'checked';
            }

            if ($a['isActive'] > 0 && $a['Operation'] == 'not') {
                $notParms['checked'] = 'checked';
            }

            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', $parms). HTMLContainer::generateMarkup('span', 'Not:', array('style'=>'margin-left:.9em;')) . HTMLInput::generateMarkup('', $notParms), array('style'=>'text-align:center;padding-right:0;padding-left:0;'));
        }

        return array('row'=>$tr);

    }

    /**
     *
     * @param \PDO $dbh
     * @param int $id
     * @param array $post
     * @param string $username
     * @return string HTML markup of the new table of constraints
     */
    public function saveMarkup(\PDO $dbh, $id, $post, $username) {

        $constraintArray = $this->getConstraints();

        $conRs = new ConstraintRS();

        if (isset($constraintArray[$id])) {

            $c = $constraintArray[$id];
            $conRs = $c->getConstraintRs();

        } else if ($id != 0) {

            return array('error'=>'Cannot find constraint id= '.$id);
        }

        $cat = '';
        if (isset($post['txtRsCat'])) {
            $cat = filter_var($post['txtRsCat'], FILTER_SANITIZE_STRING);
        }

        $title = '';
        if (isset($post['txtRsTitle'])) {
            $title = filter_var($post['txtRsTitle'], FILTER_SANITIZE_STRING);
        }

        $type = '';
        if (isset($post['selRsType'])) {
            $type = filter_var($post['selRsType'], FILTER_SANITIZE_STRING);
        }

        if ($type == '' || $title == '') {
            return array('error'=>'Title and Type must both be set.  ');
        }

        $conRs->Category->setNewVal($cat);
        $conRs->Title->setNewVal($title);
        $conRs->Type->setNewVal($type);
        $conRs->Status->setNewVal('a');
        $conRs->Updated_By->setNewVal($username);
        $conRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        if ($conRs->idConstraint->getStoredVal() == 0) {
            // Insert
            $id = EditRS::insert($dbh, $conRs);
        } else {
            //Update
            EditRS::update($dbh, $conRs, array($conRs->idConstraint));
        }

        // Reload the object
        $this->loadConstraints($dbh);

        // Run through the posts to find any attributes
        $capturedAttributes = array();
        foreach ($post as $k => $p) {

            if (stristr($k, 'cbat_')) {

                $parts = explode('_', $k);
                $atId = intval($parts[1], 10);

                $atOperation = '';
                if (isset($post['cbnat_' . $atId])) {
                    $atOperation = 'not';
                }

                $capturedAttributes[$atId] = $atOperation;

            }
        }

        $cArray = $this->getConstraints();
        $roomAttr = new ConstraintAttributes($dbh, $cArray[$id]);
        $roomAttr->saveAttributes($dbh, $capturedAttributes);

        return array('constList'=>$this->createConstraintTable($dbh));
    }

    /**
     *
     * @param \PDO $dbh
     * @param int $id
     * @return array Result message
     */
    public function delete(\PDO $dbh, $id) {

        $constraintArray = $this->getConstraints();

        if (isset($constraintArray[$id])) {

            $c = $constraintArray[$id];
            $conRs = $c->getConstraintRs();

            EditRS::delete($dbh, $conRs, array($conRs->idConstraint));

            // delete from constraint_attribute
            $dbh->query("Delete from constraint_attribute where idConstraint = $id");

        }

        return array('success'=>'Record Deleted.  ');
    }
}


class Constraint {

    protected $id;
    protected $type;
    protected $title;
    protected $category;
    protected $status;
    private $constraintRs;
    protected $attributes;

    public function __construct(\ConstraintRS $constRs) {
        $this->loadObj($constRs);
    }

    protected function loadObj(\ConstraintRS $constRs) {

        $this->constraintRs = $constRs;

        $this->category = $constRs->Category->getStoredVal();
        $this->id = $constRs->idConstraint->getStoredVal();
        $this->status = $constRs->Status->getStoredVal();
        $this->title = $constRs->Title->getStoredVal();
        $this->type = $constRs->Type->getStoredVal();

    }

    public function getId() {
        return $this->id;
    }

    public function getType() {
        return $this->type;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getCategory() {
        return $this->category;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getConstraintRs() {
        return $this->constraintRs;
    }


}


class ConstraintAttributes {

    /**
     *
     * @var array
     */
    protected $attributes;
    /**
     *
     * @var int
     */
    protected $constraint;

    /**
     *
     * @param \PDO $dbh
     * @param \Constraint $constraint
     */
    public function __construct(\PDO $dbh, \Constraint $constraint) {

        $this->constraint = $constraint;
        $this->attributes = $this->loadAttributes($dbh);

    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function getActiveAttributes() {

        $roomAttrs = array();
        foreach ($this->attributes as $k => $a) {
            if ($a['isActive'] > 0) {
                $roomAttrs[$k] = $a;
            }
        }

        return $roomAttrs;
    }

    protected function loadAttributes(\PDO $dbh) {

        $attrs = array();

        if ($this->constraint->getId() > 0) {

            $rmAtrStmt = $dbh->prepare("select
    a.idAttribute,
    a.Title,
    ifnull(ar.Operation, '') as `Operation`,
	case when ar.idConstraint is null then 0 else 1 end as `isActive`
from
    attribute a
        left join
    constraint_attribute ar ON a.idAttribute = ar.idAttribute and ar.idConstraint = :id
where
     a.`Status` = 'a'
ORDER by a.idAttribute");

            $rmAtrStmt->execute(array(':id'=>$this->constraint->getId()));
            while ($a = $rmAtrStmt->fetch(PDO::FETCH_ASSOC)) {

                $attrs[$a['idAttribute']] = $a;
            }
        }

        return $attrs;
    }

    /**
     *
     * @param \PDO $dbh
     * @param array $capturedAttributes
     */
    public function saveAttributes(\PDO $dbh, $capturedAttributes) {

        foreach ($this->attributes as $k => $v) {

            if (isset($capturedAttributes[$k]) && $v['isActive'] == 0) {
                // set new attribute
                $rmAt = new ConstraintAttributeRS();
                $rmAt->idConstraint->setNewVal($this->constraint->getId());
                $rmAt->idAttribute->setNewVal($k);
                $rmAt->Type->setNewVal($this->constraint->getType());
                $rmAt->Operation->setNewVal($capturedAttributes[$k]);

                EditRS::insert($dbh, $rmAt);

            } else if (isset($capturedAttributes[$k]) === FALSE && $v['isActive'] != 0) {
                // remove attribute
                $rmAt = new ConstraintAttributeRS();
                $rmAt->idConstraint->setStoredVal($this->constraint->getId());
                $rmAt->idAttribute->setStoredVal($k);
                EditRS::delete($dbh, $rmAt, array($rmAt->idConstraint, $rmAt->idAttribute));

            }
        }

        $this->attributes = $this->loadAttributes($dbh);
    }
}



abstract class ConstraintsEntity {

    /**
     *
     * @var int
     */
    protected $idEntity;

    /**
     *
     * @var int
     */
    protected $idCopyEntity;

    /**
     *
     * @var array
     */
    protected $constraints;

    /**
     *
     * @param \PDO $dbh
     * @param int $idEntity
     * @param int $idCopyEntity
     */
    public function __construct(\PDO $dbh, $idEntity, $idCopyEntity = 0) {

        if (is_int($idEntity)) {
            $this->idEntity = $idEntity;
        } else {
            $this->idEntity = intval($idEntity, 10);
        }

        if (is_int($idCopyEntity)) {
            $this->idCopyEntity = $idCopyEntity;
        } else {
            $this->idCopyEntity = intval($idCopyEntity, 10);
        }

        $this->constraints = $this->loadConstraints($dbh);
    }

    /**
     *
     * @param \PDO $dbh
     * @param int $idEntity
     * @param string $type
     * @param int $idCopyEntity
     * @return \ConstraintsReservation|\ConstraintsHospital|\ConstraintsVisit
     */
    public static function constructByType(\PDO $dbh, $idEntity, $type, $idCopyEntity = 0) {

        switch ($type) {

            case Constraint_Type::Hospital:
                return new ConstraintsHospital($dbh, $idEntity, $idCopyEntity);

            case Constraint_Type::Reservation:
                return new ConstraintsReservation($dbh, $idEntity, $idCopyEntity);

            case Constraint_Type::Visit:
                return new ConstraintsVisit($dbh, $idEntity, $idCopyEntity);

        }
    }

    protected function loadConstraints(\PDO $dbh) {

        $cArray = array();

        $stmt = $dbh->prepare("select c.idConstraint, c.Title, case when ce.idEntity is null then 0 else 1 end as isActive
from `constraints` c left join `constraint_entity` ce on c.idConstraint = ce.idConstraint and ce.idEntity = :id
where c.Status = 'a' and c.Type = :tpe");

        // Use old reservation template for constratints?
        $idEntity = $this->getIdEntity();
        if ($idEntity == 0 && $this->idCopyEntity > 0) {
            $idEntity = $this->idCopyEntity;
        }

        $stmt->execute(array(':id'=>$idEntity, ':tpe'=>$this->getConstraintType()));

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cArray[$r['idConstraint']] = $r;
        }

        return $cArray;
    }

    protected function getIdEntity() {
        return $this->idEntity;
    }

    public function getConstraints() {
        return $this->constraints;
    }

    public function getActiveConstraintsArray() {

        $rConsts = array();

        foreach ($this->constraints as $c) {

            if ($c['isActive'] == 1) {
                $rConsts[$c['idConstraint']] = $c;
            }
        }

        return $rConsts;
    }

    protected abstract function getConstraintType();

    public function saveConstraints(\PDO $dbh, $capturedConstraints) {

        if ($this->getIdEntity() == 0) {
            return;
        }

        foreach ($this->constraints as $id => $c) {

            if (isset($capturedConstraints[$id]) && $c['isActive'] == 0) {

                $stmt = $dbh->query("insert Into `constraint_entity` (idConstraint, idEntity, `Type`) values ($id, ". $this->getIdEntity() . ", '" . $this->getConstraintType() . "' )");


            } else if (isset($capturedConstraints[$id]) === FALSE && $c['isActive'] == 1) {
                $stmt = $dbh->query("Delete from `constraint_entity` where idConstraint = $id and idEntity = ". $this->getIdEntity() . " and `Type` = '" . $this->getConstraintType() . "'");

            }

        }

        $this->constraints = $this->loadConstraints($dbh);
    }


}

class ConstraintsReservation extends ConstraintsEntity {


    public function __construct(\PDO $dbh, $idReservation, $idCopyEntity = 0) {

        parent::__construct($dbh, $idReservation, $idCopyEntity);

    }

    protected function getConstraintType() {
        return Constraint_Type::Reservation;
    }

    public function getIdReservation() {
            return $this->getIdEntity();
    }

}

class ConstraintsVisit extends ConstraintsEntity {


    public function __construct(\PDO $dbh, $idReservation, $idCopyEntity = 0) {

        parent::__construct($dbh, $idReservation, $idCopyEntity);

    }

    protected function getConstraintType() {
        return Constraint_Type::Visit;
    }

    public function getIdReservation() {
            return $this->getIdEntity();
    }

}


class ConstraintsHospital extends ConstraintsEntity {


    public function __construct(\PDO $dbh, $idHospital, $idCopyEntity = 0) {

        parent::__construct($dbh, $idHospital, $idCopyEntity);

    }

    protected function getConstraintType() {
        return Constraint_Type::Hospital;
    }

    public function getIdHospital() {
            return $this->getIdEntity();
    }

}


