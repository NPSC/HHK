<?php
namespace HHK\Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBitSanitizer};

/**
 * NamePhoneRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NamePhoneRS extends AbstractTableRS {
    
    public $idName;  // int(11) NOT NULL,
    public $Phone_Num;  // varchar(45) NOT NULL DEFAULT '',
    public $Phone_Extension;  // varchar(15) NOT NULL DEFAULT '',
    public $Phone_Code;  // varchar(5) NOT NULL,
    public $Phone_Search;  // varchar(25) NOT NULL DEFAULT '',
    public $is_Mobile;  // bit(1) NOT NULL DEFAULT b'0',
    public $is_Toll_Free;  // bit(1) NOT NULL DEFAULT b'0',
    public $is_International;  // bit(1) NOT NULL DEFAULT b'0',
    public $Bad_Number;  // varchar(15) NOT NULL DEFAULT '',
    public $Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    
    function __construct($TableName = "name_phone") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Phone_Num = new DB_Field("Phone_Num", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Phone_Search = new DB_Field("Phone_Search", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Phone_Extension = new DB_Field("Phone_Extension", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Phone_Code = new DB_Field("Phone_Code", "", new DbStrSanitizer(5));
        $this->is_Mobile = new DB_Field("is_Mobile", 0, new DbBitSanitizer());
        $this->is_Toll_Free = new DB_Field("is_Toll_Free", 0, new DbBitSanitizer());
        $this->is_International = new DB_Field("is_International", 0, new DbBitSanitizer());
        $this->Bad_Number = new DB_Field("Bad_Number", "", new DbStrSanitizer(5));
        
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
    
}
?>