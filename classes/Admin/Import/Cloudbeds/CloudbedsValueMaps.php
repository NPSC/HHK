<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\SysConst\ItemId;
use HHK\SysConst\PayType;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;

/**
 * The value mappings of the Cloudbeds import, each from a Cloudbeds value to an HHK value:
 *
 *  - room:               Cloudbeds room name -> HHK room (idResource). Rooms that aren't mapped are matched by name.
 *  - payment_method:     Cloudbeds payment method -> HHK pay type (cash, check, transfer, external)
 *  - reservation_status: Cloudbeds reservation status -> what to import it as (see STATUS_CHOICES)
 *  - charge_item:        Cloudbeds folio transaction type -> HHK invoice item (see CHARGE_ITEM_CHOICES)
 *
 * Several Cloudbeds values can map to the same HHK value (credit and debit cards are both external payments), which is why these
 * are stored keyed by the Cloudbeds value, in import_cloudbeds_map, rather than in the HHK keyed CRM mapping tables.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
final class CloudbedsValueMaps {

    public const ROOM = 'room';
    public const PAYMENT_METHOD = 'payment_method';
    public const RESERVATION_STATUS = 'reservation_status';

    public const CHARGE_ITEM = 'charge_item';

    public const TYPES = [self::ROOM, self::PAYMENT_METHOD, self::RESERVATION_STATUS, self::CHARGE_ITEM];

    /** choice for a Cloudbeds status or charge type that shouldn't be imported */
    public const SKIP = 'skip';

    /** The reservation statuses Cloudbeds uses */
    public const CLOUDBEDS_STATUSES = ['not_confirmed', 'confirmed', 'checked_in', 'checked_out', 'canceled', 'no_show', 'inquiry', 'deleted'];

    /**
     * What a Cloudbeds reservation status can be imported as: choice => label.
     * Checked out and Staying also create the visit (and stays) that goes with the reservation.
     */
    public const STATUS_CHOICES = [
        ReservationStatus::Checkedout => 'Checked out (reservation and visit)',
        ReservationStatus::Staying => 'Staying (reservation and checked in visit)',
        ReservationStatus::Committed => 'Committed reservation',
        ReservationStatus::UnCommitted => 'Uncommitted reservation',
        ReservationStatus::Waitlist => 'Waitlist reservation',
        ReservationStatus::Canceled => 'Canceled reservation',
        ReservationStatus::NoShow => 'No show reservation',
        ReservationStatus::TurnDown => 'Turned down reservation',
        self::SKIP => 'Do not import',
    ];

    /** The transaction types Cloudbeds uses for folio charges (payments are handled by the payment method mapping) */
    public const CLOUDBEDS_CHARGE_TYPES = [
        'rate', 'roomRevenue_manual', 'roomRevenue_cancellation', 'roomRevenue_no_show', 'product', 'addon', 'custom_item', 'space',
        'tax', 'fee', 'service_charge', 'gratuity', 'adjustment', 'check_discount', 'channel_commission', 'accountsReceivable',
    ];

    /**
     * The HHK items a Cloudbeds charge can be imported as (item id => label). These are the items that make an ordinary charge line:
     * Lodging is dated and merged into a line per run of nights at the same rate, the others are one line each.
     */
    public const CHARGE_ITEM_CHOICES = [
        ItemId::Lodging => 'Lodging',
        ItemId::VisitFee => 'Visit fee',
        ItemId::AddnlCharge => 'Additional charge',
        ItemId::Discount => 'Discount',
        self::SKIP => 'Do not import',
    ];

    /** HHK pay types a Cloudbeds payment method can be recorded as */
    public const PAY_TYPE_CHOICES = [
        PayType::Cash => 'Cash',
        PayType::Check => 'Check',
        PayType::Transfer => 'Bank transfer',
        PayType::External => 'External (card or other)',
    ];

    /**
     * The reservation and visit status a status choice imports as
     *
     * @return array{reservation: ?string, visit: ?string} reservation is null to skip
     */
    public static function statusPair(string $choice): array {
        return match ($choice) {
            ReservationStatus::Checkedout => ['reservation' => ReservationStatus::Checkedout, 'visit' => VisitStatus::CheckedOut],
            ReservationStatus::Staying => ['reservation' => ReservationStatus::Staying, 'visit' => VisitStatus::CheckedIn],
            self::SKIP => ['reservation' => null, 'visit' => null],
            default => ['reservation' => $choice, 'visit' => null],
        };
    }

    /**
     * The status choice for a reservation and visit status, the reverse of statusPair()
     *
     * @param array{reservation: ?string, visit: ?string} $pair
     */
    public static function statusChoice(array $pair): string {
        return $pair['reservation'] === null ? self::SKIP : $pair['reservation'];
    }

    /**
     * The form a Cloudbeds value is compared in: case, spacing and punctuation don't matter
     */
    public static function normalize(string $type, string $value): string {
        $value = strtolower(trim($value));

        return $type === self::ROOM ? $value : trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }

    /**
     * @return array<string,string>|null the HHK choices [value => label] for the type, null when they come from the database (rooms)
     */
    public static function choices(string $type): ?array {
        return match ($type) {
            self::PAYMENT_METHOD => self::PAY_TYPE_CHOICES,
            self::RESERVATION_STATUS => self::STATUS_CHOICES,
            self::CHARGE_ITEM => self::CHARGE_ITEM_CHOICES,
            default => null,
        };
    }

    public static function isValidValue(string $type, string $hhkValue): bool {
        $choices = self::choices($type);

        return $choices !== null ? isset($choices[$hhkValue]) : ($hhkValue !== '' && ctype_digit($hhkValue));
    }

    /**
     * Check mapping rows: known type, valid HHK value, and each Cloudbeds value only once
     *
     * @param array $rows [type => [['cb' => Cloudbeds value, 'hhk' => HHK value], ...]]
     * @throws \InvalidArgumentException naming the problem
     */
    public static function validate(array $rows): void {
        foreach ($rows as $type => $pairs) {
            if (!in_array($type, self::TYPES, true)) {
                throw new \InvalidArgumentException("Unknown mapping type '$type'");
            }

            $seen = [];
            foreach ($pairs as $pair) {
                $cb = trim($pair['cb']);
                $key = self::normalize($type, $cb);

                if ($key === '') {
                    throw new \InvalidArgumentException("A Cloudbeds $type value is blank");
                }
                if (strlen($cb) > 100) {
                    throw new \InvalidArgumentException("Cloudbeds $type '$cb' is too long");
                }
                if (!self::isValidValue($type, $pair['hhk'])) {
                    throw new \InvalidArgumentException("'" . $pair['hhk'] . "' is not a valid HHK value for the Cloudbeds $type '$cb'");
                }
                if (isset($seen[$key])) {
                    throw new \InvalidArgumentException("The Cloudbeds $type '$cb' is mapped more than once");
                }

                $seen[$key] = true;
            }
        }
    }

    /**
     * Parse the mapping tables posted by the import page: valmap_posted[type] marks a table as present (so clearing every row
     * clears the mapping), with valmap_cb[type][i] and valmap_hhk[type][i] for each row. Rows with no HHK value are dropped:
     * the value is left to the default.
     *
     * @return array [type => [['cb' => ..., 'hhk' => ...], ...]] only for the types posted
     */
    public static function fromForm(array $post): array {
        $rows = [];

        foreach (self::TYPES as $type) {
            if (empty($post['valmap_posted'][$type])) {
                continue;
            }

            $rows[$type] = [];
            foreach ((array) ($post['valmap_cb'][$type] ?? []) as $i => $cb) {
                $cb = trim(strip_tags((string) $cb));
                $hhk = trim(strip_tags((string) ($post['valmap_hhk'][$type][$i] ?? '')));

                if ($cb !== '' && $hhk !== '') {
                    $rows[$type][] = ['cb' => $cb, 'hhk' => $hhk];
                }
            }
        }

        return $rows;
    }
}
