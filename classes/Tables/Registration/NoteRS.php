<?php
namespace HHK\Tables\Registration;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * NoteRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NoteRS extends AbstractTableRS {

    public $idNote;   // INT NOT NULL AUTO_INCREMENT,
    public $User_Name;   // VARCHAR(45) NOT NULL,
    public $Note_Type;   // VARCHAR(15) NULL,
    public $Flag; // BOOL DEFAULT false
    public $Category; //VARCHAR(15)
    public $Title;   // VARCHAR(145) NULL,
    public $Note_Text;   // TEXT NULL,
    public $Updated_By;   // VARCHAR(45) NULL,
    public $Last_Updated;   // DATETIME NULL,
    public $Status;   // VARCHAR(5) NOT NULL DEFAULT 'a',
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "note") {

        $this->idNote = new DB_Field("idNote", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Note_Type = new DB_Field("Note_Type", '', new DbStrSanitizer(15), TRUE, TRUE);
        $this->Title = new DB_Field("Title", '', new DbStrSanitizer(145), TRUE, TRUE);
        $this->Flag = new DB_Field("flag", FALSE, new DbBitSanitizer(), TRUE, TRUE);
        $this->Category = new DB_Field("Category", '', new DbStrSanitizer(15), TRUE, TRUE);
        $this->User_Name = new DB_Field("User_Name", '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field("Status", '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Note_Text = new DB_Field("Note_Text", '', new DbStrSanitizer(5000), TRUE, TRUE);

        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
?>