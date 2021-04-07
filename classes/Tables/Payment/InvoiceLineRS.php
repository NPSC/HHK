<?php
namespace HHK\Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbDecimalSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * InvoiceLineRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class InvoiceLineRS extends AbstractTableRS {
    
    public $idInvoice_Line;  // INTEGER NOT NULL,
    public $Invoice_Id;  // INTEGER,
    public $Type_Id;  //Integer NOT NULL DEFAULT '0',
    public $Amount;  // DECIMAL(22,10) NOT NULL,
    public $Quantity;  // DECIMAL(22,10),
    public $Price;  // DECIMAL(22,10),
    public $Period_Start;  // DATETIME,
    public $Period_End;
    public $Deleted;  // SMALLINT default 0 NOT NULL,
    public $Item_Id;  // INTEGER,
    public $Description;  // VARCHAR(1000),
    public $Source_Item_Id;  // INTEGER,
    public $Is_Percentage;  // SMALLINT default 0 NOT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = 'invoice_line') {
        $this->idInvoice_Line = new DB_Field('idInvoice_Line', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Invoice_Id = new DB_Field('Invoice_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type_Id = new DB_Field('Type_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Quantity = new DB_Field('Quantity', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Price = new DB_Field('Price', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Period_Start = new DB_Field("Period_Start", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Period_End = new DB_Field("Period_End", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Deleted = new DB_Field('Deleted', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Item_Id = new DB_Field('Item_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Description = new DB_Field('Description', '', new DbStrSanitizer(1000), TRUE, TRUE);
        $this->Source_Item_Id = new DB_Field('Source_Item_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Is_Percentage = new DB_Field('Is_Percentage', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Timestamp = new DB_Field('Timestamp', null, new DbDateSanitizer('Y-m-d H:i:s'), FALSE);
        parent::__construct($TableName);
    }
    
}
?>