<?php
namespace HHK\ Tables\Registration;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * HospitalRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class HospitalRS extends AbstractTableRS {
    
    public $idHospital;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;   // varchar(45) NOT NULL DEFAULT '',
    public $Description;   // varchar(245) NOT NULL DEFAULT '',
    public $Type;   // varchar(45) NOT NULL DEFAULT '',
    public $Status;   // varchar(4) NOT NULL DEFAULT '',
    public $idLocation;   // int(11) NOT NULL DEFAULT '0',
    public $idName;   // int(11) NOT NULL DEFAULT '0',
    public $Reservation_Style;   // varchar(145) NOT NULL DEFAULT '',
    public $Stay_Style;   // varchar(145) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Updated_By;   //
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "hospital") {
        
        $this->idHospital = new DB_Field("idHospital", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->idLocation = new DB_Field("idLocation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Reservation_Style = new DB_Field("Reservation_Style", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Stay_Style = new DB_Field("Stay_Style", "", new DbStrSanitizer(145), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
    
}
?>