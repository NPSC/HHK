<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Applies the manual Cloudbeds custom field -> HHK field mapping (the crm_field_map rows, see CloudbedsConfigStore).
 *
 * A mapping's Cloudbeds field may be the custom field's id, shortcode, name or label, checked in that order (case insensitive).
 * The HHK field is one of those listed in GUEST_FIELDS / RESERVATION_FIELDS - a guest (profile-level) custom field may target
 * anything a reservation field can too, since Cloudbeds has no concept of the patient separate from the guest (see fieldsFor()).
 * CloudbedsImport::importReservation() is what actually merges a guest-mapped patient/reservation value in; this class only
 * validates and applies the mapping.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsFieldMapper {

    public const SCOPE_GUEST = 'guest';
    public const SCOPE_RESERVATION = 'reservation';

    /**
     * HHK fields a guest profile custom field can feed, by group => [target => label]. guest.* keys are the import row keys understood by AbstractImport::addGuest
     */
    public const GUEST_FIELDS = [
        'Guest' => [
            'guest.Middle' => 'Middle Name',
            'guest.Phone' => 'Phone',
            'guest.Mobile' => 'Mobile Phone',
            'guest.Email' => 'Email',
            'guest.BirthDate' => 'Birth Date',
            'guest.Gender' => 'Gender',
            'guest.Ethnicity' => 'Ethnicity',
            'guest.Banned' => 'No Return',
            'guest.mediaSource' => 'Media Source',
        ],
        'Notes' => [
            'note.member' => 'Guest Note',
        ],
    ];

    /**
     * HHK fields a reservation custom field can feed, by group => [target => label]
     */
    public const RESERVATION_FIELDS = [
        'Patient' => [
            'patient.first' => 'Patient First Name',
            'patient.last' => 'Patient Last Name',
            'patient.full' => 'Patient Full Name',
            'patient.Middle' => 'Patient Middle Name',
            'patient.Phone' => 'Patient Phone',
            'patient.Mobile' => 'Patient Mobile Phone',
            'patient.Email' => 'Patient Email',
            'patient.BirthDate' => 'Patient Birth Date',
            'patient.Gender' => 'Patient Gender',
            'patient.Ethnicity' => 'Patient Ethnicity',
            'patient.Address' => 'Patient Address',
            'patient.Address2' => 'Patient Address 2',
            'patient.City' => 'Patient City',
            'patient.County' => 'Patient County',
            'patient.State' => 'Patient State',
            'patient.ZipCode' => 'Patient ZIP Code',
            'patient.Country' => 'Patient Country',
        ],
        'Hospital Stay' => [
            'hospital' => 'Hospital',
            'diagnosis' => 'Diagnosis',
            'mrn' => 'MRN',
        ],
        'PSG' => [
            'relationship' => 'Relationship to Patient',
            'note.psg' => 'PSG Note',
        ],
        'Vehicle' => [
            'vehicle.make' => 'Vehicle Make',
            'vehicle.model' => 'Vehicle Model',
            'vehicle.color' => 'Vehicle Color',
            'vehicle.license' => 'Vehicle License Number',
            'vehicle.state' => 'Vehicle State',
        ],
        'Reservation' => [
            'note.reservation' => 'Reservation Note',
        ],
    ];

    /** @var array<string, array<string,string>> [scope => [lowercased key => target]] */
    protected array $map = [];

    protected string $unmapped;

    /**
     * @param array $customFieldsConfig ["guest"=>[key=>target], "reservation"=>[key=>target]]
     * @param string $unmapped "note" to keep unmapped values as a note, "ignore" to drop them
     * @throws \InvalidArgumentException if a target is not valid for its scope
     */
    public function __construct(array $customFieldsConfig = [], string $unmapped = 'note') {

        if (!in_array($unmapped, ['note', 'ignore'], true)) {
            throw new \InvalidArgumentException("unmappedCustomFields must be 'note' or 'ignore'");
        }
        $this->unmapped = $unmapped;

        foreach ($customFieldsConfig as $scope => $fields) {
            if (!in_array($scope, [self::SCOPE_GUEST, self::SCOPE_RESERVATION], true)) {
                throw new \InvalidArgumentException("Unknown custom field scope '$scope', expected 'guest' or 'reservation'");
            }
            $valid = self::targetsFor($scope);
            foreach ((array) $fields as $key => $target) {
                if (!is_string($target) || !self::isValidTarget($scope, $target)) {
                    throw new \InvalidArgumentException("Invalid target '" . (is_scalar($target) ? $target : gettype($target)) . "' for $scope custom field '$key'. Valid targets: " . implode(', ', $valid) . ', or any enabled demographic (guest.demog.<Code> / patient.demog.<Code>)');
                }
                $this->map[$scope][strtolower(trim((string) $key))] = $target;
            }
        }
    }

    /**
     * Normalize the two Cloudbeds custom field shapes (guest profiles API and PMS API) to [id, shortcode, name, value]
     *
     * @param array $field
     * @return array{id: string, shortcode: string, name: string, label: string, value: string}
     */
    public static function normalizeField(array $field): array {
        $value = $field['customFieldValue'] ?? $field['value'] ?? '';

        return [
            'id' => (string) ($field['customFieldID'] ?? $field['customFieldId'] ?? ''),
            'shortcode' => (string) ($field['shortcode'] ?? ''),
            'name' => (string) ($field['customFieldName'] ?? $field['name'] ?? $field['label'] ?? ''),
            'label' => (string) ($field['label'] ?? ''),
            'value' => is_scalar($value) ? trim((string) $value) : '',
        ];
    }

    /**
     * The value stored in a mapping for a custom field: what identifies it in the data from Cloudbeds.
     * Reservation custom fields carry a shortcode, guest profile custom fields are known by name.
     *
     * @param string $scope
     * @param array $field ["id", "shortcode", "name"]
     * @return string
     */
    public static function fieldKey(string $scope, array $field): string {
        $order = $scope === self::SCOPE_GUEST ? ['name', 'shortcode', 'id'] : ['shortcode', 'name', 'id'];
        foreach ($order as $key) {
            if (trim((string) ($field[$key] ?? '')) !== '') {
                return trim((string) $field[$key]);
            }
        }
        return '';
    }

    /**
     * Find the config entry that maps a custom field
     *
     * @param string $scope
     * @param array $normalizedField
     * @return array{key: string, target: string}|null null when the field is not mapped
     */
    public function resolve(string $scope, array $normalizedField): ?array {
        foreach (['id', 'shortcode', 'name', 'label'] as $key) {
            $k = strtolower(trim($normalizedField[$key] ?? ''));
            if ($k !== '' && isset($this->map[$scope][$k])) {
                return ['key' => $k, 'target' => $this->map[$scope][$k]];
            }
        }
        return null;
    }

    /**
     * Find the configured target for a custom field
     *
     * @param string $scope
     * @param array $normalizedField
     * @return string|null null when the field is not mapped
     */
    public function targetFor(string $scope, array $normalizedField): ?string {
        return $this->resolve($scope, $normalizedField)['target'] ?? null;
    }

    /**
     * @return array<string, array<string,string>> [scope => [lowercased key => target]]
     */
    public function getMap(): array {
        return $this->map;
    }

    /**
     * The HHK fields for a scope, grouped
     *
     * @return array<string, array<string,string>> [group => [target => label]]
     */
    public static function fieldsFor(string $scope): array {
        if ($scope === self::SCOPE_GUEST) {
            // Cloudbeds has no separate concept for the patient: a guest (profile-level) custom field can hold data about
            // the patient, hospital stay, vehicle or PSG too, so everything a reservation field can target is offered here
            // as well. CloudbedsImport::importReservation() reads the main guest's profile-level mapped values for this.
            return array_merge(self::GUEST_FIELDS, self::RESERVATION_FIELDS);
        }
        return self::RESERVATION_FIELDS;
    }

    /**
     * Valid targets for a scope
     *
     * @return string[]
     */
    public static function targetsFor(string $scope): array {
        return array_merge(...array_map('array_keys', array_values(self::fieldsFor($scope))));
    }

    /**
     * A target is valid either as one of the fixed GUEST_FIELDS/RESERVATION_FIELDS, or as a "map to any enabled
     * demographic" target - guest.demog.<Code> / patient.demog.<Code>, where <Code> is a gen_lookups Demographics row.
     * This is checked by pattern rather than against a live list of enabled demographics: the mapper itself has no
     * database access (CloudbedsConfig, which owns it, is built from stored settings on every page load with no
     * $dbh), and the site's admin UI only ever offers a real enabled demographic in the dropdown in the first place -
     * a Code that doesn't match anything real just resolves to nothing at import time (see AbstractImport::addGuest()).
     */
    public static function isValidTarget(string $scope, string $target): bool {
        if (in_array($target, self::targetsFor($scope), true)) {
            return true;
        }
        // guest.demog.* only makes sense on a guest-profile field, same as GUEST_FIELDS; patient.demog.* is valid from
        // either scope, same as the rest of RESERVATION_FIELDS (Cloudbeds has no concept of the patient separate from
        // the guest, see fieldsFor())
        if ($scope === self::SCOPE_GUEST && preg_match('/^guest\.demog\.[A-Za-z0-9_]+$/', $target) === 1) {
            return true;
        }
        return preg_match('/^patient\.demog\.[A-Za-z0-9_]+$/', $target) === 1;
    }

    /**
     * Sort a list of custom fields into HHK targets
     *
     * @param string $scope
     * @param array $customFields raw custom fields from either Cloudbeds API
     * @return array{values: array<string,string>, notes: array<string,string[]>, unmapped: array<string,string>}
     *   values:   [target => value] for every mapped, non-empty field (last one wins if a target is mapped twice)
     *   notes:    [note.<entity> => lines] with "Name: value" lines for note targets and (if configured) unmapped fields
     *   unmapped: [field name => value] of every non-empty field without a mapping
     */
    public function apply(string $scope, array $customFields): array {
        $out = ['values' => [], 'notes' => [], 'unmapped' => []];
        $defaultNote = $scope === self::SCOPE_GUEST ? 'note.member' : 'note.reservation';

        foreach ($customFields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $f = self::normalizeField($field);
            if ($f['value'] === '') {
                continue;
            }

            $label = $f['name'] !== '' ? $f['name'] : ($f['shortcode'] !== '' ? $f['shortcode'] : $f['id']);
            $target = $this->targetFor($scope, $f);

            if ($target === null) {
                $out['unmapped'][$label] = $f['value'];
                if ($this->unmapped === 'note') {
                    $out['notes'][$defaultNote][] = $label . ': ' . $f['value'];
                }
            } elseif (str_starts_with($target, 'note.')) {
                $out['notes'][$target][] = $label . ': ' . $f['value'];
            } else {
                $out['values'][$target] = $f['value'];
            }
        }

        return $out;
    }

    /**
     * Like apply(), but for custom field data that might be mapped under either scope. Cloudbeds attaches some fields
     * to a reservation's per-guest-slot data (see CloudbedsImport::importReservation()'s pmsGuestList source) that a
     * site's own mapping may still categorize as a guest field, matching how Cloudbeds' own UI presents them, rather
     * than a reservation one - confirmed against a live config where every veteran-specific field (Branch of Service,
     * Door Code, Gender/Ethnicity of veteran, Relationship to Patient, ...) is mapped under the guest scope even
     * though the data lives in this reservation-side structure. Each field is resolved against $primaryScope first,
     * falling back to $secondaryScope only if genuinely unmapped there too - so a field mapped under one scope is
     * never also treated as unmapped in the other, which would double its "kept as a note" fallback.
     */
    public function applyEitherScope(array $customFields, string $primaryScope, string $secondaryScope): array {
        $out = ['values' => [], 'notes' => [], 'unmapped' => []];

        foreach ($customFields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $f = self::normalizeField($field);
            if ($f['value'] === '') {
                continue;
            }

            $label = $f['name'] !== '' ? $f['name'] : ($f['shortcode'] !== '' ? $f['shortcode'] : $f['id']);
            $target = $this->targetFor($primaryScope, $f) ?? $this->targetFor($secondaryScope, $f);

            if ($target === null) {
                $out['unmapped'][$label] = $f['value'];
                if ($this->unmapped === 'note') {
                    $out['notes']['note.reservation'][] = $label . ': ' . $f['value'];
                }
            } elseif (str_starts_with($target, 'note.')) {
                $out['notes'][$target][] = $label . ': ' . $f['value'];
            } else {
                $out['values'][$target] = $f['value'];
            }
        }

        return $out;
    }

    /**
     * Placeholder values a "Veteran Name if different" custom field sometimes holds instead of an actual name - these
     * must not be treated as a name (see import specs.md). Matched after stripping spaces/periods, so "N/A", "n.a.",
     * "None" etc all match.
     */
    protected const JUNK_NAME_VALUES = ['n/a', 'na', 'none', 'false', 'no', 'null', '-', '--'];

    /**
     * Split a patient full name into first/last. Handles "Last, First" and "First Middle Last". A placeholder value
     * like "n/a" or "false" (see JUNK_NAME_VALUES) is treated the same as an empty name.
     *
     * @param string $full
     * @return array{first: string, last: string}
     */
    public static function splitFullName(string $full): array {
        $full = trim(preg_replace('/\s+/', ' ', $full));

        if ($full === '' || in_array(strtolower(str_replace([' ', '.'], '', $full)), self::JUNK_NAME_VALUES, true)) {
            return ['first' => '', 'last' => ''];
        }

        if (str_contains($full, ',')) {
            [$last, $first] = array_map('trim', explode(',', $full, 2));
            return ['first' => $first, 'last' => $last];
        }

        $parts = explode(' ', $full);
        if (count($parts) === 1) {
            return ['first' => '', 'last' => $parts[0]];
        }

        $last = array_pop($parts);
        return ['first' => implode(' ', $parts), 'last' => $last];
    }
}
