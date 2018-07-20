<?php
/**
 * ResourceBuilder.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'History.php');
require (CLASSES . 'CreateMarkupFromDB.php');

require (DB_TABLES . 'GenLookupsRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'AttributeRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'ItemRS.php');


require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Room.php');
require (CLASSES . 'HouseLog.php');
require (CLASSES . 'Purchase/RoomRate.php');
require (CLASSES . 'FinAssistance.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

function saveArchive(\PDO $dbh, $desc, $subt, $tblName) {

    $defaultCode = '';

    if (isset($desc)) {

        $uS = Session::getInstance();

        foreach ($desc as $k => $r) {

            $code = trim(filter_var($k, FILTER_SANITIZE_STRING));

            if ($code == '' || $tblName == '') {
                continue;
            }

            $glRs = new GenLookupsRS();
            $glRs->Table_Name->setStoredVal($tblName);
            $glRs->Code->setStoredVal($code);
            $rows = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if (count($rows) < 1) {
                continue;
            }

            EditRS::loadRow($rows[0], $glRs);

            $newDesc = '';

            if ($r != '') {
                $newDesc = filter_var($r, FILTER_SANITIZE_STRING);
            } else {
                continue;
            }

            if (isset($subt[$code])) {
                $newSubt = filter_var($subt[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            } else {
                continue;
            }

            // Check if value changed.
            if ($glRs->Substitute->getStoredVal() != $newSubt) {

                // Create new entry
                $newRs = new GenLookupsRS();
                $defaultCode = incCounter($dbh, 'codes');

                $newRs->Table_Name->setNewVal($tblName);
                $newRs->Code->setNewVal($defaultCode);
                $newRs->Description->setNewVal($newDesc);
                $newRs->Substitute->setNewVal($newSubt);

                EditRS::insert($dbh, $newRs);
                $logText = HouseLog::getInsertText($newRs, $tblName);
                HouseLog::logGenLookups($dbh, $tblName, $defaultCode, $logText, 'insert', $uS->username);

                // Update Old
                $glRs->Type->setNewVal(GlTypeCodes::Archive);

                $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));
                $logTextu = HouseLog::getUpdateText($glRs, $tblName . $code);
                HouseLog::logGenLookups($dbh, $tblName, $code, $logTextu, 'update', $uS->username);

            } else {

                // update
                if ($newDesc != '') {
                    $glRs->Description->setNewVal($newDesc);
                }

                $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

                if ($ctr > 0) {
                    $logText = HouseLog::getUpdateText($glRs, $tblName . $code);
                    HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'update', $uS->username);
                }

            }
        }
    }

    return $defaultCode;
}

function getSelections(\PDO $dbh, $tableName, $type) {

    $uS = Session::getInstance();

    // Generate selectors.
    $diags = readGenLookupsPDO($dbh, $tableName, 'Order');

    $tbl = new HTMLTable();

    if ($type == 'm') {
        $hdrTr = HTMLTable::makeTh(count($diags) . ' Entries') . HTMLTable::makeTh('Order') . HTMLTable::makeTh('Use');
    } else {
        $hdrTr = HTMLTable::makeTh(count($diags) . ' Entries') . HTMLTable::makeTh('Order')
                . ($type == 'ca' ? HTMLTable::makeTh('Amount') : '')
                . ($type == 'ha' ? HTMLTable::makeTh('Days') : '')
                . ($type == 'd' && $uS->GuestNameColor == $tableName ? HTMLTable::makeTh('Colors (font, bkgrnd)') : '')
                . ($type == 'u' ? '' : HTMLTable::makeTh('Delete') . HTMLTable::makeTh('Replace With'));
    }

    $tbl->addHeaderTr($hdrTr);

    foreach ($diags as $d) {

        // Remove this item from the replacement entries.
        $tDiags = removeOptionGroups($diags);
        unset($tDiags[$d[0]]);

        $cbDelMU = '';

        if ($type == 'm') {

            $ary = array('name' => 'cbDiagDel[' . $d[0] . ']', 'type' => 'checkbox', 'class' => 'hhkdiagdelcb');

            if (strtolower($d[2]) == 'y') {
                $ary['checked'] = 'checked';
            }

            $cbDelMU = HTMLTable::makeTd(HTMLInput::generateMarkup('', $ary));

        } else if ($type == 'd' && $d[0] == 'z') {

            $cbDelMU = HTMLTable::makeTd('');

        } else if ($type != 'u') {

            $cbDelMU = HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'cbDiagDel[' . $d[0] . ']', 'type' => 'checkbox', 'class' => 'hhkdiagdelcb', 'data-did' => 'selDiagDel[' . $d[0] . ']')));
        }

        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($d[1], array('name' => 'txtDiag[' . $d[0] . ']')))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($d[4], array('name' => 'txtDOrder[' . $d[0] . ']', 'size'=>'3')))
                . ($type == 'ha' || $type == 'ca' || ($type == 'd' && $uS->GuestNameColor == $tableName) ? HTMLTable::makeTd(HTMLInput::generateMarkup($d[2], array('size' => '10', 'style' => 'text-align:right;', 'name' => 'txtDiagAmt[' . $d[0] . ']'))) : '')
                . $cbDelMU
                . ($type != 'm' && $type != 'u' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($tDiags, ''), array('name' => 'selDiagDel[' . $d[0] . ']'))) : '')
        );
    }

    if ($type != 'u' && $type != 'm') {
        // new entry row
        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtDiag[0]')))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtDOrder[0]', 'size'=>'3')))
                . HTMLTable::makeTd('New', array('colspan' => 2))
                . ($type == 'ha' || $type == 'ca' ? HTMLTable::makeTd(HTMLInput::generateMarkup('', array('size' => '7', 'style' => 'text-align:right;', 'name' => 'txtDiagAmt[0]'))) : '')
        );
    }

    return $tbl;

}


$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();
$uS = Session::getInstance();

// Kick out 'Guest' Users
if ($uS->rolecode > WebRole::WebUser) {

    exit("Unauthorized - " . HTMLContainer::generateMarkup('a', 'Continue', array('href'=>'index.php')));
}


$tabIndex = 0;
$rteFileSelection = '';
$rteMsg = '';

// Get labels
$labels = new Config_Lite(LABEL_FILE);

// Add diags and locations buttons
if (isset($_POST['btnAddDiags'])) {
    $dbh->exec("insert into gen_lookups (`Table_Name`, `Code`, `Description`, `Type`, `Order`) values ('Diagnosis', 'q9', 'New Entry', 'h', 10 )");
    $tabIndex = 5;
}

if (isset($_POST['btnAddLocs'])) {
    $dbh->exec("insert into gen_lookups (`Table_Name`, `Code`, `Description`, `Type`, `Order`) values ('Location', 'q9', 'New Entry', 'h', 10 )");
    $tabIndex = 5;
}


// Lookups
if (isset($_POST['table'])) {

    $tableName = filter_var($_POST['table'], FILTER_SANITIZE_STRING);

    if ($tableName == '') {
        echo '';
        exit();
    }

    $cmd = '';
    $type = '';
    $order = 0;

    if (isset($_POST['cmd'])) {
        $cmd = filter_var($_POST['cmd'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['tp'])) {
        $type = filter_var($_POST['tp'], FILTER_SANITIZE_STRING);
    }

    // Save
    if ($cmd == 'save' && isset($_POST['txtDiag'])) {

        // Check for a new entry
        if (isset($_POST['txtDiag'][0]) && $_POST['txtDiag'][0] != '') {

            // new entry
            $dText = filter_var($_POST['txtDiag'][0], FILTER_SANITIZE_STRING);
            $aText = '';

            if (isset($_POST['txtDiagAmt'][0])) {
                $aText = filter_var($_POST['txtDiagAmt'][0], FILTER_SANITIZE_STRING);
            }

            $orderNumber = 0;
            if (isset($_POST['txtDOrder'][0])) {
                $orderNumber = intval(filter_var($_POST['txtDOrder'][0], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            // Check for an entry with the same description
            $stmt = $dbh->query("Select count(*) from gen_lookups where `Table_Name` = '$tableName' and LOWER(`Description`) = '" . strtolower($dText) . "';");
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($rows[0][0] == 0) {
                // Not there.
                $newCode = 'g' . incCounter($dbh, 'codes');

                $glRs = new GenLookupsRS();
                $glRs->Table_Name->setNewVal($tableName);
                $glRs->Code->setNewVal($newCode);
                $glRs->Description->setNewVal($dText);
                $glRs->Substitute->setNewVal($aText);
                $glRs->Type->setNewVal($type);
                $glRs->Order->setNewVal($orderNumber);

                EditRS::insert($dbh, $glRs);

                $logText = HouseLog::getInsertText($glRs);
                HouseLog::logGenLookups($dbh, $tableName, $newCode, $logText, "insert", $uS->username);
            }

            unset($_POST['txtDiag'][0]);
        }

        $rep = NULL;

        $demos = readGenLookupsPDO($dbh, 'Demographics');

        // Define the return functions.
        if (isset($demos[$tableName])) {

            if ($tableName == 'Gender') {
                $rep = function($dbh, $newId, $oldId, $tableName) {
                    return $dbh->exec("update name set `$tableName` = '$newId' where `$tableName` = '$oldId';");
                };
            } else {
                $rep = function($dbh, $newId, $oldId, $tableName) {
                    return $dbh->exec("update name_demog set `$tableName` = '$newId' where `$tableName` = '$oldId';");
                };
            }

        } else {
            switch ($tableName) {

                case 'Patient_Rel_Type':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update name_guest set Relationship_Code = '$newId' where Relationship_Code = '$oldId';");
                    };

                    $verify = "Select n.Relationship_Code from name_guest n left join gen_lookups g on n.Relationship_Code = g.Code Where g.Table_Name = 'Patient_Rel_Type' and g.Code is null;";
                    break;

                case 'Diagnosis':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update hospital_stay set Diagnosis = '$newId' where Diagnosis = '$oldId';");
                    };

                    $verify = "select hs.Diagnosis from hospital_stay hs left join gen_lookups g on hs.Diagnosis = g.Code where g.Table_Name = 'Diagnosis' and g.Code is null;";
                    break;

                case 'Location':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update hospital_stay set Location = '$newId' where Location = '$oldId';");
                    };
                    break;

                case 'OSS_Codes':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update resource_use set OSS_Code = '$newId' where OSS_Code = '$oldId';");
                    };
                    break;

                case 'Utilization_Category':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update resource set Utilization_Category = '$newId' where Utilization_Category = '$oldId';");
                    };
                    break;

                case 'Ins_Type':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update insurance set `Type` = '$newId' where `Type` = '$oldId';");
                    };
                    break;

                case 'Room_Cleaning_Days':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update room set `Cleaning_Cycle_Code` = '$newId' where `Cleaning_Cycle_Code` = '$oldId';");
                    };
                    break;

                case 'NoReturnReason':

                    $rep = function($dbh, $newId, $oldId) {
                        return $dbh->exec("update name_demog set `No_Return` = '$newId' where `No_Return` = '$oldId';");
                    };
                    break;
            }
        }

        $amounts = array();
        if (isset($_POST['txtDiagAmt'])) {

            foreach ($_POST['txtDiagAmt'] as $k => $a) {
                if (is_numeric($a)) {
                    $a = abs($a);
                }

                $amounts[$k] = $a;
            }
        }

        $codeArray = filter_var_array($_POST['txtDiag'], FILTER_SANITIZE_STRING);
        $orderNums = filter_var_array($_POST['txtDOrder'], FILTER_SANITIZE_NUMBER_INT);

        if ($type === 'm') {

            foreach ($codeArray as $c => $v) {

                $gluRs = new GenLookupsRS();
                $gluRs->Table_Name->setStoredVal($tableName);
                $gluRs->Code->setStoredVal($c);

                $rw = EditRS::select($dbh, $gluRs, array($gluRs->Table_Name, $gluRs->Code));

                if (count($rw) == 1) {

                    $gluRs = new GenLookupsRS();
                    EditRS::loadRow($rw[0], $gluRs);

                    $use = '';
                    if (isset($_POST['cbDiagDel'][$c])) {
                        $use = 'y';
                    }

                    $orderNumber = 0;
                    if (isset($_POST['txtDOrder'][$c])) {
                        $orderNumber = intval(filter_var($_POST['txtDOrder'][$c], FILTER_SANITIZE_NUMBER_INT), 10);
                    }

                    $desc = '';
                    if (isset($_POST['txtDiag'][$c])) {
                        $desc = filter_var($_POST['txtDiag'][$c], FILTER_SANITIZE_STRING);
                    }

                    $gluRs->Description->setNewVal($desc);
                    $gluRs->Substitute->setNewVal($use);
                    $gluRs->Order->setNewVal($orderNumber);

                    $upCtr = EditRS::update($dbh, $gluRs, array($gluRs->Table_Name, $gluRs->Code));

                    if ($upCtr > 0) {

                        $logText = HouseLog::getUpdateText($gluRs);
                        HouseLog::logGenLookups($dbh, $tableName, $c, $logText, "update", $uS->username);
                    }
                }
            }
        } else {
            replaceGenLk($dbh, $tableName, $codeArray, $amounts, $orderNums, (isset($_POST['cbDiagDel']) ? $_POST['cbDiagDel'] : NULL), $rep, (isset($_POST['cbDiagDel']) ? $_POST['selDiagDel'] : array()));
        }
    }


    // Generate selectors.
    $tbl = getSelections($dbh, $tableName, $type);

    echo($tbl->generateMarkup());
    exit();
}


//
// Generate tab content
//
// General Lookup categories
$stmt2 = $dbh->query("select distinct `Type`, `Table_Name` from gen_lookups where `Table_Name` in ('Diagnosis', 'Location');");
$rows2 = $stmt2->fetchAll(PDO::FETCH_NUM);

$lkups = array();
$hasDiags = FALSE;
$hasLocs = FALSE;

foreach ($rows2 as $r) {

    $lkups[] = $r;

    if ($r[1] == 'Diagnosis') {
        $hasDiags = TRUE;
    } else if ($r[1] == 'Location') {
        $hasLocs = TRUE;
    }

}

$selLookups = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($lkups, ''), array('name' => 'sellkLookup', 'class' => 'hhk-selLookup'));



// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("uh-oh");

$resultMessage = $alertMsg->createMarkup();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo RTE_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RTE_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function () {
        "use strict";

        $('.hhk-selLookup').change(function () {
            var $sel = $(this),
                table = $(this).find("option:selected").text(),
                type = $(this).val();

            if ($sel.data('type') === 'd') {
                table = $sel.val();
                type = 'd';
            }

            $.post('ResourceBuilder.php', {table: table, cmd: "load", tp: type},
                    function (data) {
                        $sel.closest('form').children('div').children().remove();
                        if (data) {
                            $sel.closest('form').children('div').append(data);
                        }
                    });
        });

        $('.hhk-saveLookup').click(function () {
            var $btn = $(this).closest('form');
            var sel = $btn.find('select.hhk-selLookup');
            var table = sel.find('option:selected').text(),
                type = $btn.find('select').val();

            if (sel.data('type') === 'd') {
                table = sel.val();
                type = 'd';
            }

            $.post('ResourceBuilder.php', $btn.serialize() + '&cmd=save' + '&table=' + table + '&tp=' + type,
                function(data) {
                    if (data) {
                        $btn.children('div').children().remove().end().append(data);
                    }
                });
        }).button();



        // Add diagnosis and locations
        if ($('#btnAddDiags').length > 0) {
            $('#btnAddDiags').button();
        }
        if ($('#btnAddLocs').length > 0) {
            $('#btnAddLocs').button();
        }


    });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <h1><?php echo $wInit->pageHeading; ?></h1>
            </div>
<?php echo $resultMessage ?>

            <div id="lkTable" class="hhk-tdbox hhk-visitdialog" style="clear:left;font-size: .9em;margin-top:20px;">

                    <form method="POST" action="ResourceBuilder.php" id="formlk">
                        <table><tr>
                                <th>Category</th>
                                <td><?php echo $selLookups; ?></td>
                            </tr></table>
                        <div id="divlk" style="margin:10px;"></div>
                        <span style="margin:10px;">
                            <?php if (!$hasDiags) { ?>
                            <input type="submit" name='btnAddDiags' id="btnAddDiags" value="Add Diagnosis"/>
                            <?php } if (!$hasLocs) { ?>
                            <input type="submit" id='btnAddLocs' name="btnAddLocs" value="Add Location"/>
                            <?php } ?>
                            <input type="button" id='btnlkSave' class="hhk-saveLookup"data-type="h" value="Save"/>
                        </span>
                    </form>
            </div>

        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
