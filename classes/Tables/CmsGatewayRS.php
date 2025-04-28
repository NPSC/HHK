<?php
namespace HHK\Tables;

use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};

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
class CmsGatewayRS extends AbstractTableRS {

    public $idcms_gateway;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Gateway_Name;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $username;  // varchar(45) NOT NULL,
    public $clientId;  // varchar(45) NOT NULL DEFAULT '',
    public $clientSecret;  // varchar(245) NOT NULL DEFAULT '',
    public $securityToken;  // varchar(145) NOT NULL DEFAULT '',
    public $password;  // varchar(145) NOT NULL DEFAULT '',
    public $endpointUrl;  // varchar(145) NOT NULL DEFAULT '',
    public $userLoginUrl;
    public $apiVersion;
    public $retryCount;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "cc_hosted_gateway") {
        $this->idcms_gateway = new DB_Field("idcc_gateway", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Gateway_Name = new DB_Field("Gateway_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->username = new DB_Field("cc_name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->clientId = new DB_Field("Merchant_Id", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->clientSecret = new DB_Field("Password", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->securityToken = new DB_Field("Credit_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->password = new DB_Field("Trans_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->endpointUrl = new DB_Field("CardInfo_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->userLoginUrl = new DB_Field("Checkout_Url", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->retryCount = new DB_Field("Retry_Count", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->apiVersion = new DB_Field("CheckoutPOS_Url", "", new DbStrSanitizer(25), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>