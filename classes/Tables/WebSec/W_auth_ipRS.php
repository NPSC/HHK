<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};

/**
 * W_auth_ipRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

//Lists PCs and their IP address
class W_auth_ipRS extends AbstractTableRS {
    
    public $IP_addr; // varchar(45) NOT NULL
    public $cidr; // int(2)
    public $Title; // varchar(245),
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "w_auth_ip") {
        $this->IP_addr = new DB_Field("IP_addr", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->cidr = new DB_Field("cidr", 32, new DBIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DBStrSanitizer(245), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>