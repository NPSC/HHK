<?php

use HHK\sec\{Session, WebInit};
use HHK\SysConst\WebPageCode;
use HHK\History;
use HHK\CreateMarkupFromDB;
use HHK\SysConst\ReservationStatus;

/**
 * PrtWaitList.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");


$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$checkinDate = '';

if (isset($_GET['d'])) {
    $checkinDate = filter_var($_GET['d'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}


if ($checkinDate == '') {
    $checkinDate = date('M j, Y');
}

$history = new History();

$rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Waitlist, FALSE, $checkinDate, 0, TRUE, 'Order BY `Patient Name`');

$regForm = CreateMarkupFromDB::generateHTML_Table($rows, 'tbl');

?>
<!DOCTYPE html>
<html lang="en" moznomarginboxes>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>

        <style type="text/css" media="print">
            body {margin:0; padding:0; line-height: 1.4em; word-spacing:1px; letter-spacing:0.2px; font: 13px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            .hhk-noprint {display:none;}
        </style>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type='text/javascript'>
            $(document).ready(function () {
                "use strict";
                $('#btnPrint, #btnEmail').button();
                $('#btnPrint').click(function () {
                    $("div.PrintArea").printArea();
                });
            });
        </script>
    </head>
    <body>

            <h2><?php echo $wInit->pageHeading . ' For ' . $checkinDate; ?></h2>
            <div style='margin-left:50px;margin-bottom:10px; clear:left; float:left;' class='hhk-noprint ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox'>
                <input type="button" value="Print" id='btnPrint' style="margin-right:.3em;"/>
            </div>
            <div id="divBody" style="clear:left; float:left; font-size: .9em;" class='PrintArea ui-widget ui-widget-content hhk-tdbox'>

                <?php echo $regForm; ?>
            </div>

    </body>
</html>
