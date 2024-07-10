<?php


use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\Deluxe\Request\Reports\CcReconciliationReport;
use HHK\Payment\PaymentGateway\Deluxe\Request\Reports\CcTransactionReport;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\GLTableNames;
use HHK\ColumnSelectors;
use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLSelector};
use HHK\SysConst\PaymentStatusCode;
use HHK\Payment\Statement;
use HHK\House\Report\PaymentReport;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\SysConst\ItemPriceCode;
use HHK\Payment\CreditToken;
use HHK\House\Report\ReportFieldSet;
use HHK\House\Report\ReportFilter;
use HHK\TableLog\HouseLog;


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
$labels = Labels::getLabels();
$menuMarkup = $wInit->generatePageMenu();

$mkTable = '';  // var handed to javascript to make the report table or not.
$hdrTbl = '';
$dataTable = '';

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();
$filter->createBillingAgents($dbh);
$filter->createPaymentGateways($dbh);
$filter->createPayStatuses($dbh);
$filter->createPayTypes($dbh);

$hospitalSelections = array();
$assocSelections = array();
$statusSelections = array();
$payTypeSelections = array();
$billingAgentSelections = array();
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
$tabReturn = 0;
$delCofListClass = 'hhk-delcoflist';

// COF listing return
if (isset($_POST['cmd'])) {

	$dataArray = array();
	$cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    switch($cmd){
        case 'cof':
            $dataArray['coflist'] = CreditToken::getCardsOnFile($dbh, 'GuestEdit.php?id=');
            break;
        case 'ccReconciliation':
            $start = filter_input(INPUT_POST, 'startDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $end = filter_input(INPUT_POST, 'endDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            try{
                $report = new CcReconciliationReport($dbh, new DeluxeGateway($dbh, 'wireland'));
                $start = new DateTime($start);
                $end = new DateTime($end);
                $data = $report->submit($start, $end);
                $dataArray["ccReconciliation"] = $data["data"];
            }catch(\Exception $e){

            }
            
            break;
        case 'ccTransaction':
            $start = filter_input(INPUT_POST, 'startDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $end = filter_input(INPUT_POST, 'endDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            try{
                $report = new CcTransactionReport($dbh, new DeluxeGateway($dbh, 'wireland'));
                $start = new DateTime($start);
                $end = new DateTime($end);
                $data = $report->submit($start, $end);
                $dataArray["ccTransaction"] = $data["data"];
            }catch(\Exception $e){

            }
            break;
        default:
            $dataArray["error"] = "Unknown Command";
    }

	echo json_encode($dataArray);
	exit();
}


// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms, DT Type
$cFields[] = array('Payor Last', 'Last', 'checked', '', 'string', '20', array());
$cFields[] = array("Payor First", 'First', 'checked', '', 'string', '20', array());
$cFields[] = array("Date", 'Payment_Date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("Time", 'Payment_Timestamp', 'checked', '', 'h:mm:ss AM/PM;@', '15', array(), 'time');
$cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '10', array());
$cFields[] = array("Room", 'Title', 'checked', '', 'string', '15', array('style'=>'text-align:center;'));

if ((count($filter->getHList())) > 1) {
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
if (count($filter->getPaymentGateways()) > 1) {
	$cFields[] = array('Location', 'Merchant', 'checked', '', 'string', '20', array());
}

// Show External Id (external payment record id)
if (!empty($_ENV['Service_Name'])) {
    $cFields[] = array('External Id', 'Payment_External_Id', '', '', 'string', '15', array());
}

$cFields[] = array("Updated", 'Updated', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("By", 'By', 'checked', '', 'string', '20', array());
$cFields[] = array("Notes", 'Notes', 'checked', '', 'string', '20', array());

$fieldSets = ReportFieldSet::listFieldSets($dbh, 'payment', true);
$fieldSetSelection = (isset($_REQUEST['fieldset']) ? $_REQUEST['fieldset']: '');
$colSelector = new ColumnSelectors($cFields, 'selFld', true, $fieldSets, $fieldSetSelection);
$defaultFields = array();
foreach($cFields as $field){
    if($field[2] == 'checked'){
        $defaultFields[] = $field[1];
    }
}

if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

	$tabReturn = 0;

    $headerTable = new HTMLTable();
    $headerTable->addBodyTr(HTMLTable::makeTd('Report Generated: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y')));

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);
    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();
    $filter->loadSelectedBillingAgents();
    $filter->loadSelectedPayStatuses();
    $filter->loadSelectedPayTypes();
    $filter->loadSelectedPaymentGateways();

    $whDates = " and (CASE WHEN lp.Payment_Status = 'r' THEN DATE(lp.Payment_Last_Updated) ELSE DATE(lp.Payment_Date) END) < DATE('" . $filter->getQueryEnd() . "') and (CASE WHEN lp.Payment_Status = 'r' THEN DATE(lp.Payment_Last_Updated) ELSE DATE(lp.Payment_Date) END) >= DATE('" . $filter->getReportStart() . "') ";

    $endDT = new DateTime($end);
    $endDT->sub(new DateInterval('P1D'));

    $headerTable->addBodyTr(HTMLTable::makeTd('Reporting Period: ', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($filter->getReportEnd()))));

    if (isset($_POST['txtInvoiceNumber']) && $_POST['txtInvoiceNumber'] != '') {

        if (($invoiceNumber = filter_input(INPUT_POST,'txtInvoiceNumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) === false) {
            $invoiceNumber = '0';
        }

        $where = "and lp.Invoice_Number = '$invoiceNumber' ";
        $headerTable->addBodyTr(HTMLTable::makeTd('Invoice Number: ', array('class' => 'tdlabel')) . HTMLTable::makeTd($invoiceNumber));


    } else {


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

		// Billing Agents
		$whBillAgent = '';
		foreach ($filter->getSelectedBillingAgents() as $a) {
			if ($a != '') {
				if ($whBillAgent == '') {
					$whBillAgent .= $a;
				} else {
					$whBillAgent .= ",". $a;
				}
			}
		}

		if ($whHosp != '') {
			$whHosp = " and hs.idHospital in (".$whHosp.") ";
		}

		if ($whAssoc != '') {
			$whAssoc = " and hs.idAssociation in (" . $whAssoc . ") ";
		}

		if ($whBillAgent != '') {
			$whBillAgent = " and lp.Payment_idPayor in (".$whBillAgent.") ";
		}

		$hdrHosps = $filter->getSelectedHospitalsString();
		$hdrAssocs = $filter->getSelectedAssocString();
		$hdrBillingAgents = $filter->getSelectedBillingAgentsString();
		$hospList = $filter->getHospitals();

        if (count($hospList) > 0) {
            $headerTable->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital') . 's: ', array('class' => 'tdlabel')) . HTMLTable::makeTd($hdrHosps));
        }

        if (count($filter->getAList()) > 1) {
            $headerTable->addBodyTr(HTMLTable::makeTd('Associations: ', array('class' => 'tdlabel')) . HTMLTable::makeTd($hdrAssocs));
        }

		if (count($filter->getBillingAgents()) > 1) {
			$headerTable->addBodyTr(HTMLTable::makeTd('Billing Agents: ', array('class'=>'tdlabel')) . HTMLTable::makeTd($hdrBillingAgents));
		}


        $whStatus = '';
        $payStatusText = '';
        $rtnIncluded = FALSE;
        $statusList = $filter->getPayStatuses();

		foreach ($filter->getSelectedPayStatuses() as $s) {
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
                $whStatus = " and (lp.Payment_Status in (" . $whStatus . ") or (lp.Is_Refund = 1 && lp.Payment_Status = '" . PaymentStatusCode::Paid . "')) ";
            } else {
                $whStatus = " and lp.Payment_Status in (" . $whStatus . ") ";
            }

        } else {
            $payStatusText = 'All';
        }

        $headerTable->addBodyTr(HTMLTable::makeTd('Pay Statuses: ', array('class' => 'tdlabel')) . HTMLTable::makeTd($payStatusText));


		$whType = '';
		$payTypeText = '';
		foreach ($filter->getSelectedPayTypes() as $s) {
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

			if ($whType != '') {
				$whType = " and lp.idPayment_Method in (" . $whType . ") ";
			} else {
				$payTypeText = 'All';
			}
		
		}
		$headerTable->addBodyTr(HTMLTable::makeTd('Pay Types: ', array('class' => 'tdlabel')) . HTMLTable::makeTd($payTypeText));

        $whGw = '';
        $gwText = '';

        if (count($gwSelections) > 0) {

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

                        $gwText .= (isset($gwList[$s][1]) ? ', ' . $gwList[$s][1] : '');
                    }
                }
            }

            if ($whGw != '') {
                $whGw = " and lp.`Merchant` in (" . $whGw . ") ";
            } else {
                $gwText = 'All';
            }

            $headerTable->addBodyTr(HTMLTable::makeTd('Locations: ', array('class' => 'tdlabel')) . HTMLTable::makeTd($gwText));
        }

        $where = $whHosp . $whAssoc . $whDates . $whStatus . $whType . $whGw . $whBillAgent;
    }

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
  $where ";

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
    $name_lk['Pay_Status'] = $filter->getPayStatuses();
    $uS->nameLookups = $name_lk;
    $total = 0;


    // Now the data ...
    $stmt = $dbh->query($query);
    $invoices = Statement::processPayments($stmt, array('First', 'Last', 'Company', 'Room', 'idHospital', 'idAssociation', 'Patient_Last', 'Patient_First', 'Hosp_Arrival'));

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
        HouseLog::logDownload($dbh, 'Payment Report', "Excel", "Payment Report for " . $filter->getReportStart() . " - " . $filter->getReportEnd() . " downloaded", $uS->username);
        $writer->download();
    }

}

// Setups for the page.
$statusSelector = $filter->payStatusMarkup()->generateMarkup(array('class' => 'mb-2 mr-2'));
$payTypeSelector = $filter->payTypesMarkup()->generateMarkup(array('class' => 'mb-2 mr-2'));
$gwSelector = $filter->paymentGatewaysMarkup()->generateMarkup(array('class' => 'mb-2 mr-2'));
$timePeriodMarkup = $filter->timePeriodMarkup('Payment')->generateMarkup(array('class'=>'mb-2 mr-2'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('class'=>'mb-2 mr-2'));
$baSelector = $filter->billingAgentMarkup()->generateMarkup(array('class'=>'mb-2 mr-2'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('class'=>'mb-2 mr-2', 'id'=>'includeFields'));

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
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

<script type="text/javascript">
	var deleteThisTr;

	function delCofEntry(gtId) {
        $.post('PaymentReport.php', {cmd: 'delcof', 'gtId':gtId},
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

                    } else if (data.message && data.message == 'true') {
						deleteThisTr.remove();
						flagAlertMessage('The Card on file entry is deleted.', 'success');
                    }
                }
            }
        );
	}

    $(document).ready(function() {
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var makeTable = '<?php echo $mkTable; ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
        var tabReturn = '<?php echo $tabReturn; ?>';
        var delCofClass = '<?php echo $delCofListClass; ?>';

        $('#btnHere, #btnExcel, #cbColClearAll, #cbColSelAll').button();
        $('.ckdate').datepicker({
            yearRange: '<?php echo $uS->StartYear; ?>:+01',
            changeMonth: true,
            changeYear: true,
            autoSize: true,
            numberOfMonths: 1,
            dateFormat: 'M d, yy'
        });
        $('#mainTabs').tabs({
        	beforeActivate: function (event, ui) {
                if (ui.newTab.prop('id') === 'licof') {

                    $.post('PaymentReport.php', {cmd: 'cof'},
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

                            } else if (data.coflist) {
								$('#cofDiv').empty().append($(data.coflist));

								$('#cofDiv').on('change', '.'+delCofClass, function (){
									var gid = $(this).val();
									deleteThisTr = $(this).parents('tr');
									delCofEntry(gid);
								});
                            }
                        }
                    });

                }
        	}
        });
        $('#mainTabs').tabs("option", "active", tabReturn);

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
        });
        if (makeTable === '1') {
            $('#rptFeeLoading').hide();
            $('div#hhk-reportWrapper').css('display', 'block');
            $('#tblrpt').dataTable({
                'columnDefs': [
                    {'targets': columnDefs,
                     'type': 'date',
                     'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                    }
                 ],
                "displayLength": 50,
                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                //"dom": '<"top ui-toolbar ui-helper-clearfix"ilf><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp><"clear">',
                "dom": '<\"top ui-toolbar ui-helper-clearfix\"if><\"hhk-overflow-x\"rt><\"bottom ui-toolbar ui-helper-clearfix\"lp>',
            });

            $('#printButton').button().click(function() {
                $("div#hhk-reportWrapper").printArea();
            });
            $('#tblrpt').on('click', '.invAction', function (event) {
                invoiceAction($(this).data('iid'), 'view', event.target.id, '', true);
            });
        }

        $('#includeFields').fieldSets({'reportName': 'payment', 'defaultFields': <?php echo json_encode($defaultFields); ?>});
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
        <div id="mainTabs" style="font-size:.9em;" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog">
            <ul>
                <li><a href="#payr">Payments</a></li>
                <?php if ($uS->RoomPriceModel != ItemPriceCode::None) {?>
                <li id='licof'><a href="#cards">Credit Cards on File</a></li>
                <?php }?>
            </ul>
            <div id="payr" >
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog filterWrapper">
                <form id="fcat" action="PaymentReport.php" method="post">
                    <div class="hhk-flex hhk-flex-wrap" id="filterSelectors">
                    <?php
                        echo $timePeriodMarkup;

                    	if (count($filter->getHospitals()) > 1) {
                            echo $hospitalMarkup;
                        }
                        if(count($filter->getBillingAgents()) > 1) {
                            echo $baSelector;
                        }

                        echo $payTypeSelector;
                        echo $statusSelector;

                        if(count($filter->getPaymentGateways()) > 1){
                            echo $gwSelector;
                        }
                        echo $columSelector;
                    ?>
                    </div>
                    <div id="filterBtns" class="mt-3">
						<input type='text' name="txtInvoiceNumber" id="txtInvoiceNumber" placeholder="Search Invoice Number" value='' style="margin-right:1em;"/>
                        <input type="submit" name="btnHere" id="btnHere" value="Run Here"/>
                        <input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/>
                    </div>
                </form>
                </div>
            <div id="hhk-reportWrapper" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox" style="display:none; font-size: 0.9em;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <div class="my-3" style="min-width: 350px;">
                    <?php echo $hdrTbl; ?>
                </div>
                <form autocomplete="off">
                <?php echo $dataTable; ?>
                </form>
            </div>
                 </div>
            <div id='cards'>
                <div id="cofDiv" class="hhk-visitdialog"></div>
            </div>
            </div>
       </div>
    </body>
</html>
