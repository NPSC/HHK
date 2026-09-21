<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbDateSanitizer, DbStrSanitizer};

/**
 * W_usersRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_usersRS extends AbstractTableRS {

    public DB_Field $idName;  // int(11) NOT NULL,
    public DB_Field $User_Name;  // varchar(100) NOT NULL DEFAULT '',
    public DB_Field $Enc_PW;  // varchar(100) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    public DB_Field $idIdp;  // int(11) NOT NULL DEFAULT 0,
    public DB_Field $totpSecret; //varchar(45) NOT NULL DEFAULT '',
    public DB_Field $emailSecret; //varchar(45) NOT NULL DEFAULT '',
    public DB_Field $backupSecret; //varchar(45) NOT NULL DEFAULT '',
    public DB_Field $Certificate;  // varchar(145) NOT NULL DEFAULT '',
    //public DB_Field $Cookie;  // char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    //public DB_Field $Session;  // char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    public DB_Field $Ip;  // varchar(15) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    public DB_Field $Verify_Address;  // varchar(4) NOT NULL DEFAULT '',
    public DB_Field $Last_Login;  // datetime DEFAULT NULL,
    public DB_Field $Default_Page;  // varchar(100) NOT NULL DEFAULT '',
    public DB_Field $PW_Change_Date;  // DATETIME NULL
    public DB_Field $PW_Updated_By;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Chg_PW; // bool
    public DB_Field $Status;  // varchar(4) NOT NULL DEFAULT '',
    public DB_Field $Last_Updated;  // datetime DEFAULT NULL,
    public DB_Field $Updated_By;  // varchar(45) DEFAULT '',
    public DB_Field $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "w_users") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Enc_PW = new DB_Field("Enc_PW", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->idIdp = new DB_Field("idIdp", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->totpSecret = new DB_Field("totpSecret", '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->emailSecret = new DB_Field("emailSecret", '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->backupSecret = new DB_Field("backupSecret", '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Certificate = new DB_Field("Certificate", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Default_Page = new DB_Field("Default_Page", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Ip = new DB_Field("Ip", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Verify_Address = new DB_Field("Verify_Address", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Last_Login = new DB_Field("Last_Login", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->PW_Change_Date = new DB_Field("PW_Change_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->PW_Updated_By = new DB_Field("PW_Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Chg_PW = new DB_Field("Chg_PW", '0', new DbIntSanitizer(), TRUE, TRUE);

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>