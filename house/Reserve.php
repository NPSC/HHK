<?php
/**
 * Reserve.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (CLASSES . 'Purchase/Item.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (CLASSES . 'CleanAddress.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'PaymentSvcs.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require CLASSES . 'TableLog.php';

require (CLASSES . 'Document.php');
require (CLASSES . 'Parsedown.php');

require (PMT . 'GatewayConnect.php');
require (PMT . 'PaymentGateway.php');
require (PMT . 'PaymentResponse.php');
require (PMT . 'PaymentResult.php');
require (PMT . 'Receipt.php');
require (PMT . 'Invoice.php');
require (PMT . 'InvoiceLine.php');
require (PMT . 'CheckTX.php');
require (PMT . 'CashTX.php');
require (PMT . 'Transaction.php');
require (PMT . 'CreditToken.php');

require (HOUSE . 'psg.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReserveData.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'Hospital.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'PaymentManager.php');
require (HOUSE . 'PaymentChooser.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();
creditIncludes($uS->PaymentGateway);

$menuMarkup = $wInit->generatePageMenu();

// Get labels
$labels = new Config_Lite(LABEL_FILE);
$paymentMarkup = '';
$receiptMarkup = '';
$payFailPage = $wInit->page->getFilename();
$idGuest = -1;
$idReserv = 0;
$idPsg = 0;

// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }
    }

} catch (Hk_Exception_Runtime $ex) {
    $paymentMarkup = $ex->getMessage();
}

// Send confrirm form as a word doc.
if (isset($_POST['hdnCfmRid'])) {

    require(HOUSE . 'TemplateForm.php');
    require(HOUSE . 'ConfirmationForm.php');

    $idReserv = intval(filter_var($_POST['hdnCfmRid'], FILTER_SANITIZE_NUMBER_INT), 10);
    $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

    $idGuest = $resv->getIdGuest();

    $guest = new Guest($dbh, '', $idGuest);

    $notes = '';
    if (isset($_POST['tbCfmNotes'])) {
        $notes = filter_var($_POST['tbCfmNotes'], FILTER_SANITIZE_STRING);
    }

    $txt = '';
    if (isset($_POST['hdnCfmText'])) {
        $txt = base64_decode(filter_var($_POST['hdnCfmText'], FILTER_SANITIZE_STRING));
    }

    try {

        $sty = '<style>' . file_get_contents('css/tui-editor/tui-editor-contents-min.css') . '</style>';

        $form = '<!DOCTYPE html>' . $sty . $txt . ConfirmationForm::createNotes($notes, FALSE);

        header('Content-Disposition: attachment; filename=confirm.doc');
        header("Content-Description: File Transfer");
        header('Content-Type: text/html');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        echo($form);
        exit();

    } catch (Exception $ex) {
        $paymentMarkup .= "Confirmation Form Error: " . $ex->getMessage();
    }
}

if (isset($uS->cofrid)) {
    $idReserv = $uS->cofrid;
    unset($uS->cofrid);
}


$resvObj = new ReserveData(array());


if (isset($_GET['id'])) {
    $idGuest = intval(filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['rid'])) {
    $idReserv = intval(filter_var($_GET['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['idPsg'])) {
    $idPsg = intval(filter_var($_GET['idPsg'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if ($idReserv > 0 || $idGuest >= 0) {

    $mk1 = "<h2>Loading...</h2>";
    $resvObj->setIdResv($idReserv);
    $resvObj->setId($idGuest);
    $resvObj->setIdPsg($idPsg);

} else {
    // Guest Search markup
    $gMk = Role::createSearchHeaderMkup("gst", "Guest or " . $labels->getString('MemberType', 'patient', 'Patient') . " Name Search: ");
    $mk1 = $gMk['hdr'];

}

$resvAr = $resvObj->toArray();
$resvAr['patBD'] = $resvObj->getPatBirthDateFlag();
$resvAr['patAddr'] = $uS->PatientAddr;
$resvAr['gstAddr'] = $uS->GuestAddr;
$resvAr['addrPurpose'] = $resvObj->getAddrPurpose();
$resvAr['patAsGuest'] = $resvObj->getPatAsGuestFlag();

// Page title
$title = $wInit->pageHeading;

if (isset($_GET['title'])) {
    $title = 'Check In';
    $resvAr['arrival'] = date('M j, Y');
}

$resvObjEncoded = json_encode($resvAr);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo DR_PICKER_CSS ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo MULTISELECT_CSS; ?>
        <?php echo INCIDENT_CSS; ?>
        <link rel="stylesheet" href="css/tui-editor/tui-editor-contents-min.css">

        <?php echo FAVICON; ?>
<!--        Fix the ugly checkboxes-->
        <style>
            .ui-icon-background, .ui-state-active .ui-icon-background {background-color:#fff;}
        </style>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MULTISELECT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DR_PICKER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JSIGNATURE_JS; ?>"></script>

        <script src="../js/tuiEditorSupport.js"></script>
        <script src="../js/tui-editor-Editor.min.js"></script>
<!--        <script type="text/javascript" src="<?php echo DIRRTY_JS; ?>"></script>-->

        <script type="text/javascript" src="<?php echo RESV_MANAGER_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == PaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>

    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu() ?>
        <div id="contentDiv">
            <h1><?php echo $title; ?> <span id="spnStatus" sytle="margin-left:50px; display:inline;"></span></h1>
            <div id="paymentMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
                <?php echo $paymentMarkup; ?>
            </div>
            <div id="guestSearch" style="padding-left:0;padding-top:0; margin-bottom:1.5em; clear:left; float:left;">
                <?php echo $mk1; ?>
            </div>

            <form action="Reserve.php" method="post"  id="form1">
                <div id="datesSection" style="display:none;" class="ui-widget ui-widget-header ui-state-default ui-corner-all hhk-panel hhk-row"></div>
                <div id="famSection" style="font-size: .9em; display:none; min-width: 810px;" class="ui-widget hhk-visitdialog hhk-row"></div>
                <?php if ($uS->UseIncidentReports) { ?>
	            <div id="incidentsSection" style="font-size: .9em; display: none; min-width: 810px" class="ui-widget hhk-visitdialog hhk-row">
		            <div style="padding:2px; cursor:pointer;" class="ui-widget-header ui-state-default ui-corner-top hhk-incidentHdr">
			            <div class="hhk-checkinHdr" style="display: inline-block;">Incidents<span id="incidentCounts"></span></div>
			            <ul style="list-style-type:none; float:right;margin-left:5px;padding-top:2px;" class="ui-widget"><li class="ui-widget-header ui-corner-all" title="Open - Close"><span id="f_drpDown" class="ui-icon ui-icon-circle-triangle-n"></span></li></ul>
			        </div>
	                <div id="incidentContent" style="padding: 5px;" class="ui-corner-bottom hhk-tdbox ui-widget-content"></div>
	            </div>
	            <?php } ?>
                <div id="hospitalSection" style="font-size: .9em; display:none; min-width: 810px;" class="ui-widget hhk-visitdialog hhk-row"></div>
                <div id="resvSection" style="font-size:.9em; display:none; min-width: 810px;" class="ui-widget hhk-visitdialog hhk-row"></div>
                <div style="clear:left; min-height: 70px;"></div>
                <div id="submitButtons" class="ui-corner-all" style="font-size:.9em; clear:both;">
                    <table >
                        <tr><td ><span id="pWarnings" style="display:none; font-size: 1.4em; border: 1px solid #ddce99;margin-bottom:3px; padding: 0 2px; color:red; background-color: yellow; float:right;"></span></td></tr>
                        <tr><td>
                        <input type="button" id="btnDelete" value="Delete" style="display:none;"/>
                        <input type="button" id="btnCheckinNow" value='Check-in Now' style="display:none;"/><input type="hidden" id="resvCkinNow" name="resvCkinNow" value="no" />
                        <input type="button" id="btnShowReg" value='Show Registration Form' style="display:none;"/>
                        <input type='button' id='btnDone' value='Continue' style="display:none;"/>
                            </td></tr>
                    </table>
                </div>

            </form>

			

            <div id="pmtRcpt" style="font-size: .9em; display:none;"><?php echo $receiptMarkup; ?></div>
            <div id="resDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
            <div id="psgDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;"></div>
            <div id="activityDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="keysfees" style="display:none;font-size: .85em;"></div>

        </div>
        <form name="xform" id="xform" method="post"></form>
        <div id="confirmDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;">
            <form id="frmConfirm" action="Reserve.php" method="post">
                <div id="viewerSection"></div>
                <textarea id ="tbCfmNotes" name ="tbCfmNotes" placeholder='Special Notes' rows="3" cols="80" style="font-size: 13px;"></textarea>
                <div style="padding-top:10px;" class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix">
                    <span>Email Address</span>
                    <input type="text" id="confEmail" value="" style="margin-left: .3em;"/>
                </div>
            </form>
        </div>
        <input type="hidden" value="<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>" id="fixedRate"/>
        <input type="hidden" value="<?php echo $payFailPage; ?>" id="payFailPage"/>
        <input type="hidden" value="<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>" id="dateFormat"/>
        <input type="hidden" value='<?php echo $resvObjEncoded; ?>' id="resv"/>
        <input type="hidden" value='<?php echo $paymentMarkup; ?>' id="paymentMarkup"/>

<script type="text/javascript">
var fixedRate = '<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>';
var payFailPage = '<?php echo $payFailPage; ?>';
var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
var paymentMarkup = '<?php echo $paymentMarkup; ?>';
var pageManager;

$(document).ready(function() {
    "use strict";
    var t = this;
    var $guestSearch = $('#gstSearch');
    var resv = $.parseJSON('<?php echo $resvObjEncoded; ?>');
    var pageManager = t.pageManager;

    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });


// Dialog Boxes
    $("#resDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: '95%',
        modal: true
    });

    $('#confirmDialog').dialog({
        autoOpen: false,
        resizable: true,
        width: 850,
        modal: true,
        title: 'Confirmation Form',
        close: function () {$('div#submitButtons').show(); $("#frmConfirm").children().remove();},
        buttons: {
            'Download MS Word': function () {
                var $confForm = $("form#frmConfirm");
                $confForm.append($('<input name="hdnCfmRid" type="hidden" value="' + $('#btnShowCnfrm').data('rid') + '"/>'));
                $confForm.append($('<input name="hdnCfmText" type="hidden" value="' + btoa($('#viewerSection').html()) + '"/>'));
                $confForm.submit();
            },
            'Send Email': function() {
                var parms = {
                    cmd:'confrv',
                    eml: '1',
                    eaddr: $('#confEmail').val(),
                    notes: $('#tbCfmNotes').val(),
                    rid: $('#btnShowCnfrm').data('rid'),
                    text: btoa($('#viewerSection').html())
                };
                $.post('ws_ckin.php', parms, function(data) {
                    data = $.parseJSON(data);
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.mesg, true);
                });
                $(this).dialog("close");
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#activityDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 900,
        modal: true,
        title: 'Reservation Activity Log',
        close: function () {$('div#submitButtons').show();},
        open: function () {$('div#submitButtons').hide();},
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#psgDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 500,
        modal: true,
        title: resv.patLabel + ' Chooser',
        close: function (event, ui) {$('div#submitButtons').show();},
        open: function (event, ui) {$('div#submitButtons').hide();}
    });

    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function() {$('#submitButtons').show();}
    });

    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: 530,
        modal: true,
        title: 'Payment Receipt'
    });

    if (paymentMarkup !== '') {
        $('#paymentMessage').show();
    }


    pageManager = new resvManager(resv);

    // hide the alert on mousedown
    $(document).mousedown(function (event) {

        if (isIE()) {
            var target = $(event.target[0]);

            if (target.id && target.id !== undefined && target.id !== 'divSelAddr' && target.closest('div') && target.closest('div').id !== 'divSelAddr') {
                $('#divSelAddr').remove();
            }

        } else {

            if (event.target.className === undefined || event.target.className !== 'hhk-addrPickerPanel') {
                $('#divSelAddr').remove();
            }
        }
    });

	//incident reports
	if(resv.idPsg){
		$('#incidentContent').incidentViewer({
			psgId: resv.idPsg
		});
		$('#incidentsSection').show();
	}
	//incident block
	var Ihdr = $("#incidentsSection .hhk-incidentHdr");
	var Icontent = $("#incidentsSection #incidentContent");
	Ihdr.click(function() {
	    if (Icontent.css('display') === 'none') {
	        Icontent.show('blind');
	        Ihdr.removeClass('ui-corner-all').addClass('ui-corner-top');
	    } else {
	        Icontent.hide('blind');
	        Ihdr.removeClass('ui-corner-top').addClass('ui-corner-all');
	    }
	});

// Buttons
    $('#btnDone, #btnShowReg, #btnDelete, #btnCheckinNow').button();

    $('#btnDelete').click(function () {

        if ($(this).val() === 'Deleting >>>>') {
            return;
        }

        if (confirm('Delete this ' + pageManager.resvTitle + '?')) {

            $(this).val('Deleting >>>>');

            pageManager.deleteReserve(pageManager.getIdResv(), 'form#form1', $(this));
        }
    });

    $('#btnShowReg').click(function () {
        window.open('ShowRegForm.php?rid=' + pageManager.getIdResv(), '_blank');
    });

    $('#btnCheckinNow').click(function () {
        $('#resvCkinNow').val('yes');
        $('#btnDone').click();
    });

    $('#btnDone').click(function () {

        if ($(this).val() === 'Saving >>>>') {
            return;
        }

        $('#pWarnings').hide();

        if (pageManager.verifyInput() === true) {

            $.post(
                'ws_resv.php',
                $('#form1').serialize() + '&cmd=saveResv&idPsg=' + pageManager.getIdPsg() + '&rid=' + pageManager.getIdResv() + '&' + $.param({mem: pageManager.people.list()}),
                function(data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        flagAlertMessage(err.message, 'error');
                        return;
                    }

                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }

                    if (data.error) {
                        flagAlertMessage(data.error, 'error');
                        $('#btnDone').val('Save').show();
                    }

                    pageManager.loadResv(data);

                    if (data.resv !== undefined) {
                        if (data.warning === undefined) {
                            flagAlertMessage(data.resvTitle + ' Saved.  Status: ' + data.resv.rdiv.rStatTitle, 'success');
                        }
                    } else {
                        flagAlertMessage(data.resvTitle + ' Saved.', 'success');
                    }
                }
            );

            $(this).val('Saving >>>>');
        }

    });


    function getGuest(item) {

        if (item.No_Return !== undefined && item.No_Return !== '') {
            flagAlertMessage('This person is set for No Return: ' + item.No_Return + '.', 'alert');
            return;
        }

        if (typeof item.id !== 'undefined') {
            resv.id = item.id;
        } else if (typeof item.rid !== 'undefined') {
            resv.rid = item.rid;
        } else {
            return;
        }

        resv.fullName = item.fullName;
        resv.cmd = 'getResv';

        pageManager.getReserve(resv);

    }

    if (parseInt(resv.id, 10) >= 0 || parseInt(resv.rid, 10) > 0) {

        // Avoid automatic new guest for existing reservations.
        if (parseInt(resv.id, 10) === 0 && parseInt(resv.rid, 10) > 0) {
            resv.id = -2;
        }

        resv.cmd = 'getResv';
        pageManager.getReserve(resv);

    } else {

        createAutoComplete($guestSearch, 3, {cmd: 'role', gp:'1'}, getGuest);

        // Phone number search
        createAutoComplete($('#gstphSearch'), 4, {cmd: 'role', gp:'1'}, getGuest);

        $guestSearch.keypress(function(event) {
            $(this).removeClass('ui-state-highlight');
        });

        $guestSearch.focus();
    }
});
        </script>
        <script type="text/javascript" src="js/incidentReports.js"></script>
    </body>
</html>
