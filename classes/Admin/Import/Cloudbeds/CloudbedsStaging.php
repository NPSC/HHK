<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Staging tables for the Cloudbeds import.
 *
 * Data is first fetched from the Cloudbeds APIs into `import_cloudbeds` (one row per profile, reservation and folio, payload is the JSON from the API),
 * then imported into HHK in batches. This keeps the slow, rate limited API calls separate from the database work,
 * makes both steps resumable and lets the custom fields be reviewed before anything is written to HHK.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsStaging {

    public const TBL_NAME = 'import_cloudbeds';
    public const META_TBL_NAME = 'import_cloudbeds_meta';

    public const PROFILE = 'profile';
    public const RESERVATION = 'reservation';
    public const FOLIO = 'folio';

    /** Import order: people, then reservations/visits, then folios */
    public const PHASES = [self::PROFILE => 1, self::RESERVATION => 2, self::FOLIO => 3];

    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const DONE = 'done';
    public const ERROR = 'error';
    public const SKIPPED = 'skipped';

    public function __construct(protected \PDO $dbh) {
    }

    public function ensureTables(): void {
        $this->dbh->exec("CREATE TABLE IF NOT EXISTS `" . self::TBL_NAME . "` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `entityType` ENUM('profile', 'reservation', 'folio') NOT NULL,
            `phase` TINYINT NOT NULL,
            `cloudbedsId` VARCHAR(45) NOT NULL,
            `parentId` VARCHAR(45) NOT NULL DEFAULT '',
            `propertyId` VARCHAR(45) NOT NULL DEFAULT '',
            `payload` LONGTEXT NOT NULL,
            `status` ENUM('pending', 'processing', 'done', 'error', 'skipped') NOT NULL DEFAULT 'pending',
            `workerId` VARCHAR(32) NULL,
            `hhkId` INT NULL,
            `hhkData` TEXT NULL,
            `message` TEXT NULL,
            `fetchedAt` DATETIME NULL,
            `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_entity` (`entityType`, `cloudbedsId`),
            KEY `idx_batch` (`status`, `phase`, `id`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4");

        $this->dbh->exec("CREATE TABLE IF NOT EXISTS `" . self::META_TBL_NAME . "` (
            `k` VARCHAR(45) NOT NULL,
            `v` TEXT NULL,
            PRIMARY KEY (`k`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4");
    }

    /**
     * Discard all staged data (does not touch anything already imported into HHK)
     */
    public function reset(): void {
        $this->dbh->exec("DROP TABLE IF EXISTS `" . self::TBL_NAME . "`");
        $this->dbh->exec("DROP TABLE IF EXISTS `" . self::META_TBL_NAME . "`");
        $this->ensureTables();
    }

    public function getMeta(string $key, string $default = ''): string {
        $stmt = $this->dbh->prepare("select `v` from `" . self::META_TBL_NAME . "` where `k` = :k");
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? $default : (string) $v;
    }

    public function setMeta(string $key, string|int $value): void {
        $stmt = $this->dbh->prepare("insert into `" . self::META_TBL_NAME . "` (`k`, `v`) values (:k, :v) on duplicate key update `v` = :v2");
        $stmt->execute([':k' => $key, ':v' => (string) $value, ':v2' => (string) $value]);
    }

    protected function encode(array $payload): string {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    /**
     * Insert a staged record, or replace the payload of an existing one that hasn't been imported yet
     */
    public function upsert(string $type, string $cloudbedsId, array $payload, string $parentId = '', string $propertyId = ''): void {
        $json = $this->encode($payload);
        $stmt = $this->dbh->prepare("insert into `" . self::TBL_NAME . "` (`entityType`, `phase`, `cloudbedsId`, `parentId`, `propertyId`, `payload`) values (:type, :phase, :id, :parent, :property, :payload)
            on duplicate key update `payload` = if(`status` = 'done', `payload`, :payload2)");
        $stmt->execute([
            ':type' => $type, ':phase' => self::PHASES[$type], ':id' => $cloudbedsId, ':parent' => $parentId,
            ':property' => $propertyId, ':payload' => $json, ':payload2' => $json,
        ]);
    }

    /**
     * @return array|null decoded payload
     */
    public function getPayload(string $type, string $cloudbedsId): ?array {
        $stmt = $this->dbh->prepare("select `payload` from `" . self::TBL_NAME . "` where `entityType` = :type and `cloudbedsId` = :id");
        $stmt->execute([':type' => $type, ':id' => $cloudbedsId]);
        $json = $stmt->fetchColumn();
        return $json === false ? null : json_decode($json, true);
    }

    /**
     * @return array|null the row with payload and hhkData decoded
     */
    public function getRow(string $type, string $cloudbedsId): ?array {
        $stmt = $this->dbh->prepare("select * from `" . self::TBL_NAME . "` where `entityType` = :type and `cloudbedsId` = :id");
        $stmt->execute([':type' => $type, ':id' => $cloudbedsId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row = $this->decodeRow($row);
        $row['hhkData'] = $row['hhkData'] !== null ? (json_decode($row['hhkData'], true) ?? []) : [];
        return $row;
    }

    public function setPayload(int $rowId, array $payload): void {
        $stmt = $this->dbh->prepare("update `" . self::TBL_NAME . "` set `payload` = :payload where `id` = :id");
        $stmt->execute([':payload' => $this->encode($payload), ':id' => $rowId]);
    }

    /**
     * Rows of a type whose extra API data has not been fetched yet
     * (profiles: custom fields and reservations, reservations: folios)
     *
     * @return array[] rows with the payload decoded
     */
    public function nextUnfetched(string $type, int $limit): array {
        $stmt = $this->dbh->prepare("select * from `" . self::TBL_NAME . "` where `entityType` = :type and `fetchedAt` is null and `status` = 'pending' order by `id` limit " . (int) $limit);
        $stmt->execute([':type' => $type]);
        return array_map([$this, 'decodeRow'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Profiles after the given row id, for steps that walk through the profiles keeping a cursor
     *
     * @return array[] rows with the payload decoded
     */
    public function nextProfilesAfter(int $afterId, int $limit): array {
        $stmt = $this->dbh->prepare("select * from `" . self::TBL_NAME . "` where `entityType` = 'profile' and `status` = 'pending' and `id` > :after order by `id` limit " . (int) $limit);
        $stmt->execute([':after' => $afterId]);
        return array_map([$this, 'decodeRow'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function markFetched(int $rowId): void {
        $this->dbh->exec("update `" . self::TBL_NAME . "` set `fetchedAt` = now() where `id` = " . (int) $rowId);
    }

    public function countUnfetched(string $type): int {
        $stmt = $this->dbh->prepare("select count(*) from `" . self::TBL_NAME . "` where `entityType` = :type and `fetchedAt` is null and `status` = 'pending'");
        $stmt->execute([':type' => $type]);
        return (int) $stmt->fetchColumn();
    }

    protected function decodeRow(array $row): array {
        $row['payload'] = json_decode($row['payload'], true) ?? [];
        return $row;
    }

    /**
     * Claim the next batch of pending rows for a worker. A batch only contains rows of the earliest pending phase so
     * people are always imported before reservations, and reservations before folios.
     *
     * @return array[] claimed rows with the payload decoded
     */
    public function claimBatch(string $workerId, int $limit): array {
        $phase = $this->dbh->query("select min(`phase`) from `" . self::TBL_NAME . "` where `status` = 'pending'")->fetchColumn();
        if ($phase === false || $phase === null) {
            return [];
        }

        $stmt = $this->dbh->prepare("update `" . self::TBL_NAME . "` set `status` = 'processing', `workerId` = :worker where `status` = 'pending' and `phase` = :phase order by `id` limit " . (int) $limit);
        $stmt->execute([':worker' => $workerId, ':phase' => $phase]);

        $stmt = $this->dbh->prepare("select * from `" . self::TBL_NAME . "` where `status` = 'processing' and `workerId` = :worker order by `id`");
        $stmt->execute([':worker' => $workerId]);

        return array_map([$this, 'decodeRow'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function markDone(int $rowId, ?int $hhkId = null, array $hhkData = [], string $message = ''): void {
        $this->finish($rowId, self::DONE, $hhkId, $hhkData, $message);
    }

    public function markSkipped(int $rowId, string $message): void {
        $this->finish($rowId, self::SKIPPED, null, [], $message);
    }

    public function markError(int $rowId, string $message): void {
        $this->finish($rowId, self::ERROR, null, [], $message);
    }

    protected function finish(int $rowId, string $status, ?int $hhkId, array $hhkData, string $message): void {
        $stmt = $this->dbh->prepare("update `" . self::TBL_NAME . "` set `status` = :status, `hhkId` = :hhkId, `hhkData` = :hhkData, `message` = :message where `id` = :id");
        $stmt->execute([
            ':status' => $status, ':hhkId' => $hhkId, ':hhkData' => count($hhkData) > 0 ? json_encode($hhkData) : null,
            ':message' => $message !== '' ? mb_substr($message, 0, 5000) : null, ':id' => $rowId,
        ]);
    }

    /**
     * Put every imported/failed/skipped row back to pending, e.g. after undoing an import
     */
    public function resetProcessed(): void {
        $this->dbh->exec("update `" . self::TBL_NAME . "` set `status` = 'pending', `workerId` = null, `hhkId` = null, `hhkData` = null, `message` = null where `status` != 'pending'");
    }

    /**
     * Put failed rows and rows left in 'processing' (e.g. the request timed out) back to pending so they are retried
     */
    public function retryFailed(): int {
        return (int) $this->dbh->exec("update `" . self::TBL_NAME . "` set `status` = 'pending', `workerId` = null, `message` = null where `status` in ('error', 'processing')");
    }

    /**
     * @return array<string, array<string,int>> [entityType => [status => count]]
     */
    public function counts(): array {
        $counts = [];
        foreach ([self::PROFILE, self::RESERVATION, self::FOLIO] as $type) {
            $counts[$type] = [self::PENDING => 0, self::PROCESSING => 0, self::DONE => 0, self::ERROR => 0, self::SKIPPED => 0];
        }

        $stmt = $this->dbh->query("select `entityType`, `status`, count(*) as `n` from `" . self::TBL_NAME . "` group by `entityType`, `status`");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $counts[$row['entityType']][$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * @return array{total: int, processed: int, remaining: int, errors: int, progress: int}
     */
    public function getProgress(): array {
        $row = $this->dbh->query("select count(*) as `total`, coalesce(sum(`status` in ('done', 'skipped', 'error')), 0) as `processed`, coalesce(sum(`status` = 'error'), 0) as `errors` from `" . self::TBL_NAME . "`")->fetch(\PDO::FETCH_ASSOC);
        $total = (int) $row['total'];
        $processed = (int) $row['processed'];

        return [
            'total' => $total,
            'processed' => $processed,
            'remaining' => $total - $processed,
            'errors' => (int) $row['errors'],
            'progress' => $total > 0 ? (int) floor($processed / $total * 100) : 0,
        ];
    }

    /**
     * @return array[] most recent failed rows
     */
    public function getErrors(int $limit = 50): array {
        return $this->dbh->query("select `entityType`, `cloudbedsId`, `message` from `" . self::TBL_NAME . "` where `status` = 'error' order by `Timestamp` desc limit " . (int) $limit)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Call $callback with the decoded payload of every row of a type, reading in chunks to keep memory use flat
     *
     * @param string $type
     * @param callable(array):void $callback
     */
    public function eachPayload(string $type, callable $callback): void {
        $lastId = 0;
        $stmt = $this->dbh->prepare("select `id`, `payload` from `" . self::TBL_NAME . "` where `entityType` = :type and `id` > :last order by `id` limit 500");

        do {
            $stmt->execute([':type' => $type, ':last' => $lastId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $callback(json_decode($row['payload'], true) ?? []);
            }
        } while (count($rows) === 500);
    }

    /**
     * The room names, payment methods and reservation statuses found in the staged data, for filling in the mapping tables
     *
     * @return array{rooms: string[], paymentMethods: string[], statuses: string[], chargeTypes: string[]} each sorted and without duplicates
     */
    public function summarizeValues(): array {
        $rooms = [];
        $methods = [];
        $statuses = [];
        $chargeTypes = [];

        $this->eachPayload(self::RESERVATION, function (array $payload) use (&$rooms, &$statuses) {
            foreach ((array) ($payload['summary']['rooms'] ?? []) as $room) {
                $name = trim((string) ($room['roomName'] ?? ''));
                if ($name !== '') {
                    $rooms[$name] = true;
                }
            }

            $status = trim((string) ($payload['summary']['reservationStatus'] ?? ''));
            if ($status !== '') {
                $statuses[$status] = true;
            }
        });

        $this->eachPayload(self::FOLIO, function (array $payload) use (&$methods, &$chargeTypes) {
            foreach ((array) ($payload['transactions'] ?? []) as $transaction) {
                $method = trim((string) ($transaction['paymentDetails']['paymentMethod'] ?? ''));
                if ($method !== '') {
                    $methods[$method] = true;
                }

                $type = trim((string) ($transaction['transactionType'] ?? ''));
                if ($type !== '' && $type !== 'payment' && ($transaction['internalCodeGroup'] ?? '') !== 'PAYMENT') {
                    $chargeTypes[$type] = true;
                }
            }
        });

        $sorted = function (array $found): array {
            $names = array_map('strval', array_keys($found));
            natcasesort($names);
            return array_values($names);
        };

        return ['rooms' => $sorted($rooms), 'paymentMethods' => $sorted($methods), 'statuses' => $sorted($statuses), 'chargeTypes' => $sorted($chargeTypes)];
    }

    /**
     * Distinct values found for gen-lookup-backed fields, after applying the current custom field mapping, with how many
     * staged records have each value. Used to review which values (e.g. a relationship, an ethnicity) HHK doesn't have yet,
     * see CloudbedsImport::GEN_LOOKUP_TARGETS.
     *
     * @param CloudbedsFieldMapper $mapper
     * @param array<string,string> $targets [mapper target => gen lookup table name]. guest.* targets are read from staged
     *                                        profiles, everything else from staged reservations.
     * @return array<string, array<string,int>> [genLookupTableName => [rawValue => count]]
     */
    public function summarizeGenLookupValues(CloudbedsFieldMapper $mapper, array $targets): array {
        $guestTargets = array_filter($targets, fn($t) => str_starts_with($t, 'guest.'), ARRAY_FILTER_USE_KEY);
        $reservationTargets = array_diff_key($targets, $guestTargets);
        $counts = [];

        $tally = function (string $table, string $value) use (&$counts) {
            $value = trim($value);
            if ($value !== '') {
                $counts[$table][$value] = ($counts[$table][$value] ?? 0) + 1;
            }
        };

        if (count($guestTargets) > 0) {
            $this->eachPayload(self::PROFILE, function (array $payload) use ($mapper, $guestTargets, $tally) {
                $values = $mapper->apply(CloudbedsFieldMapper::SCOPE_GUEST, (array) ($payload['customFields'] ?? []))['values'];
                foreach ($guestTargets as $target => $table) {
                    if (isset($values[$target])) {
                        $tally($table, $values[$target]);
                    }
                }
            });
        }

        if (count($reservationTargets) > 0) {
            $this->eachPayload(self::RESERVATION, function (array $payload) use ($mapper, $reservationTargets, $tally) {
                $values = $mapper->apply(CloudbedsFieldMapper::SCOPE_RESERVATION, (array) ($payload['customFields'] ?? []))['values'];
                foreach ($reservationTargets as $target => $table) {
                    if (isset($values[$target])) {
                        $tally($table, $values[$target]);
                    }
                }
            });
        }

        return $counts;
    }

    /**
     * Every custom field found in the staged data so the mappings in the config can be reviewed
     *
     * @param CloudbedsFieldMapper $mapper
     * @return array[] ["scope", "id", "shortcode", "name", "count", "sample", "target", "mappedKey" (the config key that matched)]
     */
    public function summarizeCustomFields(CloudbedsFieldMapper $mapper): array {
        $found = [];

        foreach ([self::PROFILE => CloudbedsFieldMapper::SCOPE_GUEST, self::RESERVATION => CloudbedsFieldMapper::SCOPE_RESERVATION] as $type => $scope) {
            $this->eachPayload($type, function (array $payload) use (&$found, $scope, $mapper) {
                foreach ((array) ($payload['customFields'] ?? []) as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $f = CloudbedsFieldMapper::normalizeField($field);
                    $key = $scope . '|' . ($f['id'] !== '' ? $f['id'] : $f['shortcode'] . '|' . $f['name']);

                    $match = $mapper->resolve($scope, $f);
                    $found[$key] ??= $f + ['scope' => $scope, 'count' => 0, 'sample' => '', 'target' => $match['target'] ?? null, 'mappedKey' => $match['key'] ?? null];
                    if ($f['value'] !== '') {
                        $found[$key]['count']++;
                        if ($found[$key]['sample'] === '') {
                            $found[$key]['sample'] = mb_substr($f['value'], 0, 60);
                        }
                    }
                }
            });
        }

        usort($found, fn($a, $b) => [$a['scope'], $a['name']] <=> [$b['scope'], $b['name']]);
        return $found;
    }
}
