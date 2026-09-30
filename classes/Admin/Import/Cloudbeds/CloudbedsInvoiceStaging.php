<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Staging table for the $0 "additional charge" invoice CSV import (see CloudbedsInvoiceCsv and CloudbedsInvoiceImporter).
 * One row per CSV row, keyed by a hash of its content so re-uploading the same file does not create duplicate invoices -
 * mirrors CloudbedsStaging's own status/claim/retry pattern, just for a single kind of row instead of three.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsInvoiceStaging {

    public const TBL_NAME = 'import_cloudbeds_invoice';

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
            `rowHash` CHAR(40) NOT NULL,
            `reservationId` VARCHAR(45) NOT NULL,
            `item` VARCHAR(255) NOT NULL,
            `invoiceDate` VARCHAR(30) NOT NULL,
            `notes` VARCHAR(1000) NOT NULL DEFAULT '',
            `status` ENUM('pending', 'processing', 'done', 'error', 'skipped') NOT NULL DEFAULT 'pending',
            `workerId` VARCHAR(32) NULL,
            `hhkId` INT NULL,
            `hhkData` TEXT NULL,
            `message` TEXT NULL,
            `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_row` (`rowHash`),
            KEY `idx_status` (`status`, `id`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4");
    }

    /**
     * Discard all staged rows (does not touch anything already imported into HHK)
     */
    public function reset(): void {
        $this->dbh->exec("DROP TABLE IF EXISTS `" . self::TBL_NAME . "`");
        $this->ensureTables();
    }

    /**
     * Insert a staged row, or do nothing if a row with the same hash was already staged by a prior upload (whatever
     * its status now - reprocessing a done/skipped row is not the point, retryFailed() is for errors).
     *
     * @return bool true if a new row was inserted
     */
    public function upsert(string $rowHash, string $reservationId, string $item, string $invoiceDate, string $notes): bool {
        $stmt = $this->dbh->prepare("insert ignore into `" . self::TBL_NAME . "` (`rowHash`, `reservationId`, `item`, `invoiceDate`, `notes`) values (:rowHash, :reservationId, :item, :invoiceDate, :notes)");
        $stmt->execute([':rowHash' => $rowHash, ':reservationId' => $reservationId, ':item' => $item, ':invoiceDate' => $invoiceDate, ':notes' => $notes]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Claim the next batch of pending rows for a worker, same pattern as CloudbedsStaging::claimBatch()
     *
     * @return array[] claimed rows
     */
    public function claimBatch(string $workerId, int $limit): array {
        $stmt = $this->dbh->prepare("update `" . self::TBL_NAME . "` set `status` = 'processing', `workerId` = :worker where `status` = 'pending' order by `id` limit " . (int) $limit);
        $stmt->execute([':worker' => $workerId]);

        $stmt = $this->dbh->prepare("select * from `" . self::TBL_NAME . "` where `status` = 'processing' and `workerId` = :worker order by `id`");
        $stmt->execute([':worker' => $workerId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markDone(int $rowId, ?int $hhkId, array $hhkData = [], string $message = ''): void {
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
            ':message' => $message !== '' ? mb_substr($message, 0, 1000) : null, ':id' => $rowId,
        ]);
    }

    /**
     * Put failed rows (and rows left in 'processing', e.g. the request timed out) back to pending so they are retried
     */
    public function retryFailed(): int {
        return (int) $this->dbh->exec("update `" . self::TBL_NAME . "` set `status` = 'pending', `workerId` = null, `message` = null where `status` in ('error', 'processing')");
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
        return $this->dbh->query("select `reservationId`, `item`, `message` from `" . self::TBL_NAME . "` where `status` = 'error' order by `Timestamp` desc limit " . (int) $limit)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Item names on rows not yet imported, with how many such rows use each - used to find which items need creating
     * before import (see CloudbedsInvoiceCsv::unmatchedItems())
     *
     * @return array<string, int>
     */
    public function pendingItemNames(): array {
        $rows = $this->dbh->query("select `item`, count(*) as `n` from `" . self::TBL_NAME . "` where `status` = 'pending' group by `item`")->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $out[$row['item']] = (int) $row['n'];
        }

        return $out;
    }
}
