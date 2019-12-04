<?php
/**
 * HouseRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class House_LogRS extends TableRS {

    public $Log_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Sub_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $User_Name;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Id1;  // INT NOT NULL DEFAULT 0 ,
    public $Id2;  // INT NOT NULL DEFAULT 0 ,
    public $Str1;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Str2;  // VARCHAR(45) NOT NULL DEFAULT '' 0 ,
    public $Log_Text;  // VARCHAR(5000) NOT NULL DEFAULT '' ,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()


    function __construct($TableName = "house_log") {
        $this->Log_Type = new DB_Field("Log_Type", "", new DbStrSanitizer(45));
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45));
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(45));
        $this->Id1 = new DB_Field("Id1", 0, new DbIntSanitizer());
        $this->Id2 = new DB_Field("Id2", 0, new DbIntSanitizer());
        $this->Str1 = new DB_Field("Str1", "", new DbStrSanitizer(45));
        $this->Str2 = new DB_Field("Str2", "", new DbStrSanitizer(45));
        $this->Log_Text = new DB_Field("Log_Text", "", new DbStrSanitizer(5000));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"));

        parent::__construct($TableName);

    }

}

class RoomRs extends TableRS {

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
//    public $Rate;   // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Rate_Code;   // varchar(5) NOT NULL DEFAULT '',
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
        $this->idHouse = new DB_Field("idHouse", 0, new DbIntSanitizer());
        $this->Item_Id = new DB_Field("Item_Id", 0, new DbIntSanitizer());
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
//        $this->Rate = new DB_Field("Rate", "", new DbStrSanitizer(15), TRUE, TRUE);
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

class Room_RateRS extends TableRS {

    public $idRoom_rate;    // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;    // varchar(45) NOT NULL DEFAULT '',
    public $Description;    // varchar(245) NOT NULL DEFAULT '',
    public $FA_Category;    // varchar(2) NOT NULL DEFAULT '',
    public $PriceModel;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Reduced_Rate_1;    // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Reduced_Rate_2;    // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Reduced_Rate_3;    // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Min_Rate;    // decimal(10,4) NOT NULL DEFAULT '0.0000',
    public $Status;    // varchar(4) NOT NULL DEFAULT '',
    public $Updated_By;    // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;    // datetime DEFAULT NULL,
    public $Timestamp;    // timestamp NULL DEFAULT NULL,

    function __construct($TableName = 'room_rate') {
        $this->idRoom_rate = new DB_Field('idRoom_rate', 0, new DbIntSanitizer());
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Description = new DB_Field('Description', '', new DbStrSanitizer(245), TRUE, TRUE);
        $this->FA_Category = new DB_Field('FA_Category', '', new DbStrSanitizer(2), TRUE, TRUE);
        $this->PriceModel = new DB_Field('PriceModel', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Reduced_Rate_1 = new DB_Field('Reduced_Rate_1', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Reduced_Rate_2 = new DB_Field('Reduced_Rate_2', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Reduced_Rate_3 = new DB_Field('Reduced_Rate_3', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Min_Rate = new DB_Field('Min_Rate', 0, new DbDecimalSanitizer(), TRUE, TRUE);

        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field('Last_Updated', NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field('Timestamp', NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        // This line stays at the end of the function.
        parent::__construct($TableName);
    }

}

class ResourceRS extends TableRS {

    public $idResource;    // int(11) NOT NULL AUTO_INCREMENT,
    public $idSponsor; // int(11) NOT NULL DEFAULT '0',
    public $Title;    // varchar(45) NOT NULL DEFAULT '',
    public $Utilization_Category;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Background_Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Text_Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Border_Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Type;    // varchar(15) NOT NULL DEFAULT '',
    public $Category;    // varchar(5) NOT NULL DEFAULT '',
    public $Partition_Size;    // varchar(5) NOT NULL DEFAULT '',
    public $Util_Priority;    // varchar(5) NOT NULL DEFAULT '',
    public $Status;    // varchar(5) NOT NULL DEFAULT '',
    public $Rate_Adjust;    // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Rate_Adjust_Code;    // varchar(15) NOT NULL DEFAULT '',
    public $Updated_By;    // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;    // datetime DEFAULT NULL,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()


    function __construct($TableName = 'resource') {
        $this->idResource = new DB_Field('idResource', 0, new DbIntSanitizer());
        $this->idSponsor = new DB_Field('idSponsor', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Utilization_Category = new DB_Field('Utilization_Category', '', new DbStrSanitizer(5));
        $this->Color = new DB_Field('Color', '', new DbStrSanitizer(15));
        $this->Background_Color = new DB_Field('Background_Color', '', new DbStrSanitizer(15));
        $this->Text_Color = new DB_Field('Text_Color', '', new DbStrSanitizer(15));
        $this->Border_Color = new DB_Field('Border_Color', '', new DbStrSanitizer(15));
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Partition_Size = new DB_Field("Partition_Size", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Util_Priority = new DB_Field("Util_Priority", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Rate_Adjust = new DB_Field("Rate_Adjust", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Rate_Adjust_Code = new DB_Field("Rate_Adjust_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE, TRUE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}

class ResourceUseRS extends TableRS {

    /**
     * @var \DB_Field
     */
    public $idResource_use;  // int(11) NOT NULL AUTO_INCREMENT,
    /**
     * @var \DB_Field
     */
    public $idResource;  // int(11) NOT NULL DEFAULT '0',
    /**
     * @var \DB_Field
     */
    public $idRoom;  // int(11) NOT NULL DEFAULT '0',
    /**
     * @var \DB_Field
     */
    public $Start_Date;  // datetime DEFAULT NULL,
    /**
     * @var \DB_Field
     */
    public $End_Date;  // datetime DEFAULT NULL,
    /**
     * @var \DB_Field
     */
    public $Status;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var \DB_Field
     */
    public $OOS_Code;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var \DB_Field
     */
    public $Unavail_Code;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var \DB_Field
     */
    public $Room_State;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var \DB_Field
     */
    public $Room_Availability;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var \DB_Field
     */
    public $Notes;  // varchar(245) NOT NULL DEFAULT '',
    /**
     * @var \DB_Field
     */
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    /**
     * @var \DB_Field
     */
    public $Last_Updated;    // datetime DEFAULT NULL,
    /**
     * @var \DB_Field
     */
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()

    function __construct($TableName = "resource_use") {
        $this->idResource_use = new DB_Field("idResource_use", 0, new DbIntSanitizer());
        $this->idResource = new DB_Field("idResource", 0, new DbIntSanitizer());
        $this->idRoom = new DB_Field("idRoom", 0, new DbIntSanitizer());
        $this->Start_Date = new DB_Field("Start_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->End_Date = new DB_Field("End_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->OOS_Code = new DB_Field("OOS_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Unavail_Code = new DB_Field("Unavail_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Room_State = new DB_Field("Room_State", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Room_Availability = new DB_Field("Room_Availability", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(245), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE, TRUE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}

class CleaningLogRS extends TableRS {

    public $idResource;   // int(11) NOT NULL DEFAULT '0',
    public $idRoom;   // int(11) NOT NULL DEFAULT '0',
    public $Type;   // varchar(45) NOT NULL DEFAULT '',
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Notes;
    public $Last_Cleaned;   // datetime DEFAULT NULL,
    public $Username;   // varchar(45) NOT NULL DEFAULT ''
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

    function __construct($TableName = 'cleaning_log') {
        $this->idResource = new DB_Field('idResource', 0, new DbIntSanitizer());
        $this->idRoom = new DB_Field('idRoom', 0, new DbIntSanitizer());
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(45));
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(5));
        $this->Notes = new DB_Field('Notes', '', new DbStrSanitizer(2000));
        $this->Username = new DB_Field('Username', '', new DbStrSanitizer(45));
        $this->Last_Cleaned = new DB_Field('Last_Cleaned', NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Timestamp = new DB_Field('Timestamp', NULL, new DbDateSanitizer("Y-m-d H:i:s"));

        // This line stays at the end of the function.
        parent::__construct($TableName);
    }
}

class LocationRS extends TableRS {

    public $idLocation;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;   // varchar(45) DEFAULT '',
    public $Description;   // varchar(245) DEFAULT '',
    public $Status;   // varchar(5) DEFAULT '',
    public $Address;   // varchar(145) NOT NULL DEFAULT '',
    public $Phone;   // varchar(45) NOT NULL DEFAULT '',
    public $Map;   // varchar(45) NOT NULL DEFAULT '',
    public $Owner_Id;   // int(11) NOT NULL DEFAULT '0',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "location") {

        $this->idLocation = new DB_Field("idLocation", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45));
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(2000));
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Address = new DB_Field("Address", "", new DbStrSanitizer(145));
        $this->Phone = new DB_Field("Phone", "", new DbStrSanitizer(45));
        $this->Map = new DB_Field("Map", "", new DbStrSanitizer(45));
        $this->Owner_Id = new DB_Field("Owner_Id", 0, new DbIntSanitizer());
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}

class Fa_CategoryRs extends TableRS {

    public $idFa_category;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idHouse;   // int(11) NOT NULL DEFAULT '0',
    public $HouseHoldSize;   // int(11) NOT NULL DEFAULT '0',
    public $Income_A;   // int(11) NOT NULL DEFAULT '0',
    public $Income_B;   // int(11) NOT NULL DEFAULT '0',
    public $Income_C;   // int(11) NOT NULL DEFAULT '0',
    public $Income_D;   // int(11) NOT NULL DEFAULT '0',
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "fa_category") {
        $this->idFa_category = new DB_Field("idFa_category", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idHouse = new DB_Field("idHouse", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->HouseHoldSize = new DB_Field("HouseHoldSize", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_A = new DB_Field("Income_A", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_B = new DB_Field("Income_B", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_C = new DB_Field("Income_C", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_D = new DB_Field("Income_D", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class Desig_HolidaysRS extends TableRS {

    public $Year;   // int(11) NOT NULL,
    public $dh1;   // date DEFAULT NULL,
    public $dh2;   // date DEFAULT NULL,
    public $dh3;   // date DEFAULT NULL,
    public $dh4;   // date DEFAULT NULL,
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,

    function __construct($TableName = "desig_holidays") {

        $this->Year = new DB_Field("Year", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->dh1 = new DB_Field("dh1", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->dh2 = new DB_Field("dh2", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->dh3 = new DB_Field("dh3", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->dh4 = new DB_Field("dh4", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}

class DocumentRS extends TableRS {

    public $idDocument;  // INT NOT NULL AUTO_INCREMENT,
    public $Title;  // VARCHAR(128) NOT NULL,
    public $Name;
    public $Category;  // VARCHAR(5) NOT NULL DEFAULT '',
    public $Type;  // VARCHAR(5) NOT NULL DEFAULT '',
    public $Folder;
    public $Language;
    public $Mime_Type;  // VARCHAR(85) NOT NULL DEFAULT '',
    public $Abstract;  // TEXT NULL,
    public $Doc;  // BLOB NULL,
    public $Status;  // VARCHAR(5) NOT NULL,
    public $Last_Updated;  // DATETIME NULL,
    public $Created_By;
    public $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '',

    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now(),

    function __construct($TableName = "document") {

        $this->idDocument = new DB_Field("idDocument", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(128), TRUE, TRUE);
        $this->Name = new DB_Field("Name", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Language = new DB_Field("Language", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Folder = new DB_Field("Folder", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Abstract = new DB_Field("Abstract", "", new DbStrSanitizer(1000), TRUE, TRUE);
        $this->Doc = new DB_Field("Doc", "", new DbBlobSanitizer(), TRUE, TRUE);
        $this->Mime_Type = new DB_Field("Mime_Type", "", new DbStrSanitizer(85), TRUE, TRUE);

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Created_By = new DB_Field("Created_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}