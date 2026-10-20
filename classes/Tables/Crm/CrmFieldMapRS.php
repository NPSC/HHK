<?php
namespace HHK\Tables\Crm;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/**
 * CrmFieldMapRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CrmFieldMapRS extends AbstractTableRS {

    public DB_Field $id;  // INT NOT NULL AUTO_INCREMENT
    public DB_Field $gateway_id;  // INT NOT NULL
    public DB_Field $crm_object;  // VARCHAR(100) NOT NULL
    public DB_Field $hhk_field;  // VARCHAR(100) NOT NULL
    public DB_Field $crm_field;  // VARCHAR(100) NOT NULL
    public DB_Field $in_search;  // TINYINT(1) NOT NULL DEFAULT 0
    public DB_Field $in_export;  // TINYINT(1) NOT NULL DEFAULT 1
    public DB_Field $transform;  // VARCHAR(100) NULL DEFAULT NULL
    public DB_Field $display_order;  // INT NOT NULL DEFAULT 0

    function __construct($TableName = "crm_field_map") {

        $this->id = new DB_Field("id", 0, new DbIntSanitizer());
        $this->gateway_id = new DB_Field("gateway_id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->crm_object = new DB_Field("crm_object", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->hhk_field = new DB_Field("hhk_field", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->crm_field = new DB_Field("crm_field", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->in_search = new DB_Field("in_search", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->in_export = new DB_Field("in_export", 1, new DbIntSanitizer(), TRUE, TRUE);
        $this->transform = new DB_Field("transform", null, new DbStrSanitizer(100), TRUE, TRUE);
        $this->display_order = new DB_Field("display_order", 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
