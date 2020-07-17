<?php
namespace Tables\PaymentGW;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * Guest_TokenRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Gateway_TransactionRS
 *
 * @author Eric
 */
class Gateway_TransactionRS extends AbstractTableRS {
    
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
?>