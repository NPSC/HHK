<?php
namespace Tables\Name;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Name_GuestRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Name_GuestRS extends AbstractTableRS {
    
    public $idName;   // int(11) NOT NULL,
    public $idPsg;   // int(11) NOT NULL,
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Legal_Custody;  // int(11) NOT NULL DEFAULT '0',
    public $Relationship_Code;   // varchar(5) NOT NULL DEFAULT '',
    public $Type;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "name_guest") {
        
        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Legal_Custody = new DB_Field("Legal_Custody", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Relationship_Code = new DB_Field("Relationship_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(45), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
        
    }
    
}
?>