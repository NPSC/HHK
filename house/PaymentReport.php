<?php
/**
 * PaymentReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");
require (PMT . 'Receipt.php');
require (CLASSES . 'ColumnSelectors.php');
require CLASSES . 'OpenXML.php';

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

function doMarkupRow($fltrdFields, $r, $p, $isLocal, $hospital, &$total, &$tbl, &$sml, &$reportRows, $subsidyId) {

    $origAmt = $p['Payment_Amount'];
    $amt = 0;
    $payDetail = '';
    $payStatus = $p['Payment_Status_Title'];


    $payStatusAttr = array();
    $payType = $p['Payment_Method_Title'];
    $attr['style'] = 'text-align:right;';

    if ($p['idPayment_Method'] == PaymentMethod::Charge || $p['idPayment_Method'] == PaymentMethod::ChgAsCash) {

        if (isset($p['auths'])) {

            foreach ($p['auths'] as $a) {

                if ($a['Card_Type'] != '') {
                    $payDetail = $a['Card_Type'] . ' - ' . $a['Masked_Account'];
                }
            }
        }

        $payType = 'Credit Card';


    } else if ($p['idPayment_Method'] == PaymentMethod::Check || $p['idPayment_Method'] == PaymentMethod::Transfer) {

        $payDetail = $p['Check_Number'];
    }

    switch ($p['Payment_Status']) {

        case PaymentStatusCode::VoidSale:
            $attr['style'] .= 'color:grey;';
            $payStatusAttr = array('style'=>'text-align:right;');
            $amt = 0;

            break;

        case PaymentStatusCode::VoidReturn:
            $attr['style'] .= 'color:grey;';
            $payStatusAttr = array('style'=>'text-align:right;');
            $amt = 0;
            $origAmt = 0 - $origAmt;

            break;

        case PaymentStatusCode::Reverse:
            $attr['style'] .= 'color:grey;';
            $payStatusAttr = array('style'=>'text-align:right;');
            $amt = 0;

            break;

        case PaymentStatusCode::Retrn:  // Return payment
            $attr['style'] .= 'color:red;';
            $payStatusAttr = array('style'=>'text-align:right;');

            break;

        case PaymentStatusCode::Paid:

            if ($p['Is_Refund'] == 1) {
                $origAmt = 0 - $origAmt;
                $payStatus = 'Refund';
            }

            $amt = $origAmt;


            break;

        case PaymentStatusCode::Declined:
            $attr['style'] .= 'color:grey;';
            $payStatusAttr = array('style'=>'text-align:right;');
            $amt = 0;

            break;

    }


    if ($r['i']['Sold_To_Id'] == $subsidyId) {

        $payType = 'House Discount';
        $payorLast = $r['i']['Company'];
        $payorFirst = '';

    } else if ($r['i']['Bill_Agent'] == 'a') {

        $payorLast = $r['i']['Company'];
        $payorFirst = $r['i']['Last'] . ', ' . $r['i']['First'];

    } else {

        $payorLast = HTMLContainer::generateMarkup('a', $r['i']['Last'], array('href'=>'GuestEdit.php?id=' . $r['i']['Sold_To_Id'], 'title'=>'Click to go to the Guest Edit page.'));
        $payorFirst = $r['i']['First'];
    }


    $invNumber = $r['i']['Invoice_Number'];

    if ($invNumber != '') {

        $iAttr = array('href'=>'ShowInvoice.php?invnum=' . $r['i']['Invoice_Number'], 'style'=>'float:left;', 'target'=>'_blank');

        if ($r['i']['Invoice_Deleted'] > 0) {
            $iAttr['style'] .= 'color:red;';
            $iAttr['title'] = 'Invoice is Deleted.';
        } else if ($r['i']['Invoice_Balance'] != 0) {

            $iAttr['title'] = 'Partial payment.';
            $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
        }

        $invNumber = HTMLContainer::generateMarkup('a', $invNumber, $iAttr)
            .HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicon'.$p['idPayment'], 'data-stat'=>'view', 'data-iid'=>$r['i']['idInvoice'], 'style'=>'cursor:pointer;', 'title'=>'View Items'));
    }

    $invoiceMkup = HTMLContainer::generateMarkup('span', $invNumber, array("style"=>'white-space:nowrap'));

    $dateDT = new DateTime($p['Payment_Date']);

    $g = array(
        'idHospital' => $hospital,
        'Title' => $r['i']['Room'],
        'Patient_Last'=>$r['i']['Patient_Last'],
        'Patient_First'=>$r['i']['Patient_First'],
        'Pay_Type' => $payType,
        'Detail' => $payDetail,
        'Status' => $payStatus,
        'Payment_External_Id'=>$p['Payment_External_Id'],
        'By' => $p['Payment_Created_By'],
        'Notes'=>$p['Payment_Note']
    );

    if ($isLocal) {

        $g['Last'] = $payorLast;
        $g['First'] = $payorFirst;
        $g['Payment_Date'] = ($p['Payment_Date'] == '' ? '' :$dateDT->format('c'));
        $g['Invoice_Number'] = $invoiceMkup;

        $g['Orig_Amount'] = number_format($origAmt, 2);
        $g['Amount'] = number_format($amt, 2);


        $tr = '';
        foreach ($fltrdFields as $f) {
            $tr .= HTMLTable::makeTd($g[$f[1]], $f[6]);
        }

        $tbl->addBodyTr($tr);
        $total += $amt;


    } else {

        $g['Last'] = $r['i']['Last'];
        $g['First'] = $r['i']['First'];
        $g['Payment_Date'] = PHPExcel_Shared_Date::PHPToExcel(strtotime($p['Payment_Date']));
        $g['Invoice_Number'] = $r['i']['Invoice_Number'];

        $g['Orig_Amount'] = $origAmt;
        $g['Amount'] = $amt;

        $n = 0;

        $flds = array(
            $n++ => array('type' => "n",
                'value' => $r['i']['Sold_To_Id']
            ),
            $n++ => array('type' => "s",
                'value' => ($r['i']['Bill_Agent'] == 'a' ? $r['i']['Company'] : '')
            )
        );

        foreach ($fltrdFields as $f) {
            $flds[$n++] = array('type' => $f[4], 'value' => $g[$f[1]], 'style'=>$f[5]);
        }

        $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);

    }

}

$mkTable = '';  // var handed to javascript to make the report table or not.
$hdrTbl = '';
$dataTable = '';

$hospitalSelections = array();
$assocSelections = array();
$statusSelections = array();
$payTypeSelections = array();
$calSelection = '19';

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

foreach ($uS->nameLookups[GL_TableNames::PayType] as $p) {
    if ($p[2] != '') {
        $payTypes[$p[2]] = array($p[2], $p[1]);
    }
}

// Hospital and association lists
$hospList = $uS->guestLookups[GL_TableNames::Hospital];
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

// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel Style, td parms, DT Type
$cFields[] = array('Payor Last', 'Last', 'checked', '', 's', '', array());
$cFields[] = array("Payor First", 'First', 'checked', '', 's', '', array());
$cFields[] = array("Date", 'Payment_Date', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14, array(), 'date');
$cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 's', '', array());
$cFields[] = array("Room", 'Title', 'checked', '', 's', '', array('style'=>'text-align:center;'));

if ((count($hospList)) > 1) {
    $cFields[] = array($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'), 'idHospital', 'checked', '', 's', '', array());
}

$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient')." Last", 'Patient_Last', '', '', 's', '', array());
$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient')." First", 'Patient_First', '', '', 's', '', array());
$cFields[] = array("Pay Type", 'Pay_Type', 'checked', '', 's', '', array());
$cFields[] = array("Detail", 'Detail', 'checked', '', 's', '', array());
$cFields[] = array("Status", 'Status', 'checked', '', 's', '', array());
$cFields[] = array("Original Amount", 'Orig_Amount', 'checked', '', 'n', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)' , array('style'=>'text-align:right;'));
$cFields[] = array("Amount", 'Amount', 'checked', '', 'n', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

// Show External Id (external payment record id)
if ($config->getString('webServices', 'ContactManager', '') != '') {
    $cFields[] = array('External Id', 'Payment_External_Id', '', '', 's', '', array());
}

$cFields[] = array("By", 'By', 'checked', '', 's', '', array());
$cFields[] = array("Notes", 'Notes', 'checked', '', 's', '', array());

$colSelector = new ColumnSelectors($cFields, 'selFld');

// Check POST
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
        $reqs = $_POST['selPayType'];
        if (is_array($reqs)) {
            $payTypeSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
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
            $start = $startDT->format('Y-m-d');
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
            $endDT->add(new DateInterval('P1D'));
            $end = $endDT->format('Y-m-d');
        }

    } else if ($calSelection == 22) {
        // Year to date
        $start = $year . '-01-01';

        $endDT = new DateTime($year . date('m') . date('d'));

        $end = $endDT->add(new DateInterval('P1D'))->format('Y-m-d');


    } else {
        // Months
        $interval = 'P' . count($months) . 'M';
        $month = $months[0];
        $start = $year . '-' . $month . '-01';

        $endDate = new DateTime($start);
        $endDate->add(new DateInterval($interval));

        $end = $endDate->format('Y-m-d');
    }



    $whDates = " and DATE(lp.Payment_Date) < '$end' and DATE(lp.Payment_Date) >= '$start' ";

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

    $headerTable->addBodyTr(HTMLTable::makeTd($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital').'s: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrHosps));

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
                $whType = $s ;
            } else {
                $whType .= ",".$s;
            }

            if ($payTypeText == '') {
                $payTypeText .= $payTypes[$s][1];
            } else {
                $payTypeText .= ', ' . $payTypes[$s][1];
            }
        }
    }

    if ($whType != '') {
        $whType = " and lp.idPayment_Method in (" . $whType . ") ";
    } else {
        $payTypeText = 'All';
    }

    $headerTable->addBodyTr(HTMLTable::makeTd('Pay Types: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($payTypeText));

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
    vlist_inv_pments lp
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
 $whHosp $whAssoc $whDates $whStatus $whType ";

    $stmt = $dbh->query($query);
    $invoices = Receipt::processPayments($stmt, array('First', 'Last', 'Company', 'Room', 'idHospital', 'idAssociation', 'Patient_Last', 'Patient_First', 'Hosp_Arrival'));

    $tbl = null;
    $sml = null;
    $reportRows = 0;

    if (count($aList) > 0) {
        $hospHeader = 'Hospital / Assoc';
    } else {
        $hospHeader = 'Hospital';
    }

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
        $sml = OpenXML::createExcel($uS->username, 'Payment Report');

        // build header
        $hdr = array();
        $n = 0;

        $hdr[$n++] = 'Payor Id';
        $hdr[$n++] = 'Company';

        foreach ($fltrdTitles as $t) {
            $hdr[$n++] = $t;
        }

        OpenXML::writeHeaderRow($sml, $hdr);
        $reportRows++;
    }

    $name_lk = $uS->nameLookups;
    $name_lk['Pay_Status'] = readGenLookupsPDO($dbh, 'Pay_Status');
    $uS->nameLookups = $name_lk;
    $total = 0;

    // Now the data ...
    foreach ($invoices as $r) {

        // Payments
        foreach ($r['p'] as $p) {

            // Hospital
            $hospital = '';
            $assoc = '';
            $hosp = '';

            if ($r['i']['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['i']['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$r['i']['idAssociation']][1] != '(None)') {
                $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['i']['idAssociation']][1] . ' / ';
                $assoc = $uS->guestLookups[GL_TableNames::Hospital][$r['i']['idAssociation']][1];
            }
            if ($r['i']['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['i']['idHospital']])) {
                $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['i']['idHospital']][1];
                $hosp = $uS->guestLookups[GL_TableNames::Hospital][$r['i']['idHospital']][1];
            }


            doMarkupRow($fltrdFields, $r, $p, $local, $hospital, $total, $tbl, $sml, $reportRows, $uS->subsidyId, $uS->returnId);

        }
    }



    // Finalize and print.
    if ($local) {

        $headerTable->addBodyTr(HTMLTable::makeTd('Total Amount: ', array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format($total,2), array('style'=>'font-weight:bold;')));

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display'));
        $mkTable = 1;
        $hdrTbl = $headerTable->generateMarkup();

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

// Prepare controls

$statusSelector = HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($statusList, $statusSelections), array('name' => 'selPayStatus[]', 'size' => '7', 'multiple' => 'multiple'));


$payTypeSelector = HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($payTypes, $payTypeSelections), array('name' => 'selPayType[]', 'size' => '5', 'multiple' => 'multiple'));


$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>$monSize, 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
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
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
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
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
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
                    <?php if ((count($aList) + count($hList)) > 1) { ?>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2"><?php echo $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'); ?> Filter</th>
                        </tr>
                        <?php if (count($aList) > 0) { ?><tr>
                            <th>Associations</th>
                            <th><?php echo $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'); ?>s</th>
                        </tr><?php } ?>
                        <tr>
                            <?php if (count($aList) > 0) { ?><td><?php echo $assocs; ?></td><?php } ?>
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
                    <?php echo $columSelector; ?>
                   <table style="width:100%; clear:both;">
                        <tr>
                            <td style="width:50%;"></td>
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
                    <?php echo $hdrTbl; ?>
                </div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
