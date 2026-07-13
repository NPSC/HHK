<?php
namespace HHK\Tables\Visit;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Visit_LogRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Visit_LogRS extends AbstractTableRS {
    
    public DB_Field $Log_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $Sub_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $User_Name;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public DB_Field $idName;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idPsg;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idRegistration;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idVisit;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $Span;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idStay;  // INT NOT NULL DEFAULT 0 ,
    public DB_Field $idRr;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $Status;  // varchar(15) NOT NULL DEFAULT '',
    public DB_Field $Log_Text;  // VARCHAR(5000) NOT NULL DEFAULT '' ,
    public DB_Field $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()
    
    
    function __construct($TableName = "visit_log") {
        
        $this->Log_Type = new DB_Field("Log_Type", "", new DbStrSanitizer(45));
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45));
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(45));
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer());
        $this->idRegistration = new DB_Field("idRegistration", 0, new DbIntSanitizer());
        $this->idVisit = new DB_Field("idVisit", 0, new DbIntSanitizer());
        $this->Span = new DB_Field("Span", 0, new DbIntSanitizer());
        $this->idStay = new DB_Field("idStay", 0, new DbIntSanitizer());
        $this->idRr = new DB_Field("idRr", 0, new DbIntSanitizer());
        $this->Status = new DB_Field("Status", '', new DbStrSanitizer(15));
        $this->Log_Text = new DB_Field("Log_Text", "", new DbStrSanitizer(5000));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
        
    }
}
?>