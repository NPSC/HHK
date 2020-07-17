<?php
namespace Tables\House;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};

/**
 * House_LogRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class House_LogRS extends AbstractTableRS {

    public $Log_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Sub_Type;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $User_Name;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Id1;  // INT NOT NULL DEFAULT 0 ,
    public $Id2;  // INT NOT NULL DEFAULT 0 ,
    public $Str1;  // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Str2;  // VARCHAR(45) NOT NULL DEFAULT '' 0 ,
    public $Log_Text;  // VARCHAR(5000) NOT NULL DEFAULT '' ,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()


    function __construct($TableName = "house_log") {
        $this->Log_Type = new DB_Field("Log_Type", "", new DbStrSanitizer(45));
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45));
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(45));
        $this->Id1 = new DB_Field("Id1", 0, new DbIntSanitizer());
        $this->Id2 = new DB_Field("Id2", 0, new DbIntSanitizer());
        $this->Str1 = new DB_Field("Str1", "", new DbStrSanitizer(45));
        $this->Str2 = new DB_Field("Str2", "", new DbStrSanitizer(45));
        $this->Log_Text = new DB_Field("Log_Text", "", new DbStrSanitizer(5000));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"));

        parent::__construct($TableName);

    }

}
?>