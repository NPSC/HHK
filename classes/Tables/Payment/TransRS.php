<?php
namespace Tables\Payment;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbDecimalSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * TransRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class TransRS extends AbstractTableRS {
    
    public $idTrans;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Trans_Type;  // varchar(5) NOT NULL DEFAULT '' COMMENT '	',
    public $Trans_Method;  // varchar(5) NOT NULL DEFAULT '',
    public $Trans_Date;  // datetime DEFAULT NULL,
    public $idName;  // varchar(15) NOT NULL DEFAULT '',
    public $Order_Number;  // varchar(45) NOT NULL DEFAULT '',
    public $Invoice_Number;  // varchar(45) NOT NULL DEFAULT '',
    public $Payment_Type;  // varchar(15) NOT NULL DEFAULT '',
    public $Check_Number;  // varchar(15) NOT NULL DEFAULT '',
    public $Check_Bank;  // varchar(45) NOT NULL DEFAULT '',
    public $Card_Number;  // varchar(4) NOT NULL DEFAULT '',
    public $Card_Expire;  // varchar(15) NOT NULL DEFAULT '',
    public $Card_Authorize;  // varchar(15) NOT NULL DEFAULT '',
    public $Card_Name;  // varchar(45) NOT NULL DEFAULT '',
    public $Auth_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $RefNo;  // varchar(25) NOT NULL DEFAULT '',
    public $Process_Code;  // varchar(15) NOT NULL DEFAULT '',
    public $Gateway_Ref;  // varchar(45) NOT NULL DEFAULT '',
    public $Payment_Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Amount;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Date_Entered;  // datetime DEFAULT NULL,
    public $Entered_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "trans") {
        $this->idTrans = new DB_Field("idTrans", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Trans_Type = new DB_Field("Trans_Type", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Trans_Method = new DB_Field("Trans_Method", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Trans_Date = new DB_Field("Trans_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Order_Number = new DB_Field("Order_Number", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Invoice_Number = new DB_Field("Invoice_Number", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Payment_Type = new DB_Field("Payment_Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Check_Number = new DB_Field("Check_Number", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Check_Bank = new DB_Field("Check_Bank", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Card_Number = new DB_Field("Card_Number", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Card_Expire = new DB_Field("Card_Expire", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Card_Authorize = new DB_Field("Card_Authorize", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Card_Name = new DB_Field("Card_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Auth_Code = new DB_Field("Auth_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->RefNo = new DB_Field("RefNo", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Process_Code = new DB_Field("Process_Code", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Gateway_Ref = new DB_Field("Gateway_Ref", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Payment_Status = new DB_Field("Payment_Status", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Date_Entered = new DB_Field("Date_Entered", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        
        $this->Entered_By = new DB_Field("Entered_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>