<?php
namespace HHK\Tables\Registration;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * RegistrationRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RegistrationRS
 * @package name
 * @author Eric
 */

class RegistrationRS extends AbstractTableRS {

    public $idRegistration;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idPsg;   // int(11) NOT NULL,
    public $Date_Registered;   // datetime DEFAULT NULL,
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Sig_Card;   // int(11) NOT NULL DEFAULT '0',
    public $Pamphlet;   // int(11) NOT NULL DEFAULT '0',
    public $Email_Receipt;  // tinyint(4) NOT NULL DEFAULT '0',
    public $Pref_Token_Id;
    public $Referral;   // int(11) NOT NULL DEFAULT '0',
    public $Vehicle;   // int(1) NOT NULL DEFAULT '0',
    public $Guest_Ident;   // varchar(45) NOT NULL DEFAULT '',
    public $Key_Deposit_Bal;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Lodging_Balance;  // DECIMAL(10,2) NOT NULL DEFAULT 0.00
    public $Notes;   // text
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    protected $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "registration") {

        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer());
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Pref_Token_Id = new DB_Field("Pref_Token_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Date_Registered = new DB_Field("Date_Registered", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Sig_Card = new DB_Field("Sig_Card", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Pamphlet = new DB_Field("Pamphlet", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Email_Receipt = new DB_Field("Email_Receipt", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Referral = new DB_Field("Referral", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Vehicle = new DB_Field("Vehicle", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Guest_Ident = new DB_Field("Guest_Ident", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Key_Deposit_Bal = new DB_Field("Key_Deposit_Bal", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Lodging_Balance = new DB_Field("Lodging_Balance", 0, new DbDecimalSanitizer(), TRUE, TRUE);

        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
?>
