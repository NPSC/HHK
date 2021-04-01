<?php
namespace HHK\ Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbDecimalSanitizer, DbStrSanitizer, DbDateSanitizer};
use HHK\Tables\Fields\DbBitSanitizer;

/**
 * InvoiceRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class InvoiceRS extends AbstractTableRS {
    
    public $idInvoice;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Delegated_Invoice_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Invoice_Number;  // varchar(45) DEFAULT NULL,
    public $Deleted;  // SMALLINT default 0 NOT NULL,
    public $Amount;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Sold_To_Id;  //;  // int(11) DEFAULT NULL,
    public $idGroup;  // int(11) DEFAULT NULL,
    public $Invoice_Date;  // datetime DEFAULT NULL,
    public $Payment_Attempts;  // int(11) NOT NULL DEFAULT '0',
    public $Status;  // varchar(5) NOT NULL DEFAULT '',
    public $Carried_Amount;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Balance;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Order_Number;  // varchar(45) DEFAULT NULL,
    public $Suborder_Number; //   `Suborder_Number` smallint(6) NOT NULL DEFAULT '0',
    public $Billing_Process_Id;
    public $BillStatus;  // VARCHAR(5) NOT NULL DEFAULT '',
    public $BillDate;  // DATE NULL,
    public $Description;  // varchar(45) DEFAULT NULL,
    public $Notes;  // varchar(450) DEFAULT NULL,
    public $tax_exempt; // tinyint default 0,
    public $Updated_By;  // varchar(45) DEFAULT NULL,
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Timestamp;  // timestamp NULL DEFAULT NULL,
    
    function __construct($TableName = 'invoice') {
        $this->idInvoice = new DB_Field('idInvoice', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Delegated_Invoice_Id = new DB_Field('Delegated_Invoice_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Invoice_Number = new DB_Field('Invoice_Number', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Billing_Process_Id = new DB_Field('Billing_Process_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Deleted = new DB_Field('Deleted', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Sold_To_Id = new DB_Field('Sold_To_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGroup = new DB_Field('idGroup', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Invoice_Date = new DB_Field('Invoice_Date', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Payment_Attempts = new DB_Field('Payment_Attempts', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field('Status', "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Carried_Amount = new DB_Field('Carried_Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Balance = new DB_Field('Balance', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Order_Number = new DB_Field('Order_Number', "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Suborder_Number = new DB_Field('Suborder_Number', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(450), TRUE, TRUE);
        $this->tax_exempt = new DB_Field('tax_exempt', 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->BillStatus = new DB_Field('BillStatus', "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->BillDate = new DB_Field("BillDate", '',  new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>