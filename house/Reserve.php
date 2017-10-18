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
require (DB_TABLES . 'MercuryRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (CLASSES . 'CleanAddress.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'MercPay/Gateway.php');
require (CLASSES . 'MercPay/MercuryHCClient.php');
require (PMT . 'Payments.php');
require (PMT . 'HostedPayments.php');
require (PMT . 'CreditToken.php');
require (CLASSES . 'PaymentSvcs.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

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


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

// Get labels
$labels = new Config_Lite(LABEL_FILE);
$paymentMarkup = '';
$receiptMarkup = '';

$idGuest = 0;
$idReserv = 0;
$idPsg = 0;

// Hosted payment return
if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_POST)) === FALSE) {

    $receiptMarkup = $payResult->getReceiptMarkup();
    $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
}

if (isset($_POST['hdnCfmRid'])) {

    $idReserv = intval(filter_var($_POST['hdnCfmRid'], FILTER_SANITIZE_NUMBER_INT), 10);
    $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

    $idGuest = $resv->getIdGuest();

    $guest = new Guest($dbh, '', $idGuest);

    $notes = '';
    if (isset($_POST['tbCfmNotes'])) {
        $notes = filter_var($_POST['tbCfmNotes'], FILTER_SANITIZE_STRING);
    }

    require(HOUSE . 'ConfirmationForm.php');

    $confirmForm = new ConfirmationForm($uS->ConfirmFile);

    $formNotes = $confirmForm->createNotes($notes, FALSE);
    $form = '<!DOCTYPE html>' . $confirmForm->createForm($dbh, $resv, $guest, 0, $formNotes);

    header('Content-Disposition: attachment; filename=confirm.doc');
    header("Content-Description: File Transfer");
    header('Content-Type: text/html');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    echo($form);
    exit();
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


if ($idReserv > 0 || $idGuest > 0) {

    $mk1 = "<h2>Loading...</h2>";
    $resvObj->setIdResv($idReserv);
    $resvObj->setId($idGuest);
    $resvObj->setIdPsg($idPsg);

} else {
    // Guest Search markup
    $gMk = Role::createSearchHeaderMkup("gst", "Guest or " . $labels->getString('MemberType', 'patient', 'Patient') . " Name Search: ");
    $mk1 = $gMk['hdr'];

}


// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("uh-oh");

$resultMessage = $alertMsg->createMarkup();

$resvAr = $resvObj->toArray();
$resvAr['patBD'] = $resvObj->getPatBirthDateFlag();
$resvAr['patAddr'] = $uS->PatientAddr;
$resvAr['gstAddr'] = $uS->GuestAddr;
$resvAr['addrPurpose'] = $resvObj->getAddrPurpose();
$resvAr['patAsGuest'] = $resvObj->getPatAsGuestFlag();


$resvObjEncoded = json_encode($resvAr);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <link rel="stylesheet" href="css/daterangepicker.min.css">

        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
<!--        Fix the ugly checkboxes-->
        <style>.ui-icon-background, .ui-state-active .ui-icon-background {background-color:#fff;}</style>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DR_PICKER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VERIFY_ADDRS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>

    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?> <span id="spnStatus" sytle="margin-left:50px; display:inline;"></span></h1>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="paymentMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
                <?php echo $paymentMarkup; ?>
            </div>

            <div id="guestSearch" style="padding-left:0;padding-top:0; margin-bottom:1.5em; clear:left; float:left;">
                <?php echo $mk1; ?>
            </div>

            <form action="Reserve.php" method="post"  id="form1">
                <div id="datesSection" style="clear:left; float:left; display:none;" class="ui-widget ui-widget-header ui-state-default ui-corner-all hhk-panel"></div>
                <div id="famSection" style="clear:left; float:left; font-size: .9em; display:none; min-width: 810px; margin-bottom:.5em;" class="ui-widget hhk-visitdialog"></div>

                <div id="hospitalSection" style="font-size: .9em; margin-bottom:.5em; clear:left; float:left; display:none; min-width: 810px;"  class="ui-widget hhk-visitdialog"></div>
                <div id="resvSection" style="clear:left; float:left; font-size:.9em; display:none; margin-bottom:.5em; min-width: 810px;" class="ui-widget hhk-visitdialog"></div>

                <div id="submitButtons" class="ui-corner-all" style="font-size:.9em; clear:both;">
                    <input type="button" id="btnDelete" value="Delete" style="display:none;"/>
                    <input type="button" id="btnShowReg" value='Show Registration Form' style="display:none;"/>
                    <input id='btnDone' type='button' value='Continue' style="display:none;"/>
                </div>

            </form>
            <div id="pmtRcpt" style="font-size: .9em; display:none;"><?php echo $receiptMarkup; ?></div>
            <div id="resDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="psgDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;"></div>
            <div id="activityDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>

        </div>
        <form name="xform" id="xform" method="post"><input type="hidden" name="CardID" id="CardID" value=""/></form>
        <div id="confirmDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;">
            <form id="frmConfirm" action="Reserve.php" method="post"></form>
        </div>

<script type="text/javascript">
function PageManager(initData) {
    var t = this;

    var patLabel = initData.patLabel;
    var resvTitle = initData.resvTitle;
    var patBirthDate = initData.patBD;
    var patAddrRequired = initData.patAddr;
    var gstAddrRequired = initData.gstAddr;
    var patAsGuest = initData.patAsGuest;
    var addrPurpose = initData.addrPurpose;
    var idPsg = initData.idPsg;
    var idResv = initData.rid;
    var idName = initData.id;

    var people = new Items();
    var addrs = new Items();
    var familySection = new FamilySection($('#famSection'));
    var resvSection = new ResvSection($('#resvSection'));
    var hospSection = new HospitalSection($('#hospitalSection'));
    var expDatesSection = new ExpDatesSection($('#datesSection'));

    // Exports
    t.getReserve = getReserve;
    t.verifyInput = verifyInput;
    t.loadResv = loadResv;
    t.people = people;
    t.addrs = addrs;
    t.idPsg = idPsg;
    t.idResv = idResv;
    t.idName = idName;





    function FamilySection($wrapper) {
        var t = this;
        var divFamDetailId = 'divfamDetail';
        var cpyAddr = [];

        // Exports
        t.findStaysChecked = findStaysChecked;
        t.setupComplete = false;
        t.setUp = setUp;
        t.newGuestMarkup = newGuestMarkup;
        t.verify = verify;
        t.divFamDetailId = divFamDetailId;


        function findStaysChecked() {
            var numGuests = 0;
            var singlePrefix = '';

            // Get Primary guest setting from form.
            var pgPrefix = $( "input[type=radio][name=rbPriGuest]:checked" ).val();

            // Each available stay control
            $('.hhk-cbStay').each(function () {

                var prefix = $(this).data('prefix');

                if ($(this).prop('checked')) {

                    people.list()[prefix].stay = '1';
                    numGuests++;
                    $('#' + prefix + 'rbPri').prop('disabled', false);

                    if (pgPrefix && pgPrefix !== '' && pgPrefix == prefix) {
                        people.list()[prefix].pri = '1';
                    } else {
                         people.list()[prefix].pri = '0';
                    }

                    singlePrefix = prefix;

                } else {

                    people.list()[prefix].stay = '0';
                    people.list()[prefix].pri = '0';

                    $('#' + prefix + 'rbPri').prop('checked', false).prop('disabled', true);
                }
            });

            // Only one guest staying, set as primary guest
            if (numGuests === 1 && singlePrefix !== '') {
                people.list()[singlePrefix].pri = '1';
                $('#' + singlePrefix + 'rbPri').prop('checked', true);
            }

            return numGuests;
        }

        function openSection(torf) {

            var $fDiv = $('#divfamDetail');

            if (torf === true) {
                $fDiv.show('blind');
                $fDiv.prev('div').removeClass('ui-corner-all').addClass('ui-corner-top');
            } else {
                $fDiv.hide('blind');
                $fDiv.prev('div').addClass('ui-corner-all').removeClass('ui-corner-top');
            }
        }

        function addGuest(item, data) {

            hideAlertMessage();

            // Check for guest already added.
            //

            if (item.No_Return !== undefined && item.No_Return !== '') {
                flagAlertMessage('This person is set for No Return: ' + item.No_Return + '.', true);
                return;
            }

            if (typeof item.id === 'undefined') {
                return;
            }

            if (item.id > 0 && people.findItem('id', item.id) !== null) {
                flagAlertMessage('This person is already listed here. ', true);
                return;
            }

            var resv = {
                id: item.id,
                rid: data.rid,
                idPsg: data.idPsg,
                cmd: 'addThinGuest'
            };

            getReserve(resv);

        }

        function verifyAddress(prefix) {

            var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
            var msg = false;

            // Incomplete checked?
            if ($('#' + prefix + 'incomplete').length > 0 && $('#' + prefix + 'incomplete').prop('checked') === false) {

                // Look at each entry
                $('.' + prefix + 'hhk-addr-val').each(function() {

                    if ($(this).val() === '' && !$(this).hasClass('bfh-states')) {

                        // Missing
                        $(this).addClass('ui-state-error');
                        msg = true;

                    } else {
                        $(this).removeClass('ui-state-error');
                    }
                });

                // Did we catch any?
                if (msg) {
                    // Yes,open address row.
                    if ($('#' + prefix + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                        $('#' + prefix + 'toggleAddr').click();
                    }

                    return 'Some or all of the indicated addresses are missing.  ';

                }
            }

            // Validate Phone Number
            $('.hhk-phoneInput[id^="' +prefix + 'txtPhone"]').each(function (){

                if ($.trim($(this).val()) !== '' && testreg.test($(this).val()) === false) {

                    // error
                    $(this).addClass('ui-state-error');

                    //Open address row
                    if ($('#' + prefix + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                        $('#' + prefix + 'toggleAddr').click();
                    }

                    // open phone tab
                    $('#' + prefix + 'phEmlTabs').tabs("option", "active", 1);

                    msg = true;

                } else {
                    $(this).removeClass('ui-state-error');
                }
            });

            if (msg) {
                return 'Indicated phone numbers are invalid.  ';
            }

            return '';

        }

        function copyAddress(prefix) {

            //var adrs = addrs.list();

            for (var p in addrs.list()) {
                // Use this one already?
                if (p === cpyAddr[prefix]) {
                    cpyAddr[prefix] = 0;
                    continue;
                }

                if (addrs.list()[p].Address_1 !== '') {

                    cpyAddr[prefix] = p;

                    $('#' + prefix + 'adraddress1' + addrPurpose).val(addrs.list()[p].Address_1);
                    $('#' + prefix + 'adraddress2' + addrPurpose).val(addrs.list()[p].Address_2);
                    $('#' + prefix + 'adrcity' + addrPurpose).val(addrs.list()[p].City);
                    $('#' + prefix + 'adrcounty' + addrPurpose).val(addrs.list()[p].County);

                    $('#' + prefix + 'adrcountry' + addrPurpose).val(addrs.list()[p].Country_Code);
                    $('#' + prefix + 'adrcountry' + addrPurpose).change();

                    $('#' + prefix + 'adrstate' + addrPurpose).val(addrs.list()[p].State_Province);
                    $('#' + prefix + 'adrzip' + addrPurpose).val(addrs.list()[p].Postal_Code);

                    // Clear the incomplete address checkbox if the address is valid.
                    if ($('#' + prefix + 'adraddress1' + addrPurpose).val() !== '' && $('#' + prefix + 'adrcity' + addrPurpose).val() !== '' && $('#' + prefix + 'incomplete').prop('checked') === true) {
                        $('#' + prefix + 'incomplete').prop('checked', false);
                    }

                    //loadAddress(prefix);

                    // Update the address flag
                    setAddrFlag($('#' + prefix + 'liaddrflag'));

                    break;
                }
            }
        }

        function eraseAddress(prefix) {

            $('#' + prefix + 'adraddress1' + addrPurpose).val('');
            $('#' + prefix + 'adraddress2' + addrPurpose).val('');
            $('#' + prefix + 'adrcity' + addrPurpose).val('');
            $('#' + prefix + 'adrcounty' + addrPurpose).val('');
            $('#' + prefix + 'adrstate' + addrPurpose).val('');
            $('#' + prefix + 'adrcountry' + addrPurpose).val('');
            $('#' + prefix + 'adrzip' + addrPurpose).val('');

            setAddrFlag($('#' + prefix + 'liaddrflag'));

        }

        function loadAddress(prefix) {

            if (prefix === undefined) {
                return;
            }

            addrs.list()[prefix].Address_1 = $('#' + prefix + 'adraddress1' + addrPurpose).val();
            addrs.list()[prefix].Address_2 = $('#' + prefix + 'adraddress2' + addrPurpose).val();
            addrs.list()[prefix].City = $('#' + prefix + 'adrcity' + addrPurpose).val();
            addrs.list()[prefix].County = $('#' + prefix + 'adrcounty' + addrPurpose).val();
            addrs.list()[prefix].State_Province = $('#' + prefix + 'adrstate' + addrPurpose).val();
            addrs.list()[prefix].Country_Code = $('#' + prefix + 'adrcountry' + addrPurpose).val();
            addrs.list()[prefix].Postal_Code = $('#' + prefix + 'adrzip' + addrPurpose).val();

            setAddrFlag($('#' + prefix + 'liaddrflag'));

        }

        function setAddrFlag($addrFlag) {

            var prefix = $addrFlag.parents('ul').data('pref');

            // Address status icon
            if ($('#' + prefix + 'incomplete').prop('checked') === true) {

                $addrFlag.show().find('span').removeClass('ui-icon-alert').addClass('ui-icon-check').attr('title', 'Incomplete Address is checked');
                $addrFlag.removeClass('ui-state-error').addClass('ui-state-highlight');

            } else {

                if ($('#' + prefix + 'adraddress1' + addrPurpose).val() === '' || $('#' + prefix + 'adrcity' + addrPurpose).val() === '') {
                    $addrFlag.show().find('span').removeClass('ui-icon-check').addClass('ui-icon-alert').attr('title', 'Address is Incomplete');
                    $addrFlag.removeClass('ui-state-highlightui-state-error').addClass('ui-state-error');
                } else {
                    $addrFlag.hide();
                }
            }
        }

        function initFamilyTable(data) {

            var fDiv, fHdr, expanderButton;

            fDiv = $('<div/>').addClass('ui-widget-content ui-corner-bottom hhk-tdbox').prop('id', divFamDetailId).css('padding', '5px');

            fDiv.append(
                    $('<table/>')
                        .prop('id', data.famSection.tblId)
                        .addClass('hhk-table')
                        .append($('<thead/>').append($(data.famSection.tblHead)))
                        .append($('<tbody/>')))
                    .append($(data.famSection.adtnl));

            expanderButton = $("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>")
                .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
                .append($("<span id='f_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));

            fHdr = $('<div id="divfamHdr" style="padding:2px; cursor:pointer;"/>')
                    .append($(data.famSection.hdr))
                    .append(expanderButton).append('<div style="clear:both;"/>');

            fHdr.addClass('ui-widget-header ui-state-default ui-corner-top');
            fHdr.click(function() {
                if (fDiv.css('display') === 'none') {
                    fDiv.show('blind');
                    fHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                } else {
                    fDiv.hide('blind');
                    fHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                }
            });

            $wrapper
                    .empty()
                    .append(fHdr)
                    .append(fDiv)
                    .show();

        }

        function setUp(data) {

            var $addrTog, $addrFlag, $famTbl;

            if (data.famSection === undefined || data.famSection.tblId === undefined || data.famSection.tblId == '') {
                return;
            }

            initFamilyTable(data);
            $famTbl = $wrapper.find('#' + data.famSection.tblId);


            for (var t=0; t < data.famSection.tblBody.length; t++) {
                $famTbl.find('tbody:first').append($(data.famSection.tblBody[t]));
            }

            $('.hhk-cbStay').checkboxradio({
                classes: {"ui-checkboxradio-label": "hhk-unselected-text" }
            });

            $('.hhk-lblStay').each(function () {
                if ($(this).data('stay') == '1') {
                    $(this).click();
                }
            });

            $('.ckbdate').datepicker({
                yearRange: '-99:+00',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                maxDate: 0,
                dateFormat: 'M d, yy'
            });

            // toggle address row
            $('#' + divFamDetailId).on('click', '.hhk-togAddr, .hhk-AddrFlag', function () {

                if ($(this).hasClass('hhk-togAddr')) {
                    $addrTog = $(this);
                    $addrFlag = $(this).siblings();
                } else {
                    $addrFlag = $(this);
                    $addrTog = $(this).siblings();
                }

                if ($(this).parents('tr').next('tr').css('display') === 'none') {
                    $(this).parents('tr').next('tr').show();
                    $addrTog.find('span').removeClass('ui-icon-circle-triangle-s').addClass('ui-icon-circle-triangle-n');
                    $addrTog.attr('title', 'Hide Address Section');
                } else {
                    $(this).parents('tr').next('tr').hide();
                    $addrTog.find('span').removeClass('ui-icon-circle-triangle-n').addClass('ui-icon-circle-triangle-s');
                    $addrTog.attr('title', 'Show Address Section');
                }

                setAddrFlag($addrFlag);
            });

            $('.hhk-togAddr').click();

            // set country and state selectors
            $('.hhk-addrPanel').find('select.bfh-countries').each(function() {
                var $countries = $(this);
                $countries.bfhcountries($countries.data());
            });

            $('.hhk-addrPanel').find('select.bfh-states').each(function() {
                var $states = $(this);
                $states.bfhstates($states.data());
            });

            // Load the addresses into the addrs object if changed.
            $('.hhk-addrPanel').on('click', 'input, select', function() {
                loadAddress($(this).data('pref'));
            });

            $('.hhk-phemtabs').tabs();

            // Copy Address
            $('#' + divFamDetailId).on('click', '.hhk-addrCopy', function() {
                copyAddress($(this).attr('name'));
            });

            // Delete address
            $('#' + divFamDetailId).on('click', '.hhk-addrErase', function() {
                eraseAddress($(this).attr('name'));
            });

            // Incomplete address bind to address flag.
            $('#' + divFamDetailId).on('click', '.hhk-incompleteAddr', function() {
                setAddrFlag($('#' + $(this).data('prefix') + 'liaddrflag'));
            });

            verifyAddrs('#divfamDetail');

            $('input.hhk-zipsearch').each(function() {
                var lastXhr;
                createZipAutoComplete($(this), 'ws_admin.php', lastXhr);
            });

            // Relationship chooser
            $('#' + divFamDetailId).on('change', '.patientRelch', function () {

                if ($(this).val() === 'slf') {

                    people.list()[$(this).data('prefix')].role = 'p';

                    if (patAsGuest === false) {
                        // remove stay button
                        $('#' + $(this).data('prefix') + 'lblStay').parent('td').empty();
                    }

                } else {

                    people.list()[$(this).data('prefix')].role = 'g';
                }
            });

            createAutoComplete($('#txtPersonSearch'), 3, {cmd: 'role', gp:'1'}, function (item) {
                addGuest(item, data);
            });

            t.setupComplete = true;
        };

        function newGuestMarkup(data, prefix) {

            var $countries, $states, $famTbl, stripeClass;

            if (data.tblId === undefined || data.tblId == '') {
                return;
            }

            $famTbl = $wrapper.find('#' + data.tblId);

            if ($famTbl.length === 0) {
                return;
            }

            if ($famTbl.children('tbody').children('tr').last().hasClass('odd')) {
                stripeClass = 'even';
            } else {
                stripeClass = 'odd';
            }

            $famTbl.find('tbody:first').append($(data.ntr).addClass(stripeClass)).append($(data.atr).addClass(stripeClass));

            // prepare stay button
            $('#' + prefix + 'cbStay').checkboxradio({
                classes: {"ui-checkboxradio-label": "hhk-unselected-text" }
            });

            if ($('#' + prefix + 'lblStay').data('stay') === '1') {
                $('#' + prefix + 'lblStay').click();
            }

            // Prepare birth date picker
            $('.ckbdate').datepicker({
                yearRange: '-99:+00',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                maxDate: 0,
                dateFormat: 'M d, yy'
            });

            // Address button
            setAddrFlag($('#' + prefix + 'liaddrflag'));

            // Remove button
            $('#' + prefix + 'btnRemove').button().click(function () {

                // Is the name entered?
                if ($('#' + prefix + 'txtFirstName').val() !== '' || $('#' + prefix + 'txtLastName').val() !== '') {
                    if (confirm('Remove this person: ' + $('#' + prefix + 'txtFirstName').val() + ' ' + $('#' + prefix + 'txtLastName').val() + '?') === false) {
                        return;
                    }
                }

                $(this).parentsUntil('tbody', 'tr').next().remove();
                $(this).parentsUntil('tbody', 'tr').remove();
                people.removeIndex[prefix];
                addrs.removeIndex[prefix];
            });

            // set country and state selectors
            $countries = $('#' + prefix + 'adrcountry' + addrPurpose);
            $countries.bfhcountries($countries.data());

            $states = $('#' + prefix + 'adrstate' + addrPurpose);
            $states.bfhstates($states.data());

            $('#' + prefix + 'phEmlTabs').tabs();

            $('input#' + prefix + 'adrzip1').each(function() {
                var lastXhr;
                createZipAutoComplete($(this), 'ws_admin.php', lastXhr);
            });

        };

        function verify() {

            var numPat = 0;
            var numGuests = 0;
            var numPriGuests = 0;
            var nameErr = false;


            // Flag blank Relationships
            $('.patientRelch').removeClass('ui-state-error');
            $('.patientRelch').each(function () {

                if ($(this).val() === '') {

                    $(this).addClass('ui-state-error');
                    flagAlertMessage('Set the highlighted Relationship.', true);
                    return false;

                }
            });

            // Compute number of guests and patients
            for (var i in people.list()) {
                // Patients
                if (people.list()[i].role === 'p') {
                    numPat++;
                }
                // guests
                if (people.list()[i].stay === '1') {
                    numGuests++;
                }
                // Primary Guests
                if (people.list()[i].pri === '1') {
                    numPriGuests++;
                }
                // Close address boxes.
                if ($('#' + i + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-n')) {
                    // Close address row
                    $('#' + i + 'toggleAddr').click();
                }

            }

            // Only one patient allowed.
            if (numPat < 1) {

                flagAlertMessage('Choose a ' + patLabel + '.', true);

                $('.patientRelch').addClass('ui-state-error');
                return false;

            } else if (numPat > 1) {

                flagAlertMessage('Only 1 ' + patLabel + ' is allowed.', true);

                for (var i in people.list()) {
                    if (people.list()[i].role === 'p') {
                        $('#' + i + 'selPatRel').addClass('ui-state-error');
                    }
                }
                return false;
            }

            // Someone checking in?
            if (findStaysChecked() < 1) {
                flagAlertMessage('There is no one actually staying.  Pick someone to stay.', true);
                return false;
            }

            // Primary guests
            if (numPriGuests > 1) {
                flagAlertMessage('There are too many primary guests.', true);
                return false;
            }


            // Last names
            $wrapper.find('.hhk-lastname').each(function () {
                if ($(this).val() == '') {
                    $(this).addClass('ui-state-error');
                    nameErr = true;
                } else {
                    $(this).removeClass('ui-state-error');
                }
            });

            // First names
            $wrapper.find('.hhk-firstname').each(function () {
                if ($(this).val() == '') {
                    $(this).addClass('ui-state-error');
                    nameErr = true;
                } else {
                    $(this).removeClass('ui-state-error');
                }
            });

            if (nameErr === true) {
                openSection(true);
                flagAlertMessage("Enter a first and last name for the people highlighted.", true);
                return false;
            }

            // each person
            for (var p in people.list()) {

                if (people.list()[p].role === 'p') {

                    // Check patient birthdate
                    if (patBirthDate & $('#' + p + 'txtBirthDate').val() === '') {
                        $('#' + p + 'txtBirthDate').addClass('ui-state-error');
                        flagAlertMessage(patLabel + ' is missing the Birth Date.', true);
                        openSection(true);
                        return false;
                    } else {
                        $('#' + p + 'txtBirthDate').removeClass('ui-state-error');
                    }

                    // Check patient address
                    if (patAddrRequired) {

                        var pMessage = verifyAddress(p);

                        if (pMessage !== '') {

                            flagAlertMessage(pMessage, true);
                            openSection(true);

                            // Open address row
                            if ($('#' + p + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                                $('#' + p + 'toggleAddr').click();
                            }

                            return false;
                        }
                    }

                // Guests
                } else {

                    // Check Patient Relationship
                    if ($('#' + p + 'selPatRel').val() === '') {

                        $('#' + p + 'selPatRel').addClass('ui-state-error');
                        flagAlertMessage('Person highlighted is missing their ' + patLabel + ' Relationship.', true);
                        openSection(true);
                        return false;

                    } else {
                        $('#' + p + 'selPatRel').removeClass('ui-state-error');
                    }

                    // Check Guest address
                    if (gstAddrRequired) {

                        var pMessage = verifyAddress(p);

                         if (pMessage !== '') {

                            flagAlertMessage(pMessage, true);
                            openSection(true);

                            // Open address row
                            if ($('#' + p + 'toggleAddr').find('span').hasClass('ui-icon-circle-triangle-s')) {
                                $('#' + p + 'toggleAddr').click();
                            }

                            return false;
                        }
                   }
                }
            }

            return true;
        };
    }

    function ExpDatesSection($dateSection) {

        var t = this;

        // Export
        t.setupComplete = false;
        t.ciDate = new Date();
        t.coDate = new Date();

        t.setUp = function(expDates) {

            $dateSection.empty();
            $dateSection.append($(expDates.mu));

            var gstDate = $('#gstDate'),
                gstCoDate = $('#gstCoDate'),
                nextDays = parseInt(expDates.defdays, 10);

            if (isNaN(nextDays) || nextDays < 1) {
                nextDays = 21;
            }

            $('#spnRangePicker').dateRangePicker({
                format: 'MMM D, YYYY',
                separator : ' to ',
                minDays: 1,
                autoClose: true,
                showShortcuts: true,
                shortcuts :
                {
                        'next-days': [nextDays]
                },
                getValue: function()
                {
                    if (gstDate.val() && gstCoDate.val() ) {
                        return gstDate.val() + ' to ' + gstCoDate.val();
                    } else {
                        return '';
                    }
                },
                setValue: function(s,s1,s2)
                {
                    gstDate.val(s1);
                    gstCoDate.val(s2);
                }
            }).bind('datepicker-change', function(event, dates) {

                // Update the number of days display text.
                var numDays = Math.ceil((dates['date2'].getTime() - dates['date1'].getTime()) / 86400000);

                $('#' + expDates.daysEle).val(numDays);

                if ($('#spnNites').length > 0) {
                    $('#spnNites').text(numDays);
                }
            });


            $dateSection.show();

            // Open the dialog if the dates are not defined yet.
//            if ($('#gstDate').val() == '') {
//                $('#spnRangePicker').data('dateRangePicker').open();
//            }

            setupComplete = true;
        };

        t.verify = function() {

            var $arrDate = $('#gstDate'),
                $deptDate = $('#gstCoDate');

            $arrDate.removeClass('ui-state-error');
            $deptDate.removeClass('ui-state-error');

            // Check in Date
            if ($arrDate.val() === '') {

                $arrDate.addClass('ui-state-error');
                flagAlertMessage("This " + resvTitle + " is missing the check-in date.", true);
                return false;

            } else {

                t.ciDate = new Date($arrDate.val());

                if (isNaN(t.ciDate.getTime())) {
                    $arrDate.addClass('ui-state-error');
                    flagAlertMessage("This " + resvTitle + " is missing the check-in date.", true);
                    return false;
                }
            }

            // Check-out date
            if ($deptDate.val() === '') {

                $deptDate.addClass('ui-state-error');
                flagAlertMessage("This " + resvTitle + " is missing the expected departure date.", true);
                return false;

            } else {

                t.coDate = new Date($deptDate.val());

                if (isNaN(t.coDate.getTime())) {
                    $deptDate.addClass('ui-state-error');
                    flagAlertMessage("This " + resvTitle + " is missing the expected departure date", true);
                    return false;
                }

                if (t.ciDate > t.coDate) {
                    $arrDate.addClass('ui-state-error');
                    flagAlertMessage("This " + resvTitle + "'s check-in date is after the expected departure date.", true);
                    return false;
                }
            }

            return true;
        };
    }

    function HospitalSection($hospSection) {
        var t = this;
        t.setupComplete = false;

        t.setUp = function(hosp) {

            var hDiv = $(hosp.div).addClass('ui-widget-content').prop('id', 'divhospDetail').hide();
            var expanderButton = $("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>")
                .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
                .append($("<span id='h_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));
            var hHdr = $('<div id="divhospHdr" style="padding:2px; cursor:pointer;"/>')
                    .append($(hosp.hdr))
                    .append(expanderButton).append('<div style="clear:both;"/>');

            hHdr.addClass('ui-widget-header ui-state-default ui-corner-top');

            hHdr.click(function() {
                if (hDiv.css('display') === 'none') {
                    hDiv.show('blind');
                    hHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                } else {
                    hDiv.hide('blind');
                    hHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                }
            });

            $hospSection.empty().append(hHdr).append(hDiv);

            $('#txtEntryDate, #txtExitDate').datepicker({
                yearRange: '-01:+01',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                dateFormat: 'M d, yy'
            });

            if ($('#txtAgentSch').length > 0) {
                createAutoComplete($('#txtAgentSch'), 3, {cmd: 'filter', basis: 'ra'}, getAgent);
                if ($('#a_txtLastName').val() === '') {
                    $('.hhk-agentInfo').hide();
                }
            }

            if ($('#txtDocSch').length > 0) {
                createAutoComplete($('#txtDocSch'), 3, {cmd: 'filter', basis: 'doc'}, getDoc);
                if ($('#d_txtLastName').val() === '') {
                    $('.hhk-docInfo').hide();
                }
            }

            $hospSection.on('change', '#selHospital, #selAssoc', function() {
                var hosp = $('#selAssoc').find('option:selected').text();
                if (hosp != '') {
                    hosp += '/ ';
                }
                $('span#spnHospName').text(hosp + $('#selHospital').find('option:selected').text());
            });


            $hospSection.show();

            if ($('#selHospital').val() === '') {
                hHdr.click();
            }

            t.setupComplete = true;
        };

        t.verify = function() {

            $hospSection.find('.ui-state-error').each(function() {
                $(this).removeClass('ui-state-error');
            });

            if ($('#selHospital').length > 0 && t.setupComplete === true) {

                if ($('#selHospital').val() == "" ) {

                    $('#selHospital').addClass('ui-state-error');

                    flagAlertMessage("Select a hospital.", true, 0);

                    $('#divhospDetail').show('blind');
                    $('#divhospHdr').removeClass('ui-corner-all').addClass('ui-corner-top');
                    return false;
                }
            }

            $('#divhospDetail').hide('blind');
            $('#divhospHdr').removeClass('ui-corner-top').addClass('ui-corner-all');

            return true;
        };

    }

    function ResvSection($wrapper) {
        var t = this;
        var $rDiv, $veh, $rHdr, $expanderButton;

        t.setupComplete = false;
        t.setUp = setUp;
        t.verify = verify;

        function setupVehicle(veh) {
            var nextVehId = 1;
            var $cbVeh = veh.find('#cbNoVehicle');
            var $nextVeh = veh.find('#btnNextVeh');
            var $tblVeh = veh.find('#tblVehicle');

            $cbVeh.change(function() {
                if (this.checked) {
                    $tblVeh.hide('scale, horizontal');
                } else {
                    $tblVeh.show('scale, horizontal');
                }
            });

            $cbVeh.change();
            $nextVeh.button();

            $nextVeh.click(function () {
                veh.find('#trVeh' + nextVehId).show('fade');
                nextVehId++;
                if (nextVehId > 4) {
                    $nextVeh.hide('fade');
                }
            });

        }

        function setupRate(data) {

            var reserve = {};
            var $finAppBtn = $wrapper.find('#btnFapp');

            if ($finAppBtn.length > 0) {

                $("#faDialog").dialog({
                    autoOpen: false,
                    resizable: true,
                    width: 650,
                    modal: true,
                    title: 'Income Chooser',
                    close: function () {$('div#submitButtons').show();},
                    open: function () {$('div#submitButtons').hide();},
                    buttons: {
                        Save: function() {
                            $.post('ws_ckin.php', $('#formf').serialize() + '&cmd=savefap' + '&rid=' + data.rid, function(rdata) {
                                try {
                                    rdata = $.parseJSON(rdata);
                                } catch (err) {
                                    alert('Bad JSON Encoding');
                                    return;
                                }
                                if (rdata.gotopage) {
                                    window.open(rdata.gotopage, '_self');
                                }
                                if (rdata.rstat && rdata.rstat == true) {
                                    var selCat = $('#selRateCategory');
                                    if (rdata.rcat && rdata.rcat != '' && selCat.length > 0) {
                                        selCat.val(rdata.rcat);
                                        selCat.change();
                                    }
                                }
                            });
                            $(this).dialog("close");
                        },
                        "Exit": function() {
                            $(this).dialog("close");
                        }
                    }
                });

                $finAppBtn.button().click(function() {
                    getIncomeDiag(data.rid);
                });
            }

            // Days


            reserve.rateList = data.resv.rdiv.ratelist;
            reserve.resources = data.resv.rdiv.rooms;
            reserve.visitFees = data.resv.rdiv.vfee;

            setupRates(reserve);

            $('#selResource').change(function () {
                $('#selRateCategory').change();

                var selected = $("option:selected", this);
                selected.parent()[0].label === "Not Suitable" ? $('#hhkroomMsg').text("Not Suitable").show(): $('#hhkroomMsg').hide();
            });

        }

        function setupRoom(idReserv) {

            // Reservation history button
            $('.hhk-viewResvActivity').click(function () {
              $.post('ws_ckin.php', {cmd:'viewActivity', rid: $(this).data('rid')}, function(data) {
                data = $.parseJSON(data);

                if (data.error) {

                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, true);
                    return;
                }
                 if (data.activity) {

                    $('div#submitButtons').hide();
                    $("#activityDialog").children().remove();
                    $("#activityDialog").append($(data.activity));
                    $("#activityDialog").dialog('open');
                }
                });

            });

            // Room selector update for constraints changes.
            $('input.hhk-constraintsCB').change( function () {
                // Disable max room size.
                updateRoomChooser(idReserv, '1', $('#gstDate').val(), $('#gstCoDate').val());
            });

            // Show confirmation form button.
            $('#btnShowCnfrm').button().click(function () {
                var amount = $('#spnAmount').text();
                if (amount === '') {
                    amount = 0;
                }
                $.post('ws_ckin.php', {cmd:'confrv', rid: $(this).data('rid'), amt: amount, eml: '0'}, function(data) {

                    data = $.parseJSON(data);

                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, true);
                        return;
                    }

                     if (data.confrv) {

                        $('div#submitButtons').hide();
                        $("#frmConfirm").children().remove();
                        $("#frmConfirm").html(data.confrv)
                            .append($('<div style="padding-top:10px;" class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix"><span>Email Address </span><input type="text" id="confEmail" value="'+data.email+'"/></div>'));

                        $("#confirmDialog").dialog('open');
                    }
                });
            });

        }

        function setUp(data) {

            $rDiv = $('<div id="divResvDetail" style="padding:2px; float:left;" class="ui-widget-content ui-corner-bottom hhk-tdbox"/>');
            $rDiv.append($(data.resv.rdiv.rChooser));

            // Rate section
            if (data.resv.rdiv.rate !== undefined) {
                $rDiv.append($(data.resv.rdiv.rate));
            }

            // Stat and notes sections
            $rDiv.append($(data.resv.rdiv.rstat)).append($(data.resv.rdiv.notes));

            // Vehicle section
            if (data.resv.rdiv.vehicle !== undefined) {
                $veh = $(data.resv.rdiv.vehicle);
                $rDiv.append($veh);
                setupVehicle($veh);
            }

            // Header
            $expanderButton = $("<ul style='list-style-type:none; float:right; margin-left:5px; padding-top:2px;' class='ui-widget'/>")
                .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
                .append($("<span id='r_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));
            $rHdr = $('<div id="divResvHdr" style="padding:2px; cursor:pointer;"/>')
                    .append($(data.resv.hdr))
                    .append($expanderButton).append('<div style="clear:both;"/>');

            $rHdr.addClass('ui-widget-header ui-state-default ui-corner-top');

            $rHdr.click(function() {
                if ($rDiv.css('display') === 'none') {
                    $rDiv.show('blind');
                    $rHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                } else {
                    $rDiv.hide('blind');
                    $rHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                }
            });

            // Add to the page.
            $wrapper.empty().append($rHdr).append($rDiv).show();

            t.$totalGuests = $('#spnNumGuests');

            setupRoom(data.rid);

            if (data.resv.rdiv.rate !== undefined) {
                setupRate(data);
            }

            t.setupComplete = true;
        };

        function verify() {

            return true;
        };
    }

    function Items () {

        var _list = {};
        var _index;
        var t = this;
        t.hasItem = hasItem;
        t.findItem = findItem;
        t.addItem = addItem;
        t.removeIndex = removeIndex;
        t.list = list;
        t.makeList = makeList;
        t._list = _list;

        function list() {
            return _list;
        };

        function makeList(theList, indexProperty) {

            _index = indexProperty;

            for (var i in theList) {
                addItem(theList[i]);
            }
        };

        function addItem(item) {

            if (hasItem(item) === false) {
                _list[item[_index]] = item;
                return true;
            }

            return false;
        };

        function removeIndex(index) {
            delete _list[index];
        }

        function hasItem(item) {

            if (_list[item[_index]] !== undefined) {
                return true;
            }

            return false;
        };

        function findItem(property, value) {
            for (var i in _list) {
                if (_list[i][property] == value) {
                    return _list[i];
                }
            }
            return null;
        }

    }

    function transferToGw(data) {

        var xferForm = $('#xform');
        xferForm.children('input').remove();
        xferForm.prop('action', data.xfer);
        if (data.paymentId && data.paymentId != '') {
            xferForm.append($('<input type="hidden" name="PaymentID" value="' + data.paymentId + '"/>'));
        } else if (data.cardId && data.cardId != '') {
            xferForm.append($('<input type="hidden" name="CardID" value="' + data.cardId + '"/>'));
        } else {
            flagAlertMessage('PaymentId and CardId are missing!', true);
            return;
        }
        xferForm.submit();
    }

    function resvPicker(data, $resvDiag, $psgDiag) {
        "use strict";
        var buttons = {};

        // reset then fill the reservation dialog
        $resvDiag.empty()
            .append($(data.resvChooser))
            .children().find('input:button').button();

        // Set up 'Check-in Now' button
        $resvDiag.children().find('.hhk-checkinNow').click(function () {
            window.open('CheckIn.php?rid=' + $(this).data('rid') + '&gid=' + data.id, '_self');
        });

        // Set up go to PSG chooser button
        if (data.psgChooser && data.psgChooser !== '') {
            buttons[data.patLabel + ' Chooser'] = function() {
                $(this).dialog("close");
                psgChooser(data, $psgDiag);
            };
        }

        // Set up New Reservation button.
        if (data.resvTitle) {
            buttons['New ' + data.resvTitle] = function() {
                data.rid = -1;
                data.cmd = 'getResv';
                $(this).dialog("close");
                getReserve(data);
            };
        }

        buttons['Exit'] = function() {
            $(this).dialog("close");
            $('div#guestSearch').show();
            $('#gstSearch').val('').focus();
        };

        $resvDiag.dialog('option', 'buttons', buttons);
        $resvDiag.dialog('option', 'title', data.resvTitle);
        $resvDiag.dialog('open');

    }

    function psgChooser(data, $dialog) {
        "use strict";

        $dialog
            .empty()
            .append($(data.psgChooser))
            .dialog('option', 'buttons', {
                Open: function() {
                    $(this).dialog('close');
                    getReserve({idPsg: $dialog.find('input[name=cbselpsg]:checked').val(), id: data.id, cmd: 'getResv'});
                },
                Cancel: function () {
                    $(this).dialog('close');
                    $('div#guestSearch').show();
                    $('#gstSearch').val('').focus();
                }
            })
            .dialog('option', 'title', data.patLabel + ' Chooser For: ' + data.fullName)
            .dialog('open');
    }

    function getReserve(sdata) {

        $.post('ws_resv.php', {id:sdata.id, rid:sdata.rid, idPsg:sdata.idPsg, cmd:sdata.cmd}, function(data) {

            try {
                data = $.parseJSON(data);
            } catch (err) {
                flagAlertMessage(err.message, true);
                return;
            }

            if (data.gotopage) {
                window.open(data.gotopage, '_self');
            }

            if (data.error) {
                flagAlertMessage(data.error, true);
            }

            loadResv(data);
        });

        $('div#guestSearch').hide();

    }

    function deleteReserve(rid, idForm) {

        var cmdStr = '&cmd=delResv' + '&rid=' + rid;
        $.post(
                'ws_ckin.php',
                cmdStr,
                function(datas) {
                    var data;
                    try {
                        data = $.parseJSON(datas);
                    } catch (err) {
                        flagAlertMessage(err.message, true);
                        $(idForm).remove();
                    }

                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, true);
                        $(idForm).remove();
                    }

                    if (data.warning) {
                        flagAlertMessage(data.warning, true);
                    }

                    if (data.result) {
                        $(idForm).remove();
                        flagAlertMessage(data.result + ' <a href="Reserve.php">Continue</a>', true);
                    }
                }
        );
    }

    function loadResv(data) {

        if (data.xfer) {
            transferToGw(data);
        }

        if (data.resvChooser && data.resvChooser !== '') {
            resvPicker(data, $('#resDialog'), $('#psgDialog'));
            return;
        } else if (data.psgChooser && data.psgChooser !== '') {
            psgChooser(data, $('#psgDialog'));
            return;
        }

        if (data.idPsg) {
            t.idPsg = data.idPsg;
        }

        if (data.id) {
            t.idName = data.id;
        }

        if (data.rid) {
            t.idResv = data.rid;
        }

        if (data.famSection) {

            people.makeList(data.famSection.mem, 'pref');
            addrs.makeList(data.famSection.addrs, 'pref');

            familySection.setUp(data);

            $('#btnDone').val('Save Family').show();
        }

        // Hospital
        if (data.hosp !== undefined) {
            hospSection.setUp(data.hosp);
        }

        // Expected Dates Control
        if (data.expDates !== undefined && data.expDates !== '') {
            expDatesSection.setUp(data.expDates);
        }

        // Reservation
        if (data.resv !== undefined) {

            resvSection.setUp(data);

            // String together some events
            $('#' + familySection.divFamDetailId).on('change', '.hhk-cbStay, .hhk-rbPri', function () {

                var tot = familySection.findStaysChecked();
                resvSection.$totalGuests.text(tot);

                if (tot > 0) {
                    resvSection.$totalGuests.parent().removeClass('ui-state-highlight');
                } else {
                    resvSection.$totalGuests.parent().addClass('ui-state-highlight');
                }
            });

            $('#famSection.hhk-cbStay').change();

            $('#btnDone').val('Save ' + resvTitle).show();

            if (data.rid > 0) {

                $('#btnDelete').click(function () {

                    if ($(this).val() === 'Deleting >>>>') {
                        return;
                    }

                    if (confirm('Delete this ' + data.resvTitle + '?')) {

                        $(this).val('Deleting >>>>');

                        deleteReserve(data.rid, 'form#form1');
                    }
                });

                $('#btnDelete').val('Delete ' + resvTitle).show();

                $('#btnShowReg').click(function () {
                    window.open('ShowRegForm.php?rid=' + data.rid, '_blank');
                });

                $('#btnShowReg').show();
            }
        }

        if (data.addPerson !== undefined) {

            // Clear the person search textbox.
            $('input#txtPersonSearch').val('');

            if (people.addItem(data.addPerson.mem)) {
                addrs.addItem(data.addPerson.addrs);
                familySection.newGuestMarkup(data.addPerson, data.addPerson.mem.pref);
                familySection.findStaysChecked();
            }
        }
    }

    function verifyInput() {

        // dates
        if (expDatesSection.verify() === false) {
            return false;
        }

        // Family
        if (familySection.verify() === false) {

            return false;
        }

        // hospital
        if (hospSection.verify() === false) {
            return false;
        }

        if (resvSection.setupComplete === true) {

            if (resvSection.verify() === false) {
                return false;
            }
        }

        return true;

    }
}

$(document).ready(function() {
    "use strict";
    var $guestSearch = $('#gstSearch');
    var resv = $.parseJSON('<?php echo $resvObjEncoded; ?>');

    var pageManager = new PageManager(resv);

    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });

    $('#btnDone, #btnShowReg, #btnDelete').button();


    $('#btnDone').click(function () {

        if ($(this).val() === 'Saving >>>>') {
            return;
        }

        hideAlertMessage();

        if (pageManager.verifyInput() === true) {

            $.post(
                'ws_resv.php',
                $('#form1').serialize() + '&cmd=saveResv&idPsg=' + pageManager.idPsg + '&rid=' + pageManager.idResv + '&' + $.param({mem: pageManager.people.list()}),
                function(data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        flagAlertMessage(err.message, true);
                        return;
                    }

                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }

                    if (data.error) {
                        flagAlertMessage(data.error, true);
                    }

                    pageManager.loadResv(data);
                }
            );

            $(this).val('Saving >>>>');
        }

    });

    $("#resDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 900,
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
                $confForm.append($('<input name="hdnCfmRid" type="hidden" value="' + $('#btnShowCnfrm').data('rid') + '"/>'))
                $confForm.submit();
            },
            'Send Email': function() {
                $.post('ws_ckin.php', {cmd:'confrv', rid: $('#btnShowCnfrm').data('rid'), eml: '1', eaddr: $('#confEmail').val(), amt: $('#spnAmount').text(), notes: $('#tbCfmNotes').val()}, function(data) {
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

    function getGuest(item) {

        hideAlertMessage();
        if (item.No_Return !== undefined && item.No_Return !== '') {
            flagAlertMessage('This person is set for No Return: ' + item.No_Return + '.', true);
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

    if (parseInt(resv.id, 10) > 0 || parseInt(resv.rid, 10) > 0) {

        resv.cmd = 'getResv';
        pageManager.getReserve(resv);

    } else {

        createAutoComplete($guestSearch, 3, {cmd: 'role', gp:'1'}, getGuest);

        // Phone number search
        createAutoComplete($('#gstphSearch'), 4, {cmd: 'role', gp:'1'}, getGuest);

        $guestSearch.keypress(function(event) {
            hideAlertMessage();
            $(this).removeClass('ui-state-highlight');
        });

        $guestSearch.focus();
    }
});
        </script>
    </body>
</html>
