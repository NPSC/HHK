<?php

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

    public function createTimePeriod($defaultYear, $defaultCalendarOption, $fiscalYearDiffMonths = 0) {
        $this->months = array(
            0 => array(0, 'December'), 1 => array(1, 'January'), 2 => array(2, 'February'),
            3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
            7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'), 13 => array(13, 'January'));

        if ($fiscalYearDiffMonths == 0) {
            $this->calendarOptions = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
        } else {
            $this->calendarOptions = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
        }

        $this->selectedYear = $defaultYear;
        $this->selectedCalendar = $defaultCalendarOption;
        $this->selectedMonths = date('m');
        $this->fyDiffMonths = $fiscalYearDiffMonths;
    }

    public function timePeriodMarkup(Config_Lite $config) {

        $monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->months, $this->selectedMonths, FALSE), array('name' => 'selIntMonth[]', 'size'=>'12', 'multiple'=>'multiple'));
        $yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($this->selectedYear, $config->getString('site', 'Start_Year', '2010'), $this->fyDiffMonths, FALSE), array('name' => 'selIntYear', 'size'=>'12'));
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

        $tbl->addBodyTr(HTMLTable::makeTd(
                HTMLContainer::generateMarkup('span', 'Start:', array('class'=>'dates', 'style'=>'margin-right:.3em;display:none;'))
                . HTMLInput::generateMarkup($this->selectedStart, array('name'=>"stDate", 'class'=>"ckdate dates", 'style'=>"margin-right:.3em;display:none;"))
                . HTMLContainer::generateMarkup('span', 'End:', array('class'=>'dates', 'style'=>'margin-right:.3em;display:none;'))
                . HTMLInput::generateMarkup($this->selectedEnd, array('name'=>"enDate", 'class'=>"ckdate dates", 'style'=>"margin-right:.3em;display:none;"))
                . $this->timePeriodScript()
                , array('colspan'=>'3')
                ));

        return $tbl;
    }

    protected function timePeriodScript() {

        return "<script type='text/javascript'>
                $('#selCalendar').change(function () {
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
        </script>";

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


        if ($this->selectedCalendar == 20) {
            // fiscal year
            $adjustPeriod = new DateInterval('P' . $this->fyDiffMonths . 'M');
            $startDT = new DateTime($this->selectedYear . '-01-01');
            $startDT->sub($adjustPeriod);
            $this->reportStart = $startDT->format('Y-m-d');

            $endDT = new DateTime(($this->selectedYear + 1) . '-01-01');
            $this->reportEnd = $endDT->sub($adjustPeriod)->format('Y-m-d');

        } else if ($this->selectedCalendar == 21) {
            // Calendar year
            $startDT = new DateTime($this->selectedYear . '-01-01');
            $this->reportStart = $startDT->format('Y-m-d');

            $this->reportEnd = ($this->selectedYear + 1) . '-01-01';

        } else if ($this->selectedCalendar == 22) {
            // Year to date
            $this->reportStart = date('Y') . '-01-01';

            $endDT = new DateTime();
            $endDT->add(new DateInterval('P1D'));
            $this->reportEnd = $endDT->format('Y-m-d');

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

            $endDate = new DateTime($this->reportStart);
            $endDate->add(new DateInterval($interval));
            $endDate->sub(new DateInterval('P1D'));

            $this->reportEnd = $endDate->format('Y-m-d');
        }

    }

    public function createHospitals() {

        $uS = Session::getInstance();

        $this->hospitals = array();
        if (isset($uS->guestLookups[GL_TableNames::Hospital])) {
            $this->hospitals = $uS->guestLookups[GL_TableNames::Hospital];
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

        // Setups for the page.
        if (count($this->aList) > 1) {
            $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->aList, $this->selectedAssocs, FALSE),
                    array('name'=>'selAssoc[]', 'size'=>(count($this->aList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
        }

        $hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($this->hList, $this->selectedHosptials, FALSE),
                array('name'=>'selHospital[]', 'size'=>(count($this->hList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));

        $tbl = new HTMLTable();
        $tr = '';

        $tbl->addHeaderTr(HTMLTable::makeTh('Hospitals', array('colspan'=>'2')));

        if (count($this->aList) > 1) {
            $tbl->addHeaderTr(HTMLTable::makeTh('Associations') . HTMLTable::makeTh('Hospitals'));
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
