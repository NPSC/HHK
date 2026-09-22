<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Converts Cloudbeds API data to the import row format used by AbstractImport (FirstName, LastName, Address, ZipCode, ...)
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsNormalizer {

    /** check-in/out times used when Cloudbeds only gives a date, same as the CSV importer */
    public const DEFAULT_ARRIVAL_TIME = '16:00:00';
    public const DEFAULT_DEPARTURE_TIME = '10:00:00';

    /**
     * Normalize a guest profile (Guest Profiles API `Profile`) or a reservation guest (`guests[]` of a profile reservation)
     *
     * @param array $g
     * @return array import row with every key AbstractImport::addGuest / addPatient read
     */
    public static function person(array $g): array {
        $address = (array) ($g['address'] ?? []);

        return [
            'externalId' => (string) ($g['id'] ?? ''),
            'FirstName' => trim((string) ($g['firstName'] ?? '')),
            'Middle' => '',
            'LastName' => trim((string) ($g['lastName'] ?? '')),
            'Email' => trim((string) ($g['email'] ?? '')),
            'Phone' => trim((string) ($g['phone'] ?? '')),
            'Mobile' => trim((string) ($g['cellPhone'] ?? '')),
            'Address' => trim((string) ($g['address1'] ?? $address['address1'] ?? '')),
            'Address2' => trim((string) ($g['address2'] ?? $address['address2'] ?? '')),
            'City' => trim((string) ($g['city'] ?? $address['city'] ?? '')),
            'County' => '',
            'State' => trim((string) ($g['state'] ?? $address['state'] ?? '')),
            'ZipCode' => trim((string) ($g['zip'] ?? $address['zip'] ?? '')),
            'Country' => trim((string) ($g['country'] ?? $address['country'] ?? '')),
            'BirthDate' => self::birthDate((string) ($g['birthday'] ?? '')),
            'Gender' => self::gender((string) ($g['gender'] ?? '')),
            'Hospital' => '',
        ];
    }

    /**
     * Cloudbeds has 0000-00-00 style placeholders for unknown birth dates. Anything that isn't a plausible date is blank.
     */
    public static function birthDate(string $value): string {
        $value = trim($value);

        if ($value === '' || !preg_match('/^(\d{4})-\d{2}-\d{2}/', $value, $m) || (int) $m[1] < 1900) {
            return '';
        }

        return $value;
    }

    /**
     * Cloudbeds gender (M, F, N/A) to the description of the HHK Gender lookup value
     */
    public static function gender(string $gender): string {
        return match (strtoupper(trim($gender))) {
            'M', 'MALE' => 'Male',
            'F', 'FEMALE' => 'Female',
            default => '',
        };
    }

    /**
     * Parse a Cloudbeds date or datetime to Y-m-d H:i:s, applying a default time when only a date (or midnight) is given
     *
     * @param string $value
     * @param string $defaultTime H:i:s
     * @return string|null null when empty or unparseable
     */
    public static function dateTime(string $value, string $defaultTime): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $dt = new \DateTime($value);
        } catch (\Exception) {
            return null;
        }

        if ($dt->format('H:i:s') === '00:00:00') {
            return $dt->format('Y-m-d') . ' ' . $defaultTime;
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
