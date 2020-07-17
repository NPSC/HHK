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
 * Description of Guest_TokenRS
 *
 * @author Eric
 */
class Guest_TokenRS extends AbstractTableRS {

    public $idGuest_token;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idGuest;   // int(11) NOT NULL DEFAULT '0',
    public $Running_Total;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idRegistration;   // int(11) NOT NULL DEFAULT '0',
    public $Token;   // varchar(100) NOT NULL DEFAULT '',
    public $Merchant;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $Granted_Date;   // datetime DEFAULT NULL,
    public $LifetimeDays;   // int(11) NOT NULL DEFAULT '0',
    public $MaskedAccount;   // varchar(18) NOT NULL DEFAULT '',
    public $Frequency;   // varchar(15) NOT NULL DEFAULT '',
    public $Status;   // varchar(10) NOT NULL DEFAULT '',
    public $Response_Code;   // int(11) NOT NULL DEFAULT '1',
    public $CardHolderName;   // varchar(132) NOT NULL DEFAULT '',
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
        $this->Merchant = new DB_Field("Merchant", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Granted_Date = new DB_Field("Granted_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->LifetimeDays = new DB_Field("LifetimeDays", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->MaskedAccount = new DB_Field("MaskedAccount", "", new DbStrSanitizer(18), TRUE, TRUE);
        $this->Frequency = new DB_Field("Frequency", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->Response_Code = new DB_Field("Response_Code", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->CardHolderName = new DB_Field("CardHolderName", "", new DbStrSanitizer(132), TRUE, TRUE);
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
?>