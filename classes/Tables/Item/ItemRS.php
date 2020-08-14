<?php
namespace HHK\Tables\Item;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDecimalSanitizer};

/*
 * ItemRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ItemRS
 *
 * @author Eric
 */
class ItemRS extends AbstractTableRS {

    public $idItem;  // INTEGER NOT NULL,
    public $Timeout_Days;  // VARCHAR(50) NOT NULL default '',
    public $First_Order_Id;  // INTEGER NOT NULL DEFAULT 0,
    public $Last_Order_Id;  // INTEGER NOT NULL DEFAULT 0,
    public $Percentage;  // DECIMAL(22,10) NOT NULL DEFAULT '0.00',
    public $Deleted;  // SMALLINT default 0 NOT NULL DEFAULT '0',
    public $Has_Decimals;  // SMALLINT default 0 NOT NULL DEFAULT '0',
    public $Gl_Code;  // VARCHAR(50) NOT NULL default '',
    public $Description;  // VARCHAR(1000), NOT NULL default ''.

    function __construct($TableName = 'item') {
        $this->idItem = new DB_Field('idItem', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Timeout_Days = new DB_Field('Timeout_Days', '', new DbStrSanitizer(50), TRUE, TRUE);
        $this->First_Order_Id = new DB_Field('First_Order_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Last_Order_Id = new DB_Field('Last_Order_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Percentage = new DB_Field('Percentage', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Deleted = new DB_Field('Deleted', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Has_Decimals = new DB_Field('Has_Decimals', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Gl_Code = new DB_Field('Gl_Code', '', new DbStrSanitizer(50), TRUE, TRUE);
        $this->Description = new DB_Field('Description', '', new DbStrSanitizer(1000), TRUE, TRUE);
        parent::__construct($TableName);
    }

}
?>