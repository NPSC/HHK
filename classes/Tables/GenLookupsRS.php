<?php
namespace HHK\Tables;

use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbJsonSanitizer};

/**
 * GenLookupsRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of GenLookupsRS
 *
 * @author Eric
 */
class GenLookupsRS extends AbstractTableRS {

    public DB_Field $Table_Name;   // varchar(45) NOT NULL,
    public DB_Field $Code;   // varchar(65) NOT NULL DEFAULT '',
    public DB_Field $Description;   // varchar(255) NOT NULL DEFAULT '',
    public DB_Field $Substitute;   // varchar(255) NOT NULL DEFAULT '',
    public DB_Field $Attributes; //JSON NOT NULL DEFAULT '[]',
    public DB_Field $Type;  // varchar(4) NOT NULL DEFAULT '',
    public DB_Field $Order;  // INT NOT NULL DEFAULT 0
    public DB_Field $Timestamp;   // timestamp NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "gen_lookups") {

        $this->Table_Name = new DB_Field("Table_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Code = new DB_Field("Code", "", new DbStrSanitizer(65), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Substitute = new DB_Field("Substitute", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Attributes = new DB_Field("Attributes", "[]", new DbJsonSanitizer(), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Order = new DB_Field("Order", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }
}
?>