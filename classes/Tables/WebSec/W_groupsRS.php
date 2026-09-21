<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * W_groupsRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_groupsRS extends AbstractTableRS {
    
    public DB_Field $Group_Code;  // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Title;  // varchar(45) NOT NULL DEFAULT '',
    public DB_Field $Description;  // varchar(255) NOT NULL DEFAULT '',
    public DB_Field $Default_Access_Level;  // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Max_Level;  // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Min_Access_Level;  // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Cookie_Restricted;  // bit(1) NOT NULL DEFAULT b'0',
    public DB_Field $IP_Restricted; // tinyint NOT NULL DEFAULT 0,
    public DB_Field $Password_Policy;  // varchar(45) NOT NULL DEFAULT '',
    public DB_Field $Last_Updated;  // datetime DEFAULT NULL,
    public DB_Field $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public DB_Field $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "w_groups") {
        
        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(5));
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Default_Access_Level = new DB_Field("Default_Access_Level", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Max_Level = new DB_Field("Max_Level", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Min_Access_Level = new DB_Field("Min_Access_Level", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Cookie_Restricted = new DB_Field("Cookie_Restricted", "", new DbBitSanitizer(), TRUE, TRUE);
        $this->IP_Restricted = new DB_Field("IP_Restricted", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Password_Policy = new DB_Field("Password_Policy", "", new DbStrSanitizer(5), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>