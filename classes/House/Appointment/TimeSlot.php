<?php
namespace HHK\House\Appointment;

/*
 * Timeslot.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class TimeSlot
{

    /**
     *
     * @var array of Appointment objects inside this Timeslot
     */
    protected $appointments;
    /**
     *
     * @var int duration in minutes
     */
    protected $duration;
    /**
     *
     * @var int Maximum number of appointment objects in a TimeSlot
     */
    protected $maxAppointments;
    /**
     *
     * @var \DateTime Start time of timeslot
     */
    protected $startDT;

    /**
     *
     * @param string $startTime
     * @param int $duration
     * @param int $maxAppointments
     */
    public function __construct($startTime, $duration, $maxAppointments) {

        $this->setStartDT($startTime);
        $this->duration = intval($duration, 10);
        $this->maxAppointments = intval($maxAppointments, 10);

        // Preload appointment hangers.
        for ($a=1; $a<=$maxAppointments; $a++) {
            $this->appointments[$a] = NULL;
        }

    }

    public function fillAppointment(AbstractAppointment $appointment) {

        foreach ($this->appointments as $a) {

            if (is_null($a)) {
                $this->appointments[$appointment->getIdAppt()] = $appointment;
                return TRUE;
            }

        }

        return FALSE;
    }

    public function getOpenAppts() {

        $number = 0;

        foreach ($this->appointments as $a) {
            if (is_null($a)) {
                $number++;
            }
        }

        return $number;
    }

    /**
     * @return array of appointment objects
     */
    public function getAppointments() {

        return $this->appointments;
    }

    /**
     * @return \DateTime
     */
    public function getStartDT() {

        return $this->startDT;
    }

    /**
     * @return number
     */
    public function getDuration() {

        return $this->duration;
    }

    /**
     * @return number
     */
    public function getMaxAppointments() {

        return $this->maxAppointments;
    }

    /**
     * @param array $appointments An array of appointments.
     */
    public function setAppointments($appointments) {

        if (count($appointments) <= $this->getMaxAppointments()) {
            $this->appointments = $appointments;
        }
    }

    /**
     * @param string $startTime
     */
    protected function setStartDT($startTime) {

        $this->startDT = new \DateTime($startTime);

    }

}

