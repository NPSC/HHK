<?php
namespace HHK\House\Appointment;

use HHK\SysConst\AppointmentType;
use HHK\Exception\InvalidArgumentException;
use HHK\Tables\Appointment\AppointmentRS;
use HHK\Tables\EditRS;

/*
 * ReservationAppointment.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ReservationAppointment extends AbstractAppointment {


    public function __construct($idAppointment) {

        parent::__construct($idAppointment);
        $this->type = AppointmentType::Reservation;

    }

    public function findApppointment(\PDO $dbh, $reservationId) {

        $this->reservationId = $reservationId;

        if ($this->getReservationId() === 0) {
            return FALSE;
        }

        $apptRs = new AppointmentRS();
        $apptRs->Reservation_Id->setStoredVal($reservationId);

        $rows = EditRS::select($dbh, $apptRs, array($apptRs->Reservation_Id));

        if (count($rows) !== 1) {
            return FALSE;
        }

        EditRS::loadRow($rows[0], $apptRs);

        if ($apptRs->Type->getStoredVal() != AppointmentType::Reservation) {
            throw new InvalidArgumentException('Reservation Appointment has the wrong type. ');
        }

        $this->loadAppointment($apptRs);

        return TRUE;
    }

}

