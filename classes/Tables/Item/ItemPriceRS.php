<?php
namespace Tables\Item;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDecimalSanitizer};

/*
 * ItemPriceRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ItemPriceRS
 *
 * @author Eric
 */
class ItemPriceRS extends AbstractTableRS {
    
    public $idItem_price;  // INTEGER NOT NULL,
    public $Item_Id;  // INTEGER NOT NULL,
    public $Price;  // decimal
    public $Currency_Id;  // INTEGER NOT NULL,
    public $ModelCode;  // VARCHAR(5) NOT NULL DEFAULT ''
    
    function __construct($TableName = 'item_price') {
        $this->idItem_price = new DB_Field('idItem_price', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Item_Id = new DB_Field('Item_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Price = new DB_Field('Price', '', new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Currency_Id = new DB_Field('Currency_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->ModelCode = new DB_Field('ModelCode', '', new DbStrSanitizer(4), TRUE, TRUE);
        
        parent::__construct($TableName);
    }
    
}
?>