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
    public DB_Field $idName;
    public DB_Field $Vol_Category;
    public DB_Field $Vol_Code;
    public DB_Field $Vol_Status;
    public DB_Field $Vol_Availability;
    public DB_Field $Vol_Notes;
    public DB_Field $Vol_Begin;
    public DB_Field $Vol_End;
    public DB_Field $Vol_Check_Date;
    public DB_Field $Dormant_Code;
    public DB_Field $Vol_Rank;
    public DB_Field $Vol_License;
    public DB_Field $Vol_Training_Date;
    public DB_Field $Vol_Trainer;
    public DB_Field $Last_Updated;
    public DB_Field $Updated_By;
    public DB_Field $Timestamp;
    
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