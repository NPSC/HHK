<?php
use HHK\SysConst\WebPageCode;
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\AlertControl\AlertMessage;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\SecurityComponent;
use HHK\HTMLControls\HTMLContainer;
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLTable;
use HHK\Neon\TransferMembers;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLInput;
use HHK\ExcelHelper;
use HHK\sec\Labels;

/**
 * GuestTransfer.php
 * List and transfer guests to NEON
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


try {
    // Do not add CSP.
    $wInit = new WebInit(WebPageCode::Page, FALSE);
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$pageHeader = $wInit->pageHeading;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

$config = new Config_Lite(ciCFG_FILE);

$serviceName = $config->getString('webServices', 'Service_Name', '');
$webServices = $config->getString('webServices', 'ContactManager', '');

if ($serviceName != '' && $webServices != '') {
    $wsConfig = new Config_Lite(REL_BASE_DIR . 'conf' . DS .  $webServices);
} else {
    exit('<h4>HHK configuration error:  Web Services Configuration file is missing. Trying to open file name: ' . REL_BASE_DIR . 'conf' . DS .  $webServices . '</h4>');
}

if (function_exists('curl_version') === FALSE) {
    exit('<h4>PHP configuration error: cURL functions are missing. </h4>');
}

$isGuestAdmin = SecurityComponent::is_Authorized('guestadmin');

$labels = Labels::getLabels();

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

function getPeopleReport(\PDO $dbh, $start, $end, $extIdFlag = FALSE) {

    $whExt = '';
    if ($extIdFlag) {
        $whExt = "ifnull(vg.External_Id, '') = '' and ";
    }

    $uS = Session::getInstance();
    $transferIds = [];

    $query = "SELECT
    vg.External_Id as `External Id`,
    vg.Id,
    CASE
        WHEN vg.Relationship_Code = 'slf' THEN '&#x2714;'
        ELSE ''
    END AS `Patient`,
    CASE
		WHEN v.idPrimaryGuest = s.idName THEN 'Yes'
        ELSE ''
	END AS `Primary Guest`,
    CONCAT(vg.Prefix,
            ' ',
            vg.First,
            ' ',
            vg.Last,
            ' ',
            vg.Suffix) AS `Name`,
    IFNULL(vg.BirthDate, '') AS `Birth Date`,
    CONCAT(vg.Address,
            ', ',
            vg.City,
            ', ',
            vg.County,
            ' ',
            vg.State,
            ' ',
            vg.Zip) AS `Address`,
    vg.Phone,
    vg.Email,
    vg.idPsg,
    MAX(IFNULL(s.Span_Start_Date, '')) AS `Arrival`,
    IFNULL(s.Span_End_Date, '') AS `Departure`
FROM
    stays s
        JOIN
    vguest_listing vg ON vg.Id = s.idName
        JOIN
    visit v ON s.idVisit = v.idVisit
        AND s.Visit_Span = v.Span
where $whExt ifnull(DATE(s.Span_End_Date), DATE(now())) >= DATE('$start') and DATE(s.Span_Start_Date) < DATE('$end')
GROUP BY vg.Id ORDER BY vg.idPsg";

    $stmt = $dbh->query($query);

    $rows = array();
    $firstRow = TRUE;
    $hdr = array();
    
    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $transferIds[] = $r['Id'];


        if ($r['Address'] == ', ,   ') {
        	$r['Address'] = '';
        }
        
        // Transfer opt-out
        if ($r['External Id'] == '') {
        	
       		if ($r['Email'] !== '' || $r['Address'] !== '') {
       			$r['External Id'] = HTMLInput::generateMarkup('', array('name'=>'tf_'.$r['Id'], 'class'=>'hhk-txCbox', 'data-txid'=>$r['Id'], 'type'=>'checkbox', 'checked'=>'checked'));
       		} else {
       			$r['External Id'] = HTMLInput::generateMarkup('', array('name'=>'tf_'.$r['Id'], 'class'=>'hhk-txCbox', 'data-txid'=>$r['Id'], 'type'=>'checkbox'));
        	}
        }
            
        $r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'GuestEdit.php?id=' . $r['Id'] . '&psg=' . $r['idPsg']));
        
        $rows[] = $r;

    }

    $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
    return array('mkup' =>$dataTable, 'xfer'=>$transferIds);

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
        $results = getPeopleReport($dbh, $start, $end, FALSE);
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
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, '2010', $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>count($calOpts)));

$wsLogo = $wsConfig->getString('credentials', 'Logo_URI', '');
$wsLink = $wsConfig->getString('credentials', 'Login_URI', '');

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo FAVICON; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo NOTY_CSS; ?>
        <style>
            .hhk-rowseparater { border-top: 2px #0074c7 solid !important; }
            #aLoginLink:hover {background-color: #337a8e; }
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

    </head>
    <body <?php if ($wInit->testVersion) { echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?>  <span style="font-size: .7em;"><a href="SetupNeonCRM.htm" target="_blank">(Instructions)</a></span></h2>
            <a id='aLoginLink' href="<?php echo $wsLink; ?>" style="float:left;margin-top:15px;margin-left:5px;margin-right:5px;padding-left:5px;padding-right:5px;" title="Click to log in."><span style="height:55px; width:130px; background: url(<?php echo $wsLogo; ?>) left top no-repeat; background-size:contain;"></span></a>

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
        <input id='hmkTable' type="hidden" value='<?php echo $mkTable; ?>'/>
        <input id='htransferIds' type="hidden" value='<?php echo json_encode($transferIds); ?>'/>
        <input id='hstart' type="hidden" value='<?php echo $start; ?>'/>
        <input id='hend' type="hidden" value='<?php echo $end; ?>'/>
        <input id='hdateFormat' type="hidden" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>'/>
        
        <script type="text/javascript" src="<?php echo GUESTTRANSFER_JS; ?>"></script>
        
    </body>
</html>
