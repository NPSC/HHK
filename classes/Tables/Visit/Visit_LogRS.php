<?php
namespace Tables\Visit;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Visit_LogRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Visit_LogRS extends AbstractTableRS {
    
    public $Log_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Sub_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $User_Name;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $idName;  // INT NOT NULL DEFAULT 0 ,
    public $idPsg;  // INT NOT NULL DEFAULT 0 ,
    public $idRegistration;  // INT NOT NULL DEFAULT 0 ,
    public $idVisit;  // INT NOT NULL DEFAULT 0 ,
    public $Span;  // INT NOT NULL DEFAULT 0 ,
    public $idStay;  // INT NOT NULL DEFAULT 0 ,
    public $idRr;  // int(11) NOT NULL DEFAULT '0',
    public $Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Log_Text;  // VARCHAR(5000) NOT NULL DEFAULT '' ,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()
    
    
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