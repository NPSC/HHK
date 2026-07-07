<?php
namespace HHK\Tables\Attribute;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/*
 * ConstraintAttributeRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ConstraintAttribute
 *
 * @author Eric
 */
 
class ConstraintAttributeRS extends AbstractTableRS {
    
    public DB_Field $idConstraint;  // int(11) NOT NULL,
    public DB_Field $idAttribute;
    public DB_Field $Type;  // varchar(4) NOT NULL,
    public DB_Field $Operation;  // varchar(4) NOT NULL,
    
    
    function __construct($TableName = "constraint_attribute") {
        
        $this->idConstraint = new DB_Field("idConstraint", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idAttribute = new DB_Field("idAttribute", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Operation = new DB_Field('Operation', '', new DbStrSanitizer(4), TRUE, TRUE);
        parent::__construct($TableName);
    }
}
?>