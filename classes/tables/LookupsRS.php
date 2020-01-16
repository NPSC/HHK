<?php
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
class LookupsRS extends TableRS {

    public $Category;   // varchar(45) NOT NULL,
    public $Code;   // varchar(45) NOT NULL DEFAULT '',
    public $Title;   // varchar(255) NOT NULL DEFAULT '',
    public $Use;   // varchar(2) NOT NULL DEFAULT '',
    public $Show;  // varchar(4) NOT NULL DEFAULT '',
    public $Type;  // varchar(255) NOT NULL DEFAULT '',
    public $Other; // varchar(255) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NULL DEFAULT CURRENT_TIMESTAMP,


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
