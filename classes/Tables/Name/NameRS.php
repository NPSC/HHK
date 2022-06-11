<?php
namespace HHK\Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * NameRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class NameRS extends AbstractTableRS {

    public $idName;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Name_First;  // varchar(45) NOT NULL DEFAULT '',
    public $Name_Last;  // varchar(45) NOT NULL DEFAULT '',
    public $Name_Middle;  // varchar(45) NOT NULL DEFAULT '',
    public $Name_Nickname;  // varchar(45) NOT NULL DEFAULT '',
    public $Name_Full;  // varchar(170) NOT NULL DEFAULT '',
    public $Name_Previous;  // varchar(45) NOT NULL DEFAULT '',
    public $Web_Site;  // varchar(145) NOT NULL DEFAULT '',
    public $Member_Since;  // datetime DEFAULT NULL,
    public $Prev_MT_Change_Date;  // datetime DEFAULT NULL,
    public $Date_Added;  // datetime DEFAULT NULL,
    public $BirthDate;  // datetime DEFAULT NULL,
    public $Member_Status_Date;  // datetime DEFAULT NULL,
    public $Date_Deceased;  // DATETIME NULL DEFAULT NULL
    public $Member_Type;  // varchar(15) NOT NULL DEFAULT '',
    public $Member_Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Previous_Member_Type;  // varchar(15) NOT NULL DEFAULT '',
    public $Member_Category;  // varchar(45) NOT NULL DEFAULT '',
    public $Preferred_Mail_Address;  // varchar(5) NOT NULL DEFAULT '',
    public $Preferred_Email;  // varchar(5) NOT NULL DEFAULT '',
    public $Preferred_Phone;  // varchar(5) NOT NULL DEFAULT '',
    public $Organization_Code;  // varchar(15) NOT NULL DEFAULT '',
    public $External_Id;  // VARCHAR(25) NOT NULL DEFAULT '',
    public $Company_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Title;  // varchar(75) NOT NULL DEFAULT '',
    public $Company;  // varchar(80) NOT NULL DEFAULT '',
    public $Company_CareOf;  // varchar(4) NOT NULL DEFAULT '',
    public $Record_Member;  // bit(1) NOT NULL DEFAULT b'0',
    public $Record_Company;  // bit(1) NOT NULL DEFAULT b'0',
    public $Exclude_Directory;  // bit(1) NOT NULL DEFAULT b'0',
    public $Exclude_Mail;  // bit(1) NOT NULL DEFAULT b'0',
    public $Exclude_Email;  // bit(1) NOT NULL DEFAULT b'0',
    public $Exclude_Phone;  // bit(1) NOT NULL DEFAULT b'0',
    public $Gender;  // varchar(2) NOT NULL DEFAULT '',
    public $Name_Suffix;  // varchar(10) NOT NULL DEFAULT '',
    public $Name_Prefix;  // varchar(25) NOT NULL DEFAULT '',
    public $Name_Last_First;  // varchar(90) NOT NULL DEFAULT '',
    public $Birth_Month;  // int(11) NOT NULL DEFAULT '0',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "name") {

        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Name_First = new DB_Field("Name_First", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Name_Last = new DB_Field("Name_Last", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Name_Middle = new DB_Field("Name_Middle", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Name_Nickname = new DB_Field("Name_Nickname", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Name_Full = new DB_Field("Name_Full", "", new DbStrSanitizer(170));
        $this->Name_Previous = new DB_Field("Name_Previous", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Web_Site = new DB_Field("Web_Site", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Member_Since = new DB_Field("Member_Since", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Prev_MT_Change_Date = new DB_Field("Prev_MT_Change_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Date_Added = new DB_Field("Date_Added", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->BirthDate = new DB_Field("BirthDate", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Date_Deceased = new DB_Field("Date_Deceased", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Member_Status_Date = new DB_Field("Member_Status_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Member_Type = new DB_Field("Member_Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Member_Status = new DB_Field("Member_Status", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Previous_Member_Type = new DB_Field("Previous_Member_Type", "", new DbStrSanitizer(15));
        $this->Member_Category = new DB_Field("Member_Category", "", new DbStrSanitizer(45));
        $this->Preferred_Mail_Address = new DB_Field("Preferred_Mail_Address", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Preferred_Email = new DB_Field("Preferred_Email", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Preferred_Phone = new DB_Field("Preferred_Phone", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Organization_Code = new DB_Field("Organization_Code", "", new DbStrSanitizer(15));
        $this->External_Id = new DB_Field("External_Id", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Company_Id = new DB_Field("Company_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(75));
        $this->Company = new DB_Field("Company", "", new DbStrSanitizer(80), TRUE, TRUE);
        $this->Company_CareOf = new DB_Field("Company_CareOf", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Record_Member = new DB_Field("Record_Member", 0, new DbBitSanitizer());
        $this->Record_Company = new DB_Field("Record_Company", 0, new DbBitSanitizer());
        $this->Exclude_Directory = new DB_Field("Exclude_Directory", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Exclude_Mail = new DB_Field("Exclude_Mail", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Exclude_Email = new DB_Field("Exclude_Email", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Exclude_Phone = new DB_Field("Exclude_Phone", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Gender = new DB_Field("Gender", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Name_Suffix = new DB_Field("Name_Suffix", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->Name_Prefix = new DB_Field("Name_Prefix", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Name_Last_First = new DB_Field("Name_Last_First", "", new DbStrSanitizer(90));
        $this->Birth_Month = new DB_Field("Birth_Month", 0, new DbIntSanitizer());

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>