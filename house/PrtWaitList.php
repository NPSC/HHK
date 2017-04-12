<?php
/**
 * PrtWaitList.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'History.php');
require (CLASSES . 'CreateMarkupFromDB.php');
require (CLASSES . 'Purchase/RoomRate.php');



$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$checkinDate = '';

if (isset($_GET['d'])) {
    $checkinDate = filter_var($_GET['d'], FILTER_SANITIZE_STRING);
}


if ($checkinDate == '') {
    $checkinDate = date('M j, Y');
}

$history = new History();

$rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Waitlist, '', FALSE, $checkinDate, 1);

$regForm = CreateMarkupFromDB::generateHTML_Table($rows, 'tbl');

?>
<!DOCTYPE html>
<html lang="en" moznomarginboxes>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <link href="css/house.css" rel="stylesheet" type="text/css" />
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <style type="text/css" media="print">
            body {margin:0; padding:0; line-height: 1.4em; word-spacing:1px; letter-spacing:0.2px; font: 13px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            .hhk-noprint {display:none;}
        </style>

        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
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
            <div style='margin-left:100px;margin-bottom:10px; clear:left; float:left;' class='hhk-noprint ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox'>
                <input type="button" value="Print" id='btnPrint' style="margin-right:.3em;"/>
            </div>
            <div id="divBody" style="clear:left; float:left; font-size: .8em;" class='PrintArea ui-widget ui-widget-content hhk-tdbox'>

                <?php echo $regForm; ?>
            </div>

    </body>
</html>
