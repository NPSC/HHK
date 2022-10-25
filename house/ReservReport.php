<?php

use HHK\AlertControl\AlertMessage;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Report\ReportFilter;
use HHK\ColumnSelectors;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\RoomRateCategories;
use HHK\SysConst\GLTableNames;
use HHK\HTMLControls\HTMLSelector;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\House\Report\ReportFieldSet;
use HHK\House\Report\ReservationReport;

/**
 * ReservReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

// 7/1/2021 - Added "Days" column.  EKC

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();
$labels = Labels::getLabels();
$menuMarkup = $wInit->generatePageMenu();

$dataTableWrapper = '';

$reservationReport = new ReservationReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $reservationReport->getInputSetReportName()])) {
    $dataTableWrapper = $reservationReport->generateMarkup();
}

if (isset($_POST['btnExcel-' . $reservationReport->getInputSetReportName()])) {
    ini_set('memory_limit', "280M");
    $reservationReport->downloadExcel("reservReport");
}

//set up template

$wInit->template->inlineJS = '$(document).ready(function() {
                var dateFormat = "' . $labels->getString("momentFormats", "report", "MMM D, YYYY") . '";
                var columnDefs = $.parseJSON("' . json_encode($reservationReport->colSelector->getColumnDefs()) . '");'
                 . $reservationReport->filter->getTimePeriodScript()
                 . $reservationReport->generateReportScript()
            . '});';

$wInit->template->contentDiv = "<h2>" . $wInit->pageHeading . "</h2>" . $reservationReport->generateFilterMarkup() . $dataTableWrapper;

require("template/base.php");

?>