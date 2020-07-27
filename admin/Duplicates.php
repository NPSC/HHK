<?php

use HHK\AlertControl\AlertMessage;
use HHK\Duplicate;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLSelector};
use HHK\SysConst\GLTableNames;

/**
 * Duplicates.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

/* require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'visitRS.php');

require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . 'Addresses.php');


require(CLASSES . "chkBoxCtrlClass.php");
require(CLASSES . "selCtrl.php");

require (HOUSE . 'psg.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'visitViewer.php');
require (HOUSE . 'Visit.php');

require (HOUSE . 'Vehicle.php');
require (HOUSE . 'HouseServices.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Hospital.php');

require (CLASSES . 'FinAssistance.php');
require (CLASSES . 'Notes.php');

require (CLASSES . 'Duplicate.php');
require (CLASSES . 'CreateMarkupFromDB.php');
*/

$wInit = new webInit();
$dbh = $wInit->dbh;
$uS = Session::getInstance();

$wInit->sessionLoadGuestLkUps();

// AJAX
if (isset($_POST['cmd'])) {

    $cmd = filter_var($_POST['cmd'], FILTER_SANITIZE_STRING);

    $markup = '';
    $events = array();

    try {
    if ($cmd == 'exp' && isset($_POST['nf']) && $_POST['nf'] != '') {

        $fullName = filter_var($_POST['nf'], FILTER_SANITIZE_STRING);

        $markup = Duplicate::expand($dbh, $fullName, $_POST, $uS->guestLookups[GLTableNames::PatientRel]);

        $events = array('mk' => $markup);

    } else if ($cmd == 'list') {

        $mType = filter_var($_POST['mType'], FILTER_SANITIZE_STRING);

        $events = array('mk'=>Duplicate::listNames($dbh, $mType));

    } else if ($cmd == 'pik') {
        // Combine members.
        $mType = filter_var($_POST['mType'], FILTER_SANITIZE_STRING);
        $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);

        $markup = Duplicate::combine($dbh, $mType, $id);

        $events = array('msg' => HTMLContainer::generateMarkup('p', $markup));

    } else if ($cmd == 'cpsg') {

        $idGood = intval(filter_var($_POST['idg'], FILTER_SANITIZE_NUMBER_INT), 10);
        $idBad = intval(filter_var($_POST['idb'], FILTER_SANITIZE_NUMBER_INT), 10);

        $events['msg'] = Duplicate::combinePsg($dbh, $idGood, $idBad);


    } else if ($cmd == 'cids') {

        $idGood = intval(filter_var($_POST['idg'], FILTER_SANITIZE_NUMBER_INT), 10);
        $idBad = intval(filter_var($_POST['idb'], FILTER_SANITIZE_NUMBER_INT), 10);

        $events['msg'] = Duplicate::combineId($dbh, $idGood, $idBad);
    }

    } catch (PDOException $pex) {
        $events = array('error'=> $pex->getMessage() . '---' . $pex->getTraceAsString());
    }

    echo json_encode($events);
    exit();
}


$mtypes = array(
    array(0 => 'g', 1 => 'Guest'),
    array(0 => 'p', 1 => 'Patient'),
    array(0 => 'ra', 1 => 'Referral Agent'),
    array(0 => 'doc', 1 => 'Doctor')
);

$mtypeSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($mtypes, '', TRUE), array('name' => 'selmtype'));

// Instantiate the alert message control
$alertMsg = new AlertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(AlertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo NOTY_CSS; ?>

        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        
        <script type="text/javascript">
// Init j-query
$(document).ready(function () {
    $('#selmtype').change(function() {
        $('#divExpansion').children().remove();
        $.post('Duplicates.php', {cmd: 'list', mType: $(this).val()},
            function (data) {
                "use strict";
                if (!data) {
                    alert('Bad Reply from Server');
                    return;
                }
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }
                if (data.error) {
                    flagAlertMessage(data.error, true);
                    return;
                }
                $('#divList').children().remove().end().append($(data.mk));

                $('.hhk-expand').click(function () {
                    $('#dupNames td').css('background-color','');
                    $(this).parent('td').css('background-color','yellow');
                    $.post('Duplicates.php', {cmd: 'exp', nf: $(this).data('fn'), mType: $(this).data('type')},
                        function (data) {
                            "use strict";
                            if (!data) {
                                alert('Bad Reply from Server');
                                return;
                            }
                            try {
                                data = $.parseJSON(data);
                            } catch (err) {
                                alert("Parser error - " + err.message);
                                return;
                            }
                            if (data.error) {
                                flagAlertMessage(data.error, true);
                                return;
                            }

                            $('#divExpansion').children().remove().end().append($(data.mk)).show();
                            $('#btnCombPSG, #btnCombId').button();
                            $('#btnCombine').click(function () {
                                var id = $('input[name=rbchoose]:checked').val();
                                $('#spnAlert').text('');
                                if (!id || id == 0) {
                                    $('#spnAlert').text('Pick a name to combine.');
                                    return false;
                                }
                                $.post('Duplicates.php', {cmd: 'pik', id: id, mType: $(this).data('type')},
                                        function (data) {
                                            "use strict";
                                            if (!data) {
                                                alert('Bad Reply from Server');
                                                return;
                                            }
                                            try {
                                                data = $.parseJSON(data);
                                            } catch (err) {
                                                alert("Parser error - " + err.message);
                                                return;
                                            }
                                            if (data.error) {
                                                flagAlertMessage(data.error, true);
                                                return;
                                            }
                                            if (data.msg) {
                                                $('#divExpansion').children().remove().end().append($(data.msg)).show();
                                            }
                                        });
                            });
                            $('#btnCombPSG').click(function () {
                                $("#divAlert1").hide();
                                var idGood = $('input[name=rbgood]:checked').val();
                                var idBad = $('input[name=rbbad]:checked').val();
                                $('#spnAlert').text('');
                                if (!idGood || idGood == 0) {
                                    $('#spnAlert').text('Pick a Good PSG to combine.');
                                    return false;
                                }
                                if (!idBad || idBad == 0) {
                                    $('#spnAlert').text('Pick a Bad PSG to combine.');
                                    return false;
                                }
                                if (idBad == idGood) {
                                    $('#spnAlert').text('Pick a different bad and good PSG to combine.');
                                    return false;
                                }
                                $.post('Duplicates.php', {cmd: 'cpsg', idg: idGood, idb: idBad},
                                        function (data) {
                                            "use strict";
                                            if (!data) {
                                                alert('Bad Reply from Server');
                                                return;
                                            }
                                            try {
                                                data = $.parseJSON(data);
                                            } catch (err) {
                                                alert("Parser error - " + err.message);
                                                return;
                                            }
                                            if (data.error) {
                                                flagAlertMessage(data.error, 'error');
                                                return;
                                            }
                                            if (data.msg && data.msg != '') {
                                                flagAlertMessage(data.msg, 'info');
                                            }
                                });
                            });
                            $('#btnCombId').click(function () {
                                $("#divAlert1").hide();
                                var idGood = $('input[name=rbsave]:checked').val();
                                var idBad = $('input[name=rbremove]:checked').val();
                                $('#spnAlert').text('');
                                if (!idGood || idGood == 0) {
                                    $('#spnAlert').text('Pick a Save Id to combine.');
                                    return false;
                                }
                                if (!idBad || idBad == 0) {
                                    $('#spnAlert').text('Pick a Remove Id to combine.');
                                    return false;
                                }
                                if (idBad == idGood) {
                                    $('#spnAlert').text('Pick a different save and remove Id to combine.');
                                    return false;
                                }
                                $.post('Duplicates.php', {cmd: 'cids', idg: idGood, idb: idBad},
                                        function (data) {
                                            "use strict";
                                            if (!data) {
                                                alert('Bad Reply from Server');
                                                return;
                                            }
                                            try {
                                                data = $.parseJSON(data);
                                            } catch (err) {
                                                alert("Parser error - " + err.message);
                                                return;
                                            }
                                            if (data.error) {
                                                flagAlertMessage(data.error, 'alert');
                                                return;
                                            }
                                            if (data.msg && data.msg != '') {
                                                flagAlertMessage(data.msg, 'success');
                                            }
                                });
                            });
                    });
                });
        });
    });
});
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="divAlertMsg" style="clear:left;"><?php echo $resultMessage; ?></div>
            <?php echo 'Search for: ' . $mtypeSel; ?>
            <div style="clear:both;"></div>
            <div id="divList" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail" style="overflow: scroll; float:left; max-height: 500px; font-size:.85em;"></div>
            <div id="divExpansion" style="float:left;display:none;font-size:.85em;" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail"></div>
        </div>
    </body>
</html>
