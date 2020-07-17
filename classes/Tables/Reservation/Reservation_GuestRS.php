<?php
namespace Tables\Reservation;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Reservation_GuestRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Reservation_GuestRS extends AbstractTableRS {
    
    public $idReservation;  // int(11) NOT NULL DEFAULT '0',
    public $idGuest;  // INT NOT NULL DEFAULT 0 ,
    public $Primary_Guest;  // varchar(2) NOT NULL DEFAULT '',
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()
    
    function __construct($TableName = "reservation_guest") {
        
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer());
        $this->idGuest = new DB_Field("idGuest", 0, new DbIntSanitizer());
        $this->Primary_Guest = new DB_Field("Primary_Guest", "", new DbStrSanitizer(2));
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
    }
}
?>