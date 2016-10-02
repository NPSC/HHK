<?php
/**
 * GenLookupsRS.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of GenLookupsRS
 *
 * @author Eric
 */
class GenLookupsRS extends TableRS {

    public $Table_Name;   // varchar(45) NOT NULL,
    public $Code;   // varchar(65) NOT NULL DEFAULT '',
    public $Description;   // varchar(255) NOT NULL DEFAULT '',
    public $Substitute;   // varchar(255) NOT NULL DEFAULT '',
    public $Type;  // varchar(4) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "gen_lookups") {

        $this->Table_Name = new DB_Field("Table_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Code = new DB_Field("Code", "", new DbStrSanitizer(65), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Substitute = new DB_Field("Substitute", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }
}
