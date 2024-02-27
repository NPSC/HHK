<?php
namespace HHK\Tables\Registration;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * PSG_RS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PSG_RS
 * @package name
 * @author Eric
 */
class PSG_RS extends AbstractTableRS {

    public $idPsg;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;   // varchar(45) NOT NULL DEFAULT '',
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $idPatient;   // int(11) NOT NULL DEFAULT '0',
    public $Primany_Language;   // int(11) NOT NULL DEFAULT '0',
    public $Info_Last_Confirmed;  // DATETIME NULL DEFAULT NULL
    public $Language_Notes;   // text,
    public $Notes;   // text,
    public $Checklist_Item1;   // TINYINT(1) NOT NULL DEFAULT '0' AFTER `Notes`,
    public $Checklist_Date1;   // DATETIME NULL AFTER `Checklist_Item1`,
    public $Checklist_Item2;   // TINYINT(1) NOT NULL DEFAULT '0' AFTER `Checklist_Date1`,
    public $Checklist_Date2;   // DATETIME NULL AFTER `Checklist_Item2`,
    public $Checklist_Item3;   // TINYINT(1) NOT NULL DEFAULT '0' AFTER `Checklist_Date2`,
    public $Checklist_Date3;   // DATETIME NULL AFTER `Checklist_Item3`;

    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "psg") {

        $this->idPsg = new DB_Field("idPsg", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45));
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->idPatient = new DB_Field("idPatient", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Primany_Language = new DB_Field("Primany_Language", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Info_Last_Confirmed = new DB_Field("Info_Last_Confirmed", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Language_Notes = new DB_Field("Language_Notes", "", new DbStrSanitizer(19000), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(19000), TRUE, TRUE);
        $this->Checklist_Item1 = new DB_Field("Checklist_Item1", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Checklist_Item2 = new DB_Field("Checklist_Item2", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Checklist_Item3 = new DB_Field("Checklist_Item3", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Checklist_Date1 = new DB_Field("Checklist_Date1", 0, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Checklist_Date2 = new DB_Field("Checklist_Date2", 0, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Checklist_Date3 = new DB_Field("Checklist_Date3", 0, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>