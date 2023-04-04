<?php

use HHK\sec\{WebInit};
use HHK\SysConst\WebPageCode;
use HHK\Member\MemberSearch;

/**
 * roleSearch.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

$wInit = new webInit(WebPageCode::Service);
$dbh = $wInit->dbh;


if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_UNSAFE_RAW);
} else {
    exit();
}

$events = array();

switch ($c) {

    case "srrel":

        //get the q parameter from URL
        $letters = filter_var(urldecode($_REQUEST["letters"]), FILTER_SANITIZE_STRING);

        // get basis
        $basis = filter_var(urldecode($_REQUEST["basis"]), FILTER_SANITIZE_STRING);

        // get id
        $id = 0;
        if (isset($_REQUEST["id"])) {
            $id = intval(filter_var(urldecode($_REQUEST["id"]), FILTER_VALIDATE_INT),10);
        }

        // Should we return "New Individual" entries?
        $namesOnly = FALSE;
        if (isset($_REQUEST["nonly"])) {
            if ($_REQUEST["nonly"] == '1') {
                $namesOnly = TRUE;
            }
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->searchLinks($dbh, $basis, $id, $namesOnly);

        break;

    case "filter":

        //get the q parameter from URL
        $letters = '';
        if (isset($_GET['letters'])) {
            $letters = filter_var(urldecode($_GET['letters']), FILTER_SANITIZE_STRING);
        }

        // get basis
        $basis = '';
        if (isset($_GET['basis'])) {
            $basis = filter_var(urldecode($_GET['basis']), FILTER_SANITIZE_STRING);
        }

        $fltr = "";
        if (isset($_REQUEST["filter"])) {
            $fltr = filter_var(urldecode($_REQUEST["filter"]), FILTER_SANITIZE_STRING);
        }

        $additional = "";
        if (isset($_REQUEST["add"])) {
            $additional = filter_var(urldecode($_REQUEST["add"]), FILTER_SANITIZE_STRING);
        }

        $psg = "";
        if (isset($_REQUEST["psg"])) {
            $psg = filter_var(urldecode($_REQUEST["psg"]), FILTER_SANITIZE_STRING);
        }

        $memberSearch = new MemberSearch($letters);

        $events = $memberSearch->volunteerCmteFilter($dbh, $basis, $fltr, $additional, $psg);

        break;

    case 'role':

        $letters = '';
        if (isset($_GET['letters'])) {
            $letters = filter_var(urldecode($_GET['letters']), FILTER_SANITIZE_STRING);
        }
        $mode = '';
        if (isset($_GET['mode'])) {
            $mode = filter_var(urldecode($_GET['mode']), FILTER_SANITIZE_STRING);
        }
        $gp = FALSE;
        if (isset($_GET['gp']) && $_GET['gp'] == '1') {
            $gp = TRUE;
        }

        $mrn = FALSE;
        if (isset($_GET['mrn']) && $_GET['mrn'] == '1') {
            $mrn = TRUE;
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->roleSearch($dbh, $mode, $gp, $mrn);

        break;

    case 'guest':

        $letters = '';
        if (isset($_GET['letters'])) {
            $letters = filter_var(urldecode($_GET['letters']), FILTER_UNSAFE_RAW);
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->guestSearch($dbh);

        break;

    case 'mrn':

        $letters = '';
        if (isset($_GET['letters'])) {
            $letters = filter_var(urldecode($_GET['letters']), FILTER_SANITIZE_STRING);
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->MRNSearch($dbh);

        break;

    case 'phone':

        $letters = '';
        if (isset($_GET['letters'])) {
            $letters = filter_var(urldecode($_GET['letters']), FILTER_SANITIZE_STRING);
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->phoneSearch($dbh);

        break;

    case 'diagnosis':

        $letters = '';
        if (isset($_GET['letters'])) {
            $letters = filter_var(urldecode($_GET['letters']), FILTER_SANITIZE_STRING);
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->diagnosisSearch($dbh);

        break;

    default:
        $events = array("error" => "Bad Command:  $c");

}



echo( json_encode($events) );
exit();