<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * CleaningLogRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CleaningLogRS extends AbstractTableRS {

    public $idResource;   // int(11) NOT NULL DEFAULT '0',
    public $idRoom;   // int(11) NOT NULL DEFAULT '0',
    public $Type;   // varchar(45) NOT NULL DEFAULT '',
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Notes;
    public $Last_Cleaned;   // datetime DEFAULT NULL,
    public $Username;   // varchar(45) NOT NULL DEFAULT ''
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

    function __construct($TableName = 'cleaning_log') {
        $this->idResource = new DB_Field('idResource', 0, new DbIntSanitizer());
        $this->idRoom = new DB_Field('idRoom', 0, new DbIntSanitizer());
        $this->Type = new DB_Field('Type', '', new DbStrSanitizer(45));
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(5));
        $this->Notes = new DB_Field('Notes', '', new DbStrSanitizer(2000));
        $this->Username = new DB_Field('Username', '', new DbStrSanitizer(45));
        $this->Last_Cleaned = new DB_Field('Last_Cleaned', NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Timestamp = new DB_Field('Timestamp', NULL, new DbDateSanitizer("Y-m-d H:i:s"));

        // This line stays at the end of the function.
        parent::__construct($TableName);
    }
}
?>