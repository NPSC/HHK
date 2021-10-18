<?php
namespace HHK\Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * NameVolunteerRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NameVolunteerRS extends AbstractTableRS {
    
    // Only DB_Field types can be public
    public $idName;
    public $Vol_Category;
    public $Vol_Code;
    public $Vol_Status;
    public $Vol_Availability;
    public $Vol_Notes;
    public $Vol_Begin;
    public $Vol_End;
    public $Vol_Check_Date;
    public $Dormant_Code;
    public $Vol_Rank;
    public $Vol_License;
    public $Vol_Training_Date;
    public $Vol_Trainer;
    public $Last_Updated;
    public $Updated_By;
    public $Timestamp;
    
    function __construct($TableName = 'name_volunteer2') {
        
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Vol_Category = new DB_Field("Vol_Category", "", new DbStrSanitizer(15));
        $this->Vol_Code = new DB_Field("Vol_Code", "", new DbStrSanitizer(5));
        $this->Vol_Status = new DB_Field("Vol_Status", "", new DbStrSanitizer(5));
        $this->Vol_Availability = new DB_Field("Vol_Availability", "", new DbStrSanitizer(5));
        $this->Vol_Notes = new DB_Field("Vol_Notes", "", new DbStrSanitizer(1500));
        $this->Vol_Begin = new DB_Field("Vol_Begin", null, new DbDateSanitizer());
        $this->Vol_End = new DB_Field("Vol_End", null, new DbDateSanitizer());
        $this->Vol_Check_Date = new DB_Field("Vol_Check_Date", null, new DbDateSanitizer());
        $this->Dormant_Code = new DB_Field("Dormant_Code", "", new DbStrSanitizer(45));
        $this->Vol_Rank = new DB_Field("Vol_Rank", "", new DbStrSanitizer(45));
        $this->Vol_License = new DB_Field("Vol_License", "", new DbStrSanitizer(25));
        $this->Vol_Training_Date = new DB_Field("Vol_Training_Date", null, new DbDateSanitizer());
        $this->Vol_Trainer = new DB_Field("Vol_Trainer", "", new DbStrSanitizer(45));
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(25), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>