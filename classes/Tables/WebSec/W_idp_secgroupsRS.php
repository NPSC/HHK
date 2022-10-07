<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer};

/**
 * W_idp_secgroupsRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_idp_secgroupsRS extends AbstractTableRS {

    public $idIdpSecGroup;  // int(11) NOT NULL,
    public $idIdp;  // int(11) NOT NULL,
    public $idSecGroup;  // varchar(5) NOT NULL,

    function __construct($TableName = "w_idp_secgroups") {
        $this->idIdpSecGroup = new DB_Field("idIdpSecGroup", 0, new DbIntSanitizer());
        $this->idIdp = new DB_Field("idIdp", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idSecGroup = new DB_Field("idSecGroup", "", new DbStrSanitizer(5), TRUE, TRUE);
        parent::__construct($TableName);
    }
}
?>