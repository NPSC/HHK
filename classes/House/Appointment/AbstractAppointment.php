<?php
namespace HHK\House\Appointment;

use \HHK\SysConst\AppointmentType;
use HHK\Exception\UnexpectedValueException;

/*
 * AbstractAppointment.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
abstract class AbstractAppointment {

    protected $idAppointment;
    protected $dateAppt;
    protected $timeAppt;
    protected $duration;
    protected $status;
    protected $type;
    protected $appointmentRs;



    public function __construct($idAppointment) {

        $this->idAppointment = $idAppointment;

    }

    public static function factory($appointmentType, $idAppointment) {

        switch ($appointmentType) {

            case AppointmentType::Block:

                return new BlockAppointment($idAppointment);
                break;

            case AppointmentType::Reservation:

                return new ReservationAppointment($idAppointment);
                break;

            default:
                throw new UnexpectedValueException("Appointment Type value missing or unknown: " . $appointmentType);
        }
    }

    public function hasSameDataTime() {

    }

    public function getApptDateTime() {

        if ($this->dateAppt != '' && $this->timeAppt != '') {
            return new \DateTimeImmutable($this->dateAppt . ' ' . $this->timeAppt);
        }

    }

    public function getTimeAppt() {
        return $this->timeAppt;
    }

    public function setTimeAppt($strTime) {
        $this->timeAppt = $strTime;
    }

    public function setDateAppt($strDate) {
        $this->dateAppt = $strDate;
    }

    public function getDateAppt() {
        return $this->dateAppt;
    }

    public function getDuration() {
        return $this->duration;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function getType() {
        return $this->type;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function getIdAppt() {
        return $this->idAppointment;
    }
}

