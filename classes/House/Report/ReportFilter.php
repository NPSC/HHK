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

    /**
     * Summary of months
     * @var array
     */
    protected $months;
    /**
     * Summary of calendarOptions
     * @var array
     */
    protected $calendarOptions;
    /**
     * Summary of selectedCalendar
     * @var
     */
    protected $selectedCalendar;
    /**
     * Summary of selectedMonths
     * @var
     */
    protected $selectedMonths;
    /**
     * Summary of selectedYear
     * @var
     */
    protected $selectedYear;
    /**
     * Summary of selectedStart
     * @var
     */
    protected $selectedStart;
    /**
     * Summary of selectedEnd
     * @var
     */
    protected $selectedEnd;
    /**
     * Summary of fyDiffMonths
     * @var
     */
    protected $fyDiffMonths;

    /**
     * Summary of hospitals
     * @var
     */
    protected $hospitals;
    /**
     * Summary of hList
     * @var
     */
    protected $hList;
    /**
     * Summary of aList
     * @var
     */
    protected $aList;
    /**
     * Summary of selectedHosptials
     * @var
     */
    protected $selectedHosptials;
    /**
     * Summary of selectedAssocs
     * @var
     */
    protected $selectedAssocs;

    /**
     * Summary of selectedResourceGroups
     * @var
     */
    protected $selectedResourceGroups;
    /**
     * Summary of resourceGroups
     * @var
     */
    protected $resourceGroups;

        /**
     * Summary of selectedDiagnoses
     * @var
     */
    protected $selectedDiagnoses;
    /**
     * Summary of diagnsoses
     * @var
     */
    protected $diagnoses;
    protected $diagnosisCategories;

    /**
     * Summary of reportStart
     * @var
     */
    protected $reportStart;
    /**
     * Summary of reportEnd
     * @var
     */
    protected $reportEnd;
    /**
     * Summary of queryEnd
     * @var
     */
    protected $queryEnd;

    /**
     * Summary of __construct
     */
    public function __construct() {
        $this->selectedAssocs = array();
        $this->selectedHosptials = array();
        $this->selectedResourceGroups = array();
        $this->selectedDiagnoses = array();
        $this->selectedMonths = array();
        $this->hospitals = array();
    }

    /**
     * Summary of createTimePeriod
     * @param mixed $defaultYear
     * @param mixed $defaultCalendarOption
     * @param mixed $fiscalYearDiffMonths
     * @param mixed $omits
     * @return ReportFilter
     */
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
        $this->selectedMonths = array(date('m'));
        $this->fyDiffMonths = $fiscalYearDiffMonths;
        return $this;
    }

    /**
     * Summary of timePeriodMarkup
     * @param mixed $prefix
     * @return HTMLTable
     */
    public function timePeriodMarkup($prefix = '') {

        $uS = Session::getInstance();

        $monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->months, $this->selectedMonths, FALSE), array('name' => 'selIntMonth[]', 'size'=>'12', 'multiple'=>'multiple'));
        $yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($this->selectedYear, ($uS->StartYear ? $uS->StartYear : "2013"), $this->fyDiffMonths, FALSE), array('name' => 'selIntYear', 'size'=>'12'));
        $calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->calendarOptions, $this->selectedCalendar, FALSE), array('name' => 'selCalendar', 'size'=>'5'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh(($prefix == '' ? '' : $prefix . ' ') . 'Time Period', array('colspan'=>'3')));

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
                ), array('class'=>'dates'));
        }

        return $tbl;
    }

    /**
     * Summary of getTimePeriodScript
     * @return string
     */
    public function getTimePeriodScript() {
        $uS = Session::getInstance();
        $ckdate = '';

        if (isset($this->calendarOptions[self::DATES])) {
            $ckdate = "$('.ckdate').datepicker({
yearRange: '" . $uS->StartYear . ":+02',
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

    /**
     * Summary of loadSelectedTimePeriod
     * @return ReportFilter
     */
    public function loadSelectedTimePeriod() {

        // gather input
        if (filter_has_var(INPUT_POST, 'selCalendar')) {
            $this->selectedCalendar = intval(filter_input(INPUT_POST, 'selCalendar', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (filter_has_var(INPUT_POST, 'selIntMonth')) {
            $this->selectedMonths = filter_input(INPUT_POST, 'selIntMonth', FILTER_SANITIZE_NUMBER_INT, FILTER_FORCE_ARRAY);
        }

        if (filter_has_var(INPUT_POST, 'selIntYear')) {
            $this->selectedYear = intval(filter_input(INPUT_POST, 'selIntYear', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (filter_has_var(INPUT_POST, 'stDate')) {
            $this->selectedStart = filter_input(INPUT_POST, 'stDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (filter_has_var(INPUT_POST, 'enDate')) {
            $this->selectedEnd = filter_input(INPUT_POST, 'enDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }


        if ($this->selectedCalendar == self::FISCAL_YEAR) {
            // fiscal year
            $adjustPeriod = new \DateInterval('P' . $this->fyDiffMonths . 'M');
            $startDT = new \DateTime($this->selectedYear . '-01-01');
            $startDT->sub($adjustPeriod);
            $this->reportStart = $startDT->format('Y-m-d');

            $endDT = new \DateTime(($this->selectedYear + 1) . '-01-01');

            $this->queryEnd = $endDT->sub($adjustPeriod)->format('Y-m-d');

            $this->reportEnd = $endDT->sub(new \DateInterval('P1D'))->format('Y-m-d');

        } else if ($this->selectedCalendar == self::CAL_YEAR) {
            // Calendar year
            $startDT = new \DateTime($this->selectedYear . '-01-01');
            $this->reportStart = $startDT->format('Y-m-d');

            $this->queryEnd = $startDT->add(new \DateInterval('P1Y'))->format('Y-m-d');

            $this->reportEnd = $startDT->sub(new \DateInterval('P1D'))->format('Y-m-d');

        } else if ($this->selectedCalendar == self::YEAR_2_DATE) {
            // Year to date
            $this->reportStart = date('Y') . '-01-01';

            $endDT = new \DateTime();
            $this->reportEnd = $endDT->format('Y-m-d');

            $this->queryEnd = $endDT->add(new \DateInterval('P1D'))->format('Y-m-d');


        } else if ($this->selectedCalendar == self::DATES) {
            // selected dates.
            $startDT = new \DateTime($this->selectedStart);
            $endDT = new \DateTime($this->selectedEnd);


            if ($startDT <= $endDT) {
                $this->reportEnd = $endDT->format('Y-m-d');
                $this->queryEnd = $endDT->format('Y-m-d');
                $this->reportStart = $startDT->format('Y-m-d');
            } else {
                $this->reportStart = $endDT->format('Y-m-d');
                $this->reportEnd = $startDT->format('Y-m-d');
                $this->queryEnd = $startDT->format('Y-m-d');
            }

        } else if ($this->selectedCalendar == self::MONTHS){
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

            $startDT = new \DateTimeImmutable($this->reportStart);
            $endDT = $startDT->add(new \DateInterval($interval));

            $this->reportStart = $startDT->format('Y-m-d');
            $this->queryEnd = $endDT->format('Y-m-d');
            $this->reportEnd = $endDT->sub(new \DateInterval('P1D'))->format('Y-m-d');
        }

        return $this;
    }

    /**
     * Summary of createHospitals
     * @return ReportFilter
     */
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

        return $this;
    }

    /**
     * Summary of hospitalMarkup
     * @return HTMLTable
     */
    public function hospitalMarkup() {

        $assocs = '';
        $labels = Labels::getLabels();
        // Setups for the page.
        if (count($this->aList) > 1) {
            $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->aList, $this->selectedAssocs, FALSE),
                    array('name'=>'selAssoc[]', 'size'=>(count($this->aList)), 'multiple'=>'multiple', 'style'=>'min-width:60px; width:100%;'));
        }

        $hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($this->hList, $this->selectedHosptials, FALSE),
        		array('name'=>'selHospital[]', 'size'=>(count($this->hList)>12 ? '12' : count($this->hList)), 'multiple'=>'multiple', 'style'=>'min-width:60px; width: 100%'));

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

    /**
     * Summary of loadSelectedHospitals
     * @return ReportFilter
     */
    public function loadSelectedHospitals() {

        if (filter_has_var(INPUT_POST, 'selAssoc')) {
            $reqs = $_POST['selAssoc'];
            if (is_array($reqs)) {
                $this->selectedAssocs = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        if (filter_has_var(INPUT_POST, 'selHospital')) {
            $reqs = $_POST['selHospital'];
            if (is_array($reqs)) {
                $this->selectedHosptials = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        return $this;
    }

    /**
     * Summary of createResourceGroups
     * @param mixed $rescGroups
     * @param mixed $defaultGroupBy
     * @return ReportFilter
     */
    public function createResourceGroups($rescGroups, $defaultGroupBy) {

        if (isset($rescGroups[$defaultGroupBy])) {
            $this->selectedResourceGroups = $defaultGroupBy;
        } else {
            $this->selectedResourceGroups = reset($rescGroups)[0];
        }

        $this->resourceGroups = removeOptionGroups($rescGroups);
        return $this;
    }

    /**
     * Summary of loadSelectedResourceGroups
     * @return ReportFilter
     */
    public function loadSelectedResourceGroups() {

        if (filter_has_var(INPUT_POST, 'selRoomGroup')) {
            $this->selectedResourceGroups = filter_input(INPUT_POST, 'selRoomGroup', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        return $this;
    }

    /**
     * Summary of resourceGroupsMarkup
     * @return HTMLTable
     */
    public function resourceGroupsMarkup() {

        $rooms = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($this->resourceGroups, $this->selectedResourceGroups, FALSE),
                array('name'=>'selRoomGroup', 'size'=>(count($this->resourceGroups)), 'style'=>'min-width:60px;'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh('Room Groups'));
        $tbl->addBodyTr(HTMLTable::makeTd($rooms, array('style'=>'vertical-align: top;')));

        return $tbl;
    }

    /**
     * Load diagnoses and categories
     * @param \PDO $dbh
     * @return ReportFilter
     */
    public function createDiagnoses(\PDO $dbh){
        $this->diagnoses = readGenLookupsPDO($dbh, 'Diagnosis', 'Description');
        $this->diagnosisCategories = readGenLookupsPDO($dbh, 'Diagnosis_Category', 'Description');

        if (count($this->diagnoses) > 0) {

            //prepare diag categories for doOptionsMkup
            foreach($this->diagnoses as $key=>$diag){
                if(!empty($diag['Substitute'])){
                    $this->diagnoses[$key][2] = $this->diagnosisCategories[$diag['Substitute']][1];
                }
            }

            array_unshift($this->diagnoses, array('','(All)'));
        }
        return $this;
    }

    /**
     * Summary of loadSelectedDiagnoses
     * @return ReportFilter
     */
    public function loadSelectedDiagnoses() {

        if (filter_has_var(INPUT_POST, 'selDiagnoses')) {
            $reqs = $_POST['selDiagnoses'];
            if (is_array($reqs)) {
                $this->selectedDiagnoses = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        return $this;
    }

    /**
     * Summary of diagnosisMarkup
     * @return HTMLTable
     */
    public function diagnosisMarkup() {

        $diags = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($this->diagnoses, $this->selectedDiagnoses, FALSE),
        array('name'=>'selDiagnoses[]', 'size'=>(count($this->diagnoses)>12 ? '12' : count($this->diagnoses)), 'multiple'=>'multiple', 'style'=>'min-width:60px; width: 100%'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh(Labels::getString('hospital', 'diagnosis', 'Diagnosis')));
        $tbl->addBodyTr(HTMLTable::makeTd($diags, array('style'=>'vertical-align: top;')));

        return $tbl;
    }

    /**
     * Summary of getSelectedHospitalsString
     * @return string
     */
    public function getSelectedHospitalsString(){
        $hospList = $this->getHospitals();
        $hospitalTitles = "";
        foreach ($this->getSelectedHosptials() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }
        if ($hospitalTitles != '') {
            $h = trim($hospitalTitles);
            return substr($h, 0, strlen($h) - 1);
        }else{
            return "All";
        }
    }

    /**
     * Summary of getSelectedAssocString
     * @return string
     */
    public function getSelectedAssocString(){
        $assocList = $this->getHospitals();
        $assocTitles = "";
        foreach ($this->getSelectedAssocs() as $h) {
            if (isset($assocList[$h])) {
                $assocTitles .= $assocList[$h][1] . ', ';
            }
        }
        if ($assocTitles != '') {
            $h = trim($assocTitles);
            return substr($h, 0, strlen($h) - 1);
        }else{
            return "All";
        }
    }

    /**
     * Summary of getSelectedResourceGroups
     * @return array|mixed
     */
    public function getSelectedResourceGroups() {
        return $this->selectedResourceGroups;
    }

    /**
     * Summary of getResourceGroups
     * @return array<array>
     */
    public function getResourceGroups() {
        return $this->resourceGroups;
    }

    /**
     * Summary of getSelectedDiagnoses
     * @return array|mixed
     */
    public function getSelectedDiagnoses() {
        return $this->selectedDiagnoses;
    }

    /**
     * Summary of getDiagnoses
     * @return array<array>
     */
    public function getDiagnoses() {
        return $this->diagnoses;
    }

    /**
     * Summary of getMonths
     * @return array
     */
    public function getMonths() {
        return $this->months;
    }

    /**
     * Summary of getCalendarOptions
     * @return array<array>
     */
    public function getCalendarOptions() {
        return $this->calendarOptions;
    }

    /**
     * Summary of getSelectedCalendar
     * @return int|mixed
     */
    public function getSelectedCalendar() {
        return $this->selectedCalendar;
    }

    /**
     * Summary of getSelectedMonths
     * @return array|mixed
     */
    public function getSelectedMonths() {
        return $this->selectedMonths;
    }

    /**
     * Summary of getSelectedYear
     * @return int|mixed
     */
    public function getSelectedYear() {
        return $this->selectedYear;
    }

    /**
     * Summary of getSelectedStart
     * @return mixed
     */
    public function getSelectedStart() {
        return $this->selectedStart;
    }

    /**
     * Summary of getSelectedEnd
     * @return mixed
     */
    public function getSelectedEnd() {
        return $this->selectedEnd;
    }

    /**
     * Summary of getHospitals
     * @return array|mixed
     */
    public function getHospitals() {
        return $this->hospitals;
    }

    /**
     * Summary of getHList
     * @return array<array>
     */
    public function getHList() {
        return $this->hList;
    }

    /**
     * Summary of getAList
     * @return array<array>
     */
    public function getAList() {
        return $this->aList;
    }

    /**
     * Summary of getSelectedHosptials
     * @return array|bool
     */
    public function getSelectedHosptials() {
        return $this->selectedHosptials;
    }

    /**
     * Summary of getSelectedAssocs
     * @return array|bool
     */
    public function getSelectedAssocs() {
        return $this->selectedAssocs;
    }

    /**
     * Summary of getReportStart
     * @return string
     */
    public function getReportStart() {
        return $this->reportStart;
    }

    /**
     * Summary of getReportEnd
     * @return string
     */
    public function getReportEnd() {
        return $this->reportEnd;
    }

    /**
     * Summary of getQueryEnd
     * @return string
     */
    public function getQueryEnd() {
        return $this->queryEnd;
    }

}
