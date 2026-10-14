<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/**
 * W_group_ipRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_group_ipRS extends AbstractTableRS {

    public DB_Field $id;  // INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    public DB_Field $Group_Code;  // VARCHAR(5) NOT NULL DEFAULT '',
    public DB_Field $IP_addr;  // VARCHAR(45) NOT NULL DEFAULT '',

    function __construct($TableName = "w_group_ip") {

        $this->id = new DB_Field("id", 0, new DbIntSanitizer());
        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->IP_addr = new DB_Field("IP_addr", "", new DbStrSanitizer(45), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
