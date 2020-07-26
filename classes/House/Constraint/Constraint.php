<?php

namespace HHK\House\Constraint;

use HHK\Tables\Attribute\ConstraintRS;

/**
 * Constraint.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Constraint {

    protected $id;
    protected $type;
    protected $title;
    protected $category;
    protected $status;
    private $constraintRs;
    protected $attributes;

    public function __construct(ConstraintRS $constRs) {
        $this->loadObj($constRs);
    }

    protected function loadObj(ConstraintRS $constRs) {

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
?>