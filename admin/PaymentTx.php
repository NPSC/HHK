<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLSelector, HTMLTable};
use HHK\Tables\EditRS;
use HHK\Tables\PaymentGW\Gateway_TransactionRS;

/**
 * PaymentTx.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

/* require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'PaymentGwRS.php'); */


$wInit = new webInit();

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();


function makeParmtable($parms) {

    if (is_null($parms) === TRUE) {
        return '';
    }

    $reqTbl = new HTMLTable();

    if (is_array($parms)) {

        foreach ($parms as $key => $v) {
           if ($key == 'MerchantID' && $v != '') {
               $v = 'xxxx.' . substr($v, -4);
           }

            $reqTbl->addBodyTr(HTMLTable::makeTd($key . ':', array('class' => 'tdlabel')) . HTMLTable::makeTd($v));
        }
    } else {
        $reqTbl->addBodyTr(HTMLTable::makeTd($parms));
    }

    return $reqTbl->generateMarkup(array('style' => 'width:100%;'));
}

$txData = '';
$txSelection = '';
$resultMessage = '';
$dateSelected = '';
$nameSelected = '';

if (filter_has_var(INPUT_POST, 'btnGo')) {

    // gather input
    if (filter_has_var(INPUT_POST, 'selTx')) {
        $txSelection = filter_input(INPUT_POST, 'selTx', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    $searchDate = date('Y-m-d');
    if (filter_has_var(INPUT_POST, 'txtDate')) {
        $dateSelected = filter_input(INPUT_POST, 'txtDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($dateSelected != '') {
            $searchDate = date('Y-m-d', strtotime($dateSelected));
        }
    }

    if (filter_has_var(INPUT_POST, 'txtName')) {
        $nameSelected = filter_input(INPUT_POST, 'txtName', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }


    $stmt = $dbh->prepare("select * from gateway_transaction where DATE(Timestamp) = :sdate and GwTransCode like :txcode");
    $stmt->execute(array(':sdate'=>$searchDate, ':txcode'=>$txSelection));

    $records = $stmt->rowCount();

    $tbl = new HTMLTable();
    $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan' => '5', 'style' => 'background-color:#459E00;')));

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $txRs = new Gateway_TransactionRS();
        EditRS::loadRow($r, $txRs);

        $req = json_decode($txRs->Vendor_Request->getStoredVal(), TRUE);

        if (is_null($req)) {
            $req = $txRs->Vendor_Request->getStoredVal();
        }

        $res = json_decode($txRs->Vendor_Response->getStoredVal(), TRUE);

        // Filter on names
        if ($nameSelected != '') {
            if (is_null($req) === FALSE && isset($req['CardHolderName']) && $req['CardHolderName'] != '') {

                if (stristr($req['CardHolderName'], $nameSelected) === FALSE) {
                    continue;
                }
            }
            if (is_null($res) === FALSE && isset($res['CardHolderName']) && $res['CardHolderName'] != '') {

                if (stristr($res['CardHolderName'], $nameSelected) === FALSE) {
                    continue;
                }
            }
        }

        // Table header and top row.
        $tbl->addBodyTr(HTMLTable::makeTh('Date') . HTMLTable::makeTh('Transaction Code') . HTMLTable::makeTh('Result Code') . HTMLTable::makeTh('Amount') . HTMLTable::makeTh('Auth Code'));
        $tbl->addBodyTr(
                HTMLTable::makeTd(date('M d, Y H:i:s', strtotime($txRs->Timestamp->getStoredVal())))
                . HTMLTable::makeTd($txRs->GwTransCode->getStoredVal())
                . HTMLTable::makeTd($txRs->GwResultCode->getStoredVal())
                . HTMLTable::makeTd('')  //$txRs->Amount->getStoredVal())
                . HTMLTable::makeTd($txRs->AuthCode->getStoredVal())
        );

        // Request parameters
        $reqTbl = makeParmtable($req);
        $tbl->addBodyTr(
                HTMLTable::makeTd('Request', array('class' => 'tdlabel', 'style' => 'font-weight:bold;'))
                . HTMLTable::makeTd($reqTbl, array('colspan' => '4', 'style' => 'padding:0;'))
        );

        // Response parameters
        $resTbl = makeParmtable($res);
        $tbl->addBodyTr(
                HTMLTable::makeTd('Response', array('class' => 'tdlabel', 'style' => 'font-weight:bold;'))
                . HTMLTable::makeTd($resTbl, array('colspan' => '4', 'style' => 'padding:0;'))
        );

        $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan' => '5', 'style' => 'background-color:#459E00;')));
    }

    $txData = HTMLContainer::generateMarkup('h3', 'Found ' . $records . ' records for ' . $searchDate) . $tbl->generateMarkup(array("max-width"=>"100%"));
}


$txList = array(
    array(0=>'%', 1=>'(all)'),
    array(0=>'CardInfoInit', 1=>'Card Info Init'),
    array(0=>'CardInfoVerify', 1=>'Card Info Verify'),
    array(0=>'HostedCoInit', 1=>'Hosted CO Init'),
    array(0=>'HostedCoVerify', 1=>'Hosted CO Verify'),
    array(0=>'CreditSaleToken', 1=>'Credit Sale Token'),
    array(0=>'CreditVoidSaleToken', 1=>'Credit Void Sale Token'),
    array(0=>'CreditReturnToken', 1=>'Credit Return Token'),
    array(0=>'CreditVoidReturnToken', 1=>'Credit Void Return Token'),
    array(0=>'Webhook', 1=>'Webhook'),

);
$txSelector = HTMLSelector::generateMarkup(
        HTMLSelector::doOptionsMkup($txList, $txSelection, FALSE), array('name' => 'selTx'));

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">
            $(document).ready(function() {
                $.datepicker.setDefaults({
                    yearRange: '-02:+01',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    dateFormat: 'M d, yy'
                });
                $('.ckdate').datepicker();
            });
        </script>
    </head>
    <body <?php if ($testVersion){ echo "class='testbody'";} ?> >
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-widget-content mb-3" style="text-align:center;">
            <form method="post">
                <table class="mb-3">
                    <tr>
                        <th>Transaction Type</th>
                        <th>Date</th>
                        <th title='Leave blank to return all names'>Name Filter</th>
                    </tr>
                    <tr>
                        <td><?php echo $txSelector; ?></td>
                        <td><input type="text" class="ckdate" name='txtDate' value='<?php echo $dateSelected; ?>'/></td>
                        <td><input type="text" name='txtName' value='<?php echo $nameSelected; ?>'/></td>
                    </tr>
                </table>
                <input type='submit' value='Go' name='btnGo' class="ui-button ui-corner-all"/>
                </form>
            </div>
            <?php if($txData != ""){ ?>
            <div class="ui-widget ui-widget-content hhk-widget-content ui-corner-all mb-3" style='font-size: .8em;'>
                <?php echo $txData; ?>
            </div>
            <?php } ?>
        </div>
    </body>
</html>
