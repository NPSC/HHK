<?php
namespace Tests\Unit\Tables;

use HHK\AuditLog\LogOptions;
use HHK\Payment\PaymentSvcs;
use HHK\Tables\AbstractTableRS;
use HHK\Tables\EditRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * In-memory stand-in for MariaDB.  It only understands the statements EditRS and Auditable send,
 * which is enough to run the real audit logic end to end (merge, checkpoints, replay, purge).
 */
class FakeAuditPdo extends \PDO {

    /** @var array<string, string[]> */
    public array $pks = [];

    /** @var array<string, array[]> data tables */
    public array $tables = [];

    /** @var array[] audit_log rows */
    public array $log = [];

    public string $now = '2026-01-01 08:00:00';
    public string $lastId = '0';

    public function __construct() {}

    public function prepare(string $query, array $options = []): \PDOStatement|false {
        return new FakeAuditStmt($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false {
        $s = new FakeAuditStmt($this, $query);
        $s->execute();
        return $s;
    }

    public function lastInsertId(?string $name = null): string|false {
        return $this->lastId;
    }

    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(): bool { return true; }

    public function tick(int $seconds): void {
        $this->now = date('Y-m-d H:i:s', strtotime($this->now) + $seconds);
    }
}

class FakeAuditStmt extends \PDOStatement {

    private array $bound = [];
    private array $result = [];
    private int $count = 0;
    private string $q;

    public function __construct(private FakeAuditPdo $db, string $q) {
        $this->q = trim(preg_replace('/\s+/', ' ', $q), " ;");
    }

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool {
        $this->bound[$this->p($param)] = $value;
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return array_shift($this->result) ?? false;
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array {
        $r = $this->result;
        $this->result = [];
        return $r;
    }

    public function fetchColumn(int $column = 0): mixed {
        $r = array_shift($this->result);
        return $r === null ? false : array_values($r)[$column];
    }

    public function rowCount(): int {
        return $this->count;
    }

    private function p($k): string {
        return ':' . ltrim((string)$k, ':');
    }

    public function execute(?array $params = null): bool {
        $prm = $this->bound;
        foreach ($params ?? [] as $k => $v) {
            $prm[$this->p($k)] = $v;
        }
        $q = $this->q;
        $db = $this->db;

        if (preg_match('/^SHOW KEYS FROM `(\w+)`/', $q, $m)) {
            $this->result = [];
            foreach ($db->pks[$m[1]] ?? [] as $i => $c) {
                $this->result[] = ['Seq_in_index' => $i + 1, 'Column_name' => $c];
            }
        } else if (str_contains($q, 'audit_log')) {
            $this->activity($q, $prm);
        } else if (preg_match('/^select \* from `?(\w+)`? where (.+)$/', $q, $m)) {
            $this->result = array_values(array_filter($db->tables[$m[1]], fn($r) => $this->match($r, $m[2], $prm)));
        } else if (preg_match('/^update (\w+) set (.+) where (.+)$/', $q, $m)) {
            $this->count = 0;
            foreach ($db->tables[$m[1]] as &$row) {
                if ($this->match($row, $m[3], $prm)) {
                    $before = $row;
                    foreach (explode(',', $m[2]) as $set) {
                        [$c, $v] = explode('=', $set, 2);
                        $row[trim($c, '` ')] = $this->val(trim($v), $prm);
                    }
                    $this->count += ($before != $row ? 1 : 0);
                }
            }
        } else if (preg_match('/^insert into (\w+) \((.+)\) values \((.+)\)$/', $q, $m)) {
            $row = array_fill_keys(array_keys($db->tables[$m[1]][0] ?? []), null);
            foreach (array_map(fn($c) => trim($c, '` '), explode(',', $m[2])) as $i => $c) {
                $row[$c] = $this->val(trim(explode(',', $m[3])[$i]), $prm);
            }
            $pk = $db->pks[$m[1]];
            if (count($pk) == 1 && empty($row[$pk[0]])) {
                $row[$pk[0]] = (string)(count($db->tables[$m[1]]) + 100);
                $db->lastId = $row[$pk[0]];
            } else {
                $db->lastId = '0';
            }
            $db->tables[$m[1]][] = $row;
        } else if (preg_match('/^delete from (\w+) where (.+)$/', $q, $m)) {
            $db->tables[$m[1]] = array_values(array_filter($db->tables[$m[1]], fn($r) => !$this->match($r, $m[2], $prm)));
        } else {
            throw new \RuntimeException('FakeAuditPdo does not understand: ' . $q);
        }
        return true;
    }

    private function val(string $token, array $prm) {
        if ($token[0] == ':') return $prm[$token];
        if (strcasecmp($token, 'null') == 0) return null;
        if (strcasecmp($token, 'NOW()') == 0) return $this->db->now;
        return trim($token, "'");
    }

    private function match(array $row, string $where, array $prm): bool {
        $or = str_contains($where, ' or ');
        foreach (preg_split('/ (and|or) /', $where) as $cond) {
            [$c, $v] = array_map('trim', explode('=', $cond, 2));
            $hit = ((string)($row[trim($c, '` ')] ?? '') === (string)$this->val($v, $prm));
            if ($or && $hit) return true;
            if (!$or && !$hit) return false;
        }
        return !$or;
    }

    private function rowsFor(array $prm): array {
        return array_filter($this->db->log, fn($r) => $r['subject_type'] == $prm[':t'] && $r['record_key'] == $prm[':k']);
    }

    private static function byTime(array $rows, bool $desc = false): array {
        usort($rows, fn($a, $b) => [$a['created_at'], $a['id']] <=> [$b['created_at'], $b['id']]);
        return $desc ? array_reverse($rows) : $rows;
    }

    private function activity(string $q, array $prm): void {
        $db = $this->db;

        if (preg_match('/^insert into `audit_log` \((.+)\) values \((.+)\)$/', $q, $m)) {
            $row = ['id' => count($db->log) ? max(array_column($db->log, 'id')) + 1 : 1];
            $vals = array_map('trim', explode(',', $m[2]));
            foreach (array_map('trim', explode(',', $m[1])) as $i => $c) {
                $row[$c] = $this->val($vals[$i], $prm);
            }
            $p = json_decode($row['properties'], true);
            $row['record_key'] = $p['key'] ?? null;
            $row['is_checkpoint'] = isset($p['checkpoint']) ? 1 : 0;
            $db->log[] = $row;

        } else if (str_contains($q, 'TIMESTAMPDIFF')) {
            $r = self::byTime($this->rowsFor($prm), true)[0] ?? null;
            if ($r) {
                $r['age'] = strtotime($db->now) - strtotime($r['updated_at']);
            }
            $this->result = $r ? [$r] : [];

        } else if (preg_match('/^update `audit_log` set properties = :p/', $q)) {
            foreach ($db->log as &$r) {
                if ($r['id'] == $prm[':id']) {
                    $r['properties'] = $prm[':p'];
                    $r['updated_at'] = $db->now;
                }
            }

        } else if (str_contains($q, 'is_checkpoint = 1')) {
            $rows = array_filter($this->rowsFor($prm), fn($r) => $r['is_checkpoint'] && $r['created_at'] <= $prm[':asOf']);
            $this->result = array_slice(self::byTime($rows, true), 0, 1);

        } else if (str_contains($q, ':cpTime')) {
            $rows = array_filter($this->rowsFor($prm), fn($r) => $r['created_at'] <= $prm[':asOf']
                && ($r['created_at'] > $prm[':cpTime'] || ($r['created_at'] == $prm[':cpTime'] && $r['id'] >= $prm[':cpId'])));
            $this->result = self::byTime($rows);

        } else if (str_contains($q, 'order by created_at, id limit 1')) {
            $this->result = array_slice(self::byTime($this->rowsFor($prm)), 0, 1);

        } else if (str_starts_with($q, 'select distinct subject_type, record_key')) {
            $seen = [];
            foreach ($db->log as $r) {
                if ($r['created_at'] < $prm[':c'] && !is_null($r['record_key']) && !is_null($r['event'])) {
                    $seen[$r['subject_type'] . '|' . $r['record_key']] = ['subject_type' => $r['subject_type'], 'record_key' => $r['record_key']];
                }
            }
            $this->result = array_values($seen);

        } else if (str_starts_with($q, 'select subject_id from')) {
            $r = self::byTime($this->rowsFor($prm), true)[0] ?? null;
            $this->result = $r ? [['subject_id' => $r['subject_id']]] : [];

        } else if (str_contains($q, 'record_key is null')) {
            $db->log = array_values(array_filter($db->log, fn($r) => !($r['created_at'] < $prm[':c'] && is_null($r['record_key']))));

        } else if (str_starts_with($q, 'delete from `audit_log`')) {
            $db->log = array_values(array_filter($db->log, fn($r) => !($r['subject_type'] == $prm[':t'] && $r['record_key'] == $prm[':k'] && $r['created_at'] < $prm[':c'])));

        } else {
            throw new \RuntimeException('FakeAuditPdo does not understand: ' . $q);
        }
    }
}

/** composite key (idThing, Span), like visit */
class ThingRS extends AbstractTableRS {
    public DB_Field $idThing;
    public DB_Field $Span;
    public DB_Field $Status;
    public DB_Field $Notes;
    public DB_Field $Password;
    public DB_Field $Last_Updated;

    function __construct($TableName = 'thing') {
        $this->idThing = new DB_Field('idThing', 0, new DbIntSanitizer());
        $this->Span = new DB_Field('Span', 0, new DbIntSanitizer());
        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(15));
        $this->Notes = new DB_Field('Notes', '', new DbStrSanitizer(5000));
        $this->Password = new DB_Field('Password', '', new DbStrSanitizer(100));
        $this->Last_Updated = new DB_Field('Last_Updated', null, new DbDateSanitizer('Y-m-d H:i:s'));
        parent::__construct($TableName);
    }
}

/** single auto-increment key */
class WidgetRS extends AbstractTableRS {
    public DB_Field $idWidget;
    public DB_Field $Title;

    function __construct($TableName = 'widget') {
        $this->idWidget = new DB_Field('idWidget', 0, new DbIntSanitizer());
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(45));
        parent::__construct($TableName);
    }
}

class QuietWidgetRS extends WidgetRS {
    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->disableLogging();
    }
}

#[CoversClass(EditRS::class)]
#[CoversClass(LogOptions::class)]
#[CoversClass(PaymentSvcs::class)]
class AuditableTest extends TestCase {

    private FakeAuditPdo $db;

    protected function setUp(): void {
        $this->db = new FakeAuditPdo();
        $this->db->pks = ['thing' => ['idThing', 'Span'], 'widget' => ['idWidget']];
        $this->db->tables = [
            'thing' => [['idThing' => '5', 'Span' => '1', 'Status' => 'a', 'Notes' => '', 'Password' => 'x', 'Last_Updated' => null]],
            'widget' => [],
        ];
        EditRS::$auditEnabled = true;
        EditRS::newAuditBatch();
    }

    /** a new request: new batch, time moves on */
    private function request(int $seconds = 600): void {
        $this->db->tick($seconds);
        EditRS::newAuditBatch();
    }

    private function updateThing(array $vals, int $id = 5, int $span = 1): void {
        $rs = new ThingRS();
        $rs->idThing->setStoredVal($id);
        $rs->Span->setStoredVal($span);
        EditRS::loadRow(EditRS::select($this->db, $rs, [$rs->idThing, $rs->Span])[0], $rs);
        foreach ($vals as $c => $v) {
            $rs->$c->setNewVal($v);
        }
        EditRS::update($this->db, $rs, [$rs->idThing, $rs->Span]);
    }

    private function props(int $i): array {
        return json_decode($this->db->log[$i]['properties'], true);
    }

    private function asOf(string $t): ?array {
        return EditRS::getRecordAsOf($this->db, 'thing', ['idThing' => 5, 'Span' => 1], $t);
    }

    public function testInsertWritesCreatedCheckpointWithRedaction(): void {
        $rs = new WidgetRS();
        $rs->Title->setNewVal('Blue');
        $id = EditRS::insert($this->db, $rs);

        $this->assertCount(1, $this->db->log);
        $row = $this->db->log[0];
        $this->assertSame('created', $row['event']);
        $this->assertSame('widget', $row['subject_type']);
        $this->assertSame((int)$id, $row['subject_id']);
        $this->assertSame("idWidget=$id", $row['record_key']);
        $this->assertSame(1, $row['is_checkpoint']);
        $this->assertSame('Blue', $this->props(0)['attributes']['Title']);
    }

    public function testFirstUpdateIsCheckpointWithStateBeforeAuditing(): void {
        $this->updateThing(['Status' => 'b', 'Password' => 'newsecret']);

        $p = $this->props(0);
        $this->assertSame('updated', $this->db->log[0]['event']);
        $this->assertSame('idThing=5,Span=1', $p['key']);
        $this->assertSame(5, $this->db->log[0]['subject_id']);
        $this->assertSame(['Status' => 'b', 'Password' => '***'], $p['attributes']);
        $this->assertSame(['Status' => 'a', 'Password' => '***'], $p['old']);
        $this->assertTrue($p['checkpoint']);
        $this->assertSame('a', $p['snapshot']['Status']);
        $this->assertSame('***', $p['snapshot']['Password']);
        $this->assertStringNotContainsString('newsecret', $this->db->log[0]['properties']);
    }

    /** like Invoice::_deleteInvoice() marking all of an invoice's lines deleted in one update */
    public function testMultiRowUpdateLogsEachRecord(): void {
        $this->db->tables['thing'][] = ['idThing' => '5', 'Span' => '2', 'Status' => 'a', 'Notes' => '', 'Password' => '', 'Last_Updated' => null];
        $this->db->tables['thing'][] = ['idThing' => '5', 'Span' => '3', 'Status' => 'z', 'Notes' => '', 'Password' => '', 'Last_Updated' => null];

        $rs = new ThingRS();
        $rs->idThing->setStoredVal(5);
        $rs->Status->setNewVal('z');
        EditRS::update($this->db, $rs, [$rs->idThing]);

        $this->assertSame(['idThing=5,Span=1', 'idThing=5,Span=2'], array_column($this->db->log, 'record_key'), 'span 3 was already z');
        $this->assertSame(['Status' => 'a'], $this->props(1)['old']);
    }

    public function testDeletePaymentRecordsRemovesAndLogsChildren(): void {
        $this->db->pks += ['payment' => ['idPayment'], 'payment_invoice' => ['idPayment_Invoice'], 'payment_info_check' => ['idpayment_info_Check'], 'payment_auth' => ['idPayment_auth']];
        $this->db->tables += [
            'payment' => [['idPayment' => '7', 'Amount' => '10.00'], ['idPayment' => '8', 'Amount' => '5.00']],
            'payment_invoice' => [['idPayment_Invoice' => '1', 'Payment_Id' => '7', 'Invoice_Id' => '3'], ['idPayment_Invoice' => '2', 'Payment_Id' => '8', 'Invoice_Id' => '3']],
            'payment_info_check' => [['idpayment_info_Check' => '4', 'idPayment' => '7', 'Check_Number' => '1001']],
            'payment_auth' => [],
        ];

        PaymentSvcs::deletePaymentRecords($this->db, 7);

        $this->assertSame(['8'], array_column($this->db->tables['payment'], 'idPayment'));
        $this->assertSame(['8'], array_column($this->db->tables['payment_invoice'], 'Payment_Id'));
        $this->assertCount(0, $this->db->tables['payment_info_check']);
        $this->assertSame(
            ['payment_invoice', 'payment_info_check', 'payment'],
            array_column(array_filter($this->db->log, fn($r) => $r['event'] == 'deleted'), 'subject_type')
        );
    }

    /** a plain EditRS::update would skip Status = '' because '' is the field's default */
    public function testUpdateFieldsWritesValueEqualToDefault(): void {
        $n = EditRS::updateFields($this->db, new ThingRS(), ['idThing' => 5, 'Span' => 1], ['Status' => '']);

        $this->assertSame(1, $n);
        $this->assertSame('', $this->db->tables['thing'][0]['Status']);
        $this->assertSame(['Status' => 'a'], $this->props(0)['old']);

        $this->assertSame(0, EditRS::updateFields($this->db, new ThingRS(), ['idThing' => 99, 'Span' => 1], ['Status' => 'x']), 'no such record');
    }

    public function testReplaceValueChangesEveryMatchingRow(): void {
        $this->db->tables['thing'][] = ['idThing' => '6', 'Span' => '0', 'Status' => 'a', 'Notes' => '', 'Password' => '', 'Last_Updated' => null];
        $this->db->tables['thing'][] = ['idThing' => '7', 'Span' => '0', 'Status' => 'b', 'Notes' => '', 'Password' => '', 'Last_Updated' => null];

        $this->assertSame(2, EditRS::replaceValue($this->db, new ThingRS(), 'Status', 'a', 'c'));
        $this->assertSame(['c', 'c', 'b'], array_column($this->db->tables['thing'], 'Status'));
        $this->assertCount(2, $this->db->log);
        $this->assertSame(0, EditRS::replaceValue($this->db, new ThingRS(), 'Status', 'b', 'b'));
    }

    public function testUpdateWhereAndDeleteWhereGoRowByRow(): void {
        $this->db->tables['thing'][] = ['idThing' => '6', 'Span' => '0', 'Status' => 'b', 'Notes' => '', 'Password' => '', 'Last_Updated' => null];
        $this->db->tables['thing'][] = ['idThing' => '7', 'Span' => '0', 'Status' => 'c', 'Notes' => '', 'Password' => '', 'Last_Updated' => null];

        $this->assertSame(2, EditRS::updateWhere($this->db, new ThingRS(), "Status = :a or Status = :b", [':a' => 'a', ':b' => 'b'], ['Notes' => 'x']));
        $this->assertSame(['x', 'x', ''], array_column($this->db->tables['thing'], 'Notes'));

        $this->assertSame(2, EditRS::deleteWhere($this->db, new ThingRS(), "Notes = :n", [':n' => 'x']));
        $this->assertSame(['7'], array_column($this->db->tables['thing'], 'idThing'));
        $this->assertSame(['updated', 'updated', 'deleted', 'deleted'], array_column($this->db->log, 'event'));
    }

    public function testUpsertInsertsThenUpdates(): void {
        // no row yet: insert with key + values
        $this->assertSame(1, EditRS::upsert($this->db, new ThingRS(), ['idThing' => 9, 'Span' => 0], ['Status' => 'n']));
        $this->assertSame('n', end($this->db->tables['thing'])['Status']);

        // existing row: update it
        $this->assertSame(1, EditRS::upsert($this->db, new ThingRS(), ['idThing' => 9, 'Span' => 0], ['Status' => 'm']));
        $this->assertCount(2, $this->db->tables['thing']);
        $this->assertSame('m', end($this->db->tables['thing'])['Status']);

        // unchanged: nothing written
        $this->assertSame(0, EditRS::upsert($this->db, new ThingRS(), ['idThing' => 9, 'Span' => 0], ['Status' => 'm']));
    }

    public function testUpsertRefusesAmbiguousKey(): void {
        $this->db->tables['thing'][] = ['idThing' => '5', 'Span' => '2', 'Status' => 'a', 'Notes' => '', 'Password' => '', 'Last_Updated' => null];

        $this->expectException(\RuntimeException::class);
        EditRS::upsert($this->db, new ThingRS(), ['idThing' => 5], ['Status' => 'z']);
    }

    public function testIgnoredColumnOnlyWritesNothing(): void {
        $this->updateThing(['Last_Updated' => '2026-01-01 09:00:00']);
        $this->assertCount(0, $this->db->log);
    }

    public function testRepeatSaveInSameRequestIsMerged(): void {
        $this->updateThing(['Status' => 'b']);
        $this->request();
        $this->updateThing(['Status' => 'c']);
        $this->db->tick(5);
        $this->updateThing(['Status' => 'd', 'Notes' => 'hi']);

        $this->assertCount(2, $this->db->log);
        $p = $this->props(1);
        $this->assertSame(['Status' => 'b', 'Notes' => ''], $p['old']);
        $this->assertSame(['Status' => 'd', 'Notes' => 'hi'], $p['attributes']);
    }

    public function testCheckpointEvery20AndPointInTime(): void {
        $times = [];
        $times[0] = $this->db->now;
        for ($i = 1; $i <= 25; $i++) {
            $this->request();
            $this->updateThing(['Status' => "s$i"]);
            $times[$i] = $this->db->now;
        }

        $this->assertCount(25, $this->db->log);
        $checkpoints = array_keys(array_filter($this->db->log, fn($r) => $r['is_checkpoint']));
        $this->assertSame([0, 20], $checkpoints, 'checkpoints at the 1st and 21st update');

        $this->assertSame('a', $this->asOf('2025-12-31 00:00:00')['Status'], 'before auditing started');
        foreach ($times as $i => $t) {
            $this->assertSame($i == 0 ? 'a' : "s$i", $this->asOf($t)['Status'], "state after update $i");
        }
    }

    public function testDeleteThenAsOf(): void {
        $this->updateThing(['Status' => 'b']);
        $t1 = $this->db->now;
        $this->request();

        $rs = new ThingRS();
        $rs->idThing->setStoredVal(5);
        $rs->Span->setStoredVal(1);
        EditRS::delete($this->db, $rs, [$rs->idThing, $rs->Span]);

        $last = end($this->db->log);
        $this->assertSame('deleted', $last['event']);
        $this->assertSame('b', json_decode($last['properties'], true)['attributes']['Status']);
        $this->assertSame('b', $this->asOf($t1)['Status']);
        $this->assertNull($this->asOf($this->db->now));
    }

    public function testDisabledTableAndKillSwitch(): void {
        $rs = new QuietWidgetRS();
        $rs->Title->setNewVal('Quiet');
        EditRS::insert($this->db, $rs);

        EditRS::$auditEnabled = false;
        $this->updateThing(['Status' => 'z']);
        EditRS::$auditEnabled = true;

        $this->assertCount(0, $this->db->log);
    }

    public function testPurgeKeepsStateFromCutoff(): void {
        $times = [];
        for ($i = 1; $i <= 6; $i++) {
            $this->request(86400);
            $this->updateThing(['Status' => "s$i"]);
            $times[$i] = $this->db->now;
        }
        $expected = [];
        foreach ($times as $i => $t) {
            $expected[$i] = $this->asOf($t)['Status'];
        }

        $cutoff = date('Y-m-d H:i:s', strtotime($times[3]) + 60);
        $this->assertSame(1, EditRS::purgeActivityBefore($this->db, $cutoff));

        $this->assertCount(4, $this->db->log, 'updates 4-6 plus one rollup');
        $this->assertSame('s3', $this->asOf($cutoff)['Status']);
        for ($i = 4; $i <= 6; $i++) {
            $this->assertSame($expected[$i], $this->asOf($times[$i])['Status'], "state after update $i survives the purge");
        }

        // the next run does not churn the rollup it just wrote
        $this->assertSame(0, EditRS::purgeActivityBefore($this->db, date('Y-m-d H:i:s', strtotime($cutoff) + 60)));
    }
}
