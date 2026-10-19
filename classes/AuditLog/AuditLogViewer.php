<?php
namespace HHK\AuditLog;

use HHK\DataTableServer\SSP;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector};

/**
 * AuditLogViewer.php
 *
 * Site Logs > Audit Log tab (admin/Configure.php): the filter controls, and the server side of its
 * DataTable (admin/ws_gen.php cmd=showAuditLog).  See HHK\Tables\Auditable for what's in the log.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class AuditLogViewer {

    const TABLE = 'audit_log';

    const EVENTS = [
        ['created', 'Created'],
        ['updated', 'Updated'],
        ['deleted', 'Deleted'],
        ['summary', 'Summary / other'],
    ];

    /**
     * Filter controls shown above the log table.
     *
     * @param \PDO $dbh
     * @return string
     */
    public static function filterMarkup(\PDO $dbh): string {

        // tables that have entries (uses the subject index)
        $tables = [];
        try {
            foreach ($dbh->query("select distinct `subject_type` from `" . self::TABLE . "` where `subject_type` is not null order by `subject_type`")->fetchAll(\PDO::FETCH_COLUMN) as $t) {
                $tables[] = [$t, $t];
            }
        } catch (\PDOException $e) {
            // table not created yet on this site
            return HTMLContainer::generateMarkup('p', 'The audit log table does not exist yet. Run the site update.', ['class' => 'ui-state-error p-2']);
        }

        $field = function (string $label, string $control): string {
            return HTMLContainer::generateMarkup('label', $label . ' ' . $control, ['class' => 'mr-3 mb-2']);
        };

        $controls =
            $field('Table', HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($tables, '', TRUE), ['id' => 'alTable', 'class' => 'hhk-alFilter']))
            . $field('Record Id', HTMLInput::generateMarkup('', ['id' => 'alSubjectId', 'type' => 'number', 'min' => '1', 'style' => 'width:7em;', 'class' => 'hhk-alFilter']))
            . $field('Record Key', HTMLInput::generateMarkup('', ['id' => 'alRecordKey', 'size' => '18', 'placeholder' => 'idVisit=5,Span=1', 'class' => 'hhk-alFilter']))
            . $field('User', HTMLInput::generateMarkup('', ['id' => 'alUser', 'size' => '12', 'class' => 'hhk-alFilter']))
            . $field('Event', HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(self::EVENTS, '', TRUE), ['id' => 'alEvent', 'class' => 'hhk-alFilter']))
            . $field('From', HTMLInput::generateMarkup('', ['id' => 'alFrom', 'type' => 'date', 'class' => 'hhk-alFilter']))
            . $field('To', HTMLInput::generateMarkup('', ['id' => 'alTo', 'type' => 'date', 'class' => 'hhk-alFilter']))
            . $field('Batch', HTMLInput::generateMarkup('', ['id' => 'alBatch', 'size' => '12', 'class' => 'hhk-alFilter']))
            . HTMLInput::generateMarkup('Clear Filters', ['type' => 'button', 'id' => 'alClear', 'class' => 'mb-2']);

        return HTMLContainer::generateMarkup('div', $controls, ['id' => 'alFilters', 'class' => 'd-flex flex-wrap align-items-center mb-2']);
    }

    /**
     * DataTables server-side response.
     *
     * @param \PDO $dbh
     * @param array $request the DataTables request ($_GET), which also carries the filter values
     * @return array
     */
    public static function dataTable(\PDO $dbh, array $request): array {

        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'created_at', 'dt' => 'created_at'],
            ['db' => 'event', 'dt' => 'event'],
            ['db' => 'description', 'dt' => 'description'],
            ['db' => 'subject_type', 'dt' => 'subject_type'],
            ['db' => 'subject_id', 'dt' => 'subject_id'],
            ['db' => 'record_key', 'dt' => 'record_key'],
            ['db' => 'causer_id', 'dt' => 'causer_id'],
            ['db' => 'batch_uuid', 'dt' => 'batch_uuid'],
            ['db' => 'properties', 'dt' => 'properties', 'formatter' => function ($d) {
                return (is_null($d) ? [] : (json_decode($d, true) ?? []));
            }],
        ];

        return SSP::complex($request, $dbh, self::TABLE, 'id', $columns, self::filterCondition($request), null);
    }

    /**
     * The filter values as one bound WHERE condition for SSP.
     *
     * @param array $request
     * @return array|null
     */
    public static function filterCondition(array $request): ?array {

        $conds = [];
        $binds = [];

        $val = function (string $key, int $maxLen = 255) use ($request): string {
            return isset($request[$key]) && is_scalar($request[$key]) ? substr(trim((string) $request[$key]), 0, $maxLen) : '';
        };

        if (($t = $val('alTable', 64)) !== '') {
            $conds[] = "`subject_type` = :alTable";
            $binds[':alTable'] = $t;
        }

        if (($id = intval($val('alSubjectId', 20), 10)) > 0) {
            $conds[] = "`subject_id` = :alSubjectId";
            $binds[':alSubjectId'] = $id;
        }

        if (($rk = $val('alRecordKey', 191)) !== '') {
            $conds[] = "`record_key` = :alRecordKey";
            $binds[':alRecordKey'] = $rk;
        }

        if (($u = $val('alUser', 100)) !== '') {
            // the username is kept in properties.user (causer_id is empty for the built-in admin)
            $conds[] = "JSON_UNQUOTE(JSON_EXTRACT(`properties`, '$.user')) = :alUser";
            $binds[':alUser'] = $u;
        }

        $ev = $val('alEvent', 20);
        if ($ev === 'summary') {
            $conds[] = "`event` is null";
        } else if (in_array($ev, ['created', 'updated', 'deleted'], TRUE)) {
            $conds[] = "`event` = :alEvent";
            $binds[':alEvent'] = $ev;
        }

        if (($from = self::dateVal($val('alFrom', 10))) !== '') {
            $conds[] = "`created_at` >= :alFrom";
            $binds[':alFrom'] = $from . ' 00:00:00';
        }

        if (($to = self::dateVal($val('alTo', 10))) !== '') {
            // through the end of that day
            $conds[] = "`created_at` < DATE_ADD(:alTo, INTERVAL 1 DAY)";
            $binds[':alTo'] = $to;
        }

        if (($b = $val('alBatch', 36)) !== '') {
            $conds[] = "`batch_uuid` = :alBatch";
            $binds[':alBatch'] = $b;
        }

        return (count($conds) > 0 ? ['condition' => implode(' AND ', $conds), 'bindings' => $binds] : null);
    }

    /**
     * A Y-m-d date, or '' if it isn't one.
     */
    private static function dateVal(string $v): string {
        $dt = \DateTime::createFromFormat('Y-m-d', $v);
        return ($dt && $dt->format('Y-m-d') === $v ? $v : '');
    }
}
