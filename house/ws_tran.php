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
require (CLASSES . "SiteConfig.php");
require CLASSES . 'TableLog.php';
require CLASSES . 'HouseLog.php';
require (THIRD_PARTY . 'neon.php');
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

} else {
    throw new Hk_Exception_Runtime('Web Services Configuration file is missing. ');
}


if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$events = array();
try {

    $transfer = new TransferMembers($wsConfig->getString('credentials', 'User'), $wsConfig->getString('credentials', 'Password'));

switch ($c) {

    case 'xfer':

        $ids = [];

        if (isset($_REQUEST['ids'])) {
            $ids = filter_var_array($_REQUEST['ids'], FILTER_SANITIZE_NUMBER_INT);
        }

        if (count($ids) > 0) {

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
            $options = array(
                'No_Return' => array(),
                'PSG_Relationship' => array()
            );

            foreach ($results as $v) {

                $tr = '';
                $th = '';

                foreach ($v as $k => $r) {

                    if (is_array($r) === FALSE) {
                        $tr .= HTMLTable::makeTd($r);

                        $th .= HTMLTable::makeTh($k);

                } else if ($k == 'fieldOptions' && isset($options[$v['fieldName']])) {

                        foreach ($r['fieldOptions']['fieldOption'] as $op) {

                            if ($op['name'] == 'No_Return') {
                                
                            }
                        }

                    }

                }

                $tbl->addBodyTr( $tr );
                $custom_fields[$v['fieldName']] = $v['fieldId'];

            }

            $confData = array('custom_fields' => $custom_fields);

            SiteConfig::saveConfig($dbh, $wsConfig, $confData, $uS->username);

            $tbl->addHeader($th);

            $events = array('data'=>$tbl->generateMarkup());

        } catch (Hk_Exception_Runtime $ex) {
            $events = array("error" => "Transfer Error: " . $ex->getMessage());
        }

        break;

    case 'getAcct':

        $accountId = '';
        if (isset($_GET['accountId'])) {
            $accountId = intval(filter_var($_GET['accountId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

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


