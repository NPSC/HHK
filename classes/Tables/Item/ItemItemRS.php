<?php
namespace HHK\Tables\Item;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer};

/**
 * ItemItemRS.php
 *
 * Links an item to the tax items that apply to it.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ItemItemRS extends AbstractTableRS {

    public DB_Field $idItem;  // INT NOT NULL,
    public DB_Field $Item_Id;  // INT NOT NULL,

    function __construct($TableName = "item_item") {

        $this->idItem = new DB_Field('idItem', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Item_Id = new DB_Field('Item_Id', 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
