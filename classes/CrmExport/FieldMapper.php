<?php
namespace HHK\CrmExport;

/**
 * Loads CRM field mappings from crm_field_map and translates canonical
 * vguest_canonical row keys into the field names expected by a specific CRM object.
 *
 * Default mappings are seeded on first use via insertDefaults().
 */
class FieldMapper {

    /** @var array<string, array<string,string>>  crm_object => [hhk_field => crm_field] */
    private array $exportMaps = [];

    /** @var list<string>  hhk_fields where in_search = 1 */
    private array $searchHhkFields = [];

    private int $gatewayId;

    public function __construct(\PDO $dbh, int $gatewayId) {
        $this->gatewayId = $gatewayId;
        $stmt = $dbh->query(
            "SELECT * FROM `crm_field_map`
             WHERE `gateway_id` = $gatewayId
             ORDER BY `display_order`, `crm_object`, `hhk_field`"
        );
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $obj = $r['crm_object'];
            $hhk = $r['hhk_field'];
            $crm = $r['crm_field'];
            if ($r['in_export']) {
                $this->exportMaps[$obj][$hhk] = $crm;
            }
            if ($r['in_search']) {
                $this->searchHhkFields[] = $hhk;
            }
        }
    }

    /**
     * Translate a canonical vguest_canonical row into CRM-named fields for one object.
     * Empty-string values are omitted so the caller can send only populated fields.
     *
     * @param array<string,mixed>  $canonicalRow
     * @param string               $crmObject    e.g. 'Contact', 'Account', 'npe4__Relationship__c'
     * @return array<string,mixed>
     */
    public function translateRow(array $canonicalRow, string $crmObject): array {
        $result = [];
        foreach ($this->exportMaps[$crmObject] ?? [] as $hhkField => $crmField) {
            if (isset($canonicalRow[$hhkField]) && $canonicalRow[$hhkField] !== '') {
                $result[$crmField] = $canonicalRow[$hhkField];
            }
        }
        return $result;
    }

    /**
     * HHK field names (canonical) flagged in_search = 1.
     * Used to build the WHERE clause when searching the remote system.
     * @return list<string>
     */
    public function getSearchHhkFields(): array {
        return $this->searchHhkFields;
    }

    /**
     * hhk_field => crm_field pairs for the given object (all exported fields).
     * Used by updateRemoteMember to detect stale remote values.
     * @return array<string,string>
     */
    public function getExportMap(string $crmObject): array {
        return $this->exportMaps[$crmObject] ?? [];
    }

    /**
     * All CRM field names (values) mapped for the given object.
     * Used to build the SELECT clause of remote queries.
     * @return list<string>
     */
    public function getCrmFields(string $crmObject): array {
        return array_values($this->exportMaps[$crmObject] ?? []);
    }

    public function hasObject(string $crmObject): bool {
        return !empty($this->exportMaps[$crmObject]);
    }

    public function isEmpty(): bool {
        return empty($this->exportMaps);
    }

    /**
     * Wipe any existing mappings for this gateway and reseed with service defaults.
     */
    public static function resetToDefaults(\PDO $dbh, int $gatewayId, string $serviceName): void {
        $dbh->prepare("DELETE FROM `crm_field_map` WHERE `gateway_id` = :gw")->execute(['gw' => $gatewayId]);
        self::insertDefaults($dbh, $gatewayId, $serviceName);
    }

    /**
     * Seed crm_field_map with service defaults if no rows exist yet for this gateway.
     */
    public static function insertDefaults(\PDO $dbh, int $gatewayId, string $serviceName): void {
        $stmt = $dbh->query("SELECT COUNT(*) FROM `crm_field_map` WHERE `gateway_id` = $gatewayId");
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $defaults = self::getServiceDefaults($serviceName);
        if (empty($defaults)) {
            return;
        }

        $insert = $dbh->prepare(
            "INSERT INTO `crm_field_map`
                (`gateway_id`, `crm_object`, `hhk_field`, `crm_field`,
                 `in_search`, `in_export`, `display_order`)
             VALUES (:gw, :obj, :hhk, :crm, :srch, :exp, :ord)"
        );

        foreach ($defaults as $i => $row) {
            $insert->execute([
                'gw'   => $gatewayId,
                'obj'  => $row[0],
                'hhk'  => $row[1],
                'crm'  => $row[2],
                'srch' => $row[3],
                'exp'  => $row[4],
                'ord'  => ($i + 1) * 10,
            ]);
        }
    }

    private static function getServiceDefaults(string $serviceName): array {
        return match (strtolower($serviceName)) {
            'sf'   => self::sfDefaults(),
            'neon' => [],   // Phase 2
            default => [],
        };
    }

    /**
     * Default SF field mapping seeded on first gateway save.
     * Columns: [crm_object, hhk_field, crm_field, in_search, in_export]
     */
    private static function sfDefaults(): array {
        return [
            ['Contact', 'prefix',                'Salutation',        0, 1],
            ['Contact', 'first_name',           'FirstName',         1, 1],
            ['Contact', 'middle_name',          'Middle_Name__c',    0, 1],
            ['Contact', 'last_name',            'LastName',          1, 1],
            ['Contact', 'suffix',               'Suffix__c',         0, 1],
            ['Contact', 'nickname',             'Nickname__c',       0, 1],
            ['Contact', 'gender',               'Gender__c',         0, 1],
            ['Contact', 'birthdate',            'Birthdate',         0, 1],
            ['Contact', 'email',                'Email',             1, 1],
            ['Contact', 'home_phone',           'HomePhone',         0, 1],
            ['Contact', 'address.home.street',      'MailingStreet',     0, 1],
            ['Contact', 'address.home.city',        'MailingCity',       0, 1],
            ['Contact', 'address.home.state',       'MailingState',      0, 1],
            ['Contact', 'address.home.postal_code', 'MailingPostalCode', 0, 1],
            ['Contact', 'address.home.country',     'MailingCountry',    0, 1],
            ['Contact', 'contact_type',              'Contact_Type__c',   0, 1],
            ['Contact', 'is_deceased',              'Deceased__c',       0, 1],
            ['Account',               'psg_id',                  'HHK_idPsg__c',     0, 1],
            ['npe4__Relationship__c', 'relationship_to_patient', 'npe4__Type__c',    0, 1],
            ['npe4__Relationship__c', 'legal_custody',           'Legal_Custody__c', 0, 1],
        ];
    }
}
