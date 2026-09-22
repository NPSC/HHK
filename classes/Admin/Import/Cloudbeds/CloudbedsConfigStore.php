<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\Crypto;
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;

/**
 * Persists the Cloudbeds import configuration.
 *
 * The connection is stored the way the Salesforce config is: a row in cc_hosted_gateway (Gateway_Name 'cloudbeds') accessed through
 * CmsGatewayRS, whose columns are reused, with the secrets encrypted by Crypto:
 *
 *   cc_name        (username)       organization id
 *   Merchant_Id    (clientId)       property ids, comma separated
 *   Password       (clientSecret)   API key, encrypted
 *   Trans_Url      (password)       OAuth access token, encrypted (optional)
 *   CardInfo_Url   (endpointUrl)    API base url
 *
 * The custom field mapping is stored like the CRM export field mapping, in crm_field_map with this gateway's id: crm_object is
 * 'guest' (profile custom fields) or 'reservation', hhk_field the HHK field (see CloudbedsFieldMapper::GUEST_FIELDS / RESERVATION_FIELDS)
 * and crm_field the Cloudbeds custom field.
 *
 * The room, payment method, reservation status and charge item mappings (see CloudbedsValueMaps) are rows in import_cloudbeds_map, keyed by the
 * Cloudbeds value since several Cloudbeds values can map to the same HHK value.
 *
 * The other import settings (see CloudbedsConfig::SETTING_KEYS) are one JSON value per key in import_cloudbeds_settings.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsConfigStore {

    public const GATEWAY_NAME = 'cloudbeds';
    public const SETTINGS_TBL_NAME = 'import_cloudbeds_settings';
    public const FIELD_MAP_TBL_NAME = 'crm_field_map';
    public const VALUE_MAP_TBL_NAME = 'import_cloudbeds_map';

    /** the config key each value mapping type is loaded into */
    protected const VALUE_MAP_CONFIG_KEYS = [
        CloudbedsValueMaps::ROOM => 'roomMap',
        CloudbedsValueMaps::PAYMENT_METHOD => 'paymentMethodMap',
        CloudbedsValueMaps::RESERVATION_STATUS => 'reservationStatusMap',
        CloudbedsValueMaps::CHARGE_ITEM => 'chargeItemMap',
    ];

    /** crm_field_map.crm_object values */
    public const FIELD_MAP_OBJECTS = [CloudbedsFieldMapper::SCOPE_GUEST, CloudbedsFieldMapper::SCOPE_RESERVATION];

    /** shown in place of a stored secret, submitting it unchanged keeps the stored secret */
    public const SECRET_PLACEHOLDER = '**********';

    /** cc_hosted_gateway column sizes of the encrypted values */
    protected const API_KEY_COLUMN_SIZE = 245;
    protected const ACCESS_TOKEN_COLUMN_SIZE = 255;

    public function __construct(protected \PDO $dbh) {
    }

    public function ensureTables(): void {
        $this->dbh->exec("CREATE TABLE IF NOT EXISTS `" . self::SETTINGS_TBL_NAME . "` (
            `k` VARCHAR(45) NOT NULL,
            `v` MEDIUMTEXT NULL,
            `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
            `Last_Updated` DATETIME NULL,
            PRIMARY KEY (`k`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4");

        $this->dbh->exec("CREATE TABLE IF NOT EXISTS `" . self::VALUE_MAP_TBL_NAME . "` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `map_type` VARCHAR(30) NOT NULL,
            `cloudbeds_value` VARCHAR(100) NOT NULL,
            `hhk_value` VARCHAR(100) NOT NULL,
            `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
            `Last_Updated` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_type_value` (`map_type`, `cloudbeds_value`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4");
    }

    protected function loadGateway(): ?CmsGatewayRS {
        $cmsRs = new CmsGatewayRS();
        $cmsRs->Gateway_Name->setStoredVal(self::GATEWAY_NAME);
        $rows = EditRS::select($this->dbh, $cmsRs, [$cmsRs->Gateway_Name]);

        if (count($rows) !== 1) {
            return null;
        }

        $cmsRs = new CmsGatewayRS();
        EditRS::loadRow($rows[0], $cmsRs);
        return $cmsRs;
    }

    /**
     * The saved configuration in the form CloudbedsConfig takes, with the secrets decrypted.
     * Keys that haven't been saved yet are missing.
     *
     * @return array
     */
    public function load(): array {
        $this->ensureTables();
        $cfg = [];

        $gateway = $this->loadGateway();
        if ($gateway !== null) {
            $cfg['organizationId'] = $gateway->username->getStoredVal();
            $cfg['propertyIds'] = array_values(array_filter(array_map('trim', explode(',', $gateway->clientId->getStoredVal())), 'strlen'));
            $cfg['baseUrl'] = $gateway->endpointUrl->getStoredVal();
            $cfg['apiKey'] = $gateway->clientSecret->getStoredVal() !== '' ? Crypto::decryptMessage($gateway->clientSecret->getStoredVal()) : '';
            $cfg['accessToken'] = $gateway->password->getStoredVal() !== '' ? Crypto::decryptMessage($gateway->password->getStoredVal()) : '';
        }

        foreach ($this->dbh->query("select `k`, `v` from `" . self::SETTINGS_TBL_NAME . "`")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (in_array($row['k'], CloudbedsConfig::SETTING_KEYS, true)) {
                $cfg[$row['k']] = json_decode((string) $row['v'], true);
            }
        }

        // custom field mapping, as [scope => [Cloudbeds field => HHK field]]
        foreach ($this->loadFieldMap() as $object => $pairs) {
            foreach ($pairs as $pair) {
                $cfg['customFields'][$object][$pair['crm']] = $pair['hhk'];
            }
        }

        // room, payment method and status mappings, as [Cloudbeds value => HHK value]
        foreach ($this->loadValueMaps() as $type => $pairs) {
            foreach ($pairs as $pair) {
                $cfg[self::VALUE_MAP_CONFIG_KEYS[$type]][$pair['cb']] = $pair['hhk'];
            }
        }

        return $cfg;
    }

    /**
     * The room, payment method and reservation status mapping rows
     *
     * @return array<string, array<int, array{cb: string, hhk: string}>> [type => [['cb' => Cloudbeds value, 'hhk' => HHK value], ...]]
     */
    public function loadValueMaps(): array {
        $this->ensureTables();
        $maps = [];

        $stmt = $this->dbh->query("select `map_type`, `cloudbeds_value`, `hhk_value` from `" . self::VALUE_MAP_TBL_NAME . "` order by `id`");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (isset(self::VALUE_MAP_CONFIG_KEYS[$row['map_type']])) {
                $maps[$row['map_type']][] = ['cb' => $row['cloudbeds_value'], 'hhk' => $row['hhk_value']];
            }
        }

        return $maps;
    }

    /**
     * Replace the mapping of each type in $rows. Types not in $rows are left alone.
     *
     * @param array $rows [type => [['cb' => Cloudbeds value, 'hhk' => HHK value], ...]]
     * @throws \InvalidArgumentException if the mapping is invalid, nothing is changed
     * @return int number of mappings saved
     */
    public function saveValueMaps(array $rows, string $username): int {
        CloudbedsValueMaps::validate($rows);
        $this->ensureTables();

        $del = $this->dbh->prepare("delete from `" . self::VALUE_MAP_TBL_NAME . "` where `map_type` = :type");
        $ins = $this->dbh->prepare("insert into `" . self::VALUE_MAP_TBL_NAME . "` (`map_type`, `cloudbeds_value`, `hhk_value`, `Updated_By`, `Last_Updated`) values (:type, :cb, :hhk, :user, now())");

        $ownTransaction = !$this->dbh->inTransaction();
        if ($ownTransaction) {
            $this->dbh->beginTransaction();
        }

        try {
            $count = 0;

            foreach ($rows as $type => $pairs) {
                $del->execute([':type' => $type]);

                foreach ($pairs as $pair) {
                    $ins->execute([':type' => $type, ':cb' => trim($pair['cb']), ':hhk' => $pair['hhk'], ':user' => $username]);
                    $count++;
                }
            }

            if ($ownTransaction) {
                $this->dbh->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            throw $e;
        }

        return $count;
    }

    /**
     * @return int id of the cc_hosted_gateway row, 0 if the connection hasn't been saved yet
     */
    public function getGatewayId(): int {
        return (int) ($this->loadGateway()?->idcms_gateway->getStoredVal() ?? 0);
    }

    /**
     * The custom field mapping rows, in display order
     *
     * @throws \RuntimeException if the crm_field_map table doesn't exist
     * @return array<string, array<int, array{hhk: string, crm: string}>> [crm_object => [[hhk, crm], ...]]
     */
    public function loadFieldMap(): array {
        $gatewayId = $this->getGatewayId();
        $map = [];

        if ($gatewayId < 1) {
            return $map;
        }

        try {
            $stmt = $this->dbh->prepare("select `crm_object`, `hhk_field`, `crm_field` from `" . self::FIELD_MAP_TBL_NAME . "` where `gateway_id` = :gw and `crm_object` in ('" . implode("','", self::FIELD_MAP_OBJECTS) . "') order by `display_order`, `id`");
            $stmt->execute([':gw' => $gatewayId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                throw new \RuntimeException('The ' . self::FIELD_MAP_TBL_NAME . ' table is missing. Re-create the tables from the site configuration page.', 0, $e);
            }
            throw $e;
        }

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[$row['crm_object']][] = ['hhk' => $row['hhk_field'], 'crm' => $row['crm_field']];
        }

        return $map;
    }

    /**
     * Check custom field mapping rows: each HHK field belongs to the object and is mapped once, and each Cloudbeds field feeds one HHK field
     *
     * @param array $rows [crm_object => [['hhk' => HHK field, 'crm' => Cloudbeds field], ...]]
     * @throws \InvalidArgumentException naming the problem
     */
    public static function validateFieldMap(array $rows): void {
        foreach ($rows as $object => $pairs) {
            if (!in_array($object, self::FIELD_MAP_OBJECTS, true)) {
                throw new \InvalidArgumentException("Unknown custom field object '$object'");
            }

            $labels = array_merge(...array_values(CloudbedsFieldMapper::fieldsFor($object)));
            $seenHhk = [];
            $seenCrm = [];

            foreach ($pairs as $pair) {
                $hhk = $pair['hhk'];
                $crm = $pair['crm'];

                if (!isset($labels[$hhk])) {
                    throw new \InvalidArgumentException("'$hhk' is not an HHK field for $object custom fields");
                }
                if (strlen($crm) > 100) {
                    throw new \InvalidArgumentException("Cloudbeds field '$crm' is too long");
                }
                if (isset($seenHhk[$hhk])) {
                    throw new \InvalidArgumentException("The HHK field '" . $labels[$hhk] . "' is mapped more than once for $object custom fields");
                }
                if (isset($seenCrm[strtolower($crm)])) {
                    throw new \InvalidArgumentException("The Cloudbeds field '$crm' is mapped to more than one HHK field for $object custom fields");
                }

                $seenHhk[$hhk] = true;
                $seenCrm[strtolower($crm)] = true;
            }
        }
    }

    /**
     * Replace the custom field mapping of each object in $rows. Objects not in $rows are left alone.
     *
     * @param array $rows [crm_object => [['hhk' => HHK field, 'crm' => Cloudbeds field], ...]]
     * @throws \InvalidArgumentException if the mapping is invalid or the connection hasn't been saved, nothing is changed
     * @return int number of mappings saved
     */
    public function saveFieldMap(array $rows): int {
        $gatewayId = $this->getGatewayId();
        if ($gatewayId < 1) {
            throw new \InvalidArgumentException('Save the Cloudbeds connection before mapping custom fields');
        }

        self::validateFieldMap($rows);

        $del = $this->dbh->prepare("delete from `" . self::FIELD_MAP_TBL_NAME . "` where `gateway_id` = :gw and `crm_object` = :obj");
        $ins = $this->dbh->prepare("insert into `" . self::FIELD_MAP_TBL_NAME . "` (`gateway_id`, `crm_object`, `hhk_field`, `crm_field`, `in_export`, `in_search`, `display_order`) values (:gw, :obj, :hhk, :crm, 1, 0, :ord)");

        $ownTransaction = !$this->dbh->inTransaction();
        if ($ownTransaction) {
            $this->dbh->beginTransaction();
        }

        try {
            $count = 0;

            foreach ($rows as $object => $pairs) {
                $del->execute([':gw' => $gatewayId, ':obj' => $object]);

                foreach (array_values($pairs) as $i => $pair) {
                    $ins->execute([':gw' => $gatewayId, ':obj' => $object, ':hhk' => $pair['hhk'], ':crm' => $pair['crm'], ':ord' => ($i + 1) * 10]);
                    $count++;
                }
            }

            if ($ownTransaction) {
                $this->dbh->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            throw $e;
        }

        return $count;
    }

    /**
     * Parse the mapping table posted by the import page: fldmap_posted[object] marks an object's table as present (so removing every
     * row clears it), with fldmap_hhk[object][i] and fldmap_crm[object][i] for each row. Rows missing either field are dropped.
     *
     * @return array [crm_object => [['hhk' => ..., 'crm' => ...], ...]] only for the objects posted
     */
    public static function fieldMapFromForm(array $post): array {
        $rows = [];

        foreach (self::FIELD_MAP_OBJECTS as $object) {
            if (empty($post['fldmap_posted'][$object])) {
                continue;
            }

            $rows[$object] = [];
            foreach ((array) ($post['fldmap_hhk'][$object] ?? []) as $i => $hhk) {
                $hhk = trim(strip_tags((string) $hhk));
                $crm = trim(strip_tags((string) ($post['fldmap_crm'][$object][$i] ?? '')));

                if ($hhk !== '' && $crm !== '') {
                    $rows[$object][] = ['hhk' => $hhk, 'crm' => $crm];
                }
            }
        }

        return $rows;
    }

    /**
     * Save the connection. A secret that is blank or still the placeholder keeps the stored one.
     *
     * @param array $in ["organizationId", "propertyIds" (comma separated or array), "baseUrl", "apiKey", "accessToken"]
     * @param string $username
     * @throws \InvalidArgumentException if something is missing or invalid
     * @return string message
     */
    public function saveConnection(array $in, string $username): string {
        $existing = $this->loadGateway();
        $crmRs = new CmsGatewayRS();

        $organizationId = trim((string) ($in['organizationId'] ?? ''));
        if ($organizationId === '') {
            throw new \InvalidArgumentException('Organization id is required');
        }

        $propertyIds = self::parsePropertyIds($in['propertyIds'] ?? '');
        if (count($propertyIds) === 0) {
            throw new \InvalidArgumentException('At least one numeric property id is required');
        }

        $baseUrl = trim((string) ($in['baseUrl'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = CloudbedsConfig::DEFAULT_BASE_URL;
        }
        if (!preg_match('~^https://[^\s/]+~i', $baseUrl)) {
            throw new \InvalidArgumentException('The API base url must start with https://');
        }

        $secrets = [
            'apiKey' => ['field' => $crmRs->clientSecret, 'size' => self::API_KEY_COLUMN_SIZE, 'label' => 'API key', 'stored' => $existing?->clientSecret->getStoredVal() ?? ''],
            'accessToken' => ['field' => $crmRs->password, 'size' => self::ACCESS_TOKEN_COLUMN_SIZE, 'label' => 'Access token', 'stored' => $existing?->password->getStoredVal() ?? ''],
        ];

        $hasCredential = false;
        foreach ($secrets as $key => $secret) {
            $value = trim((string) ($in[$key] ?? ''));

            if ($value !== '' && $value !== self::SECRET_PLACEHOLDER) {
                $encrypted = Crypto::encryptMessage($value);

                // the column would silently truncate it, and a truncated secret just fails to authenticate
                if (strlen($encrypted) > $secret['size']) {
                    throw new \InvalidArgumentException($secret['label'] . ' is too long to store (' . strlen($encrypted) . ' encrypted characters, the limit is ' . $secret['size'] . ')');
                }

                $secret['field']->setNewVal($encrypted);
                $hasCredential = true;
            } elseif ($secret['stored'] !== '') {
                $hasCredential = true;
            }
        }

        if (!$hasCredential) {
            throw new \InvalidArgumentException('An API key or access token is required');
        }

        $crmRs->username->setNewVal($organizationId);
        $crmRs->clientId->setNewVal(implode(',', $propertyIds));
        $crmRs->endpointUrl->setNewVal($baseUrl);
        $crmRs->Updated_By->setNewVal($username);
        $crmRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        if ($existing === null) {
            $crmRs->Gateway_Name->setNewVal(self::GATEWAY_NAME);
            EditRS::insert($this->dbh, $crmRs);
            return 'Cloudbeds connection created.';
        }

        $crmRs->Gateway_Name->setStoredVal(self::GATEWAY_NAME);
        $rc = EditRS::update($this->dbh, $crmRs, [$crmRs->Gateway_Name]);

        return $rc > 0 ? 'Cloudbeds connection updated.' : 'Cloudbeds connection: no updates found.';
    }

    /**
     * Save import settings (any of CloudbedsConfig::SETTING_KEYS, others are ignored)
     *
     * @throws \InvalidArgumentException if the settings are invalid, nothing is saved
     */
    public function saveSettings(array $settings, string $username): void {
        CloudbedsConfig::validateSettings($settings);
        $this->ensureTables();

        $stmt = $this->dbh->prepare("insert into `" . self::SETTINGS_TBL_NAME . "` (`k`, `v`, `Updated_By`, `Last_Updated`) values (:k, :v, :user, now())
            on duplicate key update `v` = :v2, `Updated_By` = :user2, `Last_Updated` = now()");

        foreach (CloudbedsConfig::SETTING_KEYS as $key) {
            if (array_key_exists($key, $settings)) {
                $json = json_encode($settings[$key], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $stmt->execute([':k' => $key, ':v' => $json, ':v2' => $json, ':user' => $username, ':user2' => $username]);
            }
        }
    }

    /**
     * "12, 34 56" or [12, "34"] to ['12', '34', '56']
     *
     * @return string[]
     */
    public static function parsePropertyIds(string|array $ids): array {
        $parts = is_array($ids) ? array_map('strval', $ids) : (preg_split('/[^0-9]+/', $ids) ?: []);
        return array_values(array_unique(array_filter(array_map('trim', $parts), fn($id) => $id !== '' && ctype_digit($id))));
    }

    /**
     * Build the settings to save from the import page's settings form. Settings the form doesn't include are kept from $existing.
     *
     * Form fields: defaultHospital, createMissing[hospitals|rooms|genLookups], unmappedCustomFields, importGuestNotes,
     * The custom field mapping and the room, payment method, status and charge item mappings are not settings, see fieldMapFromForm() and CloudbedsValueMaps::fromForm().
     *
     * @param array $post
     * @param array $existing current settings
     * @return array settings
     */
    public static function settingsFromForm(array $post, array $existing = []): array {
        $settings = array_intersect_key($existing, array_flip(CloudbedsConfig::SETTING_KEYS));

        $settings['defaultHospital'] = trim((string) ($post['defaultHospital'] ?? '')); // hospital id
        $settings['createMissing'] = [
            'hospitals' => !empty($post['createMissing']['hospitals']),
            'rooms' => !empty($post['createMissing']['rooms']),
            'genLookups' => !empty($post['createMissing']['genLookups']),
        ];
        $settings['unmappedCustomFields'] = (string) ($post['unmappedCustomFields'] ?? 'note');
        $settings['importGuestNotes'] = !empty($post['importGuestNotes']);

        return $settings;
    }
}
