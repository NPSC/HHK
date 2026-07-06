<?php
namespace HHK\Tables;

use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbDateSanitizer};

/**
 * LookupsRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of LookupsRS
 *
 * @author Eric
 */
class LookupsRS extends AbstractTableRS {

    public DB_Field $Category;   // varchar(45) NOT NULL,
    public DB_Field $Code;   // varchar(45) NOT NULL DEFAULT '',
    public DB_Field $Title;   // varchar(255) NOT NULL DEFAULT '',
    public DB_Field $Use;   // varchar(2) NOT NULL DEFAULT '',
    public DB_Field $Show;  // varchar(4) NOT NULL DEFAULT '',
    public DB_Field $Type;  // varchar(255) NOT NULL DEFAULT '',
    public DB_Field $Other; // varchar(255) NOT NULL DEFAULT '',
    public DB_Field $Timestamp;   // timestamp NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "lookups") {

        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Code = new DB_Field("Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Use = new DB_Field("Use", "", new DbStrSanitizer(2), TRUE, TRUE);
        $this->Show = new DB_Field("Show", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Other = new DB_Field("Other", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }
}
?>