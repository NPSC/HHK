<?php
namespace HHK\ Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * FbxRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class FbxRS extends AbstractTableRS {
    
    public $fb_id;  // varchar(45) NOT NULL,
    public $idName;  // int(11) NOT NULL,
    public $Status;  // varchar(2) NOT NULL DEFAULT '',
    public $fb_username;  // varchar(145) NOT NULL DEFAULT '',
    public $Approved_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Approved_Date;  // datetime DEFAULT NULL,
    public $Dropped_Date;  // datetime DEFAULT NULL,
    //    public $fb_Address_1;  // varchar(145) NOT NULL DEFAULT '',
    //    public $fb_Address_2;  // varchar(45) NOT NULL DEFAULT '',
    //    public $fb_City;  // varchar(45) NOT NULL DEFAULT '',
    //    public $fb_State;  // varchar(45) NOT NULL DEFAULT '',
    //    public $fb_Zip;  // varchar(15) NOT NULL DEFAULT '',
    public $fb_First_Name;  // varchar(45) NOT NULL DEFAULT '',
    public $fb_Last_Name;  // varchar(45) NOT NULL DEFAULT '',
    public $fb_Phone;  // varchar(25) NOT NULL DEFAULT '',
    public $fb_Email;  // varchar(145) NOT NULL DEFAULT '',
    public $PIFH_Username;  // varchar(45) NOT NULL DEFAULT '',
    public $Enc_Password;  // varchar(100) NOT NULL DEFAULT '',
    public $Access_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "fbx") {
        
        $this->fb_id = new DB_Field("fb_id", '', new DbStrSanitizer(45));
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->fb_username = new DB_Field("fb_username", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Approved_By = new DB_Field("Approved_By", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Approved_Date = new DB_Field("Approved_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Dropped_Date = new DB_Field("Dropped_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->fb_First_Name = new DB_Field("fb_First_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->fb_Last_Name = new DB_Field("fb_Last_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->fb_Phone = new DB_Field("fb_Phone", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->fb_Email = new DB_Field("fb_Email", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->PIFH_Username = new DB_Field("PIFH_Username", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Enc_Password = new DB_Field("Enc_Password", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Access_Code = new DB_Field("Access_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
    }
}
?>