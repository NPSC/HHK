<?php

namespace HHK\House\Report;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\SysConst\GLTableNames;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\VolMemberType;
use RuntimeException;

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
     * Summary of selectedBillingAgents
     * @var 
     */
    protected $selectedBillingAgents;
    /**
     * Summary of billingAgents
     * @var 
     */
    protected $billingAgents;

    /**
     * Summary of selectedPayTypes
     * @var 
     */
    protected $selectedPayTypes;
    /**
     * Summary of payTypes
     * @var 
     */
    protected $payTypes;

    /**
     * Summary of selectedPayStatuses
     * @var 
     */
    protected $selectedPayStatuses;
    /**
     * Summary of payStatuses
     * @var 
     */
    protected $payStatuses;

    /**
     * Summary of selectedPaymentGateways
     * @var 
     */
    protected $selectedPaymentGateways;
    /**
     * Summary of paymentGateways
     * @var 
     */
    protected $paymentGateways;

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
        $this->selectedBillingAgents = array();
        $this->selectedPayStatuses = array();
        $this->selectedPayTypes = array();
        $this->selectedPaymentGateways = array();
        $this->selectedMonths = array();
        $this->hospitals = array();
        $this->paymentGateways = array();
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
                . HTMLInput::generateMarkup($this->reportStart, array('type'=>'date', 'name'=>"stDate", 'class'=>"ckdate dates", 'style'=>"margin-right:.3em;display:none;"))
                . HTMLContainer::generateMarkup('span', 'End:', array('class'=>'dates', 'style'=>'margin-right:.3em;display:none;'))
                . HTMLInput::generateMarkup($this->reportEnd, array('type'=>'date', 'name'=>"enDate", 'class'=>"ckdate dates", 'style'=>"margin-right:.3em;display:none;"))
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
dateFormat: 'yy-mm-dd'
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
            try{
                $startDT = new \DateTime($this->selectedStart);
                $endDT = new \DateTime($this->selectedEnd);
            } catch (\Exception $e){
                //default to this month.
                $startDT = new \DateTime(date('Y-m-01'));
                $endDT = new \DateTime(date('Y-m-t'));
            }

            if ($startDT <= $endDT) {
                $this->reportEnd = $endDT->format('Y-m-d');
                $this->queryEnd = $endDT->format('Y-m-d');
                $this->reportStart = $startDT->format('Y-m-d');
            } else {
                $this->reportStart = $endDT->format('Y-m-d');
                $this->reportEnd = $startDT->format('Y-m-d');
                $this->queryEnd = $startDT->format('Y-m-d');
            }

            $this->selectedStart = $startDT->format("M d, Y");
            $this->selectedEnd = $endDT->format("M d, Y");

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
            $tbl->addHeaderTr(HTMLTable::makeTh($labels->getString('hospital', 'association', 'Association')) . HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital').'s'));
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
                if(count($reqs) > 2 && in_array("", $reqs)){
                    $k = array_search("", $reqs);
                    unset($reqs[$k]);
                }
                $this->selectedAssocs = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        if (filter_has_var(INPUT_POST, 'selHospital')) {
            $reqs = $_POST['selHospital'];
            if (is_array($reqs)) {
                if(count($reqs) > 2 && in_array("", $reqs)){
                    $k = array_search("", $reqs);
                    unset($reqs[$k]);
                }
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
    public function createResourceGroups(\PDO $dbh) {

        $uS = Session::getInstance();

        $rescGroups = readGenLookupsPDO($dbh, 'Room_Group');

        if (isset($rescGroups[$uS->CalResourceGroupBy])) {
            $this->selectedResourceGroups = $uS->CalResourceGroupBy;
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
     * Load Billing Agents
     * @param \PDO $dbh
     * @return ReportFilter
     */
    public function createBillingAgents(\PDO $dbh){
        $stmt = $dbh->query("SELECT n.idName, n.Name_First, n.Name_Last, n.Company " .
        " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
        " where n.Member_Status='a' and n.Record_Member = 1 order by n.Name_Last, n.Name_First, n.Company");

        $this->billingAgents = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $entry = '';

            if ($r['Name_First'] != '' || $r['Name_Last'] != '') {
                $entry = trim($r['Name_First'] . ' ' . $r['Name_Last']);
            }

            if ($entry != '' && $r['Company'] != '') {
                $entry .= '; ' . $r['Company'];
            }

            if ($entry == '' && $r['Company'] != '') {
                $entry = $r['Company'];
            }

            $this->billingAgents[$r['idName']] = array(0=>$r['idName'], 1=>$entry);
        }
        return $this;
    }

    /**
     * Summary of loadSelectedBillingAgents
     * @return ReportFilter
     */
    public function loadSelectedBillingAgents() {

        if (filter_has_var(INPUT_POST, 'selBillingAgents')) {
            $reqs = $_POST['selBillingAgents'];
            if (is_array($reqs)) {
                $this->selectedBillingAgents = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                if($this->selectedBillingAgents[0] == ""){
                    unset($this->selectedBillingAgents[0]);
                }
            }
        }

        return $this;
    }

    /**
     * Summary of billingAgentMarkup
     * @return HTMLTable
     */
    public function billingAgentMarkup() {

        $agents = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($this->billingAgents, $this->selectedBillingAgents, TRUE),
        array('name'=>'selBillingAgents[]', 'size'=>(count($this->billingAgents)>12 ? '12' : count($this->billingAgents))+1, 'multiple'=>'multiple', 'style'=>'min-width:60px; width: 100%'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh("Billing Agents"));
        $tbl->addBodyTr(HTMLTable::makeTd($agents, array('style'=>'vertical-align: top;')));

        return $tbl;
    }

    /**
     * Load Pay Types
     * @param \PDO $dbh
     * @return ReportFilter
     */
    public function createPayTypes(\PDO $dbh){
        $this->payTypes = array();
        $uS = Session::getInstance();

        foreach ($uS->nameLookups[GLTableNames::PayType] as $p) {
            if ($p[2] != '') {
                $this->payTypes[$p[2]] = array($p[2], $p[1]);
            }
        }
        return $this;
    }

    /**
     * Summary of loadSelectedPayTypes
     * @return ReportFilter
     */
    public function loadSelectedPayTypes() {

        if (filter_has_var(INPUT_POST, 'selPayType')) {
            $reqs = $_POST['selPayType'];
            if (is_array($reqs)) {
                $this->selectedPayTypes = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        return $this;
    }

    /**
     * Summary of payTypesMarkup
     * @return HTMLTable
     */
    public function payTypesMarkup() {

        $payTypeSelector = HTMLSelector::generateMarkup(
            HTMLSelector::doOptionsMkup($this->payTypes, $this->selectedPayTypes), array('name' => 'selPayType[]', 'size' => '5', 'multiple' => 'multiple'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh("Pay Type"));
        $tbl->addBodyTr(HTMLTable::makeTd($payTypeSelector, array('style'=>'vertical-align: top;')));

        return $tbl;
    }

    /**
     * Load Pay Statuses
     * @param \PDO $dbh
     * @return ReportFilter
     */
    public function createPayStatuses(\PDO $dbh){
        $this->payStatuses = readGenLookupsPDO($dbh, 'Payment_Status');
        return $this;
    }

    /**
     * Summary of loadSelectedPayStatuses
     * @return ReportFilter
     */
    public function loadSelectedPayStatuses() {

        if (filter_has_var(INPUT_POST, 'selPayStatus')) {
            $reqs = $_POST['selPayStatus'];
            if (is_array($reqs)) {
                $this->selectedPayStatuses = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        return $this;
    }

    /**
     * Summary of payStatusMarkup
     * @return HTMLTable
     */
    public function payStatusMarkup() {

        $statusSelector = HTMLSelector::generateMarkup(
            HTMLSelector::doOptionsMkup($this->payStatuses, $this->selectedPayStatuses), array('name' => 'selPayStatus[]', 'size' => '7', 'multiple' => 'multiple'));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh("Pay Status"));
        $tbl->addBodyTr(HTMLTable::makeTd($statusSelector, array('style'=>'vertical-align: top;')));

        return $tbl;
    }

    /**
     * Load Payment Gateways
     * @param \PDO $dbh
     * @return ReportFilter
     */
    public function createPaymentGateways(\PDO $dbh){
        $this->paymentGateways = array();
        $uS = Session::getInstance();

        // Payment gateway lists
        $gwstmt = $dbh->query("Select cc_name from cc_hosted_gateway where Gateway_Name = '" . $uS->PaymentGateway . "' and cc_name not in ('Production', 'Test', '')");
        $gwRows = $gwstmt->fetchAll(\PDO::FETCH_NUM);

        if (count($gwRows) > 1) {

            foreach ($gwRows as $g) {
                $this->paymentGateways[$g[0]] = array(0=>$g[0], 1=>ucfirst($g[0]));
            }
        }
        return $this;
    }

    /**
     * Summary of loadSelectedPaymentGateways
     * @return ReportFilter
     */
    public function loadSelectedPaymentGateways() {

        if (filter_has_var(INPUT_POST, 'selGateway')) {
            $reqs = $_POST['selGateway'];
            if (is_array($reqs)) {
                $this->selectedPaymentGateways = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
        }

        return $this;
    }

    /**
     * Summary of paymentGatwaysMarkup
     * @return HTMLTable
     */
    public function paymentGatewaysMarkup() {

        $gwSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->paymentGateways, $this->selectedPaymentGateways), array('name' => 'selGateway[]', 'multiple' => 'multiple', 'size'=>(count($this->paymentGateways) + 1)));

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh("Location"));
        $tbl->addBodyTr(HTMLTable::makeTd($gwSelector, array('style'=>'vertical-align: top;')));

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
     * Summary of getSelectedBillingAgentsString
     * @return string
     */
    public function getSelectedBillingAgentsString(){
        $billingList = $this->getBillingAgents();
        $billingTitles = "";
        foreach ($this->getSelectedBillingAgents() as $h) {
            if (isset($billingList[$h])) {
                $billingTitles .= $billingList[$h][1] . ', ';
            }
        }
        if ($billingTitles != '') {
            $h = trim($billingTitles);
            return substr($h, 0, strlen($h) - 1);
        }else{
            return "All";
        }
    }

    public function getSelectedDiagnosesString(){
        $diagnosesList = $this->getDiagnoses();
        $diagnosesTitles = "";
        foreach ($this->getSelectedDiagnoses() as $h) {
            if (isset($diagnosesList[$h])) {
                $diagnosesTitles .= $diagnosesList[$h][1] . ', ';
            }
        }
        if ($diagnosesTitles != '') {
            $h = trim($diagnosesTitles);
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
     * Summary of getBillingAgents
     * @return array<array>
     */
    public function getBillingAgents() {
        return $this->billingAgents;
    }

    /**
     * Summary of getSelectedBillingAgents
     * @return array|mixed
     */
    public function getSelectedBillingAgents() {
        return $this->selectedBillingAgents;
    }

    public function getPayStatuses(){
        return $this->payStatuses;
    }

    public function getSelectedPayStatuses(){
        return $this->selectedPayStatuses;
    }

    public function getPayTypes(){
        return $this->payTypes;
    }

    public function getSelectedPayTypes(){
        return $this->selectedPayTypes;
    }

    public function getPaymentGateways(){
        return $this->paymentGateways;
    }

    public function getSelectedPaymentGateways(){
        return $this->selectedPaymentGateways;
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
