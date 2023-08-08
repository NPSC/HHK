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
use HHK\House\Report\ReportFilter;

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

function doMarkupRow($fltrdFields, $r, $isLocal, $invoice_Statuses, $diagnoses, $locations, &$total, &$tbl, &$writer, $hdr, &$reportRows, $subsidyId, $returnId, $labels) {

    $amt = $r['Amount'];

    $payStatusAttr = array();
    $attr['style'] = 'text-align:right;';


    $invNumber = $r['Invoice_Number'];

    if ($invNumber != '') {

        $iAttr = array('href'=>'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'style'=>'float:left;', 'target'=>'_blank');

        if ($r['Invoice_Deleted'] > 0) {
            $iAttr['style'] .= 'color:red;';
            $iAttr['title'] = 'Invoice is Deleted.';
        } else if ($r['Balance'] != 0 && $r['Balance'] != $r['Invoice_Amount']) {

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
        'Address' =>$r['Address'],
        'City'=>$r['City'],
        'County'=>$r['County'],
        'State_Province'=>$r['State_Province'],
        'Postal_Code'=>$r['Postal_Code'],
        'Country'=>$r['Country'],
        'Status' => $invoiceStatus,
        'Diagnosis' => (isset($diagnoses[$r['Diagnosis']]) ? $diagnoses[$r['Diagnosis']][1] : ''),
        'Location' => (isset($locations[$r['Location']]) ? $locations[$r['Location']][1] : ''),
        'Description' => $r['Description'],
        'Invoice_Notes' => $r["Invoice_Notes"],
        'Payment_Notes' => $r["Payment_Notes"],
        'Patient_Id' => $r['Patient_Id'],
        'Patient_Name_Last' => $r['Patient_Name_Last'],
        'Patient_Name_First' => $r['Patient_Name_First'],
        'Patient_Address' =>$r['Patient_Address'],
        'Patient_City'=>$r['Patient_City'],
        'Patient_County'=>$r['Patient_County'],
        'Patient_State_Province'=>$r['Patient_State_Province'],
        'Patient_Postal_Code'=>$r['Patient_Postal_Code'],
        'Patient_Country'=>$r['Patient_Country'],
        'Invoice_Number' => $r['Invoice_Number'],
        'Amount' => $amt,
        'Updated_By'=>$r["Updated_By"],
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

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);

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

$statusList = readGenLookupsPDO($dbh, 'Invoice_Status');



// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms
$cFields[] = array('Visit Id', 'vid', 'checked', '', 'string', '15', array());
$cFields[] = array("Organization", 'Company', 'checked', '', 'string', '20', array());
$cFields[] = array($labels->getString('memberType', 'guest', 'Guest') . ' Last', 'Last', 'checked', '', 'string', '20', array());
$cFields[] = array($labels->getString('memberType', 'guest', 'Guest') . " First", 'First', 'checked', '', 'string', '20', array());
$pFields = array('Address', 'City');
$pTitles = array($labels->getString('memberType', 'guest', 'Guest') . ' Address', 'City');
$paFields = array('Patient_Address', 'Patient_City');
$paTitles = array($labels->getString('memberType', 'patient', 'Patient') . ' Address', $labels->getString('memberType', 'patient', 'Patient') . ' City');

if ($uS->county) {
    $pFields[] = 'County';
    $pTitles[] = $labels->getString('memberType', 'guest', 'Guest') . ' County';
    $paFields[] = 'Patient_County';
    $paTitles[] = $labels->getString('memberType', 'patient', 'Patient') . ' County';
}

$pFields = array_merge($pFields, array('State_Province', 'Postal_Code', 'Country'));
$pTitles = array_merge($pTitles, array('State', 'Zip', 'Country'));
$paFields = array_merge($paFields, array("Patient_State_Province", "Patient_Postal_Code", "Patient_Country"));
$paTitles = array_merge($paTitles, array($labels->getString('memberType', 'patient', 'Patient') . " State", $labels->getString('memberType', 'patient', 'Patient') . " Zip", $labels->getString('memberType', 'patient', 'Patient') . " Country"));

$cFields[] = array($pTitles, $pFields, '', '', 'string', '20', array());
$cFields[] = array("Date", 'Date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15', array());
$cFields[] = array("Description", 'Description', 'checked', '', 'string', '20', array());
$cFields[] = array("Invoice Notes", 'Invoice_Notes', '', '', 'string', '20', array());
$cFields[] = array("Payment Notes", 'Payment_Notes', '', '', 'string', '20', array());
$cFields[]= array($labels->getString('memberType', 'patient', 'Patient') . " Id", 'Patient_Id', '', '', 'string', '20', array());
$cFields[]= array($labels->getString('memberType', 'patient', 'Patient') . " Last", 'Patient_Name_Last', '', '', 'string', '20', array());
$cFields[]= array($labels->getString('memberType', 'patient', 'Patient') . " First", 'Patient_Name_First', '', '', 'string', '20', array());
$cFields[] = array($paTitles, $paFields, '', '', 'string', '20', array());

$locations = readGenLookupsPDO($dbh, 'Location');
if (count($locations) > 0) {
    $cFields[] = array($labels->getString('hospital', 'location', 'Location'), 'Location', '', '', 'string', '20', array());
}

// Diagnosis
$diags = readGenLookupsPDO($dbh, 'Diagnosis', 'Description');
$diagCats = readGenLookupsPDO($dbh, 'Diagnosis_Category', 'Description');
//prepare diag categories for doOptionsMkup
foreach($diags as $key=>$diag){
    if(!empty($diag['Substitute'])){
        $diags[$key][2] = $diagCats[$diag['Substitute']][1];
        $diags[$key][1] = $diagCats[$diag['Substitute']][1] . ": " . $diags[$key][1];
    }
}

if (count($diags) > 0) {
    $cFields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', '', '', 'string', '20', array());
}

$cFields[] = array("Updated By", 'Updated_By', '', '', 'string', '15', array());
$cFields[] = array("Status", 'Status', 'checked', '', 'string', '15', array());
$cFields[] = array("Amount", 'Amount', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));

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

    $filter->loadSelectedTimePeriod();

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
            $diagSelections = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    if (isset($_POST['selPayStatus'])) {
        $reqs = $_POST['selPayStatus'];
        if (is_array($reqs)) {
            $statusSelections = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    if (isset($_POST['selItems'])) {
        $reqs = $_POST['selItems'];
        if (is_array($reqs)) {
            $itemSelections = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    $whDates = " and DATE(i.Invoice_Date) < DATE('".$filter->getQueryEnd()."') and DATE(i.Invoice_Date) >= DATE('".$filter->getReportStart()."') ";

    $endDT = new DateTime($end);
    $endDT->sub(new DateInterval('P1D'));

    $headerTable->addBodyTr(HTMLTable::makeTd('Reporting Period: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($filter->getReportEnd()))));



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
    i.`Updated_By`,
    i.`Notes` as `Invoice_Notes`,
    ifnull(p.`Notes`, '') as `Payment_Notes`,
    il.`Price`,
    il.`Amount`,
    il.`Quantity`,
    il.`Description`,
    il.Item_Id,
    il.Period_Start,
    il.Period_End,
    il.`Deleted` as `Line_Deleted`,
    ifnull(pn.Name_Last, '') as `Patient_Name_Last`,
    ifnull(pn.Name_First, '') as `Patient_Name_First`,
    ifnull(pn.idName, '') as `Patient_Id`,
    ifnull(hs.Diagnosis, '') as `Diagnosis`,
    ifnull(hs.Location, '') as `Location`,
    CASE when IFNULL(pa.Address_2, '') = '' THEN IFNULL(pa.Address_1, '') ELSE CONCAT(IFNULL(pa.Address_1, ''), ' ', IFNULL(pa.Address_2, '')) END AS `Patient_Address`,
    IFNULL(pa.City, '') AS `Patient_City`,
    IFNULL(pa.County, '') AS `Patient_County`,
    IFNULL(pa.State_Province, '') AS `Patient_State_Province`,
    IFNULL(pa.Postal_Code, '') AS `Patient_Postal_Code`,
    IFNULL(pa.Country_Code, '') AS `Patient_Country`,
    ifnull(n.Name_Last, '') as `Name_Last`,
    ifnull(n.Name_First, '') as `Name_First`,
    CASE when IFNULL(na.Address_2, '') = '' THEN IFNULL(na.Address_1, '') ELSE CONCAT(IFNULL(na.Address_1, ''), ' ', IFNULL(na.Address_2, '')) END AS `Address`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.County, '') AS `County`,
    IFNULL(na.State_Province, '') AS `State_Province`,
    IFNULL(na.Postal_Code, '') AS `Postal_Code`,
    IFNULL(na.Country_Code, '') AS `Country`,
    ifnull(n.`Company`, '') as `Company`,
    ifnull(nv.Vol_Code, '') as `Billing_Agent`
from
    invoice_line il join invoice i ON il.Invoice_Id = i.idInvoice
    left join `payment_invoice` pi on i.idInvoice = pi.Invoice_Id
    left join `payment` p on pi.Payment_Id = p.idPayment
    left join `name` n on i.Sold_To_Id = n.idName
    left join `name_address` na ON n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
    left join visit v on i.Order_Number = v.idVisit and i.Suborder_Number = v.Span
    left join hospital_stay hs on hs.idHospital_stay = v.idHospital_stay
    left join `name` pn on hs.idPatient = pn.idName
    left join `name_address` pa on pn.idName = pa.idName
    left join name_volunteer2 nv on nv.idName = n.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '" . VolMemberType::BillingAgent . "'
where $whDeleted  $whDates  $whItem and il.Item_Id != 5  $whStatus $whDiags group by il.idInvoice_Line order by i.idInvoice, il.idInvoice_Line";


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

        doMarkupRow($colSelector->getFilteredFields(), $r, $local, $statusList, $diags, $locations, $total, $tbl, $writer, $hdr, $reportRows, $uS->subsidyId, $uS->returnId, $labels);

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

$timePeriodMarkup = $filter->timePeriodMarkup("Invoice")->generateMarkup();

$selDiag = '';
if (count($diags) > 0) {

    $selDiag = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($diags, $diagSelections, TRUE),
        array('name'=>'selDiag[]', 'multiple'=>'multiple', 'size'=>min(count($diags)+1, 12)));
}


$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'display: inline-block; vertical-align: top;', 'id'=>'includeFields'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

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
            yearRange: '<?php echo $uS->StartYear; ?>:+01',
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
                "dom": '<"top ui-toolbar ui-helper-clearfix"ilf><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"ilp><"clear">',
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
                	<div style="display: inline-block; vertical-align: top;">
                    	<?php echo $timePeriodMarkup; ?>
                    </div>
                    <table style="display: inline-block; vertical-align: top;">
                        <tr>
                            <th>Invoice Status</th>
                        </tr>
                        <tr>
                           <td><?php echo $statusSelector; ?></td>
                        </tr>
                    </table>
                    <table style="display: inline-block; vertical-align: top;">
                        <tr>
                            <th>Item Filter</th>
                        </tr>
                        <tr>
                           <td><?php echo $itemSelector; ?></td>
                        </tr>
                    </table>
                    <?php if (count($diags) > 0) { ?>
                    <table style="display: inline-block; vertical-align: top;">
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
            <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog" style="display:none; font-size: .9em; padding: 5px; padding-bottom:25px; margin: 10px 0">
                <div><input id="printButton" value="Print" type="button"/></div>
                <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                    <?php echo $headerTableMu; ?>
                </div>
                <form autocomplete="off">
                <?php echo $dataTable; ?>
                </form>
            </div>
        </div>
    </body>
</html>
