<?php
namespace HHK\House\Appointment;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLSelector;
use \HHK\SysConst\AppointmentType;
use HHK\Exception\UnexpectedValueException;
use HHK\Exception\NotFoundException;

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
    protected $apptDay;
    protected $errorLog;

    public function __construct(\PDO $dbh, $date) {

        $this->apptDate = new \DateTime($date);
        $this->apptDay = new AppointmentDay($dbh, $this->apptDate);
    }

    public function saveAppointment(\PDO $dbh, $resvId, $apptTime) {

        // Find existing reservation appointment.
        $appointment = new ReservationAppointment(0);

        if ($appointment->findApppointment($dbh, $resvId)) {

            // update existing appointment record

        } else {

            // Set a new appointment
        }




    }

    public function createMarkup($resvId) {

        $errorMessage = '';

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Appointment', array('id'=>'hhk-roomAppttitle')));

        $selectedOption = '';
        if (is_null($this->apptDay->getAppointmentTime($resvId)) === FALSE) {
            $selectedOption = $this->apptDay->getAppointmentTime($resvId)->format('H:i:s');
        }

        $tbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->makeApptSelector($this->makeApptSelectorOptions(), $selectedOption)))
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
        $timeslots = $this->apptDay->getTimeslots();

        foreach ($timeslots as $ts) {

            if ($ts->getOpenAppts() > 0) {
                $options[] = array($ts->getStartDT()->format('H:i:s'), $ts->getStartDT()->format('g:ia') . ' (' . $ts->getOpenAppts() . ')');
            }
        }

        return $options;
    }



    protected function addErrorMessage($mess, $idAppointment, $timeslot = '') {

        $this->errorLog[] = ['idAppointment'=>$idAppointment, 'errorMsg'=> $mess, 'timeslot' => $timeslot];

    }
}

