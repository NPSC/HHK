<?php
use HHK\Common;
use HHK\sec\Login;
use HHK\Exception\RuntimeException;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use HHK\sec\WebInit;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\TableLog\HouseLog;
use HHK\HTMLControls\HTMLSelector;

/**
 * step3.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("InstallIncludes.php");

try {

    $login = new Login();
    $config = $login->initHhkSession();
} catch (PDOException $pex) {
    echo ("Database Error.  " . $pex->getMessage());
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}

// define db connection obj
try {
    $dbh = Common::initPDO(TRUE);
} catch (RuntimeException $hex) {
    exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
}


// get session instance
$ssn = Session::getInstance();

SysConfig::getCategory($dbh, $ssn, ["f", "r", "d", "h", "a", "hf", "ha", "p", "g"], WebInit::SYS_CONFIG);

$pageTitle = $ssn->siteName;

$errorMsg = '';

if (isset($_POST['btnNext'])) {
    $ssn->destroy(true);
    header('location:../index.php');
}

$rPrices = Common::readGenLookupsPDO($dbh, 'Price_Model');


if (isset($_POST['btnRoom']) && count($rPrices) > 0) {

    $numRooms = intval(filter_input(INPUT_POST, 'txtRooms', FILTER_SANITIZE_NUMBER_INT), 10);
    $rateCode = filter_input(INPUT_POST, 'selModel', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    try {

        $dbh->beginTransaction();

        if ($numRooms > 0 && $numRooms < 201) {

            // Clear the database
            $dbh->exec("Delete from `room` where idRoom > 0;");
            $dbh->exec("Delete from `resource`;");
            $dbh->exec("Delete from `resource_room`;");
            $dbh->exec("Delete from `resource_use`;");
            $dbh->exec("Delete from `room_log`;");

            $roomStmt = $dbh->prepare("insert into room "
                    . "(`idRoom`,`idHouse`,`Item_Id`,`Title`,`Type`,`Category`,`Status`,`State`,`Availability`,"
                    . "`Max_Occupants`,`Min_Occupants`,`Rate_Code`,`Key_Deposit_Code`,`Cleaning_Cycle_Code`, `idLocation`) VALUES "
                    . "(:idRoom, 0, 1, :title, 'r', 'dh', 'a', 'a', 'a', 4, 0, 'rb', 'k0', 'a', 1)");

            $resourceStmt = $dbh->prepare("insert into resource "
                    . "(`idResource`,`idSponsor`,`Title`,`Utilization_Category`,`Type`,`Util_Priority`,`Status`) Values "
                    . "(:idResource, 0, :rTitle, 'uc1', 'room', :priority, 'a')");

            $resourceRoomStmt = $dbh->prepare("insert into resource_room "
                    . "(`idResource_room`,`idResource`,`idRoom`) values "
                    . "(:idResourceRoom, :rrResource, :rrRoom)");

            // Install new rooms
            for ($n = 1; $n <= $numRooms; $n++) {

                $idRoom = $n + 9;
                $title = $idRoom + 100;

                // create room record
                $roomStmt->execute([':idRoom' => $idRoom, ':title' => $title]);

                // create resource record
                $resourceStmt->execute([':idResource' => $idRoom, ':rTitle' => $title, ':priority' => $title]);

                // Resource-Room
                $resourceRoomStmt->execute([':idResourceRoom' => $idRoom, ':rrResource' => $idRoom, ':rrRoom' => $idRoom]);
            }

        }

        if ($rateCode != '' && isset($rPrices[$rateCode])) {

        	SysConfig::saveKeyValue($dbh, WebInit::SYS_CONFIG, 'RoomPriceModel', $rateCode);

            if (isset($_POST['cbFin'])) {
            	SysConfig::saveKeyValue($dbh, WebInit::SYS_CONFIG, 'IncomeRated', 'true');
            } else {
            	SysConfig::saveKeyValue($dbh, WebInit::SYS_CONFIG, 'IncomeRated', 'false');
            }

            SysConfig::getCategory($dbh, $ssn, ["h", "hf"], WebInit::SYS_CONFIG);

            $dbh->exec("delete from `room_rate`");

            AbstractPriceModel::installRates($dbh, $rateCode, $ssn->IncomeRated);

        }

        $siteId = $ssn->sId;
        $houseName = $ssn->siteName;

        if ($siteId > 0) {

            $countStmt = $dbh->prepare("Select count(`idName`) from `name` where `idName` = :siteId");
            $countStmt->execute([':siteId' => $siteId]);
            $row = $countStmt->fetchAll(PDO::FETCH_NUM);

            if (isset($row[0]) && $row[0][0] == 0 && $houseName != '') {
                $nameStmt = $dbh->prepare("insert into `name` (`idName`, `Company`, `Member_Type`, `Member_Status`, `Record_Company`, `Last_Updated`, `Updated_By`) values (:idName, :company, 'np', 'a', 1, now(), 'admin')");
                $nameStmt->execute([':idName' => $siteId, ':company' => $houseName]);
            }

        } else {

            $nameStmt = $dbh->prepare("insert into `name` (`Company`, `Member_Type`, `Member_Status`, `Record_Company`, `Last_Updated`, `Updated_By`) values (:company, 'np', 'a', 1, now(), 'admin')");
            $nameStmt->execute([':company' => $houseName]);

            if ($nameStmt->rowCount() != 1) {
                throw new RuntimeException('Insert of house name record failed.');
            }

            $siteId = $dbh->lastInsertId();
            $ssn->sId = $siteId;

            SysConfig::saveKeyValue($dbh, WebInit::SYS_CONFIG, 'sId', $siteId);

        }

        if ($ssn->subsidyId == 0 && $siteId > 0) {
            $ssn->subsidyId = $siteId;

            SysConfig::saveKeyValue($dbh, WebInit::SYS_CONFIG, 'subsidyId', $siteId);

        }

        $dbh->commit();

        header('location: ../index.php');
        exit();

    } catch (Exception $ex) {

        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }

        $errorMsg = 'Room installation failed: ' . $ex->getMessage();
    }

}

$modelSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rPrices, '', TRUE), array('name'=>'selModel', 'style'=>"margin-top:20px;margin-right:10px;"));

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
                        Use Financial Assistance:<input type="checkbox" name="cbFin"  style="margin-top:20px;"/>
                        <input type="submit" name="btnRoom" id="btnRoom" value="Install Rooms" style="margin-left:17px;margin-top:20px;"/>
                    </fieldset>
                    <input type="submit" name="btnNext" id="btnNext" value="3.  Done" style="margin-left:17px;margin-top:20px;"/>
                </form>
            </div>
        </div>
    </body>
</html>

