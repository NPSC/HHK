<?php
namespace HHK\Tables\Visit;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * StaysRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class StaysRS extends AbstractTableRS {
    
    public $idStays;    // INT NOT NULL AUTO_INCREMENT ,
    public $idVisit;    // INT NOT NULL ,
    public $Visit_Span;    // int(11) NOT NULL,
    public $idRoom;   // INT NOT NULL ,
    public $idName;    // INT NOT NULL ,
    public $Checkin_Date;    // DATETIME NULL ,
    public $Checkout_Date;    // DATETIME NULL ,
    public $Expected_Co_Date;  // DATETIME NULL,
    public $Span_Start_Date;  // datetime DEFAULT NULL,
    public $Span_End_Date;  // datetime DEFAULT NULL,
    public $Activity_Id;    // INT NOT NULL DEFAULT 0 ,
    public $On_Leave;  // INT NOT NULL DEFAULT 0
    public $Status;    // VARCHAR(5) NOT NULL DEFAULT '' ,
    public $Updated_By;    // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Last_Updated;    // DATETIME NULL ,
    public $Timestamp;    // TIMESTAMP NOT NULL DEFAULT  CURRENT_TIMESTAMP ,
    
    function __construct($TableName = "stays") {
        
        $this->idStays = new DB_Field("idStays", 0, new DbIntSanitizer());
        $this->idVisit = new DB_Field("idVisit", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Visit_Span = new DB_Field("Visit_Span", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idRoom = new DB_Field("idRoom", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Checkin_Date = new DB_Field("Checkin_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Checkout_Date = new DB_Field("Checkout_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Expected_Co_Date = new DB_Field("Expected_Co_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Span_Start_Date = new DB_Field("Span_Start_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Span_End_Date = new DB_Field("Span_End_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Activity_Id = new DB_Field("Activity_Id", 0, new DbIntSanitizer());
        $this->On_Leave = new DB_Field("On_Leave", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>