<?php

namespace HHK\House\Constraint;

use HHK\SysConst\ConstraintType;

/**
 * AbstractConstraintsEntity.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractConstraintsEntity {

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
     * @return ConstraintsReservation|ConstraintsHospital|ConstraintsVisit
     */
    public static function constructByType(\PDO $dbh, $idEntity, $type, $idCopyEntity = 0) {

        switch ($type) {

            case ConstraintType::Hospital:
                return new ConstraintsHospital($dbh, $idEntity, $idCopyEntity);

            case ConstraintType::Reservation:
                return new ConstraintsReservation($dbh, $idEntity, $idCopyEntity);

            case ConstraintType::Visit:
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

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
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

                $dbh->exec("insert Into `constraint_entity` (idConstraint, idEntity, `Type`) values ($id, ". $this->getIdEntity() . ", '" . $this->getConstraintType() . "' )");


            } else if (isset($capturedConstraints[$id]) === FALSE && $c['isActive'] == 1) {
                $dbh->exec("Delete from `constraint_entity` where idConstraint = $id and idEntity = ". $this->getIdEntity() . " and `Type` = '" . $this->getConstraintType() . "'");

            }

        }

        $this->constraints = $this->loadConstraints($dbh);
    }


}

?>