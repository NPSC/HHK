<?php
namespace HHK\Tables\VolCalendar;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * volCalendar.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ShellEventsRS extends AbstractTableRS {
    
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
?>