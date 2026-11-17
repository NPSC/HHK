<?php

use HHK\Common;
use HHK\Donation\Campaign;
use HHK\HTMLControls\HTMLSelector;
use HHK\SysConst\CampaignType;
use HHK\Tables\EditRS;
use HHK\Tables\Donate\CampaignRS;
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

const CAMPAIGN_NEW = 'vNew';

/**
 * Convert a campaign into the plain values the form displays.
 *
 * Text columns are stored HTML-encoded, so they are decoded here and
 * re-encoded on output by whoever renders them.
 */
function campaignToArray(Campaign $campaign): array {

    $dec = fn($v) => html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $amt = fn($v) => ((float) $v > 0 ? number_format((float) $v, 2, '.', '') : '');
    $date = fn($v, $fmt) => ($v ? date($fmt, strtotime($v)) : '');

    $exists = $campaign->get_idcampaign() > 0;

    return [
        'campCode'    => $exists ? $campaign->get_campaigncode() : CAMPAIGN_NEW,
        'title'       => $dec($campaign->get_title()),
        'startDate'   => $date($campaign->get_startdate(), 'Y-m-d'),
        'endDate'     => $date($campaign->get_enddate(), 'Y-m-d'),
        'status'      => $exists ? (string) $campaign->get_status() : 'a',
        'type'        => ($exists && $campaign->get_type() != '') ? (string) $campaign->get_type() : CampaignType::Normal,
        'percent'     => ((float) $campaign->get_percentCut() > 0 ? (string) (float) $campaign->get_percentCut() : ''),
        'minDonation' => $amt($campaign->get_mindonation()),
        'maxDonation' => $amt($campaign->get_maxdonation()),
        'target'      => $amt($campaign->get_target()),
        'category'    => $dec($campaign->get_category()),
        'mergeCode'   => $dec($campaign->get_mergecode()),
        'description' => $dec($campaign->get_description()),
        'lastUpdated' => $date($campaign->get_lastupdated(), 'M j, Y g:i a'),
        'updatedBy'   => $exists ? (string) $campaign->get_updatedby() : '',
    ];
}

/**
 * Validate the posted form.
 *
 * @return array{0: array, 1: array<string,string>} [clean values, errors keyed by field name]
 */
function validateCampaign(array $in, array $types, array $statuses): array {

    $errors = [];
    $vals = [];

    $str = fn(string $k) => trim((string) ($in[$k] ?? ''));

    // Text fields are stored HTML-encoded (existing convention), so lengths are checked after encoding.
    $text = function (string $k, string $label, int $max, bool $required) use ($str, &$errors, &$vals) {
        $raw = $str($k);
        $enc = filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($required && $raw === '') {
            $errors[$k] = "$label is required.";
        } else if (mb_strlen($enc) > $max) {
            $errors[$k] = "$label must be $max characters or fewer.";
        }
        $vals[$k] = $enc;
    };

    // Optional dollar amount: blank = 0, otherwise a non-negative number with up to 2 decimals.
    $amount = function (string $k, string $label) use ($str, &$errors, &$vals) {
        $raw = str_replace(['$', ',', ' '], '', $str($k));
        if ($raw === '') {
            $vals[$k] = 0;
        } else if (preg_match('/^\d{1,13}(\.\d{1,2})?$/', $raw) !== 1) {
            $errors[$k] = "$label must be a positive dollar amount, like 25 or 25.00.";
        } else {
            $vals[$k] = (float) $raw;
        }
    };

    $date = function (string $k, string $label) use ($str, &$errors): ?\DateTime {
        $raw = $str($k);
        if ($raw === '') {
            $errors[$k] = "$label is required.";
            return null;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $raw);
        $warn = \DateTime::getLastErrors();
        if ($d === false || ($warn !== false && ($warn['warning_count'] > 0 || $warn['error_count'] > 0))) {
            $errors[$k] = "$label is not a valid date.";
            return null;
        }
        return $d;
    };

    $text('title', 'Title', 250, true);
    $text('category', 'Category', 15, false);
    $text('mergeCode', 'Mail merge code', 15, false);
    $text('description', 'Description', 250, false);

    $start = $date('startDate', 'Start date');
    $end = $date('endDate', 'End date');
    if ($start && $end && $start > $end) {
        $errors['endDate'] = 'End date must be on or after the start date.';
    }
    $vals['startDate'] = $start ? $start->format('Y-m-d') : null;
    $vals['endDate'] = $end ? $end->format('Y-m-d') : null;

    $status = $str('status');
    if (!isset($statuses[$status])) {
        $errors['status'] = 'Choose a status.';
    }
    $vals['status'] = $status;

    $type = $str('type');
    if (!isset($types[$type])) {
        $errors['type'] = 'Choose a campaign type.';
    }
    $vals['type'] = $type;

    $amount('minDonation', 'Minimum donation');
    $amount('maxDonation', 'Maximum donation');
    $amount('target', 'Goal');

    if (!isset($errors['minDonation']) && !isset($errors['maxDonation'])
            && $vals['maxDonation'] > 0 && $vals['minDonation'] > $vals['maxDonation']) {
        $errors['maxDonation'] = 'Maximum donation must be greater than or equal to the minimum.';
    }

    // Percent only applies to percentage campaigns.
    $vals['percent'] = 0;
    if ($type === CampaignType::Percent) {
        $pct = str_replace(['%', ' '], '', $str('percent'));
        if (preg_match('/^\d{1,3}(\.\d{1,3})?$/', $pct) !== 1 || (float) $pct <= 0 || (float) $pct > 100) {
            $errors['percent'] = 'Enter a percentage greater than 0 and no more than 100, with up to 3 decimal places.';
        } else {
            $vals['percent'] = (float) $pct;
        }
    }

    return [$vals, $errors];
}

/**
 * Insert or update a campaign from validated values.
 *
 * @return string the campaign code
 */
function saveCampaign(\PDO $dbh, string $campCode, array $v, string $userName): string {

    $campRS = new CampaignRS();
    $isNew = ($campCode === '' || $campCode === CAMPAIGN_NEW);

    if (!$isNew) {
        $campRS->Campaign_Code->setStoredVal($campCode);
        $rows = EditRS::select($dbh, $campRS, [$campRS->Campaign_Code]);
        if (count($rows) === 0) {
            throw new \RuntimeException('That campaign was not found. It may have been deleted.');
        }
        EditRS::loadRow($rows[0], $campRS);
    }

    $campRS->Title->setNewVal($v['title']);
    $campRS->Start_Date->setNewVal($v['startDate']);
    $campRS->End_Date->setNewVal($v['endDate']);
    $campRS->Status->setNewVal($v['status']);
    $campRS->Campaign_Type->setNewVal($v['type']);
    $campRS->Percent_Cut->setNewVal($v['percent']);
    $campRS->Min_Donation->setNewVal($v['minDonation']);
    $campRS->Max_Donation->setNewVal($v['maxDonation']);
    $campRS->Target->setNewVal($v['target']);
    $campRS->Category->setNewVal($v['category']);
    $campRS->Campaign_Merge_Code->setNewVal($v['mergeCode']);
    $campRS->Description->setNewVal($v['description']);
    $campRS->Updated_By->setNewVal($userName);
    $campRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

    if ($isNew) {

        $dbh->prepare("CALL IncrementCounter('codes', @num);")->execute();
        $rptId = (int) $dbh->query("SELECT @num")->fetchColumn();

        if ($rptId === 0) {
            throw new \RuntimeException('The campaign code counter is not set up.');
        }

        $campCode = 'cp' . $rptId;
        $campRS->Campaign_Code->setNewVal($campCode);
        EditRS::insert($dbh, $campRS);

    } else {
        EditRS::update($dbh, $campRS, [$campRS->Campaign_Code]);
    }

    return $campCode;
}

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
                $out['campaign'] = campaignToArray(new Campaign($dbh, $code));
                break;

            case 'save':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $out['error'] = 'Saving requires a POST request.';
                    break;
                }

                [$vals, $errors] = validateCampaign($_POST, $campTypes, $campStatuses);

                if (count($errors) > 0) {
                    $out['errors'] = $errors;
                    $out['error'] = 'Please correct the highlighted fields.';
                    break;
                }

                $postedCode = (string) filter_input(INPUT_POST, 'campCode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $isNew = ($postedCode === '' || $postedCode === CAMPAIGN_NEW);

                $code = saveCampaign($dbh, $postedCode, $vals, $uS->username);

                $out['success'] = $isNew ? 'Campaign created.' : 'Campaign saved.';
                $out['campaign'] = campaignToArray(new Campaign($dbh, $code));
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
$initial = campaignToArray(new Campaign($dbh, $campCode));

$campOpt = Campaign::CampaignSelOptionMarkup($dbh, ($initial['campCode'] === CAMPAIGN_NEW ? '' : $initial['campCode']), TRUE, TRUE);
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
