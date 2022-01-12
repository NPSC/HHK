<?php
namespace HHK\Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Name_InsuranceRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Name_InsuranceRS extends AbstractTableRS {

    public $idName;   // int(11) NOT NULL,
    public $Insurance_Id;   // int(11) NOT NULL,
    public $Group_Num; // varchar(100) NOT NULL DEFAULT ''
    public $Member_Num; // varchar(100) NOT NULL DEFAULT ''
    public $Primary;   // INT(1) NOT NULL DEFAULT 0,
    public $Status;  // varchar(4) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',

    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "name_insurance") {

        $this->Insurance_Id = new DB_Field("Insurance_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, FALSE);
        $this->Group_Num = new DB_Field("Group_Num", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Member_Num = new DB_Field("Member_Num", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Primary = new DB_Field("Primary", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}
?>