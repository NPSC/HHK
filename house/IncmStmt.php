<?php

use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\SysConst\VolMemberType;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\GlStmt\GlStmt;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\Payment\PaymentSvcs;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\sec\Labels;
use HHK\SysConst\RoomRateCategories;


/**
 * IncmStmt.php
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
$uS = Session::getInstance();
// Get labels
$labels = Labels::getLabels();


function getBaMarkup(\PDO $dbh, $prefix = 'bagl') {

	$stmt = $dbh->query("SELECT n.idName, n.Name_First, n.Name_Last, n.Company, nd.Gl_Code_Debit, nd.Gl_Code_Credit " .
			" FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
			" JOIN name_demog nd on n.idName = nd.idName  ".
			" where n.Member_Status='a' and n.Record_Member = 1 order by n.Company");

	// Billing agent markup
	$glTbl = new HTMLTable();

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

		$glTbl->addBodyTr(
				HTMLTable::makeTh($entry, array('class'=>'tdlabel'))
				. HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Debit'], array('name'=>$prefix.'d_'.$r['idName'], 'size'=>'25')))
//				. HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Credit'], array('name'=>$prefix.'c_'.$r['idName'], 'size'=>'25')))
				);
	}

	$glTbl->addHeaderTr(HTMLTable::makeTh('') . HTMLTable::makeTh('GL Debit Code'));

	return $glTbl->generateMarkup();

}

function saveBa(\PDO $dbh, $post) {

	foreach ($post as $k => $v) {

		if (stristr($k, 'bagld')) {

			$parts = explode('_', $k);

			if (isset($parts[1]) && $parts[1] > 0) {

				$id = intval($parts[1]);
				$gl = filter_var($v, FILTER_SANITIZE_STRING);

				$dbh->exec("Update name_demog set Gl_Code_Debit = '$gl' where idName = $id");
			}
		}

// 		if (stristr($k, 'baglc')) {

// 			$parts = explode('_', $k);

// 			if (isset($parts[1]) && $parts[1] > 0) {

// 				$id = intval($parts[1]);
// 				$gl = filter_var($v, FILTER_SANITIZE_STRING);

// 				$dbh->exec("Update name_demog set Gl_Code_Credit = '$gl' where idName = $id");
// 			}
// 		}
	}

}



// Catch ajax from page to fill in unallocated visit ids
if (isset($_POST['cmd'])) {

	if (isset($uS->unallocVids)) {
		echo json_encode(array('vids'=>$uS->unallocVids));
	}
	exit();

}

$receiptMarkup = '';
$paymentMarkup = '';

// Hosted payment return
try {

	if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

		$receiptMarkup = $payResult->getReceiptMarkup();

		if ($payResult->getDisplayMessage() != '') {
			$paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
		}
	}

} catch (RuntimeException $ex) {
	$paymentMarkup = $ex->getMessage();
}


$monthArray = array(
		1 => array(1, 'January'),
		2 => array(2, 'February'),
		3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
		7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));


$glMonthSelr = '';
$glYearSelr = '';
$glMonth = 0;
$glyear = date('Y');
$glInvoices = '';
$dataTable = '';

$glMonth = date('m');



if (isset($_POST['btnSaveGlParms'])) {
	saveBa($dbh, $_POST);
}

// Run summary report
if (isset($_POST['btnHere'])) {

    if (isset($_POST['selGlMonth'])) {
    	$glMonth = filter_var($_POST['selGlMonth'], FILTER_SANITIZE_NUMBER_INT);
    }

    if (isset($_POST['selGlYear'])) {
    	$glyear = intval(filter_var($_POST['selGlYear'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    $glStmt = new GlStmt($dbh, $glyear, $glMonth);

    $glStmt->mapRecords($dbh);

    $dataTable = HTMLContainer::generateMarkup('h2', 'Report for the month of ' . $monthArray[$glMonth][1] . ', '. $glyear);

    $tableAttrs = array('style'=>"float:left;margin-right:1em;");

    // Scans all interval payments and items to generate an item detial list.
    $dtable = $glStmt->getGlMarkup($tableAttrs);

    /*
     *
     * Scans all visits during any one month to generate an Income Statement based upon room utilization and other determinents such as
    */
    $dtable .= $glStmt->doReport($dbh, $monthArray, $tableAttrs);

    if (count($glStmt->getErrors()) > 0) {

    	$etbl = new HTMLTable();

    	foreach ($glStmt->getErrors() as $e) {
    		$etbl->addBodyTr(HTMLTable::makeTd($e));
    	}

    	$dataTable .= $etbl->generateMarkup();
    }

    $dataTable .= $dtable;
}

// Output lines
if (isset($_POST['btnInv'])) {


	if (isset($_POST['selGlMonth'])) {
		$glMonth = filter_var($_POST['selGlMonth'], FILTER_SANITIZE_NUMBER_INT);
	}

	if (isset($_POST['selGlYear'])) {
		$glyear = intval(filter_var($_POST['selGlYear'], FILTER_SANITIZE_NUMBER_INT), 10);
	}

	$glStmt = new GlStmt($dbh, $glyear, $glMonth);

	$glStmt->mapRecords($dbh);

	$tbl = new HTMLTable();
	$tbl->addBodyTr(HTMLTable::makeTh('Gl Code') . HTMLTable::makeTh('Debit') . HTMLTable::makeTh('Credit') . HTMLTable::makeTh('Date') . HTMLTable::makeTh('Inv'));

	$credits = 0;
	$debits = 0;
	$glInvoices = '';

	foreach ($glStmt->lines as $l) {
		$tbl->addBodyTr(
				HTMLTable::makeTd($l['glcode'])
				.HTMLTable::makeTd(number_format($l['debit'], 2))
				.HTMLTable::makeTd(number_format($l['credit'], 2))
				.HTMLTable::makeTd($l['date'])
				.HTMLTable::makeTd($l['InvoiceNumber'])
				);

		$credits += $l['credit'];
		$debits += $l['debit'];

	}

	if (count($glStmt->getErrors()) > 0) {

		$etbl = new HTMLTable();

		foreach ($glStmt->getErrors() as $e) {
			$etbl->addBodyTr(HTMLTable::makeTd($e));
		}

		$glInvoices .= $etbl->generateMarkup();
	}

	$glInvoices .= "<p style='margin-top:20px;'>Total Credits = " . number_format($credits, 2) . " Total Debits = " . number_format($debits, 2) . "</p>";
	$glInvoices .= $tbl->generateMarkup();

}

// Invoice detail
if (isset($_POST['btnGlGo'])) {

	if (isset($_POST['selGlMonth'])) {
		$glMonth = filter_var($_POST['selGlMonth'], FILTER_SANITIZE_NUMBER_INT);
	}

	if (isset($_POST['selGlYear'])) {
		$glyear = intval(filter_var($_POST['selGlYear'], FILTER_SANITIZE_NUMBER_INT), 10);
	}



	$glCodes = new GlStmt($dbh, $glyear, $glMonth);

		$tbl = new HTMLTable();
		$glInvoices = '';

		$invHdr = '';
		foreach ($glCodes->invoiceHeader() as $h) {
			$invHdr .= "<th style='border-top-width: 2px;'>" . ($h == '' ? ' ' : $h) . "</th>";
		}
		$tbl->addBodyTr($invHdr);

		$pmtHdr = '';
		foreach ($glCodes->paymentHeader() as $h) {
			$pmtHdr .= "<th style='color:blue;'>" . ($h == '' ? ' ' : $h) . "</th>";
		}
		$tbl->addBodyTr($pmtHdr);

		$lineHdr = '';
		foreach ($glCodes->lineHeader() as $h) {
			$lineHdr .= "<th style='color:green;'>" . ($h == '' ? ' ' : $h) . "</th>";
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

		foreach ($glCodes->getInvoices() as $id => $r) {

			if ($recordCtr++ > 12) {
				$tbl->addBodyTr($invHdr);
				$tbl->addBodyTr($pmtHdr);
				$tbl->addBodyTr($lineHdr);
				$recordCtr = 0;
			}

			$mkupRow = '';

			foreach ($r['i'] as $k=> $col) {

				if ($k == 'iStatus' && $col == 'p') {
					$col = 'paid';
				} else if ($k == 'Rate') {
					$col = number_format($col, 2);
				} else if ($k == 'iNumber') {
					$col .= ' ('. $id .')';
				}

				if ($col == '0.00') {
					$col = '';
				}

				$mkupRow .= "<td style='border-top-width: 2px;'>" . ($col == '' ? ' ' : $col) . "</td>";
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


		$glInvoices .= '';  //"<p style='margin-top:20px;'>Total Credits = " . number_format($glCodes->getTotalCredit(), 2) . " Total Debits = " . number_format($glCodes->getTotalDebit(), 2) . "</p>";
		$glInvoices .= $tbl->generateMarkup();

		if (count($glCodes->getErrors()) > 0) {
			$etbl = new HTMLTable();
			foreach ($glCodes->getErrors() as $e) {
				$etbl->addBodyTr(HTMLTable::makeTd($e));
			}
			$glInvoices = $etbl->generateMarkup() . $glInvoices;
		}

}


// Setups for the page.
//Month and Year chooser
$glMonthSelr = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $glMonth, FALSE), array('name' => 'selGlMonth', 'size'=>12));
$glYearSelr = HTMLSelector::generateMarkup(getYearOptionsMarkup($glyear, '2018', 0, FALSE), array('name' => 'selGlYear', 'size'=>'5'));

$tbl = new HTMLTable();
$tbl->addBodyTr(
		HTMLTable::makeTd(getBaMarkup($dbh), array('style'=>'vertical-align:top;'))
		);

// Add save button
$tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Save 3rd Party Payers', array('name'=>'btnSaveGlParms', 'type'=>'submit', 'style'=>'font-size:smaller;')), array('colspan'=>'2', 'style'=>'text-align:right;')));

$glBa = $tbl->generateMarkup(array('style'=>'float:left;margin-right:1.5em;'));

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
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>

<script type="text/javascript">
function displayVids(vids) {

	var select = $("<select size='3'></select>");
    var contr = $('<div id="unVids" style="font-size:0.9em;position: absolute; z-index: 1; display: block;" />');
    contr.addClass('ui-widget ui-widget-content ui-helper-clearfix ui-corner-all');

	$.each(vids, function (ii, vv) {
		var vid = parseInt((ii / 100), 10);
		select.append('<option value=' + ii + '>' + 'Visit Id: ' + vid + ' ' + vv + '</option>');
	});

	contr.append(select);
    $('body').append(contr);

    select.change(function() {
		if (select.val() != '') {
			var vid = parseInt((select.val() / 100), 10);
			var span = select.val() % 100;
		    var buttons = {
		            "Show Statement": function() {
		                window.open('ShowStatement.php?vid=' + vid, '_blank');
		            },
		            "Show Registration Form": function() {
		                window.open('ShowRegForm.php?vid=' + vid + '&span=' + span, '_blank');
		            },
		            "Save": function() {
		                saveFees(0, vid, span, true, 'IncmStmt.php');
		            },
		            "Cancel": function() {
		                $(this).dialog("close");
		            }
		        };
		        viewVisit(0, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
		}
    });

    contr.position({
        my: 'top',
        at: 'bottom',
        of: '#unallocVisits'
    });
}

var pmtMkup,
	rctMkup,
	dateFormat,
	fixedRate;

    $(document).ready(function() {

        pmtMkup = $('#pmtMkup').val();
        rctMkup = $('#rctMkup').val();
        dateFormat = $('#dateFormat').val();
        fixedRate = $('#fixedRate').val();

        if (pmtMkup !== '') {
            $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
        }

        $('#btnHere, #btnGlGo, #btnSaveGlParms, #btnInv').button();
        $('div#printArea').css('display', 'block');
        $('#printButton').button().click(function() {
            $("div#printArea").printArea();
        });

        $('.hhk-matchlgt').hover(function() {
			$('.hhk-matchlgt').toggleClass('ui-state-highlight');
        });
        $('.hhk-matchinc').hover(function() {
			$('.hhk-matchinc').toggleClass('ui-state-highlight');
        });
        $('.hhk-itempmt').hover(function() {
			$('.hhk-itempmt').toggleClass('ui-state-highlight');
        });
        $('#unallocVisits').click(function() {

            $.post('IncmStmt.php', {cmd: 'unallocVisits'}, function(data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return false;
                }
                if (data.error) {
                    if (data.gotopage) {
                        window.location.assign(data.gotopage);
                    }
                    flagAlertMessage(data.error, 'error');
                    return false;
                }
                if (data.vids && data.vids !== '') {
                    displayVids(data.vids);
                }
            });
        });

        $('#keysfees').dialog({
            autoOpen: false,
            resizable: true,
            modal: true
        });

        $('#pmtRcpt').dialog({
            autoOpen: false,
            resizable: true,
            width: getDialogWidth(530),
            modal: true,
            title: 'Payment Receipt'
        });

        $(document).mousedown(function (event) {
            var target = $(event.target);
            if ( target[0].id !== 'unVids' && target.parents("#" + 'unVids').length === 0) {
                $('div#unVids').remove();
            }
        });

    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>

            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="min-width: 400px; padding:10px;">
            <div id="paymentMessage" style="display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2"></div>
                <form id="fcat" action="IncmStmt.php" method="post">

                	<table style="float:left;">
                	<tr><th>Month</th><th>Year</th><th>3rd Party Payers</th>
                	<tr>
                	<td><?php echo $glMonthSelr; ?></td>
                    <td style="vertical-align: top;"><?php echo $glYearSelr; ?></td>
                    <td><?php echo $glBa; ?></td>
                	</tr>
                    </table>

                    <table style="width:100%; clear:both;">
                        <tr>
                            <td style="width:70%;">
                            <input type="submit" name="btnInv" id="btnInv" value="Show Lines" /></td>
                            <td style="text-align: right;"><input type="submit" name="btnHere" id="btnHere" value="Run Report"/></td>

                        </tr>
                    </table>

                </form>
            </div>
            <div style="clear:both;"></div>
            <div><input id="printButton" value="Print" type="button" style="margin:5px;font-size: .9em;"/></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-member-detail hhk-tdbox  hhk-visitdialog" style="font-size: .8em; padding: 5px; padding-bottom:25px;">
                <?php echo $dataTable; ?>
            <div id="rptGl" class="hhk-tdbox hhk-visitdialog" style="font-size:0.9em;">
                 <?php echo $glInvoices; ?>
             </div>
            </div>
        	<div id="keysfees" style="font-size: .9em;"></div>
        </div>
        <form name="xform" id="xform" method="post"></form>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
		<input  type="hidden" id="pmtMkup" value='<?php echo $paymentMarkup; ?>' />
		<input  type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
        <input  type="hidden" id="dateFormat" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>' />
        <input  type="hidden" id="fixedRate" value='<?php echo RoomRateCategories::Fixed_Rate_Category; ?>' />

    </body>
</html>
