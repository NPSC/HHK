<?php
namespace Tables\PaymentGW;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * InstamedGatewayRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of InstamedGatewayRS
 *
 * @author Eric
 */
class InstamedGatewayRS extends AbstractTableRS {
    
    public $idcc_gateway;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Gateway_Name;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $cc_name;  // varchar(45) NOT NULL,
    public $account_Id;  // varchar(45) NOT NULL DEFAULT '',
    public $security_Key;  // varchar(245) NOT NULL DEFAULT '',
    public $password;
    public $sso_Alias;  // varchar(145) NOT NULL DEFAULT '',
    public $merchant_Id;  // varchar(145) NOT NULL DEFAULT '',
    public $store_Id;  // varchar(145) NOT NULL DEFAULT '',
    public $providersSso_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $nvp_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $terminal_Id;  // varchar(145) NOT NULL DEFAULT '',
    public $WorkStation_Id;  // varchar(145) NOT NULL DEFAULT '',
    public $Use_AVS_Flag;  // bit(1) NOT NULL DEFAULT b'0',
    public $Use_Ccv_Flag;  // bit(1) NOT NULL DEFAULT b'0',
    public $Retry_Count;  // int(11) NOT NULL DEFAULT '0',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "cc_hosted_gateway") {
        $this->idcc_gateway = new DB_Field("idcc_gateway", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Gateway_Name = new DB_Field("Gateway_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->cc_name = new DB_Field("cc_name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->account_Id = new DB_Field("Merchant_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->security_Key = new DB_Field("Password", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->password = new DB_Field("Mobile_CardInfo_Url", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->sso_Alias = new DB_Field("Credit_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->merchant_Id = new DB_Field("Trans_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->store_Id = new DB_Field("CardInfo_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->providersSso_Url = new DB_Field("Checkout_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->nvp_Url = new DB_Field("Mobile_Checkout_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->terminal_Id = new DB_Field("CheckoutPOS_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->WorkStation_Id = new DB_Field("CheckoutPOSiFrame_Url", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Use_AVS_Flag = new DB_Field("Use_AVS_Flag", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Use_Ccv_Flag = new DB_Field("Use_Ccv_Flag", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Retry_Count = new DB_Field("Retry_Count", 0, new DbIntSanitizer(), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>