<?php
namespace HHK\ Tables\Visit;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * VisitRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *  VisitRS
 */
class VisitRS extends AbstractTableRS {

    public $idVisit;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Span;   // INT NOT NULL ,
    public $idRegistration;   // INT NOT NULL ,
    public $idReservation;   // INT NOT NULL ,
    public $idResource;   // INT NOT NULL ,
    public $idPrimaryGuest;  // int(11) NOT NULL DEFAULT '0',
    public $idHospital_stay;  // int(11) NOT NULL DEFAULT '0',
    public $Arrival_Date;   // DATETIME NULL DEFAULT NULL ,
    public $Title;
    public $Key_Deposit;   // DECIMAL(10,2) NOT NULL DEFAULT '0.00' ,
    public $Expected_Departure;   // DATETIME NULL DEFAULT NULL ,
    public $Actual_Departure;   // DATETIME NULL DEFAULT NULL ,
    public $Return_Date;   // DATETIME NULL DEFAULT NULL ,
    public $Key_Dep_Disposition;   // VARCHAR(4) NOT NULL DEFAULT '' ,
    public $DepositPayType;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Ext_Phone_Installed;   // INT(1) NOT NULL DEFAULT 0 ,
    public $Medical_Cooler;   // INT(1) NOT NULL DEFAULT '',
    public $Wheel_Chair;   // INT(1) NOT NULL DEFAULT '',
    public $OverRideMaxOcc;  // int(1) NOT NULL DEFAULT '0',
    public $Span_Start;  // datetime DEFAULT NULL,
    public $Span_End;  // datetime DEFAULT NULL,
    public $Expected_Rate;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Pledged_Rate;   // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Amount_Per_Guest;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idRoom_rate;  // int(11) NOT NULL DEFAULT '0',
    public $Rate_Category;  // varchar(5) NOT NULL DEFAULT '',
    public $Rate_Glide_Credit;  // int(11) not null default '0',
    public $Notes;   // TEXT NULL DEFAULT NULL ,
    public $Status;   // VARCHAR(5) NOT NULL DEFAULT '' ,
    public $Updated_By;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Last_Updated;   // DATETIME NULL DEFAULT NULL ,
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,

    function __construct($TableName = "visit") {

        $this->idVisit = new DB_Field("idVisit", 0, new DbIntSanitizer());
        $this->Span = new DB_Field("Span", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idResource = new DB_Field("idResource", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPrimaryGuest = new DB_Field("idPrimaryGuest", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idHospital_stay = new DB_Field("idHospital_stay", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Arrival_Date = new DB_Field("Arrival_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Key_Deposit = new DB_Field("Key_Deposit", "0.00", new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Expected_Rate = new DB_Field("Expected_Rate", "0.00", new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Pledged_Rate = new DB_Field("Pledged_Rate", "0.00", new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Amount_Per_Guest = new DB_Field("Amount_Per_Guest", "0.00", new DbDecimalSanitizer(), TRUE, TRUE);
        $this->idRoom_rate = new DB_Field('idRoom_Rate', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Expected_Departure = new DB_Field("Expected_Departure", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Rate_Category = new DB_Field("Rate_Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Rate_Glide_Credit = new DB_Field("Rate_Glide_Credit", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->OverRideMaxOcc = new DB_Field("OverRideMaxOcc", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Actual_Departure = new DB_Field("Actual_Departure", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Span_Start = new DB_Field("Span_Start", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Span_End = new DB_Field("Span_End", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Return_Date = new DB_Field("Return_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Key_Dep_Disposition = new DB_Field("Key_Dep_Disposition", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->DepositPayType = new DB_Field("DepositPayType", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Ext_Phone_Installed = new DB_Field("Ext_Phone_Installed", 0, new DbIntSanitizer());
        $this->Medical_Cooler = new DB_Field("Medical_Cooler", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Wheel_Chair = new DB_Field("Wheel_Chair", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(15000), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
?>