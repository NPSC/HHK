<?php

namespace HHK\House\Constraint;

use HHK\SysConst\ConstraintType;


/**
 * ConstraintsReservation.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ConstraintsReservation extends AbstractConstraintsEntity {


    public function __construct(\PDO $dbh, $idReservation, $idCopyEntity = 0) {

        parent::__construct($dbh, $idReservation, $idCopyEntity);

    }

    protected function getConstraintType() {
        return ConstraintType::Reservation;
    }

    public function getIdReservation() {
            return $this->getIdEntity();
    }

}
?>