<?php
namespace HHK\ Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * RelationsRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class RelationsRS extends AbstractTableRS {
    
    public $idRelationship;  // int(11) NOT NULL AUTO_INCREMENT,
    public $idName;  // int(11) NOT NULL,
    public $Target_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Relation_Type;  // varchar(5) NOT NULL DEFAULT '',
    public $Status;  // varchar(45) NOT NULL DEFAULT '',
    public $Principal;  // bit(1) NOT NULL DEFAULT b'0',
    public $Effective_Date;  // date DEFAULT NULL,
    public $Thru_date;  // date DEFAULT NULL,
    public $Note;  // text
    public $Date_Added;  // datetime DEFAULT NULL,
    public $Group_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "relationship") {
        
        $this->idRelationship = new DB_Field("idRelationship", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Target_Id = new DB_Field("Target_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Relation_Type = new DB_Field("Relation_Type", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Principal = new DB_Field("Principal", 0, new DbBitSanitizer());
        $this->Effective_Date = new DB_Field("Effective_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Thru_date = new DB_Field("Thru_date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Note = new DB_Field("Note", "", new DbStrSanitizer(245));
        $this->Date_Added = new DB_Field("Date_Added", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
    }
}
?>