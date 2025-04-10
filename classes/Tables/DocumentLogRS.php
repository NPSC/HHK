<?php
namespace HHK\Tables;

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

class DocumentLogRS extends AbstractTableRS {
    
    public $Log_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Sub_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $User_Name;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $idDocument;  // int(11) NOT NULL DEFAULT '0',
    public $idName;  // INT NOT NULL DEFAULT 0 ,
    public $idPsg;  // INT NOT NULL DEFAULT 0 ,
    public $idReservation;  // int(11) NOT NULL DEFAULT '0',
    public $Log_Text;  // VARCHAR(5000) NOT NULL DEFAULT '' ,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()
    
    function __construct($TableName = "document_log") {
        
        $this->Log_Type = new DB_Field("Log_Type", "", new DbStrSanitizer(45));
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45));
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(100));
        $this->idDocument = new DB_Field("idDocument", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer());
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer());
        $this->Log_Text = new DB_Field("Log_Text", "", new DbStrSanitizer(5000));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
    }
    
}
?>