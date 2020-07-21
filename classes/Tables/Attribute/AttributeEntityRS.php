<?php
namespace HHK\Tables\Attribute;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/*
 * AttributeRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class AttributeEntityRS extends AbstractTableRS {


    public $idEntity;  // int(11) NOT NULL,
    public $idAttribute;
    public $Type;  // varchar(4) NOT NULL,


    function __construct($TableName = "attribute_entity") {

        $this->idEntity = new DB_Field("idEntity", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idAttribute = new DB_Field("idAttribute", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        parent::__construct($TableName);
    }
}
?>