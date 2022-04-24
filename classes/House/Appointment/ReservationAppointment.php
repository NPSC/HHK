<?php
namespace HHK\House\Appointment;

use HHK\SysConst\AppointmentType;
use HHK\Exception\InvalidArgumentException;

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

    public function findApppointment(\PDO $dbh, $reservationId) {

        $this->reservationId = $reservationId;

        if ($this->getReservationId() === 0) {
            return FALSE;
        }

        $stmt = $dbh->query("select * from appointment where Reservation_Id = " . $this->getReservationId());

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            return FALSE;
        }

        if ($r['Type'] != AppointmentType::Reservation) {
            throw new InvalidArgumentException('Reservation Appointment has the wrong type. ');
        }

        $this->dateAppt = $rows[0]['Date_Appt'];
        $this->duration = $rows[0]['Duration'];
        $this->timeAppt = $rows[0]['Time_Appt'];
        $this->reservationId = $rows[0]['Reservation_Id'];
        $this->idAppointment = $rows[0]['idAppointment'];
        $this->status = $rows[0]['Status'];

        return TRUE;
    }

    public function setReservationId($id) {
        $this->reservationId = intval($id);
        return $this;
    }

    public function getReservationId() {
        return $this->reservationId;
    }
}

