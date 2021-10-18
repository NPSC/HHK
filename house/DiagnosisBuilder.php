<?php
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLSelector;
use HHK\SysConst\WebRole;
use HHK\Config_Lite\Config_Lite;
use HHK\Tables\GenLookupsRS;
use HHK\Tables\EditRS;
use HHK\TableLog\HouseLog;
use HHK\sec\Labels;

/**
 * ResourceBuilder.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die($exw->getMessage());
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

    // new entry row
    $tbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtDiag[0]')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtDOrder[0]', 'size'=>'3')))
            . HTMLTable::makeTd('New', array('colspan' => 2))
            . ($type == 'ha' || $type == 'ca' ? HTMLTable::makeTd(HTMLInput::generateMarkup('', array('size' => '7', 'style' => 'text-align:right;', 'name' => 'txtDiagAmt[0]'))) : '')
    );


    return $tbl;

}


$dbh = $wInit->dbh;
$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$uS = Session::getInstance();

// Kick out 'Guest' Users
if ($uS->rolecode > WebRole::WebUser) {

    exit("Unauthorized - " . HTMLContainer::generateMarkup('a', 'Continue', array('href'=>'index.php')));
}


$tabIndex = 0;
$rteFileSelection = '';
$rteMsg = '';

// Get labels
$labels = Labels::getLabels();

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


        $amounts = array();

        $codeArray = filter_var_array($_POST['txtDiag'], FILTER_SANITIZE_STRING);
        $orderNums = filter_var_array($_POST['txtDOrder'], FILTER_SANITIZE_NUMBER_INT);

        replaceGenLk($dbh, $tableName, $codeArray, $amounts, $orderNums, (isset($_POST['cbDiagDel']) ? $_POST['cbDiagDel'] : NULL), $rep, (isset($_POST['cbDiagDel']) ? $_POST['selDiagDel'] : array()));

    }


    // Generate selectors.
    $tbl = getSelections($dbh, $tableName, 'u');

    echo($tbl->generateMarkup());
    exit();
}


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

$selLookups = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($lkups, ''), array('name' => 'selLookup', 'class' => 'hhk-selLookup'));
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function () {
        "use strict";

        $('#selLookup').change(function () {
            var table = $(this).find("option:selected").text(),
                type = $(this).val();

            $('#saveMsg').hide();
            $('#divlk').empty().text('Loading...');
            $.post('DiagnosisBuilder.php', {table: table, cmd: "load", tp: type},
                function (data) {
                    $('#divlk').empty();
                    if (data) {
                        $('#divlk').append(data);
                    }
                });
        });

        $('#btnlkSave').click(function () {

            var sel = $('#selLookup');
            var table = sel.find('option:selected').text(),
                type = $('#selLookup').val(),
                $btn = $(this);

            $('#saveMsg').hide();

            if ($btn.val() === 'Saving...') {
                return;
            }

            $btn.val('Saving...');

            $.post('DiagnosisBuilder.php', $('#formlk').serialize() + '&cmd=save' + '&table=' + table + '&tp=' + type,
                function(data) {
                    $btn.val('Save');
                    $('#divlk').empty();
                    if (data) {
                        $('#divlk').append(data);
                        $('#saveMsg').text(table + ' saved').show();
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
            <div id="lkTable" class="hhk-tdbox hhk-visitdialog" style="clear:left;font-size: .9em;margin-top:20px;">
                <form method="POST" action="DiagnosisBuilder.php" id="formlk">
                    <table><tr>
                            <th>Category</th>
                            <td><?php echo $selLookups; ?></td>
                        </tr></table>
                    <p id="saveMsg" style="display:none; max-width: 50%;" class="ui-state-highlight"></p>
                    <div id="divlk" style="margin:10px;"></div>
                    <span style="margin:10px;">
                        <?php if (!$hasDiags) { ?>
                        <input type="submit" name='btnAddDiags' id="btnAddDiags" value="Add Diagnosis"/>
                        <?php } if (!$hasLocs) { ?>
                        <input type="submit" id='btnAddLocs' name="btnAddLocs" value="Add Location"/>
                        <?php } ?>
                        <input type="button" id='btnlkSave' class="hhk-saveLookup "data-type="h" value="Save"/>
                    </span>
                </form>
            </div>

        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
