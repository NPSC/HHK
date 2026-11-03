<?php
use HHK\Exception\RuntimeException;
use HHK\House\GLCodes\GLCodes;
use HHK\House\GLCodes\GLParameters;
use HHK\House\GLCodes\GLTemplateRecord;
use HHK\House\Report\InvoiceReport;
use HHK\House\Report\ReportFilter;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentSvcs;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\sec\WebInit;
use HHK\SysConst\Mode;
use HHK\SysConst\WebPageCode;

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

$dataTableWrapper = '';
$paymentMarkup = '';
$receiptMarkup = '';
$receiptBilledToEmail = '';
$receiptPaymentId = 0;
$tabReturn = 0;

$year = date('Y');

//$useGlReport = stristr(strtolower($uS->siteName), 'gorecki');
$useGlReport = $uS->useGLCodes;

// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();
        $receiptBilledToEmail = $payResult->getInvoiceBillToEmail($dbh);
        $receiptPaymentId = $payResult->getIdPayment();

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
            echo json_encode(["receipt"=>$receiptMarkup, ($payResult->wasError() ? "error": "success")=>$payResult->getDisplayMessage(), 'idPayment'=>$receiptPaymentId, 'billToEmail'=>$receiptBilledToEmail]);
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


$report = new InvoiceReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $report->getInputSetReportName()]) || $report->getInvNum() != '') {
    $dataTableWrapper = $report->generateMarkup();
}

if (isset($_POST['btnExcel-' . $report->getInputSetReportName()])) {
    $report->downloadExcel("InvoiceReport");
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
            $glCodes->mapRecords()->downloadCSV();

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
			$pmstmt = $dbh->prepare("SELECT `idPayment_method`, `Method_Name` FROM `payment_method`;");
			$pmstmt->execute();
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
	$glMonthSelr = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($report->filter->getMonths(), $glMonth, FALSE), array('name' => 'selGlMonth', 'size'=>12));
	$glYearSelr = HTMLSelector::generateMarkup(ReportFilter::getYearOptionsMarkup($year, ($uS->StartYear ? $uS->StartYear : "2013"), 0, FALSE), array('name' => 'selGlYear', 'size'=>'12'));

}

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
        <?php echo BOOTSTRAP_ICONS_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
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
    var pmtMkup = '<?php echo $paymentMarkup; ?>';
    var rctMkup = '<?php echo $receiptMarkup; ?>';
    var receiptPaymentId = '<?php echo $receiptPaymentId; ?>';
    var receiptBilledToEmail = '<?php echo $receiptBilledToEmail; ?>';
    var tabReturn = '<?php echo $tabReturn; ?>';

    $('#btnInvGo, #btnSaveGlParms, #btnGlGo, #btnGlTx, #btnGlcsv').button();

    $( "form[name=glParmsForm] input[type=checkbox]" ).checkboxradio({
      icon: true
    });

    $("form[name=glParmsForm] .ui-checkboxradio-icon").removeClass('ui-state-hover');

    <?php echo $report->generateReportScript(); ?>

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
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt', 550, receiptPaymentId, receiptBilledToEmail);
    }

    $('#tbl<?php echo $report->getInputSetReportName(); ?>rpt').on('click', '.invAction', function (event) {
        if ($(this).data('stat') === 'del') {
            if (!confirm('Delete Invoice #' + $(this).data('inv') + '?')) {
                return;
            }
        }
        invoiceAction($(this).data('iid'), $(this).data('stat'), event.target.id);
    });
    $('#tbl<?php echo $report->getInputSetReportName(); ?>rpt').on('click', '.invSetBill', function (event) {
        event.preventDefault();
        invSetBill($(this).data('inb'), $(this).data('name'), 'div#setBillDate', '#trBillDate' + $(this).data('inb'), $('#trBillDate' + $(this).data('inb')).text(), $('#divInvNotes' + $(this).data('inb')).text(), '#divInvNotes' + $(this).data('inb'));
    });
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
                <?php echo $report->generateFilterMarkup() . $dataTableWrapper; ?>
            </div>
            <div id="vInv" style="display:none;">
                <input type="button" id="btnInvGo" value="Refresh"/>

                  <div id="rptInvdiv" class="hhk-visitdialog"></div>

            </div>
            <div id="vGl" class="hhk-tdbox hhk-visitdialog" style="display:none; font-size:0.8em;">
                <form name="glParmsForm" method="post" action="InvoiceReport.php">
                	<?php echo $glChooser;?>
                </form>
                <form name="glReportForm" method="post" action="InvoiceReport.php">
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
