<?php
namespace HHK\ Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};
use HHK\Tables\Fields\DbBitSanitizer;

/**
 * NameDemogRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NameDemogRS extends AbstractTableRS {
    
    public $idName;  // int(11) NOT NULL,
    public $Gen_Notes;  // text,
    public $Contact_Date;  // date DEFAULT NULL,
    public $Orientation_Date;  // date DEFAULT NULL,
    public $Confirmed_Date;  // DATETIME NULL DEFAULT NULL
    public $Age_Bracket;
    public $Income_Bracket;
    public $Education_Level;  // varchar(5) NOT NULL DEFAULT '',
    public $Newsletter;
    public $Photo_Permission;
    public $Guest_Photo_Id;
    public $No_Return;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Ethnicity;  // varchar(5) NOT NULL DEFAULT '',
    public $Media_Source;  // varchar(5) NOT NULL DEFAULT '',
    public $Special_Needs;  // varchar(5) NOT NULL DEFAULT '',
    public $Gl_Code_Debit;  // VARCHAR(25) NOT NULL DEFAULT ''
    public $Gl_Code_Credit;  // VARCHAR(25) NOT NULL DEFAULT ''
    public $Tax_Exempt; // TINYINT(1) NOT NULL DEFAULT 0
    public $Background_Check_Date;
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "name_demog") {
        
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Newsletter = new DB_Field("Newsletter", '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Photo_Permission = new DB_Field("Photo_Permission", '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Guest_Photo_Id = new DB_Field("Guest_Photo_Id", 0, new DbIntSanitizer());
        $this->Gen_Notes = new DB_Field("Gen_Notes", "", new DbStrSanitizer(5000), TRUE, TRUE);
        $this->Contact_Date = new DB_Field("Contact_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE);
        $this->Orientation_Date = new DB_Field("Orientation_Date", NULL, new DbDateSanitizer("Y-m-d"), TRUE);
        $this->Confirmed_Date = new DB_Field("Confirmed_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE);
        $this->Age_Bracket = new DB_Field('Age_Bracket', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->No_Return = new DB_Field('No_Return', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Income_Bracket = new DB_Field('Income_Bracket', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Education_Level = new DB_Field('Education_Level', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Ethnicity = new DB_Field('Ethnicity', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Media_Source = new DB_Field('Media_Source', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Special_Needs = new DB_Field('Special_Needs', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Gl_Code_Debit = new DB_Field('Gl_Code_Debit', '', new DbStrSanitizer(25), TRUE, TRUE);
        $this->Gl_Code_Credit = new DB_Field('Gl_Code_Credit', '', new DbStrSanitizer(25), TRUE, TRUE);
        $this->Tax_Exempt = new DB_Field('tax_exempt', 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Background_Check_Date = new DB_Field('Background_Check_Date', NULL, new DbDateSanitizer("Y-m-d"), TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
    }
}
?>