<?php
namespace HHK\Tables;

use HHK\AuditLog\LogOptions;
use HHK\Tables\Fields\DB_Field;

/**
 * Auditable.php
 *
 * Writes every EditRS insert/update/delete to `audit_log`, which uses the exact schema of
 * spatie/laravel-activitylog v4 (plus two virtual search columns and extra indexes).
 *
 * properties JSON:
 *   attributes        new values (created/deleted: the full row, updated: changed columns only)
 *   old               old values of the changed columns (updated only)
 *   key               canonical primary key, "col=val,col=val" (indexed as record_key)
 *   checkpoint        true when the row holds a full copy of the record (indexed as is_checkpoint)
 *   snapshot          full row before this row's change (checkpoint updates and rollups)
 *   since_checkpoint  number of updates since the record's last checkpoint
 *   user, source      username and script, for readability
 *
 * A record's state at any time is rebuilt from its latest checkpoint plus the diffs after it,
 * see getRecordAsOf().
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
trait Auditable {

    /** Turn off to skip auditing, e.g. for bulk jobs. */
    public static bool $auditEnabled = true;

    const AUDIT_TABLE = 'audit_log';
    const CHECKPOINT_EVERY = 20;
    const MERGE_WINDOW_SECONDS = 120;
    const MAX_VALUE_LENGTH = 4096;
    const CAUSER_TYPE = 'user';

    /** @var array<string, string[]> primary key columns by table */
    private static array $auditPkCache = [];

    private static ?string $auditBatchUuid = null;

    /**
     * Start a new batch: rows written after this share a new batch_uuid.  Each request gets one automatically.
     */
    public static function newAuditBatch(): string {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        self::$auditBatchUuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
        return self::$auditBatchUuid;
    }

    /**
     * Write a manual entry, like spatie's activity()->log().
     *
     * @param \PDO $dbh
     * @param string $description
     * @param array $properties
     * @param string $logName
     * @param string|null $subjectType table name
     * @param int|null $subjectId
     */
    public static function logActivity(\PDO $dbh, string $description, array $properties = [], string $logName = 'default', ?string $subjectType = null, ?int $subjectId = null): void {
        try {
            self::insertActivity($dbh, $logName, $description, null, $subjectType, $subjectId, $properties);
        } catch (\Throwable $e) {
            error_log('Audit log write failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the rows an update or delete is about to change, as they are now.
     *
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param array $whereDbFieldArray
     * @param string $combiner
     * @return array
     */
    protected static function auditBeforeWrite(\PDO $dbh, TableRSInterface $rs, array $whereDbFieldArray, string $combiner = 'and'): array {

        if (is_null(self::auditOptions($rs)) || count($whereDbFieldArray) == 0) {
            return [];
        }

        try {
            return EditRS::select($dbh, $rs, $whereDbFieldArray, $combiner);
        } catch (\Throwable $e) {
            error_log('Audit log before-image failed for ' . $rs->getTableName() . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param int|string $id last insert id
     */
    protected static function auditInsert(\PDO $dbh, TableRSInterface $rs, $id): void {

        if (is_null($opts = self::auditOptions($rs))) {
            return;
        }

        try {
            $table = $rs->getTableName();
            $pkCols = self::primaryKeyCols($dbh, $table);
            $types = self::fieldTypes($rs);

            $row = [];
            foreach (get_object_vars($rs) as $dbF) {
                if ($dbF instanceof DB_Field) {
                    $row[$dbF->getColUnticked()] = is_null($dbF->getNewVal()) ? $dbF->getStoredVal() : $dbF->getNewVal();
                }
            }

            if (count($pkCols) == 1 && (int)$id > 0 && empty($row[$pkCols[0]])) {
                $row[$pkCols[0]] = $id;
            }

            // read the row back so database defaults are captured
            $row = self::selectByKey($dbh, $table, $pkCols, $row) ?? $row;

            $attributes = self::cleanRow($row, $opts, $types);

            self::writeRecordActivity($dbh, $opts, 'created', $table, $pkCols, $row, [
                'attributes' => $attributes,
                'checkpoint' => true,
                'since_checkpoint' => 0,
            ]);

        } catch (\Throwable $e) {
            error_log('Audit log insert failed for ' . $rs->getTableName() . ': ' . $e->getMessage());
        }
    }

    /**
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param array $beforeRows from auditBeforeWrite()
     */
    protected static function auditUpdate(\PDO $dbh, TableRSInterface $rs, array $beforeRows): void {

        if (is_null($opts = self::auditOptions($rs)) || count($beforeRows) == 0) {
            return;
        }

        try {
            $table = $rs->getTableName();
            $pkCols = self::primaryKeyCols($dbh, $table);
            $types = self::fieldTypes($rs);

            // the same columns EditRS::update() sets
            $newVals = [];
            foreach (get_object_vars($rs) as $dbF) {
                if ($dbF instanceof DB_Field && !is_null($dbF->getNewVal()) && $dbF->getNewVal() != $dbF->getStoredVal()) {
                    $newVals[$dbF->getColUnticked()] = ($dbF->getDbType() == \PDO::PARAM_NULL ? null : $dbF->getNewVal());
                }
            }

            foreach ($beforeRows as $before) {

                $old = [];
                $new = [];
                $meaningful = false;

                foreach ($newVals as $col => $nv) {

                    if (!$opts->logsColumn($col)) {
                        continue;
                    }

                    $o = self::cleanValue($before[$col] ?? null, $types[$col] ?? null);
                    $n = self::cleanValue($nv, $types[$col] ?? null);

                    if (self::sameValue($o, $n)) {
                        continue;
                    }

                    $old[$col] = $opts->isRedacted($col) ? '***' : $o;
                    $new[$col] = $opts->isRedacted($col) ? '***' : $n;

                    if (!$opts->isIgnored($col)) {
                        $meaningful = true;
                    }
                }

                if ($meaningful) {
                    self::writeUpdateActivity($dbh, $opts, $table, $pkCols, $before, self::cleanRow($before, $opts, $types), $new, $old);
                }
            }

        } catch (\Throwable $e) {
            error_log('Audit log update failed for ' . $rs->getTableName() . ': ' . $e->getMessage());
        }
    }

    /**
     * @param \PDO $dbh
     * @param TableRSInterface $rs
     * @param array $beforeRows from auditBeforeWrite()
     */
    protected static function auditDelete(\PDO $dbh, TableRSInterface $rs, array $beforeRows): void {

        if (is_null($opts = self::auditOptions($rs))) {
            return;
        }

        try {
            $table = $rs->getTableName();
            $pkCols = self::primaryKeyCols($dbh, $table);
            $types = self::fieldTypes($rs);

            foreach ($beforeRows as $before) {
                self::writeRecordActivity($dbh, $opts, 'deleted', $table, $pkCols, $before, [
                    'attributes' => self::cleanRow($before, $opts, $types),
                    'checkpoint' => true,
                    'since_checkpoint' => 0,
                ]);
            }

        } catch (\Throwable $e) {
            error_log('Audit log delete failed for ' . $rs->getTableName() . ': ' . $e->getMessage());
        }
    }

    /**
     * Rebuild a record as it was at a point in time.
     *
     * @param \PDO $dbh
     * @param string $table
     * @param array $key primary key column => value
     * @param string $asOf 'Y-m-d H:i:s'
     * @return array|null null if the record did not exist (or is not known) at $asOf
     */
    public static function getRecordAsOf(\PDO $dbh, string $table, array $key, string $asOf): ?array {
        return self::getRecordAsOfByKey($dbh, $table, self::recordKey(self::primaryKeyCols($dbh, $table), $key), $asOf);
    }

    /**
     * Rows are ordered by (created_at, id): a purge rollup row has a newer id than the rows after its time.
     *
     * @param \PDO $dbh
     * @param string $table
     * @param string $recordKey canonical key from recordKey()
     * @param string $asOf 'Y-m-d H:i:s'
     * @return array|null
     */
    public static function getRecordAsOfByKey(\PDO $dbh, string $table, string $recordKey, string $asOf): ?array {

        $stmt = $dbh->prepare("select id, created_at from `" . self::AUDIT_TABLE . "` where subject_type = :t and record_key = :k and is_checkpoint = 1 and created_at <= :asOf order by created_at desc, id desc limit 1");
        $stmt->execute([':t' => $table, ':k' => $recordKey, ':asOf' => $asOf]);
        $checkpoint = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($checkpoint === false) {
            // before the first row: an update's snapshot holds the state from before auditing started
            $stmt = $dbh->prepare("select event, properties from `" . self::AUDIT_TABLE . "` where subject_type = :t and record_key = :k order by created_at, id limit 1");
            $stmt->execute([':t' => $table, ':k' => $recordKey]);
            $first = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($first && $first['event'] == 'updated') {
                $p = json_decode($first['properties'], true);
                return $p['snapshot'] ?? null;
            }
            return null;
        }

        $stmt = $dbh->prepare("select event, properties from `" . self::AUDIT_TABLE . "` where subject_type = :t and record_key = :k and created_at <= :asOf
            and (created_at > :cpTime or (created_at = :cpTime2 and id >= :cpId)) order by created_at, id");
        $stmt->execute([':t' => $table, ':k' => $recordKey, ':asOf' => $asOf, ':cpTime' => $checkpoint['created_at'], ':cpTime2' => $checkpoint['created_at'], ':cpId' => $checkpoint['id']]);

        $state = null;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $p = json_decode($r['properties'], true) ?? [];

            if ($r['event'] == 'created') {
                $state = $p['attributes'] ?? [];
            } else if ($r['event'] == 'deleted') {
                $state = null;
            } else {
                if (isset($p['snapshot'])) {
                    $state = $p['snapshot'];
                }
                $state = array_merge($state ?? [], $p['attributes'] ?? []);
            }
        }

        return $state;
    }

    /**
     * Delete rows older than $cutoff.  A record that still existed at $cutoff first gets a rollup checkpoint
     * holding its state then, so getRecordAsOf() keeps working from $cutoff on.
     *
     * @param \PDO $dbh
     * @param string $cutoff 'Y-m-d H:i:s'
     * @param int $maxRecords records handled per call
     * @param bool $dryRun count only
     * @return int records rolled up (or that would be)
     */
    public static function purgeActivityBefore(\PDO $dbh, string $cutoff, int $maxRecords = 5000, bool $dryRun = false): int {

        // event is null on rollups, so a record whose only old row is an earlier rollup is left alone
        $stmt = $dbh->prepare("select distinct subject_type, record_key from `" . self::AUDIT_TABLE . "` where created_at < :c and record_key is not null and event is not null limit " . (int)$maxRecords);
        $stmt->execute([':c' => $cutoff]);
        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($dryRun) {
            return count($records);
        }

        $del = $dbh->prepare("delete from `" . self::AUDIT_TABLE . "` where subject_type = :t and record_key = :k and created_at < :c");
        $subj = $dbh->prepare("select subject_id from `" . self::AUDIT_TABLE . "` where subject_type = :t and record_key = :k order by created_at desc, id desc limit 1");
        $ins = $dbh->prepare("insert into `" . self::AUDIT_TABLE . "`
            (log_name, description, subject_type, event, subject_id, causer_type, causer_id, properties, batch_uuid, created_at, updated_at)
            values ('default', 'checkpoint', :t, null, :sid, null, null, :p, :b, :c, :c2)");

        foreach ($records as $r) {

            $dbh->beginTransaction();

            try {
                $state = self::getRecordAsOfByKey($dbh, $r['subject_type'], $r['record_key'], $cutoff);

                if (!is_null($state)) {
                    $subj->execute([':t' => $r['subject_type'], ':k' => $r['record_key']]);

                    $ins->execute([
                        ':t' => $r['subject_type'],
                        ':sid' => $subj->fetchColumn() ?: null,
                        ':p' => self::encode([
                            'attributes' => new \stdClass(),
                            'key' => $r['record_key'],
                            'checkpoint' => true,
                            'snapshot' => $state,
                            'since_checkpoint' => 0,
                            'user' => '',
                            'source' => 'purge',
                        ]),
                        ':b' => self::batchUuid(),
                        ':c' => $cutoff,
                        ':c2' => $cutoff,
                    ]);
                }

                $del->execute([':t' => $r['subject_type'], ':k' => $r['record_key'], ':c' => $cutoff]);

                $dbh->commit();

            } catch (\Throwable $e) {
                $dbh->rollBack();
                throw $e;
            }
        }

        // manual entries (no record)
        if (count($records) < $maxRecords) {
            $dbh->prepare("delete from `" . self::AUDIT_TABLE . "` where created_at < :c and record_key is null")->execute([':c' => $cutoff]);
        }

        return count($records);
    }

    /**
     * Canonical record key, "col=val,col=val" in primary key order.
     *
     * @param string[] $pkCols
     * @param array $row
     * @return string
     */
    public static function recordKey(array $pkCols, array $row): string {
        $parts = [];
        foreach ($pkCols as $col) {
            $parts[] = $col . '=' . ($row[$col] ?? '');
        }
        return implode(',', $parts);
    }

    /**
     * @param \PDO $dbh
     * @param string $table
     * @return string[]
     */
    public static function primaryKeyCols(\PDO $dbh, string $table): array {

        if (!isset(self::$auditPkCache[$table])) {

            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                throw new \InvalidArgumentException('Bad table name: ' . $table);
            }

            $cols = [];
            foreach ($dbh->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll(\PDO::FETCH_ASSOC) as $k) {
                $cols[(int)$k['Seq_in_index']] = $k['Column_name'];
            }
            ksort($cols);
            self::$auditPkCache[$table] = array_values($cols);
        }

        return self::$auditPkCache[$table];
    }

    /**
     * @param TableRSInterface $rs
     * @return LogOptions|null null when this table is not audited
     */
    private static function auditOptions(TableRSInterface $rs): ?LogOptions {

        if (!self::$auditEnabled || $rs->getTableName() == self::AUDIT_TABLE) {
            return null;
        }

        $opts = ($rs instanceof AbstractTableRS ? $rs->getActivitylogOptions() : LogOptions::defaults());

        return $opts->enabled ? $opts : null;
    }

    /**
     * Apply the merge and checkpoint rules, then write an 'updated' row.
     */
    private static function writeUpdateActivity(\PDO $dbh, LogOptions $opts, string $table, array $pkCols, array $keyRow, array $snapshot, array $new, array $old): void {

        $recordKey = self::recordKey($pkCols, $keyRow);
        [$causerId] = self::causer();

        $stmt = $dbh->prepare("select id, event, causer_id, batch_uuid, properties, TIMESTAMPDIFF(SECOND, updated_at, NOW()) as age
            from `" . self::AUDIT_TABLE . "` where subject_type = :t and record_key = :k order by created_at desc, id desc limit 1");
        $stmt->execute([':t' => $table, ':k' => $recordKey]);
        $last = $stmt->fetch(\PDO::FETCH_ASSOC);

        // repeat save of the same record in the same request: fold into the previous row
        if ($last && $last['event'] == 'updated'
                && $last['batch_uuid'] == self::batchUuid()
                && $last['causer_id'] == $causerId
                && (int)$last['age'] <= self::MERGE_WINDOW_SECONDS) {

            $p = json_decode($last['properties'], true);
            $p['attributes'] = array_merge($p['attributes'] ?? [], $new);
            $p['old'] = ($p['old'] ?? []) + $old;   // keep the original old values

            $upd = $dbh->prepare("update `" . self::AUDIT_TABLE . "` set properties = :p, updated_at = NOW() where id = :id");
            $upd->execute([':p' => self::encode($p), ':id' => $last['id']]);
            return;
        }

        $props = ['attributes' => $new, 'old' => $old];

        if (!$last || $last['event'] == 'deleted') {
            // first audited write of this record: keep its state from before auditing
            $since = 0;
        } else {
            $p = json_decode($last['properties'], true);
            $since = (int)($p['since_checkpoint'] ?? 0) + 1;
        }

        if ($since == 0 || $since >= self::CHECKPOINT_EVERY) {
            $since = 0;
            $props['checkpoint'] = true;
            $props['snapshot'] = $snapshot;
        }

        $props['since_checkpoint'] = $since;

        self::writeRecordActivity($dbh, $opts, 'updated', $table, $pkCols, $keyRow, $props);
    }

    /**
     * Write a row about one record.
     */
    private static function writeRecordActivity(\PDO $dbh, LogOptions $opts, string $event, string $table, array $pkCols, array $keyRow, array $props): void {

        // subject_id: the first integer primary key column
        $subjectId = null;
        foreach ($pkCols as $col) {
            if (isset($keyRow[$col]) && ctype_digit((string)$keyRow[$col])) {
                $subjectId = (int)$keyRow[$col];
                break;
            }
        }

        $props['key'] = self::recordKey($pkCols, $keyRow);

        self::insertActivity($dbh, $opts->logName, $event, $event, $table, $subjectId, $props);
    }

    private static function insertActivity(\PDO $dbh, string $logName, string $description, ?string $event, ?string $subjectType, ?int $subjectId, array $props): void {

        [$causerId, $username] = self::causer();

        $props['user'] = $username;
        $props['source'] = $_SERVER['SCRIPT_NAME'] ?? 'cli';

        $stmt = $dbh->prepare("insert into `" . self::AUDIT_TABLE . "`
            (log_name, description, subject_type, event, subject_id, causer_type, causer_id, properties, batch_uuid, created_at, updated_at)
            values (:ln, :d, :st, :ev, :sid, :ct, :cid, :p, :b, NOW(), NOW())");

        $stmt->execute([
            ':ln' => $logName,
            ':d' => $description,
            ':st' => $subjectType,
            ':ev' => $event,
            ':sid' => $subjectId,
            ':ct' => (is_null($causerId) ? null : self::CAUSER_TYPE),
            ':cid' => $causerId,
            ':p' => self::encode($props),
            ':b' => self::batchUuid(),
        ]);
    }

    private static function batchUuid(): string {
        return self::$auditBatchUuid ?? self::newAuditBatch();
    }

    /**
     * Read the session without starting one, so cron and CLI work.
     *
     * @return array{0: int|null, 1: string}
     */
    private static function causer(): array {

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['username'])) {

            // causer_id is unsigned (spatie schema) and the built-in admin user's id is -1,
            // so a non-positive id is left out; properties.user still names who it was
            $uid = (int)($_SESSION['uid'] ?? 0);

            return [($uid > 0 ? $uid : null), (string)$_SESSION['username']];
        }

        return [null, (PHP_SAPI === 'cli' ? 'cli' : '')];
    }

    /**
     * @param \PDO $dbh
     * @param string $table
     * @param string[] $pkCols
     * @param array $row
     * @return array|null
     */
    private static function selectByKey(\PDO $dbh, string $table, array $pkCols, array $row): ?array {

        if (count($pkCols) == 0) {
            return null;
        }

        $where = [];
        $params = [];
        foreach ($pkCols as $i => $col) {
            if (!isset($row[$col]) || $row[$col] === '') {
                return null;
            }
            $where[] = "`$col` = :k$i";
            $params[":k$i"] = $row[$col];
        }

        $stmt = $dbh->prepare("select * from `$table` where " . implode(' and ', $where));
        $stmt->execute($params);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ($r === false ? null : $r);
    }

    /**
     * @param TableRSInterface $rs
     * @return array<string, int> column => PDO param type
     */
    private static function fieldTypes(TableRSInterface $rs): array {
        $types = [];
        foreach (get_object_vars($rs) as $dbF) {
            if ($dbF instanceof DB_Field) {
                $types[$dbF->getColUnticked()] = $dbF->getDbType();
            }
        }
        return $types;
    }

    /**
     * Drop unlogged columns, redact secrets and make values JSON safe.
     */
    private static function cleanRow(array $row, LogOptions $opts, array $types): array {
        $clean = [];
        foreach ($row as $col => $val) {
            if ($opts->logsColumn($col)) {
                $clean[$col] = $opts->isRedacted($col) ? '***' : self::cleanValue($val, $types[$col] ?? null);
            }
        }
        return $clean;
    }

    /**
     * Make a value JSON safe and keep large values out of the log.
     *
     * @param mixed $val
     * @param int|null $dbType
     * @return mixed
     */
    private static function cleanValue($val, ?int $dbType) {

        if (is_null($val) || is_int($val) || is_float($val) || is_bool($val)) {
            return $val;
        }

        $val = (string)$val;

        // bit(1) columns come back from PDO as "\x00" / "\x01"
        if ($dbType === \PDO::PARAM_BOOL || (strlen($val) == 1 && ord($val) < 2)) {
            return ($val === '1' || (strlen($val) > 0 && ord($val) == 1)) ? 1 : 0;
        }

        if (!mb_check_encoding($val, 'UTF-8')) {
            return '[binary ' . strlen($val) . ' bytes, sha1 ' . sha1($val) . ']';
        }

        if (strlen($val) > self::MAX_VALUE_LENGTH) {
            return '[' . strlen($val) . ' chars, sha1 ' . sha1($val) . ']';
        }

        return $val;
    }

    /**
     * Loose compare that treats '2026-01-02' and '2026-01-02 00:00:00' as the same.
     */
    private static function sameValue($a, $b): bool {

        if (is_null($a) || is_null($b)) {
            return is_null($a) && is_null($b);
        }

        if ($a == $b) {
            return true;
        }

        $dateRe = '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/';
        if (is_string($a) && is_string($b) && preg_match($dateRe, $a) && preg_match($dateRe, $b)) {
            return strtotime($a) === strtotime($b);
        }

        return false;
    }

    private static function encode(array $props): string {
        return json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
