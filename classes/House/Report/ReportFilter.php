<?php

namespace HHK\House\Report;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\SysConst\GLTableNames;
use HHK\sec\Labels;
use HHK\sec\Session;

/*
 * The MIT License
 *
 * Copyright 2018 Eric Crane <ecrane at nonprofitsoftwarecorp.org>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * ReportFilter.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ReportFilter {

    const DATES = 18;
    const MONTHS = 19;
    const FISCAL_YEAR = 20;
    const CAL_YEAR = 21;
    const YEAR_2_DATE = 22;

    protected $months;
    protected $calendarOptions;
    protected $selectedCalendar;
    protected $selectedMonths;
    protected $selectedYear;
    protected $selectedStart;
    protected $selectedEnd;
    protected $fyDiffMonths;

    protected $hospitals;
    protected $hList;
    protected $aList;
    protected $selectedHosptials;
    protected $selectedAssocs;

    protected $selectedResourceGroups;
    protected $resourceGroups;

    protected $reportStart;
    protected $reportEnd;

    public function __construct() {
        $this->selectedAssocs = array();
        $this->selectedHosptials = array();
        $this->selectedResourceGroups = array();
        $this->selectedMonths = array();
        $this->hospitals = array();
    }

    public function createTimePeriod($defaultYear, $defaultCalendarOption, $fiscalYearDiffMonths = 0, $omits = array()) {
        $this->months = array(
            0 => array(1, 'January'), 1 => array(2, 'February'),
            2 => array(3, 'March'), 3 => array(4, 'April'), 4 => array(5, 'May'), 5 => array(6, 'June'),
            6 => array(7, 'July'), 7 => array(8, 'August'), 8 => array(9, 'September'), 9 => array(10, 'October'), 10 => array(11, 'November'), 11 => array(12, 'December'));


        $this->calendarOptions = array(
            self::DATES => array(self::DATES, 'Dates'),
            self::MONTHS => array(self::MONTHS, 'Month'),
            self::FISCAL_YEAR => array(self::FISCAL_YEAR, 'Fiscal Year'),
            self::CAL_YEAR => array(self::CAL_YEAR, 'Calendar Year'),
            self::YEAR_2_DATE => array(self::YEAR_2_DATE, 'Year to Date')
        );


        foreach ($omits as $o) {
            unset($this->calendarOptions[$o]);
        }

        if ($fiscalYearDiffMonths == 0) {
            unset($this->calendarOptions[self::FISCAL_YEAR]);
        }

        $this->selectedYear = $defaultYear;
        $this->selectedCalendar = $defaultCalendarOption;
        $this->selectedMonths = date('m');
        $this->fyDiffMonths = $fiscalYearDiffMonths;
    }

    public function timePeriodMarkup() {

        $monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->months, $this->selectedMonths, FALSE), array('name' => 'selIntMonth[]', 'size'=>'12', 'multiple'=>'multiple'));
        $yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($this->selectedYear, '2010', $this->fyDiffMonths, FALSE), array('name' => 'selIntYear', 'size'=>'12'));
        $calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->calendarOptions, $this->selectedCalendar, FALSE), array('name' => 'selCalendar', 'size'=>'5'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh('Time Period', array('colspan'=>'3')));

        $tbl->addHeaderTr(
                HTMLTable::makeTh('Interval')
                . HTMLTable::makeTh('Month', array('style'=>'min-width:100px;'))
                . HTMLTable::makeTh('Year'));

        $tbl->addBodyTr(
                HTMLTable::makeTd($calSelector, array('style'=>'vertical-align: top;'))
                . HTMLTable::makeTd($monthSelector, array('style'=>'vertical-align: top;'))
                . HTMLTable::makeTd($yearSelector, array('style'=>'vertical-align: top;'))
        );

        if (isset($this->calendarOptions[self::DATES])) {
            $tbl->addBodyTr(HTMLTable::makeTd(
                HTMLContainer::generateMarkup('span', 'Start:', array('class'=>'dates', 'style'=>'margin-right:.3em;display:none;'))
                . HTMLInput::generateMarkup($this->selectedStart, array('name'=>"stDate", 'class'=>"ckdate dates", 'style'=>"margin-right:.3em;display:none;"))
                . HTMLContainer::generateMarkup('span', 'End:', array('class'=>'dates', 'style'=>'margin-right:.3em;display:none;'))
                . HTMLInput::generateMarkup($this->selectedEnd, array('name'=>"enDate", 'class'=>"ckdate dates", 'style'=>"margin-right:.3em;display:none;"))
                , array('colspan'=>'3')
                ));
        }

        return $tbl;
    }

    public function getTimePeriodScript() {

        $ckdate = '';

        if (isset($this->calendarOptions[self::DATES])) {
            $ckdate = "$('.ckdate').datepicker({
yearRange: '-05:+02',
changeMonth: true,
changeYear: true,
autoSize: true,
numberOfMonths: 1,
dateFormat: 'M d, yy'
});";
        }

        return "$('#selCalendar').change(function () {
    $('#selIntYear').show();
    if ($(this).val() && $(this).val() != '19') {
        $('#selIntMonth').hide();
    } else {
        $('#selIntMonth').show();
    }
    if ($(this).val() && $(this).val() != '18') {
        $('.dates').hide();
    } else {
        $('.dates').show();
        $('#selIntYear').hide();
    }
});
$('#selCalendar').change();
$ckdate";

    }

    public function loadSelectedTimePeriod() {

        // gather input
        if (isset($_POST['selCalendar'])) {
            $this->selectedCalendar = intval(filter_var($_POST['selCalendar'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_POST['selIntMonth'])) {
            $this->selectedMonths = filter_var_array($_POST['selIntMonth'], FILTER_SANITIZE_NUMBER_INT);
        }

        if (isset($_POST['selIntYear'])) {
            $this->selectedYear = intval(filter_var($_POST['selIntYear'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_POST['stDate'])) {
            $this->selectedStart = filter_var($_POST['stDate'], FILTER_SANITIZE_STRING);
        }

        if (isset($_POST['enDate'])) {
            $this->selectedEnd = filter_var($_POST['enDate'], FILTER_SANITIZE_STRING);
        }


        if ($this->selectedCalendar == self::FISCAL_YEAR) {
            // fiscal year
            $adjustPeriod = new \DateInterval('P' . $this->fyDiffMonths . 'M');
            $startDT = new \DateTime($this->selectedYear . '-01-01');
            $startDT->sub($adjustPeriod);
            $this->reportStart = $startDT->format('Y-m-d');

            $endDT = new \DateTime(($this->selectedYear + 1) . '-01-01');
            $this->reportEnd = $endDT->sub($adjustPeriod)->format('Y-m-d');

        } else if ($this->selectedCalendar == self::CAL_YEAR) {
            // Calendar year
            $startDT = new \DateTime($this->selectedYear . '-01-01');
            $this->reportStart = $startDT->format('Y-m-d');

            $this->reportEnd = ($this->selectedYear + 1) . '-01-01';

        } else if ($this->selectedCalendar == self::YEAR_2_DATE) {
            // Year to date
            $this->reportStart = date('Y') . '-01-01';

            $endDT = new \DateTime();
            //$endDT->add(new DateInterval('P1D'));
            $this->reportEnd = $endDT->format('Y-m-d');

        } else if ($this->selectedCalendar == self::DATES) {
            // selected dates.
            $startDT = new \DateTime($this->selectedStart);
            $endDT = new \DateTime($this->selectedEnd);
            //$endDT->add(new \DateInterval('P1D'));

            if ($startDT <= $endDT) {
                $this->reportEnd = $endDT->format('Y-m-d');
                $this->reportStart = $startDT->format('Y-m-d');
            } else {
                $this->reportStart = $endDT->format('Y-m-d');
                $this->reportEmd = $startDT->format('Y-m-d');
            }

        } else {
            // Months
            $interval = 'P' . count($this->selectedMonths) . 'M';
            $month = $this->selectedMonths[0];

            if ($month < 1) {
                $y = $this->selectedYear - 1;
                $this->reportStart = $y . '-12-01';
            } else if ($month > 12) {
                $y = $this->selectedYear + 1;
                $this->reportStart = $y . '-01-01';
            } else {
                $this->reportStart = $this->selectedYear . '-' . $month . '-01';
            }

            $endDT = new \DateTime($this->reportStart);
            $endDT->add(new \DateInterval($interval));

            $this->reportEnd = $endDT->format('Y-m-d');
        }

    }

    public function createHospitals() {

        $uS = Session::getInstance();

        $this->hospitals = array();
        if (isset($uS->guestLookups[GLTableNames::Hospital])) {
            $this->hospitals = $uS->guestLookups[GLTableNames::Hospital];
        }

        $this->hList[] = array(0=>'', 1=>'(All)');
        $this->aList[] = array(0=>'', 1=>'(All)');
        foreach ($this->hospitals as $h) {
            if ($h[2] == 'h') {
                $this->hList[] = array(0=>$h[0], 1=>$h[1]);
            } else if ($h[2] == 'a') {
                $this->aList[] = array(0=>$h[0], 1=>$h[1]);
            }
        }

    }

    public function hospitalMarkup() {

        $assocs = '';
        $labels = Labels::getLabels();
        // Setups for the page.
        if (count($this->aList) > 1) {
            $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->aList, $this->selectedAssocs, FALSE),
                    array('name'=>'selAssoc[]', 'size'=>(count($this->aList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
        }

        $hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($this->hList, $this->selectedHosptials, FALSE),
                array('name'=>'selHospital[]', 'size'=>(count($this->hList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));

        $tbl = new HTMLTable();
        $tr = '';

        $tbl->addHeaderTr(HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital').'s', array('colspan'=>'2')));

        if (count($this->aList) > 1) {
            $tbl->addHeaderTr(HTMLTable::makeTh('Associations') . HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital').'s'));
            $tr .= HTMLTable::makeTd($assocs, array('style'=>'vertical-align: top;'));
        }

        $tbl->addBodyTr($tr . HTMLTable::makeTd($hospitals, array('style'=>'vertical-align: top;')));

        return $tbl;
    }

    public function loadSelectedHospitals() {

        if (isset($_POST['selAssoc'])) {
            $reqs = $_POST['selAssoc'];
            if (is_array($reqs)) {
                $this->selectedAssocs = filter_var_array($reqs, FILTER_SANITIZE_STRING);
            }
        }

        if (isset($_POST['selHospital'])) {
            $reqs = $_POST['selHospital'];
            if (is_array($reqs)) {
                $this->selectedHosptials = filter_var_array($reqs, FILTER_SANITIZE_STRING);
            }
        }

    }

    public function createResoourceGroups($rescGroups, $defaultGroupBy) {

        if (isset($rescGroups[$defaultGroupBy])) {
            $this->selectedResourceGroups = $defaultGroupBy;
        } else {
            $this->selectedResourceGroups = reset($rescGroups)[0];
        }

        $this->resourceGroups = removeOptionGroups($rescGroups);
    }

    public function loadSelectedResourceGroups() {

        if (isset($_POST['selRoomGroup'])) {
            $this->selectedResourceGroups = filter_var($_POST['selRoomGroup'], FILTER_SANITIZE_STRING);
        }
    }

    public function resourceGroupsMarkup() {

        $rooms = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($this->resourceGroups, $this->selectedResourceGroups, FALSE),
                array('name'=>'selRoomGroup', 'size'=>(count($this->resourceGroups)), 'style'=>'min-width:60px;'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh('Room Groups'));
        $tbl->addBodyTr(HTMLTable::makeTd($rooms, array('style'=>'vertical-align: top;')));

        return $tbl;
    }

    public function getSelectedResourceGroups() {
        return $this->selectedResourceGroups;
    }

    public function getResourceGroups() {
        return $this->resourceGroups;
    }

    public function getMonths() {
        return $this->months;
    }

    public function getCalendarOptions() {
        return $this->calendarOptions;
    }

    public function getSelectedCalendar() {
        return $this->selectedCalendar;
    }

    public function getSelectedMonths() {
        return $this->selectedMonths;
    }

    public function getSelectedYear() {
        return $this->selectedYear;
    }

    public function getSelectedStart() {
        return $this->selectedStart;
    }

    public function getSelectedEnd() {
        return $this->selectedEnd;
    }

    public function getHospitals() {
        return $this->hospitals;
    }

    public function getHList() {
        return $this->hList;
    }

    public function getAList() {
        return $this->aList;
    }

    public function getSelectedHosptials() {
        return $this->selectedHosptials;
    }

    public function getSelectedAssocs() {
        return $this->selectedAssocs;
    }

    public function getReportStart() {
        return $this->reportStart;
    }

    public function getReportEnd() {
        return $this->reportEnd;
    }


}
