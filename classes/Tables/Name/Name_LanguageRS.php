<?php
namespace HHK\ Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * NameLanguageRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Name_LanguageRS extends AbstractTableRS {
    
    public $idName;   // int(11) NOT NULL,
    public $Language_Id;   // int(11) NOT NULL,
    public $Mother_Tongue;   // INT(1) NOT NULL DEFAULT 0,
    public $Proficiency;   // varchar(4) NOT NULL DEFAULT '',
    
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "name_language") {
        
        $this->Language_Id = new DB_Field("Language_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Mother_Tongue = new DB_Field("Mother_Tongue", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Proficiency = new DB_Field("Proficiency", "", new DbStrSanitizer(4), TRUE, TRUE);
        
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>