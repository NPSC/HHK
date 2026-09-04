<?php

use HHK\Common;
use HHK\HTMLControls\{HTMLInput, HTMLSelector, HTMLTable};
use HHK\sec\{WebInit};
use HHK\Tables\EditRS;
use HHK\Tables\GenLookupsRS;
use HHK\Vite\Vite;

/**
 * CategoryEdit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new WebInit();
$dbh = $wInit->dbh;

/**
 * Builds the editable codes table for a Vol_Category group.
 */
function getCategoryCodesTable(\PDO $dbh, string $tableName): HTMLTable {

    $codes = Common::readGenLookupsPDO($dbh, $tableName, 'Description');

    $tbl = new HTMLTable();

    $tbl->addHeaderTr(
        HTMLTable::makeTh('ID')
        . HTMLTable::makeTh(count($codes) . ' Entries')
        . HTMLTable::makeTh('Fill Color')
        . HTMLTable::makeTh('Text Color')
    );

    foreach ($codes as $c) {

        $colorParts = explode(',', $c['Substitute']);
        $fillColor = (isset($colorParts[0]) && $colorParts[0] != '' ? $colorParts[0] : '#3788d8');
        $textColor = (isset($colorParts[1]) && $colorParts[1] != '' ? $colorParts[1] : '#ffffff');

        $tbl->addBodyTr(
            HTMLTable::makeTd($c['Code'])
            . HTMLTable::makeTd(HTMLInput::generateMarkup($c['Description'], ['name' => 'txtCatDesc[' . $c['Code'] . ']', 'size' => '45']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($fillColor, ['name' => 'txtCatFill[' . $c['Code'] . ']', 'type' => 'color']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($textColor, ['name' => 'txtCatText[' . $c['Code'] . ']', 'type' => 'color']))
        );
    }

    // New entry row
    $tbl->addBodyTr(
        HTMLTable::makeTd('New')
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'txtCatDesc[0]', 'size' => '45', 'placeholder' => 'New Category...']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('#3788d8', ['name' => 'txtCatFill[0]', 'type' => 'color']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('#ffffff', ['name' => 'txtCatText[0]', 'type' => 'color']))
    );

    return $tbl;
}

function addCategoryCode(\PDO $dbh, string $tbl, string $desc, string $colr): void {

    $rptId = Common::incCounter($dbh, 'codes');

    $gl = new GenLookupsRS();
    $gl->Code->setNewVal('v' . $rptId);
    $gl->Description->setNewVal($desc);
    $gl->Substitute->setNewVal($colr);
    $gl->Table_Name->setNewVal($tbl);
    EditRS::insert($dbh, $gl);
}

function updateCategoryCode(\PDO $dbh, string $tbl, string $cde, string $desc, string $colr): void {

    $gl = new GenLookupsRS();
    $gl->Table_Name->setStoredVal($tbl);
    $gl->Code->setStoredVal($cde);
    $rows = EditRS::select($dbh, $gl, array($gl->Table_Name, $gl->Code));

    if (count($rows) !== 1) {
        return;
    }

    EditRS::loadRow($rows[0], $gl);
    $gl->Description->setNewVal($desc);
    $gl->Substitute->setNewVal($colr);
    EditRS::update($dbh, $gl, array($gl->Table_Name, $gl->Code));
}

function saveCategoryCodes(\PDO $dbh, string $tbl, array $post): void {

    $descArray = isset($post['txtCatDesc']) ? $post['txtCatDesc'] : [];
    $fillArray = isset($post['txtCatFill']) ? $post['txtCatFill'] : [];
    $textArray = isset($post['txtCatText']) ? $post['txtCatText'] : [];

    foreach ($descArray as $code => $desc) {

        $code = trim(filter_var($code, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $desc = trim(filter_var($desc, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $colr = filter_var((isset($fillArray[$code]) ? $fillArray[$code] : ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            . ',' . filter_var((isset($textArray[$code]) ? $textArray[$code] : ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($code === '0') {

            if ($desc !== '') {
                addCategoryCode($dbh, $tbl, $desc, $colr);
            }
            continue;
        }

        if ($desc === '') {
            continue;
        }

        updateCategoryCode($dbh, $tbl, $code, $desc, $colr);
    }
}

// AJAX load/save of a category's codes table.
if (filter_has_var(INPUT_POST, 'cmd')) {

    $cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $tableName = filter_input(INPUT_POST, 'table', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $volArray = $wInit->reloadSessionVolLkUps();

    if ($tableName == '' || !isset($volArray['Vol_Category'][$tableName])) {
        exit;
    }

    if ($cmd === 'save') {
        $postLookups = filter_has_var(INPUT_POST, 'lookups') ? json_decode(filter_input(INPUT_POST, 'lookups', FILTER_UNSAFE_RAW), true) : [];
        saveCategoryCodes($dbh, $tableName, is_array($postLookups) ? $postLookups : []);
    }

    echo getCategoryCodesTable($dbh, $tableName)->generateMarkup();
    exit;
}

$volCategories = Common::readGenLookupsPDO($dbh, "Vol_Category");
$vCatOptions = HTMLSelector::doOptionsMkup($volCategories, '', false);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>

        <?php echo Vite::asset('resources/js/admin.js'); ?>

        <?php echo FAVICON; ?>

        <style>
            #catCodesTbl table td, #catCodesTbl table th {
                padding: 4px 8px;
                border: 1px solid #ddd;
            }
            #catCodesTbl table {
                border-collapse: collapse;
            }
        </style>

        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", () => {

                $('#selVol').change(function () {

                    var tbl = $(this).val();

                    if (!tbl) {
                        $('#divCatCodes').hide();
                        return;
                    }

                    $('#selVolValue').val(tbl);
                    $('#catCodesTbl').empty().text('Loading...');
                    $('#divCatCodes').show();

                    $.post('CategoryEdit.php', { table: tbl, cmd: 'load' }, function (data) {
                        $('#catCodesTbl').empty().append(data);
                    });
                });

                $('#btnCatSave').click(function () {

                    var $btn = $(this);
                    var tbl = $('#selVolValue').val();

                    if (!tbl || $btn.val() === 'Saving...') {
                        return;
                    }

                    $btn.val('Saving...');

                    var lookupData = $('#formCat').serializeJSON();

                    $.post('CategoryEdit.php', {
                        table: tbl,
                        cmd: 'save',
                        lookups: JSON.stringify(lookupData)
                    }, function (data) {
                        $btn.val('Save');
                        $('#catCodesTbl').empty().append(data);
                    });
                });
            });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {
            echo "class='testbody'";
        } ?>>
<?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>

            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content hhk-flex" style="font-size:1em; align-items: flex-start; gap: 20px;">
                <div>
                    <table>
                        <tr>
                            <th>Category Group</th>
                        </tr><tr><td>
                                <select id="selVol" name="selVol" size="12">
                                    <?php echo $vCatOptions; ?>
                                </select></td>
                        </tr>
                    </table>
                </div>

                <div id="divCatCodes" style="display:none; flex: 1;">
                    <h3>Calendar Colors</h3>
                    <form id="formCat">
                        <input type="hidden" id="selVolValue" name="table" value="" />
                        <div id="catCodesTbl"></div>
                        <div class="hhk-flex mt-2" style="justify-content: flex-end;">
                            <input type="button" id="btnCatSave" class="ui-button ui-widget ui-corner-all" value="Save" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
