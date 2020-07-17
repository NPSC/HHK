<?php
namespace Tables\Item;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer};

/*
 * ItemTypeMapRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ItemTypeMapRS
 *
 * @author Eric
 */
 
class ItemTypeMapRS extends AbstractTableRS {
    
    public $Item_Id;  // INTEGER,
    public $Type_Id;  // INTEGER);
    
    function __construct($TableName = 'item_type_map') {
        $this->Item_Id = new DB_Field('Item_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type_Id = new DB_Field('Type_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        
        parent::__construct($TableName);
    }
    
}
?>