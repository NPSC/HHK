<?php
namespace HHK\Tables\Item;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/*
 * ItemTypeRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ItemTypeRS
 *
 * @author Eric
 */

class ItemTypeRS extends AbstractTableRS {

    /**
     * Summary of idItem_Type
     * @var int
     */
    public $idItem_Type;  // INTEGER NOT NULL,
    /**
     * Summary of Category_Type
     * @var int
     */
    public $Category_Type;  // INTEGER NOT NULL,
    /**
     * Summary of Type_Description
     * @var string
     */
    public $Type_Description;  // VARCHAR(100),

    /**
     * Summary of Order_Line_Type_Id
     * @var int
     */
    public $Order_Line_Type_Id;  // INTEGER NOT NULL,

    /**
     * Summary of __construct
     * @param mixed $TableName
     */
    function __construct($TableName = 'item_type') {
        $this->idItem_Type = new DB_Field('idItem_Type', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Category_Type = new DB_Field('Category_Type', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type_Description = new DB_Field('Type_Description', '', new DbStrSanitizer(100), TRUE, TRUE);
        $this->Order_Line_Type_Id = new DB_Field('Order_Line_Type_Id', 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
?>