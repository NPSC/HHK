<?php
namespace HHK\Tables\Reservation;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Reservation_LogRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Reservation_LogRS extends AbstractTableRS {
    
    public DB_Field $Log_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $Sub_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $User_Name;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $idName;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idPsg;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idRegistration;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idHospital;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $idAgent;  // int(11) DEFAULT '0',
    public DB_Field $idHospital_stay;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $idReservation;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $idSpan;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $idRoom_rate;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $idResource;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $Log_Text;  // VARCHAR(5000) NOT NULL DEFAULT '' ,
    public DB_Field $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()
    
    function __construct($TableName = "reservation_log") {
        
        $this->Log_Type = new DB_Field("Log_Type", "", new DbStrSanitizer(45));
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45));
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(45));
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer());
        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer());
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer());
        $this->idSpan = new DB_Field("idSpan", 0, new DbIntSanitizer());
        $this->idHospital = new DB_Field("idHospital", 0, new DbIntSanitizer());
        $this->idAgent = new DB_Field("idAgent", 0, new DbIntSanitizer());
        $this->idHospital_stay = new DB_Field("idHospital_stay", 0, new DbIntSanitizer());
        $this->idRoom_rate = new DB_Field("idRoom_rate", 0, new DbIntSanitizer());
        $this->idResource = new DB_Field("idResource", 0, new DbIntSanitizer());
        $this->Log_Text = new DB_Field("Log_Text", "", new DbStrSanitizer(5000));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
    }
    
}
?>