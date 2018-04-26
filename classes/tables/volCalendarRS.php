<?php
/**
 * volCalendar.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Volunteer_HoursRS extends TableRS {

    public $idVolunteer_hours;  // INT NOT NULL AUTO_INCREMENT ,
    public $idmcalendar;  // int(11) NOT NULL DEFAULT '0',
    public $idName;  // INT NOT NULL ,
    public $idName2;  // INT NOT NULL ,
    public $idCompany;  // INT NOT NULL DEFAULT 0 ,
    public $Org;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Hours;  // DECIMAL(10,3) NOT NULL DEFAULT 0.0 ,
    public $Start;  // DATETIME NULL ,
    public $End;  // DATETIME NULL ,
    public $Logged_By;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Date_Logged;  // DATETIME NULL ,
    public $Verified_By;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Date_Verified;  // DATETIME NULL ,
    public $Vol_Category;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Vol_Code;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Status;  // VARCHAR(5) NOT NULL DEFAULT '' ,
    public $Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $idHouse;  // INT NOT NULL DEFAULT 0 ,
    public $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Last_Updated;  // DATETIME NULL ,
    public $Timestamp;  // TIMESTAMP NULL DEFAULT now() ,

    function __construct($TableName = 'volunteer_hours') {

        $this->idVolunteer_hours = new DB_Field("idVolunteer_hours", 0, new DbIntSanitizer());
        $this->idmcalendar = new DB_Field("idmcalendar", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->idName2 = new DB_Field("idName2", 0, new DbIntSanitizer());
        $this->idCompany = new DB_Field("idCompany", 0, new DbIntSanitizer());
        $this->Org = new DB_Field("Org", "", new DbStrSanitizer(45));
        $this->Hours = new DB_Field("Hours", 0.0, new DbDecimalSanitizer());
        $this->Start = new DB_Field("E_Start", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->End = new DB_Field("E_End", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Logged_By = new DB_Field("Logged_By", "", new DbStrSanitizer(45));
        $this->Date_Logged = new DB_Field("Date_Logged", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Verified_By = new DB_Field("Verified_By", "", new DbStrSanitizer(45));
        $this->Date_Verified = new DB_Field("Date_Verified", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Vol_Category = new DB_Field("E_Vol_Category", "", new DbStrSanitizer(45));
        $this->Vol_Code = new DB_Field("E_Vol_Code", "", new DbStrSanitizer(45));
        $this->Status = new DB_Field("E_Status", "", new DbStrSanitizer(5));
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(45));
        $this->idHouse = new DB_Field("idHouse", 0, new DbIntSanitizer());

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class McalendarRS extends TableRS {

    public $idmcalendar;  // int(11) NOT NULL AUTO_INCREMENT,
    public $E_Id;  // varchar(100) NOT NULL,
    public $idName;  // int(11) NOT NULL DEFAULT '0',
    public $idName2;  // int(11) NOT NULL DEFAULT '0',
    public $E_Title;  // varchar(45) NOT NULL DEFAULT '',
    public $E_Start;  // datetime NOT NULL,
    public $E_End;  // datetime DEFAULT NULL,
    public $E_URL;  // varchar(145) NOT NULL DEFAULT '',
    public $E_ClassName;  // varchar(45) NOT NULL DEFAULT '',
    public $E_Editable;  // bit(1) NOT NULL DEFAULT b'0',
    public $E_Source;  // varchar(244) NOT NULL DEFAULT '',
    public $E_Description;  // text,
    public $E_AllDay;  // bit(1) NOT NULL DEFAULT b'0',
    public $E_Vol_Category;  // varchar(45) NOT NULL DEFAULT '',
    public $E_Vol_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $E_Status;  // varchar(4) NOT NULL DEFAULT '',
    public $E_Take_Overable;  // bit(1) NOT NULL DEFAULT b'0',
    public $E_Fixed_In_Time;  // bit(1) NOT NULL DEFAULT b'0',
    public $E_Shell;  // bit(1) NOT NULL DEFAULT b'0',
    public $E_Locked;  // bit(1) NOT NULL DEFAULT b'0',
    public $E_Shell_Id;  // int(11) NOT NULL DEFAULT '0',
    public $E_Rpt_Id;
    public $E_Show_All;

    public $Updated_By;
    public $Last_Updated;
    public $Timestamp;

    function __construct($TableName = "mcalendar") {

        $this->idmcalendar = new DB_Field("idmcalendar", 0, new DbIntSanitizer());
        $this->E_Id = new DB_Field("E_Id", "", new DbStrSanitizer(100));
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->idName2 = new DB_Field("idName2", 0, new DbIntSanitizer());
        $this->E_Title = new DB_Field("E_Title", "", new DbStrSanitizer(45));
        $this->E_Start = new DB_Field("E_Start", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->E_End = new DB_Field("E_End", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->E_URL = new DB_Field("E_URL", "", new DbStrSanitizer(145));
        $this->E_ClassName = new DB_Field("E_ClassName", "", new DbStrSanitizer(45));
        $this->E_Editable = new DB_Field("E_Editable", 0, new DbBitSanitizer());
        $this->E_Source = new DB_Field("E_Source", "", new DbStrSanitizer(244));
        $this->E_Description = new DB_Field("E_Description", "", new DbStrSanitizer(1000));
        $this->E_AllDay = new DB_Field("E_AllDay", 0, new DbBitSanitizer());
        $this->E_Vol_Category = new DB_Field("E_Vol_Category", "", new DbStrSanitizer(45));
        $this->E_Vol_Code = new DB_Field("E_Vol_Code", "", new DbStrSanitizer(45));
        $this->E_Status = new DB_Field("E_Status", "", new DbStrSanitizer(5));
        $this->E_Take_Overable = new DB_Field("E_Take_Overable", 0, new DbBitSanitizer());
        $this->E_Fixed_In_Time = new DB_Field("E_Fixed_In_Time", 0, new DbBitSanitizer());
        $this->E_Shell = new DB_Field("E_Shell", 0, new DbBitSanitizer());
        $this->E_Locked = new DB_Field("E_Locked", 0, new DbBitSanitizer());
        $this->E_Shell_Id = new DB_Field("E_Shell_Id", 0, new DbIntSanitizer());
        $this->E_Rpt_Id = new DB_Field("E_Rpt_Id", 0, new DbIntSanitizer());
        $this->E_Show_All = new DB_Field("E_Show_All", 0, new DbIntSanitizer());

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}

class ShellEventsRS extends TableRS {

    public $idShell;  // INT NOT NULL AUTO_INCREMENT ,

    public $Title;  //  varchar(45) NOT NULL DEFAULT '',
    public $Description;  //  varchar(145) NOT NULL DEFAULT '',
    public $Vol_Cat;  //  varchar(45) NOT NULL DEFAULT '',
    public $Vol_Code;  //  varchar(45) NOT NULL DEFAULT '',
    public $Time_Start;  //  time DEFAULT NULL,
    public $Time_End;  //  time DEFAULT NULL,
    public $Date_Start;  //  date DEFAULT NULL,
    public $Duration_Code;  //  varchar(4) NOT NULL DEFAULT '',
    public $Sun;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Mon;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Tue;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Wed;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Thu;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Fri;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Sat;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Skip_Holidays;  //  bit(1) NOT NULL DEFAULT b'0',
    public $AllDay;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Class_Name;  //  varchar(45) NOT NULL DEFAULT '',
    public $URL;  //  varchar(145) NOT NULL DEFAULT '',
    public $Status;  //  varchar(4) NOT NULL DEFAULT '',
    public $Shell_Color;  //  varchar(45) NOT NULL DEFAULT '',
    public $Fixed_In_Time;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Take_Overable;  //  bit(1) NOT NULL DEFAULT b'0',
    public $Locked;  //  bit(1) NOT NULL DEFAULT b'0',

    function __construct($TableName = 'shell_events') {

        $this->idShell = new DB_Field("idShell", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45));
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(145));
        $this->Vol_Cat = new DB_Field("Vol_Cat", "", new DbStrSanitizer(45));
        $this->Vol_Code = new DB_Field("Vol_Code", "", new DbStrSanitizer(45));
        $this->Time_Start = new DB_Field("Time_Start", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Time_End = new DB_Field("Time_End", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Date_Start = new DB_Field("Date_Start", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Duration_Code = new DB_Field("Duration_Code", "", new DbStrSanitizer(4));

        $this->Sun = new DB_Field("Sun", 0, new DbBitSanitizer());
        $this->Mon = new DB_Field("Mon", 0, new DbBitSanitizer());
        $this->Tue = new DB_Field("Tue", 0, new DbBitSanitizer());
        $this->Wed = new DB_Field("Wed", 0, new DbBitSanitizer());
        $this->Thu = new DB_Field("Thu", 0, new DbBitSanitizer());
        $this->Fri = new DB_Field("Fri", 0, new DbBitSanitizer());
        $this->Sat = new DB_Field("Sat", 0, new DbBitSanitizer());

        $this->Skip_Holidays = new DB_Field("Skip_Holidays", 0, new DbBitSanitizer());
        $this->AllDay = new DB_Field("AllDay", 0, new DbBitSanitizer());
        $this->Class_Name = new DB_Field("Class_Name", "", new DbStrSanitizer(45));
        $this->URL = new DB_Field("URL", "", new DbStrSanitizer(145));
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4));
        $this->Shell_Color = new DB_Field("Shell_Color", "", new DbStrSanitizer(45));
        $this->Take_Overable = new DB_Field("Take_Overable", 0, new DbBitSanitizer());
        $this->Fixed_In_Time = new DB_Field("Fixed_In_Time", 0, new DbBitSanitizer());
        $this->Locked = new DB_Field("Locked", 0, new DbBitSanitizer());


        parent::__construct($TableName);
    }

}