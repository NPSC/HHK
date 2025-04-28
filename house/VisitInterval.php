<?php

use HHK\ColumnSelectors;
use HHK\House\Visit\VisitIntervalOldRpt;
use HHK\Exception\RuntimeException;
use HHK\House\Report\ReportFieldSet;
use HHK\House\Report\ReportFilter;
use HHK\HTMLControls\HTMLContainer;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentSvcs;
use HHK\sec\{
    Session,
    WebInit,
    Labels
};
use HHK\SysConst\{
    RoomRateCategories,
    ItemPriceCode,
    InvoiceStatus,
    ItemType,
};
use HHK\SysConst\Mode;


/**
 * VisitInterval.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require "homeIncludes.php";

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("Arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();


// Get labels
$labels = Labels::getLabels();
$paymentMarkup = '';
$receiptMarkup = '';

$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Visit Report Detail', array('style' => 'margin-top: .5em;'))
    . HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        //make receipt copy
        if ($receiptMarkup != '' && $uS->merchantReceipt == true) {
            $receiptMarkup = HTMLContainer::generateMarkup(
                'div',
                HTMLContainer::generateMarkup('div', $receiptMarkup . HTMLContainer::generateMarkup('div', 'Customer Copy', ['style' => 'text-align:center;']), ['style' => 'margin-right: 15px; width: 100%;'])
                . HTMLContainer::generateMarkup('div', $receiptMarkup . HTMLContainer::generateMarkup('div', 'Merchant Copy', ['style' => 'text-align: center']), ['style' => 'margin-left: 15px; width: 100%;'])
                ,
                ['style' => 'display: flex; min-width: 100%;', 'data-merchCopy' => '1'],
            );
        }

        // Display a status message.
        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }

        if (WebInit::isAJAX()) {
            echo json_encode(["receipt" => $receiptMarkup, ($payResult->wasError() ? "error" : "success") => $payResult->getDisplayMessage()]);
            exit;
        }
    }

} catch (RuntimeException $ex) {
    if (WebInit::isAJAX()) {
        echo json_encode(["error" => $ex->getMessage()]);
        exit;
    } else {
        $paymentMarkup = $ex->getMessage();
    }
}


$dataTable = '';
$statsTable = '';
$errorMessage = '';
$cFields = [];
$rescGroups = readGenLookupsPDO($dbh, 'Room_Group');
$useTaxes = FALSE;
$eachTaxPaid = [];
$eachTaxSQL = '';

// Look for taxes
$tstmt = $dbh->query("Select i.idItem, i.Percentage, i.Description from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = " . ItemType::Tax . " where i.Deleted = 0");

while ($taxItem = $tstmt->fetch(\PDO::FETCH_ASSOC)) {

    $eachTaxSQL .= " ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id = " . $taxItem['idItem'] . " and i.Sold_To_Id != " . $uS->subsidyId . " and i.Order_Number = v.idVisit),
            0) as `paid_".$taxItem['idItem']."`, ";

    $eachTaxPaid[$taxItem['idItem']]['desc'] = $taxItem['Description'];
    $eachTaxPaid[$taxItem['idItem']]['perc'] = $taxItem['Percentage'];

    $useTaxes = true;
}


// Dynamic column filter
$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();
$filter->createResourceGroups($dbh);

// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel Style, [td parms]
$cFields[] = ['Visit Id', 'idVisit', 'checked', 'f', 'n', '', ['style' => 'text-align:center;']];
$cFields[] = [$labels->getString('MemberType', 'primaryGuest', 'Primary Guest'), 'idPrimaryGuest', 'checked', '', 's', '', []];
$cFields[] = [$labels->getString('MemberType', 'patient', 'Patient'), 'idPatient', 'checked', '', 's', '', []];

// Patient address.
if ($uS->PatientAddr) {

    $pFields = ['pAddr', 'pCity'];
    $pTitles = [$labels->getString('MemberType', 'patient', 'Patient') . ' Address', $labels->getString('MemberType', 'patient', 'Patient') . ' City'];

    if ($uS->county) {
        $pFields[] = 'pCounty';
        $pTitles[] = $labels->getString('MemberType', 'patient', 'Patient') . ' County';
    }

    $pFields = array_merge($pFields, array('pState', 'pCountry', 'pZip'));
    $pTitles = array_merge($pTitles, [$labels->getString('MemberType', 'patient', 'Patient') . ' State', $labels->getString('MemberType', 'patient', 'Patient') . ' Country', $labels->getString('MemberType', 'patient', 'Patient') . ' Zip']);

    $cFields[] = array($pTitles, $pFields, '', '', 's', '', array());
}

if ($uS->ShowBirthDate) {
    $cFields[] = [$labels->getString('MemberType', 'patient', 'Patient') . ' DOB', 'pBirth', '', '', 'n', "", [], 'date'];
}

// Referral Agent
if ($uS->ReferralAgent) {
    $cFields[] = [$labels->getString('hospital', 'referralAgent', 'Ref. Agent'), 'Referral_Agent', 'checked', '', 's', '', []];
}

// Hospital
if (count($filter->getHospitals()) > 1) {

    if (count($filter->getAList()) > 0) {
        $cFields[] = [$labels->getString('hospital', 'hospital', 'Hospital') . " / Assoc", 'hospitalAssoc', 'checked', '', 's', '', []];
    } else {
        $cFields[] = [$labels->getString('hospital', 'hospital', 'Hospital'), 'hospitalAssoc', 'checked', '', 's', '', []];
    }
}

if ($uS->searchMRN) {
    $cFields[] = array($labels->getString('hospital', 'MRN', 'MRN'), 'MRN', '', '', 's', '', array());
}

if ($uS->Doctor) {
    $cFields[] = array("Doctor", 'Doctor', '', '', 's', '', array());
}

$locations = readGenLookupsPDO($dbh, 'Location');
if (count($locations) > 0) {
    $cFields[] = array($labels->getString('hospital', 'location', 'Location'), 'Location', 'checked', '', 's', '', array());
}

$diags = readGenLookupsPDO($dbh, 'Diagnosis');
if (count($diags) > 0) {
    $cFields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', 'checked', '', 's', '', array());
}

if ($uS->ShowDiagTB) {
    $cFields[] = array($labels->getString('hospital', 'diagnosisDetail', 'Diagnosis Details'), 'Diagnosis2', 'checked', '', 's', '20', array());
}

if ($uS->InsuranceChooser) {
    $cFields[] = array($labels->getString('MemberType', 'patient', 'Patient') . " Insurance", 'Insurance', '', '', 's', '', array());
}

$cFields[] = array("Arrive", 'Arrival', 'checked', '', 'n', '', array(), 'date');
$cFields[] = array("Depart", 'Departure', 'checked', '', 'n', '', array(), 'date');
$cFields[] = array("Room", 'Title', 'checked', '', 's', '', array('style' => 'text-align:center;'));

if ($uS->VisitFee) {
    $cFields[] = array($labels->getString('statement', 'cleaningFeeLabel', "Clean Fee"), 'visitFee', 'checked', '', 's', '', array('style' => 'text-align:right;'));
}

$adjusts = readGenLookupsPDO($dbh, 'Addnl_Charge');
if (count($adjusts) > 0) {
    $cFields[] = array("Addnl Charge", 'adjch', 'checked', '', 's', '', array('style' => 'text-align:right;'));

    if ($useTaxes) {
        $cFields[] = ["Addnl Tax", 'adjchtx', 'checked', '', 's', '', ['style' => 'text-align:right;']];
    }
}


$cFields[] = array("Nights", 'nights', 'checked', '', 'n', '', array('style' => 'text-align:center;'));
$cFields[] = array("Days", 'days', '', '', 'n', '', array('style' => 'text-align:center;'));

$amtChecked = 'checked';

if ($uS->RoomPriceModel !== ItemPriceCode::None) {

    if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {

        $cFields[] = array('Extra ' . $labels->getString('MemberType', 'guest', 'Guest') . ' Nights', 'gnights', 'checked', '', 'n', '', array('style' => 'text-align:center;'));
        $cFields[] = array("Rate Per " . $labels->getString('MemberType', 'guest', 'Guest'), 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());
        $cFields[] = array("Mean Rate Per " . $labels->getString('MemberType', 'guest', 'Guest'), 'meanGstRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

    } else {

        $cFields[] = array("Rate", 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());

        if ($uS->RoomPriceModel == ItemPriceCode::NdayBlock) {
            $cFields[] = array("Adj. Rate", 'rateAdj', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
        }

        $cFields[] = array("Mean Rate", 'meanRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
    }

    $cFields[] = array("Lodging Charge", 'lodg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

    if ($useTaxes) {
        $tFields = ['taxcgd'];
        $tTitles = ['Lodging Tax Charged'];

        foreach ($eachTaxPaid as $k => $t) {
            $tTitles[] = rtrim(number_format($t['perc'], 3), "0.") . '% Tax Charged';
            $tFields[] = "chg_$k";
        }

        $cFields[] = [$tTitles, $tFields, $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', ['style' => 'text-align:right;']];
    }

    $cFields[] = array($labels->getString('MemberType', 'visitor', 'Guest') . " Paid", 'gpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
    $cFields[] = array("3rd Party Paid", 'thdpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
    $cFields[] = array("House Paid", 'hpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
    $cFields[] = array("Lodging Paid", 'totpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

    if ($useTaxes) {

        $tFields = ['taxpd'];
        $tTitles = ['Lodging Tax Paid'];

        foreach ($eachTaxPaid as $k => $t) {
            $tTitles[] = rtrim(number_format($t['perc'], 3), "0.") . '% Tax Paid';
            $tFields[] = "paid_$k";
        }

        $cFields[] = [$tTitles, $tFields, $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', ['style' => 'text-align:right;']];
    }

    $cFields[] = array("Unpaid", 'unpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
    $cFields[] = array("Pending", 'pndg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

    if ($useTaxes) {
        $cFields[] = array('Tax Pending', 'taxpndg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
    }

    if ($uS->RoomPriceModel != ItemPriceCode::NdayBlock) {
        $cFields[] = array("Rate Subsidy", 'sub', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
    }

    $cFields[] = array("Contribution", 'donpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
}

$fieldSets = ReportFieldSet::listFieldSets($dbh, 'visit', true);
$fieldSetSelection = isset($_REQUEST['fieldset']) ? $_REQUEST['fieldset'] : '';

$defaultFields = [];

foreach ($cFields as $field) {
    if ($field[2] == 'checked') {
        $defaultFields[] = $field[1];
    }
}

$colSelector = new ColumnSelectors($cFields, 'selFld', true, $fieldSets, $fieldSetSelection);


if (isset($_POST['btnHere']) || isset($_POST['btnExcel']) || isset($_POST['btnStatsOnly'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    $statsOnly = FALSE;
    if (isset($_POST['btnStatsOnly'])) {
        $statsOnly = TRUE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);

    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();
    $filter->loadSelectedResourceGroups();

    // Hospitals
    $whHosp = '';
    foreach ($filter->getSelectedHosptials() as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
            } else {
                $whHosp .= "," . $a;
            }
        }
    }

    $whAssoc = '';
    foreach ($filter->getSelectedAssocs() as $a) {

        if ($a != '') {

            if ($whAssoc == '') {
                $whAssoc .= $a;
            } else {
                $whAssoc .= "," . $a;
            }
        }

    }

    if ($whHosp != '') {
        $whHosp = " and hs.idHospital in (" . $whHosp . ") ";
    }

    if ($whAssoc != '') {
        $whAssoc = " and hs.idAssociation in (" . $whAssoc . ") ";
    }

    if ($filter->getReportStart() != '' && $filter->getReportEnd() != '') {

        $visitInterval = new VisitIntervalOldRpt($eachTaxPaid);

        $tblArray = $visitInterval->doReport($dbh, $colSelector, $filter->getReportStart(), $filter->getQueryEnd(), $whHosp, $whAssoc, $local, $uS->VisitFee, $statsOnly, $rescGroups[$filter->getSelectedResourceGroups()], $eachTaxSQL);

        $dataTable = $tblArray['data'];
        $statsTable = $tblArray['stats'];
        $mkTable = 1;


        $headerTable .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($filter->getReportEnd())));

        $hospitalTitles = '';
        $hospList = $filter->getHospitals();

        foreach ($filter->getSelectedAssocs() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }
        foreach ($filter->getSelectedHosptials() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }

        if ($hospitalTitles != '') {
            $h = trim($hospitalTitles);
            $hospitalTitles = substr($h, 0, strlen($h) - 1);
            $headerTable .= HTMLContainer::generateMarkup('p', $labels->getString('hospital', 'hospital', 'Hospital') . 's: ' . $hospitalTitles);
        } else {
            $headerTable .= HTMLContainer::generateMarkup('p', 'All ' . $labels->getString('hospital', 'hospital', 'Hospital') . 's');
        }

    } else {
        $errorMessage = 'Missing the dates.';
    }

}

// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup(array('style' => 'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style' => 'float: left;margin-left:5px;'));
$roomGroupMarkup = $filter->resourceGroupsMarkup()->generateMarkup(array('style' => 'float: left;margin-left:5px;'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style' => 'float:left;margin-left:5px', 'id' => 'includeFields'));

$dateFormat = $labels->getString("momentFormats", "report", "MMM D, YYYY");

if ($uS->CoTod) {
    $dateFormat .= ' H:mm';
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_INTERVAL_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo SMS_DIALOG_JS; ?>"></script>
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

</head>

<body <?php if ($wInit->testVersion)
    echo "class='testbody'"; ?>>
    <?php echo $wInit->generatePageMenu(); ?>
    <div id="contentDiv">
        <h2><?php echo $wInit->pageHeading; ?></h2>
        <div id="paymentMessage" style="display:none;"
            class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2"></div>

        <div id="vcategory"
            class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog"
            style="min-width: 400px; padding:10px;">
            <form id="fcat" action="VisitInterval.php" method="post">
                <div class="ui-helper-clearfix">
                    <?php
                    echo $timePeriodMarkup;

                    if (count($filter->getHospitals()) > 1) {
                        echo $hospitalMarkup;
                    }
                    echo $roomGroupMarkup;
                    echo $columSelector;
                    ?>
                </div>
                <div style="text-align:center; margin-top: 10px;">
                    <span style="color:red; margin-right:1em;"><?php echo $errorMessage; ?></span>
                    <input type="submit" name="btnStatsOnly" id="btnStatsOnly" value="Stats Only"
                        style="margin-right: 1em;" />
                    <input type="submit" name="btnHere" id="btnHere" value="Run Here" style="margin-right: 1em;" />
                    <input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel" />
                </div>
            </form>
        </div>
        <div id="stats" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog"
            style="display: none; padding:10px; margin: 10px 0; clear:left;">
            <?php echo $statsTable; ?>
        </div>
        <div style="clear:both;"></div>
        <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox"
            style="display:none; font-size: .8em; padding: 5px; padding-bottom:25px; margin-bottom: 10px;">
            <div><input id="printButton" value="Print" type="button" /></div>
            <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                <?php echo $headerTable; ?>
            </div>
            <form autocomplete="off">
                <?php echo $dataTable; ?>
            </form>
        </div>
    </div>
    <input type="hidden" value="<?php echo RoomRateCategories::Fixed_Rate_Category; ?>" id="fixedRate" />
    <input type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
    <input type="hidden" id="pmtMkup" value='<?php echo $paymentMarkup; ?>' />
    <input type="hidden" id="startYear" value='<?php echo $uS->StartYear; ?>' />
    <input type="hidden" id="dateFormat" value='<?php echo $dateFormat; ?>' />
    <input type="hidden" id="makeTable" value='<?php echo $mkTable; ?>' />
    <input type="hidden" id="columnDefs" value='<?php echo json_encode($colSelector->getColumnDefs()); ?>' />
    <input type="hidden" id="defaultFields" value='<?php echo json_encode($defaultFields); ?>' />
    <div id="keysfees" style="font-size: .9em;"></div>
    <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
    <div id="hsDialog" class="hhk-tdbox hhk-visitdialog hhk-hsdialog" style="display:none;font-size:.8em;"></div>
    <div id="vehDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
    <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
    <?php if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) {
        echo DeluxeGateway::getIframeMkup();
    } ?>
    <form name="xform" id="xform" method="post"></form>
</body>

</html>