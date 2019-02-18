<?php
/**
 * PaymentGwRs.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of
 *
 * @author Eric
 */
class Guest_TokenRS extends TableRS {


    public $idGuest_token;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idGuest;   // int(11) NOT NULL DEFAULT '0',
    public $Running_Total;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idRegistration;   // int(11) NOT NULL DEFAULT '0',
    public $Token;   // varchar(100) NOT NULL DEFAULT '',
    public $Granted_Date;   // datetime DEFAULT NULL,
    public $LifetimeDays;   // int(11) NOT NULL DEFAULT '0',
    public $MaskedAccount;   // varchar(18) NOT NULL DEFAULT '',
    public $Frequency;   // varchar(15) NOT NULL DEFAULT '',
    public $Status;   // varchar(10) NOT NULL DEFAULT '',
    public $Response_Code;   // int(11) NOT NULL DEFAULT '1',
    public $CardHolderName;   // varchar(32) NOT NULL DEFAULT '',
    public $CardType;   // varchar(45) NOT NULL DEFAULT '',
    public $CardUsage;   // varchar(20) NOT NULL DEFAULT '',
    public $ExpDate;   // varchar(14) NOT NULL DEFAULT '',
    public $OperatorID;   // varchar(10) NOT NULL DEFAULT '',
    public $Tran_Type;   // varchar(10) NOT NULL DEFAULT '',
    public $StatusMessage;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "guest_token") {
        $this->idGuest_token = new DB_Field("idGuest_token", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGuest = new DB_Field("idGuest", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Running_Total = new DB_Field('Running_Total', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Token = new DB_Field("Token", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Granted_Date = new DB_Field("Granted_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->LifetimeDays = new DB_Field("LifetimeDays", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->MaskedAccount = new DB_Field("MaskedAccount", "", new DbStrSanitizer(18), TRUE, TRUE);
        $this->Frequency = new DB_Field("Frequency", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->Response_Code = new DB_Field("Response_Code", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->CardHolderName = new DB_Field("CardHolderName", "", new DbStrSanitizer(32), TRUE, TRUE);
        $this->CardType = new DB_Field("CardType", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->CardUsage = new DB_Field("CardUsage", "", new DbStrSanitizer(20), TRUE, TRUE);
        $this->ExpDate = new DB_Field("ExpDate", "", new DbStrSanitizer(14), TRUE, TRUE);
        $this->OperatorID = new DB_Field("OperatorID", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->Tran_Type = new DB_Field("Tran_Type", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->StatusMessage = new DB_Field("StatusMessage", "", new DbStrSanitizer(45), TRUE, TRUE);

        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}


class Gateway_TransactionRS extends TableRS {

    public $idgateway_transaction;   // int(11) NOT NULL AUTO_INCREMENT,
    public $GwTransCode;   // varchar(64) NOT NULL DEFAULT '',
    public $GwResultCode;   // varchar(44) NOT NULL DEFAULT '',
    public $Amount;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Vendor_Request;   // varchar(2000) NOT NULL DEFAULT '',
    public $Vendor_Response;   // varchar(5000) NOT NULL DEFAULT '',
    public $AuthCode;   // varchar(45) NOT NULL DEFAULT '',
    public $idPayment_Detail;   // int(11) NOT NULL DEFAULT '0',
    public $Created_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "gateway_transaction") {
        $this->idgateway_transaction = new DB_Field("idgateway_transaction", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->GwTransCode = new DB_Field("GwTransCode", "", new DbStrSanitizer(64), TRUE, TRUE);
        $this->GwResultCode = new DB_Field("GwResultCode", "", new DbStrSanitizer(44), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Vendor_Request = new DB_Field("Vendor_Request", "", new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Vendor_Response = new DB_Field("Vendor_Response", "", new DbStrSanitizer(5000), TRUE, TRUE);
        $this->AuthCode = new DB_Field("AuthCode", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->idPayment_Detail = new DB_Field("idPayment_Detail", 0, new DbIntSanitizer(), TRUE, TRUE);

        $this->Created_By = new DB_Field("Created_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}


class Cc_Hosted_GatewayRS extends TableRS {

    public $idcc_gateway;  // int(11) NOT NULL AUTO_INCREMENT,
    public $cc_name;  // varchar(45) NOT NULL,
    public $Merchant_Id;  // varchar(45) NOT NULL DEFAULT '',
    public $Password;  // varchar(245) NOT NULL DEFAULT '',
    public $Credit_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Trans_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $CardInfo_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Checkout_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Mobile_CardInfo_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Mobile_Checkout_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $CheckoutPOS_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $CheckoutPOSiFrame_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Use_AVS_Flag;  // bit(1) NOT NULL DEFAULT b'0',
    public $Use_Ccv_Flag;  // bit(1) NOT NULL DEFAULT b'0',
    public $Retry_Count;  // int(11) NOT NULL DEFAULT '0',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

     function __construct($TableName = "cc_hosted_gateway") {
        $this->idcc_gateway = new DB_Field("idcc_gateway", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->cc_name = new DB_Field("cc_name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Merchant_Id = new DB_Field("Merchant_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Password = new DB_Field("Password", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Credit_Url = new DB_Field("Credit_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Trans_Url = new DB_Field("Trans_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->CardInfo_Url = new DB_Field("CardInfo_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Checkout_Url = new DB_Field("Checkout_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Mobile_CardInfo_Url = new DB_Field("Mobile_CardInfo_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Mobile_Checkout_Url = new DB_Field("Mobile_Checkout_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->CheckoutPOS_Url = new DB_Field("CheckoutPOS_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->CheckoutPOSiFrame_Url = new DB_Field("CheckoutPOSiFrame_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Use_AVS_Flag = new DB_Field("Use_AVS_Flag", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Use_Ccv_Flag = new DB_Field("Use_Ccv_Flag", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Retry_Count = new DB_Field("Retry_Count", 0, new DbIntSanitizer(), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class InstamedGatewayRS extends TableRS {

    public $idcc_gateway;  // int(11) NOT NULL AUTO_INCREMENT,
    public $cc_name;  // varchar(45) NOT NULL,
    public $account_Id;  // varchar(45) NOT NULL DEFAULT '',
    public $security_Key;  // varchar(245) NOT NULL DEFAULT '',
    public $sso_Alias;  // varchar(145) NOT NULL DEFAULT '',
    public $merchant_Id;  // varchar(145) NOT NULL DEFAULT '',
    public $store_Id;  // varchar(145) NOT NULL DEFAULT '',
    public $providersSso_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $soap_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $nvp_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $terminal_Id;  // varchar(145) NOT NULL DEFAULT '',
//    public $CheckoutPOSiFrame_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Use_AVS_Flag;  // bit(1) NOT NULL DEFAULT b'0',
    public $Use_Ccv_Flag;  // bit(1) NOT NULL DEFAULT b'0',
    public $Retry_Count;  // int(11) NOT NULL DEFAULT '0',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

     function __construct($TableName = "cc_hosted_gateway") {
        $this->idcc_gateway = new DB_Field("idcc_gateway", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->cc_name = new DB_Field("cc_name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->account_Id = new DB_Field("Merchant_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->security_Key = new DB_Field("Password", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->sso_Alias = new DB_Field("Credit_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->merchant_Id = new DB_Field("Trans_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->store_Id = new DB_Field("CardInfo_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->providersSso_Url = new DB_Field("Checkout_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->soap_Url = new DB_Field("Mobile_CardInfo_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->nvp_Url = new DB_Field("Mobile_Checkout_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->terminal_Id = new DB_Field("CheckoutPOS_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
//        $this->CheckoutPOSiFrame_Url = new DB_Field("CheckoutPOSiFrame_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Use_AVS_Flag = new DB_Field("Use_AVS_Flag", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Use_Ccv_Flag = new DB_Field("Use_Ccv_Flag", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Retry_Count = new DB_Field("Retry_Count", 0, new DbIntSanitizer(), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}

class Card_IdRS extends TableRS {

    public $idName;   // int(11) NOT NULL,
    public $idGroup;   // int(11) NOT NULL,
    public $CardID;   // varchar(36) NOT NULL DEFAULT '',
    public $Init_Date;   // datetime DEFAULT NULL,
    public $ReturnCode;   // int(11) NOT NULL DEFAULT '0',
    public $Frequency;   // varchar(9) NOT NULL DEFAULT '',
    public $OperatorID;   // varchar(10) NOT NULL DEFAULT '',
    public $ResponseCode;   // int(11) NOT NULL DEFAULT '0',
    public $Transaction;   // varchar(14) NOT NULL DEFAULT '',
    public $InvoiceNumber;   // varchar(36) NOT NULL DEFAULT '',
    public $Amount;  // DECIMAL(11,2) NOT NULL DEFAULT 0.00,

    function __construct($TableName = "card_id") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGroup = new DB_Field("idGroup", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->CardID = new DB_Field("CardID", "", new DbStrSanitizer(36), TRUE, TRUE);
        $this->Init_Date = new DB_Field("Init_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->ReturnCode = new DB_Field("ReturnCode", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Frequency = new DB_Field("Frequency", "", new DbStrSanitizer(9), TRUE, TRUE);
        $this->OperatorID = new DB_Field("OperatorID", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->ResponseCode = new DB_Field("ResponseCode", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Transaction = new DB_Field("Transaction", "", new DbStrSanitizer(14), TRUE, TRUE);
        $this->InvoiceNumber = new DB_Field("InvoiceNumber", "", new DbStrSanitizer(36), TRUE, TRUE);
        $this->Amount = new DB_Field("Amount", 0, new DbDecimalSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }
}

class SsoTokenRS extends TableRS {

    public $Token;  // varchar(136) NOT NULL DEFAULT '',
    public $idName;  // int(11) NOT NULL,
    public $idGroup;  // int(11) NOT NULL,
    public $InvoiceNumber;  // varchar(36) NOT NULL DEFAULT '',
    public $Amount;  // DECIMAL(11,2) NOT NULL DEFAULT 0.00,
    public $State;  // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "ssotoken") {
        $this->Token = new DB_Field("Token", "", new DbStrSanitizer(36), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGroup = new DB_Field("idGroup", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->InvoiceNumber = new DB_Field("InvoiceNumber", "", new DbStrSanitizer(36), TRUE, TRUE);
        $this->Amount = new DB_Field("Amount", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->State = new DB_Field("State", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}
