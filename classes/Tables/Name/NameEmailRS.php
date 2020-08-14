<?php
namespace HHK\ Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * NameEmailRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NameEmailRS extends AbstractTableRS {
    
    public $idName;  // int(11) NOT NULL,
    public $Purpose;  // varchar(25) NOT NULL DEFAULT '',
    public $Email;  // varchar(140) NOT NULL DEFAULT '',
    public $Bad_Address;  // varchar(15) NOT NULL DEFAULT '' COMMENT '				',
    public $Last_Verified;  // date DEFAULT NULL,
    public $Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Last_Updated;  // date DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "name_email") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Purpose = new DB_Field("Purpose", "", new DbStrSanitizer(25));
        $this->Email = new DB_Field("Email", "", new DbStrSanitizer(140), TRUE, TRUE);
        $this->Bad_Address = new DB_Field("Bad_Address", "", new DbStrSanitizer(15));
        $this->Last_Verified = new DB_Field("Last_Verified", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>