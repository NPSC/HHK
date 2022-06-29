<?php

use HHK\sec\Pages;
use HHK\sec\{Session, SecurityComponent, UserClass, WebInit};
use HHK\SysConst\WebPageCode;
use HHK\Tables\EditRS;
use HHK\Tables\WebSec\{Id_SecurityGroupRS, W_authRS, W_usersRS};
use HHK\AuditLog\NameLog;
use HHK\DataTableServer\SSP;
use HHK\Exception\RuntimeException;
use HHK\House\Report\GuestReport;
use HHK\Member\WebUser;
use HHK\Member\Relation\AbstractRelation;
use HHK\sec\SAML;
use HHK\Neon\ConfigureNeon;
use HHK\Cron\JobFactory;
use HHK\Tables\CronRS;
use HHK\Cron\AbstractJob;

/**
 * ws_gen.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");




$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;

$uS = Session::getInstance();

$maintFlag = SecurityComponent::is_Authorized("ws_gen_Maint");
$donationsFlag = SecurityComponent::is_Authorized("NameEdit_Donations");


if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
} else {
    $c = 'bad command';
}

$events = array();
try {


    switch ($c) {

        case "zipd":

            if (isset($_POST["zipf"])) {
                $zipf = filter_var($_POST["zipf"], FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_POST["zipt"])) {
                $zipt = filter_var($_POST["zipt"], FILTER_SANITIZE_NUMBER_INT);
            }

            try{
                $events['success'] = number_format(GuestReport::calcZipDistance($dbh, $zipf, $zipt), 0);
            } catch (RuntimeException $hex) {
                $events['error'] = "Zip code not found.  ";
            }

            break;

    case 'schzip':

        if (isset($_GET['zip'])) {
            $zip = filter_var($_GET['zip'], FILTER_SANITIZE_NUMBER_INT);
            $events = searchZip($dbh, $zip);
        }
        break;

        case "save":

            $parms = array();
            if (isset($_POST["parms"])) {
                $parms = filter_var_array($_POST["parms"], FILTER_SANITIZE_STRING);
            }

            $events = WebUser::saveUname($dbh, $uS->username, $parms, $maintFlag);

            break;

        case "gpage":

            $site = filter_var($_REQUEST["page"], FILTER_SANITIZE_STRING);

            if ($maintFlag) {
                $events = Pages::getPages($dbh, $site);
            } else {
                $events = array("error" => "Unauthorized");
            }

            break;

        case "edsite":

            $parms = $_REQUEST["parms"];

            if (($parms = filter_var_array($parms)) === false) {
                $events = array("error" => "Bad input");
            } else if (SecurityComponent::is_TheAdmin()) {
                $events = Pages::editSite($dbh, $parms);
            } else {
                $events = array("error" => "Sites Access denied");
            }

            break;


        case "recent":

            $events = recentReport($dbh, $_POST["parms"], $donationsFlag);
            break;

        case "chglog" :

            $id = filter_var(urldecode($_REQUEST["uid"]), FILTER_VALIDATE_INT);

            $events = changeLog($dbh, $id, $_GET);
            break;

        case 'showLog':

            $logSel = '';
            $where = '';
            $edRows = array();

            $dbView = 'vsyslog';
            $whereField = '';
            $priKey = 'Log_Type';

            $columns = array(
                array( 'db' => 'Log_Type',  'dt' => 'Log_Type' ),
                array( 'db' => 'Sub_Type',   'dt' => 'Sub_Type' ),
                array( 'db' => 'User_Name', 'dt' => 'User_Name'),
                array( 'db' => 'Id1', 'dt' => 'Id1'),
                array( 'db' => 'Str1', 'dt' => 'Str1'),
                array( 'db' => 'Str2', 'dt' => 'Str2'),
                array( 'db' => 'Log_Text', 'dt' => 'Log_Text'),
                array( 'db' => 'Timestamp', 'dt' => 'Ts'),

            );

            if (isset($_REQUEST['logId'])) {
                $logSel = filter_var($_REQUEST['logId'], FILTER_SANITIZE_STRING);
            }

            if ($logSel == 'liss') {
                $where = " `Log_Type` in ('sys_config', 'Site_Config_File') ";
            } else if ($logSel == 'lirr') {
                $where = " `Log_Type` in ('resource', 'room_rate', 'room') ";
            } else if ($logSel == 'lill') {
                $where = " `Log_Type` = 'gen_lookups' ";
            }

            $events = SSP::complex ( $_GET, $dbh, $dbView, $priKey, $columns, null, $where );

            break;

        case "showCron":

            $columns = array(
            array( 'db' => 'idJob',  'dt' => 'ID' ),
            array( 'db' => 'Title',   'dt' => 'Title' ),
            array( 'db' => 'Params', 'dt' => 'Params'),
            array( 'db' => 'Interval', 'dt' => 'Interval'),
            array( 'db' => 'Day', 'dt' => 'Day'),
            array( 'db' => 'Hour', 'dt' => 'Hour'),
            array( 'db' => 'Minute', 'dt' => 'Minute'),
            array( 'db' => 'Status', 'dt' => 'Status'),
            array( 'db' => 'LastRun', 'dt' => 'Last Run'),
            );
            $events = SSP::complex ( $_GET, $dbh, "cronjobs", "idJob", $columns, null, null );

            break;

        case "showCronLog":

            $columns = array(
            array( 'db' => 'idJob',  'dt' => 'Job ID' ),
            array( 'db' => 'Job',   'dt' => 'Job' ),
            array( 'db' => 'Log_Text', 'dt' => 'Log Text'),
            array( 'db' => 'Status', 'dt' => 'Status'),
            array( 'db' => 'timestamp', 'dt' => 'Run Time'),
            );
            $events = SSP::complex ( $_GET, $dbh, "vcron_log", "idLog", $columns, null, null );

            break;

        case "forceRunCron":
            $idJob = 0;
            if (isset($_REQUEST['idJob'])) {
                $idJob = intval(filter_var($_REQUEST['idJob'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $dryRun = true;
            if (isset($_REQUEST['dryRun'])) {
                $dryRun = boolval(filter_var($_REQUEST['dryRun'], FILTER_SANITIZE_NUMBER_INT));
            }

            $job = JobFactory::make($dbh, $idJob, $dryRun);
            $job->run();
            $events = ["idJob"=>$idJob, 'status'=>$job->status, 'logMsg'=>($dryRun ? "<strong>Dry Run: </strong>": "") . $job->logMsg];

            break;

        case "updateCronJob":
            $idJob = 0;
            if (isset($_REQUEST['idJob'])) {
                $idJob = intval(filter_var($_REQUEST['idJob'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $interval = '';
            if (isset($_REQUEST['interval'])) {
                $interval = filter_var($_REQUEST['interval'], FILTER_SANITIZE_STRING);
            }

            $day = '';
            if (isset($_REQUEST['day'])) {
                $day = filter_var($_REQUEST['day'], FILTER_SANITIZE_STRING);
            }

            $weekday = '';
            if (isset($_REQUEST['weekday'])) {
                $weekday = filter_var($_REQUEST['weekday'], FILTER_SANITIZE_STRING);
            }

            $hour = '';
            if (isset($_REQUEST['hour'])) {
                $hour = filter_var($_REQUEST['hour'], FILTER_SANITIZE_STRING);
            }

            $minute = '';
            if (isset($_REQUEST['minute'])) {
                $minute = filter_var($_REQUEST['minute'], FILTER_SANITIZE_STRING);
            }

            $status = '';
            if (isset($_REQUEST['status'])) {
                $status = filter_var($_REQUEST['status'], FILTER_SANITIZE_STRING);
            }

            $events = updateCronJob($dbh, $idJob, $interval, $day, $weekday, $hour, $minute, $status);
            break;

        case "delRel":

            $id = 0;
            $rId = 0;
            $relCode = "";
            if (isset($_POST['id'])) {
                $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            if (isset($_POST['rId'])) {
                $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            if (isset($_POST['rc'])) {
                $rc = filter_var($_POST['rc'], FILTER_SANITIZE_STRING);
            }

            $events = deleteRelationLink($dbh, $id, $rId, $rc);
            break;

        case "newRel":

            $id = 0;
            $rId = 0;
            $relCode = "";
            if (isset($_POST['id'])) {
                $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            if (isset($_POST['rId'])) {
                $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            if (isset($_POST['rc'])) {
                $rc = filter_var($_POST['rc'], FILTER_SANITIZE_STRING);
            }

            $events = newRelationLink($dbh, $id, $rId, $rc);
            break;

        case "addcareof":

            $id = 0;
            $rId = 0;
            $relCode = "";
            if (isset($_POST['id'])) {
                $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            if (isset($_POST['rId'])) {
                $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            if (isset($_POST['rc'])) {
                $rc = filter_var($_POST['rc'], FILTER_SANITIZE_STRING);
            }

            $events = changeCareOfFlag($dbh, $id, $rId, $rc, TRUE);
            break;

        case "delcareof":

            $id = 0;
            $rId = 0;
            $relCode = "";
            if (isset($_POST['id'])) {
                $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            if (isset($_POST['rId'])) {
                $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            if (isset($_POST['rc'])) {
                $rc = filter_var($_POST['rc'], FILTER_SANITIZE_STRING);
            }

            $events = changeCareOfFlag($dbh, $id, $rId, $rc, FALSE);
            break;

        case "adchgpw":
            $adPw = '';
            $uid = 0;

            if (isset($_POST["adpw"])) {
                $adPw = filter_var($_POST["adpw"], FILTER_SANITIZE_STRING);
            }

            if (isset($_POST['uid'])) {
                $uid = intval(filter_var($_POST['uid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            if (isset($_POST["uname"])) {
                $uname = filter_var($_POST["uname"], FILTER_SANITIZE_STRING);
            }

            $events = adminChangePW($dbh, $adPw, $uid, $uname);

            break;

        case "accesslog":
            $events = AccessLog($dbh, $_GET);

            break;

        case 'shoConfNeon':

            $enFile = '';
            if (isset($_POST["servFile"])) {
                $enFile = filter_var($_POST["servFile"], FILTER_SANITIZE_STRING);
            }

            $servFile = decryptMessage($enFile);

            if ($servFile !== '') {
                $confNeon = new ConfigureNeon($servFile);
                $events = $confNeon->showConfig($dbh);
            }
            break;

        default:
            $events = array("error" => "Bad Command");
    }

} catch (\ErrorException $ex) {

    $events = array("error" => "<strong>Error</strong> " . $ex->getMessage());
} catch (PDOException $ex) {

    $events = array("error" => "Database Error" . $ex->getMessage());
} catch (RuntimeException $ex) {

    $events = array("error" => "HouseKeeper Error" . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();


function searchZip(PDO $dbh, $zip) {

    $query = "select * from postal_codes where Zip_Code like :zip LIMIT 10";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(':zip'=>$zip . "%"));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = array();
    foreach ($rows as $r) {
        $ent = array();

        $ent['value'] = $r['Zip_Code'];
        $ent['label'] = $r['City'] . ', ' . $r['State'] . ', ' . $r['Zip_Code'];
        $ent['City'] = $r['City'];
        $ent['County'] = $r['County'];
        $ent['State'] = $r['State'];

        $events[] = $ent;
    }

    return $events;
}

function adminChangePW(PDO $dbh, $adminPw, $wUserId, $uname) {

    $event = array();

    if (SecurityComponent::is_Admin()) {

        $u = new UserClass();

        $newPw = $u->generateStrongPassword();

        if ($u->updateDbPassword($dbh, $wUserId, $adminPw, $newPw, $uname, true) === TRUE) {
            $event = array('success' => 'Password updated.', 'tempPW'=>$newPw);
        } else {
            $event = array('error' => $u->logMessage .  '.  Password is unchanged.');
        }
    } else {
        $event = array('error' => 'Insufficient authorization.  Password is unchanged.');
    }

    return $event;
}

function changeCareOfFlag(PDO $dbh, $id, $rId, $relCode, $flag) {

    $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

    if (is_null($rel) === FALSE) {
        $uS = Session::getInstance();
        $msh = $rel->setCareOf($dbh, $rId, $flag, $uS->username);

        $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

        return array('success' => $msh, 'rc' => $relCode, 'markup' => $rel->createMarkup());
    }
    return array('error' => 'Relationship is Undefined.');
}

function deleteRelationLink(PDO $dbh, $id, $rId, $relCode) {

    $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

    if (is_null($rel) === FALSE) {

        $msh = $rel->removeRelationship($dbh, $rId);

        $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

        return array('success' => $msh, 'rc' => $relCode, 'markup' => $rel->createMarkup());
    }
    return array('error' => 'Relationship is Undefined.');
}

function newRelationLink(PDO $dbh, $id, $rId, $relCode) {

    $uS = Session::getInstance();

    $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

    if (is_null($rel) === FALSE) {
        $msh = $rel->addRelationship($dbh, $rId, $uS->username);

        $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);
        return array('success' => $msh, 'rc' => $relCode, 'markup' => $rel->createMarkup());
    }

    return array('error' => 'Relationship is Undefined.');
}

function changeLog(PDO $dbh, $id, $get) {

    //require(CLASSES . 'DataTableServer.php');

    $view = 'vaudit_log';

    if (isset($get['vw'])) {
        $view = filter_var($get['vw'], FILTER_SANITIZE_STRING);
    }

    $columns = array(

        array( 'db' => 'LogDate',  'dt' => 'Date' ),
        array( 'db' => 'LogType',   'dt' => 'Type' ),
        array( 'db' => 'Subtype',     'dt' => 'Sub-Type' ),
        array( 'db'  => 'User', 'dt' => 'User' ),
        array( 'db'  => 'idName', 'dt' => 'Id' ),
        array( 'db' => 'LogText', 'dt' => 'Log Text')
    );

    return SSP::complex ( $get, $dbh, $view, 'idName', $columns, null, "idName=$id" );

}

function recentReport(PDO $dbh, $parms, $donationsFlag) {

    // exit on bad dates
    if (isset($parms["sdate"]) === FALSE || $parms["sdate"] == "") {
        return array("success" => "Fill in Start Date");
    }

    $dStart = date("Y-m-d", strtotime(filter_var($parms["sdate"], FILTER_SANITIZE_STRING)));

    if (isset($parms["edate"]) && $parms["edate"] != '') {
        $dEnd = date("Y-m-d 23:59:59", strtotime(filter_var($parms["edate"], FILTER_SANITIZE_STRING)));
    } else {
        $dEnd = date("Y-m-d 23:59:59");
    }

    $incNew = false;
    $incUpd = false;
    $sParms = array();

    if (isset($parms["incnew"])) {
        $incNew = filter_var($parms["incnew"], FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($parms["incupd"])) {
        $incUpd = filter_var($parms["incupd"], FILTER_VALIDATE_BOOLEAN);
    }

    // exit if neither is checked
    if (!$incUpd && !$incNew) {
        return array("success" => "Check 'Include Updates to Existing Members' or 'New'");
    }

//    $members = array();
//    $member = array();
    $newWClause = "";
    $uptWClause = "";

    // set up where clauses
    if ($incNew) {
        if ($dStart != "") {
            $newWClause = " (`Created On` >= :dstart ";
            $sParms[':dstart'] = $dStart;
            if ($dEnd != "") {
                $newWClause .= " and `Created On` <= :dEnd) ";
                $sParms[':dEnd'] = $dEnd;
            } else {
                $newWClause .= ") ";
            }
        }
    }

    if ($incUpd) {
        if ($dStart != "") {
            $uptWClause = " (Last_Updated >= :upstart ";
            $sParms[':upstart'] = $dStart;
            if ($dEnd != "") {
                $uptWClause .= " and Last_Updated <= :upEnd) ";
                $sParms[':upEnd'] = $dEnd;
            } else {
                $uptWClause .= ") ";
            }
        }
    }

    // Combine the where clauses
    if ($newWClause != "" && $uptWClause != "") {
        $whereClause = " (" . $newWClause . " or " . $uptWClause . ") ";
    } else {
        // one of these is empty, or both
        $whereClause = $newWClause . $uptWClause;
    }

    // Exit if no where clause?
    if ($whereClause == "") {
        return array("success" => "Check one of Categories");
    }


    // Create an array to store data in order to orient data to names instead of tables.
    $tableNames = array("[name]", "[address]", "[phone]", "[email]", "[volunteer]", "[web]", "[calendar]", "[donations]");
    $controlNames = array("cbname", "cbaddr", "cbphone", "cbemail", "cbvol", "cbweb", "cbevents", "cbdonations");
    $tables = array("vdump_name", "vdump_address", "vdump_phone", "vdump_email", "vdump_volunteer", "vdump_webuser", "vdump_events", "vdump_donations");
    $names = array();

    // run through checkboxes
    for ($i = 0; $i < count($tableNames); $i++) {

        if (isset($parms[$controlNames[$i]]) && filter_var($parms[$controlNames[$i]], FILTER_VALIDATE_BOOLEAN) === TRUE) {
            if ($tableNames[$i] == "[donations]" && !$donationsFlag) {
                continue;
            }
            $query = "select * from " . $tables[$i] . " where $whereClause;";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute($sParms);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $names = makeTable($rows, $tableNames[$i]);
        }
    }

    $markup = createRecentReportMU($dbh, $names);
    return array("success" => $markup);
}

function makeTable($rows, $tableName) {

    $names = array();

    foreach ($rows as $rw) {

        $names[$rw["Id"]][$tableName][] = $rw;
    }
    return $names;
}

function createRecentReportMU(PDO $dbh, $names) {
    $markup = "";

    // array have data?
    if (empty($names)) {
        return "No Data";
    }

    // header
    $markup .= "<p>" . count($names) . " Members Listed</p><br/>";

    foreach ($names as $id => $data) {
        // get member name
        $stmt = $dbh->prepare("select case when Record_Member = 1 then concat(Name_First,' ',Name_Last)
                else Company end as `name` from name where idName = :id;");
        $stmt->execute(array(':id' => $id));
//        $res = queryDB($dbcon, "select case when Record_Member = 1 then concat(Name_First,' ',Name_Last)
//                else Company end as `name` from name where idName = $id;");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $nameStr = "";
        if (count($rows) > 0) {
            $nameStr = $rows[0][0];
        }

        // member id and name
        $markup .= "<a style='text-decoration:none;' href='NameEdit.php?id=$id'>$id: $nameStr</a><div class='hhk-recent'>";


        foreach ($data as $tname => $rows) {
            $numcols = count($rows[0]);

            $markup .= "<table>";
            $markup .= "<tr><td colspan='$numcols'><span class='hhk-recent-tablenames'>$tname</span></td></tr>";

            // make the column titles
            $markup .= "<tr>";
            foreach ($rows[0] as $title => $val) {
                if ($val != "") {
                    $markup .= "<th>" . $title . "</td>";
                } else {
                    $markup .= "<th></td>";
                }
            }

            $markup .= "</tr><tr>";
            foreach ($rows as $row) {

                $markup .= "<tr>";
                foreach ($row as $title => $val) {
                    $markup .= "<td>" . $val . "</td>";
                }
                $markup .= "</tr>";
            }
            $markup .= "</table>";
        }
        $markup .= "</div>";
    }

    return $markup;
}

function saveUname(PDO $dbh, $vaddr, $role, $id, $status, $fbStatus, $admin, $parms, $maintFlag) {

    $reply = array();

    // fbx table
    $stmt = $dbh->query("select * from fbx where idName=$id;");
    $fbxRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($fbxRows) == 1) {

        if (strtolower($fbxRows[0]["Status"]) != $fbStatus) {

            $stmt = $dbh->prepare("update fbx set Approved_Date=now(), Approved_By=:admin, Status=:stat where idName = $id;");
            $stmt->execute(array(':admin' => $admin, ':stat' => $fbStatus));
        }
    }
    // else we dont care.
    // w_users table
    $usersRS = new W_usersRS();
    $usersRS->idName->setStoredVal($id);
    $userRows = EditRS::select($dbh, $usersRS, array($usersRS->idName));

    if (count($userRows) == 1) {
        EditRS::loadRow($userRows[0], $usersRS);
        // update existing entry

        $usersRS->Status->setNewVal($status);
        $usersRS->Verify_Address->setNewVal($vaddr);
        $usersRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $usersRS->Updated_By->setNewVal($admin);

        $n = EditRS::update($dbh, $usersRS, array($usersRS->idName));

        if ($n == 1) {

            NameLog::writeUpdate($dbh, $usersRS, $id, $admin);
            $reply[] = array("success" => "Update web users.  ");
        }
    } else {
        $reply[] = array("error", "Record not found");
    }



    if ($maintFlag) {

        // update w_auth table with new role
        $authRS = new W_authRS();
        $authRS->idName->setStoredVal($id);
        $authRows = EditRS::select($dbh, $authRS, array($authRS->idName));

        if (count($authRows) == 1) {
            // update existing entry
            EditRS::loadRow($authRows[0], $authRS);

            $authRS->Role_Id->setNewVal($role);

            $authRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $authRS->Updated_By->setNewVal($admin);

            $n = EditRS::update($dbh, $authRS, array($authRS->idName));

            if ($n == 1) {

                NameLog::writeUpdate($dbh, $authRS, $id, $admin);
                $reply[] = array("success" => "Update web authorization.  ");
            }
        } else {
            $reply[] = array("error", "Record not found");
        }


        // Group Code security table
        //$sArray = readGenLookups($dbh, "Group_Code");
        $stmt = $dbh->query("select Group_Code as Code, Description from w_groups");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($groups as $g) {
            $sArray[$g['Code']] = $g;
        }



        $secRS = new Id_SecurityGroupRS();
        $secRS->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $secRS, array($secRS->idName));

        foreach ($rows as $r) {
            $sArray[$r['Group_Code']]["exist"] = "t";
        }

        foreach ($sArray as $g) {

            if (isset($parms["grpSec_" . $g["Code"]])) {

                if (!isset($g["exist"]) && $parms["grpSec_" . $g["Code"]] == "checked") {

                    // new group code to put into the database
                    $secRS = new Id_SecurityGroupRS();
                    $secRS->idName->setNewVal($id);
                    $secRS->Group_Code->setNewVal($g["Code"]);
                    $n = EditRS::insert($dbh, $secRS);

                    NameLog::writeInsert($dbh, $secRS, $id, $admin);

                } else if (isset($g["exist"]) && $parms["grpSec_" . $g["Code"]] != "checked") {

                    // group code to delete from the database.
                    $secRS = new Id_SecurityGroupRS();
                    $secRS->idName->setStoredVal($id);
                    $secRS->Group_Code->setStoredVal($g["Code"]);
                    $n = EditRS::delete($dbh, $secRS, array($secRS->idName, $secRS->Group_Code));

                    if ($n == 1) {
                        NameLog::writeDelete($dbh, $secRS, $id, $admin);
                    }
                }
            }
        }
    }
    return $reply;
}

function AccessLog(\PDO $dbh, $get) {

    $columns = array(

        //array( 'db' => 'id',  'dt' => 'id' ),
        array( 'db' => 'Username',   'dt' => 'Username' ),
        array( 'db' => 'IP',     'dt' => 'IP' ),
        array( 'db'  => 'Action', 'dt' => 'Action' ),
        array( 'db' => 'Access_Date',   'dt' => 'Date' ),
        array( 'db' => 'Browser', 'dt' => 'Browser' ),
        array( 'db' => 'OS', 'dt' => 'OS' )
    );

    return SSP::simple($get, $dbh, "w_user_log", 'Username', $columns);
}

function updateCronJob(\PDO $dbh, $idJob, $interval, $day, $weekday, $hour, $minute, $status){

    $validIntervals = AbstractJob::AllowedIntervals;
    $validStatuses = array('a','d');
    $errors = array();
    if($idJob <= 0){
        $errors[] = "Job ID is invalid";
    }
    if(!in_array($interval, $validIntervals)){
        $errors[] = "Interval must be Hourly, Daily, Weekly or Monthly";
    }

    switch($interval){
        case 'weekly':
            if($day > 7){
                $errors[] = "Day must be Sunday - Saturday";
            }
            break;
        case 'monthly':
            if($day > 31){
                $errors[] = "Day must be 1 - 31";
            }
            break;
    }

    if(!in_array($status, $validStatuses)){
        $errors[] = "Status must be Active or Disabled";
    }

    if(count($errors) == 0){

        switch ($interval){
            case 'hourly':
                $day = '';
                $hour = '';
                break;
            case 'daily':
                $day = '';
                break;
            case 'weekly':
                $day = $weekday;
            default:
                break;
        }

        $cronRS = new CronRS();
        $cronRS->idJob->setStoredVal($idJob);

        $rows = EditRS::select($dbh, $cronRS, array($cronRS->idJob));
        if (count($rows) == 1) {

            EditRS::loadRow($rows[0], $cronRS);

            $cronRS->Interval->setNewVal($interval);
            $cronRS->Day->setNewVal($day);
            $cronRS->Hour->setNewVal($hour);
            $cronRS->Minute->setNewVal($minute);
            $cronRS->Status->setNewVal($status);

            EditRS::update($dbh, $cronRS, array($cronRS->idJob));
            return array("status"=>"success", "msg"=>"Job " . $cronRS->Title->getStoredVal() . " updated successfully", "job"=>array("idJob"=>$cronRS->idJob->getStoredVal(), "Interval"=>$cronRS->Interval->getNewVal(), "Day"=>$cronRS->Day->getNewVal(), "Hour"=>$cronRS->Hour->getNewVal(), "Minute"=>$cronRS->Minute->getNewVal(), "Status"=>$cronRS->Status->getNewVal()));
        }
    }
    return array("error"=>"<strong>Error</strong><br>" . implode("<br>", $errors));
}
