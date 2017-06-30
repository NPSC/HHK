<?php
/**
 * nameRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class NameRS extends TableRS {

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
        $this->Gender = new DB_Field("Gender", "", new DbStrSanitizer(2), TRUE, TRUE);
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

class NameAddressRS extends TableRS {

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

class NamePhoneRS extends TableRS {

    public $idName;  // int(11) NOT NULL,
    public $Phone_Num;  // varchar(45) NOT NULL DEFAULT '',
    public $Phone_Extension;  // varchar(15) NOT NULL DEFAULT '',
    public $Phone_Code;  // varchar(5) NOT NULL,
    public $Phone_Search;  // varchar(25) NOT NULL DEFAULT '',
    public $is_Mobile;  // bit(1) NOT NULL DEFAULT b'0',
    public $is_Toll_Free;  // bit(1) NOT NULL DEFAULT b'0',
    public $is_International;  // bit(1) NOT NULL DEFAULT b'0',
    public $Bad_Number;  // varchar(15) NOT NULL DEFAULT '',
    public $Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "name_phone") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Phone_Num = new DB_Field("Phone_Num", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Phone_Search = new DB_Field("Phone_Search", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Phone_Extension = new DB_Field("Phone_Extension", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Phone_Code = new DB_Field("Phone_Code", "", new DbStrSanitizer(5));
        $this->is_Mobile = new DB_Field("is_Mobile", 0, new DbBitSanitizer());
        $this->is_Toll_Free = new DB_Field("is_Toll_Free", 0, new DbBitSanitizer());
        $this->is_International = new DB_Field("is_International", 0, new DbBitSanitizer());
        $this->Bad_Number = new DB_Field("Bad_Number", "", new DbStrSanitizer(5));

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class NameEmailRS extends TableRS {

    public $idName;  // int(11) NOT NULL,
    public $Purpose;  // varchar(25) NOT NULL DEFAULT '',
    public $Email;  // varchar(140) NOT NULL DEFAULT '',
    public $Bad_Address;  // varchar(15) NOT NULL DEFAULT '' COMMENT '				',
    public $Last_Verified;  // date DEFAULT NULL,
    public $Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Last_Updated;  // date DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "name_email") {
       $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Purpose = new DB_Field("Purpose", "", new DbStrSanitizer(25));
        $this->Email = new DB_Field("Email", "", new DbStrSanitizer(140), TRUE, TRUE);
        $this->Bad_Address = new DB_Field("Bad_Address", "", new DbStrSanitizer(15));
        $this->Last_Verified = new DB_Field("Last_Verified", NULL, new DbDateSanitizer("Y-m-d H:i:s"));

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
     }

}

class NameDemogRS extends TableRS {

    public $idName;  // int(11) NOT NULL,
    public $Gen_Notes;  // text,
    public $Contact_Date;  // date DEFAULT NULL,
    public $Orientation_Date;  // date DEFAULT NULL,
    public $Confirmed_Date;  // DATETIME NULL DEFAULT NULL
    public $Age_Bracket;
    public $Income_Bracket;
    public $Education_Level;  // varchar(5) NOT NULL DEFAULT '',
    public $Newsletter;
    public $Photo_Permission;
    public $No_Return;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Ethnicity;  // varchar(45) NOT NULL DEFAULT '',
    public $Media_Source;  // varchar(5) NOT NULL DEFAULT '',
    public $Special_Needs;  // varchar(5) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


      function __construct($TableName = "name_demog") {

        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Newsletter = new DB_Field("Newsletter", '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Photo_Permission = new DB_Field("Photo_Permission", '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Gen_Notes = new DB_Field("Gen_Notes", "", new DbStrSanitizer(5000), TRUE, TRUE);
        $this->Contact_Date = new DB_Field("Contact_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE);
        $this->Orientation_Date = new DB_Field("Orientation_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE);
        $this->Confirmed_Date = new DB_Field("Confirmed_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE);
        $this->Age_Bracket = new DB_Field('Age_Bracket', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->No_Return = new DB_Field('No_Return', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Income_Bracket = new DB_Field('Income_Bracket', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Education_Level = new DB_Field('Education_Level', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Ethnicity = new DB_Field('Ethnicity', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Media_Source = new DB_Field('Media_Source', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Special_Needs = new DB_Field('Special_Needs', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
      }

}

class NameVolunteerRS extends TableRS {

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

class relationsRS extends TableRS {

    public $idRelationship;  // int(11) NOT NULL AUTO_INCREMENT,
    public $idName;  // int(11) NOT NULL,
    public $Target_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Relation_Type;  // varchar(5) NOT NULL DEFAULT '',
    public $Status;  // varchar(45) NOT NULL DEFAULT '',
    public $Principal;  // bit(1) NOT NULL DEFAULT b'0',
    public $Effective_Date;  // date DEFAULT NULL,
    public $Thru_date;  // date DEFAULT NULL,
    public $Note;  // text
    public $Date_Added;  // datetime DEFAULT NULL,
    public $Group_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "relationship") {

        $this->idRelationship = new DB_Field("idRelationship", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Target_Id = new DB_Field("Target_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Relation_Type = new DB_Field("Relation_Type", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Principal = new DB_Field("Principal", 0, new DbBitSanitizer());
        $this->Effective_Date = new DB_Field("Effective_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Thru_date = new DB_Field("Thru_date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Note = new DB_Field("Note", "", new DbStrSanitizer(245));
        $this->Date_Added = new DB_Field("Date_Added", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}

class EmergContactRs extends TableRS {

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

class Name_GuestRS extends TableRS {

    public $idName;   // int(11) NOT NULL,
    public $idPsg;   // int(11) NOT NULL,
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Legal_Custody;  // int(11) NOT NULL DEFAULT '0',
    public $Relationship_Code;   // varchar(5) NOT NULL DEFAULT '',
    public $Type;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "name_guest") {

        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Legal_Custody = new DB_Field("Legal_Custody", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Relationship_Code = new DB_Field("Relationship_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(45), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}

class Name_LanguageRS extends TableRS {

    public $idName;   // int(11) NOT NULL,
    public $Language_Id;   // int(11) NOT NULL,
    public $Mother_Tongue;   // INT(1) NOT NULL DEFAULT 0,
    public $Proficiency;   // varchar(4) NOT NULL DEFAULT '',

    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "name_language") {

        $this->Language_Id = new DB_Field("Language_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Mother_Tongue = new DB_Field("Mother_Tongue", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Proficiency = new DB_Field("Proficiency", "", new DbStrSanitizer(4), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}

class Name_InsuranceRS extends TableRS {

    public $idName;   // int(11) NOT NULL,
    public $Insurance_Id;   // int(11) NOT NULL,
    public $Primary;   // INT(1) NOT NULL DEFAULT 0,
    public $Status;  // varchar(4) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',

    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "name_insurance") {

        $this->Insurance_Id = new DB_Field("Insurance_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Primary = new DB_Field("Primary", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}


