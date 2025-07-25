<?php
use HHK\ColumnSelectors;
use HHK\ExcelHelper;
use HHK\Exception\RuntimeException;
use HHK\House\GLCodes\GLCodes;
use HHK\House\GLCodes\GLParameters;
use HHK\House\GLCodes\GLTemplateRecord;
use HHK\House\Report\ReportFieldSet;
use HHK\House\Report\ReportFilter;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentResult\PaymentResult;
use HHK\Payment\PaymentSvcs;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\sec\WebInit;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemId;
use HHK\SysConst\Mode;
use HHK\SysConst\VolMemberType;
use HHK\SysConst\WebPageCode;
use HHK\TableLog\HouseLog;

/**
 * InvoiceReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


try {
    $wInit = new WebInit(WebPageCode::Page, FALSE);
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();
$labels = Labels::getLabels();

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();

function doMarkupRow($fltrdFields, $r, $isLocal, $hospital, $statusTxt, &$tbl, &$writer, $hdr, &$reportRows, $subsidyId) {

    $g = array();

    $g['Payor'] = $r['Company'];
    if ($r['Sold_To_Name'] != '') {

        if ($r['Company'] != '') {
            $g['Payor'] = $r['Company'] . ' c/o ';
        }

        $g['Payor'] .= $r['Sold_To_Name'];
    }


    $g['Patient'] = $r['Patient_Name'];
    if ($r['idPatient'] > 0) {
        $g['Patient'] = HTMLContainer::generateMarkup('a', $g['Patient'], ['href' => 'GuestEdit.php?id=' . $r['idPatient']]);
    }

    $g['payments'] = HTMLContainer::generateMarkup('span', number_format(($r['Amount'] - $r['Balance']), 2), ['style' => 'float:right;']);
    if (($r['Amount'] - $r['Balance']) != 0 && $r['Sold_To_Id'] != $subsidyId) {
        $g['payments'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'vwpmt' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'vpmt', 'style' => 'cursor:pointer;', 'title' => 'View Payments']);
    }

    $invoiceNumber = $r['Invoice_Number'];
    if ($invoiceNumber != '') {

        $invAttr = ['href' => 'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'target' => '_blank'];

        if ($r['Balance'] != 0 && $r['Balance'] != $r['Amount']) {
            $invoiceNumber .= HTMLContainer::generateMarkup('sup', '-p');
            $invAttr['title'] = 'Partial Payment';
        }

        $invoiceNumber = HTMLContainer::generateMarkup('a', $invoiceNumber, $invAttr)
            .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction ml-2', 'id' => 'invicon' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items']);
    }

    //$g['invoiceMkup'] = HTMLContainer::generateMarkup('span', $invoiceNumber, ["style" => 'white-space:nowrap;']);
    $g['invoiceMkup'] = $invoiceNumber;

    if ($r['Status'] == InvoiceStatus::Carried && $r['Deleted'] == 0) {

        $r['Balance'] = 0;

        $g['Status'] = HTMLContainer::generateMarkup('span',
                HTMLContainer::generateMarkup('span', $statusTxt . ' by ' . HTMLContainer::generateMarkup('a', $r['Delegated_Invoice_Number'], ['href' => 'ShowInvoice.php?invnum=' . $r['Delegated_Invoice_Number'], 'target' => '_blank']))
                .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'invicond' . $r['Delegated_Invoice_Id'], 'data-iid' => $r['Delegated_Invoice_Id'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items'])
                , array("style"=>'white-space:nowrap;'));
    } else {

        $g['Status']= HTMLContainer::generateMarkup('span', $statusTxt, ["style" => 'white-space:nowrap;']);
    }

    $dateDT = new DateTime($r['Invoice_Date']);
    $g['date'] = $dateDT->format('c');

    $billDateStr = ($r['BillDate'] == '' ? '' : date('M j, Y', strtotime($r['BillDate'])));

    $g['billed'] = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('span', $billDateStr, ['id' => 'trBillDate' . $r['Invoice_Number']])
        . HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-calendar invSetBill', 'data-name' => $g['Payor'], 'data-inb' => $r['Invoice_Number'], 'style' => 'cursor:pointer;margin-left:5px;', 'title' => 'Set Billing Date']),
        ["class"=>"hhk-flex", "style"=>"justify-content: space-between; align-items: end;"]);

    $g['emailed'] = ($r["EmailDate"] == '' ? '' : (new DateTime($r["EmailDate"]))->format("M j, Y"));

    $g['Amount'] = number_format($r['Amount'], 2);

    // Show Delete Icon?
    if (($r['Amount'] == 0 || $r['Item_Id'] == ItemId::Discount) && $r['Deleted'] != 1) {
        $g['Amount'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-trash invAction', 'id' => 'invdel' . $r['idInvoice'], 'data-inv' => $r['Invoice_Number'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'del', 'style' => 'cursor:pointer;', 'title' => 'Delete This Invoice']);
    }

    $g['County'] = $r['County'];
    $g['Zip'] = $r['Zip'];
    $g['Title'] = $r['Title'];
    $g['Arrival'] = $r['Arrival'];
    $g['Departure'] = $r['Departure'];
    $g['hospital'] = $hospital;
    $g['Balance'] = number_format($r['Balance'], 2);
    $g['Notes'] = HTMLContainer::generateMarkup('div', $r['Notes'], ['id' => 'divInvNotes' . $r['Invoice_Number'], 'style' => 'max-width:190px; white-space:normal;']);

//add invoice notes to payment report
    if ($isLocal) {

        $tr = '';
        foreach ($fltrdFields as $f) {
            $tr .= HTMLTable::makeTd($g[$f[1]], $f[6]);
        }

        $tbl->addBodyTr($tr);

    } else {

        $g['invoiceMkup'] = $r['Invoice_Number'];
        $g['date'] = $r['Invoice_Date'];
        $g['Status'] = $statusTxt;
        $g['billed'] = $billDateStr;
        $g['Patient'] = $r['Patient_Name'];
        $g['Notes'] = $r['Notes'];
        $g['payments'] = $r['Amount'] - $r['Balance'];
        $g['Amount'] = $r['Amount'];
        $g['Balance'] = $r['Balance'];

        $n = 0;
        $flds = array();

        foreach ($fltrdFields as $f) {
            //$flds[$n++] = array('type' => $f[4], 'value' => $g[$f[1]], 'style'=>$f[5]);
            $flds[] = $g[$f[1]];
        }

        $row = $writer->convertStrings($hdr, $flds);
        $writer->writeSheetRow("Sheet1", $row);
    }
}

$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTableMkup = '';
$dataTable = '';

$hospitalSelections = array();
$assocSelections = array();
$invStatus = array();
$calSelection = '19';
$baSelections = array();
$baSelector = '';
$paymentMarkup = '';
$receiptMarkup = '';
$tabReturn = 0;

$year = date('Y');
$months = array(date('n'));       // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$showDeleted = FALSE;
$useVisitDates = FALSE;
$cFields = array();

$useGlReport = stristr(strtolower($uS->siteName), 'gorecki');

// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        //make receipt copy
        if($receiptMarkup != '' && $uS->merchantReceipt == true) {
            $receiptMarkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('div', $receiptMarkup.HTMLContainer::generateMarkup('div', 'Customer Copy', ['style' => 'text-align:center;']), ['style' => 'margin-right: 15px; width: 100%;'])
                .HTMLContainer::generateMarkup('div', $receiptMarkup.HTMLContainer::generateMarkup('div', 'Merchant Copy', ['style' => 'text-align: center']), ['style' => 'margin-left: 15px; width: 100%;'])
                ,
                ['style' => 'display: flex; min-width: 100%;', 'data-merchCopy' => '1']);
        }

        // Display a status message.
        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }

        if(WebInit::isAJAX()){
            echo json_encode(["receipt"=>$receiptMarkup, ($payResult->wasError() ? "error": "success")=>$payResult->getDisplayMessage()]);
            exit;
        }
    }

} catch (RuntimeException $ex) {
    if(WebInit::isAJAX()){
        echo json_encode(["error"=>$ex->getMessage()]);
        exit;
    } else {
        $paymentMarkup = $ex->getMessage();
    }
}



// Hospital and association lists
$hospList = $filter->getHList();
$aList = $filter->getAList();

// Invoices
$invoiceStatuses = readGenLookupsPDO($dbh, 'Invoice_Status');

// Billing agent.
$stmt = $dbh->query("SELECT n.idName, n.Name_First, n.Name_Last, n.Company " .
        " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
        " where n.Member_Status='a' and n.Record_Member = 1 order by n.Name_Last, n.Name_First, n.Company");

$bagnts = [];

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

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

    $bagnts[$r['idName']] = array(0=>$r['idName'], 1=>$entry);
}


// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms, DT Type
$cFields[] = ['Inv #', 'invoiceMkup', 'checked', '', 'string', '20', array('style' => 'text-align:center;')];
$cFields[] = array("Date", 'date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("Status", 'Status', 'checked', '', 'string', '20', array());
$cFields[] = array("Payor", 'Payor', 'checked', '', 'string', '20', array());
$cFields[] = array("Billed", 'billed', 'checked', '', 'string', '20', array());
$cFields[] = array("Emailed", 'emailed', 'checked', '', 'string', '20', array());
$cFields[] = array("Room", 'Title', 'checked', '', 'string', '15', array('style'=>'text-align:center;'));
$cFields[] = array("Visit Arrival", 'Arrival', '', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("Visit Departure", 'Departure', '', '', 'MM/DD/YYYY', '15', array(), 'date');

if ((count($hospList)) > 1) {
    $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'hospital', 'checked', '', 'string', '20', array());
}

$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient'), 'Patient', '', '', 'string', '20', array());

if ($uS->county) {
    $cFields[] = array($labels->getString('MemberType', 'patient', 'Patient') . ' County', 'County', '', '', 'string', '20', array());
}

$cFields[] = array("Zip Code", 'Zip', '', '', 'string', '10', array());

// Old number format - '_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)'
$cFields[] = array("Amount", 'Amount', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));
$cFields[] = array("Payments", 'payments', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));
$cFields[] = array("Balance", 'Balance', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));

$cFields[] = array("Notes", 'Notes', 'checked', '', 'string', '20', array());

$fieldSets = ReportFieldSet::listFieldSets($dbh, 'invoice', true);
$fieldSetSelection = (isset($_REQUEST['fieldset']) ? $_REQUEST['fieldset']: '');
$colSelector = new ColumnSelectors($cFields, 'selFld', true, $fieldSets, $fieldSetSelection);
$defaultFields = array();
foreach($cFields as $field){
    if($field[2] == 'checked'){
        $defaultFields[] = $field[1];
    }
}

$invNum = '';
if (filter_has_var(INPUT_POST, 'invNum')) {

    $invNum = filter_var($_POST['invNum'], FILTER_SANITIZE_NUMBER_INT);
}


if (filter_has_var(INPUT_POST, 'btnHere') || filter_has_var(INPUT_POST, 'btnExcel') || $invNum != '') {

    $headerTable = new HTMLTable();
    $headerTable->addBodyTr(HTMLTable::makeTd('Report Generated: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y')));

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);

    if (isset($_POST['cbShoDel'])) {
        $showDeleted = TRUE;
    }

    if (isset($_POST['cbUseVisitDates'])) {
        $useVisitDates = TRUE;
    }

    if (isset($_POST['selInvStatus'])) {
        $reqs = $_POST['selInvStatus'];
        if (is_array($reqs)) {
            $invStatus = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    if (isset($_POST['selbillagent'])) {
        $reqs = $_POST['selbillagent'];
        if (is_array($reqs)) {
            $baSelections = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    $whHosp = '';
    $whAssoc = '';
    $whStatus = '';
    $whBillAgent = '';
    $whDeleted = '';

    $filter->loadSelectedTimePeriod()
        ->loadSelectedHospitals();

    if ($invNum != '') {

        $whDates = " i.Invoice_Number = '$invNum' ";
        $headerTable->addBodyTr(HTMLTable::makeTd('For Invoice Number: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($invNum));

    } else {

        $headerTable->addBodyTr(HTMLTable::makeTd('Reporting Period: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($filter->getReportEnd()))));

        if ($useVisitDates) {
            $whDates = " and DATE(v.Arrival_Date) < DATE('".$filter->getQueryEnd()."') and ifnull(DATE(v.Actual_Departure), DATE(v.Expected_Departure)) >= DATE('".$filter->getReportStart()."') ";
        } else {
            $whDates = " and DATE(`i`.`Invoice_Date`) < DATE('".$filter->getQueryEnd()."') and DATE(`i`.`Invoice_Date`) >= DATE('".$filter->getReportStart()."') ";
        }


        // Hospitals
        foreach ($filter->getSelectedHosptials() as $a) {
            if ($a != '') {
                if ($whHosp == '') {
                    $whHosp .= $a;
                } else {
                    $whHosp .= ",". $a;
                }
            }
        }

        foreach ($filter->getSelectedAssocs() as $a) {
            if ($a != '') {
                if ($whAssoc == '') {
                    $whAssoc .= $a;
                } else {
                    $whAssoc .= ",". $a;
                }
            }
        }

        if ($whHosp != '') {
            $whHosp = " and hs.idHospital in (".$whHosp.") ";
        }
        if ($whAssoc != '') {
            $whAssoc = " and hs.idAssociation in (".$whAssoc.") ";
        }

        $hdrHosps = $filter->getSelectedHospitalsString();
        $hdrAssocs = $filter->getSelectedAssocString();

        $headerTable->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrHosps));

        if (count($aList) > 0) {
            $headerTable->addBodyTr(HTMLTable::makeTd('Associations: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrAssocs));
        }


        // Invoice status
        $hdrStatus = 'All';
        foreach ($invStatus as $s) {
            if ($s != '') {
                if ($whStatus == '') {
                    $whStatus = "'" . $s . "'";
                    $hdrStatus = $invoiceStatuses[$s][1];
                } else {
                    $whStatus .= ",'".$s . "'";
                    $hdrStatus .= ", " . $invoiceStatuses[$s][1];
                }
            }
        }
        if ($whStatus != '') {
            $whStatus = " and i.`Status` in (" . $whStatus . ") ";
        }

        $headerTable->addBodyTr(HTMLTable::makeTd('Pay Statuses: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrStatus));

        // Billing Agent
        $hdrBillAgent = 'All';
        foreach ($baSelections as $s) {
            if ($s != '') {
                if ($whBillAgent == '') {
                    $whBillAgent = $s;
                    $hdrBillAgent = $bagnts[$s][1];
                } else {
                    $whBillAgent .= ",".$s;
                    $hdrBillAgent .= ", ".$bagnts[$s][1];
                }
            }
        }

        if ($whBillAgent != '') {
            $whBillAgent = " and `i`.`Sold_To_Id` in (" . $whBillAgent . ") ";
            $headerTable->addBodyTr(HTMLTable::makeTd('Billing Agents: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrBillAgent));
        }

        if ($showDeleted) {
            $whDeleted = '1=1 ';
        } else {
            $whDeleted = 'i.Deleted = 0 ';
        }
    }


    $query = "SELECT
`i`.`idInvoice`,
`i`.`Delegated_Invoice_Id`,
ifnull(di.Invoice_Number, '') as Delegated_Invoice_Number,
ifnull(di.Status, '') as Delegated_Invoice_Status,
i.Deleted,
`i`.`Invoice_Number`,
`i`.`Amount`,
`i`.`Carried_Amount`,
`i`.`Balance`,
`i`.`Order_Number`,
`i`.`Sold_To_Id`,
`i`.`Notes`,
n.Name_Full as Sold_To_Name,
n.Company,
ifnull(np.Name_Full, '') as Patient_Name,
ifnull(nap.County, '') as `County`,
ifnull(nap.Postal_Code, '') as `Zip`,
ifnull(re.Title, '') as `Title`,
ifnull(v.Arrival_Date, '') as `Arrival`,
ifnull(v.Actual_Departure, '') as `Departure`,
ifnull(hs.idHospital, 0) as `idHospital`,
ifnull(hs.idAssociation, 0) as `idAssociation`,
ifnull(hs.idPatient, 0) as `idPatient`,
`i`.`idGroup`,
`i`.`Invoice_Date`,
i.BillStatus,
i.BillDate,
i.EmailDate,
`i`.`Payment_Attempts`,
`i`.`Status`,
`i`.`Updated_By`,
`i`.`Last_Updated`,
ifnull(il.Item_Id, 0) as `Item_Id`
FROM `invoice` `i` left join `name` n on i.Sold_To_Id = n.idName
    left join invoice_line il on il.Invoice_Id = i.idInvoice and il.Item_Id = 6
    left join visit v on i.Order_Number = v.idVisit and i.Suborder_Number = v.Span
    left join hospital_stay hs on hs.idHospital_stay = v.idHospital_stay
    left join name np on hs.idPatient = np.idName
    left join name_address nap on np.idName = nap.idName and nap.Purpose = np.Preferred_Mail_Address
    left join resource re on v.idResource = re.idResource
    left join invoice di on i.Delegated_Invoice_Id = di.idInvoice
where $whDeleted $whDates $whHosp $whAssoc  $whStatus $whBillAgent ";


    $tbl = null;
    $sml = null;
    $reportRows = 0;

    if (count($aList) > 0) {
        $hospHeader = $labels->getString('hospital', 'hospital', 'Hospital').' / Assoc';
    } else {
        $hospHeader = $labels->getString('hospital', 'hospital', 'Hospital');
    }

    $fltrdTitles = $colSelector->getFilteredTitles();
    $fltrdFields = $colSelector->getFilteredFields();

    $hdr = array();

    if ($local) {
        $tbl = new HTMLTable();
        $th = '';

        foreach ($fltrdTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }

        $tbl->addHeaderTr($th);

    } else {


        $reportRows = 1;
        $fileName = 'InvoiceReport';
        $writer = new ExcelHelper($fileName);
        $writer->setAuthor($uS->username);
        $writer->setTitle("Invoice Report");

        // build header
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

    $totalAmount = 0.0;
    $totalPaid = 0.0;
    $totalBalance = 0.0;



    // Now the data ...
    $stmt = $dbh->query($query);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // Hospital
        $hospital = '';

        if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
        }
        if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
        }

        $statusTxt = '';
        if (isset($invoiceStatuses[$r['Status']])) {
            $statusTxt = $invoiceStatuses[$r['Status']][1];
        }

        if ($r['Deleted'] == 1) {

            $statusTxt = HTMLContainer::generateMarkup('span', 'Deleted', array('style'=>'color:red;'));
            $r['Balance'] = 0;
            $r['Amount'] = 0;

        } else if ($r['Status'] == InvoiceStatus::Carried) {

            $totalPaid += $r['Amount'] - $r['Balance'];


        } else {

            // Totals
            $totalPaid += $r['Amount'] - $r['Balance'];
            $totalBalance += $r['Balance'];
            $totalAmount += $r['Amount'];
        }

        doMarkupRow($fltrdFields, $r, $local, $hospital, $statusTxt, $tbl, $writer, $hdr, $reportRows, $uS->subsidyId);

    }




    // Finalize and print.
    if ($local) {

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display', 'style'=>'font-size:.9em;width:100%'));
        $mkTable = 1;

        $totTable = new HTMLTable();

        $totTable->addBodyTr(
                HTMLTable::makeTd('Total Amount:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($totalAmount,2), array('style'=>'text-align:right;')));

        $totTable->addBodyTr(
                HTMLTable::makeTd('Total Payment:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($totalPaid,2), array('style'=>'text-align:right;')));

        $totTable->addBodyTr(
                HTMLTable::makeTd('Total Balance:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($totalBalance,2), array('style'=>'text-align:right;')));

        $headerTableMkup = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Invoice Report ', array('style'=>'margin-top: .5em;'))
                .$headerTable->generateMarkup(array('style'=>'float:left;')) . $totTable->generateMarkup(array('style'=>'float:left;'));


    } else {
        HouseLog::logDownload($dbh, 'Invoice Report', "Excel", "Invoice Report for " . $filter->getReportStart() . " - " . $filter->getReportEnd() . " downloaded", $uS->username);
        $writer->download();
    }

}

// Gl REport
$glChooser = '';
$glInvoices = '';
$glMonthSelr = '';
$glYearSelr = '';
$glMonth = 0;
$glyear = 0;
$bytesWritten = '';

if ($useGlReport) {

	$glParm = new GLParameters($dbh, 'Gl_Code');
	$glPrefix = 'gl_';

	// Check for new parameters
	if (isset($_POST['btnSaveGlParms'])) {
		$glParm->saveParameters($dbh, $_POST, $glPrefix);
		$tabReturn = 2;
	}

	$glMonth = date('m');

	// Output report
	if (filter_has_var(INPUT_POST, 'btnGlGo') || filter_has_var(INPUT_POST, 'btnGlTx') || filter_has_var(INPUT_POST, 'btnGlcsv')) {

		$tabReturn = 2;

		if (isset($_POST['selGlMonth'])) {
			$glMonth = filter_var($_POST['selGlMonth'], FILTER_SANITIZE_NUMBER_INT);
		}

		if (isset($_POST['selGlYear'])) {
			$glyear = intval(filter_var($_POST['selGlYear'], FILTER_SANITIZE_NUMBER_INT), 10);
		}

		$glCodes = new GLCodes($dbh, $glMonth, $glyear, $glParm, new GLTemplateRecord());

		if (isset($_POST['btnGlTx'])) {

			$bytesWritten = $glCodes->mapRecords(FALSE)
					->transferRecords();

			$etbl = new HTMLTable();

			foreach ($glCodes->getErrors() as $e) {
				$etbl->addBodyTr(HTMLTable::makeTd($e));
			}

			if ($bytesWritten != '') {
				$etbl->addBodyTr(HTMLTable::makeTd("Bytes Written: ".$bytesWritten));
			}

			$glInvoices = $etbl->generateMarkup() . $glInvoices;

		} else if (isset($_POST['btnGlcsv'])) {

			// Comma delemeted file.
			$glCodes->mapRecords(TRUE);

			foreach ($glCodes->getLines() as $l) {

				$glInvoices .= implode(',', $l['l']) . "\r\n";

			}

		} else {

			$tbl = new HTMLTable();

			$invHdr = '';
			foreach ($glCodes->invoiceHeader() as $h) {
				$invHdr .= "<td>" . ($h == '' ? ' ' : $h) . "</td>";
			}
			$tbl->addBodyTr($invHdr);

			$pmtHdr = '';
			foreach ($glCodes->paymentHeader() as $h) {
				$pmtHdr .= "<td style='color:blue;'>" . ($h == '' ? ' ' : $h) . "</td>";
			}
			$tbl->addBodyTr($pmtHdr);

			$lineHdr = '';
			foreach ($glCodes->lineHeader() as $h) {
				$lineHdr .= "<td style='color:green;'>" . ($h == '' ? ' ' : $h) . "</td>";
			}
			$tbl->addBodyTr($lineHdr);

			// Get payment methods (types) labels.
			$pmstmt = $dbh->query("Select idPayment_method, Method_Name from payment_method;");
			$pmRows = $pmstmt->fetchAll(\PDO::FETCH_NUM);
			$pmtMethods = array();
			foreach ($pmRows as $r) {
				$pmtMethods[$r[0]] = $r[1];
			}

			$recordCtr = 0;

			foreach ($glCodes->getInvoices() as $r) {

				if ($recordCtr++ > 16) {
					$tbl->addBodyTr($invHdr);
					$tbl->addBodyTr($pmtHdr);
					$tbl->addBodyTr($lineHdr);
					$recordCtr = 0;
				}

				$mkupRow = '';

				foreach ($r['i'] as $k=> $col) {

					if ($k == 'iStatus' && $col == 'p') {
						$col = 'paid';
					}

					$mkupRow .= "<td>" . ($col == '' ? ' ' : $col) . "</td>";
				}
				$tbl->addBodyTr($mkupRow);

				if (isset($r['p'])) {

					foreach ($r['p'] as $p) {
						$mkupRow = '<td> </td>';
						foreach ($p as $k => $col) {

							if ($k == 'pTimestamp') {
								$col = date('Y/m/d', strtotime($col));
							} else if ($k == 'pMethod') {
								$col = $pmtMethods[$col];
							} else if ($k == 'pStatus' && $col == 's') {
								$col = "sale";
							} else if ($k == 'pStatus' && $col == 'r') {
								$col = "return";
							}

							$mkupRow .= "<td style='color:blue;'>" . ($col == '' ? ' ' : $col) . "</td>";

						}
						$tbl->addBodyTr($mkupRow);

					}
				}

				if (isset($r['l'])) {
					foreach ($r['l'] as $h) {
						$mkupRow = '<td> </td><td> </td>';
						foreach ($h as $k => $col) {

							if ($k == 'il_Amount') {
								$col = number_format($col, 2);
							}

							$mkupRow .= "<td style='color:green;'>" . ($col == '' ? ' ' : $col) . "</td>";

						}
						$tbl->addBodyTr($mkupRow);

					}
				}
			}

			$glInvoices = $tbl->generateMarkup();

			// Comma delemeted file.
			$glCodes->mapRecords(TRUE);

			$tbl = new HTMLTable();

			foreach ($glCodes->getLines() as $l) {

				$tbl->addBodyTr(HTMLTable::makeTd($l['in'], array('style' => 'font-size:0.8em')) . HTMLTable::makeTd(implode(',', $l['l']), array('style'=>'font-size:0.8em')));

			}

			if ($glCodes->getStopoAtInvoice() != '') {
				$tbl->addBodyTr(HTMLTable::makeTd('Stop at Invoice number ' . $glCodes->getStopoAtInvoice(), array()));
			}


			$glInvoices .= "<p style='margin-top:20px;'>Total Credits = " . number_format($glCodes->getTotalCredit(), 2) . " Total Debits = " . number_format($glCodes->getTotalDebit(), 2) . "</p>" .$tbl->generateMarkup();

			if (count($glCodes->getErrors()) > 0) {
				$etbl = new HTMLTable();
				foreach ($glCodes->getErrors() as $e) {
					$etbl->addBodyTr(HTMLTable::makeTd($e));
				}
				$glInvoices = $etbl->generateMarkup() . $glInvoices;
			}
		}
	}

	// GL Parms chooser markup
	$glChooser = $glParm->getChooserMarkup($dbh, $glPrefix);

	//Month and Year chooser
	$glMonthSelr = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($filter->getMonths(), $glMonth, FALSE), array('name' => 'selGlMonth', 'size'=>12));
	$glYearSelr = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, ($uS->StartYear ? $uS->StartYear : "2013"), 0, FALSE), array('name' => 'selGlYear', 'size'=>'12'));

}

// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup();
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'display: inline-block; vertical-align: top;'));

$invStatusSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($invoiceStatuses, $invStatus), array('name' => 'selInvStatus[]', 'size' => '4', 'multiple' => 'multiple'));


if (count($bagnts) > 0) {

	$baSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($bagnts, $baSelections), array('name' => 'selbillagent[]', 'size' => (count($bagnts)>12 ? '12' : (count($bagnts)+1)), 'multiple' => 'multiple'));
}

$dAttrs = array('name'=>'cbShoDel', 'id'=>'cbShoDel', 'type'=>'checkbox', 'style'=>'margin-right:.3em;');

if ($showDeleted) {
    $dAttrs['checked'] = 'checked';
}
$shoDeletedCb = HTMLInput::generateMarkup('', $dAttrs)
        . HTMLContainer::generateMarkup('label', 'Show Deleted Invoices', array('for'=>'cbShoDel'));

$vAttrs = array('name'=>'cbUseVisitDates', 'id'=>'cbUseVisitDates', 'type'=>'checkbox', 'style'=>'margin-right:.3em;', 'title'=>'Show all invoices for any Visit having days within this date range.');

if ($useVisitDates) {
    $vAttrs['checked'] = 'checked';
}

$useVisitDatesCb = HTMLInput::generateMarkup('', $vAttrs)
        . HTMLContainer::generateMarkup('label', 'Use Visit Dates', array('for'=>'cbUseVisitDates'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'display: inline-block; vertical-align:top;', 'id'=>'includeFields'));


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
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>
        <?php 
            if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) {
                if ($uS->mode == Mode::Live) {
                    echo DELUXE_EMBED_JS;
                }else{
                    echo DELUXE_SANDBOX_EMBED_JS;
                }
            }
        ?>

<script type="text/javascript">
$(document).ready(function() {
    var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
    var makeTable = '<?php echo $mkTable; ?>';
    var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
    var pmtMkup = '<?php echo $paymentMarkup; ?>';
    var rctMkup = '<?php echo $receiptMarkup; ?>';
    var tabReturn = '<?php echo $tabReturn; ?>';

    $('#btnHere, #btnExcel,  #cbColClearAll, #cbColSelAll, #btnInvGo, #btnSaveGlParms, #btnGlGo, #btnGlTx, #btnGlcsv').button();

    $( "form[name=glform] input[type=checkbox]" ).checkboxradio({
      icon: true
    });

    $("form[name=glform] .ui-checkboxradio-icon").removeClass('ui-state-hover');

    <?php echo $filter->getTimePeriodScript(); ?>
    
    $("#setBillDate").dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Set Invoice Billing Date'
    });
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(530),
        modal: true,
        title: 'Payment Receipt'
    });
    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function (event, ui) {
            $('div#submitButtons').show();
        },
        open: function (event, ui) {
            $('div#submitButtons').hide();
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


    // disappear the pop-ups.
    $(document).mousedown(function (event) {
        var target = $(event.target);
        if ($('div#pudiv').length > 0 && target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

    $('#mainTabs').tabs({
        beforeActivate: function (event, ui) {
            if (ui.newTab.prop('id') === 'liInvoice') {
                $('#btnInvGo').click();
            }
        }
    });

    $('#mainTabs').tabs("option", "active", tabReturn);


    $('#btnInvGo').click(function () {
        var statuses = ['up'];
        var parms = {
            cmd: 'actrpt',
            st: statuses,
            inv: 'on'
        };

        $.post('ws_resc.php', parms,
            function (data) {

                if (data) {

                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert("Parser error - " + err.message);
                        return;
                    }

                    if (data.error) {

                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, 'error');

                    } else if (data.success) {

                        $('#rptInvdiv').remove();
                        $('#vInv').append($('<form autocomplete="off"/>').append($('<div id="rptInvdiv" class="hhk-visitdialog"/>').append($(data.success))));
                        $('#rptInvdiv .gmenu').menu();

                        $('#rptInvdiv').on('click', '.invLoadPc', function (event) {
                            event.preventDefault();
                            $("#divAlert1, #paymentMessage").hide();
                            invLoadPc($(this).data('name'), $(this).data('id'), $(this).data('iid'));
                        });

                        $('#rptInvdiv').on('click', '.invSetBill', function (event) {
                            event.preventDefault();
                            $(".hhk-alert").hide();
                            invSetBill($(this).data('inb'), $(this).data('name'), 'div#setBillDate', '#trBillDate' + $(this).data('inb'), $('#trBillDate' + $(this).data('inb')).text(), $('#divInvNotes' + $(this).data('inb')).text(), '#divInvNotes' + $(this).data('inb'));
                        });

                        $('#rptInvdiv').on('click', '.invAction', function (event) {
                            event.preventDefault();
                            $(".hhk-alert").hide();

                            if ($(this).data('stat') == 'del') {
                                if (!confirm('Delete Invoice ' + $(this).data('inb') + ($(this).data('payor') != '' ? ' for ' + $(this).data('payor') : '') + '?')) {
                                //if (!confirm('Delete this Invoice?')) {
                                    return;
                                }
                            }

                            // Check for email
                            if ($(this).data('stat') === 'vem') {
                                    window.open('ShowInvoice.php?invnum=' + $(this).data('inb'));
                                    return;
                            }

                            invoiceAction($(this).data('iid'), $(this).data('stat'), event.target.id, '', true);
                            $('#rptInvdiv .gmenu').menu("collapse");
                        });

                        $('#InvTable').dataTable({
                            'columnDefs': [
                                {'targets': [2,4],
                                 'type': 'date',
                                 'render': function ( data, type, row ) {return dateRender(data, type);}
                                }
                             ],
                            "dom": '<"top ui-toolbar ui-helper-clearfix"if><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp><"clear">',
                            "displayLength": 50,
                            "lengthMenu": [[20, 50, 100, -1], [20, 50, 100, "All"]],
                            "order": [[ 1, 'asc' ]]
                        });
                    }
                }
            });
    });

    if (pmtMkup !== '') {
        $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
    }

    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
    }

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
            "dom": '<"top ui-toolbar ui-helper-clearfix"if><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp><"clear">',
        });

        $('#printButton').button().click(function() {
            $("div#printArea").printArea();
        });
        $('#tblrpt').on('click', '.invAction', function (event) {
            if ($(this).data('stat') === 'del') {
                if (!confirm('Delete Invoice #' + $(this).data('inv') + '?')) {
                    return;
                }
            }
            invoiceAction($(this).data('iid'), $(this).data('stat'), event.target.id);
        });
        $('#tblrpt').on('click', '.invSetBill', function (event) {
            event.preventDefault();
            invSetBill($(this).data('inb'), $(this).data('name'), 'div#setBillDate', '#trBillDate' + $(this).data('inb'), $('#trBillDate' + $(this).data('inb')).text(), $('#divInvNotes' + $(this).data('inb')).text(), '#divInvNotes' + $(this).data('inb'));
        });

    }

    $('#includeFields').fieldSets({'reportName': 'invoice', 'defaultFields': <?php echo json_encode($defaultFields); ?>});
});
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        <h2><?php echo $wInit->pageHeading; ?></h2>
        <div id="paymentMessage" style="display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2"></div>

        <div id="mainTabs" style="font-size:.9em;" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog">
            <ul>
                <li><a href="#invr">All Invoices</a></li>
                <li id="liInvoice"><a href="#vInv">Unpaid Invoices</a></li>
                <?php if ($useGlReport) {?>
                <li id="gl"><a href="#vGl">GL Report</a></li>
                <?php }?>
            </ul>
            <div id="invr" >
                <form id="fcat" action="InvoiceReport.php" method="post">
                    <div class="hhk-flex hhk-flex-wrap" id="filterSelectors">
                        <div>
                            <?php echo $timePeriodMarkup; ?>
                            <table style="width: 100%;">
                                <tr>
                                    <td style="border-top: 0px;"><?php echo $useVisitDatesCb; ?></td>
                                </tr>
                            </table>
                        </div>
                        <?php echo $hospitalMarkup; ?>
                        <table>
                            <tr>
                                <th>Status</th>
                            </tr>

                            <tr>
                                <td><?php echo $invStatusSelector; ?></td>
                            </tr>
                        </table>
                        <?php if ($baSelector != '') { ?>
                        <table>
                            <tr>
                                <th>Billing Agent</th>
                            </tr>

                            <tr>
                                <td><?php echo $baSelector; ?></td>
                            </tr>
                        </table>
                        <?php } ?>
                        <?php echo $columSelector; ?>
                    </div>
                    <table style="width:100%; margin: 10px 0px;">
                        <tr>
                            <td><?php echo $shoDeletedCb; ?></td>
                            <td>Search Invoice #: <input type="text" id="invNum" name="invNum" size="5"/></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
                <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox" style="display:none; padding: 5px; padding-bottom:25px;">
                    <div><input id="printButton" value="Print" type="button"/></div>
                    <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                        <?php echo $headerTableMkup; ?>
                        <div style="clear:both;"></div>
                    </div>
                    <form autocomplete="off">
                    <?php echo $dataTable; ?>
                    </form>
                </div>
            </div>
            <div id="vInv" style="display:none;">
                <input type="button" id="btnInvGo" value="Refresh"/>

                  <div id="rptInvdiv" class="hhk-visitdialog"></div>

            </div>
            <div id="vGl" class="hhk-tdbox hhk-visitdialog" style="display:none; font-size:0.8em;">
                <form name="glform" method="post" action="InvoiceReport.php">
                	<?php echo $glChooser;?>
                	<table style="float:left;">
                	<tr><th>Month</th><th>Year</th>
                	<tr>
                	<td><?php echo $glMonthSelr; ?></td>
                    <td style="vertical-align: top;"><?php echo $glYearSelr; ?></td>
                	</tr><tr>
                    <td colspan=2>
                    <input type="submit" id="btnGlcsv" name="btnGlcsv" value="csv" style="margin-right:.5em;"/>
                    <input type="submit" id="btnGlGo" name="btnGlGo" value="Show" style="margin-right:.5em;"/>
                    <input type="submit" id="btnGlTx" name="btnGlTx" value="Transfer to CentraCare"/></td>
                    </tr>
                    </table>
                </form>
                 <div id="rptGl" class="hhk-visitdialog" style="font-size:0.9em; clear:both;">
                     <?php echo $glInvoices; ?>
                 </div>
            </div>
        </div>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        <form name="xform" id="xform" method="post"></form>
        <div id="keysfees" style="font-size: .9em;"></div>

        <div id="cardonfile" style="font-size: .9em; display:none;"></div>
        <div id="setBillDate" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display:none;">
            <span class="ui-helper-hidden-accessible"><input type="text"/></span>
            <table style="width: 100%"><tr>
                    <td class="tdlabel">Invoice Number:</td>
                    <td><span id="spnInvNumber"></span></td>
                </tr><tr>
                    <td class="tdlabel">Payor:</td>
                    <td><span id="spnBillPayor"></span></td>
                </tr><tr>
                    <td class="tdlabel">Bill Date:</td>
                    <td><input id="txtBillDate" readonly="readonly" class="ckdate" /></td>
                </tr><tr>
                    <td colspan="2"><textarea rows="2" id="taBillNotes" style="width: 100%"></textarea></td>
                </tr>
            </table>
        </div>
        </div>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) { echo DeluxeGateway::getIframeMkup(); } ?>
    </body>
</html>
