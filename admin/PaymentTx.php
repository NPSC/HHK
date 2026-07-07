<?php

use HHK\sec\{Session, SecurityComponent, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLSelector, HTMLTable};
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
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

$wInit = new webInit();

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();
$isDeluxe = strtolower($uS->PaymentGateway) === AbstractPaymentGateway::DELUXE;
$isTheAdmin = SecurityComponent::is_TheAdmin();
$showLog = in_array(strtolower($uS->PaymentGateway), [AbstractPaymentGateway::DELUXE, AbstractPaymentGateway::INSTAMED]);


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

            if(is_array($v)){
                $reqTbl->addBodyTr(HTMLTable::makeTd($key . ':', array('class' => 'tdlabel')) . HTMLTable::makeTd(makeParmtable($v)));
            } else {
                $reqTbl->addBodyTr(HTMLTable::makeTd($key . ':', array('class' => 'tdlabel')) . HTMLTable::makeTd($v));
            }
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
$errorCodeSelected = '';
$errorCodeText = '';
$nameSelected = '';
$selectParams = [];

if (filter_has_var(INPUT_POST, 'btnGo')) {

    $whereClause = '';

    // Date is always delivered to the where
    $searchDate = date('Y-m-d');
    if (filter_has_var(INPUT_POST, 'txtDate')) {
        $dateSelected = filter_input(INPUT_POST, 'txtDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($dateSelected != '') {
            $searchDate = date('Y-m-d', strtotime($dateSelected));
        }
        $selectParams[':sdate'] = $searchDate;
    }

    if (filter_has_var(INPUT_POST, 'selTx')) {
        $txSelection = filter_input(INPUT_POST, 'selTx', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($txSelection != '') {
            $selectParams[':txcode'] = $txSelection;
            $whereClause .= ' and `GwTransCode` = :txcode ';
        }
    }

    // Not in the where clause ??
    if (filter_has_var(INPUT_POST, 'txtName')) {
        $nameSelected = filter_input(INPUT_POST, 'txtName', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    // Add Response errorCode.  EKC,  12/6/2024
    if (filter_has_var(INPUT_POST, 'errorCode')) {
        $errorCodeSelected = filter_input(INPUT_POST, 'errorCode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($errorCodeSelected != '') {
            $selectParams[':errorCode'] = "%error_Code:_$errorCodeSelected%";
            $whereClause .= ' and `Vendor_Response` LIKE :errorCode ';
            $errorCodeText = "; Error Code = $errorCodeSelected";
        }
    }


    $stmt = $dbh->prepare("select * from `gateway_transaction` where DATE(`Timestamp`) = DATE(:sdate) $whereClause");
    $stmt->execute($selectParams);

    $records = $stmt->rowCount();

    $tbl = new HTMLTable();
    $tbl->addBodyTr(HTMLTable::makeTd('', ['colspan' => '5', 'style' => 'background-color:#459E00;']));

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
                HTMLTable::makeTd('Request', ['class' => 'tdlabel', 'style' => 'font-weight:bold;'])
                . HTMLTable::makeTd($reqTbl, ['colspan' => '4', 'style' => 'padding:0;'])
        );

        // Response parameters
        $resTbl = makeParmtable($res);
        $tbl->addBodyTr(
                HTMLTable::makeTd('Response', ['class' => 'tdlabel', 'style' => 'font-weight:bold;'])
                . HTMLTable::makeTd($resTbl, ['colspan' => '4', 'style' => 'padding:0;'])
        );

        $tbl->addBodyTr(HTMLTable::makeTd('', ['colspan' => '5', 'style' => 'background-color:#459E00;']));
    }

    $txData = HTMLContainer::generateMarkup('h3', "Found $records records for $searchDate$errorCodeText") . $tbl->generateMarkup(["max-width" => "100%"]);
}


$txList = [
    [0 => '', 1 => '(all)'],
    [0 => 'CardInfoInit', 1 => 'Card Info Init'],
    [0 => 'CardInfoVerify', 1 => 'Card Info Verify'],
    [0 => 'HostedCoInit', 1 => 'Hosted CO Init'],
    [0 => 'HostedCoVerify', 1 => 'Hosted CO Verify'],
    [0 => 'CreditSaleToken', 1 => 'Credit Sale Token'],
    [0 => 'CreditVoidSaleToken', 1 => 'Credit Void Sale Token'],
    [0 => 'CreditReturnToken', 1 => 'Credit Return Token'],
    [0 => 'CreditVoidReturnToken', 1 => 'Credit Void Return Token'],
    [0 => 'Webhook', 1 => 'Webhook'],

];
$txSelector = HTMLSelector::generateMarkup(
        HTMLSelector::doOptionsMkup($txList, $txSelection, FALSE),
    ['name' => 'selTx']);

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
        <?php echo JQ_DT_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS; ?>"></script>

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
                        <th>Error Code</th>
                        <th>Transaction Type</th>
                        <th>Date</th>
                        <th title='Leave blank to return all names'>Name Filter</th>
                    </tr>
                    <tr>
                        <td><input type='text' name='errorCode' value='<?php echo $errorCodeSelected; ?>'/></td>
                        <td><?php echo $txSelector; ?></td>
                        <td><input type="text" class="ckdate" name='txtDate' value='<?php echo $dateSelected; ?>'/></td>
                        <td><input type="text" name='txtName' value='<?php echo $nameSelected; ?>'/></td>
                    </tr>
                </table>
                <input type='submit' value='Go' name='btnGo' class="ui-button ui-corner-all"/>
                <?php if ($showLog) { ?>
                <button type="button" id="btnShowLog" class="ui-button ui-corner-all ml-2">Show Detailed Log</button>
                <?php if ($isTheAdmin && $isDeluxe){ ?><button type="button" id="btnSearchPayment" class="ui-button ui-corner-all ml-2">Search Payment</button><?php } ?>
                <?php } ?>
                </form>
            </div>
            <?php if($txData != ""){ ?>
            <div class="ui-widget ui-widget-content hhk-widget-content ui-corner-all mb-3" style='font-size: .8em;'>
                <?php echo $txData; ?>
            </div>
            <?php } ?>
        </div>
        <?php if ($showLog) { ?>
        <div id="logDialog" class="hhk-tdbox hhk-visitdialog" style="font-size: .85em; display:none;"><table id="deluxeLog"></table></div>
        <?php if ($isTheAdmin) { ?>
        <div id="searchPaymentDialog" style="display:none; font-size: .9em;">
            <div class="mb-3">
                <label for="txtPaymentId"><strong>Payment ID:</strong></label>
                <input type="text" id="txtPaymentId" class="ml-2" style="width:320px;" placeholder="Enter Deluxe Payment ID" />
                <button type="button" id="btnSearchPaymentGo" class="ui-button ui-corner-all ml-2">Search</button>
            </div>
            <div id="searchPaymentResults" style="display:none;">
                <div id="searchPaymentDetails" class="ui-widget ui-widget-content ui-corner-all p-3 mb-3"></div>
                <div id="searchPaymentVoidDiv" style="display:none;">
                    <button type="button" id="btnVoidPayment" class="ui-button ui-corner-all">Void This Payment</button>
                </div>
                <div id="searchPaymentVoidResult" class="mt-2"></div>
            </div>
        </div>
        <?php } ?>
        <script type="text/javascript">
            $(document).ready(function () {

                var dtLogCols = [
                    {
                        targets: [0],
                        className: 'dt-control',
                        orderable: false,
                        data: null,
                        defaultContent: ''
                    },
                    {
                        "targets": [1],
                        "title": "Method",
                        "searchable": false,
                        "sortable": true,
                        "data": "requestMethod",
                    },
                    {
                        "targets": [2],
                        "title": "Type",
                        "searchable": false,
                        "sortable": true,
                        "data": "Type",
                    },
                    {
                        "targets": [3],
                        "title": "Request Endpoint",
                        "searchable": false,
                        "sortable": false,
                        "data": "endpoint",
                    },
                    {
                        "targets": [4],
                        "title": "Response Code",
                        "searchable": false,
                        "sortable": true,
                        "data": "responseCode",
                    },
                    {
                        "targets": [5],
                        "title": "Request",
                        "searchable": true,
                        "sortable": true,
                        "data": "request",
                        "visible": false,
                    },
                    {
                        "targets": [6],
                        "title": "Response",
                        "searchable": true,
                        "sortable": true,
                        "data": "response",
                        "visible": false,
                    },
                    {
                        "targets": [7],
                        "title": "User",
                        "searchable": true,
                        "sortable": true,
                        "data": "username",
                    },
                    {
                        "targets": [8],
                        "title": "Timestamp",
                        'data': 'Timestamp',
                        render: function (data, type) {
                            return dateRender(data, type, 'MMM D YYYY h:mm:ss a');
                        }
                    }
                ];

                var logTable = $('#deluxeLog').dataTable({
                    "columnDefs": dtLogCols,
                    "serverSide": true,
                    "processing": true,
                    "language": { "sSearch": "Search Log:" },
                    "sorting": [[8, 'desc']],
                    "displayLength": 25,
                    "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                    'dom': '<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">',
                    'autoWidth': false,
                    layout: {
                        topStart: 'info',
                        bottom: 'paging',
                        bottomStart: null,
                        bottomEnd: null
                    },
                    ajax: {
                        url: 'ws_gen.php',
                        data: function (d) {
                            d.cmd = 'viewLog';
                            d.service = '<?php echo $uS->PaymentGateway; ?>';
                        }
                    },
                    createdRow: function (row, data, dataIndex) {
                        if (data.responseCode >= 400) {
                            $(row).addClass('ui-state-error');
                        }
                    }
                });

                logTable.on('click', 'td.dt-control', function (e) {
                    var tr = e.target.closest('tr');
                    var row = logTable.DataTable().row(tr);

                    if (row.child.isShown()) {
                        row.child.hide();
                    } else {
                        row.child(formatAPIDetails(row.data())).show();
                    }
                });

                function formatAPIDetails(row) {
                    return '<div class="d-flex">' +
                        (row.request != '' ? '<div class="mx-3 p-2 ui-widget ui-widget-content ui-corner-all">' +
                            '<strong>Request</strong>' +
                            (row.requestHeaders != '' ? '<details><summary>Headers</summary><pre style="white-space: pre-wrap;">' + row.requestHeaders + '</pre></details>' : '') +
                            '<details open><summary>Body</summary><pre style="white-space: pre-wrap;">' + row.request + '</pre></details>' +
                        '</div>' : '') +
                        '<div class="mx-3 p-2 ui-widget ui-widget-content ui-corner-all">' +
                            '<strong>Response</strong>' +
                            '<pre style="white-space: pre-wrap;">' + row.response + '</pre>' +
                        '</div>' +
                    '</div>';
                }

                var $logDialog = $("#logDialog").dialog({
                    autoOpen: false,
                    modal: true,
                    minWidth: getDialogWidth(1500),
                    maxHeight: $(window).height() - 100,
                    title: 'Payment Log',
                    buttons: {
                        "Close": function () {
                            $logDialog.dialog('close');
                        }
                    },
                    open: function () {
                        $('#deluxeLog').DataTable().ajax.reload();
                    }
                });

                $('#btnShowLog').click(function () {
                    $logDialog.dialog('open');
                });

                <?php if ($isTheAdmin) { ?>
                var $searchPaymentDialog = $("#searchPaymentDialog").dialog({
                    autoOpen: false,
                    modal: true,
                    width: getDialogWidth(700),
                    title: 'Search Deluxe Payment',
                    close: function () {
                        $('#txtPaymentId').val('');
                        $('#searchPaymentResults').hide();
                        $('#searchPaymentDetails').html('');
                        $('#searchPaymentVoidDiv').hide();
                        $('#searchPaymentVoidResult').html('');
                    }
                });

                $('#btnSearchPayment').click(function () {
                    $searchPaymentDialog.dialog('open');
                });

                $('#btnSearchPaymentGo').click(function () {
                    var paymentId = $('#txtPaymentId').val().trim();
                    if (!paymentId) {
                        alert('Please enter a Payment ID.');
                        return;
                    }
                    if (!/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/.test(paymentId)) {
                        alert('Payment ID must be a valid UUID (e.g. 630bd170-4705-4532-a1cc-974ae30f19e8).');
                        return;
                    }

                    $('#searchPaymentResults').hide();
                    $('#searchPaymentDetails').html('').addClass('hhk-loading');
                    $('#searchPaymentResults').show();
                    $('#searchPaymentVoidDiv').hide();
                    $('#searchPaymentVoidResult').html('');

                    $.post('ws_gen.php', { cmd: 'searchDeluxePayment', paymentId: paymentId }, function (data) {
                        $('#searchPaymentDetails').removeClass('hhk-loading');
                        if (data.error) {
                            $('#searchPaymentDetails').html('<span class="ui-state-error" style="padding:5px;">' + data.error + '</span>');
                            return;
                        }

                        var resp = (data.payment && data.payment.data) ? data.payment.data : {};

                        if (!resp.payments || resp.payments.length === 0) {
                            var errMsg = (resp.errorMessages && resp.errorMessages.length > 0) ? resp.errorMessages.join(', ') : 'Payment not found.';
                            $('#searchPaymentDetails').html('<span class="ui-state-error" style="padding:5px;">' + $('<span>').text(errMsg).html() + '</span>');
                            $('#searchPaymentVoidDiv').hide();
                            return;
                        }

                        var p = resp.payments[0];
                        var pmt = p.payment || {};
                        var card = p.card || {};
                        var billing = p.billingAddress || {};

                        function row(label, val) {
                            if (val === null || val === undefined || val === '') return '';
                            return '<tr><th style="text-align:left;padding:3px 10px;white-space:nowrap;font-weight:normal;">' +
                                $('<span>').text(label).html() + ':</th>' +
                                '<td style="padding:3px 10px;">' + $('<span>').text(String(val)).html() + '</td></tr>';
                        }

                        function section(title) {
                            return '<tr><td colspan="2" style="padding:6px 10px 2px;font-weight:bold;border-top:1px solid #ccc;">' + title + '</td></tr>';
                        }

                        var html = '<table style="width:100%;border-collapse:collapse;">';
                        html += section('Payment');
                        html += row('Payment ID', p.paymentId);
                        html += row('Type', pmt.paymentType);
                        html += row('Amount', pmt.amount !== undefined ? '$' + parseFloat(pmt.amount).toFixed(2) : '');
                        html += row('Date/Time', pmt.paymentDateTime);
                        html += row('Auth Response', pmt.authResponse);
                        html += row('Response Code', pmt.responseCode);
                        html += row('Order ID', pmt.orderId);
                        html += row('Batch Number', pmt.batchNumber);
                        html += row('Settled', pmt.settled !== undefined ? (pmt.settled ? 'Yes' : 'No') : '');
                        html += row('Settled Date', pmt.settledDate);
                        html += row('Successful', pmt.isSuccessful !== undefined ? (pmt.isSuccessful ? 'Yes' : 'No') : '');

                        html += section('Card');
                        html += row('Cardholder Name', card.cardholderName);
                        html += row('Card Type', card.cardType);
                        html += row('Last 4', card.card);
                        html += row('Expiry', card.expiry);

                        if (billing.paymentOrigin || billing.email || billing.phone) {
                            html += section('Billing');
                            html += row('Payment Origin', billing.paymentOrigin);
                            html += row('Email', billing.email);
                            html += row('Phone', billing.phone);
                        }

                        html += '</table>';
                        $('#searchPaymentDetails').html(html);

                        var canVoid = pmt.isSuccessful === true && pmt.settled === false &&
                            pmt.paymentType !== 'VOID' && pmt.paymentType !== 'REFUND';
                        if (canVoid) {
                            $('#searchPaymentVoidDiv').show();
                        } else {
                            $('#searchPaymentVoidDiv').hide();
                        }
                    }, 'json').fail(function () {
                        $('#searchPaymentDetails').removeClass('hhk-loading').html('<span class="ui-state-error" style="padding:5px;">An unexpected error occurred.</span>');
                    });
                });

                $('#txtPaymentId').on('keydown', function (e) {
                    if (e.key === 'Enter') {
                        $('#btnSearchPaymentGo').trigger('click');
                    }
                });

                $('#btnVoidPayment').click(function () {
                    var paymentId = $('#txtPaymentId').val().trim();
                    if (!paymentId || !/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/.test(paymentId)) return;

                    if (!confirm('Are you sure you want to void payment ' + paymentId + '? This action cannot be undone.')) {
                        return;
                    }

                    $('#btnVoidPayment').prop('disabled', true).text('Voiding...');
                    $('#searchPaymentVoidResult').html('');

                    $.post('ws_gen.php', { cmd: 'voidDeluxePayment', paymentId: paymentId }, function (data) {
                        $('#btnVoidPayment').prop('disabled', false).text('Void This Payment');

                        if (data.error) {
                            $('#searchPaymentVoidResult').html('<span class="ui-state-error" style="padding:5px;">' + data.error + '</span>');
                            return;
                        }

                        $('#searchPaymentVoidDiv').hide();
                        $('#searchPaymentVoidResult').html('<span class="ui-state-highlight" style="padding:5px; display:inline-block;">' +
                            'Payment voided successfully. Response code: ' + (data.responseCode || '') +
                            (data.message ? ' — ' + $('<span>').text(data.message).html() : '') +
                            '</span>');
                    }, 'json').fail(function () {
                        $('#btnVoidPayment').prop('disabled', false).text('Void This Payment');
                        $('#searchPaymentVoidResult').html('<span class="ui-state-error" style="padding:5px;">An unexpected error occurred.</span>');
                    });
                });
                <?php } ?>
            });
        </script>
        <?php } ?>
    </body>
</html>
