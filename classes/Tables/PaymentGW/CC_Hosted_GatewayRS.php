<?php
namespace HHK\Tables\PaymentGW;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * CC_Hosted_GatewayRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of CC_Hosted_GatewayRS
 *
 * @author Eric
 */
class CC_Hosted_GatewayRS extends AbstractTableRS {

    public $idcc_gateway;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Gateway_Name;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $cc_name;  // varchar(45) NOT NULL,
    public $Merchant_Id;  // varchar(45) NOT NULL DEFAULT '',
    public $Password;  // varchar(245) NOT NULL DEFAULT '',
    public $Credit_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Trans_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Page_Header_URL;  // varchar(145) NOT NULL DEFAULT '',
    public $Checkout_Url;  // varchar(145) NOT NULL DEFAULT '',
    public $Manual_MerchantId;  // varchar(145) NOT NULL DEFAULT '',
    public $Manual_Password;  // varchar(145) NOT NULL DEFAULT '',
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
        $this->Gateway_Name = new DB_Field("Gateway_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->cc_name = new DB_Field("cc_name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Merchant_Id = new DB_Field("Merchant_Id", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Password = new DB_Field("Password", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Credit_Url = new DB_Field("Credit_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Trans_Url = new DB_Field("Trans_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Page_Header_URL = new DB_Field("CardInfo_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Checkout_Url = new DB_Field("Checkout_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Manual_MerchantId = new DB_Field("Mobile_CardInfo_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Manual_Password = new DB_Field("Mobile_Checkout_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->CheckoutPOS_Url = new DB_Field("CheckoutPOS_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->CheckoutPOSiFrame_Url = new DB_Field("CheckoutPOSiFrame_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
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