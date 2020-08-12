<?php

namespace HHK\House\Constraint;

use HHK\SysConst\ConstraintType;

/**
 * Constraint.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ConstraintsHospital extends AbstractConstraintsEntity {


    public function __construct(\PDO $dbh, $idHospital, $idCopyEntity = 0) {

        parent::__construct($dbh, $idHospital, $idCopyEntity);

    }

    protected function getConstraintType() {
        return ConstraintType::Hospital;
    }

    public function getIdHospital() {
            return $this->getIdEntity();
    }

}
?>