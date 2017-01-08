<?php
/**
 * ws_tran.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");
require (CLASSES . "TransferMembers.php");
require ('../thirdParty/neon.php');
require (CLASSES . 'CreateMarkupFromDB.php');
require (MEMBER . 'MemberSearch.php');

// Set page type for AdminPageCommon
$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;


$guestAdmin = ComponentAuthClass::is_Authorized("guestadmin");

// get session instance
$uS = Session::getInstance();
$config = new Config_Lite(ciCFG_FILE);


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

    case 'sch':

        $arguments = array(
            'letters' => FILTER_SANITIZE_SPECIAL_CHARS,
            'mode'  => FILTER_SANITIZE_SPECIAL_CHARS,
        );

        $searchCriteria = filter_input_array( INPUT_GET, $arguments );        $letters = '';

        $transfer = new TransferMembers($config->getString('transfer', 'User'), $config->getString('transfer', 'Password'), array());

        try {
            $events = $transfer->searchAccount($searchCriteria);
        } catch (Execption $ex) {
            $events = array("error" => "Transfer Error: " . $ex->getMessage());
        }

        break;

    case 'getAcct':

        $accountId = '';
        if (isset($_GET['accountId'])) {
            $accountId = intval(filter_var($_GET['accountId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $transfer = new TransferMembers($config->getString('transfer', 'User'), $config->getString('transfer', 'Password'), array());
        $result = $transfer->retrieveAccount($accountId);

        if ($result['accountId'] == $accountId) {

            $events['metadata'] = array(
                'createdDateTime'=> $result['createdDateTime'],
                'createdBy' => $result['createdBy'],
                'lastModifiedDateTime' => $result['lastModifiedDateTime'],
                'lastModifiedBy' => $result['lastModifiedBy'],
                );

            $events['name'] = array();
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


