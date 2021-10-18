<?php
namespace HHK\Tables\Attribute;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/*
 * ConstraintRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Constraint
 *
 * @author Eric
 */
class ConstraintRS extends AbstractTableRS {

    public $idConstraint;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Type;  // varchar(4) NOT NULL,
    public $Title;  // varchar(145) NOT NULL DEFAULT '',
    public $Category;  // varchar(45) NOT NULL DEFAULT '',
    public $Status;  // varchar(4) NOT NULL DEFAULT '',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,

    function __construct($TableName = 'constraints') {

        $this->idConstraint = new DB_Field('idConstraint', 0, new DbIntSanitizer());
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(145), TRUE, TRUE);
        $this->Category = new DB_Field('Category', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(4), TRUE, TRUE);

        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field('Last_Updated', null, new DbDateSanitizer('Y-m-d H:i:s'), FALSE);
        //$this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
?>