<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Applies the manual Cloudbeds custom field -> HHK field mapping (the crm_field_map rows, see CloudbedsConfigStore).
 *
 * A mapping's Cloudbeds field may be the custom field's id, shortcode, name or label, checked in that order (case insensitive).
 * The HHK field is one of those listed in GUEST_FIELDS / RESERVATION_FIELDS.
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
            'patient.Phone' => 'Patient Phone',
            'patient.Email' => 'Patient Email',
            'patient.BirthDate' => 'Patient Birth Date',
            'patient.Gender' => 'Patient Gender',
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
                if (!in_array($target, $valid, true)) {
                    throw new \InvalidArgumentException("Invalid target '" . (is_scalar($target) ? $target : gettype($target)) . "' for $scope custom field '$key'. Valid targets: " . implode(', ', $valid));
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
        return $scope === self::SCOPE_GUEST ? self::GUEST_FIELDS : self::RESERVATION_FIELDS;
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
     * Split a patient full name into first/last. Handles "Last, First" and "First Middle Last".
     *
     * @param string $full
     * @return array{first: string, last: string}
     */
    public static function splitFullName(string $full): array {
        $full = trim(preg_replace('/\s+/', ' ', $full));

        if ($full === '') {
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
