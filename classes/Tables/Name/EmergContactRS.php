<?php
namespace HHK\ Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * EmergContactRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class EmergContactRS extends AbstractTableRS {
    
    public $idEmergency_contact;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idName;   // int(11) NOT NULL,
    public $Name_Last;   // varchar(45) NOT NULL DEFAULT '',
    public $Name_First;   // varchar(45) NOT NULL DEFAULT '',
    public $Relationship;   // varchar(5) NOT NULL DEFAULT '',
    public $Phone_Home;   // varchar(15) NOT NULL DEFAULT '',
    public $Phone_Mobile;   // varchar(15) NOT NULL DEFAULT '',
    public $Phone_Alternate;   // varchar(15) NOT NULL DEFAULT '',
    //public $Notes;   // text,
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    protected $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "emergency_contact") {
        
        $this->idEmergency_contact = new DB_Field("idEmergency_contact", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Name_Last = new DB_Field("Name_Last", "", new DbStrSanitizer(45));
        $this->Name_First = new DB_Field("Name_First", "", new DbStrSanitizer(45));
        $this->Relationship = new DB_Field("Relationship", "", new DbStrSanitizer(45));
        $this->Phone_Home = new DB_Field("Phone_Home", "", new DbStrSanitizer(15));
        $this->Phone_Mobile = new DB_Field("Phone_Mobile", "", new DbStrSanitizer(15));
        $this->Phone_Alternate = new DB_Field("Phone_Alternate", "", new DbStrSanitizer(15));
        //$this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(2000));
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>