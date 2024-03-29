<?php

use HHK\SysConst\VisitStatus;
use HHK\Update\SiteLog;
use HHK\AlertControl\AlertMessage;
use HHK\AuditLog\NameLog;
use HHK\sec\{
    Session,
    WebInit
};
use HHK\SysConst\GLTableNames;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameRS;
use HHK\Admin\SiteDbBackup;
use HHK\Member\AbstractMember;
use HHK\SysConst\CodeVersion;

/**
 * Misc.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

$uname = $uS->username;

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

    $whereName = ($naIndex == 0) ? "" : " and idName = " . $naIndex;

    $result2 = $dbh->query("SELECT * FROM name_log WHERE 1=1 " . $whereName . $logDates . " order by Date_Time desc limit 200;");

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
if (filter_has_var(INPUT_POST, "table")) {

    $tableName = substr(filter_var($_POST["table"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 0, 45);

    $res = $dbh->query("Select Code, Description, Substitute from gen_lookups where Table_Name='" . $tableName . "';");

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

$lookupErrMsg = "";

// Maintain the accordian index accross posts
$accordIndex = 0;

// Check for Gen_Lookups post
if (isset($_POST["btnGenLookups"])) {

    $accordIndex = 0;
    $lookUpAlert = new AlertMessage("lookUpAlert");
    $lookUpAlert->set_Context(AlertMessage::Alert);

    if ($wInit->page->is_TheAdmin() == FALSE) {
        $lookUpAlert->set_Text("Don't mess with these settings.  ");
    } else {

        $code = filter_var($_POST["txtCode"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $desc = filter_var($_POST["txtDesc"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $subt = filter_var($_POST["txtAddl"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $selTbl = filter_var($_POST["selLookup"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $selCode = filter_var($_POST["selCode"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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
                    $lookUpAlert->set_Context(AlertMessage::Success);
                    $lookUpAlert->set_Text("Okay");
                }
            }
        }
    }
    $lookupErrMsg = $lookUpAlert->createMarkup();
}

/*
 * Change Log Output
 */
$chgLogMkup = "";
if (isset($_POST["btnGenLog"])) {
    $accordIndex = 3;
    $sDate = filter_var($_POST["sdate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($sDate != '') {
        $sDate = date("Y-m-d", strtotime($sDate));
    }
    $eDate = filter_var($_POST["edate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($eDate != '') {
        $eDate = date("Y-m-d 23:59:59", strtotime($eDate));
    }

    $chgLogMkup = getChangeLog($dbh, 0, $sDate, $eDate);
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

        $n += $dbh->exec("update name_phone set Phone_Num = '$new', Phone_Search = '$srch' where idName = " . $r['idName'] . " and Phone_Code='" . $r['Phone_Code'] . "'");
    }

    $cleanMsg = $n . " phone records cleaned.";
}

// CLean names
if (isset($_POST['btnClnNames'])) {
    // Clean up
    $accordIndex = 1;
    $stmt = $dbh->query("select * from `name` where idName > 0 and Record_Member = 1 and Name_Last <> '';");
    $c = 0;

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        if ($r['Name_Last'] != ucfirst(strtolower($r['Name_Last'])) || $r['Name_First'] != ucfirst(strtolower($r['Name_First']))) {

            $n = new NameRS();
            EditRS::loadRow($r, $n);

            $n->Name_First->setNewVal(ucfirst(strtolower($n->Name_First->getStoredVal())));
            //$n->Name_Last->setNewVal(ucfirst(strtolower($n->Name_Last->getStoredVal())));
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
            if (isset($uS->nameLookups[GLTableNames::NamePrefix][$n->Name_Prefix->getNewVal()])) {
                $prefix = $uS->nameLookups[GLTableNames::NamePrefix][$n->Name_Prefix->getNewVal()][AbstractMember::DESC];
            }
            if (isset($uS->nameLookups[GLTableNames::NameSuffix][$n->Name_Suffix->getNewVal()])) {
                $suffix = $uS->nameLookups[GLTableNames::NameSuffix][$n->Name_Suffix->getNewVal()][AbstractMember::DESC];
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
$igtables = array(
    'Member photos' => 'photo',
    'Documents' => 'document',
    'Generated table1' => 'mail_listing',
    'Generated table2' => 'member_history',
    'Zip code data' => 'postal_codes',
    'Street name suffixes and common misspellings' => 'street_suffix',
    'Apt, Unit, etc.' => 'secondary_unit_desig',
    'List of world languages' => 'language',
    'List of world country codes' => 'country_code',
);

// Create markup
$ignoreTableMarkup = '';
foreach ($igtables as $t => $n) {

    // Don;t show generated tables.
    if ( ! stristr($t, 'Generated') && ! stristr($t, 'Zip')) {
        $ignoreTableMarkup .= "<tr><td>`$n`</td><td>$t</td></tr>";
    }
}

if (isset($_POST["btnDoBackup"])) {

    $accordIndex = 2;
    $bkupAlert = new alertMessage("bkupAlert");
    $bkupAlert->set_Context(alertMessage::Notice);

    $dbBack = new SiteDbBackup(sys_get_temp_dir() . DS, CONF_PATH.ciCFG_FILE);

    if ($dbBack->backupSchema($igtables)) {
        // success
        $logText = "Database Download.";

        SiteLog::logDbDownload($dbh, $logText, CodeVersion::GIT_Id);

        $dbBack->downloadFile();  // exits here.
    } else {

        $logText = "Failed Database Download:  " . $dbBack->getErrors();
        SiteLog::logDbDownload($dbh, $logText, CodeVersion::GIT_Id);
    }

    $bkupMsg = $bkupAlert->createMarkup($dbBack->getErrors());
}

/*
 *  Delete Name records.
 */
$delIdListing = "";

$res3 = $dbh->query("select idName from name where name.Member_Status in ('u','TBD');");

while ($r = $res3->fetch(\PDO::FETCH_NUM)) {
    $delIdListing .= "<a href='NameEdit.php?id=" . $r[0] . "'>" . $r[0] . "</a> ";
}

if ($delIdListing == "") {
    $delIdListing = "No records.";
}


$delNamesMsg = "";
if (filter_has_var(INPUT_POST, "btnDelIds")) {

    $ids = "";
    $total = 0;
    $numStays = 0;
    $stayIds = '';

    // Check for existing donation records
    $query = "select d.Donor_Id, sum(d.Amount), n.Name_Last_First from donations d left join name n on d.Donor_Id = n.idName where d.Status='a' and n.Member_Status in ('u','TBD') group by d.Donor_Id;";
    $res = $dbh->query($query);

    while ($r = $res->fetch(\PDO::FETCH_NUM)) {
        $ids .= $r[0] . ",  ";
        $total += $r[1];
    }

    // Check for existing stays
    $staysStmt = $dbh->query("select n.idName, n.Name_Last_First from stays s left join name n on n.idName = s.idName where n.Member_Status in ('u','TBD') and (s.Status = '" . VisitStatus::CheckedIn . "' or DATEDIFF(ifnull(s.Span_End_Date, now()), s.Span_Start_Date) > 0) group by s.idName");
    while ($r = $staysStmt->fetch(\PDO::FETCH_ASSOC)) {
        $stayIds .= $r['idName'] . ', ';
        $numStays++;
    }

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
        $delStmt = $dbh->query("call delete_names_u_tbd;");
        $response = $delStmt->fetchAll(\PDO::FETCH_ASSOC);
        $delStmt->nextRowset();


        if (isset($response[0]['msg'])) {
            $delDupsAlert->set_Context(alertMessage::Success);
            $delDupsAlert->set_Text($response[0]['msg']);
        } elseif (isset($response[0]['error'])) {
            $delDupsAlert->set_Context(alertMessage::Alert);
            $delDupsAlert->set_Text($response[0]['error']);
        } else {
            $delDupsAlert->set_Context(alertMessage::Alert);
            $delDupsAlert->set_Text("An unknown error has occurred.");
        }
    }
    $delNamesMsg = $delDupsAlert->createMarkup();
}


$selLookups = "<option value=''>No records</option>";

if (($stmt = $dbh->query("select distinct `Table_Name` from `gen_lookups`;")) !== FALSE) {

    $selLookups = "<option value=''>Select</option>";

    while ($rw = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        if ($rw['Table_Name'] != "") {
            $selLookups .= "<option value='" . $rw['Table_Name'] . "'>" . $rw['Table_Name'] . "</option>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
<?php echo JQ_UI_CSS; ?>
<?php echo DEFAULT_CSS; ?>
<?php echo JQ_DT_CSS; ?>
<?php echo FAVICON; ?>
<?php echo NOTY_CSS; ?>
<?php echo GRID_CSS; ?>
<?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="js/misc.js"></script>

    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
<?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <form action="Misc.php" method="post" id="frmLookups" name="frmLookups">
                <div id="accordion" class="hhk-member-detail" style="display:none;">
                    <ul>
                        <li><a href="#lookups">Lookups</a></li>
                        <li><a href="#clean">Clean Data</a></li>
                        <li><a href="#backup">Dump Database</a></li>
                        <li><a href="#changlog">Member Change Log</a></li>
                        <li><a href="#delid">Delete Member Records</a></li>
                    </ul>
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
                                <td colspan="2"><h3>Dump Database</h3></td>
                            </tr>
                            <tr>
                                <td colspan="2"><span style="font-weight:bold;">The following tables are not included in the dump:</span></td>
                            </tr>
<?php echo $ignoreTableMarkup; ?>
                            <tr>
                            <tr><td>&nbsp;</td></tr>
                            <tr>
                                <td colspan="2" style="text-align:right;"><input type="submit" name="btnDoBackup" value="Run Database Dump"/></td>

                            </tr>
                            <tr>
                                <td colspan="2"><?php echo $bkupMsg; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div id="changlog" class="ui-tabs-hide" >
                        <table>
                            <tr><td colspan="2" style="background-color: transparent;"><h3>View the All Member Change Log</h3>
                                </td></tr>
                            <tr>
                                <td>Starting:
                                    <input type="text" id ="sdate" class="autoCal" name="sdate" VALUE='' />
                                </td>
                                <td>Ending:
                                    <INPUT TYPE='text' NAME='edate' id="edate" class="autoCal"  VALUE='' />
                                </td>
                                <td style="text-align:right;"><input type="submit" name="btnGenLog" value="Run"/></td>
                            </tr>
                        </table>
                        <div id="divMkup" style="margin-top: 10px;">
                            <?php echo $chgLogMkup; ?>
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
                                <td><?php echo $delIdListing ?></td>
                            </tr>
                            <tr>
                                <td ><input type="submit" name="btnDelIds"  value="Delete Name Records"/></td>
                            </tr>
                            <tr>
                                <td><?php echo $delNamesMsg; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div id="clean" class="ui-tabs-hide" >
                        <table>
                            <tr><td colspan="2" style="background-color: transparent;"><h3>Clean Data</h3></td></tr>
                            <tr>
                                <td style="text-align:right;">
                                    <input type="submit" name="btnClnPhone" value="Clean up Phone Numbers"/>
                                    <input type="submit" name="btnAddrs" value="Verify Addresses" style="margin-left:10px;"/>
                                </td>
                            </tr>
                        </table>
<?php echo $cleanMsg; ?>
                    </div>
                </div>
                <input id="accordIndex" type="hidden" value="<?php echo $accordIndex; ?>"/>
            </form>
        </div>
    </body>
</html>
