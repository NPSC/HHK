<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};

/**
 * RoomRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class RoomRS extends AbstractTableRS {

    public $idRoom;  // int(11) NOT NULL AUTO_INCREMENT,
    public $idHouse;   // int(11) NOT NULL DEFAULT '0',
    public $Item_Id;   // int(11) NOT NULL DEFAULT '0',
    public $Title;   // varchar(45) NOT NULL DEFAULT '',
    public $Description;   // text,
    public $Type;   // varchar(15) NOT NULL DEFAULT '',
    public $Category;   // varchar(5) NOT NULL DEFAULT '',
    public $Report_Category;   // varchar(5) NOT NULL DEFAULT '',
    public $State;   // varchar(15) NOT NULL DEFAULT '',
    public $Availability;   // varchar(15) Not Null Default '',
    public $Max_Occupants;  // int(11) NOT NULL DEFAULT '0',
    public $Min_Occupants;  // int(11) NOT NULL DEFAULT '0',
    public $Beds_King;  // int(11) NOT NULL DEFAULT '0',
    public $Beds_Queen;  // int(11) NOT NULL DEFAULT '0',
    public $Beds_Full;  // int(11) NOT NULL DEFAULT '0',
    public $Beds_Twin;  // int(11) NOT NULL DEFAULT '0',
    public $Beds_Utility;  // INT NOT NULL DEFAULT 0
    public $Phone;  // varchar(15) NOT NULL DEFAULT '',
    public $Floor;  // varchar(15) NOT NULL DEFAULT '',
    public $Util_Priority;   // varchar(5) NOT NULL DEFAULT '',
    public $idLocation;   // int(11) NOT NULL DEFAULT '0',
    public $Owner_Id;   // int(11) NOT NULL DEFAULT '0',
    public $Last_Cleaned;  // datetime DEFAULT NULL,
    public $Default_Rate_Category;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Rate_Code;   // varchar(5) NOT NULL DEFAULT '',     Static Rate lookup code to gen_lookups
    public $Visit_Fee_Code;   // varchar(5) NOT NULL DEFAULT '',
    public $Key_Deposit;   // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Key_Deposit_Code;   // varchar(5) NOT NULL DEFAULT '',
    public $Cleaning_Cycle_Code;  // VARCHAR(5) NOT NULL DEFAULT 'a'
    public $Notes;   // TEXT NULL DEFAULT NULL ,
    public $Status;   // VARCHAR(5) NOT NULL DEFAULT '' ,
    public $Updated_By;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Last_Updated;   // DATETIME NULL DEFAULT NULL ,
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,

    function __construct($TableName = "room") {

        $this->idRoom = new DB_Field("idRoom", 0, new DbIntSanitizer());
        $this->idHouse = new DB_Field("idHouse", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Item_Id = new DB_Field("Item_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Report_Category = new DB_Field("Report_Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->State = new DB_Field("State", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Availability = new DB_Field("Availability", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Max_Occupants = new DB_Field("Max_Occupants", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Min_Occupants = new DB_Field("Min_Occupants", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Beds_King = new DB_Field("Beds_King", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Beds_Queen = new DB_Field("Beds_Queen", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Beds_Full = new DB_Field("Beds_Full", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Beds_Twin = new DB_Field("Beds_Twin", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Beds_Utility = new DB_Field("Beds_Utility", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Util_Priority = new DB_Field("Util_Priority", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Phone = new DB_Field("Phone", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Floor = new DB_Field("Floor", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->idLocation = new DB_Field("idLocation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Owner_Id = new DB_Field("Owner_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Last_Cleaned = new DB_Field("Last_Cleaned", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Default_Rate_Category = new DB_Field("Default_Rate_Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Rate_Code = new DB_Field("Rate_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Visit_Fee_Code = new DB_Field("Visit_Fee_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Key_Deposit_Code = new DB_Field("Key_Deposit_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Cleaning_Cycle_Code = new DB_Field("Cleaning_Cycle_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Key_Deposit = new DB_Field("Key_Deposit", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
?>