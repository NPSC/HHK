<?php

namespace HHK\House\Constraint;

use HHK\Tables\EditRS;
use HHK\Tables\Attribute\ConstraintAttributeRS;

/**
 * Constraint.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

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
     * @param Constraint $constraint
     */
    public function __construct(\PDO $dbh, Constraint $constraint) {

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
            while ($a = $rmAtrStmt->fetch(\PDO::FETCH_ASSOC)) {

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
?>