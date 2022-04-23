<?php
namespace HHK\House\Appointment;

use HHK\SysConst\AppointmentType;

/*
 * ReservationAppointment.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ReservationAppointment extends AbstractAppointment {

    protected $reservationId;

    public function __construct($idAppointment) {

        parent::__construct($idAppointment);
        $this->type = AppointmentType::Reservation;

    }

    public function setReservationId($id) {
        $this->reservationId = intval($id);
        return $this;
    }

    public function getReservationId() {
        return $this->reservationId;
    }
}

