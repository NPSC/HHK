<?php

/**
 * liveNameSearch.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");
require(MEMBER . 'MemberSearch.php');

$wInit = new webInit(WebPageCode::Service);
$dbh = $wInit->dbh;

addslashesextended($_GET);

// get session instance
$uS = Session::getInstance();


if (isset($_GET['cmd'])) {
    $c = filter_var($_GET['cmd'], FILTER_SANITIZE_STRING);
} else {
    exit();
}

$events = array();
try {

switch ($c) {

    case "srrel":

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

        // get id
        $id = 0;
        if (isset($_GET["id"])) {
            $id = intval(filter_var(urldecode($_GET["id"]), FILTER_VALIDATE_INT),10);
        }

        $namesOnly = FALSE;
        if (isset($_GET["nonly"])) {
            if ($_GET["nonly"] == '1') {
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

        $fltr = '';
        if (isset($_GET['filter'])) {
            $fltr = filter_var(urldecode($_GET['filter']), FILTER_SANITIZE_STRING);
        }

        $memberSearch = new MemberSearch($letters);
        $events = $memberSearch->volunteerCmteFilter($dbh, $basis, $fltr);

        break;

    case 'srchName':

        if (isset($_POSt['md'])) {
            $md = filter_var($_POSt['md'], FILTER_SANITIZE_STRING);
            $nameLast = (isset($_POSt['nl']) ? filter_var($_POSt['nl'], FILTER_SANITIZE_STRING) : '');
            $nameFirst = (isset($_POSt['nf']) ? filter_var($_POSt['nf'], FILTER_SANITIZE_STRING) : '');
            $email = (isset($_POSt['em']) ? filter_var($_POSt['em'], FILTER_SANITIZE_STRING) : '');

            // Check for duplicate member records
            $dups = MemberSearch::searchName($dbh, $md, $nameLast, $nameFirst, $email);

            if (count($dups) > 0) {
                $events = array(
                    'success'=>'Returned '. count($dups) . ' duplicates',
                    'dups' => MemberSearch::createDuplicatesDiv($dups));
            }

        } else {
            $events = array('error' => 'Search Names: must supply a member designation.  ');
        }

        break;

    case "delwu":

        $usr = addslashes(filter_var(urldecode($_GET["id"]), FILTER_SANITIZE_STRING));

        if ($usr != "") {
            $events = deleteWuRow($dbh, $uS->username, $usr);
        } else {
            $events = array("error" => "Key value is blank.");
        }

        break;

    case 'del':

        $fid = '';
        if (isset($_GET["fid"])) {
            $fid = filter_var(urldecode($_GET["fid"]), FILTER_SANITIZE_STRING);
        }

        require_once (CLASSES . 'PDOdata.php');
        require_once (DB_TABLES . 'WebSecRS.php');

        $fbRs = new FbxRS();
        $fbRs->fb_id->setStoredVal($fid);
        $cnt = EditRS::delete($dbh, $fbRs, array($fbRs->fb_id));

        $events = array('success' => 'y');
        break;

    default:
        $events = array("error" => "Bad Command:  $c");
}

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage());
} catch (Hk_Exception_Runtime $ex) {
    $events = array("error" => "HouseKeeper Error: " . $ex->getMessage());
}


echo( json_encode($events) );
exit();



function deleteWuRow(PDO $dbh, $admin, $usr) {

    $query = "call del_webuser ($usr, '$admin');";
    $dbh->exec($query);
    $events = array("success" => "deleted: " . $usr);

    return $events;
}


function deleteFBRow(PDO $dbh, $fbid) {

    $query = "Delete from fbx where fb_id = :fbid;";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(':fbid'=>$fbid));

    if ($stmt->rowCount() == 1) {
        $events = array("success" => "deleted: " . $fbid);
    } else {
        $events = array("error" => "delete failed");
    }


    return $events;
}
