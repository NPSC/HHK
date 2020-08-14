<?php
namespace HHK\ Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Page_SecurityGroupRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Page_SecurityGroupRS extends AbstractTableRS {
    
    public $idPage;  // int(11) NOT NULL,
    public $Group_Code;  // varchar(5) NOT NULL,
    public $Timestamp;  // timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "page_securitygroup") {
        $this->idPage = new DB_Field("idPage", 0, new DbIntSanitizer());
        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>