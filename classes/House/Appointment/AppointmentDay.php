<?php
namespace HHK\House\Appointment;


use HHK\Exception\UnexpectedValueException;
use HHK\Exception\NotFoundException;

/*
 * AppointmentDay.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class AppointmentDay
{
    /**
     *
     * @var \DateTime Date for thes appointmentDay.
     */
    protected $apptDate;
    /**
     *
     * @var int
     */
    protected $dayOfWeekIndex;
    /**
     *
     * @var array of TimeSlot objects
     */
    protected $timeslots;
    /**
     *
     * @var array assigning reservations to their timeslots
     */
    protected $resvTs;
    /**
     *
     * @var array of Appointment objects that do not fit for this appointment day.
     */
    protected $overfillAppts;

    protected $errorLog;

    /**
     * Builds out the day with timeslots filled with active appointments (if any)
     *
     * @param \PDO $dbh
     * @param string|\DateTime $startDate
     * @throws NotFoundException
     */
    public function __construct(\PDO $dbh, $myDate) {

        $this->setApptDate($myDate);        // sets the appointment's date and the day of week index.
        $this->errorLog = [];
        $this->resvTs = [];
        $this->timeslots = [];
        $this->overfillAppts = [];

        // Get indicated appointment template
        $stmt = $dbh->query("select Start_ToD, End_ToD, Timeslot_Duration, Max_Ts_Appointments from appointment_template where Weekday_Index = '".$this->dayOfWeekIndex."';" );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0) {

            // Use first row
            $this->generateTimeslots($rows[0]['Start_ToD'], $rows[0]['End_ToD'], $rows[0]['Timeslot_Duration'], $rows[0]['Max_Ts_Appointments']);

            // Fill in Appointments
            $this->fillInAppointments($dbh);

        } else {
            // Appt Template not defined?
            throw new NotFoundException('Appointment Template not found for date: ' . $this->getApptDate()->format('M j, Y'));
        }

    }

    /**
     * Generate all timeslots for the day.  Uses \DatePeriod
     *
     * @param string $startTod starting time of the day
     * @param string $endTTod  ending time of day, exclusive
     * @param int $duration    duration of each timeslot
     * @param int $maxTsAppts  Maximum appointments per timeslot
     */
    protected function generateTimeslots($startTime, $endTime, $duration, $maxTsAppts) {

        $interval = new \DateInterval('PT'.$duration.'M');
        $startDT = new \DateTime($startTime);
        $endDT = new \DateTime($endTime);

        $period = new \DatePeriod($startDT, $interval, $endDT);

        foreach ($period as $dt) {

            $ts = new TimeSlot($dt->format('H:i:s'), $duration, $maxTsAppts);

            $this->timeslots[$dt->format('H:i:s')] = $ts;
        }

    }

    /**
     *
     * @param \PDO $dbh
     */
    protected function fillInAppointments(\PDO $dbh) {

        // Select appts by Type, Important: blocking type first
        $stmt = $dbh->query("SELECT
    a.idAppointment,
    a.Time_Appt,
    a.Reservation_Id,
    a.`Type`
FROM
    appointment a
		LEFT JOIN
	gen_lookups g ON g.Table_Name = 'Appointment_Type' AND g.Code = a.Type
WHERE
    a.`Date_Appt` = DATE('" . $this->getApptDate()->format('Y-m_d') . "')
	AND a.`Status` = 'a'
ORDER BY g.Order;");

        // appts for ApptDate.
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $appt = AbstractAppointment::factory($r['Type'], $r['idAppointment']);

            if (isset( $this->timeslots[$r['Time_Appt']])) {


                $ts = $this->timeslots[$r['Time_Appt']];

                if ($ts->fillAppointment($appt) == FALSE) {
                    $this->overfillAppts[$appt->getIdAppt()] = $appt;
                } else {
                    $this->resvTs[$r['Reservation_Id']] = $ts;
                }

            } else {
                // appointment timeslot is missing
                $this->overfillAppts[$appt->getIdAppt()] = $appt;
            }

        }
    }

    public function getTimeslots() {
        return $this->timeslots;
    }

    /**
     *
     * @param int $idResv
     * @return \DateTime|NULL
     */
    public function getAppointmentTime($idResv) {

        if (isset($this->resvTs[$idResv])) {

            $ts = $this->resvTs[$idResv];
            return $ts->getStartDT();
        }

        return NULL;
    }

    /**
     * @return \DateTime
     */
    public function getApptDate() {

        return $this->apptDate;
    }


    public function getOverfillAppts() {
        return $this->overfillAppts;
    }

    /**
     * @param $apptDate
     */
    protected function setApptDate($apptDate) {

        if (gettype($apptDate) == 'string') {
            $this->apptDate = new \DateTimeImmutable($apptDate);
        } else {
            $this->apptDate = $apptDate;
        }

        $this->dayOfWeekIndex = $this->apptDate->format('w');

    }

}

