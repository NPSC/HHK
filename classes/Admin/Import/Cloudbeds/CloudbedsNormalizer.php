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
     * Parse a birth date out of a custom field's free text. Properties sometimes repurpose a custom field (e.g. a "door
     * code" field) to hold a birth date typed by hand, in all kinds of formats - unlike Cloudbeds' own birthday field,
     * which is always a clean YYYY-MM-DD.
     *
     * Handles MM/DD/YYYY and MM-DD-YYYY, YYYY-MM-DD, MM/DD/YY and MM-DD-YY, and bare digits with no separator at all,
     * MMDDYYYY and MMDDYY (the "door code" case). A 2 digit year is resolved to whichever century does not put the date
     * in the future - a birth date can never be in the future, and this importer is mainly used for adult patients, so
     * that favors the right reading for most of them, but a young adult whose 2 digit year is low (e.g. "05") could
     * still be misread as 1905 rather than 2005: there is no way to tell those apart from the digits alone.
     *
     * Anything else - a different order (e.g. DD/MM/YY), a textual month, a format not listed above - is not
     * recognized and comes back blank rather than risk a silently wrong date.
     *
     * @param string $value
     * @return string Y-m-d, blank if $value is empty or not recognized
     */
    public static function parseCustomFieldDate(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // YYYY-MM-DD / YYYY/MM/DD
        if (preg_match('~^(\d{4})[/-](\d{1,2})[/-](\d{1,2})~', $value, $m)) {
            return self::toDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // MM/DD/YYYY or MM-DD-YYYY - 4 digit year, unambiguous
        if (preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$~', $value, $m)) {
            return self::toDate((int) $m[3], (int) $m[1], (int) $m[2]);
        }

        // MM/DD/YY or MM-DD-YY - 2 digit year
        if (preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{2})$~', $value, $m)) {
            return self::toDate(self::resolveTwoDigitYear((int) $m[3]), (int) $m[1], (int) $m[2]);
        }

        // MMDDYYYY - 8 bare digits, no separator
        if (preg_match('~^(\d{2})(\d{2})(\d{4})$~', $value, $m)) {
            return self::toDate((int) $m[3], (int) $m[1], (int) $m[2]);
        }

        // MMDDYY - 6 bare digits, no separator (the repurposed "door code" case)
        if (preg_match('~^(\d{2})(\d{2})(\d{2})$~', $value, $m)) {
            return self::toDate(self::resolveTwoDigitYear((int) $m[3]), (int) $m[1], (int) $m[2]);
        }

        return '';
    }

    /**
     * A 2 digit year is 20YY unless that would be in the future, in which case it's 19YY. Fixed 100 years back rather
     * than a floating "current year" window, since a birth date can be arbitrarily far in the past but never in the future.
     */
    private static function resolveTwoDigitYear(int $twoDigitYear): int {
        $asCurrentCentury = 2000 + $twoDigitYear;
        return $asCurrentCentury > (int) date('Y') ? $asCurrentCentury - 100 : $asCurrentCentury;
    }

    private static function toDate(int $year, int $month, int $day): string {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    /**
     * Cloudbeds gender (M, F, N/A) to the description of the HHK Gender lookup value
     */
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
