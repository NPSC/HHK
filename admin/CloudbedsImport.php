<?php

use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\House\Hospital\Hospital;
use HHK\sec\{Labels, Session, WebInit};
use HHK\SysConst\HospitalType;
use HHK\Admin\Import\Cloudbeds\{CloudbedsClient, CloudbedsConfig, CloudbedsConfigStore, CloudbedsFieldMapper, CloudbedsImport, CloudbedsStaging, CloudbedsValueMaps};

/**
 * CloudbedsImport.php
 *
 * Import people, reservations/visits and folios (invoices/payments) from Cloudbeds. See CloudbedsImport class.
 * The connection and the import settings are saved in the database, see CloudbedsConfigStore.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();

/**
 * @param mixed $value
 */
function cbEsc($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES);
}

$store = new CloudbedsConfigStore($dbh);
$staging = new CloudbedsStaging($dbh);
$staging->ensureTables();

$resultMsg = '';
$errorMsg = '';

// save the connection
if (filter_has_var(INPUT_POST, 'btnSaveConnection')) {
    try {
        $resultMsg = $store->saveConnection([
            'organizationId' => $_POST['organizationId'] ?? '',
            'propertyIds' => $_POST['propertyIds'] ?? '',
            'baseUrl' => $_POST['baseUrl'] ?? '',
            'apiKey' => $_POST['apiKey'] ?? '',
            'accessToken' => $_POST['accessToken'] ?? '',
        ], $uS->username);
    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}

// save the import settings and custom field mapping. Everything is checked before anything is saved.
if (filter_has_var(INPUT_POST, 'btnSaveSettings')) {
    try {
        $settings = CloudbedsConfigStore::settingsFromForm($_POST, $store->load());
        CloudbedsConfig::validateSettings($settings);

        $fieldMapRows = CloudbedsConfigStore::fieldMapFromForm($_POST);
        CloudbedsConfigStore::validateFieldMap($fieldMapRows);

        $valueMapRows = CloudbedsValueMaps::fromForm($_POST);
        CloudbedsValueMaps::validate($valueMapRows);

        $store->saveSettings($settings, $uS->username);
        $mappingCount = count($fieldMapRows) > 0 ? $store->saveFieldMap($fieldMapRows) : 0;
        $mappingCount += count($valueMapRows) > 0 ? $store->saveValueMaps($valueMapRows, $uS->username) : 0;

        $resultMsg = 'Import settings saved.' . (count($fieldMapRows) + count($valueMapRows) > 0 ? "  $mappingCount mapping(s) saved." : '');
    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}

// load the config and importer
try {
    $saved = $store->load();
    $fieldMap = $store->loadFieldMap();
    $valueMaps = $store->loadValueMaps();
} catch (\Throwable $e) {
    $saved = [];
    $fieldMap = [];
    $valueMaps = [];
    $errorMsg .= ($errorMsg !== '' ? ' ' : '') . $e->getMessage();
}
$configError = '';
$import = null;
$config = null;

try {
    $config = new CloudbedsConfig($saved);
    $import = new CloudbedsImport($dbh, $config);
} catch (\Throwable $e) {
    $configError = $e->getMessage();
}

// ajax commands
if (filter_has_var(INPUT_POST, "cmd")) {
    $cmd = filter_input(INPUT_POST, "cmd", FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($import === null) {
        echo json_encode(["error" => $configError]);
        exit;
    }

    try {
        switch ($cmd) {
            case 'fetch':
                $return = $import->fetch(20);
                break;
            case 'startImport':
                $limit = max(1, min(500, intval(filter_input(INPUT_POST, "limit", FILTER_SANITIZE_NUMBER_INT))));
                $return = $import->startImport($limit);
                break;
            case 'retryFailed':
                $return = ["success" => $import->getStaging()->retryFailed() . " records will be retried"];
                break;
            case 'undo':
                $return = $import->undoImport();
                break;
            case 'resetStaging':
                $import->getStaging()->reset();
                $return = ["success" => "Staged Cloudbeds data has been discarded"];
                break;
            default:
                $return = ["error" => "Unknown command"];
        }
    } catch (\Throwable $e) {
        $return = ["error" => $e->getMessage()];
    }

    echo json_encode($return);
    exit;
}

// ------------------------------------------------------------------------------------------------
// markup
// ------------------------------------------------------------------------------------------------

// connection form
$connTbl = new HTMLTable();
$connTbl->addBodyTr(HTMLTable::makeTh('Organization Id') . HTMLTable::makeTd(
    HTMLInput::generateMarkup(cbEsc($saved['organizationId'] ?? ''), ['name' => 'organizationId', 'size' => '30'])
    . HTMLContainer::generateMarkup('span', 'Used by the Guest Profiles API (X-Organization-Id)', ['class' => 'ml-2'])));
$connTbl->addBodyTr(HTMLTable::makeTh('Property Ids') . HTMLTable::makeTd(
    HTMLInput::generateMarkup(cbEsc(implode(', ', $saved['propertyIds'] ?? [])), ['name' => 'propertyIds', 'size' => '30'])
    . HTMLContainer::generateMarkup('span', 'Comma separated, the properties to import', ['class' => 'ml-2'])));
$connTbl->addBodyTr(HTMLTable::makeTh('API Key') . HTMLTable::makeTd(
    HTMLInput::generateMarkup(($saved['apiKey'] ?? '') === '' ? '' : CloudbedsConfigStore::SECRET_PLACEHOLDER, ['type' => 'password', 'name' => 'apiKey', 'size' => '60', 'autocomplete' => 'off'])));
$connTbl->addBodyTr(HTMLTable::makeTh('Access Token') . HTMLTable::makeTd(
    HTMLInput::generateMarkup(($saved['accessToken'] ?? '') === '' ? '' : CloudbedsConfigStore::SECRET_PLACEHOLDER, ['type' => 'password', 'name' => 'accessToken', 'size' => '60', 'autocomplete' => 'off'])
    . HTMLContainer::generateMarkup('span', 'Optional, sent as a Bearer token instead of the API key', ['class' => 'ml-2'])));
$connTbl->addBodyTr(HTMLTable::makeTh('API Base URL') . HTMLTable::makeTd(
    HTMLInput::generateMarkup(cbEsc(($saved['baseUrl'] ?? '') !== '' ? $saved['baseUrl'] : CloudbedsConfig::DEFAULT_BASE_URL), ['name' => 'baseUrl', 'size' => '60'])));
$connMkup = $connTbl->generateMarkup();

// settings form: options, custom field mapping, value mappings
try {
    $mapper = new CloudbedsFieldMapper((array) ($saved['customFields'] ?? []), (string) ($saved['unmappedCustomFields'] ?? 'note'));
} catch (\Throwable $e) {
    $mapper = new CloudbedsFieldMapper();
    $errorMsg .= ($errorMsg !== '' ? ' ' : '') . 'Saved custom field mapping is invalid: ' . $e->getMessage();
}

$checkbox = function (string $name, bool $checked, string $label): string {
    $attrs = ['type' => 'checkbox', 'name' => $name, 'id' => str_replace(['[', ']'], '_', $name), 'value' => '1'];
    if ($checked) {
        $attrs['checked'] = 'checked';
    }
    return HTMLInput::generateMarkup('1', $attrs) . HTMLContainer::generateMarkup('label', ' ' . $label, ['for' => $attrs['id'], 'class' => 'ml-1 mr-3']);
};

$createMissing = (array) ($saved['createMissing'] ?? []);

// hospital dropdown, like the one in the hospital stay: active hospitals, plus the selected one even if it is inactive
$defaultHospitalId = (string) ($saved['defaultHospital'] ?? '');
$hospitalOptions = [];
foreach (Hospital::loadHospitals($dbh) as $h) {
    if ($h['Type'] == HospitalType::Hospital && ($h['Status'] == 'a' || (string) $h['idHospital'] === $defaultHospitalId)) {
        $hospitalOptions[] = [$h['idHospital'], cbEsc($h['Title'])];
    }
}
$hospitalLabel = Labels::getLabels()->getString('hospital', 'hospital', 'Hospital');

$setTbl = new HTMLTable();
$setTbl->addBodyTr(HTMLTable::makeTh('Default ' . cbEsc($hospitalLabel)) . HTMLTable::makeTd(
    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hospitalOptions, $defaultHospitalId, TRUE), ['name' => 'defaultHospital'])
    . HTMLContainer::generateMarkup('span', 'Used for reservations without a mapped ' . cbEsc(strtolower($hospitalLabel)) . ' field. Leave blank for none.', ['class' => 'ml-2'])));
$setTbl->addBodyTr(HTMLTable::makeTh('Create Missing') . HTMLTable::makeTd(
    $checkbox('createMissing[hospitals]', !empty($createMissing['hospitals']), 'Hospitals')
    . $checkbox('createMissing[rooms]', !empty($createMissing['rooms']), 'Rooms')
    . $checkbox('createMissing[genLookups]', !empty($createMissing['genLookups']), 'Lookup values (ethnicity, diagnosis, ...)')));
$setTbl->addBodyTr(HTMLTable::makeTh('Unmapped Custom Fields') . HTMLTable::makeTd(
    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup([['note', 'Keep as a note'], ['ignore', 'Ignore']], (string) ($saved['unmappedCustomFields'] ?? 'note'), FALSE), ['name' => 'unmappedCustomFields'])));
$setTbl->addBodyTr(HTMLTable::makeTh('Only Guests Who Stayed') . HTMLTable::makeTd(
    'From ' . HTMLInput::generateMarkup(cbEsc($saved['stayedFrom'] ?? ''), ['name' => 'stayedFrom', 'type' => 'date', 'id' => false])
    . ' to ' . HTMLInput::generateMarkup(cbEsc($saved['stayedTo'] ?? ''), ['name' => 'stayedTo', 'type' => 'date', 'id' => false])
    . HTMLContainer::generateMarkup('div', $checkbox('includeCurrentGuests', !empty($saved['includeCurrentGuests']), 'Also count a guest who is currently checked in (mid-stay)'), ['class' => 'mt-1'])
    . HTMLContainer::generateMarkup('div', 'Only guest profiles with a checked-out stay (checked-in too, if checked above) whose check-out date falls in this range, at the configured properties, are imported. Uses the Cloudbeds getGuestList endpoint. Leave a date blank for no limit on that side; leave both blank to import anyone who has ever checked out.', ['class' => 'mt-1'])));
$setTbl->addBodyTr(HTMLTable::makeTh('Guest Notes') . HTMLTable::makeTd(
    $checkbox('importGuestNotes', !empty($saved['importGuestNotes'] ?? true), 'Import the notes on guests as member notes (one extra Cloudbeds request per guest when fetching)')));

// custom field mapping, modeled on the CRM export field mapping: rows of HHK field <-> Cloudbeds custom field for each kind of custom field.
// The Cloudbeds fields come from the property's custom field definitions and from the fetched data.
$cbFields = [CloudbedsFieldMapper::SCOPE_GUEST => [], CloudbedsFieldMapper::SCOPE_RESERVATION => []];
$cbFieldsNote = '';

$addCbField = function (string $scope, array $field) use (&$cbFields): void {
    $key = CloudbedsFieldMapper::fieldKey($scope, $field);
    if ($key !== '' && !isset($cbFields[$scope][$key])) {
        $shortcode = trim((string) ($field['shortcode'] ?? ''));
        $name = trim((string) ($field['name'] ?? ''));
        $cbFields[$scope][$key] = ($name !== '' ? $name : $key) . ($shortcode !== '' && $shortcode !== $name ? " ($shortcode)" : '');
    }
};

// Cloudbeds values for the mapping tables: live from Cloudbeds when connected, plus what is in the fetched data
$cbRooms = [];
$cbMethods = [];
$liveNotes = [];

if ($config !== null) {
    $client = new CloudbedsClient($config, $dbh);

    try {
        foreach ($config->getPropertyIds() as $propertyId) {
            foreach ($client->getCustomFieldDefinitions($propertyId) as $def) {
                $addCbField(($def['applyTo'] ?? '') === 'guest' ? CloudbedsFieldMapper::SCOPE_GUEST : CloudbedsFieldMapper::SCOPE_RESERVATION, $def);
            }
        }
    } catch (\Throwable $e) {
        $liveNotes[] = 'custom fields: ' . $e->getMessage();
    }

    try {
        foreach ($config->getPropertyIds() as $propertyId) {
            foreach ($client->getRoomNames($propertyId) as $name) {
                $cbRooms[$name] = $name;
            }
        }
    } catch (\Throwable $e) {
        $liveNotes[] = 'rooms: ' . $e->getMessage();
    }

    try {
        foreach ($config->getPropertyIds() as $propertyId) {
            foreach ($client->getPaymentMethods($propertyId) as $method) {
                $key = trim((string) ($method['method'] ?? ''));
                if ($key !== '') {
                    $name = trim((string) ($method['name'] ?? ''));
                    $cbMethods[$key] = ($name !== '' && strtolower($name) !== strtolower($key)) ? "$name ($key)" : $key;
                }
            }
        }
    } catch (\Throwable $e) {
        $liveNotes[] = 'payment methods: ' . $e->getMessage();
    }
}

$cbFieldsNote = count($liveNotes) > 0 ? 'Could not load from Cloudbeds: ' . implode('; ', $liveNotes) . '.' : '';

$stagedValues = $staging->summarizeValues();
foreach ($stagedValues['rooms'] as $name) {
    $cbRooms[$name] ??= $name;
}
foreach ($stagedValues['paymentMethods'] as $method) {
    $known = array_filter(array_keys($cbMethods), fn($k) => CloudbedsValueMaps::normalize(CloudbedsValueMaps::PAYMENT_METHOD, (string) $k) === CloudbedsValueMaps::normalize(CloudbedsValueMaps::PAYMENT_METHOD, $method));
    if (count($known) === 0) {
        $cbMethods[$method] = $method;
    }
}
$cbChargeTypes = array_combine(CloudbedsValueMaps::CLOUDBEDS_CHARGE_TYPES, CloudbedsValueMaps::CLOUDBEDS_CHARGE_TYPES);
$knownChargeTypes = array_map(fn($t) => CloudbedsValueMaps::normalize(CloudbedsValueMaps::CHARGE_ITEM, $t), $cbChargeTypes);
foreach ($stagedValues['chargeTypes'] as $type) {
    if (!in_array(CloudbedsValueMaps::normalize(CloudbedsValueMaps::CHARGE_ITEM, $type), $knownChargeTypes, true)) {
        $cbChargeTypes[$type] = $type;
    }
}
$cbStatuses = array_combine(CloudbedsValueMaps::CLOUDBEDS_STATUSES, CloudbedsValueMaps::CLOUDBEDS_STATUSES);
foreach ($stagedValues['statuses'] as $status) {
    if (!isset($cbStatuses[CloudbedsValueMaps::normalize(CloudbedsValueMaps::RESERVATION_STATUS, $status)])) {
        $cbStatuses[$status] = $status;
    }
}
ksort($cbRooms, SORT_NATURAL | SORT_FLAG_CASE);
ksort($cbMethods, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($staging->summarizeCustomFields($mapper) as $f) {
    $addCbField($f['scope'], $f);
}
foreach ($cbFields as $scope => $fields) {
    asort($cbFields[$scope], SORT_NATURAL | SORT_FLAG_CASE);
}

$objectLabels = [
    CloudbedsFieldMapper::SCOPE_GUEST => 'Guest Custom Fields',
    CloudbedsFieldMapper::SCOPE_RESERVATION => 'Reservation Custom Fields',
];

// [[value, label, group], ...] for the HHK field dropdown, grouped like the CRM export
$hhkOptionsFor = function (string $scope): array {
    $options = [];
    foreach (CloudbedsFieldMapper::fieldsFor($scope) as $group => $fields) {
        foreach ($fields as $target => $label) {
            $options[] = [$target, cbEsc($label), $group];
        }
    }
    return $options;
};

$mapRow = function (string $scope, int $idx, string $hhk, string $crm) use ($cbFields, $hhkOptionsFor): string {
    $hhkSelect = HTMLSelector::generateMarkup(
        HTMLContainer::generateMarkup('option', '-- HHK field --', ['value' => '']) . HTMLSelector::doOptionsMkup($hhkOptionsFor($scope), $hhk, FALSE),
        ['name' => "fldmap_hhk[$scope][$idx]", 'id' => false, 'style' => 'max-width:240px;']
    );

    $fields = $cbFields[$scope];
    if ($crm !== '' && !isset($fields[$crm])) {
        $fields[$crm] = $crm;
    }

    if (count($fields) > 0) {
        $opts = HTMLContainer::generateMarkup('option', '-- Cloudbeds field --', ['value' => '']);
        foreach ($fields as $key => $label) {
            $attrs = ['value' => cbEsc($key)];
            if ((string) $key === $crm) {
                $attrs['selected'] = 'selected';
            }
            $opts .= HTMLContainer::generateMarkup('option', cbEsc($label), $attrs);
        }
        $crmInput = HTMLSelector::generateMarkup($opts, ['name' => "fldmap_crm[$scope][$idx]", 'id' => false, 'style' => 'max-width:300px;']);
    } else {
        $crmInput = HTMLInput::generateMarkup(cbEsc($crm), ['name' => "fldmap_crm[$scope][$idx]", 'id' => false, 'size' => '30', 'placeholder' => 'Cloudbeds field name or shortcode']);
    }

    return HTMLTable::makeTd($hhkSelect) . HTMLTable::makeTd($crmInput) . HTMLTable::makeTd(
        HTMLContainer::generateMarkup('ul',
            HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('span', '', ['class' => 'bi bi-trash3']), ['class' => 'hhk-fldmap-remove ui-corner-all ui-state-default p-1', 'title' => 'Remove mapping']),
            ['class' => 'ui-widget ui-helper-clearfix hhk-ui-icons']),
        ['class' => 'text-center actionBtns']);
};

$mappingMkup = '';
if ($store->getGatewayId() < 1) {
    $mappingMkup = HTMLContainer::generateMarkup('p', 'Save the Cloudbeds connection to map custom fields.');
} else {
    foreach ($objectLabels as $scope => $label) {
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('HHK Field') . HTMLTable::makeTh('Cloudbeds Field') . HTMLTable::makeTh(''));

        $idx = 0;
        foreach ($fieldMap[$scope] ?? [] as $pair) {
            $tbl->addBodyTr($mapRow($scope, $idx++, $pair['hhk'], $pair['crm']));
        }

        $addBtn = HTMLInput::generateMarkup('+ Add Mapping', [
            'type' => 'button', 'id' => "fldmap_add_$scope", 'class' => 'ui-button ui-corner-all ui-widget hhk-fldmap-addrow mt-2',
            'data-scope' => $scope, 'data-idx' => $idx,
            'data-hhkfields' => cbEsc(json_encode(array_map(fn($o) => ['value' => $o[0], 'label' => htmlspecialchars_decode($o[1], ENT_QUOTES), 'group' => $o[2]], $hhkOptionsFor($scope)))),
            'data-cbfields' => cbEsc(json_encode((object) $cbFields[$scope])),
        ]);

        $mappingMkup .= HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('h4', cbEsc($label))
            . HTMLInput::generateMarkup('1', ['type' => 'hidden', 'name' => "fldmap_posted[$scope]", 'id' => false])
            . HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), ['id' => "fldmap_tbl_$scope"])
            . $addBtn,
            ['class' => 'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']);
    }

    if ($cbFieldsNote !== '') {
        $mappingMkup = HTMLContainer::generateMarkup('p', cbEsc($cbFieldsNote) . ' Fields already fetched are still listed; you can also fetch first.', ['class' => 'ui-corner-all ui-state-highlight p-2']) . $mappingMkup;
    }
    $mappingMkup = HTMLContainer::generateMarkup('div', $mappingMkup, ['class' => 'hhk-flex flex-wrap']);
}

// room, payment method and reservation status mapping: a row for each Cloudbeds value with the HHK value to import it as
$hhkRooms = [];
foreach ($dbh->query("select `idResource`, `Title`, `Status` from `resource` where `Type` = 'room' order by `Title`")->fetchAll(\PDO::FETCH_ASSOC) as $r) {
    $hhkRooms[] = [$r['idResource'], $r['Title'], $r['Status']];
}

$savedValueMap = function (string $type) use ($valueMaps): array {
    $saved = [];
    foreach ($valueMaps[$type] ?? [] as $pair) {
        $saved[CloudbedsValueMaps::normalize($type, $pair['cb'])] = ['cb' => $pair['cb'], 'hhk' => $pair['hhk']];
    }
    return $saved;
};

/**
 * @param array<string,string> $sources Cloudbeds value => label
 * @param array<int, array{0: string, 1: string}> $hhkOptions [value, label]
 * @param callable(string): string $defaultFor the HHK value used when a Cloudbeds value isn't mapped
 */
$valueMapSection = function (string $type, string $title, string $help, array $sources, array $hhkOptions, string $blankLabel, callable $defaultFor) use ($savedValueMap): string {
    $saved = $savedValueMap($type);

    // the Cloudbeds values we know of, then saved mappings for values we don't
    $rows = [];
    foreach ($sources as $value => $label) {
        $rows[CloudbedsValueMaps::normalize($type, (string) $value)] = ['cb' => (string) $value, 'label' => $label];
    }
    foreach ($saved as $key => $pair) {
        $rows[$key] ??= ['cb' => $pair['cb'], 'label' => $pair['cb']];
    }

    $tbl = new HTMLTable();
    $tbl->addHeaderTr(HTMLTable::makeTh('Cloudbeds') . HTMLTable::makeTh('Import as') . HTMLTable::makeTh(''));

    $idx = 0;
    foreach ($rows as $key => $row) {
        $current = $saved[$key]['hhk'] ?? $defaultFor($row['cb']);
        $select = HTMLSelector::generateMarkup(
            HTMLContainer::generateMarkup('option', cbEsc($blankLabel), ['value' => '']) . HTMLSelector::doOptionsMkup(array_map(fn($o) => [$o[0], cbEsc($o[1])], $hhkOptions), $current, FALSE),
            ['name' => "valmap_hhk[$type][$idx]", 'id' => false, 'style' => 'max-width:300px;']
        );
        $tbl->addBodyTr(
            HTMLTable::makeTd(cbEsc($row['label']) . HTMLInput::generateMarkup(cbEsc($row['cb']), ['type' => 'hidden', 'name' => "valmap_cb[$type][$idx]", 'id' => false]))
            . HTMLTable::makeTd($select)
            . HTMLTable::makeTd('')
        );
        $idx++;
    }

    $addBtn = HTMLInput::generateMarkup('+ Add Value', [
        'type' => 'button', 'id' => "valmap_add_$type", 'class' => 'ui-button ui-corner-all ui-widget hhk-valmap-addrow mt-2',
        'data-type' => $type, 'data-idx' => $idx, 'data-blank' => cbEsc($blankLabel),
        'data-hhkoptions' => cbEsc(json_encode(array_map(fn($o) => ['value' => $o[0], 'label' => $o[1]], $hhkOptions))),
    ]);

    return HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('h4', cbEsc($title))
        . HTMLContainer::generateMarkup('p', cbEsc($help), ['class' => 'mb-1'])
        . HTMLInput::generateMarkup('1', ['type' => 'hidden', 'name' => "valmap_posted[$type]", 'id' => false])
        . HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), ['id' => "valmap_tbl_$type"])
        . $addBtn,
        ['class' => 'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']);
};

$savedRoomIds = array_column($savedValueMap(CloudbedsValueMaps::ROOM), 'hhk');
$roomOptions = [];
foreach ($hhkRooms as $r) {
    if ($r[2] == 'a' || in_array((string) $r[0], $savedRoomIds, true)) {
        $roomOptions[] = [$r[0], $r[1]];
    }
}

$statusDefault = fn(string $status): string => ($p = CloudbedsConfig::DEFAULT_STATUS_MAP[CloudbedsValueMaps::normalize(CloudbedsValueMaps::RESERVATION_STATUS, $status)] ?? null) !== null ? CloudbedsValueMaps::statusChoice($p) : '';
// room rates are lodging. Everything else is decided by the amount (a discount when negative) so it isn't preselected.
$defaultItems = [];
foreach (CloudbedsConfig::DEFAULT_ITEM_MAP as $type => $itemId) {
    $defaultItems[CloudbedsValueMaps::normalize(CloudbedsValueMaps::CHARGE_ITEM, $type)] = (string) $itemId;
}
$chargeDefault = fn(string $type): string => $defaultItems[CloudbedsValueMaps::normalize(CloudbedsValueMaps::CHARGE_ITEM, $type)] ?? '';
$methodDefault = fn(string $method): string => CloudbedsConfig::DEFAULT_PAYMENT_METHOD_MAP[CloudbedsValueMaps::normalize(CloudbedsValueMaps::PAYMENT_METHOD, $method)] ?? \HHK\SysConst\PayType::External;
$toOptions = fn(array $choices): array => array_map(null, array_keys($choices), array_values($choices));

$valueMapsMkup = HTMLContainer::generateMarkup('div',
    $valueMapSection(CloudbedsValueMaps::ROOM, 'Rooms', 'Which HHK room each Cloudbeds room is imported as. Rooms left as they are are matched to the HHK room with the same name.', $cbRooms, $roomOptions, '-- Match by name --', fn() => '')
    . $valueMapSection(CloudbedsValueMaps::PAYMENT_METHOD, 'Payment Methods', 'How payments are recorded. Card and other methods are recorded as external payments.', $cbMethods, $toOptions(CloudbedsValueMaps::PAY_TYPE_CHOICES), '-- Default --', $methodDefault)
    . $valueMapSection(CloudbedsValueMaps::RESERVATION_STATUS, 'Reservation Statuses', 'What each Cloudbeds reservation status is imported as. Checked out and staying reservations also get a visit and its folio.', $cbStatuses, $toOptions(CloudbedsValueMaps::STATUS_CHOICES), '-- Default --', $statusDefault)
    . $valueMapSection(CloudbedsValueMaps::CHARGE_ITEM, 'Charge Items', 'What each kind of folio charge is imported as on the invoice. Room rates are lodging. Anything left as default is an additional charge, or a discount when the amount is negative. Charges set to Do not import are left off the invoice.', $cbChargeTypes, $toOptions(CloudbedsValueMaps::CHARGE_ITEM_CHOICES), '-- Default --', $chargeDefault),
    ['class' => 'hhk-flex flex-wrap']);

// import status
$summaryMkup = '';
$errorsMkup = '';
$progress = ['progress' => 0];
$fetchStep = '';

if ($import !== null) {
    $countRows = [];
    foreach ($staging->counts() as $entity => $statuses) {
        $countRows[] = ['Type' => ucfirst($entity) . 's'] + array_map('strval', array_combine(array_map('ucfirst', array_keys($statuses)), array_values($statuses)));
    }
    $summaryMkup = CreateMarkupFromDB::generateHTML_Table($countRows, 'cbCounts');

    $errorRows = [];
    foreach ($staging->getErrors(50) as $e) {
        $errorRows[] = ['Type' => $e['entityType'], 'Cloudbeds Id' => cbEsc($e['cloudbedsId']), 'Error' => cbEsc($e['message'])];
    }
    $errorsMkup = count($errorRows) > 0 ? CreateMarkupFromDB::generateHTML_Table($errorRows, 'cbErrors') : '';

    $progress = $staging->getProgress();
    $fetchStep = $staging->getMeta('fetchStep', 'profiles');
}

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

        <style>
            #progressBar { height: 20px; margin: 0 -1px; }
            #progressBar .progressValue { background-color: rgb(77, 141, 67); }
            #progressBar .progressValueText { position: absolute; width: 100%; text-align: center; }
            #cbErrors td:last-child { white-space: pre-wrap; }
        </style>

        <script type="text/javascript">

            function post(data, done) {
                $.ajax({
                    url: "CloudbedsImport.php",
                    method: "post",
                    data: data,
                    dataType: "json",
                    success: done,
                    error: function (xhr) {
                        flagAlertMessage("Request failed: " + xhr.status + " " + xhr.statusText, true);
                        $("button.cbAction").prop("disabled", false);
                    }
                });
            }

            // fetch from Cloudbeds until complete. Each request works for ~20 seconds.
            function runFetch() {
                post({cmd: "fetch"}, function (data) {
                    if (data.error) {
                        flagAlertMessage(data.error, true);
                        $("button.cbAction").prop("disabled", false);
                        return;
                    }
                    $("#fetchStatus").text(data.message);
                    if (data.complete) {
                        location.reload();
                    } else {
                        runFetch();
                    }
                });
            }

            // import batches until nothing is left
            function runImport() {
                post({cmd: "startImport", limit: 50}, function (data) {
                    if (data.error) {
                        flagAlertMessage(data.error, true);
                        $("button.cbAction").prop("disabled", false);
                        return;
                    }

                    var p = data.progress;
                    $("#progressBar .progressValue").css("width", p.progress + "%");
                    $("#progressBar .progressValueText").text(p.progress + "% (" + p.processed + " of " + p.total + ", " + p.errors + " errors)")
                        .css("color", p.progress >= 50 ? "white" : "black");

                    if (data.batch > 0 && p.remaining > 0) {
                        runImport();
                    } else {
                        flagAlertMessage("Import finished: " + p.processed + " of " + p.total + " records processed, " + p.errors + " errors", p.errors > 0);
                        setTimeout(function () { location.reload(); }, 1500);
                    }
                });
            }

            // custom field mapping rows
            $(document).on("click", ".hhk-fldmap-remove", function () {
                $(this).closest("tr").remove();
            });

            $(document).on("click", ".hhk-fldmap-addrow", function () {
                var btn = $(this), scope = btn.data("scope"), idx = parseInt(btn.data("idx"), 10);
                btn.data("idx", idx + 1);

                var esc = function (v) { return $("<div>").text(String(v)).html(); };

                // HHK fields, grouped
                var hhk = '<option value="">-- HHK field --</option>', groups = {}, order = [];
                $.each(btn.data("hhkfields") || [], function (i, o) {
                    if (!groups[o.group]) { groups[o.group] = ""; order.push(o.group); }
                    groups[o.group] += '<option value="' + esc(o.value) + '">' + esc(o.label) + '</option>';
                });
                $.each(order, function (i, g) { hhk += '<optgroup label="' + esc(g) + '">' + groups[g] + '</optgroup>'; });

                // Cloudbeds fields, or a text box when none are known
                var cb = btn.data("cbfields") || {}, crm;
                if (Object.keys(cb).length > 0) {
                    crm = '<select name="fldmap_crm[' + scope + '][' + idx + ']" style="max-width:300px;"><option value="">-- Cloudbeds field --</option>';
                    $.each(cb, function (key, label) { crm += '<option value="' + esc(key) + '">' + esc(label) + '</option>'; });
                    crm += "</select>";
                } else {
                    crm = '<input type="text" name="fldmap_crm[' + scope + '][' + idx + ']" size="30" placeholder="Cloudbeds field name or shortcode">';
                }

                $("#fldmap_tbl_" + scope + " tbody").append("<tr>"
                    + '<td><select name="fldmap_hhk[' + scope + '][' + idx + ']" style="max-width:240px;">' + hhk + "</select></td>"
                    + "<td>" + crm + "</td>"
                    + '<td class="text-center actionBtns"><ul class="ui-widget ui-helper-clearfix hhk-ui-icons"><li class="hhk-fldmap-remove ui-corner-all ui-state-default p-1" title="Remove mapping"><span class="bi bi-trash3"></span></li></ul></td>'
                    + "</tr>");
            });

            // room, payment method and status mapping rows for Cloudbeds values that aren't listed
            $(document).on("click", ".hhk-valmap-addrow", function () {
                var btn = $(this), type = btn.data("type"), idx = parseInt(btn.data("idx"), 10);
                btn.data("idx", idx + 1);

                var esc = function (v) { return $("<div>").text(String(v)).html(); };
                var opts = '<option value="">' + esc(btn.data("blank")) + "</option>";
                $.each(btn.data("hhkoptions") || [], function (i, o) {
                    opts += '<option value="' + esc(o.value) + '">' + esc(o.label) + "</option>";
                });

                $("#valmap_tbl_" + type + " tbody").append("<tr>"
                    + '<td><input type="text" name="valmap_cb[' + type + '][' + idx + ']" size="24" placeholder="Cloudbeds value"></td>'
                    + '<td><select name="valmap_hhk[' + type + '][' + idx + ']" style="max-width:300px;">' + opts + "</select></td>"
                    + '<td class="text-center actionBtns"><ul class="ui-widget ui-helper-clearfix hhk-ui-icons"><li class="hhk-fldmap-remove ui-corner-all ui-state-default p-1" title="Remove mapping"><span class="bi bi-trash3"></span></li></ul></td>'
                    + "</tr>");
            });

            $(document).ready(function () {

                $("#fetch").click(function () {
                    $("button.cbAction").prop("disabled", true);
                    $("#fetchStatus").text("Starting...");
                    runFetch();
                });

                $("#startImport").click(function () {
                    $("button.cbAction").prop("disabled", true);
                    $("#progressBar").removeClass("d-none").addClass("d-flex");
                    runImport();
                });

                $(".cbCmd").click(function () {
                    var btn = $(this);
                    if (btn.data("confirm") && !confirm(btn.data("confirm"))) {
                        return;
                    }
                    post({cmd: btn.data("cmd")}, function (data) {
                        if (data.error) {
                            flagAlertMessage(data.error, true);
                        } else {
                            flagAlertMessage(data.success, false);
                            setTimeout(function () { location.reload(); }, 1500);
                        }
                    });
                });
            });

        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>

            <?php if ($errorMsg != '') { ?>
            <p class="ui-corner-all ui-state-error p-2 mb-3"><?php echo cbEsc($errorMsg); ?></p>
            <?php } ?>
            <?php if ($resultMsg != '') { ?>
            <p class="ui-corner-all ui-state-highlight p-2 mb-3"><?php echo cbEsc($resultMsg); ?></p>
            <?php } ?>

            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <h2>Cloudbeds Connection</h2>
                <?php if ($import === null && $configError != '') { ?>
                <p class="ui-corner-all ui-state-highlight p-2"><?php echo cbEsc($configError); ?></p>
                <?php } ?>
                <form method="post" action="CloudbedsImport.php" autocomplete="off">
                    <?php echo $connMkup; ?>
                    <input type="submit" name="btnSaveConnection" value="Save Connection" class="ui-button ui-corner-all mt-2">
                </form>
            </div>

            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3" style="max-width:100%">
                <h2>Import Settings</h2>
                <form method="post" action="CloudbedsImport.php">
                    <?php echo $setTbl->generateMarkup(); ?>

                    <h3 class="mt-3">Custom Field Mapping</h3>
                    <p>Choose which Cloudbeds custom field feeds each HHK field. Cloudbeds has no patients, hospitals or vehicles, so this is how they are found.
                        Guest custom fields are on the guest profile, reservation custom fields are on the reservation.
                        Custom fields that aren't mapped are handled as set in "Unmapped Custom Fields" above.</p>
                    <?php echo $mappingMkup; ?>

                    <h3 class="mt-3">Room, Payment Method, Status and Charge Item Mapping</h3>
                    <?php echo $valueMapsMkup; ?>

                    <p><input type="submit" name="btnSaveSettings" value="Save Settings" class="ui-button ui-corner-all mt-2"></p>
                </form>
            </div>

            <?php if ($import !== null) { ?>

            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <h2>Fetch from Cloudbeds</h2>
                <p>Properties: <?php echo cbEsc(implode(', ', $config->getPropertyIds())); ?>
                    &nbsp;|&nbsp; Organization: <?php echo cbEsc($config->getOrganizationId()); ?>
                    &nbsp;|&nbsp; Fetch step: <strong><?php echo cbEsc($fetchStep); ?></strong></p>
                <p>First finds which reservations count as a stay (Cloudbeds getGuestList, filtered by the "Only Guests Who Stayed" setting above), then pulls
                    those guest profiles, their custom fields and reservations, reservation custom fields and folios into a staging table.
                    Only guests with a qualifying stay are ever staged. Nothing is written to HHK yet. It can be stopped and resumed. Map the custom fields above before importing.</p>
                <button class="ui-button ui-corner-all cbAction" id="fetch">Fetch / Resume Fetch</button>
                <span id="fetchStatus" class="ml-3"></span>
                <?php if ((int) $staging->getMeta('guestNotesUnpaired', '0') > 0) { ?>
                <p class="mt-2">Guest notes: <?php echo (int) $staging->getMeta('guestNotesUnpaired', '0'); ?> guests could not be paired with a Cloudbeds guest id, so their notes were not fetched.
                    Cloudbeds only pairs a guest profile with its guest id for the main guest of a reservation.</p>
                <?php } ?>
            </div>

            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3" style="max-width:100%">
                <div class="hhk-flex">
                    <h2>Import</h2>
                    <button class="ui-button ui-corner-all ml-3 cbAction" id="startImport">Start Import</button>
                    <button class="ui-button ui-corner-all ml-3 cbAction cbCmd" data-cmd="retryFailed">Retry Failed</button>
                    <button class="ui-button ui-corner-all ml-3 cbAction cbCmd" data-cmd="undo" data-confirm="Set imported people to 'To Be Deleted'? This only works before reservations and folios are imported.">Undo Import</button>
                    <button class="ui-button ui-corner-all ml-3 cbAction cbCmd" data-cmd="resetStaging" data-confirm="Discard all fetched Cloudbeds data? Nothing already imported into HHK is changed.">Discard Fetched Data</button>
                </div>
                <div id="progressBar" class="ui-widget ui-widget-content ui-corner-all d-none">
                    <div class="progressValue ui-corner-all" style="width: <?php echo (int) $progress['progress']; ?>%"></div>
                    <div class="progressValueText">0%</div>
                </div>
                <?php echo $summaryMkup; ?>
            </div>

            <?php if ($errorsMkup != '') { ?>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3" style="max-width:100%">
                <h2>Errors</h2>
                <?php echo $errorsMkup; ?>
            </div>
            <?php } ?>

            <?php } ?>
        </div>
    </body>
</html>
