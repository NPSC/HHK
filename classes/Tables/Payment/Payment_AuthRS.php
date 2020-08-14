<?php
namespace HHK\ Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbDecimalSanitizer, DbStrSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * Payment_AuthRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Payment_AuthRS extends AbstractTableRS {
    
    public $idPayment_auth;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idPayment;  // int(11) NOT NULL,
    public $Approved_Amount;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idTrans;   // int(11) NOT NULL DEFAULT '0',
    public $Processor;   // varchar(45) NOT NULL DEFAULT '',
    public $Merchant;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $Approval_Code;   // varchar(20) NOT NULL DEFAULT '',
    public $Status_Message;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $AVS;   // varchar(20) NOT NULL DEFAULT '',
    public $CVV;   // varchar(45) NOT NULL DEFAULT '',
    public $Signature_Required;  // INT(4) NOT NULL DEFAULT 0 AFTER `ProcessData`;
    public $PartialPayment;
    public $Invoice_Number;   // varchar(45) NOT NULL DEFAULT '',
    public $Acct_Number;  // varchar(25) NOT NULL DEFAULT '',
    public $Card_Type;  // varchar(10) NOT NULL DEFAULT '',
    public $Cardholder_Name;  //`Cardholder_Name` VARCHAR(45) NOT NULL DEFAULT ''
    public $Customer_Id;   // varchar(45) NOT NULL DEFAULT '',
    public $Response_Message;  // varchar(200) NOT NULL DEFAULT '',
    public $Response_Code;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $Reference_Num;   // varchar(45) NOT NULL DEFAULT '',
    public $AcqRefData;   // varchar(200) NOT NULL DEFAULT '',
    public $ProcessData;   // varchar(200) NOT NULL DEFAULT '',
    public $Serialized_Details;   // varchar(1000) NOT NULL DEFAULT '',
    public $Status_Code;   // varchar(5) NOT NULL DEFAULT '',
    public $EMVApplicationIdentifier;  // VARCHAR(45) NULL AFTER `EMVAuthoriationMode`,
    public $EMVTerminalVerificationResults;  // VARCHAR(45) NULL AFTER `EMVApplicationIdentifier`,
    public $EMVIssuerApplicationData;  // VARCHAR(45) NULL AFTER `EMVTerminalVerificationResults`,
    public $EMVTransactionStatusInformation;  // VARCHAR(45) NULL AFTER `EMVIssuerApplicationData`,
    public $EMVApplicationResponseCode;  // VARCHAR(45) NULL AFTER `EMVTransactionStatusInformation`;
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "payment_auth") {
        $this->idPayment_auth = new DB_Field("idPayment_auth", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPayment = new DB_Field("idPayment", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Approved_Amount = new DB_Field('Approved_Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Signature_Required = new DB_Field('Signature_Required', 1, new DbBitSanitizer(), TRUE, TRUE);
        $this->PartialPayment = new DB_Field('PartialPayment', 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->idTrans = new DB_Field("idTrans", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Processor = new DB_Field("Processor", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Merchant = new DB_Field("Merchant", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Approval_Code = new DB_Field("Approval_Code", "", new DbStrSanitizer(20), TRUE, TRUE);
        $this->Status_Message = new DB_Field("Status_Message", "", new DbStrSanitizer(40), TRUE, TRUE);
        $this->AVS = new DB_Field("AVS", "", new DbStrSanitizer(20), TRUE, TRUE);
        $this->Invoice_Number = new DB_Field("Invoice_Number", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Acct_Number = new DB_Field("Acct_Number", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Card_Type = new DB_Field("Card_Type", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->Cardholder_Name = new DB_Field("Cardholder_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Customer_Id = new DB_Field("Customer_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Response_Message = new DB_Field("Response_Message", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->Response_Code = new DB_Field("Response_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Reference_Num = new DB_Field("Reference_Num", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->AcqRefData = new DB_Field("AcqRefData", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->ProcessData = new DB_Field("ProcessData", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->CVV = new DB_Field("CVV", "", new DbStrSanitizer(20), TRUE, TRUE);
        $this->Serialized_Details = new DB_Field("Serialized_Details", "", new DbStrSanitizer(1000), TRUE, TRUE);
        $this->Status_Code = new DB_Field("Status_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->EMVApplicationIdentifier = new DB_Field("EMVApplicationIdentifier", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->EMVTerminalVerificationResults = new DB_Field("EMVTerminalVerificationResults", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->EMVIssuerApplicationData = new DB_Field("EMVIssuerApplicationData", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->EMVTransactionStatusInformation = new DB_Field("EMVTransactionStatusInformation", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->EMVApplicationResponseCode = new DB_Field("EMVApplicationResponseCode", "", new DbStrSanitizer(200), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>