<?php
/**
 * PaymentsRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class PaymentRS extends TableRS {
    public $idPayment;   // int(11) NOT NULL,
    public $Attempt;   // int(11) DEFAULT NULL,
    public $Amount;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Balance;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Result;   // varchar(24) NOT NULL DEFAULT '',
    public $Payment_Date;   // datetime DEFAULT NULL,
    public $idPayor;   // int(11) NOT NULL DEFAULT '0',
    public $idPayment_Method;   // int(11) NOT NULL DEFAULT '0',
    public $idTrans;  // int(11) NOT NULL DEFAULT '0',
    public $idToken;  // int(11) NOT NULL DEFAULT '0',
    public $Is_Refund;   // tinyint(4) DEFAULT NULL,
    public $Is_Preauth;   // tinyint(4) DEFAULT NULL,
    public $Status_Code;   // varchar(5) NOT NULL DEFAULT ''
    public $Notes;  // TEXT NULL DEFAULT NULL,,
    public $External_Id;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $Created_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "payment") {
        $this->idPayment = new DB_Field("idPayment", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Attempt = new DB_Field("Attempt", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Balance = new DB_Field('Balance', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Result = new DB_Field("Result", "", new DbStrSanitizer(24), TRUE, TRUE);
        $this->Payment_Date = new DB_Field("Payment_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->idPayor = new DB_Field("idPayor", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idToken = new DB_Field("idToken", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPayment_Method = new DB_Field("idPayment_Method", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idTrans = new DB_Field("idTrans", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Is_Refund = new DB_Field("Is_Refund", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Is_Preauth = new DB_Field("Is_Preauth", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status_Code = new DB_Field("Status_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->External_Id = new DB_Field("External_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", '', new DbStrSanitizer(2000), TRUE, True);
        $this->Created_By = new DB_Field("Created_By", '', new DbStrSanitizer(45), TRUE, True);

        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class PaymentInvoiceRS extends TableRS {

    public $idPayment_Invoice;   // INTEGER NOT NULL,
    public $Payment_Id;   // INTEGER,
    public $Invoice_Id;   // INTEGER,
    public $Amount;   // DECIMAL(22,10),
    public $Create_Datetime;   // TIMESTAMP NOT NULL,

    function __construct($TableName = "payment_invoice") {
        $this->idPayment_Invoice = new DB_Field("idPayment_Invoice", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Payment_Id = new DB_Field("Payment_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Invoice_Id = new DB_Field("Invoice_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Create_Datetime = new DB_Field("Create_Datetime", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}

class PaymentInfoCheckRS extends TableRS {

    public $idpayment_info_Check;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idPayment;   // int(11) NOT NULL,
    public $Bank;   // varchar(45) NOT NULL DEFAULT '',
    public $Check_Number;   // varchar(45) NOT NULL DEFAULT '',
    public $Check_Date;   // date DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "payment_info_check") {

        $this->idpayment_info_Check = new DB_Field("idpayment_info_Check", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPayment = new DB_Field("idPayment", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Bank = new DB_Field("Bank", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Check_Number = new DB_Field("Check_Number", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Check_Date = new DB_Field("Check_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);

        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class Payment_AuthRS extends TableRS {

    public $idPayment_auth;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idPayment;  // int(11) NOT NULL,
    public $Approved_Amount;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idTrans;   // int(11) NOT NULL DEFAULT '0',
    public $Processor;   // varchar(45) NOT NULL DEFAULT '',
    public $Approval_Code;   // varchar(20) NOT NULL DEFAULT '',
    public $Status_Message;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $AVS;   // varchar(20) NOT NULL DEFAULT '',
    public $Invoice_Number;   // varchar(45) NOT NULL DEFAULT '',
    public $Acct_Number;  // varchar(25) NOT NULL DEFAULT '',
    public $Card_Type;  // varchar(10) NOT NULL DEFAULT '',
    public $Customer_Id;   // varchar(45) NOT NULL DEFAULT '',
    public $Response_Message;  // varchar(200) NOT NULL DEFAULT '',
    public $Reference_Num;   // varchar(45) NOT NULL DEFAULT '',
    public $AcqRefData;   // varchar(200) NOT NULL DEFAULT '',
    public $ProcessData;   // varchar(200) NOT NULL DEFAULT '',
    public $Code3;   // varchar(45) NOT NULL DEFAULT '',
    public $Serialized_Details;   // varchar(1000) NOT NULL DEFAULT '',
    public $Status_Code;   // varchar(5) NOT NULL DEFAULT '',
    public $EMVCardEntryMode;  // VARCHAR(45) NULL AFTER `Status_Code`,
    public $EMVAuthorizationMode;  // VARCHAR(45) NULL AFTER `EMVCardEntryMode`,
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
        $this->idTrans = new DB_Field("idTrans", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Processor = new DB_Field("Processor", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Approval_Code = new DB_Field("Approval_Code", "", new DbStrSanitizer(20), TRUE, TRUE);
        $this->Status_Message = new DB_Field("Status_Message", "", new DbStrSanitizer(40), TRUE, TRUE);
        $this->AVS = new DB_Field("AVS", "", new DbStrSanitizer(20), TRUE, TRUE);
        $this->Invoice_Number = new DB_Field("Invoice_Number", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Acct_Number = new DB_Field("Acct_Number", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Card_Type = new DB_Field("Card_Type", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->Customer_Id = new DB_Field("Customer_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Response_Message = new DB_Field("Response_Message", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->Reference_Num = new DB_Field("Reference_Num", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->AcqRefData = new DB_Field("Code1", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->ProcessData = new DB_Field("Code2", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->Code3 = new DB_Field("Code3", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Serialized_Details = new DB_Field("Serialized_Details", "", new DbStrSanitizer(1000), TRUE, TRUE);
        $this->Status_Code = new DB_Field("Status_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->EMVCardEntryMode = new DB_Field("EMVCardEntryMode", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->EMVAuthorizationMode = new DB_Field("EMVAuthorizationMode", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->EMVApplicationIdentifier = new DB_Field("EMVApplicationIdentifier", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->EMVTerminalVerificationResults = new DB_Field("EMVTerminalVerificationResults", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->EMVIssuerApplicationData = new DB_Field("EMVIssuerApplicationData", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->EMVTransactionStatusInformation = new DB_Field("EMVTransactionStatusInformation", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->EMVApplicationResponseCode = new DB_Field("EMVApplicationResponseCode", "", new DbStrSanitizer(45), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class TransRs extends TableRS {

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

class InvoiceRs extends TableRS {

    public $idInvoice;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Delegated_Invoice_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Invoice_Number;  // varchar(45) DEFAULT NULL,
    public $Invoice_Type;  // varchar(4) DEFAULT NULL,
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
    public $First_Payment_Due;  // date DEFAULT NULL,
    public $BillStatus;  // VARCHAR(5) NOT NULL DEFAULT '',
    public $BillDate;  // DATE NULL,
    public $Last_Reminder;  // DATETIME,
    public $Overdue_Step;  // INTEGER NOT NULL DEFAULT '0',
    public $Description;  // varchar(45) DEFAULT NULL,
    public $Notes;  // varchar(450) DEFAULT NULL,
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
        $this->Due_Date = new DB_Field("Due_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(450), TRUE, TRUE);
        $this->BillStatus = new DB_Field('BillStatus', "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->BillDate = new DB_Field("BillDate", '',  new DbDateSanitizer("Y-m-d"), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class InvoiceLineRS extends TableRS {

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
    public $Source_User_Id;  // INTEGER,
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
        $this->Source_User_Id = new DB_Field('Source_User_Id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Is_Percentage = new DB_Field('Is_Percentage', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Timestamp = new DB_Field('Timestamp', null, new DbDateSanitizer('Y-m-d H:i:s'), FALSE);
        parent::__construct($TableName);
    }

}

class InvoiceLineTypeRS extends TableRS {

    public $id;  // INTEGER NOT NULL,
    public $Description;  // VARCHAR(50) NOT NULL,
    public $Order_Position;  // INTEGER NOT NULL,

    function __construct($TableName = 'invoice_line_type') {
        $this->id = new DB_Field('id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Description = new DB_Field('Description', '', new DbStrSanitizer(50), TRUE, TRUE);
        $this->Order_Position = new DB_Field('Order_Position', 0, new DbIntSanitizer(), TRUE, TRUE);
        parent::__construct($TableName);
    }

}

