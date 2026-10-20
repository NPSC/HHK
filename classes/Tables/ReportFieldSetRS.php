<?php
namespace HHK\Tables;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbDateSanitizer, DbIntSanitizer, DbStrSanitizer};

/**
 * ReportFieldSetRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReportFieldSetRS extends AbstractTableRS {

    public DB_Field $idFieldSet;  // INT(11) NOT NULL AUTO_INCREMENT
    public DB_Field $Title;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Report;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Fields;  // LONGTEXT
    public DB_Field $Global;  // TINYINT(4) DEFAULT '0'
    public DB_Field $Updated_by;  // VARCHAR(45) DEFAULT ''
    public DB_Field $Last_Updated;  // DATETIME DEFAULT NULL
    public DB_Field $Created_by;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Timestamp;  // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

    function __construct($TableName = "report_field_sets") {

        $this->idFieldSet = new DB_Field("idFieldSet", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Report = new DB_Field("Report", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Fields = new DB_Field("Fields", "", new DbStrSanitizer(4294967295), TRUE, TRUE);
        $this->Global = new DB_Field("Global", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Updated_by = new DB_Field("Updated_by", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Created_by = new DB_Field("Created_by", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
