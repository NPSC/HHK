<?php
namespace HHK\Tables;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbBlobSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Notification_LogRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Notification_LogRS extends AbstractTableRS {

    public DB_Field $Log_Type;
    public DB_Field $Sub_Type;
    public DB_Field $username;
    public DB_Field $To;
    public DB_Field $From;
    public DB_Field $Log_Text;
    public DB_Field $Log_Details;
    public DB_Field $Timestamp;

    function __construct($TableName = "notification_log") {

        $this->Log_Type = new DB_Field("Log_Type", '', new DbStrSanitizer(45), true, true);
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45), true, true);
        $this->username = new DB_Field("username", "", new DbStrSanitizer(45), true, true);
        $this->To = new DB_Field("To", "", new DbStrSanitizer(255), true, true);
        $this->From = new DB_Field("From", "", new DbStrSanitizer(255), true, true);
        $this->Log_Text = new DB_Field("Log_Text", "", new DbStrSanitizer(255), true, true);
        $this->Log_Details = new DB_Field("Log_Details", "{}", new DbBlobSanitizer(), true, true);
        //$this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s.u"), FALSE);
        parent::__construct($TableName);

    }

}
