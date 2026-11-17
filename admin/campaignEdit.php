<?php

use HHK\Common;
use HHK\Donation\Campaign;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Session;
use HHK\sec\WebInit;

/**
 * campaignEdit.php
 *
 * Create and edit donation campaigns.  The page renders a stacked form; loading
 * and saving are done over AJAX against this same script (cmd=load / cmd=save).
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

/** gen_lookups rows -> [code, description] pairs, so doOptionsMkup does not group on Substitute. */
function lookupOptions(array $lookups, string $selected): string {
    $pairs = array_map(fn($r) => [$r['Code'], $r['Description']], $lookups);
    return HTMLSelector::doOptionsMkup($pairs, $selected, false);
}


$wInit = new WebInit();
$dbh = $wInit->dbh;
$uS = Session::getInstance();

$campTypes = Common::readGenLookupsPDO($dbh, 'Campaign_Type', 'Description');
$campStatuses = Common::readGenLookupsPDO($dbh, 'Campaign_Status');


// AJAX commands
$cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    ?: filter_input(INPUT_GET, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($cmd) {

    $out = [];

    try {
        switch ($cmd) {

            case 'load':
                $code = (string) filter_input(INPUT_GET, 'cp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $out['campaign'] = (new Campaign($dbh, $code))->toArray();
                break;

            case 'save':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $out['error'] = 'Saving requires a POST request.';
                    break;
                }

                [$vals, $errors] = Campaign::validate($_POST, $campTypes, $campStatuses);

                if (count($errors) > 0) {
                    $out['errors'] = $errors;
                    $out['error'] = 'Please correct the highlighted fields.';
                    break;
                }

                $postedCode = (string) filter_input(INPUT_POST, 'campCode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $isNew = ($postedCode === '' || $postedCode === Campaign::NEW_CODE);

                $code = Campaign::saveCampaign($dbh, $postedCode, $vals, $uS->username);

                $out['success'] = $isNew ? 'Campaign created.' : 'Campaign saved.';
                $out['campaign'] = (new Campaign($dbh, $code))->toArray();
                $out['options'] = Campaign::CampaignSelOptionMarkup($dbh, $code, TRUE, TRUE);
                break;

            default:
                $out['error'] = 'Unknown command.';
        }
    } catch (\Exception $ex) {
        $out['error'] = $ex->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($out);
    exit();
}


// Page render
$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

$campCode = (string) filter_input(INPUT_GET, 'cp', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$initial = (new Campaign($dbh, $campCode))->toArray();

$campOpt = Campaign::CampaignSelOptionMarkup($dbh, ($initial['campCode'] === Campaign::NEW_CODE ? '' : $initial['campCode']), TRUE, TRUE);
$typeOpt = lookupOptions($campTypes, $initial['type']);
$statusOpt = lookupOptions($campStatuses, $initial['status']);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
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
        <script type="text/javascript" src="<?php echo CAMPAIGN_EDIT_JS; ?>"></script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">

            <h1><?php echo $wInit->pageHeading; ?></h1>

            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <form id="campForm" class="cf-form" novalidate
                      data-campaign="<?php echo htmlspecialchars(json_encode($initial), ENT_QUOTES); ?>">
                    <input type="hidden" name="cmd" value="save" />

                    <div class="cf-field">
                        <label for="selCamp">Campaign</label>
                        <select id="selCamp" name="campCode"><?php echo $campOpt; ?></select>
                        <p class="cf-help">Choose "New" to create a campaign.</p>
                    </div>

                    <div class="hhk-flex hhk-flex-wrap cf-groups">
                        <fieldset>
                            <legend>Details</legend>

                            <div class="cf-field">
                                <label for="txtTitle">Title<span class="cf-req" aria-hidden="true">*</span></label>
                                <input type="text" id="txtTitle" name="title" maxlength="250" required aria-describedby="err-title" />
                                <div class="cf-error" id="err-title" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="txtStart">Start Date<span class="cf-req" aria-hidden="true">*</span></label>
                                <input type="date" id="txtStart" name="startDate" required aria-describedby="err-startDate" />
                                <div class="cf-error" id="err-startDate" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="txtEnd">End Date<span class="cf-req" aria-hidden="true">*</span></label>
                                <input type="date" id="txtEnd" name="endDate" required aria-describedby="err-endDate" />
                                <div class="cf-error" id="err-endDate" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="selStatus">Status<span class="cf-req" aria-hidden="true">*</span></label>
                                <select id="selStatus" name="status" required aria-describedby="err-status"><?php echo $statusOpt; ?></select>
                                <div class="cf-error" id="err-status" role="alert"></div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Donations</legend>

                            <div class="cf-field">
                                <label for="selType">Type<span class="cf-req" aria-hidden="true">*</span></label>
                                <select id="selType" name="type" required aria-describedby="err-type"><?php echo $typeOpt; ?></select>
                                <div class="cf-error" id="err-type" role="alert"></div>
                            </div>

                            <div class="cf-field" id="percentField" hidden>
                                <label for="txtPercent">Percentage<span class="cf-req" aria-hidden="true">*</span></label>
                                <div class="cf-prefix">
                                    <input type="text" id="txtPercent" name="percent" inputmode="decimal" aria-describedby="err-percent" /><span>%</span>
                                </div>
                                <div class="cf-error" id="err-percent" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="txtMin">Minimum Donation</label>
                                <div class="cf-prefix"><span>$</span>
                                    <input type="text" id="txtMin" name="minDonation" class="hhk-money" inputmode="decimal" aria-describedby="help-min err-minDonation" />
                                </div>
                                <p class="cf-help" id="help-min">Leave blank for no minimum.</p>
                                <div class="cf-error" id="err-minDonation" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="txtMax">Maximum Donation</label>
                                <div class="cf-prefix"><span>$</span>
                                    <input type="text" id="txtMax" name="maxDonation" class="hhk-money" inputmode="decimal" aria-describedby="help-max err-maxDonation" />
                                </div>
                                <p class="cf-help" id="help-max">Leave blank for no maximum.</p>
                                <div class="cf-error" id="err-maxDonation" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="txtTarget">Goal</label>
                                <div class="cf-prefix"><span>$</span>
                                    <input type="text" id="txtTarget" name="target" class="hhk-money" inputmode="decimal" aria-describedby="err-target" />
                                </div>
                                <div class="cf-error" id="err-target" role="alert"></div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Reporting</legend>

                            <div class="cf-field">
                                <label for="txtCat">Category</label>
                                <input type="text" id="txtCat" name="category" maxlength="15" aria-describedby="err-category" />
                                <div class="cf-error" id="err-category" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="txtMergeCode">Mail Merge Code</label>
                                <input type="text" id="txtMergeCode" name="mergeCode" maxlength="15" aria-describedby="err-mergeCode" />
                                <div class="cf-error" id="err-mergeCode" role="alert"></div>
                            </div>

                            <div class="cf-field">
                                <label for="txtDesc">Description</label>
                                <textarea id="txtDesc" name="description" maxlength="250" rows="3" aria-describedby="err-description"></textarea>
                                <div class="cf-error" id="err-description" role="alert"></div>
                            </div>
                        </fieldset>
                    </div>

                    <p class="cf-meta" id="campMeta"></p>

                    <div class="cf-actions">
                        <input type="button" id="btnReset" value="Reset" class="ui-button ui-widget ui-corner-all" />
                        <input type="submit" id="btnSave" value="Save" class="ui-button ui-widget ui-corner-all" />
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
