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

    public function setApptDateTime($apptDateTime) {

        if (gettype($apptDateTime) == 'string') {
            $this->dateAppt = new \DateTime($apptDateTime);
        } else {
            $this->dateAppt = $apptDateTime;
        }

        $this->timeAppt = $this->dateAppt->format('H:i:s');
    }

    /**
     *
     * @param string $apptTime 'HH:mm:ss'
     */
    public function updateApptTime($apptTime) {
        $this->timeAppt = $apptTime;
        $this->dateAppt = new \Datetime($this->dateAppt->format('Y-m-d') . ' ' . $this->timeAppt);
    }

    public function getApptDateTime() {
        return $this->dateAppt;
    }

    public function getDuration() {
        return $this->duration;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getType() {
        return $this->type;
    }

    public function getIdAppt() {
        return $this->idAppointment;
    }
}

