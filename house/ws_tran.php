<?php
use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\Session;
use HHK\Neon\TransferMembers;
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLTable;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;

/**
 * ws_tran.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");
$wInit = new WebInit(WebPageCode::Service);

$dbh = $wInit->dbh;


// get session instance
$uS = Session::getInstance();
$config = new Config_Lite(ciCFG_FILE);


$webServices = $config->getString('webServices', 'ContactManager', '');

if ($webServices != '') {

    $wsConfig = new Config_Lite(REL_BASE_DIR . 'conf' . DS .  $webServices);


} else {
    throw new RuntimeException('Web Services Configuration file is missing. ');
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
        if (isset($_REQUEST["st"])) {
            $st = filter_var($_REQUEST["st"], FILTER_SANITIZE_STRING);
        }
        $en = '';
        if (isset($_REQUEST["en"])) {
            $en = filter_var($_REQUEST["en"], FILTER_SANITIZE_STRING);
        }

        $reply = $transfer->sendDonations($dbh, $uS->username, $st, $en);
        $events['data'] = CreateMarkupFromDB::generateHTML_Table($reply, 'tblpmt');

        if (count($transfer->getMemberReplies()) > 0) {
            $events['members'] = CreateMarkupFromDB::generateHTML_Table($transfer->getMemberReplies(), 'tblrpt');
        }

        break;

      case 'visits':

        $idPsg = 0;

        if (isset($_REQUEST['psgId'])) {
            $idPsg = intval(filter_var($_REQUEST['psgId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        // Visit results
        $events['visits'] = $transfer->sendVisits($dbh, $uS->username, $idPsg);

        // New members
        if (count($transfer->getMemberReplies()) > 0) {
            $events['members'] = $transfer->getMemberReplies();
        }

        // Households
        if (count($transfer->getHhReplies()) > 0) {
            $events['households'] = $transfer->getHhReplies();
        }

        break;

      case 'excludes':

          $idPsgs = [];

          if (isset($_REQUEST['psgIds'])) {
              $idPsgs = filter_var_array($_REQUEST['psgIds'], FILTER_SANITIZE_NUMBER_INT);
          }

          // Neon Exclude results
          $events['excludes'] = $transfer->sendExcludes($dbh, $idPsgs, $uS->username);


          break;

      case 'sch':

        $arguments = array(
            'letters' => FILTER_SANITIZE_SPECIAL_CHARS,
            'mode'  => FILTER_SANITIZE_SPECIAL_CHARS,
        );

        $searchCriteria = filter_input_array( INPUT_GET, $arguments );

        try {
            $events = $transfer->searchAccount($searchCriteria);
        } catch (Exception $ex) {
            $events = array("error" => "Transfer Error: " . $ex->getMessage());
        }

        break;

      case 'listCustFields':

        try {
            $results = $transfer->listCustomFields();

            $tbl = new HTMLTable();
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

        } catch (RuntimeException $ex) {
            $events = array("error" => "Transfer Error: " . $ex->getMessage());
        }

        break;

    case 'getAcct':

        $str = '';
        $accountId = '';
        if (isset($_POST['accountId'])) {
            $accountId = intval(filter_var($_POST['accountId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $src = '';
        if (isset($_POST['src'])) {
            $src = filter_var($_POST['src'], FILTER_SANITIZE_STRING);
        }

        if ($src === 'hhk') {

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

        } else if ($src = 'remote') {

            // Neon accounts
            $result = $transfer->retrieveAccount($accountId);

            $parms = array();
            $transfer->unwindResponse($parms, $result);


            foreach ($parms as $k => $v) {
                $str .= $k . '=' . $v . '<br/>';
            }

            // Neon Househods
            $result = $transfer->searchHouseholds($accountId);

            $parms = array();
            $transfer->unwindResponse($parms, $result);

            $str .= "*Households*<br/>";

            foreach ($parms as $k => $v) {
                $str .= $k . '=' . $v . '<br/>';
            }

        } else {
            $str = "Source for search not found: " . $src;
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

            try{
                $updateResult = $transfer->updateNeonAccount($dbh, $result, $id);
            } catch (RuntimeException $e) {
                $updateResult = $e->getMessage();
            }

            $events = array('result'=>$updateResult);

        } else {
            $events = array('warning'=>'Both the account id and the HHK id must be present.  Remote Account Id=' . $accountId . ', HHK Id =' . $id);
        }

        } catch (RuntimeException $hex) {
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

} catch (Exception $ex) {

    $events = array("error" => "HouseKeeper Error: " . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();
?>