<?php
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\Config_Lite\Config_Lite;
use HHK\AlertControl\AlertMessage;
use HHK\HTMLControls\HTMLContainer;
use HHK\SysConst\VolMemberType;
use HHK\HTMLControls\HTMLTable;
use HHK\ColumnSelectors;
use HHK\SysConst\ItemId;
use HHK\Purchase\TaxedItem;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLInput;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\House\Report\ReportFieldSet;

/**
 * ItemReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();


$labels = Labels::getLabels();

function doMarkupRow($fltrdFields, $r, $isLocal, $invoice_Statuses, $diagnoses, $locations, &$total, &$tbl, &$writer, $hdr, &$reportRows, $subsidyId, $returnId) {

    $amt = $r['Amount'];

    $payStatusAttr = array();
    $attr['style'] = 'text-align:right;';


    $invNumber = $r['Invoice_Number'];

    if ($invNumber != '') {

        $iAttr = array('href'=>'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'style'=>'float:left;', 'target'=>'_blank');

        if ($r['Invoice_Deleted'] > 0) {
            $iAttr['style'] .= 'color:red;';
            $iAttr['title'] = 'Invoice is Deleted.';
        } else if ($r['Balance'] != 0) {

            $iAttr['title'] = 'Partial payment.';
            $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
        }

        $invNumber = HTMLContainer::generateMarkup('a', $invNumber, $iAttr)
            .HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicon'.$r['idInvoice_Line'], 'data-stat'=>'view', 'data-iid'=>$r['idInvoice'], 'style'=>'cursor:pointer;', 'title'=>'View Items'));
    }

    $invoiceMkup = HTMLContainer::generateMarkup('span', $invNumber, array("style"=>'white-space:nowrap'));

    $dateDT = new DateTime($r['Invoice_Date']);

    $invoiceStatus = '';
    if (isset($invoice_Statuses[$r['Status']])) {
        $invoiceStatus = $invoice_Statuses[$r['Status']][1];
    }

    // Names
    if ($r['Sold_To_Id'] == $subsidyId) {
        $company = $r['Company'];
        $payorFirst = '';
        $payorLast = '';
    } else if ($r['Billing_Agent'] == VolMemberType::BillingAgent || $returnId = $r['Sold_To_Id']) {
        $company = $r['Company'];
        $payorFirst = $r['Name_First'];
        $payorLast = $r['Name_Last'];
    } else {
    	$payorLast = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>'GuestEdit.php?id=' . $r['Sold_To_Id'], 'title'=>'Click to go to the '.$labels->getString('MemberType', 'visitor', 'Guest'). ' Edit page.'));
        $payorFirst = $r['Name_First'];
        $company = '';
    }

    $g = array(
        'vid'=>$r['Order_Number'] . '-' . $r['Suborder_Number'],
        'Company'=>$company,
        'Last'=>$payorLast,
        'First'=>$payorFirst,
        'Status' => $invoiceStatus,
        'Diagnosis' => (isset($diagnoses[$r['Diagnosis']]) ? $diagnoses[$r['Diagnosis']][1] : ''),
        'Location' => (isset($locations[$r['Location']]) ? $locations[$r['Location']][1] : ''),
        'Description' => $r['Description'],
        'Invoice_Number' => $r['Invoice_Number'],
        'Amount' => $amt,
    );

    $total += $amt;

    if ($isLocal) {

        $g['Amount'] = HTMLContainer::generateMarkup('span', number_format($amt, 2), $attr);
        $g['Invoice_Number'] = $invoiceMkup;
        $g['Status'] = HTMLContainer::generateMarkup('span', $invoiceStatus, $payStatusAttr);
        $g['Date'] = $dateDT->format('c');

        $tr = '';
        foreach ($fltrdFields as $f) {
            $tr .= HTMLTable::makeTd($g[$f[1]], $f[6]);
        }

        $tbl->addBodyTr($tr);

    } else {

        $g['Date'] = $r['Invoice_Date'];

        foreach ($fltrdFields as $f) {
            $flds[] = $g[$f[1]];
        }

        $row = $writer->convertStrings($hdr, $flds);
        $writer->writeSheetRow("Sheet1", $row);
        
    }

}

$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTableMu = '';
$dataTable = '';

$statusSelections = array();

$itemSelections = array();
$calSelection = '19';
$showDeleted = FALSE;
$diagSelections = array();

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

$statusList = readGenLookupsPDO($dbh, 'Invoice_Status');



// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms
$cFields[] = array('Visit Id', 'vid', 'checked', '', 'string', '15', array());
$cFields[] = array("Organization", 'Company', 'checked', '', 'string', '20', array());
$cFields[] = array('Last', 'Last', 'checked', '', 'string', '20', array());
$cFields[] = array("First", 'First', 'checked', '', 'string', '20', array());
$cFields[] = array("Date", 'Date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15', array());
$cFields[] = array("Description", 'Description', 'checked', '', 'string', '20', array());

$locations = readGenLookupsPDO($dbh, 'Location');
if (count($locations) > 0) {
    $cFields[] = array($labels->getString('statement', 'location', 'Location'), 'Location', '', '', 'string', '20', array());
}

$diags = readGenLookupsPDO($dbh, 'Diagnosis');
if (count($diags) > 0) {
    $cFields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', '', '', 'string', '20', array());
}

$cFields[] = array("Status", 'Status', 'checked', '', 'string', '15', array());
$cFields[] = array("Amount", 'Amount', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));
//$cFields[] = array("By", 'By', 'checked', '', 's', '', array());

$fieldSets = ReportFieldSet::listFieldSets($dbh, 'item', true);
$fieldSetSelection = (isset($_REQUEST['fieldset']) ? $_REQUEST['fieldset']: '');
$colSelector = new ColumnSelectors($cFields, 'selFld', true, $fieldSets, $fieldSetSelection);
$defaultFields = array();
foreach($cFields as $field){
    if($field[2] == 'checked'){
        $defaultFields[] = $field[1];
    }
}

// Items
$addnlCharges = readGenLookupsPDO($dbh, 'Addnl_Charge');

$stmt = $dbh->query("SELECT idItem, Description, Percentage, Last_Order_Id from item where Deleted = 0");
$itemList = array();

while($r = $stmt->fetch(PDO::FETCH_NUM)) {

    if ($r[0] == ItemId::LodgingDonate) {
        $r[1] = "Lodging Donation";
    } else if ($r[0] == ItemId::AddnlCharge) {
        $r[1] = "Additional Charges";
    }

    if ($r[2] != 0) {
        $r[1] .= ' '.TaxedItem::suppressTrailingZeros($r[2]);

        if ($r[3] != 0) {
            $r[2] = 'Old Rates';
        } else {
            $r[2] = '';
        }
    } else {
        $r[2] = '';
    }

    if ($r[0] == ItemId::DepositRefund && $uS->KeyDeposit === FALSE) {
        continue;
    } else if ($r[0] == ItemId::KeyDeposit && $uS->KeyDeposit === FALSE) {
        continue;
    } else if ($r[0] == ItemId::VisitFee && $uS->VisitFee === FALSE) {
        continue;
    } else if ($r[0] == ItemId::AddnlCharge && count($addnlCharges) == 0) {
        continue;
    } else if ($r[0] == ItemId::InvoiceDue) {
        continue;
    }

    $itemList[$r[0]] = $r;
}



if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    $headerTable = new HTMLTable();
    $headerTable->addBodyTr(HTMLTable::makeTd('Report Generated: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y')));

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    if (isset($_POST['cbShoDel'])) {
        $showDeleted = TRUE;
    }

    if (isset($_POST['selDiag'])) {

        $reqs = $_POST['selDiag'];

        if (is_array($reqs)) {
            $diagSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }


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

    if (isset($_POST['selPayStatus'])) {
        $reqs = $_POST['selPayStatus'];
        if (is_array($reqs)) {
            $statusSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if (isset($_POST['selItems'])) {
        $reqs = $_POST['selItems'];
        if (is_array($reqs)) {
            $itemSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }


    // Determine time span
    if ($calSelection == 20) {
        // fiscal year
        $adjustPeriod = new DateInterval('P' . $uS->fy_diff_Months . 'M');
        $startDT = new DateTime($year . '-01-01');

        $start = $startDT->sub($adjustPeriod)->format('Y-m-d 00:00:00');

        $endDT = new DateTime(($year + 1) . '-01-01');
        $end = $endDT->sub($adjustPeriod)->format('Y-m-d 00:00:00');

    } else if ($calSelection == 21) {
        // Calendar year
        $startDT = new DateTime($year . '-01-01');
        $start = $startDT->format('Y-m-d 00:00:00');

        $end = ($year + 1) . '-01-01 00:00:00';

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

        $end = $endDT->add(new DateInterval('P1D'))->format('Y-m-d 00:00:00');


    } else {
        // Months
        $interval = 'P' . count($months) . 'M';
        $month = $months[0];
        $start = $year . '-' . $month . '-01 00:00:00';

        $endDate = new DateTime($start);
        $endDate->add(new DateInterval($interval));

        $end = $endDate->format('Y-m-d 00:00:00');
    }



    $whDates = " and DATE(i.Invoice_Date) < DATE('$end') and DATE(i.Invoice_Date) >= DATE('$start') ";

    $endDT = new DateTime($end);
    $endDT->sub(new DateInterval('P1D'));

    $headerTable->addBodyTr(HTMLTable::makeTd('Reporting Period: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start)) . ' thru ' . date('M j, Y', strtotime($end))));



    $whStatus = '';
    $payStatusText = '';
    foreach ($statusSelections as $s) {
        if ($s != '') {
            // Set up query where part.
            if ($whStatus == '') {
                $whStatus = "'" . $s . "'";
            } else {
                $whStatus .= ",'".$s . "'";
            }

            if ($payStatusText == '') {
                $payStatusText = $statusList[$s][1];
            } else {
                $payStatusText .= ', ' . $statusList[$s][1];
            }
        }
    }

    if ($whStatus != '') {
        $whStatus = " and i.Status in (" . $whStatus . ") ";
    } else {
        $payStatusText = 'All';
    }

    $headerTable->addBodyTr(HTMLTable::makeTd('Invoice Statuses: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($payStatusText));

    if ($showDeleted) {
        $whDeleted = ' 1=1 ';
    } else {
        $whDeleted = ' i.Deleted = 0 and il.Deleted = 0 ';
    }


    $whItem = '';
    $itemText = '';
    foreach ($itemSelections as $s) {
        if ($s != '') {
            // Set up query where part.
            if ($whItem == '') {
                $whItem = $s ;
            } else {
                $whItem .= ",".$s;
            }

            if ($itemText == '') {
                $itemText .= $itemList[$s][1];
            } else {
                $itemText .= ', ' . $itemList[$s][1];
            }
        }
    }

    if ($whItem != '') {
        $whItem = " and il.Item_Id in (" . $whItem . ") ";
    } else {
        $itemText = 'All';
    }

    $headerTable->addBodyTr(HTMLTable::makeTd('Items: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($itemText));

    $whDiags = '';
    $tdDiags = '';

    foreach ($diagSelections as $a) {
        if ($a != '') {
            if ($whDiags == '') {
                $whDiags .= "'" . $a . "'";
                $tdDiags .= $diags[$a][1];
            } else {
                $whDiags .= ",'". $a . "'";
                $tdDiags .= ', ' . $diags[$a][1];
            }
        }
    }

    if ($whDiags != '') {
        $whDiags = " and hs.Diagnosis in (".$whDiags.") ";
    } else {
        $tdDiags = 'All';
    }

    $headerTable->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'diagnosis', 'Diagnosis') . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdDiags));

        $query = "select
    il.idInvoice_Line,
    i.idInvoice,
    i.Invoice_Number,
    i.Delegated_Invoice_Id,
    i.`Amount` as `Invoice_Amount`,
    i.Sold_To_Id,
    i.idGroup,
    i.Order_Number,
    i.Suborder_Number,
    i.Invoice_Date,
    i.`Status`,
    i.Carried_Amount,
    i.`Balance`,
    i.`Deleted` as `Invoice_Deleted`,
    il.`Price`,
    il.`Amount`,
    il.`Quantity`,
    il.`Description`,
    il.Item_Id,
    il.Period_Start,
    il.Period_End,
    il.`Deleted` as `Line_Deleted`,
    ifnull(hs.Diagnosis, '') as `Diagnosis`,
    ifnull(hs.Location, '') as `Location`,
    ifnull(n.Name_Last, '') as `Name_Last`,
    ifnull(n.Name_First, '') as `Name_First`,
    ifnull(n.`Company`, '') as `Company`,
    ifnull(nv.Vol_Code, '') as `Billing_Agent`
from
    invoice_line il join invoice i ON il.Invoice_Id = i.idInvoice
    left join `name` n on i.Sold_To_Id = n.idName
    left join visit v on i.Order_Number = v.idVisit and i.Suborder_Number = v.Span
    left join hospital_stay hs on hs.idHospital_stay = v.idHospital_stay
    left join name_volunteer2 nv on nv.idName = n.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '" . VolMemberType::BillingAgent . "'
where $whDeleted  $whDates  $whItem and il.Item_Id != 5  $whStatus $whDiags order by i.idInvoice, il.idInvoice_Line";


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
        $file = 'ItemReport';
        $writer = new ExcelHelper($file);
        $writer->setAuthor($uS->username);
        $writer->setTitle('Item Report');
        
        // build header
        $hdr = array();
        $colWidths = array();
        $n = 0;

        foreach($fltrdFields as $field){
            $hdr[$field[0]] = $field[4]; //set column header name and type;
            $colWidths[] = $field[5]; //set column width
        }
        
        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
        $reportRows++;
    }

    $total = 0.0;

    $name_lk = $uS->nameLookups;
    $name_lk['Invoice_Status'] = $statusList;
    $uS->nameLookups = $name_lk;

    // Now the data ...
    $stmt = $dbh->query($query);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        doMarkupRow($colSelector->getFilteredFields(), $r, $local, $statusList, $diags, $locations, $total, $tbl, $writer, $hdr, $reportRows, $uS->subsidyId, $uS->returnId);

    }


    // Finalize and print.
    if ($local) {
        if(in_array("Amount", $fltrdTitles)){
            $footercolspan = count($fltrdTitles) - 2;
            $footerspace = '';
            if($footercolspan > 0){
                $footerspace = HTMLTable::makeTd('', array('colspan'=> $footercolspan));
            }
            $tbl->addFooterTr($footerspace
                .HTMLTable::makeTd('Total:', array('style'=>'text-align:right;font-weight:bold; border-top:2px solid black;'))
                .HTMLTable::makeTd('$'.number_format($total,2), array('style'=>'text-align:right;font-weight:bold; border-top:2px solid black;'))
                );
        }else{
            $tbl->addFooterTr(HTMLTable::makeTd('', array('colspan'=> (count($fltrdTitles)))));
        }

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display'));
        $mkTable = 1;
        $sortCol = array_search("Last", $fltrdTitles);
        if(!$sortCol){
            $sortCol = 0;
        }
        $headerTableMu = $headerTable->generateMarkup();

    } else {
        $writer->download();
    }

}

// Setups for the page.

$monSize = 5;

// Prepare controls

$statusSelector = HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($statusList, $statusSelections), array('name' => 'selPayStatus[]', 'size' => '4', 'multiple' => 'multiple'));

$itemSelector = HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($itemList, $itemSelections), array('name' => 'selItems[]', 'size' => (count($itemList) + 1), 'multiple' => 'multiple'));

$dAttrs = array('name'=>'cbShoDel', 'id'=>'cbShoDel', 'type'=>'checkbox', 'style'=>'margin-right:.3em;');

if ($showDeleted) {
    $dAttrs['checked'] = 'checked';
}
$shoDeletedCb = HTMLInput::generateMarkup('', $dAttrs)
        . HTMLContainer::generateMarkup('label', 'Show Deleted Invoices', array('for'=>'cbShoDel'));

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'12', 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, ($uS->StartYear ? $uS->StartYear : "2013"), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'12'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'5'));

$selDiag = '';
if (count($diags) > 0) {

    $selDiag = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($diags, $diagSelections, TRUE),
        array('name'=>'selDiag[]', 'multiple'=>'multiple', 'size'=>min(count($diags)+1, 12)));
}


$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;', 'id'=>'includeFields'));

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
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        
<script type="text/javascript">
function invoiceAction(idInvoice, action, eid, container, show) {
    $.post('ws_resc.php', {cmd: 'invAct', iid: idInvoice, x:eid, action: action, 'sbt':show},
      function(data) {
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                //flagAlertMessage(data.error, true);
                return;
            }
            if (data.markup) {
                var contr = $(data.markup);
                if (container != undefined && container != '') {
                    $(container).append(contr);
                } else {
                    $('body').append(contr);
                }
                $('body').append(contr);
                contr.position({
                    my: 'left top',
                    at: 'left bottom',
                    of: "#" + data.eid
                });
            }
        }
    });
}
    $(document).ready(function() {
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var makeTable = '<?php echo $mkTable; ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
        var sortCol = '<?php echo (isset($sortCol) ? $sortCol: ""); ?>';
        $('#btnHere, #btnExcel, #cbColClearAll, #cbColSelAll').button();
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
        
        if (makeTable === '1') {
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
                "dom": '<"top"ilf>rt<"bottom"ilp><"clear">',
                "order": [[ sortCol, 'asc' ]]
            });

            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
            $('#tblrpt').on('click', '.invAction', function (event) {
                invoiceAction($(this).data('iid'), 'view', event.target.id, '', true);
            });
        }
        
        $('#includeFields').fieldSets({'reportName': 'item', 'defaultFields': <?php echo json_encode($defaultFields); ?>});
        
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="ItemReport.php" method="post">
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
                    <table style="float: left;">
                        <tr>
                            <th>Invoice Status</th>
                        </tr>
                        <tr>
                           <td><?php echo $statusSelector; ?></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <th>Item Filter</th>
                        </tr>
                        <tr>
                           <td><?php echo $itemSelector; ?></td>
                        </tr>
                    </table>
                    <?php if (count($diags) > 0) { ?>
                    <table style="float:left;">
                        <tr>
                            <th><?php echo $labels->getString('hospital', 'diagnosis', 'Diagnosis'); ?></th>
                        </tr>
                        <tr>
                            <td><?php echo $selDiag; ?></td>
                        </tr>
                    </table>
                    <?php } ?>
                    <?php echo $columSelector; ?>
                    <table style="width:100%; clear:both;">
                        <tr>
                            <td style="width:50%;"><?php echo $shoDeletedCb; ?></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox" style="display:none; font-size: .9em; padding: 5px; padding-bottom:25px;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                    <?php echo $headerTableMu; ?>
                </div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
