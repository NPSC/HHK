<?php
/**
 * Misc.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

require (DB_TABLES . 'nameRS.php');
require (CLASSES . 'AuditLog.php');
require (THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php');
require CLASSES . 'CreateMarkupFromDB.php';



try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;
//$dbcon = initDB();


$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

$config = new Config_Lite(ciCFG_FILE);
$uname = $uS->username;

addslashesextended($_POST);

function getGenLookups(\PDO $dbh) {
    $stmt = $dbh->query("select distinct Table_Name from gen_lookups;");
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

    $markup = "<option value=''>Select</option>";

    foreach ($rows as $rw) {
        if ($rw[0] != "") {
            $markup .= "<option value='" . $rw[0] . "'>".$rw[0] . "</option>";
        }
    }
    return $markup;
}

function getChangeLog(\PDO $dbh, $naIndex, $stDate = "", $endDate = "") {

    // sanity test for how much data you want
    if ($stDate == "" && $endDate == "" && $naIndex < 1) {
        return "Set a Start date. ";
    }

    $logDates = "";
    $whDates = "";
    $whereName = "";

    if ($stDate != "") {
        $logDates = " and Date_Time >= '$stDate' ";
        $whDates = " and a.Effective_Date >= '$stDate' ";
    }

    if ($endDate != "") {
        $logDates .= " and Date_Time <= '$endDate' ";
        $whDates .= " and a.Effective_Date <= '$endDate' ";
    }

    if ($naIndex == 0) {
        $whereName = "";
    } else {
        $whereName = " and idName = " . $naIndex;
    }


    $query = "SELECT * FROM name_log WHERE 1=1 " . $whereName . $logDates . " order by Date_Time desc limit 100;";

    $result2 = $dbh->query($query);

    $data = "<table id='dataTbl' class='display'><thead><tr>
            <th>Date</th>
            <th>Type</th>
            <th>Sub-Type</th>
            <th>User Id</th>
            <th>Member Id</th>
            <th>Log Text</th></tr></thead><tbody>";

    while ($row2 = $result2->fetch(\PDO::FETCH_ASSOC)) {

        $data .= "<tr>
                <td>" . date("Y-m-d H:i:s", strtotime($row2['Timestamp'])) . "</td>
                <td>" . $row2['Log_Type'] . "</td>
                <td>" . $row2['Sub_Type'] . "</td>
                <td>" . $row2['WP_User_Id'] . "</td>
                <td>" . $row2['idName'] . "</td>
                <td>" . $row2['Log_Text'] . "</td></tr>";
    }


    // activity table has volunteer data
    $query = "select a.idName, a.Effective_Date, a.Action_Codes, a.Other_Code, a.Source_Code, g.Description as Code, g2.Description as Category, ifnull(g3.Description, '') as Rank
from activity a left join gen_lookups g on substring_index(Product_Code, '|', 1) = g.Table_Name and  substring_index(Product_Code, '|', -1) = g.Code
left join gen_lookups g2 on g2.Table_Name = 'Vol_Category' and substring_index(Product_Code, '|', 1) = g2.Code
left join gen_lookups g3 on g3.Table_Name = 'Vol_Rank' and g3.Code = a.Other_Code
        where a.Type = 'vol' $whereName $whDates order by a.Effective_Date desc limit 100;";

    $result3 = $dbh->query($query);

    while ($row2 = $result3->fetch(\PDO::FETCH_ASSOC)) {

        $data .= "<tr>
                <td>" . date("Y-m-d H:i:s", strtotime($row2['Effective_Date'])) . "</td>
                <td>Volunteer</td>
                <td>" . $row2['Action_Codes'] . "</td>
                <td>" . $row2['Source_Code'] . "</td>
                <td>" . $row2['idName'] . "</td>
                <td>" . $row2['Category'] . "/" . $row2["Code"] . ", Rank = " . $row2["Rank"] . "</td></tr>";
    }


    return $data . "</tbody></table>";
}

//
// catch service calls
if (isset($_POST["table"])) {

    $tableName = substr(filter_var($_POST["table"], FILTER_SANITIZE_STRING), 0, 45);

    $res = $dbh->query("Select Code, Description, Substitute from gen_lookups where Table_Name='" . $tableName . "'");

    $code = array(
        "Code" => "",
        "Description" => "",
        "Substitute" => ""
    );

    $tabl = array();
    while ($rw = $res->fetch(\PDO::FETCH_NUM)) {

        $code["Code"] = $rw[0];
        $code["Description"] = $rw[1];
        $code["Substitute"] = $rw[2];
        $tabl[] = $code;
    }

    echo( json_encode($tabl));
    exit();
}

if (isset($_POST["cmd"])) {

    if ($_POST["cmd"] == "move") {

        $list = arrayify(filter_var_array($_POST["list"]));
        $query = "";

        foreach ($list as $item) {

            if ($item["donToId"] > 0) {
                $query .= " call sp_move_donation (" . $item["donToId"] . ", " . $item["delId"] . ", '$uname'); ";
            }
        }

        if ($query != "") {

        } else {
            $rtrn = array("success" => "But nothing was updated");
            echo( json_encode($rtrn));
            exit();
        }
    }

    $rtrn = array("error" => "bad command");
    echo( json_encode($rtrn));
    exit();
}
// End of service calls
//
//
//
//$menuMarkup = $page->generateMenu($dbcon, $uS, $testHeader);

$lookupErrMsg = "";

// Maintain the accordian index accross posts
$accordIndex = 0;
$cookieReply = '';

if (isset($_COOKIE['housepc'])) {
    $cookieReply = "Access is set on this PC" . $_COOKIE['housepc']['value'];
}

if (isset($_POST['setCookie'])) {
    $accordIndex = 7;
    $sites = $uS->siteList;

    $cookVal = encryptMessage($_SERVER['REMOTE_ADDR'] . 'eric');

    if (SecurityComponent::is_TheAdmin()) {
        if ( setcookie('volpc', $cookVal,0, $sites[WebSiteCode::Volunteer]['Relative_Address'], $sites[WebSiteCode::Volunteer]['HTTP_Host']) ) {
            $cookieReply = "Access Set.  PC should work now.";
        }
        setcookie('housepc', $cookVal, 0, $sites[WebSiteCode::House]['Relative_Address'], $sites[WebSiteCode::House]['HTTP_Host']);
    } else {
        $cookieReply = "Must be logged in as THE admin to set access.";
    }

} else if (isset($_POST['removeCookie'])) {
    $accordIndex = 7;
    $sites = $uS->siteList;

    if ( setcookie('housepc', "", time() - 3600, $sites['v']['Relative_Address'], $sites['v']['HTTP_Host']) ) {
        $cookieReply = "Access Deleted.";
    }

}

// Check for Gen_Lookups post
if (isset($_POST["btnGenLookups"])) {

    $accordIndex = 0;
    $lookUpAlert = new alertMessage("lookUpAlert");
    $lookUpAlert->set_Context(alertMessage::Alert);

    $code = filter_var($_POST["txtCode"], FILTER_SANITIZE_STRING);
    //$code = substr($code, 0, $flen["Code"]);
    $desc = filter_var($_POST["txtDesc"], FILTER_SANITIZE_STRING);
    //$desc = substr($desc, 0, $flen["Description"]);
    $subt = filter_var($_POST["txtAddl"], FILTER_SANITIZE_STRING);
    //$subt = substr($subt, 0, $flen["Substitute"]);
    $selTbl = filter_var($_POST["selLookup"], FILTER_SANITIZE_STRING);
    //$selTbl = substr($selTbl, 0, $flen["Table_Name"]);
    $selCode = filter_var($_POST["selCode"], FILTER_SANITIZE_STRING);


    if ($selTbl == "") {
        $lookUpAlert->set_Text("The Table_Name must be filled in");
    } else if ($code == "") {
        $lookUpAlert->set_Text("The Code must be filled in");
    } else if ($desc != "") {

        // Is the table_name there?

        $res = $dbh->query("select count(*) from gen_lookups where Table_Name='" . $selTbl . "';");
        $rows = $res->fetchAll(PDO::FETCH_NUM);

        if ($rows[0][0] == 0) {
            $lookUpAlert->set_Text("That Table_Name does not exist.");
        } else {

            // Is the Code there?
            $query = "select count(*) from gen_lookups where Table_Name='" . $selTbl . "' and Code='" . $code . "';";
            $res1 = $dbh->query($query);
            $row = $res1->fetchAll(PDO::FETCH_NUM);

            if ($row[0][0] == 0 && $selCode == "n_$") {
                // add a new code with desc.
                $query = "insert into gen_lookups (Table_Name, Code, Description, Substitute) values ('" . $selTbl . "', '" . $code . "', '" . $desc . "', '" . $subt . "');";
            } else if ($row[0][0] > 0 && $selCode != "n_$") {
                // just update the description
                $query = "update gen_lookups set Description='" . $desc . "', Substitute='" . $subt . "' where Table_Name='" . $selTbl . "' and Code='" . $code . "';";
            } else {
                $lookUpAlert->set_Text("sorry, don't understand (been a long day)");
            }

            if ($query != "") {
                $dbh->exec($query);
                $lookUpAlert->set_Context(alertMessage::Success);
                $lookUpAlert->set_Text("Okay");
            }
        }
    }
    $lookupErrMsg = $lookUpAlert->createMarkup();
}

/*
 * Change Log Output
 */
$markup = "";
if (isset($_POST["btnGenLog"])) {
    $accordIndex = 3;
    $sDate = filter_var($_POST["sdate"], FILTER_SANITIZE_STRING);
    if ($sDate != '') {
        $sDate = date("Y-m-d", strtotime($sDate));
    }
    $eDate = filter_var($_POST["edate"], FILTER_SANITIZE_STRING);
    if ($eDate != '') {
        $eDate = date("Y-m-d 23:59:59", strtotime($eDate));
    }

    $markup = getChangeLog($dbh, 0, $sDate, $eDate);

}

$cleanMsg = '';
if (isset($_POST['btnClnPhone'])) {
    // Clean up phone numbers
    $accordIndex = 1;
    $stmt = $dbh->query("select idName, Phone_Code, Phone_Num from name_phone where Phone_Num <> '';");
    $n = 0;

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $new = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $r['Phone_Num']);

        $srch = str_replace('(', '', str_replace(')', '', str_replace('-', '', str_replace(' ', '', $new))));

        $n += $dbh->exec("update name_phone set Phone_Num = '$new', Phone_Search = '$srch' where idName = " . $r['idName'] . " and Phone_Code='".$r['Phone_Code']."'");
    }

    $cleanMsg = $n . " phone records cleaned.";
}

// CLean names
if (isset($_POST['btnClnNames'])) {
    // Clean up phone numbers
    $accordIndex = 1;
    $stmt = $dbh->query("select * from `name` where idName > 0 and Record_Member = 1 and Name_Last <> '';");
    $c = 0;

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        if ($r['Name_Last'] != ucfirst(strtolower($r['Name_Last'])) || $r['Name_First'] != ucfirst(strtolower($r['Name_First']))) {

            $n = new NameRS();
            EditRS::loadRow($r, $n);

            $n->Name_First->setNewVal(ucfirst(strtolower($n->Name_First->getStoredVal())));
            $n->Name_Last->setNewVal(ucfirst(strtolower($n->Name_Last->getStoredVal())));
            $n->Name_Middle->setNewVal(ucfirst(strtolower($n->Name_Middle->getStoredVal())));
            $n->Name_Nickname->setNewVal(ucfirst(strtolower($n->Name_Nickname->getStoredVal())));
            $n->Name_Previous->setNewVal(ucfirst(strtolower($n->Name_Previous->getStoredVal())));

            // Name Last-First
            if ($n->Name_First->getNewVal() != '') {
                $first = ', ' . $n->Name_First->getNewVal();
            } else {
                $first = '';
            }
            $n->Name_Last_First->setNewVal($n->Name_Last->getNewVal() . $first);


            // Name Full
            $prefix = '';
            $suffix = '';
            $qstring = '';
            if (isset($uS->nameLookups[GL_TableNames::NamePrefix][$n->Name_Prefix->getNewVal()])) {
                $prefix = $uS->nameLookups[GL_TableNames::NamePrefix][$n->Name_Prefix->getNewVal()][Member::DESC];
            }
            if (isset($uS->nameLookups[GL_TableNames::NameSuffix][$n->Name_Suffix->getNewVal()])) {
                $suffix = $uS->nameLookups[GL_TableNames::NameSuffix][$n->Name_Suffix->getNewVal()][Member::DESC];
            }

            if ($n->Name_Middle->getNewVal() != "") {
                $qstring .= trim($prefix . " " . $n->Name_First->getNewVal() . " " . $n->Name_Middle->getNewVal() . " " . $n->Name_Last->getNewVal() . " " . $suffix);
            } else {
                $qstring .= trim($prefix . " " . $n->Name_First->getNewVal() . " " . $n->Name_Last->getNewVal() . " " . $suffix);
            }
            $n->Name_Full->setNewVal($qstring);


            $n->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $n->Updated_By->setNewVal($uS->username);

            // Update existing member
            $numRows = EditRS::update($dbh, $n, array($n->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $n, $n->idName->getStoredVal(), $uS->username);
                EditRS::updateStoredVals($n);
                $c++;
                $cleanMsg .= $r['Name_Last'] . ' = ' . $n->Name_Last->getStoredVal() . "<br/>";
            }
        }

    }

    $cleanMsg .= $c . " name records cleaned.";
}


// Database backup on demand
$bkupMsg = "";
$ignoreTables = array(
    1 => 'postal_codes',
    2 => 'street_suffix',
    3 => 'secondary_unit_desig',
    4 => 'mail_listing',
    5 => 'member_history',
);


$to = $config->get('backup', 'BackupEmailAddr', '');

if (isset($_POST["btnDoBackup"])) {

    $accordIndex = 2;
    $bkupAlert = new alertMessage("bkupAlert");
    $bkupAlert->set_Context(alertMessage::Alert);

    $siteName = $config->get('site', 'Site_Name', 'Hospitality HouseKeeper');
    $from = $config->get('backup', 'FromEmailAddr', '');      // Email address message will show as coming from.

    $dbuser = $config->get('backup', 'BackupUser', '');
    $dbpwd = $config->get('backup', 'BackupPassword', '');

    $dbConfig = $config->getSection('db');

    if (is_array($dbConfig)) {
        $dbUrl = $dbConfig['URL'];
        $dbname = $dbConfig['Schema'];
    }

    if ($dbuser == '' || $dbpwd == '') {
        $bkupMsg = $bkupAlert->createMarkup('Backup username or password is missing.');
    } else {

        $dbpwd = decryptMessage($dbpwd);

        $datestamp = date("Y_m_d");

        $filename = REL_BASE_DIR . 'patch' . DS . 'backup.sql.gz';

        // ignore tables
        $igtables = '';
        foreach ($ignoreTables as $t) {
            $igtables = " --ignore-table=$dbname.$t";
        }

        $return_var = '';

        // Backup database
        $command = "mysqldump  --host=$dbUrl --skip-lock-tables --single-transaction $igtables --user=$dbuser --password='$dbpwd' $dbname | gzip > $filename";
        passthru($command, $return_var);


        //$attachmentname = array_pop(explode("/", $filename));   // If a path was included, strip it out for the attachment name
        $attachmentname = 'backup_' . $datestamp . '.sql.gz';
        $message = "Compressed database backup file $attachmentname attached.\r\n\r\n";

        $mail = prepareEmail($config);

        $mail->From = $from;
        $mail->FromName = $config->getString('site', 'Site_Name', 'Hospitality HouseKeeper');
        $mail->addAddress($to);     // Add a recipient
        $mail->Subject = $siteName . " DB backup file - " . $datestamp;
        $mail->msgHTML($message);
        $mail->addAttachment($filename, $attachmentname, 'base64', '', 'attachment');
        $mail->send();


        $bkupMsg = $bkupAlert->createMarkup('Result: ' . $return_var . ";  Email Error: " . $mail->ErrorInfo);
    }
}

/*
 *  Delete Name records.
 */
$delIdListing = "";


$res = $dbh->query("select idName from name where name.Member_Status = 'u' || name.Member_Status = 'TBD';");

    while ($r = $res->fetch(\PDO::FETCH_ASSOC)) {
        $delIdListing .= "<a href='NameEdit.php?id=" . $r[0] . "'>" . $r[0] . "</a> ";
    }

if ($delIdListing == "") {
    $delIdListing = "No records.";
}

$ids = "";
$total = 0;
$numStays = 0;
$stayIds = '';
$donMoveNames = "";

// Check for existing donation records
$query = "select d.Donor_Id, sum(d.Amount), n.Name_Last_First from donations d left join name n on d.Donor_Id = n.idName where d.Status='a' and (n.Member_Status = 'u' or n.Member_Status = 'TBD') group by d.Donor_Id;";
$res = $dbh->query($query);


    while ($r = $res->fetch(\PDO::FETCH_ASSOC)) {
        $donMoveNames .= "<tr><td>($r[0]) $r[2]</td><td class='tdBox'><input type='text' id='t_$r[0]' name='$r[0]' size='5' class='srchChars' title='Enter at least 3 characters to invoke search' />
          <select id='s_$r[0]' name='$r[0]' class='Selector'><option value='0'></option></select></td></tr>";
        $ids .= $r[0] . ",  ";
        $total += $r[1];
    }

// Check for existing stays
$staysStmt = $dbh->query("select n.idName, n.Name_Last_First from stays s left join name n on n.idName = s.idName where (n.Member_Status = 'u' or n.Member_Status = 'TBD') group by s.idName");
   while ($r = $staysStmt->fetch(\PDO::FETCH_ASSOC)) {
       $stayIds .= $r['idName'] . ', ';
       $numStays++;
   }

$delNamesMsg = "";
if (isset($_POST["btnDelDups"])) {
    $delDupsAlert = new alertMessage("delDupsAlert");
    $accordIndex = 4;

    // check for damage...
    if ($total > 0) {
        $delDupsAlert->set_Context(alertMessage::Alert);
        $delDupsAlert->set_Text("Donations Exist!  Names not deleted.  Id's with existing donations are: " . $ids . "  For a total amount of $" . $total);
    } else if ($numStays > 0) {
        $delDupsAlert->set_Context(alertMessage::Alert);
        $delDupsAlert->set_Text("Visits exist! Names not deleted. Ids with existing stays are: " . $stayIds);

    } else {

        // delete the name and associated records.
        $query = "call delete_names_u_tbd;";
        $res = $dbh->exec($query);

        $delDupsAlert->set_Context(alertMessage::Success);
        $delDupsAlert->set_Text("Oday.  Uh-oh, I must have a dold.");
    }
    $delNamesMsg = $delDupsAlert->createMarkup();
}

function readErrorFile($fname) {

    if (file_exists($fname)) {
        $contents = nl2br(file_get_contents($fname));
    } else {
        $contents = '<span>Error Log is Empty.</span>';
    }

    return $contents;
}

/*
 *  List Errors
 */
$contents = "";
if (isset($_POST["btnSvrErrors"])) {

    $accordIndex = 5;
    $volSiteURL = $config->getString("site", 'Volunteer_URL', '');
    $houseSiteUrl = $config->getString("site", 'House_URL', '');

    // Admin errors
    $contents = "<h3>Administration Site Error Log</h3>";
    $contents .= readErrorFile('error_log');

    // House errors
    if ($houseSiteUrl != '') {
        $contents .= "<h3>House Site Error Log</h3>";
        $contents .= readErrorFile('../house/error_log');
    }

    // Volunteer errors
    if ($volSiteURL != '') {
        $contents .= "<h3>Volunteer Site Error Log</h3>";
        $contents .= readErrorFile('../volunteer/error_log');
    }

}

// Delete Server Error Logs
if (isset($_POST["btnDelErrors"])) {

    $accordIndex = 5;
    $contents = '';

    if (file_exists('error_log')) {
        unlink('error_log');
        $contents .= 'Admin Error File Deleted.';
    }

    if (file_exists('../house/error_log')) {
        unlink('../house/error_log');
        $contents .= 'House Error File Deleted.';
    }

    if (file_exists('../volunteer/error_log')) {
        unlink('../volunteer/error_log');
        $contents .= 'Volunteer Error File Deleted.';
    }

}

// View user log
$log = '';
$users = array();
$userNameDate = '';

if (isset($_POST['btnAccess'])) {
    $accordIndex = 6;

    $whereStr = '';
    $dte = filter_var($_POST['aclogdate'], FILTER_SANITIZE_STRING);

    if ($dte != '') {
        $userNameDate = date('M j, Y', strtotime($dte));
        $whereStr = " DATE(Access_Date) = DATE('" . date('Y-m-d', strtotime($dte)) . "') ";
    }

    $userStr = '';

    if (isset($_POST['selUsers'])) {

        $postUsers = filter_var_array($_POST['selUsers']);

        foreach ($postUsers as $u) {
            $userStr .= ($userStr == '' ? "'" : ",'") . $u . "'";
            $users[$u] = $u;
        }

        if ($userStr != '') {
            $userStr = " w.idName in (" . $userStr . ")";
        }
    }

    if ($whereStr != '' && $userStr != '') {
        $whereStr = " where " . $whereStr . ' and ' . $userStr;
    } else if ($whereStr != '' && $userStr == '') {
        $whereStr = " where " . $whereStr;
    } else if ($whereStr == '' && $userStr != '') {
        $whereStr = "where " . $userStr;
    }

    $stmt = $dbh->query("Select w.idName as Id, l.* from w_user_log l left join w_users w on l.Username = w.User_Name $whereStr order by Access_Date DESC Limit 100;");

    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $edRows = array();

    foreach ($rows as $r) {

        $r['Date'] = date('M j, Y H:i:s', strtotime($r['Access_Date']));

        unset($r['Session_Id']);
        unset($r['Access_Date']);
        unset($r['Page']);

        $edRows[] = $r;
    }

    $log = CreateMarkupFromDB::generateHTML_Table($edRows, 'userlog');

}

$usernames = HTMLSelector::generateMarkup(HTMLSelector::getLookups($dbh, "select idName, User_Name from w_users", $users), array('name'=>'selUsers[]', 'multiple'=>'multiple', 'size'=>'5'));


$webAlert = new alertMessage("webContainer");
$webAlert->set_DisplayAttr("none");
$webAlert->set_Context(alertMessage::Success);
$webAlert->set_iconId("webIcon");
$webAlert->set_styleId("webResponse");
$webAlert->set_txtSpanId("webMessage");
$webAlert->set_Text("oh-oh");

$getWebReplyMessage = $webAlert->createMarkup();


$selLookups = getGenLookups($dbh);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_DT_CSS; ?>" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript">
            var table, accordIndex;
            $(document).ready(function() {
                table = new Object();
                accordIndex = <?php echo $accordIndex; ?>;
                $.ajaxSetup ({
                    beforeSend: function() {
                        //$('#loader').show()
                        $('body').css('cursor', "wait");
                    },
                    complete: function(){
                        $('body').css('cursor', "auto");
                        //$('#loader').hide()
                    },
                    cache: false
                });
                $('#accordion').tabs();
                $( '#accordion' ).tabs("option", "active", accordIndex);
                if (accordIndex == 3){
                    $('#dataTbl').dataTable({
                        "iDisplayLength": 50,
                        "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                        "aoColumnDefs": [
                            { "sWidth": "121px", "aTargets": [ 0 ] }
                        ]
                        , "sDom": '<"top"ilfp>rt<"bottom"p>'
                    });
                }
                $('#divMoveDon').dialog({
                    autoOpen: false,
                    width: 550,
                    resizable: true,
                    modal: true,
                    buttons: {
                        "Move Donations": function() {
                            doMoveDon();

                        },
                        "Exit": function() {
                            $( this ).dialog( "close" );
                        }
                    },
                    close: function() {
                    }
                })
                $( "input.autoCal" ).datepicker({
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    dateFormat: 'M d, yy'
                });
                $('#selLookup').change( function() {
                    $.ajax(
                    { type: "POST",
                        url: "Misc.php",
                        data: ({
                            table: $("#selLookup").val(),
                            cmd: "get"
                        }),
                        success: handleResponse,
                        error: handleError,
                        datatype: "json"
                    });
                });
                $('#selCode').change( function() {
                    if (table) {
                        for (code in table) {

                            if (table[code].Code == this.value) {
                                $('#txtCode').val(this.value).prop("readonly", true);
                                $('#txtDesc').val(table[code].Description);
                                $('#txtAddl').val(table[code].Substitute);
                            }
                        }
                    }
                });
                $('.srchChars').keyup( function() {
                    mm = $(this).val();
                    if (mm.length > 2) {
                        id = $(this).attr('name');
                        var slectr = 's_'+id;
                        getNames($(this), slectr, 'm', 0);
                    }
                });
                $('#btnMoveDon').click( function () {
                    $('#divMoveDon').dialog({ title: 'Edit Event' });
                    $('#divMoveDon').dialog( 'open' );
                });
                $('#accordion').show();
            });
            function doMoveDon() {
                // Command the server to move donations from one name id to another.
                var ids = new Array();
                var indx = 0;
                $('.Selector').each( function () {
                    if ($(this).val() > 0) {
                        // live one
                        ids[indx++] = new movePair($(this).attr("name"), $(this).val() );
                    }
                });
                // did we capture some live ones
                if (indx > 0) {
                    $.ajax(
                    { type: "POST",
                        url: "Misc.php",
                        data: ({
                            list: ids,
                            cmd: "move"
                        }),
                        success: function(data, statusTxt, xhrObject) {
                            if (statusTxt != "success")
                                alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);

                            var spn = document.getElementById('webMessage');

                            if (data) {
                                data = $.parseJSON(data);
                                if (data.error) {
                                    // define the err message markup
                                    $('webResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
                                    //$('#webContainer').attr("style", "display:block;");
                                    $('#webIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
                                    spn.innerHTML = "<strong>Error: </strong>"+data.error;
                                    $( "#webContainer" ).show( "slide", {}, 200);

                                }
                                else if (data.success) {
                                    // define the  message markup
                                    $('#webResponse').removeClass("ui-state-error").addClass("ui-state-highlight")
                                    //$('#webContainer').attr("style", "display:block;");
                                    $('#webIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
                                    spn.innerHTML = "Okay: "+data.success;
                                    $( "#webContainer" ).show( "slide", {}, 200);
                                }
                            }
                        },
                        error: handleError,
                        datatype: "json"
                    });

                }
            }
            function movePair(delId, donToId) {
                this.delId = delId;
                this.donToId = donToId;
            }
            function handleResponse(dataTxt, statusTxt, xhrObject) {
                if (statusTxt != "success")
                    alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);

                if (dataTxt.length > 0) {
                    table = $.parseJSON(dataTxt);
                    showTable(table);
                }
            }

            function handleError(xhrObject, stat, thrwnError) {
                alert("Server error: " + stat + ", " + thrwnError);
            }
            // Search for names, place any found into the appropiriate selector
            function getNames(ctrl, slectr, code, lid) {
                if (ctrl && ctrl.val() != "") {
                    inpt = {
                        cmd: "srrel",
                        letters: ctrl.val(),
                        basis: code,
                        id: lid
                    };
                    // set the wait cursor
                    $('body').css('cursor', 'wait');

                    $.get( "liveNameSearch.php",
                    inpt,
                    function(data){
                        $('body').css('cursor', 'auto');
                        if (data) {

                            names = $.parseJSON(data);
                            if (names && names.length > 0) {
                                if (names[0].error) {
                                    alert("Server error: " + names[0].error);
                                }
                                else {
                                    sel = $('#' + slectr);
                                    sel.children().remove();

                                    if (names[0].id != 0) {
                                        if (names.length ==1)
                                            optText = "<option value=''>Retrieved "+names.length+" Name</option>";
                                        else
                                            optText = "<option value=''>Retrieved "+names.length+" Names</option>";

                                        sel.append(optText);
                                    }
                                    for(var x=0; x < names.length; x++) {
                                        evt = names[x];
                                        if (evt.name) {
                                            optText = "<option value='" + evt.id + "'>(" + evt.id + ") " + evt.name+"</option>";
                                            sel.append(optText);
                                        }
                                    }
                                }
                            }
                            else {
                                alert('Bad Data');
                            }
                        }
                        else {
                            alert('Nothing was returned from the server');
                        };
                    });
                }
            }

            function showTable(data) {
                // remove any previous entries
                $('#selCode').children().remove();

                // first option is "New"
                var objOption = document.createElement("option");

                objOption.text = "New";
                objOption.value = "n_$";

                objOption.setAttribute("selected", "selected");
                $('#selCode').append(objOption);

                for(var x=0; x < data.length; x++) {
                    var tbl = data[x];
                    objOption = document.createElement("option");

                    objOption.text = tbl.Description;
                    objOption.value = tbl.Code;
                    $('#selCode').append(objOption);
                }
                // clear the other text boxes
                $('#txtCode').val('').prop("disabled", false);
                $('#txtDesc').val('');
                $('#txtAddl').val('');
            }
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <form action="Misc.php" method="post" id="frmLookups" name="frmLookups">
                <div id="accordion" class="hhk-member-detail" style="display:none;">
                    <ul>
                        <li><a href="#lookups">Lookups</a></li>
                        <li><a href="#clean">Clean Data</a></li>
                        <li><a href="#backup">Backup Database</a></li>
                        <li><a href="#changlog">View Change Log</a></li>
                        <li><a href="#delid">Delete Member Records</a></li>
                        <li><a href="#errors">View Server Errors</a></li>
                        <li><a href="#access">View User Access Log</a></li>
                        <li><a href="#setCook">Set House PC</a></li>
                    </ul>
                    <div id="access" class="ui-tabs-hide">
                        <table>
                            <tr>
                                <td class="tdlabel">Choose a date (leave empty for most recent entries):</td>
                                <td><input type="text" id ="aclogdate" class="autoCal" name="aclogdate" VALUE='<?php echo $userNameDate; ?>' /></td>
                            </tr>
                            <tr>
                                <td class="tdlabel">Choose one or more usernames:</td>
                                <td><?php echo $usernames; ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" style="text-align: right;"><input name="btnAccess" type="submit" value="View Access Log"/></td>
                            </tr>
                        </table>

                        <div style="margin-top:10px;">
                            <?php echo $log; ?>
                        </div>
                    </div>

                    <div id="setCook" class="ui-tabs-hide">
                        This sets or removes access on this PC that you are using now.<br/>
                        <input name="setCookie" type="submit" value="Set House PC Access"/><input name="removeCookie" type="submit" value="Remove Access"/>
                        <h3><?php echo $cookieReply; ?></h3>
                    </div>
                    <div id="lookups" class="ui-tabs-hide" >
                        <table>
                            <tr>
                                <td colspan="3" style="background-color: transparent;"><h3>Data Lookup Values</h3></td>
                            </tr>
                            <tr>
                                <th colspan="2">Heading</th>
                                <th style="width:140px;">Description</th>
                            </tr>
                            <tr>
                                <td colspan="2"><select name="selLookup" id="selLookup" ><?php echo $selLookups ?></select></td>
                                <td><select name ="selCode" id="selCode" ></select></td>
                            </tr>
                            <tr style="margin-top: 5px;">
                                <td></td><td>Edit Values</td><td></td>
                            </tr>
                            <tr>
                                <td colspan="1" class="tdlabel">Code: </td>
                                <td colspan="2"><input type="text" name="txtCode" id="txtCode" size="10" /></td>
                            </tr>
                            <tr>
                                <td class="tdlabel">Description: </td>
                                <td colspan="2"><input type="text" name="txtDesc" id="txtDesc" style="width:100%;"/></td>
                            </tr>
                            <tr>
                                <td class="tdlabel">Additional: </td>
                                <td colspan="2"><input type="text" name="txtAddl" id="txtAddl"  style="width:100%;"/></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align:right;"><input type="submit" name="btnGenLookups" value="Save" /></td>

                            </tr>
                            <tr>
                                <td colspan="3"><span id="genErrorMessage" ><?php echo $lookupErrMsg; ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div id="backup" class="ui-tabs-hide" >
                        <table>
                            <tr>
                                <td><h3>Backup Database</h3></td>
                            </tr>
                            <tr>
                                <td>Email Address:
                                    <input type="text" id ="eAddr" name="eAddr" VALUE='<?php echo $to; ?>' size="28" disabled="disabled" />
                                </td>
                            <tr>
                                <td style="text-align:right;"><input type="submit" name="btnDoBackup" value="Run Database Backup"/></td>
                            </tr>
                            <tr>
                                <td><?php echo $bkupMsg; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div id="changlog" class="ui-tabs-hide" >
                        <table>
                            <tr><td colspan="2" style="background-color: transparent;"><h3>Change Log</h3>
                                </td></tr>
                            <tr>
                                <td>Starting:
                                    <input type="text" id ="sdate" class="autoCal" name="sdate" VALUE='' />
                                </td>
                                <td>Ending:
                                    <INPUT TYPE='text' NAME='edate' id="edate" class="autoCal"  VALUE='' />
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="text-align:right;"><input type="submit" name="btnGenLog" value="Run Log Report"/></td>
                            </tr>
                        </table>
                        <div id="divMkup" style="margin-top: 10px;">
<?php echo $markup; ?>
                        </div>
                    </div>
                    <div id="delid" class="ui-tabs-hide" >
                        <table>
                            <tr><td style="background-color: transparent;"><h3>Delete Member Records</h3></td></tr>
                            <tr>
                                <td>
                                    <p>Deletes Name Records and all connected records including phone, address and email.  Before you do this, reassign all donations to appropriate surviving members.</p>
                                    <p>Deletes only those records marked as 'Duplicate' and 'To Be Deleted' for member-status.  There is no way to undo this without retrieving a backup copy of the database.</p>
                                </td></tr>
                            <tr>
                                <td>
                                    These are the records marked for deletion:
                                </td>
                            </tr>
                            <tr>
                                <td>
<?php echo $delIdListing ?>
                                </td>
                            </tr>
                            <tr>
                                <td ><input type="submit" name="btnDelDups"  value="Delete Name Records"/></td>
                            </tr>
                            <tr>
                                <td><?php echo $delNamesMsg; ?></td>
                            </tr>
                            <tr>
                                <td><input type="button" id="btnMoveDon" value="Move Donations"/></td>
                            </tr>
                        </table>
                    </div>
                    <div id="errors" class="ui-tabs-hide" >
                        <table>
                            <tr><td colspan="2" style="background-color: transparent;"><h3>View Server Error Files</h3></td></tr>
                            <tr>
                                <td style="text-align:right;">
                                    <input type="submit" name="btnSvrErrors" value="View Error Logs"/>
                                    <input type="submit" name="btnDelErrors" value="Delete Error Logs" style="margin-left:40px;"/>
                                </td>
                            </tr>
                        </table>
                            <?php echo $contents; ?>
                    </div>
                    <div id="clean" class="ui-tabs-hide" >
                        <table>
                            <tr><td colspan="2" style="background-color: transparent;"><h3>Clean Data</h3></td></tr>
                            <tr>
                                <td style="text-align:right;">
                                    <input type="submit" name="btnClnPhone" value="Clean up Phone Numbers"/>
                                    <input type="submit" name="btnAddrs" value="Verify Addresses" style="margin-left:10px;"/>
                                    <input type="submit" name="btnClnNames" value="Clean Names" style="margin-left:10px;"/>
                                </td>
                            </tr>
                        </table>
                            <?php echo $cleanMsg; ?>
                    </div>
                </div>
            </form>
            <div id="divMoveDon">
                <h3>Move Donations to Active Members</h3>
                <table>
                    <tr>
                        <th>To Be deleted</th><th>Move Donations To:</th>
                    </tr>
<?php echo $donMoveNames; ?>
                    <tr><td colspan="2"> <?php echo $getWebReplyMessage; ?></td></tr>
                </table>
            </div>
        </div>
    </body>
</html>
