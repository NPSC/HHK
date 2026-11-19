<?php

use HHK\Admin\CategoryEditor;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\WebInit;

/**
 * CategoryEdit.php
 *
 * Edit the categories in each member category group (Member Type, etc.).
 * Loading and saving are done over AJAX against this same script (cmd=load / cmd=save).
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new WebInit();
$dbh = $wInit->dbh;

$groups = CategoryEditor::groups($dbh);


// AJAX commands
$cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    ?: filter_input(INPUT_GET, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($cmd) {

    $out = [];

    try {
        $input = ($cmd === 'save') ? INPUT_POST : INPUT_GET;
        $group = (string) filter_input($input, 'grp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!isset($groups[$group])) {
            throw new \RuntimeException('Choose a category group.');
        }

        switch ($cmd) {

            case 'load':
                $out['categories'] = array_values(CategoryEditor::load($dbh, $group));
                break;

            case 'save':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $out['error'] = 'Saving requires a POST request.';
                    break;
                }

                [$vals, $errors] = CategoryEditor::validate(CategoryEditor::load($dbh, $group), $_POST);

                if (count($errors) > 0) {
                    $out['errors'] = $errors;
                    $out['error'] = 'Please correct the highlighted fields.';
                    break;
                }

                $out['success'] = CategoryEditor::save($dbh, $group, $vals);
                $out['categories'] = array_values(CategoryEditor::load($dbh, $group));

                // NameEdit reads categories from the session cache.
                $wInit->reloadSessionVolLkUps();
                break;

            default:
                $out['error'] = 'Unknown command.';
        }

        $out['group'] = $group;

    } catch (\Exception $ex) {
        $out['error'] = $ex->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($out);
    exit();
}


// Page render
$group = (string) filter_input(INPUT_GET, 'grp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!isset($groups[$group])) {
    $group = (string) array_key_first($groups);
}

$initial = [
    'group' => $group,
    'categories' => ($group === '' ? [] : array_values(CategoryEditor::load($dbh, $group))),
];

$groupOpt = HTMLSelector::doOptionsMkup(array_map(fn($c, $d) => [$c, $d], array_keys($groups), $groups), $group, false);

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CATEGORY_EDIT_JS; ?>"></script>
    </head>
    <body <?php if ($wInit->testVersion) { echo "class='testbody'"; } ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>

            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3" style="width: 500px;">
            <?php if (count($groups) === 0) { ?>
                <p>No category groups are set up.</p>
            <?php } else { ?>
                <form id="catForm" class="cf-form" novalidate
                      data-initial="<?php echo htmlspecialchars(json_encode($initial), ENT_QUOTES); ?>">
                    <input type="hidden" name="cmd" value="save" />

                    <div class="cf-field">
                        <label for="selGroup">Category Group</label>
                        <select id="selGroup" name="grp"><?php echo $groupOpt; ?></select>
                    </div>

                    <table id="catTable" class="hhk-tdbox ce-table mb-2">
                        <thead>
                            <tr>
                                <th scope="col">Code</th>
                                <th scope="col" id="catCount">0 Entries</th>
                                <th scope="col">Members</th>
                            </tr>
                        </thead>
                        <tbody id="catRows"></tbody>
                    </table>
                    <div class="cf-actions">
                        <input type="button" id="btnReset" value="Reset" class="ui-button ui-widget ui-corner-all" />
                        <input type="submit" id="btnSave" value="Save" class="ui-button ui-widget ui-corner-all" />
                    </div>
                </form>
            <?php } ?>
            </div>
        </div>
    </body>
</html>
