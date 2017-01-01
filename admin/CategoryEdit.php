<?php
/**
 * CategoryEdit.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */
require_once ("AdminIncludes.php");

require_once (DB_TABLES . 'GenLookupsRS.php');
require_once (DB_TABLES . 'nameRS.php');

$wInit = new webInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

addslashesextended($_POST);


// catch service call
$tableName = filter_input(INPUT_POST, 'ql', FILTER_SANITIZE_STRING);
if ($tableName != '') {

    $rows = array();
    $volArray = $wInit->reloadSessionVolLkUps();

    if (isset($volArray['Vol_Category'][$tableName])) {
        $rows = readGenLookups($dbh, $tableName);
    }

    echo json_encode($rows);
    exit;
}

function processAction(PDO $dbh, $tbl, $cde, $colr, $desc, $repl, $action, &$volAlert) {

    // Is the Code there?
    $gl = new GenLookupsRS();
    $gl->Table_Name->setStoredVal($tbl);
    $gl->Code->setStoredVal($cde);
    $rows = EditRS::select($dbh, $gl, array($gl->Table_Name, $gl->Code));

    if (count($rows) == 0 && $action == "add") {
        // add a new code with desc.

        $dbh->query("CALL IncrementCounter('codes', @num);");
        foreach ($dbh->query("SELECT @num") as $row) {
            $rptId = $row[0];
        }
        if ($rptId == 0) {
            throw new Hk_Exception_Runtime("Event Repeater counter not set up.");
        }

        $gl = new GenLookupsRS();
        $gl->Code->setNewVal('v' . $rptId);
        $gl->Description->setNewVal($desc);
        $gl->Substitute->setNewVal($colr);
        $gl->Table_Name->setNewVal($tbl);
        EditRS::insert($dbh, $gl);

        $volAlert->set_Context(alertMessage::Success);
        $volAlert->set_Text("ok");

    } else if (count($rows) == 1) {

        EditRS::loadRow($rows[0], $gl);

        if ($action == "del") {
            // Delete code

            if ($repl == "vNone") {

                // No replacement code so delete all instances and the gen_lookups record.
                $nvRs = new NameVolunteerRS();
                $nvRs->Vol_Category->setStoredVal($tbl);
                $nvRs->Vol_Code->setStoredVal($cde);

                EditRS::delete($dbh, $nvRs, array($nvRs->Vol_Category, $nvRs->Vol_Code));

                // delete orig from gen_lookups
                EditRS::delete($dbh, $gl, array($gl->Table_Name, $gl->Code));
                $volAlert->set_Context(alertMessage::Success);
                $volAlert->set_Text("Category deleted from the database.");

            } else {

                // Is the replacement Code there?
                $glReplace = new GenLookupsRS();
                $glReplace->Table_Name->setStoredVal($tbl);
                $glReplace->Code->setStoredVal($repl);
                $rowsReplace = EditRS::select($dbh, $glReplace, array($glReplace->Table_Name, $glReplace->Code));

                // Valid replacement code?
                if (count($rowsReplace) == 1) {
                    // Make any needed replacements in Name_Volunteer
                    // if any member has a vol record for the old code AND a record for the new code, delete the old code.
                    $qu = "delete  nv  from name_volunteer2 nv join name_volunteer2 nv2 on nv.idName = nv2.idName
                        where nv.Vol_Category=" . $dbh->quote($tbl) . " and nv.Vol_Code=" . $dbh->quote($cde)
                            . " and nv2.Vol_Category=" . $dbh->quote($tbl) . " and nv2.Vol_Code= " . $dbh->quote($repl);
                    $dbh->exec($qu);

                    // Now we are free to simply change the old code to the replacement code.
                    $query = "update name_volunteer2 set Vol_Code= " . $dbh->quote($repl) . ", Updated_By=" . $dbh->quote($_SESSION["username"]) . ", Last_Updated=Now()
                    where Vol_Category=" . $dbh->quote($tbl) . " and Vol_Code=" . $dbh->quote($cde) . ";";
                    $dbh->exec($query);

                    // delete orig from gen_lookups
                    EditRS::delete($dbh, $gl, array($gl->Table_Name, $gl->Code));

                    $volAlert->set_Context(alertMessage::Success);
                    $volAlert->set_Text("Category deleted from the database.");
                } else {

                    $volAlert->set_Context(alertMessage::Alert);
                    $volAlert->set_Text("Invalid replacement Category - couldn't find it: " . $tbl . "-" . $repl);
                }
            }
        } else if ($action == 'add') {

            // Update the description
            $gl->Description->setNewVal($desc);
            $x = EditRS::update($dbh, $gl, array($gl->Table_Name, $gl->Code));

            if ($x > 0) {
                $volAlert->set_Context(alertMessage::Success);
                $volAlert->set_Text("Category Updated.");
            }
        }
    } else {
        $volAlert->set_Context(alertMessage::Alert);
        $volAlert->set_Text("Couldn't find the TableName and Code: $tbl, $cde");
    }
}

$resMessage = "";



if (isset($_POST["btnvType"])) {

    $volAlert = new alertMessage("volAlert");
    $volAlert->set_Context(alertMessage::Alert);

    $repl = '';
    $action = 'add';
    $del = filter_input(INPUT_POST, "vTypeDel", FILTER_VALIDATE_BOOLEAN);

    if ($del === TRUE) {
        $action = "del";
        $repl = filter_input(INPUT_POST, "vTypeRepl", FILTER_SANITIZE_STRING);
    }

    $tbl = filter_input(INPUT_POST, "selVol", FILTER_SANITIZE_STRING);
    $cde = filter_input(INPUT_POST, "vTypeCode", FILTER_SANITIZE_STRING);
    $desc = filter_input(INPUT_POST, "vTypeDesc", FILTER_SANITIZE_STRING);
    $fill = filter_input(INPUT_POST, "vTypeFill", FILTER_SANITIZE_STRING);
    $text = filter_input(INPUT_POST, "vTypeText", FILTER_SANITIZE_STRING);
    $colr = $fill . "," . $text;

    if (is_null($tbl) || $tbl == "") {
        $volAlert->set_Text("The Category is missing.");
    } else if (($fill != "" && $text == "") || ($fill == "" && $text != "")) {
        $volAlert->set_Text(" Need a color for both Fill and Text, or leave both blank.");
    } else if ($desc != null && $desc != "" && strlen($desc) < 255) {

        processAction($dbh, $tbl, $cde, $colr, $desc, $repl, $action, $volAlert);
    } else {
        $volAlert->set_Text("The Category-(Text) must be filled in.");
    }

    $resMessage = $volAlert->createMarkup();
}

$vCatOptions = DoLookups($dbh, "Vol_Category", '', false);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo TOP_NAV_CSS; ?>
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript">
            $(document).ready(function() {
                var colr = new Object();
                $('#selVol').change(function() {
                    $.post('CategoryEdit.php',
                            {ql: $(this).val()},
                    function(data) {
                        var codes = $.parseJSON(data);
                        // remove any previous entries
                        $("#selCode")
                                .find('option')
                                .remove();
                        $("#selCode").append('<option value="vNew">New</option>');
                        $("#vTypeRepl")
                                .find('option')
                                .remove();
                        $("#vTypeRepl").append('<option value="vNone">-None-</option>');
                        for (var nme in codes) {
                            colr[codes[nme].Code] = codes[nme].Substitute;
                            $("#selCode").append('<option value="' + codes[nme].Code + '">' + codes[nme].Description + '</option>');
                            $("#vTypeRepl").append('<option value="' + codes[nme].Code + '">' + codes[nme].Description + '</option>');
                        }
                        $('input.hhk-vcat:text').val('');
                        $("#vTypeDel").prop('checked', false);
                        $('#vTypeRepl').prop('disabled', true);
                        $('#btnvType').val("Save");
                    });
                });

                $('#vTypeDel').change(function() {
                    if ($(this).prop('checked') && $('#selCode').val() !== "vNew" && $('#selCode').val() !== "") {
                        $('#vTypeRepl').prop('disabled', false);
                    } else {
                        $('#vTypeRepl').prop('disabled', true);
                    }
                });
                $('#selCode').change(function() {
                    //var selCtrl = this;

                    if (this.value === "vNew") {

                        $('input.hhk-vcat:text').val('');
                        $('#btnvType').val("Save");
                    } else {
                        $('#vTypeCode').val(this.value);

                        var tp = colr[this.value].split(',');
                        if (tp.length === 2) {
                            $('#vTypeFill').val(tp[0]);
                            $('#vTypeText').val(tp[1]);
                        } else {
                            $('#vTypeFill').val('');
                            $('#vTypeText').val('');
                            ;
                        }
                        for (i = 0; i < this.options.length; i++) {
                            if (this.options[i].selected) {
                                $('#vTypeDesc').val(this.options[i].text);
                            }
                        }
                        $('#btnvType').val("Update");
                    }

                });
            });
        </script>
    </head>
    <body <?php if ($testVersion) {
            echo "class='testbody'";
        } ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>

            <div id="vcategory" class="ui-widget ui-widget-content" style="float:left;">
                <form action="CategoryEdit.php" method="post" name="fCheckDates">
                    <table>
                        <tr>
                            <th>Category Group</th>
                        </tr><tr><td>
                                <select id="selVol" name="selVol" size="3">
<?php echo $vCatOptions; ?>
                                </select></td>
                        </tr>
                        <tr>
                            <td></td><td></td><th colspan="2">Calendar Colors</th>
                        </tr>
                        <tr>
                            <th>Category</th>
                            <th>Code</th>
                            <th>Fill Color</th>
                            <th>Text Color</th>
                        </tr>
                        <tr>
                            <td>
                                <select style="width:230px" id="selCode" name="selCode"></select>
                            </td>
                            <td><input id="vTypeCode" name="vTypeCode" class="hhk-vcat" type="text" value="" size="5" readonly="readonly" /></td>
                            <td><input id="vTypeFill" name="vTypeFill" class="hhk-vcat" type="text" value="" size="13" /></td>
                            <td><input id="vTypeText" name="vTypeText" class="hhk-vcat" type="text" value="" size="13" /></td>
                        </tr>
                        <tr>
                            <th colspan="4">Category (Text)</th>
                        </tr><tr>
                            <td colspan="4"><input id="vTypeDesc" name="vTypeDesc" class="hhk-vcat" type="text" value="" size="80" /></td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" id="vTypeDel" name="vTypeDel" class="hhk-vcat" /><label for="vTypeDel"> Delete</label></td>
                            <td class="tdlabel"> Replace with: </td>
                            <td colspan="2"><select style="width:230px" id="vTypeRepl" name="vTypeRepl" class="hhk-vcat" disabled="disabled" ></select></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="tdlabel"><input id="btnvType" name="btnvType" class="hhk-vcat" type="submit" value="Save" /></td>
                        </tr>
                        <tr><td colspan="4" class="tdReport"><?php echo $resMessage; ?></td></tr>
                    </table>
                </form>
            </div>
        </div>
    </body>
</html>

