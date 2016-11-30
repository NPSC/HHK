<?php
/**
 * ws_tran.php
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link
 */

require ("homeIncludes.php");
require (CLASSES . "TransferMembers.php");
require ('../thirdParty/neon.php');
require (CLASSES . 'CreateMarkupFromDB.php');

// Set page type for AdminPageCommon
$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;


$guestAdmin = ComponentAuthClass::is_Authorized("guestadmin");

// get session instance
$uS = Session::getInstance();


if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$events = array();
try {


switch ($c) {

    case 'xfer':

        $ids = [];

        if (isset($_REQUEST['ids'])) {
            $ids = filter_var_array($_REQUEST['ids'], FILTER_SANITIZE_NUMBER_INT);
        }

        if (count($ids) > 0) {

            $config = new Config_Lite(ciCFG_FILE);

            $customFields = array('PSG_Id_1'=> $config->getString('custom_fields', 'PSG_Id_1'));

            $transfer = new TransferMembers($config->getString('transfer', 'User'), $config->getString('transfer', 'Password'), $customFields);

            try {
                $reply = $transfer->sendList($dbh, $ids, $uS->username);
                $events['data'] = CreateMarkupFromDB::generateHTML_Table($reply, 'newAccts');

            } catch (Exception $ex) {
                $events = array("error" => "Transfer Error: " . $ex->getMessage());
            }

        }

        break;

    default:
        $events = array("error" => "Bad Command");
}

} catch (PDOException $ex) {

    $events = array("error" => "Database Error: " . $ex->getMessage());

} catch (Hk_Exception $ex) {

    $events = array("error" => "HouseKeeper Error: " . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();


