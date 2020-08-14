<?php
namespace HHK\ Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * NameAddressRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NameAddressRS extends AbstractTableRS {
    
    public $idName_Address;  // int(11) NOT NULL AUTO_INCREMENT,
    public $idName;  // int(11) NOT NULL,
    public $Purpose;  // varchar(25) NOT NULL DEFAULT '',
    public $Address_1;  // varchar(200) NOT NULL DEFAULT '',
    public $Address_2;  // varchar(45) NOT NULL DEFAULT '',
    public $City;  // varchar(45) NOT NULL DEFAULT '',
    public $State_Province;  // varchar(45) NOT NULL DEFAULT '',
    public $Postal_Code;  // varchar(15) NOT NULL DEFAULT '',
    public $Country;  // varchar(45) NOT NULL DEFAULT '',
    public $Country_Code;  // varchar(10) NOT NULL DEFAULT '',
    public $County;  // varchar(45) NOT NULL DEFAULT '',
    public $Set_Incomplete;  // bit(1) NOT NULL DEFAULT b'0',
    public $Mail_Code;  // varchar(5) NOT NULL DEFAULT '',
    public $Last_Verified;  // datetime DEFAULT NULL,
    public $Bad_Address;  // varchar(15) NOT NULL DEFAULT '',
    public $Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "name_address") {
        $this->idName_Address = new DB_Field("idName_Address", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Purpose = new DB_Field("Purpose", "", new DbStrSanitizer(25));
        $this->Address_1 = new DB_Field("Address_1", "", new DbStrSanitizer(200), TRUE, TRUE);
        $this->Address_2 = new DB_Field("Address_2", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->City = new DB_Field("City", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->State_Province = new DB_Field("State_Province", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Postal_Code = new DB_Field("Postal_Code", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Country = new DB_Field("Country", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Country_Code = new DB_Field("Country_Code", "", new DbStrSanitizer(10));
        $this->County = new DB_Field("County", "", new DbStrSanitizer(45));
        $this->Mail_Code = new DB_Field("Mail_Code", "", new DbStrSanitizer(5));
        $this->Last_Verified = new DB_Field("Last_Verified", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Bad_Address = new DB_Field("Bad_Address", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Set_Incomplete = new DB_Field('Set_Incomplete', 0, new DbBitSanitizer(), TRUE, TRUE);
        
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>