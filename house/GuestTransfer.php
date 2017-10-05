<?php
/**
 * GuestTransfer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

require CLASSES . 'CreateMarkupFromDB.php';
require CLASSES . 'TransferMembers.php';
require CLASSES . 'OpenXML.php';

try {
    // Do not add CSP.
    $wInit = new webInit(WebPageCode::Page, FALSE);
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$pageHeader = $wInit->pageHeading;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

$config = new Config_Lite(ciCFG_FILE);

$serviceName = $config->getString('webServices', 'Service_Name', '');
$webServices = $config->getString('webServices', 'ContactManager', '');

if ($serviceName != '' && $webServices != '') {
    $wsConfig = new Config_Lite(REL_BASE_DIR . 'conf' . DS .  $webServices);
} else {
    exit('<h2>HHK configuration error:  Web Services Configuration file is missing. Trying to open file name: ' . REL_BASE_DIR . 'conf' . DS .  $webServices . '</h2>');
}

if (function_exists('curl_version') === FALSE) {
    exit('<h2>PHP configuration error: cURL functions are missing. </h2>');
}

$resultMessage = $alertMsg->createMarkup();

$isGuestAdmin = SecurityComponent::is_Authorized('guestadmin');

$labels = new Config_Lite(LABEL_FILE);

function getPaymentReport(\PDO $dbh, $start, $end) {

    $uS = Session::getInstance();
    $whereClause = " DATE(`Payment Date`) >= DATE('$start') and DATE(`Payment Date`) <= DATE('$end') ";

    if (isset($uS->sId) && $uS->sId > 0) {
        $whereClause .= " and `HHK Id` != " . $uS->sId;
    }

    if (isset($uS->subsidyId) && $uS->subsidyId > 0) {
        $whereClause .= " and `HHK Id` != " . $uS->subsidyId;
    }

    $stmt = $dbh->query("Select * from `vneon_payment_display` where $whereClause");
    $rows = array();

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $r['HHK Id'] = HTMLContainer::generateMarkup('a', $r['HHK Id'], array('href'=>'GuestEdit.php?id=' . $r['HHK Id']));

        if (isset($r['Payment Date']) && $r['Payment Date'] != '') {
            $r['Payment Date'] = date('c', strtotime($r['Payment Date']));
        }

        if (isset($r['Amount'])) {
            $r['Amount'] = number_format($r['Amount'], 2);
        }

        $rows[] = $r;

    }

    return CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');

}

function getPeopleReport(\PDO $dbh, $local, $start, $end, $extIdFlag = FALSE) {

    $whExt = '';
    if ($extIdFlag) {
        $whExt = "ifnull(vg.External_Id, '') = '' and ";
    }

    $uS = Session::getInstance();
    $transferIds = [];

    $query = "select vg.External_Id, vg.Id, CASE WHEN vg.Relationship_Code = 'slf' THEN 'p' ELSE '' END as `Patient`, concat(vg.Prefix, ' ', vg.First, ' ', vg.Last, ' ', vg.Suffix) as `Name`, ifnull(vg.BirthDate, '') as `Birth Date`, "
        . " concat(vg.Address, ', ', vg.City, ', ', vg.County, ' ', vg.State, ' ', vg.Zip) as `Address`,  vg.Phone, vg.Email, vg.idPsg,"
        . " max(ifnull(s.Span_Start_Date, '')) as `Arrival`, ifnull(s.Span_End_Date, '') as `Departure` "
        . " from stays s
        join
    vguest_listing vg on vg.Id = s.idName
where $whExt ifnull(DATE(s.Span_End_Date), DATE(now())) > DATE('$start') and DATE(s.Span_Start_Date) < DATE('$end') "
            . " GROUP BY vg.Id";

    $stmt = $dbh->query($query);

    if (!$local) {

        $reportRows = 1;
        $file = 'GuestTransfer';
        $sml = OpenXML::createExcel('Guest Tracker', 'Guest Transfer');
    }

    $rows = array();
    $firstRow = TRUE;

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {


        if ($uS->county === FALSE) {
            unset($r['County']);
        }

        $transferIds[] = $r['Id'];

        if ($firstRow) {

            $firstRow = FALSE;

            if ($local === FALSE) {

                // build header
                $hdr = array();
                $n = 0;
                $noReturn = '';

                // HEader row
                $keys = array_keys($r);
                foreach ($keys as $k) {
                    $hdr[$n++] =  $k;
                }

                if ($noReturn != '') {
                    $hdr[$n++] =  $noReturn;
                }

                OpenXML::writeHeaderRow($sml, $hdr);
                $reportRows++;
            }
        }

        if ($local) {

            $r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'GuestEdit.php?id=' . $r['Id'] . '&psg=' . $r['idPsg']));

            if (isset($r['Birth Date']) && $r['Birth Date'] != '') {
                $r['Birth Date'] = date('c', strtotime($r['Birth Date']));
            }
            if ($r['Arrival'] != '') {
                $r['Arrival'] = date('c', strtotime($r['Arrival']));
            }
            if ($r['Departure'] != '') {
                $r['Departure'] = date('c', strtotime($r['Departure']));
            }

            if ($r['Patient'] != '') {
                $r['Patient'] = '&#x2714;';
            }

            $rows[] = $r;

        } else {

            $n = 0;
            $flds = array();


            foreach ($r as $key => $col) {

                if (($key == 'Arrival' or $key == 'Departure' || $key == 'Birth Date') && $col != '') {

                    $flds[$n++] = array('type' => "n",
                        'value' => PHPExcel_Shared_Date::PHPToExcel(new DateTime($col)),
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14);

                } else {
                    $flds[$n++] = array('type' => "s", 'value' => $col);
                }
            }

            $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
        }

    }

    if ($local) {

        $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
        return array('mkup' =>$dataTable, 'xfer'=>$transferIds);


    } else {

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();

    }
}


$mkTable = '';
$dataTable = '';
$paymentsTable = '';
$settingstable = '';
$searchTabel = '';
$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$errorMessage = '';
$calSelection = '19';
$transferIds = [];


$monthArray = array(
    1 => array(1, 'January'), 2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}


// Process report.
if (isset($_POST['btnHere']) || isset($_POST['btnGetPayments'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // gather input

    if (isset($_POST['selCalendar'])) {
        $calSelection = intval(filter_var($_POST['selCalendar'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['selIntMonth'])) {
        $months = filter_var_array($_POST['selIntMonth'], FILTER_SANITIZE_NUMBER_INT);
    }

    if (isset($_POST['selIntYear'])) {
        $year = intval(filter_var($_POST['selIntYear'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['stDate'])) {
        $txtStart = filter_var($_POST['stDate'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['enDate'])) {
        $txtEnd = filter_var($_POST['enDate'], FILTER_SANITIZE_STRING);
    }

   if ($calSelection == 20) {
        // fiscal year
        $adjustPeriod = new DateInterval('P' . $uS->fy_diff_Months . 'M');
        $startDT = new DateTime($year . '-01-01');
        $startDT->sub($adjustPeriod);
        $start = $startDT->format('Y-m-d');

        $endDT = new DateTime(($year + 1) . '-01-01');
        $end = $endDT->sub($adjustPeriod)->format('Y-m-d');

    } else if ($calSelection == 21) {
        // Calendar year
        $startDT = new DateTime($year . '-01-01');
        $start = $startDT->format('Y-m-d');

        $end = ($year + 1) . '-01-01';

    } else if ($calSelection == 18) {
        // Dates
        if ($txtStart != '') {
            $startDT = new DateTime($txtStart);
            $start = $startDT->format('Y-m-d');
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
            $end = $endDT->format('Y-m-d');
        }

    } else if ($calSelection == 22) {
        // Year to date
        $start = $year . '-01-01';

        $endDT = new DateTime($year . date('m') . date('d'));
        $endDT->add(new DateInterval('P1D'));
        $end = $endDT->format('Y-m-d');

    } else {
        // Months
        $interval = 'P' . count($months) . 'M';
        $month = $months[0];
        $start = $year . '-' . $month . '-01';

        $endDate = new DateTime($start);
        $endDate->add(new DateInterval($interval));

        $end = $endDate->format('Y-m-d');
    }


    if (isset($_POST['btnHere'])) {

        // Get HHK records result table.
        $results = getPeopleReport($dbh, $local, $start, $end, FALSE);
        $dataTable = $results['mkup'];
        $transferIds = $results['xfer'];


        // Create settings markup
        $sTbl = new HTMLTable();
        $sTbl->addBodyTr(HTMLTable::makeTh('Guest Transfer Timeframe', array('colspan'=>'4')));
        $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
        $settingstable = $sTbl->generateMarkup(array('style'=>'float:left;'));

        // Create search criteria markup
        $searchCriteria = TransferMembers::getSearchFields($dbh);

        $tr = '';
        foreach ($searchCriteria as $s) {
            $tr .= HTMLTable::makeTd($s);
        }

        $scTbl = new HTMLTable();
        $scTbl->addHeaderTr(HTMLTable::makeTh($serviceName . ' Search Criteria', array('colspan'=>count($searchCriteria))));
        $scTbl->addBodyTr($tr);
        $searchTabel = $scTbl->generateMarkup(array('style'=>'float:left; margin-left:2em;'));

        $mkTable = 1;

    } else if (isset($_POST['btnGetPayments'])) {

        $dataTable = getPaymentReport($dbh, $start, $end);

        $mkTable = 2;

    }

}



// Setups for the page.

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'5','multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>count($calOpts)));

$wsLogo = $wsConfig->getString('credentials', 'Logo_URI', '');
$wsLink = $wsConfig->getString('credentials', 'Login_URI', '');

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <style>
            .hhk-rowseparater { border-top: 2px #0074c7 solid !important; }
            #aLoginLink:hover {background-color: #337a8e; }
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo VERIFY_ADDRS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
<script type="text/javascript">
function updateLocal(id) {
    var postUpdate = $.post('ws_tran.php', {cmd:'rmvAcctId', id:id});

    postUpdate.done(function(incmg) {
        $('div#retrieve').empty();

        if (!incmg) {
            alert('Bad Reply from Server');
            return;
        }

        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        if (incmg.result) {
            flagAlertMessage(incmg.result, false);

        }
    });
}

function updateRemote(id, accountId) {

    var postUpdate = $.post('ws_tran.php', {cmd:'update', accountId:accountId, id:id});

    postUpdate.done(function(incmg) {
        $('#btnUpdate').val('Update Remote');
        if (!incmg) {
            alert('Bad Reply from Server');
            return;
        }

        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        if (incmg.warning) {

            var updteLocal = $('<input type="button" id="btnLocal" value="" />');
            $('#btnUpdate').hide();

            flagAlertMessage(incmg.warning, true);

            updteLocal.val('Remove Remote Account Id from Local Record');

            updteLocal.button().click(function () {
                $("#divAlert1").hide();
                if ($(this).val() === 'Working...') {
                    return;
                }
                $(this).val('Working...');

                updateLocal(id);
            });

            $('div#retrieve').prepend(updteLocal);

        } else if (incmg.result) {
            flagAlertMessage(incmg.result, false);
        }
    });
}

function transferRemote(transferIds) {
    var parms = {
        cmd: 'xfer',
        ids: transferIds
    };

    var posting = $.post('ws_tran.php', parms);
    posting.done(function(incmg) {
        $('#TxButton').val('Transfer').hide();
        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }
        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        if (incmg.data) {
            $('div#retrieve').empty();
            $('#divTable').empty().append($(incmg.data));
        }
    });

}

function transferPayments($btn, start, end) {

    var parms = {
        cmd: 'payments',
        st: start,
        en: end
    };

    var posting = $.post('ws_tran.php', parms);

    posting.done(function(incmg) {
        $btn.val('Transfer Payments');

        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }

        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        $('div#retrieve').empty();

        if (incmg.data) {
            $('#divTable').empty().append($(incmg.data)).show();
        }

        if (incmg.members) {
            $('#divMembers').empty().append($(incmg.members)).show();
        }

    });
}

function getRemote(item, source) {
    $('div#printArea').hide();
    $('#divPrintButton').hide();

    var posting = $.post('ws_tran.php', {cmd:'getAcct', src:source, accountId:item.id});
    posting.done(function(incmg) {
        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }
        try {
        incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        if (incmg.data) {
            $('div#retrieve').children().remove();
            $('div#retrieve').html(incmg.data);

            if (source === 'remote') {
                $('div#retrieve').prepend($('<h3>Remote Data</h3>'));
                $('#txtRSearch').val('');

            } else {

                var updteRemote = $('<input type="button" id="btnUpdate" value="" />');

                if (incmg.accountId === '') {
                    updteRemote.val('Transfer to Remote');
                    updteRemote.button().click(function () {
                        $("#divAlert1").hide();
                        if ($(this).val() === 'Working...') {
                            return;
                        }
                        $(this).val('Working...');

                        transferRemote([item.id]);
                    });
                } else if (incmg.accountId) {
                    updteRemote.val('Update Remote');
                    updteRemote.button().click(function () {
                        $("#divAlert1").hide();
                        if ($(this).val() === 'Working...') {
                            return;
                        }
                        $(this).val('Working...');
                        updateRemote(item.id, incmg.accountId);
                    });
                } else {
                    updteRemote = '';
                }

                $('div#retrieve').prepend($('<h3>Local (HHK) Data </h3>').append(updteRemote));
                $('#txtSearch').val('');
            }
        }
    });
}

$(document).ready(function() {
    var makeTable = '<?php echo $mkTable; ?>';
    var transferIds = <?php echo json_encode($transferIds); ?>;
    var start = '<?php echo $start; ?>';
    var end = '<?php echo $end; ?>';
    var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';

    $('#btnHere, #btnCustFields, #btnGetPayments').button();

    $('#printButton').button().click(function() {
        $("div#printArea").printArea();
    });

    if (makeTable === '1') {
        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#btnPay').hide();
        $('#divMembers').empty();

        $('#tblrpt').dataTable({
           'columnDefs': [
                {'targets': [4, 9, 10],
                 'type': 'date',
                 'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top"ilf>rt<"bottom"lp><"clear">'
        });

        $('#TxButton').button().show().click(function () {
            $("#divAlert1").hide();
            if ($('#TxButton').val() === 'Working...') {
                return;
            }
            $('#TxButton').val('Working...');
            transferRemote(transferIds);
        });

    } else if (makeTable === '2') {

        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#TxButton').hide();
        $('#divMembers').empty();

        $('#tblrpt').dataTable({
            'columnDefs': [
                {'targets': [4],
                 'type': 'date',
                 'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top"ilf>rt<"bottom"lp><"clear">'
        });

        $('#btnPay').button().show().click(function () {
            $("#divAlert1").hide();
            if ($(this).val() === 'Transferring ...') {
                return;
            }
            $(this).val('Transferring ...');

            transferPayments($(this), start, end);
        });
    }


    $('.ckdate').datepicker({
        yearRange: '-07:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });

    $('#selCalendar').change(function () {
        if ($(this).val() && $(this).val() != '19') {
            $('#selIntMonth').hide();
        } else {
            $('#selIntMonth').show();
        }
        if ($(this).val() && $(this).val() != '18') {
            $('.dates').hide();
        } else {
            $('.dates').show();
        }
    });

    $('#selCalendar').change();

    createAutoComplete($('#txtRSearch'), 3, {cmd: 'sch', mode: 'name'}, function (item) {getRemote(item, 'remote');}, false, '../house/ws_tran.php');
    createAutoComplete($('#txtSearch'), 3, {cmd: 'role', mode: 'mo'}, function (item) {getRemote(item, 'hhk');}, false);
});
 </script>
    </head>
    <body <?php if ($wInit->testVersion) { echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?>  <span style="font-size: .7em;"><a href="SetupNeonCRM.htm" target="_blank">(Instructions)</a></span></h2>
            <a id='aLoginLink' href="<?php echo $wsLink; ?>" style="float:left;margin-top:15px;margin-left:5px;margin-right:5px;padding-left:5px;padding-right:5px;" title="Click to log in."><div style="height:55px; width:130px; background: url(<?php echo $wsLogo; ?>) left top no-repeat; background-size:contain;"></div></a>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="GuestTransfer.php" method="post">
                   <table style="clear:left;float: left;">
                        <tr>
                            <th colspan="3">Time Period</th>
                        </tr>
                        <tr>
                            <th>Interval</th>
                            <th style="min-width:100px; ">Month</th>
                            <th>Year</th>
                        </tr>
                        <tr>
                            <td><?php echo $calSelector; ?></td>
                            <td><?php echo $monthSelector; ?></td>
                            <td><?php echo $yearSelector; ?></td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span class="dates" style="margin-right:.3em;">Start:</span>
                                <input type="text" value="<?php echo $txtStart; ?>" name="stDate" id="stDate" class="ckdate dates" style="margin-right:.3em;"/>
                                <span class="dates" style="margin-right:.3em;">End:</span>
                                <input type="text" value="<?php echo $txtEnd; ?>" name="enDate" id="enDate" class="ckdate dates"/></td>
                        </tr>
                    </table>
                    <table style="float:left;">
                        <tr>
                            <th><?php echo $serviceName; ?> <span style="font-weight: bold;">Last Name</span> Search</th>
                            <td><input id="txtRSearch" type="text" /></td>
                        </tr>
                        <tr>
                            <th>Local (HHK) Name Search</th>
                            <td><input id="txtSearch" type="text" /></td>
                        </tr>
                    </table>
                    <table style="width:100%; margin-top: 15px;">
                        <tr>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Get HHK Records"/></td>
                            <td><input type="submit" name="btnGetPayments" id="btnGetPayments" value="Get HHK Payments"/></td>
                        </tr>
                    </table>
                </form>
                <div id="retrieve"></div>
            </div>
            <div style="clear:both;"></div>

            <div id="divPrintButton" style="display:none;margin-top:6px;margin-bottom:3px;">
                <input id="printButton" value="Print" type="button" />
                <input id="TxButton" value="Transfer Guests" type="button" style="margin-left:2em;"/>
                <input id="btnPay" value="Transfer Payments" type="button" style="margin-left:2em;"/>
            </div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox hhk-visitdialog" style="float:left;display:none; font-size: .8em; padding: 5px; padding-bottom:25px;">
                <div style="margin-bottom:.8em; float:left;"><?php echo $settingstable . $searchTabel; ?></div>
                <div id="divTable">
                    <?php echo $dataTable; ?>
                </div>
                <div id="divMembers"></div>
            </div>
        </div>
    </body>
</html>
