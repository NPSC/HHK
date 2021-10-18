<?php
namespace HHK\Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * PaymentInfoCheckRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentInfoCheckRS extends AbstractTableRS {
    
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
?>