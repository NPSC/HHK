<?php
namespace HHK\Tables\Reservation;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer};

/**
 * Reservation_VehicleRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Reservation_VehicleRS extends AbstractTableRS {

    public DB_Field $idReservation;  // INT(11) NOT NULL,
    public DB_Field $idVehicle;  // INT(11) NOT NULL,
    public DB_Field $idName;  // INT(11) NOT NULL,

    function __construct($TableName = "reservation_vehicle") {

        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idVehicle = new DB_Field("idVehicle", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
