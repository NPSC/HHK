<?php

use HHK\Common;
use HHK\HTMLControls\{HTMLInput, HTMLSelector, HTMLTable};
use HHK\SysConst\CampaignType;
use HHK\Tables\EditRS;
use HHK\Tables\Donate\CampaignRS;
use HHK\sec\WebInit;
use HHK\Vite\Vite;

/**
 * campaignEdit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

 require ("AdminIncludes.php");

/**
 * Builds the editable table of all campaigns.
 */
function getCampaignsTable(\PDO $dbh): HTMLTable {

    $campRS = new CampaignRS();
    $rows = EditRS::select($dbh, $campRS, [], 'and', [$campRS->Status, $campRS->Title]);

    $types = Common::readGenLookupsPDO($dbh, 'Campaign_Type');
    $statuses = Common::readGenLookupsPDO($dbh, 'Campaign_Status');

    $tbl = new HTMLTable();

    $tbl->addHeaderTr(
        HTMLTable::makeTh('ID')
        . HTMLTable::makeTh('Title')
        . HTMLTable::makeTh('Start Date')
        . HTMLTable::makeTh('End Date')
        . HTMLTable::makeTh('Min. Donation')
        . HTMLTable::makeTh('Max. Donation')
        . HTMLTable::makeTh('Goal')
        . HTMLTable::makeTh('Type')
        . HTMLTable::makeTh('Percent')
        . HTMLTable::makeTh('Status')
        . HTMLTable::makeTh('Category')
        . HTMLTable::makeTh('Mail Merge Code')
        . HTMLTable::makeTh('Description')
        . HTMLTable::makeTh('Last Updated')
        . HTMLTable::makeTh('Updated By')
    );

    foreach ($rows as $r) {

        $code = $r['Campaign_Code'];

        $tbl->addBodyTr(
            HTMLTable::makeTd($code)
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Title'], ['name' => 'campTitle[' . $code . ']', 'size' => '25']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Start_Date'] != '' ? date('m/d/Y', strtotime($r['Start_Date'])) : '', ['name' => 'campStart[' . $code . ']', 'class' => 'ckdate', 'size' => '10']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['End_Date'] != '' ? date('m/d/Y', strtotime($r['End_Date'])) : '', ['name' => 'campEnd[' . $code . ']', 'class' => 'ckdate', 'size' => '10']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Min_Donation'] > 0 ? number_format($r['Min_Donation'], 2) : '', ['name' => 'campMin[' . $code . ']', 'size' => '8', 'class' => 'hhk-money']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Max_Donation'] > 0 ? number_format($r['Max_Donation'], 2) : '', ['name' => 'campMax[' . $code . ']', 'size' => '8', 'class' => 'hhk-money']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Target'] > 0 ? number_format($r['Target'], 2) : '', ['name' => 'campTarget[' . $code . ']', 'size' => '8', 'class' => 'hhk-money']))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($types, $r['Campaign_Type'], false), ['name' => 'campType[' . $code . ']', 'class' => 'campTypeSel']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Percent_Cut'] > 0 ? number_format($r['Percent_Cut'], 2) : '', ['name' => 'campPercent[' . $code . ']', 'size' => '6', 'class' => 'hhk-money']))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($statuses, $r['Status'], false), ['name' => 'campStatus[' . $code . ']']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Category'], ['name' => 'campCat[' . $code . ']', 'size' => '10']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Campaign_Merge_Code'], ['name' => 'campMergeCode[' . $code . ']', 'size' => '10']))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Description'], ['name' => 'campDesc[' . $code . ']', 'size' => '25']))
            . HTMLTable::makeTd($r['Last_Updated'] != '' ? date('M j, Y', strtotime($r['Last_Updated'])) : '')
            . HTMLTable::makeTd($r['Updated_By']),
            ['data-code' => $code]
        );
    }

    // New campaign row
    $tbl->addBodyTr(
        HTMLTable::makeTd('New')
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campTitle[0]', 'size' => '25', 'placeholder' => 'New Campaign...']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campStart[0]', 'class' => 'ckdate', 'size' => '10']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campEnd[0]', 'class' => 'ckdate', 'size' => '10']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campMin[0]', 'size' => '8', 'class' => 'hhk-money']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campMax[0]', 'size' => '8', 'class' => 'hhk-money']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campTarget[0]', 'size' => '8', 'class' => 'hhk-money']))
        . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($types, CampaignType::Normal, false), ['name' => 'campType[0]', 'class' => 'campTypeSel']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campPercent[0]', 'size' => '6', 'class' => 'hhk-money']))
        . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($statuses, 'a', false), ['name' => 'campStatus[0]']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campCat[0]', 'size' => '10']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campMergeCode[0]', 'size' => '10']))
        . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name' => 'campDesc[0]', 'size' => '25']))
        . HTMLTable::makeTd('')
        . HTMLTable::makeTd(''),
        ['data-code' => '0']
    );

    return $tbl;
}

/**
 * Validates and saves one campaign row.
 *
 * @return array{ok:bool,errors?:array<string,string[]>,row?:array{code:string,lastUpdated:string,updatedBy:string}}
 * Never deletes - Campaign_Code is referenced elsewhere (donations, activity) so rows are
 * only ever added or edited.
 */
function saveCampaignRow(\PDO $dbh, string $campCode, array $row): array {

    $campRS = new CampaignRS();

    if ($campCode !== '0' && $campCode !== '') {

        $campRS->Campaign_Code->setStoredVal($campCode);
        $cRows = EditRS::select($dbh, $campRS, [$campRS->Campaign_Code]);

        if (count($cRows) > 0) {
            EditRS::loadRow($cRows[0], $campRS);
        } else {
            return ['ok' => false, 'errors' => ['general' => ['Campaign Code "' . $campCode . '" was not found.']]];
        }
    }

    $errors = [];

    $title = trim(filter_var($row['title'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    if ($title === '') {
        $errors['title'] = ['A Title is required for every campaign.'];
    } else {
        $campRS->Title->setNewVal($title);
    }

    $type = filter_var($row['type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $campRS->Campaign_Type->setNewVal($type != '' ? $type : CampaignType::Normal);

    $stDateStr = filter_var($row['sdate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $enDateStr = filter_var($row['edate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $stDate = null;
    $endDate = null;

    if ($stDateStr === '') {
        $errors['sdate'] = ['A Start date is required.'];
    } else {
        try {
            $stDate = new \DateTime($stDateStr);
        } catch (\Exception $ex) {
            $errors['sdate'] = ['Undecipherable Start date.'];
        }
    }

    if ($enDateStr === '') {
        $errors['edate'] = ['An End date is required.'];
    } else {
        try {
            $endDate = new \DateTime($enDateStr);
        } catch (\Exception $ex) {
            $errors['edate'] = ['Undecipherable End date.'];
        }
    }

    if ($stDate !== null && $endDate !== null) {
        if ($stDate > $endDate) {
            $errors['edate'] = ['The End date must be after the Start date.'];
        } else {
            $campRS->Start_Date->setNewVal($stDate->format('Y-m-d'));
            $campRS->End_Date->setNewVal($endDate->format('Y-m-d'));
        }
    }

    $min = (float) filter_var($row['min'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $max = (float) filter_var($row['max'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    if ($min < 0) {
        $errors['min'] = ['Use only positive values for the minimum donation.'];
    }

    if ($max < 0) {
        $errors['max'] = ['Use only positive values for the maximum donation.'];
    }

    if (!isset($errors['min']) && !isset($errors['max']) && $max > 0 && $min > $max) {
        $errors['min'] = ['The minimum donation must be less than the maximum.'];
        $errors['max'] = ['The minimum donation must be less than the maximum.'];
    }

    if (count($errors) > 0) {
        return ['ok' => false, 'errors' => $errors];
    }

    $campRS->Min_Donation->setNewVal($min);
    $campRS->Max_Donation->setNewVal($max);
    $campRS->Target->setNewVal(filter_var($row['target'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    $campRS->Percent_Cut->setNewVal(filter_var($row['percent'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    $campRS->Status->setNewVal(filter_var($row['status'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $campRS->Category->setNewVal(filter_var($row['cat'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $campRS->Campaign_Merge_Code->setNewVal(filter_var($row['mergeCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $campRS->Description->setNewVal(filter_var($row['desc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    if ($campCode === '0' || $campCode === '') {
        $rptId = Common::incCounter($dbh, 'codes');
        $newCode = 'cp' . $rptId;
        $campRS->Campaign_Code->setNewVal($newCode);
        EditRS::insert($dbh, $campRS);
    } else {
        $newCode = $campCode;
        EditRS::update($dbh, $campRS, [$campRS->Campaign_Code]);
    }

    // Re-select so Last_Updated/Updated_By reflect what the trigger/DB actually stored.
    $freshRS = new CampaignRS();
    $freshRS->Campaign_Code->setStoredVal($newCode);
    $freshRows = EditRS::select($dbh, $freshRS, [$freshRS->Campaign_Code]);
    $fresh = $freshRows[0] ?? [];

    return [
        'ok' => true,
        'row' => [
            'code' => $newCode,
            'lastUpdated' => !empty($fresh['Last_Updated']) ? date('M j, Y', strtotime($fresh['Last_Updated'])) : '',
            'updatedBy' => $fresh['Updated_By'] ?? '',
        ],
    ];
}

$wInit = new WebInit();
$dbh = $wInit->dbh;

$menuMarkup = $wInit->generatePageMenu();

// form save button: always answers with JSON and stops before the HTML page renders.
if (filter_has_var(INPUT_POST, "bttncamp")) {

    $titles = $_POST['campTitle'] ?? [];
    $errors = [];
    $saved = [];

    foreach ($titles as $code => $title) {

        $code = (string) $code;

        if ($code === '0' && trim(filter_var($title, FILTER_SANITIZE_FULL_SPECIAL_CHARS)) === '') {
            continue;
        }

        $row = [
            'title' => $title,
            'type' => $_POST['campType'][$code] ?? '',
            'sdate' => $_POST['campStart'][$code] ?? '',
            'edate' => $_POST['campEnd'][$code] ?? '',
            'min' => $_POST['campMin'][$code] ?? '',
            'max' => $_POST['campMax'][$code] ?? '',
            'target' => $_POST['campTarget'][$code] ?? '',
            'percent' => $_POST['campPercent'][$code] ?? '',
            'status' => $_POST['campStatus'][$code] ?? '',
            'cat' => $_POST['campCat'][$code] ?? '',
            'mergeCode' => $_POST['campMergeCode'][$code] ?? '',
            'desc' => $_POST['campDesc'][$code] ?? '',
        ];

        try {
            $result = saveCampaignRow($dbh, $code, $row);
        } catch (\Exception $ex) {
            $result = ['ok' => false, 'errors' => ['general' => [$ex->getMessage()]]];
        }

        if ($result['ok']) {
            $saved[$code] = $result['row'];
        } else {
            $errors[$code] = $result['errors'];
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => count($errors) === 0, 'errors' => $errors, 'saved' => $saved]);
    exit;
}

$campaignsTable = getCampaignsTable($dbh);

?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>

        <?php echo Vite::asset(['resources/js/admin.js', 'resources/js/admin/campaignEdit.js']); ?>

        <?php echo FAVICON; ?>

        <style>
            #campaignsTbl table td, #campaignsTbl table th {
                padding: 4px 8px;
                border: 1px solid #ddd;
            }
            #campaignsTbl table {
                border-collapse: collapse;
            }
            #campaignsTbl input.ui-state-error, #campaignsTbl select.ui-state-error {
                background: #fef1ec;
                border-color: #cd0a0a;
            }
        </style>
    </head>
    <body <?php if ($wInit->testVersion) {
            echo "class='testbody'";
        } ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">

            <h1><?php echo $wInit->pageHeading; ?></h1>

            <div id="campErrors" class="ui-state-error ui-corner-all p-2 mb-2" style="display:none;"></div>

            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <form id="campForm" name="campForm" action="campaignEdit.php" method="post">
                    <div id="campaignsTbl" style="overflow-x:auto;">
                        <?php echo $campaignsTable->generateMarkup(); ?>
                    </div>
                    <div class="hhk-flex mt-1" style="justify-content: space-evenly;">
                        <input id="bttncamp" name="bttncamp" type="submit" value="Save" class="ui-button ui-widget ui-corner-all"/>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
