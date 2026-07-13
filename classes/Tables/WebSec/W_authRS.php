<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * W_authRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_authRS extends AbstractTableRS {
    
    public DB_Field $idName;  // int(11) NOT NULL,
    public DB_Field $Role_Id;  // varchar(3) NOT NULL DEFAULT '',
    public DB_Field $Organization_Id;  // varchar(3) NOT NULL DEFAULT '',
    public DB_Field $Policy_id;  // int(11) NOT NULL DEFAULT '0',
    public DB_Field $User_Name;  // varchar(245) NOT NULL DEFAULT '',
    public DB_Field $Status;  // varchar(2) NOT NULL DEFAULT '',
    public DB_Field $Last_Updated;  // datetime DEFAULT NULL,
    public DB_Field $Updated_By;  // varchar(45) DEFAULT '',
    public DB_Field $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "w_auth") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Role_Id = new DB_Field("Role_Id", "", new DbStrSanitizer(3), TRUE, TRUE);
        $this->Organization_Id = new DB_Field("Organization_Id", "", new DbStrSanitizer(3), TRUE, TRUE);
        $this->Policy_id = new DB_Field("Policy_id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(245), TRUE, TRUE);
        
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(2), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
    
}
?>