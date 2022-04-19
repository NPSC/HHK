<?php
namespace HHK\House\Appointment;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLSelector;
use \HHK\SysConst\AppointmentType;
/*
 * AppointmentChooser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class AppointmentChooser
{

    protected $apptDate;
    protected $dayOfWeekIndex;
    protected $timeslots;
    protected $appointments;


    /**
     */
    public function __construct(\PDO $dbh, $startDate) {

        $this->setApptDate($startDate);

        // Make time slots
        $stmt = $dbh->query("select Start_ToD, End_Tod, Timeslot_Duration, Max_Ts_Appointments from appointment_template where Weekday_Index = ".$this->dayOfWeekIndex );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0) {

            $this->makeTimeslots($rows[0]['Start_ToD'], $rows[0]['End_Tod'], $rows[0]['Timeslot_Duration'], $rows[0]['Max_Ts_Appointments']);

            // Fill in Appointments
            $this->fillInAppointments($dbh);

        } else {
            // Appt Template not defined?
        }

    }

    public function createMarkup($resvId) {

        $errorMessage = '';

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Appointment', array('id'=>'hhk-roomAppttitle')));

        $tbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->makeApptSelector($this->makeApptSelectorOptions(), $this->appointments[$resvId])))
        );

        // set up error message area
        $errArray = array('class'=>'ui-state-highlight', 'id'=>'hhkApptMsg');
        if ($errorMessage == '') {
            $errArray['style'] = 'display:none;';
        }

        $errorMarkup = HTMLContainer::generateMarkup('p',
            HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-info', 'style'=>'float: left; margin-right: .3em;margin-top:1px;'))
            . $errorMessage, $errArray);


        // fieldset wrapper
        $mk1 = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Appointment Chooser', array('style'=>'font-weight:bold;'))
                . $tbl->generateMarkup(array('id'=>'tblRescList')) . $errorMarkup

                , array('class'=>'hhk-panel'))
            , array('style'=>'display: inline-block', 'class'=>'mr-3')
        );

        return $mk1;
    }


    protected function makeApptSelector($options, $optionChosen) {

        return HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, $optionChosen, FALSE), array('name'=>'selCkinAppt'));
    }

    protected function makeApptSelectorOptions() {

        $options[] = array('', '-None-');

        foreach ( $this->timeslots as $k => $t) {

            foreach ($t as $a) {
                if ($a['id'] == 0) {
                    $options[$k] = array($k, $k);
                }
            }
        }

        return $options;
    }


    protected function makeTimeslots($startTime, $endTime, $duration, $maxTsAppts) {

        $interval = new \DateInterval('PT'.$duration.'M');
        $startDT = new \DateTime($startTime);
        $endDT = new \DateTime($endTime);

        $period = new \DatePeriod($startDT, $interval, $endDT);

        foreach ($period as $dt) {

            $appts = [];

            for ($a = 1; $a <= $maxTsAppts; $a++) {
                $appts[$a] = ['id'=>0, 'tp'=>'', 'rid'=>0];
            }

            $this->timeslots[$dt->format('H:i:s')] = $appts;
        }


    }

    protected function fillInAppointments(\PDO $dbh) {

        $stmt = $dbh->query("select idAppointment, Time_Appt, Reservation_Id, `Type` from appointment where `Date_Appt` = DATE('" . $this->getApptDate()->format('Y-m_d') . "') AND `Status` = 'a';");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $this->appointments[$r['Reservation_Id']] = $r['Time_Appt'];

            $indx = 0;

            foreach ( $this->timeslots[$r['Time_Appt']] as $k => $t) {

                if ($t['id'] == 0) {
                    $indx = $k;
                    break;
                }
            }

            if ($indx > 0) {

                $this->timeslots[$r['Time_Appt']][$indx]['id'] = $r['idAppointment'];
                $this->timeslots[$r['Time_Appt']][$indx]['tp'] = $r['Type'];
                $this->timeslots[$r['Time_Appt']][$indx]['rid'] = $r['Reservation_Id'];

            } else {
                // timeslot appointments are all filled up!
            }
        }
    }


    /**
     * @return \DateTime
     */
    public function getApptDate()
    {
        return $this->apptDate;
    }

    /**
     * @return int
     */
    public function getDayOfWeekIndex()
    {
        return $this->dayOfWeekIndex;
    }

    /**
     * @return array
     */
    public function getOpenTimes()
    {
        return $this->openTimes;
    }

    /**
     * @param $apptDate
     */
    public function setApptDate($apptDate)
    {
        if (gettype($apptDate) == 'string') {
            $this->apptDate = new \DateTimeImmutable($apptDate);
        } else {
            $this->apptDate = $apptDate;
        }

        $this->setDayOfWeekIndex($this->apptDate->format('w'));

    }

    /**
     * @param int $dayOfWeekIndex
     */
    protected function setDayOfWeekIndex($dayOfWeekIndex)
    {
        $this->dayOfWeekIndex = intval($dayOfWeekIndex, 10);
    }

    /**
     * @param array $openTimes
     */
    protected function setOpenTimes(array $openTimes)
    {
        $this->openTimes = $openTimes;
    }


}

