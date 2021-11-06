<?php
namespace HHK\Tables\Reservation;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * ReservationRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReservationRS extends AbstractTableRS {

    public $idReservation;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idRegistration;   // int(11) NOT NULL DEFAULT '0',
    public $idGuest;  // int(11) NOT NULL DEFAULT '0',
    public $idHospital_Stay;   // int(11) NOT NULL DEFAULT '0',
    public $idResource;   // int(11) NOT NULL DEFAULT '0',
    public $idReferralDoc;   // int(11) NOT NULL DEFAULT '0',
    public $Resource_Suitable;  // VARCHAR(4) NOT NULL DEFAULT '',
    public $Confirmation;  // varchar(4) NOT NULL DEFALUT '',
    public $Room_Rate_Category;  // VARCHAR(4)
    public $Fixed_Room_Rate;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Rate_Adjust;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idRateAdjust; // varchar(5) NOT NULL DEFAULT '0',
    public $Visit_Fee;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $idRoom_rate;  // int(11) NOT NULL DEFAULT '0',
    public $Title;   // varchar(145) NOT NULL DEFAULT '',
    public $Type;  // varchar(45) NOT NULL DEFAULT '',
    public $Expected_Pay_Type;  // varchar(4) NOT NULL DEFALUT '',
    public $Expected_Arrival;   // datetime DEFAULT NULL,
    public $Expected_Departure;   // datetime DEFAULT NULL,
    public $Actual_Arrival;   // datetime DEFAULT NULL,
    public $Actual_Departure;   // datetime DEFAULT NULL,
    public $Number_Guests;   // int(11) NOT NULL DEFAULT '0',
    public $Add_Room; //`Add_Room` INT NOT NULL DEFAULT 0
    public $Notes;   // text,
    public $Checkin_Notes;
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = 'reservation') {
        $this->idReservation = new DB_Field('idReservation', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idRegistration = new DB_Field('idRegistration', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGuest = new DB_Field('idGuest', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idResource = new DB_Field('idResource', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idReferralDoc = new DB_Field('idReferralDoc', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idHospital_Stay = new DB_Field('idHospital_Stay', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Resource_Suitable = new DB_Field('Resource_Suitable', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Confirmation = new DB_Field('Confirmation', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Room_Rate_Category = new DB_Field('Room_Rate_Category', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Fixed_Room_Rate = new DB_Field('Fixed_Room_Rate', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Rate_Adjust = new DB_Field('Rate_Adjust', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->idRateAdjust = new DB_Field('idRateAdjust', '0', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Visit_Fee = new DB_Field('Visit_Fee', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->idRoom_rate = new DB_Field('idRoom_rate', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(145), TRUE, TRUE);
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Expected_Pay_Type = new DB_Field('Expected_Pay_Type', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Expected_Arrival = new DB_Field('Expected_Arrival', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Expected_Departure = new DB_Field('Expected_Departure', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Actual_Arrival = new DB_Field('Actual_Arrival', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Actual_Departure = new DB_Field('Actual_Departure', NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Number_Guests = new DB_Field('Number_Guests', 1, new DbIntSanitizer(), TRUE, TRUE);
        $this->Add_Room = new DB_Field('Add_Room', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Checkin_Notes = new DB_Field('Checkin_Notes', '', new DbStrSanitizer(1000), TRUE, TRUE);
        $this->Notes = new DB_Field('Notes', '', new DbStrSanitizer(2000), TRUE, TRUE);
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(5), TRUE, TRUE);

        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field('Last_Updated', null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field('Timestamp', null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
?>