<?php
/**
 * step3.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("InstallIncludes.php");
require (CLASSES . 'PDOdata.php');
require (DB_TABLES . 'WebSecRS.php');
require (DB_TABLES . 'HouseRS.php');
require(SEC . 'Login.php');


require (CLASSES . 'Purchase/PriceModel.php');
require (CLASSES . 'TableLog.php');
require (CLASSES . 'HouseLog.php');
require CLASSES . 'AuditLog.php';


try {

    $login = new Login();
    $config = $login->initializeSession(ciCFG_FILE);
} catch (PDOException $pex) {
    echo ("Database Error.  " . $pex->getMessage());
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}

// define db connection obj
$dbh = initPDO();


// get session instance
$ssn = Session::getInstance();

SysConfig::getCategory($dbh, $ssn, "'f'", $ssn->sconf);
SysConfig::getCategory($dbh, $ssn, "'r'", $ssn->sconf);
SysConfig::getCategory($dbh, $ssn, "'d'", $ssn->sconf);
SysConfig::getCategory($dbh, $ssn, "'h'", $ssn->sconf);

$pageTitle = $ssn->siteName;

$errorMsg = '';



if (isset($_POST['btnNext'])) {
    header('location:../index.php');
}


$rPrices = readGenLookupsPDO($dbh, 'Price_Model');
$hostURL = $config->getString('site', 'Site_URL');
$url = parse_url($hostURL);


if (isset($_POST['btnRoom'])) {

    $numRooms = intval(filter_Var($_POST['txtRooms'], FILTER_SANITIZE_NUMBER_INT), 10);

    if ($numRooms > 0 && $numRooms < 201) {

        // Clear the database
        $dbh->exec("Delete from `room` where idRoom > 0;");
        $dbh->exec("Delete from `resource`;");
        $dbh->exec("Delete from `resource_room`;");
        $dbh->exec("Delete from `resource_use`;");
        $dbh->exec("Delete from `room_log`;");

        // Install new rooms
        for ($n = 1; $n <= $numRooms; $n++) {

            $idRoom = $n + 9;
            $title = $idRoom + 100;

            // create room record
            $dbh->exec("insert into room "
                    . "(`idRoom`,`idHouse`,`Item_Id`,`Title`,`Type`,`Category`,`Status`,`State`,`Availability`,
`Max_Occupants`,`Min_Occupants`,`Rate_Code`,`Key_Deposit_Code`,`Cleaning_Cycle_Code`) VALUES
($idRoom, 0, 1, '$title', 'r', 'dh', 'a', 'a', 'a', 4, 0,'rb', 'k0', 'a');");

            // create resource record
            $dbh->exec("insert into resource "
                    . "(`idResource`,`idSponsor`,`Title`,`Utilization_Category`,`Type`,`Util_Priority`,`Status`)"
                    . " Values "
                    . "($idRoom, 0, '$title', 'uc1', 'room', '$title', 'a')");

            // Resource-Room
            $dbh->exec("insert into resource_room "
                    . "(`idResource_room`,`idResource`,`idRoom`) values "
                    . "($idRoom, $idRoom, $idRoom)");
        }

    }

    $rateCode = filter_var($_POST['selModel'], FILTER_SANITIZE_STRING);

    if ($rateCode != '' && isset($rPrices[$rateCode])) {

        SysConfig::saveKeyValue($dbh, 'sys_config', 'RoomPriceModel', $rateCode);
        SysConfig::getCategory($dbh, $ssn, "'h'", $ssn->sconf);

        $dbh->exec("delete from `room_rate`");

        PriceModel::installRates($dbh, $rateCode);

    }
}

$modelSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rPrices, $rPrices[$ssn->RoomPriceModel][0], TRUE), array('name'=>'selModel'));

?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <script type="text/javascript" src="../<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="../<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="../js/install.js"></script>
        <script type="text/javascript">
    $(document).ready(function() {
        "use strict";

        $('#btnSave').click(function () {
            var $pw1 = $('#txtpw1'),
                $pw2 = $('#txtpw2');

            if (checkStrength($pw1)) {

                if ($pw1.val() !== $pw2.val()) {
                    $('#spanpwerror').text('Passwords are not the same.');
                    return;
                }

            } else {
                $('#spanpwerror').text("Password must have 8 characters including at least one uppercase and one lower case alphabetical character and one number.");
                return;
            }

            $pw1.val('');
            $pw2.val('');

            $.post('ws_install.php', {cmd: 'loadmd', 'new': hex_md5($pw1.val())}, function (data) {
                if (data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert("Parser error - " + err.message);
                        return;
                    }

                    if (data.result) {
                        $('#spanDone').text(data.result);
                        $(this).prop('disabled', true);
                    }

                    if (data.error) {
                        $('#spanpwerror').text(data.error);
                    }
                }
            });

        });
    });
        </script>
    </head>
    <body>
        <div id="page" style="width:900px;">
            <div>
                <h2 class="logo">Hospitality HouseKeeper Installation Process</h2>
                <h3>Step Three: Load Meta-data</h3>
            </div><div class='pageSpacer'></div>
            <div id="content" style="margin:10px; width:100%;">
                <div><span style="color:red;"><?php echo $errorMsg; ?></span></div>

                <table>
                    <tr>
                        <th style='text-align: right;'>URL:</th><td><?php echo $ssn->databaseURL; ?></td>
                    </tr><tr>
                        <th style='text-align: right;'>Schema:</th><td><?php echo $ssn->databaseName; ?></td>
                    </tr><tr>
                        <th style='text-align: right;'>User:</th><td><?php echo $ssn->databaseUName; ?></td>
                    </tr>
                </table><br/>

                    <fieldset>
                        <legend>1.  Load Metadata</legend>
                        <p>Admin account password: <input type='password' name='txtpw1'/><span id='spanpwerror' style='color:red; margin-left: .5em;'></span></p>
                        <p>Admin account password again: <input type='password' name='txtpw2'/></p>

                        <input type="button" id="btnSave" value="Load Metadata" style="margin:20px;"/><span id='spanDone' style='font-weight: bold;'></span>
                    </fieldset>

                <form method="post" action="step3.php" name="form1" id="form1">
                    <fieldset>
                        <legend>2. Create Rooms</legend>
                        How Many: <input type="text" name="txtRooms" size="5" style="margin-top:20px;margin-right:10px;"/>
                        Select Room Rate Plan: <?php echo $modelSel; ?>
                        <input type="submit" name="btnRoom" id="btnRoom" value="Install Rooms" style="margin-left:17px;margin-top:20px;"/>
                    </fieldset>
                    <input type="submit" name="btnNext" id="btnNext" value="3.  Done" style="margin-left:17px;margin-top:20px;"/>
                </form>
            </div>
        </div>
    </body>
</html>

