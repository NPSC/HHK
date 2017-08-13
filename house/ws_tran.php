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
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (CLASSES . "TransferMembers.php");
require (CLASSES . "SiteConfig.php");
require CLASSES . 'TableLog.php';
require CLASSES . 'HouseLog.php';
require CLASSES . 'AuditLog.php';

require (CLASSES . 'CreateMarkupFromDB.php');
require (MEMBER . 'MemberSearch.php');

// Set page type for AdminPageCommon
$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;


$guestAdmin = ComponentAuthClass::is_Authorized("guestadmin");

// get session instance
$uS = Session::getInstance();
$config = new Config_Lite(ciCFG_FILE);


$webServices = $config->getString('webServices', 'ContactManager', '');

if ($webServices != '') {

    $wsConfig = new Config_Lite(REL_BASE_DIR . 'conf' . DS .  $webServices);
    require (CLASSES . 'neon.php');

} else {
    throw new Hk_Exception_Runtime('Web Services Configuration file is missing. ');
}


if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$events = array();
try {

    $transfer = new TransferMembers($wsConfig->getString('credentials', 'User'), decryptMessage($wsConfig->getString('credentials', 'Password')), $wsConfig->getSection('custom_fields'));

switch ($c) {

    case 'xfer':

        $ids = [];

        if (isset($_REQUEST['ids'])) {
            $ids = filter_var_array($_REQUEST['ids'], FILTER_SANITIZE_NUMBER_INT);
        }

        if (isset($_POST['id'])) {
            $ids[] = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (count($ids) > 0) {

            try {
                $reply = $transfer->sendList($dbh, $ids, $uS->username);
                $events['data'] = CreateMarkupFromDB::generateHTML_Table($reply, 'tblrpt');

            } catch (Exception $ex) {
                $events = array("error" => "Transfer Error: " . $ex->getMessage());
            }

        }

        break;

    case 'payments':

        $st = '';
        if (isset($_REQUEST["wh"])) {
            $st = filter_var($_REQUEST["st"], FILTER_SANITIZE_STRING);
        }
        $en = '';
        if (isset($_REQUEST["en"])) {
            $en = filter_var($_REQUEST["en"], FILTER_SANITIZE_STRING);
        }

        $reply = $transfer->sendDonation($dbh, $uS->username, $st, $en);
        $events['data'] = CreateMarkupFromDB::generateHTML_Table($reply, 'tblpmt');

        break;

    case 'sch':

        $arguments = array(
            'letters' => FILTER_SANITIZE_SPECIAL_CHARS,
            'mode'  => FILTER_SANITIZE_SPECIAL_CHARS,
        );

        $searchCriteria = filter_input_array( INPUT_GET, $arguments );

        try {
            $events = $transfer->searchAccount($searchCriteria);
        } catch (Execption $ex) {
            $events = array("error" => "Transfer Error: " . $ex->getMessage());
        }

        break;

    case 'listCustFields':

        try {
            $results = $transfer->listCustomFields();

            $tbl = new HTMLTable();
            $custom_fields = array();
            $th = '';

            foreach ($results as $v) {

                $tr = '';
                $th = '';

                if ($wsConfig->has('custom_fields', $v['fieldName'])) {

                    foreach ($v as $k => $r) {

                        if (is_array($r) === FALSE) {
                            $tr .= HTMLTable::makeTd($r);
                            $th .= HTMLTable::makeTh($k);
                        }
                    }

                    $tbl->addBodyTr( $tr );
                }
            }

            $tbl->addHeader($th);
            $events = array('data'=>$tbl->generateMarkup());

        } catch (Hk_Exception_Runtime $ex) {
            $events = array("error" => "Transfer Error: " . $ex->getMessage());
        }

        break;

    case 'getAcct':

        $str = '';
        $accountId = '';
        if (isset($_POST['accountId'])) {
            $accountId = intval(filter_var($_POST['accountId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $source = 'remote';

        if (isset($_POST['src']) && $_POST['src'] === 'hhk') {

                $row = $transfer->loadSourceDB($dbh, $accountId);

                if (is_null($row)) {
                    $str = 'Error - HHK Id not found';

                } else {

                    foreach ($row as $k => $v) {
                        $str .= $k . '=' . $v . '<br/>';
                    }

                    if (isset($row['accountId'])){
                        $events['accountId'] = $row['accountId'];
                    }
                }

        } else {

            $result = $transfer->retrieveAccount($accountId);

            $parms = array();
            $transfer->unwindResponse($parms, $result);


            foreach ($parms as $k => $v) {
                $str .= $k . '=' . $v . '<br/>';
            }
        }

        $events['data'] = $str;

        break;

    case 'update':

        $accountId = '';
        if (isset($_POST['accountId'])) {
            $accountId = intval(filter_var($_POST['accountId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $id = '';
        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        try {

        if ($id > 0 && $accountId > 0) {

            $result = $transfer->retrieveAccount($accountId);

            $updateResult = $transfer->updateAccount($dbh, $result, $id);

            $events = array('result'=>$updateResult);

        } else {
            $events = array('warning'=>'Both the account id and the HHK id must be present.  Remote Account Id=' . $accountId . ', HHK Id =' . $id);
        }

        } catch (Hk_Exception_Runtime $hex) {
            $events = array('warning'=>$hex->getMessage());
        }

        break;

    case 'rmvAcctId':

        $id = '';
        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($id > 0) {
            $num = $dbh->exec("update `name` set `External_Id` = '' where `idName` = $id;");
            $events = array('result'=>$num . ' records updated.');
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

