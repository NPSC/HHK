<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\SysConst\ItemId;
use HHK\SysConst\PayType;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;

/**
 * Per-import configuration for the Cloudbeds importer: API credentials, which properties to import and
 * the manual mappings (custom fields, statuses, payment methods, rooms) that are set up for each import.
 *
 * Stored in the database and edited on the Cloudbeds import page: the connection (organization, properties, API key/access token)
 * in the cc_hosted_gateway table, like the Salesforce config, and the rest in import_cloudbeds_settings.
 * See CloudbedsConfigStore.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsConfig {

    public const DEFAULT_BASE_URL = 'https://api.cloudbeds.com';

    /**
     * Cloudbeds reservation status => HHK reservation status and (if the guest stayed) visit status.
     * A null reservation status skips the reservation.
     */
    public const DEFAULT_STATUS_MAP = [
        'checked_out'   => ['reservation' => ReservationStatus::Checkedout, 'visit' => VisitStatus::CheckedOut],
        'checked_in'    => ['reservation' => ReservationStatus::Staying,    'visit' => VisitStatus::CheckedIn],
        'confirmed'     => ['reservation' => ReservationStatus::Committed,  'visit' => null],
        'not_confirmed' => ['reservation' => ReservationStatus::UnCommitted,'visit' => null],
        'inquiry'       => ['reservation' => ReservationStatus::UnCommitted,'visit' => null],
        'canceled'      => ['reservation' => ReservationStatus::Canceled,   'visit' => null],
        'no_show'       => ['reservation' => ReservationStatus::NoShow,     'visit' => null],
        'deleted'       => ['reservation' => null,                          'visit' => null],
    ];

    /**
     * Normalized Cloudbeds payment method => HHK pay type. Anything else is recorded as an External payment.
     */
    public const DEFAULT_PAYMENT_METHOD_MAP = [
        'cash'          => PayType::Cash,
        'check'         => PayType::Check,
        'cheque'        => PayType::Check,
        'bank_transfer' => PayType::Transfer,
        'wire'          => PayType::Transfer,
        'ach'           => PayType::Transfer,
        'transfer'      => PayType::Transfer,
    ];

    /**
     * Cloudbeds transaction type => HHK item, for the types that aren't mapped. Everything not listed
     * is ItemId::AddnlCharge, or ItemId::Discount when the amount is negative.
     */
    public const DEFAULT_ITEM_MAP = [
        'rate' => ItemId::Lodging,
        'roomRevenue_manual' => ItemId::Lodging,
        'roomRevenue_cancellation' => ItemId::Lodging,
        'roomRevenue_no_show' => ItemId::Lodging,
    ];

    /** Settings saved in import_cloudbeds_settings. The rest of the config is the connection, the custom field mapping (crm_field_map) and the room, payment method and status mappings (import_cloudbeds_map). */
    public const SETTING_KEYS = ['defaultHospital', 'createMissing', 'unmappedCustomFields', 'importGuestNotes'];

    protected array $cfg;
    protected CloudbedsFieldMapper $fieldMapper;

    /**
     * @param array $cfg connection: apiKey and/or accessToken, organizationId, propertyIds, baseUrl; any of SETTING_KEYS;
     *                   customFields: [scope => [Cloudbeds field => HHK field]];
     *                   roomMap: [Cloudbeds room => idResource]; paymentMethodMap: [Cloudbeds method => pay type];
     *                   reservationStatusMap: [Cloudbeds status => CloudbedsValueMaps::STATUS_CHOICES key];
     *                   chargeItemMap: [Cloudbeds transaction type => CloudbedsValueMaps::CHARGE_ITEM_CHOICES key]
     * @throws \InvalidArgumentException when the config is incomplete or invalid
     */
    public function __construct(array $cfg) {

        if (trim((string) ($cfg['apiKey'] ?? '')) === '' && trim((string) ($cfg['accessToken'] ?? '')) === '') {
            throw new \InvalidArgumentException("Cloudbeds needs an API key or access token");
        }

        if (trim((string) ($cfg['organizationId'] ?? '')) === '') {
            throw new \InvalidArgumentException("Cloudbeds needs an organization id (used by the Guest Profiles API)");
        }

        $propertyIds = array_values(array_filter(array_map('trim', array_map('strval', (array) ($cfg['propertyIds'] ?? []))), fn($id) => ctype_digit($id)));
        if (count($propertyIds) === 0) {
            throw new \InvalidArgumentException("Cloudbeds needs at least one numeric property id");
        }
        $cfg['propertyIds'] = $propertyIds;

        self::validateSettings($cfg);
        $this->fieldMapper = new CloudbedsFieldMapper((array) ($cfg['customFields'] ?? []), (string) ($cfg['unmappedCustomFields'] ?? 'note'));

        $this->cfg = $cfg;
    }

    /**
     * @throws \InvalidArgumentException if the connection isn't set up or the settings are invalid
     */
    public static function fromDatabase(\PDO $dbh): self {
        return new self((new CloudbedsConfigStore($dbh))->load());
    }

    /**
     * Check the mapping/override settings (the SETTING_KEYS)
     *
     * @throws \InvalidArgumentException naming what is wrong
     */
    public static function validateSettings(array $settings): void {

        // custom field targets and the unmapped mode
        new CloudbedsFieldMapper((array) ($settings['customFields'] ?? []), (string) ($settings['unmappedCustomFields'] ?? 'note'));

        $defaultHospital = trim((string) ($settings['defaultHospital'] ?? ''));
        if ($defaultHospital !== '' && !ctype_digit($defaultHospital)) {
            throw new \InvalidArgumentException('defaultHospital must be a hospital id');
        }

        foreach (['reservationStatusMap' => CloudbedsValueMaps::RESERVATION_STATUS, 'paymentMethodMap' => CloudbedsValueMaps::PAYMENT_METHOD, 'roomMap' => CloudbedsValueMaps::ROOM, 'chargeItemMap' => CloudbedsValueMaps::CHARGE_ITEM] as $key => $type) {
            if (isset($settings[$key]) && !is_array($settings[$key])) {
                throw new \InvalidArgumentException("$key must be a list of mappings");
            }

            foreach ((array) ($settings[$key] ?? []) as $cloudbedsValue => $hhkValue) {
                if (!is_scalar($hhkValue) || !CloudbedsValueMaps::isValidValue($type, (string) $hhkValue)) {
                    throw new \InvalidArgumentException("$key '$cloudbedsValue' has an invalid HHK value");
                }
            }
        }
    }

    /**
     * @return array the config as stored: connection plus settings
     */
    public function getRaw(): array {
        return $this->cfg;
    }

    public function getBaseUrl(): string {
        return rtrim((string) ($this->cfg['baseUrl'] ?? self::DEFAULT_BASE_URL), '/');
    }

    public function getApiKey(): string {
        return trim((string) ($this->cfg['apiKey'] ?? ''));
    }

    public function getAccessToken(): string {
        return trim((string) ($this->cfg['accessToken'] ?? ''));
    }

    public function getOrganizationId(): string {
        return trim((string) $this->cfg['organizationId']);
    }

    /**
     * @return string[]
     */
    public function getPropertyIds(): array {
        return $this->cfg['propertyIds'];
    }

    /**
     * Import the notes on guests from Cloudbeds. On unless turned off: it costs an extra API call per guest.
     */
    public function importGuestNotes(): bool {
        return (bool) ($this->cfg['importGuestNotes'] ?? true);
    }

    public function getFieldMapper(): CloudbedsFieldMapper {
        return $this->fieldMapper;
    }

    /**
     * Id of the hospital to use for reservations that have no mapped hospital custom field. 0 for none.
     */
    public function getDefaultHospitalId(): int {
        $id = trim((string) ($this->cfg['defaultHospital'] ?? ''));
        return ctype_digit($id) ? (int) $id : 0;
    }

    /**
     * Create hospitals, rooms and gen lookup values that are used by the import but don't exist in HHK
     *
     * @param string $entity "hospitals", "rooms" or "genLookups"
     */
    public function createMissing(string $entity): bool {
        return (bool) ($this->cfg['createMissing'][$entity] ?? false);
    }

    /**
     * @return array{reservation: ?string, visit: ?string}|null null if Cloudbeds status is unknown
     */
    public function mapStatus(string $cloudbedsStatus): ?array {
        $custom = [];
        foreach ((array) ($this->cfg['reservationStatusMap'] ?? []) as $status => $choice) {
            $custom[CloudbedsValueMaps::normalize(CloudbedsValueMaps::RESERVATION_STATUS, (string) $status)] = CloudbedsValueMaps::statusPair((string) $choice);
        }

        $map = array_replace(self::DEFAULT_STATUS_MAP, $custom);
        return $map[CloudbedsValueMaps::normalize(CloudbedsValueMaps::RESERVATION_STATUS, $cloudbedsStatus)] ?? null;
    }

    /**
     * The HHK room a Cloudbeds room name is mapped to
     *
     * @return int idResource, 0 if the room isn't mapped (it is then matched by name)
     */
    public function getMappedRoomId(string $cloudbedsRoomName): int {
        $wanted = CloudbedsValueMaps::normalize(CloudbedsValueMaps::ROOM, $cloudbedsRoomName);

        foreach ((array) ($this->cfg['roomMap'] ?? []) as $name => $idResource) {
            if (CloudbedsValueMaps::normalize(CloudbedsValueMaps::ROOM, (string) $name) === $wanted && ctype_digit((string) $idResource)) {
                return (int) $idResource;
            }
        }

        return 0;
    }

    /**
     * Map a Cloudbeds payment method to an HHK pay type.
     *
     * @return string one of PayType::Cash, Check, Transfer, External
     */
    public function mapPayType(string $cloudbedsMethod): string {
        $normalize = fn(string $method): string => CloudbedsValueMaps::normalize(CloudbedsValueMaps::PAYMENT_METHOD, $method);

        $custom = [];
        foreach ((array) ($this->cfg['paymentMethodMap'] ?? []) as $method => $type) {
            $custom[$normalize((string) $method)] = $type;
        }

        $map = array_replace(self::DEFAULT_PAYMENT_METHOD_MAP, $custom);
        return $map[$normalize($cloudbedsMethod)] ?? PayType::External;
    }

    /**
     * The HHK item a Cloudbeds transaction type is imported as
     *
     * @return int|null the item id, null if the charge isn't imported
     */
    public function mapItem(string $transactionType, float $amount): ?int {
        $normalize = fn(string $type): string => CloudbedsValueMaps::normalize(CloudbedsValueMaps::CHARGE_ITEM, $type);

        $map = [];
        foreach (self::DEFAULT_ITEM_MAP as $type => $itemId) {
            $map[$normalize($type)] = (string) $itemId;
        }
        foreach ((array) ($this->cfg['chargeItemMap'] ?? []) as $type => $choice) {
            $map[$normalize((string) $type)] = (string) $choice;
        }

        $choice = $map[$normalize($transactionType)] ?? null;

        if ($choice === CloudbedsValueMaps::SKIP) {
            return null;
        }

        return $choice !== null ? (int) $choice : ($amount < 0 ? ItemId::Discount : ItemId::AddnlCharge);
    }
}
