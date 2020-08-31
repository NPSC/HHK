<?php

use HHK\Config_Lite\Config_Lite;
use HHK\sec\{Session, WebInit};
use HHK\AlertControl\AlertMessage;
use HHK\SysConst\GLTableNames;
use HHK\ColumnSelectors;
use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLSelector};
use HHK\SysConst\PaymentStatusCode;
use HHK\Payment\Receipt;
use HHK\House\Report\PaymentReport;
use HHK\ExcelHelper;

/**
 * PaymentReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();
$labels = new Config_Lite(LABEL_FILE);
$menuMarkup = $wInit->generatePageMenu();

$config = new Config_Lite(ciCFG_FILE);

$mkTable = '';  // var handed to javascript to make the report table or not.
$hdrTbl = '';
$dataTable = '';

$hospitalSelections = array();
$assocSelections = array();
$statusSelections = array();
$payTypeSelections = array();
$calSelection = '19';
$gwList = array();
$gwSelector = '';
$gwSelections = array();

$year = date('Y');
$months = array(date('n'));       // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';


$monthArray = array(
    1 => array(1, 'January'),
    2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}

$statusList = readGenLookupsPDO($dbh, 'Payment_Status');

$payTypes = array();

foreach ($uS->nameLookups[GLTableNames::PayType] as $p) {
    if ($p[2] != '') {
        $payTypes[$p[2]] = array($p[2], $p[1]);
    }
}

// Hospital and association lists
$hospList = $uS->guestLookups[GLTableNames::Hospital];
$hList = array();
$aList = array();

if (count($hospList) > 0) {
    foreach ($hospList as $h) {
        if ($h[2] == 'h') {
            $hList[$h[0]] = array(0=>$h[0], 1=>$h[1]);
        } else if ($h[2] == 'a' && $h[1] != '(None)') {
            $aList[$h[0]] = array(0=>$h[0], 1=>$h[1]);
        }
    }
}

// Payment gateway lists
$gwstmt = $dbh->query("Select cc_name from cc_hosted_gateway where Gateway_Name = '" . $uS->PaymentGateway . "' and cc_name not in ('Production', 'Test', '')");
$gwRows = $gwstmt->fetchAll(PDO::FETCH_NUM);

if (count($gwRows) > 1) {
	
	foreach ($gwRows as $g) {
		$gwList[$g[0]] = array(0=>$g[0], 1=>ucfirst($g[0]));
	}
}

// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms, DT Type
$cFields[] = array('Payor Last', 'Last', 'checked', '', 'string', '20', array());
$cFields[] = array("Payor First", 'First', 'checked', '', 'string', '20', array());
$cFields[] = array("Date", 'Payment_Date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("Time", 'Payment_Timestamp', 'checked', '', 'h:mm:ss AM/PM;@', '15', array(), 'time');
$cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '10', array());
$cFields[] = array("Room", 'Title', 'checked', '', 'string', '15', array('style'=>'text-align:center;'));

if ((count($hospList)) > 1) {
    $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'idHospital', 'checked', '', 'string', '25', array());
}

$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient')." Last", 'Patient_Last', '', '', 'string', '20', array());
$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient')." First", 'Patient_First', '', '', 'string', '20', array());
$cFields[] = array("Pay Type", 'Pay_Type', 'checked', '', 'string', '15', array());
$cFields[] = array("Detail", 'Detail', 'checked', '', 'string', '15', array());
$cFields[] = array("Status", 'Status', 'checked', '', 'string', '15', array());
$cFields[] = array("Original Amount", 'Orig_Amount', 'checked', '', 'dollar', '15' , array('style'=>'text-align:right;'));
$cFields[] = array("Amount", 'Amount', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));

// Show payment gateway
if (count($gwList) > 1) {
	$cFields[] = array('Location', 'Merchant', 'checked', '', 'string', '20', array());
}

// Show External Id (external payment record id)
if ($config->getString('webServices', 'Service_Name', '') != '') {
    $cFields[] = array('External Id', 'Payment_External_Id', '', '', 'string', '15', array());
}

$cFields[] = array("By", 'By', 'checked', '', 'string', '20', array());
$cFields[] = array("Notes", 'Notes', 'checked', '', 'string', '20', array());

$colSelector = new ColumnSelectors($cFields, 'selFld');

if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    $headerTable = new HTMLTable();
    $headerTable->addBodyTr(HTMLTable::makeTd('Report Generated: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y')));

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);

    if (isset($_POST['selIntMonth'])) {
        $months = filter_var_array($_POST['selIntMonth'], FILTER_SANITIZE_NUMBER_INT);
    }

    if (isset($_POST['selCalendar'])) {
        $calSelection = intval(filter_var($_POST['selCalendar'], FILTER_SANITIZE_NUMBER_INT), 10);
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

    if (isset($_POST['selAssoc'])) {
        $reqs = $_POST['selAssoc'];
        if (is_array($reqs)) {
            $assocSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if (isset($_POST['selHospital'])) {
        $reqs = $_POST['selHospital'];
        if (is_array($reqs)) {
            $hospitalSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if (isset($_POST['selPayStatus'])) {
        $reqs = $_POST['selPayStatus'];
        if (is_array($reqs)) {
            $statusSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if (isset($_POST['selPayType'])) {
    	// Payment Types
    	$reqs = $_POST['selPayType'];
    	
    	if (is_array($reqs)) {
    		$payTypeSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
    	}
    }
    
    if (isset($_POST['selGateway'])) {
    	// Payment Types
    	$reqs = $_POST['selGateway'];
    	
    	if (is_array($reqs)) {
    		$gwSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
    	}
    }
    

    // Determine time span
    if ($calSelection == 20) {
        // fiscal year
        $adjustPeriod = new DateInterval('P' . $uS->fy_diff_Months . 'M');
        $startDT = new DateTime($year . '-01-01');

        $start = $startDT->sub($adjustPeriod)->format('Y-m-d');

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
        } else {
            $startDT = new DateTime();
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
        } else {
            $endDT = new DateTime();
        }

        $start = $startDT->format('Y-m-d');
        $end = $endDT->format('Y-m-d');

    } else if ($calSelection == 22) {
        // Year to date
        $start = date('Y') . '-01-01';

        $endDT = new DateTime();
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



    $whDates = " and (CASE WHEN lp.Payment_Status = 'r' THEN DATE(lp.Payment_Last_Updated) ELSE DATE(lp.Payment_Date) END) < DATE('$end') and (CASE WHEN lp.Payment_Status = 'r' THEN DATE(lp.Payment_Last_Updated) ELSE DATE(lp.Payment_Date) END) >= DATE('$start') ";

    $endDT = new DateTime($end);
    $endDT->sub(new DateInterval('P1D'));

    $headerTable->addBodyTr(HTMLTable::makeTd('Reporting Period: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start)) . ' thru ' . date('M j, Y', strtotime($end))));

    // Hospitals
    $whHosp = '';
    $hdrHosps = 'All';
    foreach ($hospitalSelections as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp = $a;
                $hdrHosps = $hList[$a][1];
            } else {
                $whHosp .= ",". $a;
                $hdrHosps .= ", ". $hList[$a][1];
            }
        }
    }

    $whAssoc = '';
    $hdrAssocs = 'All';
    foreach ($assocSelections as $a) {
        if ($a != '') {
            if ($whAssoc == '') {
                $whAssoc = $a;
                $hdrAssocs = $aList[$a][1];
            } else {
                $whAssoc .= ",". $a;
                $hdrAssocs .= ", ". $aList[$a][1];
            }
        }
    }

    if ($whHosp != '') {
        $whHosp = " and hs.idHospital in (".$whHosp.") ";
    }
    if ($whAssoc != '') {
        $whAssoc = " and hs.idAssociation in (".$whAssoc.") ";
    }

    $headerTable->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrHosps));

    if (count($aList) > 0) {
        $headerTable->addBodyTr(HTMLTable::makeTd('Associations: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrAssocs));
    }


    $whStatus = '';
    $payStatusText = '';
    $rtnIncluded = FALSE;

    foreach ($statusSelections as $s) {
        if ($s != '') {
            // Set up query where part.
            if ($whStatus == '') {
                $whStatus = "'" . $s . "'";
            } else {
                $whStatus .= ",'".$s . "'";
            }

            if ($s == PaymentStatusCode::Retrn) {
                $rtnIncluded = TRUE;
            }

            if ($payStatusText == '') {
                $payStatusText = $statusList[$s][1];
            } else {
                $payStatusText .= ', ' . $statusList[$s][1];
            }
        }
    }

    if ($whStatus != '') {

        if ($rtnIncluded) {
            $whStatus = " and (lp.Payment_Status in (" . $whStatus . ") or (lp.Is_Refund = 1 && lp.Payment_Status = '" . PaymentStatusCode::Paid. "')) ";
        } else {
            $whStatus = " and lp.Payment_Status in (" . $whStatus . ") ";
        }

    } else {
        $payStatusText = 'All';
    }

    $headerTable->addBodyTr(HTMLTable::makeTd('Pay Statuses: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($payStatusText));


    $whType = '';
    $payTypeText = '';
    foreach ($payTypeSelections as $s) {
        if ($s != '') {
            // Set up query where part.
            if ($whType == '') {
            	$whType = "'" . $s . "'";
            } else {
            	$whType .= ",'".$s . "'";
            }

            if ($payTypeText == '') {
                $payTypeText .= (isset($payTypes[$s][1]) ? $payTypes[$s][1] : '');
            } else {

                $payTypeText .= (isset($payTypes[$s][1]) ? ', ' . $payTypes[$s][1] : '');
            }
        }
    }

    if ($whType != '') {
        $whType = " and lp.idPayment_Method in (" . $whType . ") ";
    } else {
        $payTypeText = 'All';
    }

    $headerTable->addBodyTr(HTMLTable::makeTd('Pay Types: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($payTypeText));

    $whGw = '';
    $gwText = '';
    foreach ($gwSelections as $s) {
    	if ($s != '') {
    		// Set up query where part.
    		if ($whGw == '') {
    			$whGw = " '" . $s . "' ";
    		} else {
    			$whGw .= ", '" . $s . "' ";
    		}
    		
    		if ($gwText == '') {
    			$gwText .= (isset($gwList[$s][1]) ? $gwList[$s][1] : '');
    		} else {
    			
    			$gwText .= (isset($gwList[$s][1]) ? ', ' .$gwList[$s][1] : '');
    		}
    	}
    }
    
    if ($whGw != '') {
    	$whGw = " and lp.`Merchant` in (" . $whGw . ") ";
    } else {
    	$gwText = 'All';
    }
    
    $headerTable->addBodyTr(HTMLTable::makeTd('Locations: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($gwText));
    
    $query = "Select
    lp.*,
    ifnull(n.Name_First, '') as `First`,
    ifnull(n.Name_Last, '') as `Last`,
    ifnull(n.Company, '') as `Company`,
    ifnull(r.Title, '') as `Room`,
    ifnull(hs.idHospital, 0) as idHospital,
    ifnull(hs.idAssociation, 0) as idAssociation,
    ifnull(np.Name_Last, '') as `Patient_Last`,
    ifnull(np.Name_First, '') as `Patient_First`,
    DATE(hs.Arrival_Date) as Hosp_Arrival
from
    vlist_pments lp
        left join
    `name` n ON lp.Sold_To_Id = n.idName
        left join
    visit v on lp.Order_Number = v.idVisit and lp.Suborder_Number = v.Span
	left join
    resource r ON v.idResource = r.idResource
        left join
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
        left join
    name np on hs.idPatient = np.idName
where lp.idPayment > 0
 $whHosp $whAssoc $whDates $whStatus $whType $whGw ";

    $tbl = null;
    $sml = null;
    $reportRows = 0;
    $hdr = array();


    $fltrdTitles = $colSelector->getFilteredTitles();
    $fltrdFields = $colSelector->getFilteredFields();

    if ($local) {
        $tbl = new HTMLTable();
        $th = '';

        foreach ($fltrdTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }
        $tbl->addHeaderTr($th);

    } else {


        $reportRows = 1;
        $file = 'PaymentReport';
        
        $writer = new ExcelHelper($file);
        $writer->setAuthor($uS->username);
        $writer->setTitle("Payment Report");
        
        // build header
        $hdr = array();
        $colWidths = array();
        

        $hdr["Payor Id"] = "string";
        $hdr["Company"] = 'string';
        $colWidths = array("10", "20");

        foreach ($fltrdFields as $field) {
            $hdr[$field[0]] = $field[4]; //set column header name and type;
            $colWidths[] = $field[5]; //set column width
        }

        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
        $reportRows++;
    }

    $name_lk = $uS->nameLookups;
    $name_lk['Pay_Status'] = readGenLookupsPDO($dbh, 'Pay_Status');
    $uS->nameLookups = $name_lk;
    $total = 0;

    
    // Now the data ...
    $stmt = $dbh->query($query);
    $invoices = Receipt::processPayments($stmt, array('First', 'Last', 'Company', 'Room', 'idHospital', 'idAssociation', 'Patient_Last', 'Patient_First', 'Hosp_Arrival'));

    foreach ($invoices as $r) {

        // Payments
        foreach ($r['p'] as $p) {

            // Hospital
            $hospital = '';

            if ($r['i']['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['i']['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['i']['idAssociation']][1] != '(None)') {
                $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['i']['idAssociation']][1] . ' / ';
            }
            if ($r['i']['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['i']['idHospital']])) {
                $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['i']['idHospital']][1];
            }


            PaymentReport::doMarkupRow($fltrdFields, $r, $p, $local, $hospital, $total, $tbl, $writer, $hdr, $reportRows, $uS->subsidyId);

        }
    }



    // Finalize and print.
    if ($local) {

        $headerTable->addBodyTr(HTMLTable::makeTd('Total Amount: ', array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format($total,2), array('style'=>'font-weight:bold;')));

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display'));
        $mkTable = 1;
        $hdrTbl = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Payment Report', array('style'=>'margin-top: .5em;'))
                . $headerTable->generateMarkup();

    } else {
        $writer->download();
    }

}

// Setups for the page.
if (count($aList) > 0) {
$assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($aList, $assocSelections),
                array('name'=>'selAssoc[]', 'size'=>'3', 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
}
$hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($hList, $hospitalSelections),
                array('name'=>'selHospital[]', 'size'=>'5', 'multiple'=>'multiple', 'style'=>'min-width:60px;'));

$monSize = 5;
if (count($hList) > 5) {

    $monSize = count($hList);

    if ($monSize > 12) {
        $monSize = 12;
    }
}

// Prepare controls

$statusSelector = HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($statusList, $statusSelections), array('name' => 'selPayStatus[]', 'size' => '7', 'multiple' => 'multiple'));


$payTypeSelector = HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($payTypes, $payTypeSelections), array('name' => 'selPayType[]', 'size' => '5', 'multiple' => 'multiple'));

$gwSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($gwList, $gwSelections), array('name' => 'selGateway[]', 'multiple' => 'multiple', 'size'=>(count($gwList) + 1)));

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'12', 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, '2010', $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'5'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var makeTable = '<?php echo $mkTable; ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');

        $('#btnHere, #btnExcel, #cbColClearAll, #cbColSelAll').button();
        $('.ckdate').datepicker({
            yearRange: '-05:+01',
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
                $('#selIntYear').show();
            } else {
                $('.dates').show();
                $('#selIntYear').hide();
            }
        });
        $('#selCalendar').change();
        // disappear the pop-up room chooser.
        $(document).mousedown(function (event) {
            var target = $(event.target);
            if ($('div#pudiv').length > 0 && target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
                $('div#pudiv').remove();
            }
        });
        $('#cbColClearAll').click(function () {
            $('#selFld option').each(function () {
                $(this).prop('selected', false);
            });
        });
        $('#cbColSelAll').click(function () {
            $('#selFld option').each(function () {
                $(this).prop('selected', true);
            });
        });
        $('#btnHere').click(function () {
            $('#rptFeeLoading').show();
        })
        if (makeTable === '1') {
            $('#rptFeeLoading').hide();
            $('div#printArea').css('display', 'block');
            $('#tblrpt').dataTable({
                'columnDefs': [
                    {'targets': columnDefs,
                     'type': 'date',
                     'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                    }
                 ],
                "displayLength": 50,
                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                "dom": '<"top"ilf>rt<"bottom"ilp><"clear">'
            });

            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
            $('#tblrpt').on('click', '.invAction', function (event) {
                invoiceAction($(this).data('iid'), 'view', event.target.id, '', true);
            });
        }
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="PaymentReport.php" method="post">
                    <table style="float: left;">
                        <tr>
                            <th colspan="3">Time Period</th>
                        </tr>
                        <tr>
                            <th>Interval</th>
                            <th style="min-width:100px; ">Month</th>
                            <th>Year</th>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;"><?php echo $calSelector; ?></td>
                            <td><?php echo $monthSelector; ?></td>
                            <td style="vertical-align: top;"><?php echo $yearSelector; ?></td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span class="dates" style="margin-right:.3em;">Start:</span>
                                <input type="text" value="<?php echo $txtStart; ?>" name="stDate" id="stDate" class="ckdate dates" style="margin-right:.3em;"/>
                                <span class="dates" style="margin-right:.3em;">End:</span>
                                <input type="text" value="<?php echo $txtEnd; ?>" name="enDate" id="enDate" class="ckdate dates"/></td>
                        </tr>
                    </table>
                    <?php if ((count($aList) + count($hList)) > 1) { ?>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2"><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?> Filter</th>
                        </tr>
                        <?php if (count($aList) > 0) { ?><tr>
                            <th>Associations</th>
                            <th><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>s</th>
                        </tr><?php } ?>
                        <tr>
                            <?php if (count($aList) > 0) { ?><td style="vertical-align: top;"><?php echo $assocs; ?></td><?php } ?>
                            <td><?php echo $hospitals; ?></td>
                        </tr>
                    </table><?php } ?>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2">Pay Type</th>
                        </tr>
                        <tr>
                           <td><?php echo $payTypeSelector; ?></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2">Pay Status</th>
                        </tr>
                        <tr>
                           <td><?php echo $statusSelector; ?></td>
                        </tr>
                    </table>
                    <?php if (count($gwList) > 1) { ?><table style="float: left;">
                        <tr>
                            <th colspan="2">Location</th>
                        </tr>
                        <tr>
                           <td><?php echo $gwSelector; ?></td>
                        </tr>
                    </table>
                    <?php } echo $columSelector; ?>
                   <table style="width:100%; clear:both;">
                        <tr>
                            <td style="width:50%;"></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"><p id="rptFeeLoading" class="ui-state-active" style="font-size: 1.1em; float:left; display:none; margin:20px; padding: 5px;">Loading Payment Report...</p></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox" style="display:none; font-size: .9em; padding: 5px; padding-bottom:25px;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                    <?php echo $hdrTbl; ?>
                </div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
