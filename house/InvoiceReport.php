<?php
/**
 * InvoiceReport.php
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require ("homeIncludes.php");

require (DB_TABLES . 'MercuryRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (CLASSES . 'MercPay/MercuryHCClient.php');
require (CLASSES . 'MercPay/Gateway.php');
require (CLASSES . 'FinAssistance.php');

require (PMT . 'Payments.php');
require (PMT . 'HostedPayments.php');
require (PMT . 'Receipt.php');
require (PMT . 'Invoice.php');
require (PMT . 'InvoiceLine.php');
require (PMT . 'CreditToken.php');
require (PMT . 'CheckTX.php');
require (PMT . 'CashTX.php');
require (PMT . 'Transaction.php');

require (CLASSES . 'PaymentSvcs.php');




try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

$config = new Config_Lite(ciCFG_FILE);

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();

$isGuestAdmin = ComponentAuthClass::is_Authorized('guestadmin');

function doMarkupRow($r, $isLocal, $hospital, $statusTxt, &$tbl, &$sml, &$reportRows, $subsidyId) {

    $payor = $r['Company'];
    if ($r['Sold_To_Name'] != '') {

        if ($r['Company'] != '') {
            $payor = $r['Company'] . ' c/o ';
        }

        $payor .= $r['Sold_To_Name'];
    }


    $patient = $r['Patient_Name'];
    if ($r['idPatient'] > 0) {
        $patient = HTMLContainer::generateMarkup('a', $patient, array('href'=>'GuestEdit.php?id='.$r['idPatient']));
    }

        $payments = HTMLContainer::generateMarkup('span', number_format(($r['Amount'] - $r['Balance']), 2), array('style'=>'float:right;'));
        if (($r['Amount'] - $r['Balance']) != 0 && $r['Sold_To_Id'] != $subsidyId) {
            $payments .= HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'vwpmt'.$r['idInvoice'], 'data-iid'=>$r['idInvoice'], 'data-stat'=>'vpmt', 'style'=>'cursor:pointer;', 'title'=>'View Payments'));
        }

    $invoiceNumber = $r['Invoice_Number'];
    if ($invoiceNumber != '') {

        $invAttr = array('href'=>'ShowInvoice.php?invnum='.$r['Invoice_Number'], 'style'=>'float:left;', 'target'=>'_blank');

        if ($r['Balance'] != 0 && $r['Balance'] != $r['Amount']) {
            $invoiceNumber .= HTMLContainer::generateMarkup('sup', '-p');
            $invAttr['title'] = 'Partial Payment';
        }

        $invoiceNumber = HTMLContainer::generateMarkup('a', $invoiceNumber, $invAttr)
            .HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicon'.$r['idInvoice'], 'data-iid'=>$r['idInvoice'], 'data-stat'=>'view', 'style'=>'cursor:pointer;', 'title'=>'View Items'));
    }

    $invoiceMkup = HTMLContainer::generateMarkup('span', $invoiceNumber, array("style"=>'white-space:nowrap;'));

    if ($r['Status'] == InvoiceStatus::Carried && $r['Deleted'] == 0) {

        $r['Balance'] = 0;

        $statusMkup = HTMLContainer::generateMarkup('span',
                HTMLContainer::generateMarkup('span', $statusTxt . ' by ' . HTMLContainer::generateMarkup('a', $r['Delegated_Invoice_Number'], array('href'=>'ShowInvoice.php?invnum='.$r['Delegated_Invoice_Number'], 'target'=>'_blank')), array('style'=>'float:left;'))
                .HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicond'.$r['Delegated_Invoice_Id'], 'data-iid'=>$r['Delegated_Invoice_Id'], 'data-stat'=>'view', 'style'=>'cursor:pointer;', 'title'=>'View Items'))
                , array("style"=>'white-space:nowrap;'));
    } else {

        $statusMkup = HTMLContainer::generateMarkup('span', $statusTxt, array("style"=>'white-space:nowrap;'));
    }

    $dateDT = new DateTime($r['Invoice_Date']);

    $billDateStr = ($r['BillDate'] == '' ? '' : date('M j, Y', strtotime($r['BillDate'])));
    //$billIcon = '';

//    if ($r['Status'] == InvoiceStatus::Unpaid) {
        $billDate = HTMLContainer::generateMarkup('span', $billDateStr, array('id'=>'trBillDate' . $r['Invoice_Number']));
        $billIcon = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-calendar invSetBill', 'data-name'=>$payor, 'data-inb'=>$r['Invoice_Number'], 'style'=>'float:right;cursor:pointer;margin-left:5px;', 'title'=>'Set Billing Date'));
//    }

    $invoiceAmt = number_format($r['Amount'], 2);

    // Show Delete Icon?
    if (($r['Amount'] == 0 || $r['Item_Id'] == ItemId::Discount) && $r['Deleted'] != 1) {
        $invoiceAmt .= HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-trash invAction', 'id'=>'invdel'.$r['idInvoice'], 'data-inv'=>$r['Invoice_Number'], 'data-iid'=>$r['idInvoice'], 'data-stat'=>'del', 'style'=>'cursor:pointer;float:left;', 'title'=>'Delete This Invoice'));
    }


    if ($isLocal) {

        $tbl->addBodyTr(
                HTMLTable::makeTd($invoiceMkup, array('style'=>'text-align:center;'))
                .HTMLTable::makeTd($dateDT->format('M j, Y'))
                .HTMLTable::makeTd($statusMkup)
                .HTMLTable::makeTd($payor)
                .HTMLTable::makeTd($billDate . $billIcon)
                .HTMLTable::makeTd($r['Title'], array('style'=>'text-align:center;'))
                .HTMLTable::makeTd($hospital)
                .HTMLTable::makeTd($patient)
                .HTMLTable::makeTd($invoiceAmt, array('style'=>'text-align:right;'))
                .HTMLTable::makeTd($payments)
                .HTMLTable::makeTd(number_format($r['Balance'], 2), array('style'=>'text-align:right;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('div', $r['Notes'], array('id'=>'divInvNotes' . $r['Invoice_Number'], 'style'=>'max-width:190px;')))
                );

    } else {

        $n = 0;
        $flds = array(
            $n++ => array('type' => "s",
                'value' => $r['Invoice_Number']
            ),
            $n++ => array('type' => "n",
                'value' => PHPExcel_Shared_Date::PHPToExcel(strtotime($r['Invoice_Date'])),
                'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14
            ),
            $n++ => array('type' => "s",
                'value' => $statusTxt
            ),
            $n++ => array('type' => "s",
                'value' => $payor
            ),
            $n++ => array('type' => "s",
                'value' => $billDateStr
            ),
             $n++ => array('type' => "s",
                'value' => $r['Title']
            ),
           $n++ => array('type' => "s",
                'value' => $hospital
            ),
            $n++ => array('type' => "s",
                'value' => $r['Patient_Name']
            ),
            $n++ => array('type' => "n",
                'value' => $r['Amount']
            ),
            $n++ => array('type' => "n",
                'value' => $r['Balance']
            ),
            $n++ => array('type' => "s",
                'value' => $r['Notes']
            )
        );

        $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);

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
$baSelector = 'None';

$year = date('Y');
$months = array(date('n'));       // logically overloaded.
$txtStart = '';
$txtEnd = '';
$status = '';
$statsTable = '';
$start = '';
$end = '';
$errorMessage = '';
$showDeleted = FALSE;
$useVisitDates = FALSE;


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


// Hospital and association lists
$hospList = $uS->guestLookups[GL_TableNames::Hospital];
$hList = array();
$aList = array();
foreach ($hospList as $h) {
    if ($h[2] == 'h') {
        $hList[$h[0]] = array(0=>$h[0], 1=>$h[1]);
    } else if ($h[2] == 'a' && $h[1] != '(None)') {
        $aList[$h[0]] = array(0=>$h[0], 1=>$h[1]);
    }
}

// Invoices
$invoiceStatuses = readGenLookupsPDO($dbh, 'Invoice_Status');

// Billing agent.
$stmt = $dbh->query("SELECT n.idName, n.Name_First, n.Name_Last, n.Company " .
        " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
        " where n.Member_Status='a' and n.Record_Member = 1 order by n.Name_Last, n.Name_First");

$bagnts = array();

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


$invNum = '';
if (isset($_REQUEST['invNum'])) {

    $invNum = filter_var($_REQUEST['invNum'], FILTER_SANITIZE_NUMBER_INT);
}


if (isset($_POST['btnHere']) || isset($_POST['btnExcel']) || $invNum != '') {

    $headerTable = new HTMLTable();
    $headerTable->addBodyTr(HTMLTable::makeTd('Report Generated: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y')));

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    if (isset($_POST['cbShoDel'])) {
        $showDeleted = TRUE;
    }

    if (isset($_POST['cbUseVisitDates'])) {
        $useVisitDates = TRUE;
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

    if (isset($_POST['selInvStatus'])) {
        $reqs = $_POST['selInvStatus'];
        if (is_array($reqs)) {
            $invStatus = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if (isset($_POST['selbillagent'])) {
        $reqs = $_POST['selbillagent'];
        if (is_array($reqs)) {
            $baSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
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
            $start = $startDT->format('Y-m-d 00:00:00');
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
            $end = $endDT->format('Y-m-d 23:59:59');
        }

    } else if ($calSelection == 22) {
        // Year to date
        $start = $year . '-01-01 00:00:00';

        $endDT = new DateTime($year . date('m') . date('d'));

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

    $whHosp = '';
    $whAssoc = '';
    $whStatus = '';
    $whBillAgent = '';
    $whDeleted = '';

    if ($invNum != '') {

        $whDates = " i.Invoice_Number = '$invNum' ";
        $headerTable->addBodyTr(HTMLTable::makeTd('For Invoice Number: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($invNum));

    } else {

        $headerTable->addBodyTr(HTMLTable::makeTd('Reporting Period: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start)) . ' thru ' . date('M j, Y', strtotime($end))));

        if ($useVisitDates) {
            $whDates = " and DATE(v.Arrival_Date) < '$end' and ifnull(DATE(v.Actual_Departure), DATE(v.Expected_Departure)) >= '$start' ";
        } else {
            $whDates = " and DATE(`i`.`Invoice_Date`) < '$end' and DATE(`i`.`Invoice_Date`) >= '$start' ";
        }


        // Hospitals
        $hdrHosps = 'All';
        foreach ($hospitalSelections as $a) {
            if ($a != '') {
                if ($whHosp == '') {
                    $whHosp .= $a;
                    $hdrHosps = $hList[$a][1];
                } else {
                    $whHosp .= ",". $a;
                    $hdrHosps .= ", ". $hList[$a][1];
                }
            }
        }

        $hdrAssocs = 'All';
        foreach ($assocSelections as $a) {
            if ($a != '') {
                if ($whAssoc == '') {
                    $whAssoc .= $a;
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

        $headerTable->addBodyTr(HTMLTable::makeTd('Hospitals: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrHosps));

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
            $whDeleted = '1=1';
        } else {
            $whDeleted = 'i.Deleted = 0';
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
ifnull(re.Title, '') as `Title`,
ifnull(hs.idHospital, 0) as `idHospital`,
ifnull(hs.idAssociation, 0) as `idAssociation`,
ifnull(hs.idPatient, 0) as `idPatient`,
`i`.`idGroup`,
`i`.`Invoice_Date`,
i.BillStatus,
i.BillDate,
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
    left join resource re on v.idResource = re.idResource
    left join invoice di on i.Delegated_Invoice_Id = di.idInvoice
where $whDeleted $whDates $whHosp $whAssoc  $whStatus $whBillAgent ";

    $stmt = $dbh->query($query);

    $tbl = null;
    $sml = null;
    $reportRows = 0;

    if (count($aList) > 0) {
        $hospHeader = 'Hospital / Assoc';
    } else {
        $hospHeader = 'Hospital';
    }

    if ($local) {
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(
                HTMLTable::makeTh('Inv #')
                .HTMLTable::makeTh('Date')
                .HTMLTable::makeTh('Status')
                .HTMLTable::makeTh('Payor')
                .HTMLTable::makeTh('Billed')
                .HTMLTable::makeTh('Room')
                .HTMLTable::makeTh($hospHeader)
                .HTMLTable::makeTh('Patient')
                .HTMLTable::makeTh('Amount')
                .HTMLTable::makeTh('Payments')
                .HTMLTable::makeTh('Balance')
                .HTMLTable::makeTh('Notes'));

    } else {
        require_once CLASSES . 'OpenXML.php';

        $reportRows = 1;
        $file = 'PaymentReport';
        $sml = OpenXML::createExcel($uS->username, 'Payment Report');

        // build header
        $hdr = array();
        $n = 0;

        $hdr[$n++] = "Invoice Number";
        $hdr[$n++] = "Date";
        $hdr[$n++] = "Status";
        $hdr[$n++] = "Payor";
        $hdr[$n++] = "Billed";
        $hdr[$n++] = "Room";
        $hdr[$n++] = $hospHeader;
        $hdr[$n++] = "Patient";
        $hdr[$n++] = "Amount";
        $hdr[$n++] = "Balance";
        $hdr[$n++] = "Notes";

        OpenXML::writeHeaderRow($sml, $hdr);
        $reportRows++;
    }

    $totalAmount = 0.0;
    $totalPaid = 0.0;
    $totalBalance = 0.0;

    $invStatuses = readGenLookupsPDO($dbh, 'Invoice_Status');


    // Now the data ...
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // Hospital
        $hospital = '';
        $assoc = '';
        $hosp = '';

        if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] != '(None)') {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] . ' / ';
            $assoc = $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1];
        }
        if ($r['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']])) {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
            $hosp = $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
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

        doMarkupRow($r, $local, $hospital, $statusTxt, $tbl, $sml, $reportRows, $uS->subsidyId);

    }




    // Finalize and print.
    if ($local) {

        $tbl->addFooterTr(HTMLTable::makeTd('', array('colspan'=>'7'))
            .HTMLTable::makeTd('Totals:', array('style'=>'text-align:right; border-top:2px solid black;'))
            .HTMLTable::makeTd('$'.number_format($totalAmount,2), array('style'=>'text-align:right; border-top:2px solid black;'))
            .HTMLTable::makeTd('$'.number_format($totalPaid,2), array('style'=>'text-align:right; border-top:2px solid black;'))
            .HTMLTable::makeTd('$'.number_format($totalBalance,2), array('style'=>'text-align:right; border-top:2px solid black;'))
                .HTMLTable::makeTd('')
            );

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'style'=>'font-size:.8em;'));
        $mkTable = 1;

        $headerTableMkup = $headerTable->generateMarkup();

    } else {

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();

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

$invStatusSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($invoiceStatuses, $invStatus), array('name' => 'selInvStatus[]', 'size' => '4', 'multiple' => 'multiple'));


if (count($bagnts) > 0) {

    $baSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($bagnts, $baSelections), array('name' => 'selbillagent[]', 'size' => '4', 'multiple' => 'multiple'));
}

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>$monSize, 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'5'));

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
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo TOP_NAV_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS ?>"></script>
<script type="text/javascript">
function invSetBill(inb, name, idDiag, idElement, billDate, notes, notesElement) {
    var dialg =  $(idDiag);
    var buttons = {
        "Save": function() {

            var dt;
            var nt = dialg.find('#taBillNotes').val();

            if (dialg.find('#txtBillDate').val() != '') {
                dt = dialg.find('#txtBillDate').datepicker('getDate').toJSON();
            }

            $.post('ws_resc.php', {cmd: 'invSetBill', inb:inb, date:dt, ele: idElement, nts: nt, ntele: notesElement},
              function(data) {

                if (data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert("Parser error - " + err.message);
                        return;
                    }

                    if (data.gotopage) {
                        window.location.assign(data.gotopage);
                    } else if (data.success) {

                        if (data.elemt && data.elemt !== '' && data.strDate) {
                            $(data.elemt).text(data.strDate);
                        }

                        if (data.notesElemt && data.notesElemt !== '' && data.notes) {
                            $(data.notesElemt).text(data.notes);
                        }
                    }
                }
            });

            $(this).dialog("close");

        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };

    dialg.find('#spnInvNumber').text(inb);
    dialg.find('#spnBillPayor').text(name);
    dialg.find('#txtBillDate').val(billDate);
    dialg.find('#taBillNotes').val(notes);
    dialg.find('#txtBillDate').datepicker({numberOfMonths: 1});

    dialg.dialog('option', 'buttons', buttons);
    dialg.dialog('option', 'width', 500);
    dialg.dialog('open');
}
function flagAlertMessage(mess, wasError) {
    "use strict";
    var spn = document.getElementById('alrMessage');
    if (!wasError) {
        // define the success message markup
        $('#alrResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
        $('#alrIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
        spn.innerHTML = "<strong>Success: </strong>" + mess;
        $("#divAlert1").show("slide");
        window.scrollTo(0, 5);
    } else {
        // define the error message markup
        $('alrResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#alrIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Alert: </strong>" + mess;
        $("#divAlert1").show("pulsate");
        window.scrollTo(0, 5);
    }
}

function invoiceAction(idInvoice, action, eid) {
    $.post('ws_resc.php', {cmd: 'invAct', iid: idInvoice, x:eid, action: action},
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
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.delete) {
                flagAlertMessage(data.delete, false);
            }
            if (data.markup) {
                var contr = $(data.markup);
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
    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
    var makeTable = '<?php echo $mkTable; ?>';
    $('#btnHere, #btnExcel').button();
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
    $("#setBillDate").dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Set Invoice Billing Date'
    });


    // disappear the pop-ups.
    $(document).mousedown(function (event) {
        var target = $(event.target);
        if ($('div#pudiv').length > 0 && target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

    if (makeTable === '1') {
        $('div#printArea').css('display', 'block');
        try {
            listTable = $('#tblrpt').dataTable({
                "iDisplayLength": 50,
                "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                "dom": '<"top"ilf>rt<"bottom"ilp><"clear">'
            });
        }
        catch (err) { }
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
});
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="InvoiceReport.php" method="post">
                    <h2><?php echo $wInit->pageHeading; ?></h2>
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
                        <tr>
                            <td colspan="3"><?php echo $useVisitDatesCb; ?></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2">Hospital Filter</th>
                        </tr>
                        <?php if (count($aList) > 0) { ?><tr>
                            <th>Associations</th>
                            <th>Hospitals</th>
                        </tr><?php } ?>
                        <tr>
                            <?php if (count($aList) > 0) { ?><td><?php echo $assocs; ?></td><?php } ?>
                            <td><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <th>Status</th>
                        </tr>

                        <tr>
                            <td><?php echo $invStatusSelector; ?></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <th>Billing Agent</th>
                        </tr>

                        <tr>
                            <td><?php echo $baSelector; ?></td>
                        </tr>
                    </table>
                    <table style="width:100%; clear:both;">
                        <tr>
                            <td><?php echo $shoDeletedCb; ?></td>
                            <td>Search: <input type="text" id="invNum" name="invNum" size="5"/></td>
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
                    <?php echo $headerTableMkup; ?>
                </div>
                <?php echo $dataTable; ?>
            </div>
        <div id="setBillDate" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em;">
            <span class="ui-helper-hidden-accessible"><input type="text"/></span>
            <table><tr>
                    <td class="tdlabel">Invoice Number:</td>
                    <td><span id="spnInvNumber"></span></td>
                </tr><tr>
                    <td class="tdlabel">Payor:</td>
                    <td><span id="spnBillPayor"></span></td>
                </tr><tr>
                    <td class="tdlabel">Bill Date:</td>
                    <td><input id="txtBillDate" readonly="readonly" class="ckdate" /></td>
                </tr><tr>
                    <td colspan="2"><textarea rows="2" cols="50" id="taBillNotes" ></textarea></td>
                </tr>
            </table>
        </div>
        </div>
    </body>
</html>
