<?php
namespace HHK\ Tables\Reservation;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Fin_ApplicationRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Fin_ApplicationRS extends AbstractTableRS {
    
    public $idFin_application;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idReservation;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idRegistration;  // int(11) NOT NULL DEFAULT '0',
    public $idGuest;  // int(11) NOT NULL DEFAULT '0',
    public $Monthly_Income;  // int(11) NOT NULL DEFAULT '0',
    public $HH_Size;  // int(11) NOT NULL DEFAULT '0',
    public $FA_Category;  // varchar(5) NOT NULL DEFAULT '',
    public $Est_Amount;  // int(11) NOT NULL DEFAULT '0',
    public $Estimated_Arrival;  // datetime DEFAULT NULL,
    public $Estimated_Departure;  // datetime DEFAULT NULL,
    public $Approved_Id;  // varchar(45) NOT NULL DEFAULT '',
    public $FA_Applied;   // varchar(2) NOT NULL DEFAULT '',
    public $FA_Applied_Date;   // datetime DEFAULT NULL,
    public $FA_Status;   // varchar(5) NOT NULL DEFAULT '',
    public $FA_Status_Date;   // datetime DEFAULT NULL,
    public $FA_Reason;   // varchar(445) NOT NULL DEFAULT '',
    public $Notes;   // text,
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "fin_application") {
        
        $this->idFin_application = new DB_Field("idFin_application", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGuest = new DB_Field("idGuest", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Monthly_Income = new DB_Field("Monthly_Income", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->HH_Size = new DB_Field("HH_Size", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->FA_Category = new DB_Field("FA_Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Est_Amount = new DB_Field("Est_Amount", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Estimated_Arrival = new DB_Field("Estimated_Arrival", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Estimated_Departure = new DB_Field("Estimated_Departure", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Approved_Id = new DB_Field("Approved_Id", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->FA_Applied = new DB_Field("FA_Applied", "", new DbStrSanitizer(2), TRUE, TRUE);
        $this->FA_Applied_Date = new DB_Field("FA_Applied_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->FA_Status = new DB_Field("FA_Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->FA_Status_Date = new DB_Field("FA_Status_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->FA_Reason = new DB_Field("FA_Reason", "", new DbStrSanitizer(445), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>