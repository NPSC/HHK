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
    $config = $login->initHhkSession(ciCFG_FILE);
} catch (PDOException $pex) {
    echo ("Database Error.  " . $pex->getMessage());
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}

// define db connection obj
// define db connection obj
try {
    $dbh = initPDO(TRUE);
} catch (Hk_Exception_Runtime $hex) {
    exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
}


// get session instance
$ssn = Session::getInstance();

SysConfig::getCategory($dbh, $ssn, "'f'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'r'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'d'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'h'", webInit::SYS_CONFIG);

$pageTitle = $ssn->siteName;

$errorMsg = '';

if (isset($_POST['btnNext'])) {
    $ssn->destroy(true);
    header('location:../index.php');
}

$rPrices = readGenLookupsPDO($dbh, 'Price_Model');


if (isset($_POST['btnRoom']) && count($rPrices) > 0) {

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
`Max_Occupants`,`Min_Occupants`,`Rate_Code`,`Key_Deposit_Code`,`Cleaning_Cycle_Code`, `idLocation`) VALUES
($idRoom, 0, 1, '$title', 'r', 'dh', 'a', 'a', 'a', 4, 0,'rb', 'k0', 'a', 1);");

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
        SysConfig::getCategory($dbh, $ssn, "'h'", webInit::SYS_CONFIG);

        $dbh->exec("delete from `room_rate`");

        PriceModel::installRates($dbh, $rateCode);

    }

    $siteId = $ssn->sId;
    $houseName = $ssn->siteName;

    if ($siteId > 0) {

        $stmt = $dbh->query("Select count(`idName`) from `name` where `idName` = $siteId");
        $row = $stmt->fetchAll(PDO::FETCH_NUM);


        if (isset($row[0]) && $row[0][0] == 0 && $houseName != '') {
            $dbh->exec("insert into `name` (`idName`, `Company`, `Member_Type`, `Member_Status`, `Record_Company`, `Last_Updated`, `Updated_By`) values ($siteId, '$houseName', 'np', 'a', 1, now(), 'admin')");
        }

    } else {

        $numRcrds = $dbh->exec("insert into `name` (`Company`, `Member_Type`, `Member_Status`, `Record_Company`, `Last_Updated`, `Updated_By`) values ('$houseName', 'np', 'a', 1, now(), 'admin')");
        if ($numRcrds != 1) {
            // problem
            exit('Insert of house name record failed.  ');
        }

        $siteId = $dbh->lastInsertId();
        $ssn->sId = $siteId;

        // log changes
        if ($ssn->sId != $siteId && is_null($dbh) === FALSE) {
            HouseLog::logSiteConfig($dbh, 'site' . ':' . 'Site_Id', $siteId, 'admin');
        }

        SysConfig::saveKeyValue($dbh, 'sys_config', 'sId', $siteId);

    }

    if ($ssn->subsidyId == 0 && $siteId > 0) {
        $ssn->subsidyId = $siteId;

        // log changes
        if ($ssn->subsidyId != $siteId && is_null($dbh) === FALSE) {
            HouseLog::logSiteConfig($dbh, 'financial' . ':' . 'RoomSubsidyId', $siteId, 'admin');
        }

        SysConfig::saveKeyValue($dbh, 'sys_config', $siteId);

    }

}

$modelSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rPrices, '', TRUE), array('name'=>'selModel'));

?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
    </head>
    <body>
        <div id="page" style="width:900px;">
            <div>
                <h2 class="logo">Hospitality HouseKeeper Installation Process</h2>
                <h3>Step Three: Initialize House</h3>
            </div><div class='pageSpacer'></div>
            <div id="content" style="margin:10px; width:100%;">
                <div><span style="color:red;"><?php echo $errorMsg; ?></span></div>

                <form method="post" action="step3.php" name="form1" id="form1">
                    <fieldset>
                        <legend>Create Rooms</legend>
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

