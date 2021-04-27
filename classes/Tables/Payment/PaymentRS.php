<?php
namespace HHK\Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbDecimalSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * PaymentsRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class PaymentRS extends AbstractTableRS {
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
?>