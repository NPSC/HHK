<?php

use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\sec\Session;
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLTable;
use HHK\Exception\RuntimeException;
use HHK\CrmExport\AbstractExportManager;
use HHK\Exception\UnexpectedValueException;

/**
 * ws_tran.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");
$wInit = new WebInit(WebPageCode::Service);

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

if (is_null($transfer = AbstractExportManager::factory($dbh, $uS->ContactManager))) {
    throw new UnexpectedValueException('A Contact Manager is not defined');
}

$events = array();

if (filter_has_var(INPUT_GET,"cmd")) {
    $c = filter_input(INPUT_GET, "cmd", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}
if (filter_has_var(INPUT_POST,"cmd")) {
    $c = filter_input(INPUT_POST, "cmd", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}


try {

    switch ($c) {

        case 'members':

            $rags = array(
                'ids' => array(
                            'filter' => FILTER_SANITIZE_NUMBER_INT,
                            'flags'  => FILTER_FORCE_ARRAY,
                           )
            );
            $post = filter_input_array(INPUT_POST, $rags);

            if (isset($post['ids']) && count($post['ids']) > 0) {

                try {
                    $events['members'] = $transfer->exportMembers($dbh, $post['ids']);
                } catch (Exception $ex) {
                    $events = array("error" => "Transfer Error: " . $ex->getMessage());
                }

            } else {
                $events = array("error" => "There are no ids to pass.");
            }
            break;

        case 'payments':

            $arguments = array(
                'st' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'en' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            );

            $post = filter_input_array(INPUT_POST, $arguments);

            $reply = $transfer->exportPayments($dbh, $post['st'], $post['en']);

            $events['data'] = CreateMarkupFromDB::generateHTML_Table($reply, 'tblpmt');

            if (count($transfer->getMemberReplies()) > 0) {
                $events['members'] = CreateMarkupFromDB::generateHTML_Table($transfer->getMemberReplies(), 'tblrpt');
            }

            break;

        case 'visits':

            $arguments = array(
                'psgId' => FILTER_SANITIZE_NUMBER_INT,
                'rels' => array(
                            'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                            'flags'  => FILTER_FORCE_ARRAY,
                           ),
            );

            $post = filter_input_array(INPUT_POST, $arguments);

            $rels = [];
            foreach ($post['rels'] as $v) {
                $rels[$v['id']] = $v['rel'];
            }

            // Visit results
            $events['visits'] = $transfer->exportVisits($dbh, intVal($post['psgId']), $rels);

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

            $arguments = array(
                'psgIds' => array(
                    'filter' => FILTER_SANITIZE_NUMBER_INT,
                    'flags' => FILTER_FORCE_ARRAY
                    )
            );

            $post = filter_input_array(INPUT_POST, $arguments);

            // Exclude results
            $events['excludes'] = $transfer->setExcludeMembers($dbh, $post['psgIds']);

            break;

        case 'sch':

            $arguments = array(
                'letters' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'mode' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            );

            $post = filter_input_array(INPUT_GET, $arguments);

            try {
                $events = $transfer->searchMembers($post);
            } catch (Exception $ex) {
                $events = array("error" => "Search Error: " . $ex->getMessage());
            }

            break;

        case 'listCustFields':

            try {
                $results = $transfer->getMyCustomFields($dbh);

                $tbl = new HTMLTable();
                $th = '';

                foreach ($results as $v) {

                    $tr = HTMLTable::makeTd($v['Code']);
                    $th .= HTMLTable::makeTh($v['Description']);

                    $tbl->addBodyTr($tr);
                }

                $tbl->addHeader($th);
                $events = array('data' => $tbl->generateMarkup());
            } catch (RuntimeException $ex) {
                $events = array("error" => "Transfer Error: " . $ex->getMessage());
            }

            break;

        case 'getAcct':

            $arguments = array(
                'accountId' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'src' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'url' => FILTER_SANITIZE_URL
            );

            $post = filter_input_array(INPUT_POST, $arguments);

            $events['data'] = $transfer->getMember($dbh, $post);
            $events['accountId'] = $transfer->getAccountId();

            break;

        case 'getRelat':

            $arguments = array(
                'accountId' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'src' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'url' => FILTER_SANITIZE_URL
            );

            $post = filter_input_array(INPUT_POST, $arguments);

            $events['data'] = $transfer->getRelationship($post);
            //$events['accountId'] = $transfer->getAccountId();

            break;

        case 'update':

            $arguments = array(
                'accountId' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'id' => FILTER_SANITIZE_NUMBER_INT,
            );

            $filtered = filter_input_array(INPUT_POST, $arguments);

            try {

                if ($filtered['id'] > 0 && $filtered['accountId'] != '') {

                    $result = $transfer->retrieveRemoteAccount($filtered['accountId']);

                    try {
                        $updateResult = $transfer->updateRemoteMember($dbh, $result, $filtered['id'], [], TRUE);
                    } catch (RuntimeException $e) {
                        $updateResult = $e->getMessage();
                    }

                    $events = array('result' => $updateResult);
                } else {
                    $events = array('warning' => 'Both the account id and the HHK id must be present.  Remote Account Id=' . $filtered['accountId'] . ', HHK Id =' . $filtered['id']);
                }
            } catch (RuntimeException $hex) {
                $events = array('warning' => $hex->getMessage());
            }

            break;

        case 'rmvAcctId':

            $arguments = array(
                'id' => FILTER_SANITIZE_NUMBER_INT,
            );

            $filtered = filter_input_array(INPUT_POST, $arguments);

            $num = $transfer->setExcludeMembers($dbh, $filtered['id']);

            $events = array('result' => $num . ' records updated.');

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