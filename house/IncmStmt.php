<?php

use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\SysConst\VolMemberType;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\GlStmt;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\House\GLCodes\GLCodes;
use HHK\House\GLCodes\GLParameters;
use HHK\House\GLCodes\GLTemplateRecord;

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

if (isset($_POST['btnHere'])) {
   
    if (isset($_POST['selGlMonth'])) {
    	$glMonth = filter_var($_POST['selGlMonth'], FILTER_SANITIZE_NUMBER_INT);
    }
    
    if (isset($_POST['selGlYear'])) {
    	$glyear = intval(filter_var($_POST['selGlYear'], FILTER_SANITIZE_NUMBER_INT), 10);
    }
    
    $glCodes = new GlStmt($dbh, $glMonth, $glyear);
    
    $glCodes->mapRecords();
    
    $dataTable = HTMLContainer::generateMarkup('h2', 'Report for the month of ' . $monthArray[$glMonth][1] . ', '. $glyear);

    if (count($glCodes->getErrors()) > 0) {
    	
    	$etbl = new HTMLTable();
    	
    	foreach ($glCodes->getErrors() as $e) {
    		$etbl->addBodyTr(HTMLTable::makeTd($e));
    	}
    	
    	$dataTable .= $etbl->generateMarkup();
    }
    
    $tableAttrs = array('style'=>"float:left;margin-right:1em;");
    
    $dataTable .= $glCodes->getGlMarkup($tableAttrs) . $glCodes->doReport($dbh, $monthArray, $tableAttrs);

}

// Output report
if (isset($_POST['btnGlGo'])) {
	
	
	if (isset($_POST['selGlMonth'])) {
		$glMonth = filter_var($_POST['selGlMonth'], FILTER_SANITIZE_NUMBER_INT);
	}
	
	if (isset($_POST['selGlYear'])) {
		$glyear = intval(filter_var($_POST['selGlYear'], FILTER_SANITIZE_NUMBER_INT), 10);
	}
	
	$glParm = new GLParameters($dbh, 'Gl_Code');
	$glParm->setStartDay(1);

	
	$glCodes = new GLCodes($dbh, $glMonth, $glyear, $glParm, new GLTemplateRecord());
	
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
				
				if ($col == 0) {
					$col = '';
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
		
				
		$glInvoices = "<p style='margin-top:20px;'>Total Credits = " . number_format($glCodes->getTotalCredit(), 2) . " Total Debits = " . number_format($glCodes->getTotalDebit(), 2) . "</p>" .$tbl->generateMarkup();
		
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
$glYearSelr = HTMLSelector::generateMarkup(getYearOptionsMarkup($glyear, '2019', 0, FALSE), array('name' => 'selGlYear', 'size'=>'5'));

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
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>

<script type="text/javascript">
    $(document).ready(function() {

        $('#btnHere, #btnGlGo, #btnSaveGlParms').button();
        $('div#printArea').css('display', 'block');
        $('#printButton').button().click(function() {
            $("div#printArea").printArea();
        });

    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>

            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
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
                            <td style="width:50%;"><input type="submit" name="btnGlGo" id="btnGlGo" value="Show Details" /></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            
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
        </div>
    </body>
</html>
