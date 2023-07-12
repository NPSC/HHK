<?php
namespace HHK\Tables\Reservation;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Reservation_MultipleRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Reservation_MultipleRS extends AbstractTableRS
{

    public $idReservation_multiple; // int(11) NOT NULL DEFAULT '0'
    public $Host_Id; // INT NOT NULL DEFAULT 0
    public $Child_Id; // varchar(2) NOT NULL DEFAULT ''
    public $Status;
    public $Timestamp; // TIMESTAMP NOT NULL DEFAULT now()

    function __construct($TableName = "reservation_multiple")
    {

        $this->idReservation_multiple = new DB_Field("idReservation_multiple", 0, new DbIntSanitizer());
        $this->Host_Id = new DB_Field("Host_Id", 0, new DbIntSanitizer());
        $this->Child_Id = new DB_Field("Child_Id", 0, new DbIntSanitizer());
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}
?>