<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\Purchase\Item;
use HHK\SysConst\ItemType;

/**
 * Parses and stages the $0 "additional charge" invoice CSV. Columns (header row required, order irrelevant):
 *  - Reservation ID (required): the Cloudbeds reservation id the invoice's visit is looked up by, see CloudbedsInvoiceImporter
 *  - Item (required): matched to an existing HHK item by name (case insensitive); see unmatchedItems()/createMissingItems()
 *  - Date (required): the invoice date, any format PHP's DateTime can parse
 *  - Notes (optional): appended to the invoice's Notes
 *
 * One row is one invoice with one $0 line - there is no grouping of rows into a multi-line invoice.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsInvoiceCsv {

    public const REQUIRED_COLUMNS = ['Reservation ID', 'Item', 'Date'];

    public function __construct(protected \PDO $dbh, protected CloudbedsInvoiceStaging $staging) {
        $this->staging->ensureTables();
    }

    /**
     * Parse an uploaded CSV file and stage its rows. Rows that fail validation are reported but do not stop the rest
     * of the file from being staged.
     *
     * @param string $tmpPath the uploaded file's tmp_name
     * @return array{staged: int, duplicates: int, errors: string[]}
     */
    public function upload(string $tmpPath): array {
        $lines = @file($tmpPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || count($lines) === 0) {
            throw new \RuntimeException('The file could not be read or is empty');
        }

        $rows = array_map(fn($line) => str_getcsv($line, ',', '"', '\\'), $lines);
        $header = array_map(fn($h) => trim((string) $h), array_shift($rows));

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if (count($missing) > 0) {
            throw new \RuntimeException('Missing required column(s): ' . implode(', ', $missing));
        }

        $staged = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $lineNum = $i + 2; // 1 is the header
            $r = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));

            $reservationId = trim((string) ($r['Reservation ID'] ?? ''));
            $item = trim((string) ($r['Item'] ?? ''));
            $dateRaw = trim((string) ($r['Date'] ?? ''));
            $notes = trim((string) ($r['Notes'] ?? ''));

            if ($reservationId === '' || $item === '' || $dateRaw === '') {
                $errors[] = "Line $lineNum: Reservation ID, Item and Date are required";
                continue;
            }

            $date = $this->parseDate($dateRaw);
            if ($date === null) {
                $errors[] = "Line $lineNum: could not parse date '$dateRaw'";
                continue;
            }

            // the row's position in the file is part of the hash so two genuinely identical rows in one file both
            // stage, while re-uploading the same file is a no-op
            $rowHash = sha1($reservationId . "\x1f" . $item . "\x1f" . $date . "\x1f" . $notes . "\x1f" . $i);

            if ($this->staging->upsert($rowHash, $reservationId, $item, $date, $notes)) {
                $staged++;
            } else {
                $duplicates++;
            }
        }

        return ['staged' => $staged, 'duplicates' => $duplicates, 'errors' => $errors];
    }

    protected function parseDate(string $value): ?string {
        try {
            return (new \DateTime($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Item names on not-yet-imported rows that don't match an existing, non-deleted HHK item by name (case
     * insensitive), with how many such rows use each.
     *
     * @return array<string, int>
     */
    public function unmatchedItems(): array {
        $known = [];
        foreach (Item::loadItems($this->dbh) as $item) {
            $known[$this->normalize($item['Description'])] = true;
        }

        $unmatched = [];
        foreach ($this->staging->pendingItemNames() as $name => $count) {
            if (!isset($known[$this->normalize($name)])) {
                $unmatched[$name] = $count;
            }
        }

        return $unmatched;
    }

    /**
     * Create a plain HHK item for every currently-unmatched item name (see unmatchedItems()), the same way a new tax
     * item is created from the room/item builder (house/ResourceBuilder.php) - an `item` row plus an `item_type_map`
     * row, just typed as a normal item instead of a tax.
     *
     * @return int number created
     */
    public function createMissingItems(): int {
        $names = array_keys($this->unmatchedItems());
        $created = 0;

        $this->dbh->beginTransaction();
        try {
            foreach ($names as $name) {
                $stmt = $this->dbh->prepare("insert into `item` (`Description`, `Gl_Code`, `Percentage`, `Timeout_Days`, `First_Order_Id`) values (:description, '', 0, '', 0)");
                $stmt->execute([':description' => mb_substr($name, 0, 1000)]);
                $idItem = (int) $this->dbh->lastInsertId();

                $this->dbh->exec("insert into `item_type_map` values ($idItem, " . ItemType::Items . ")");
                $created++;
            }
            $this->dbh->commit();
        } catch (\Throwable $e) {
            $this->dbh->rollBack();
            throw $e;
        }

        return $created;
    }

    protected function normalize(string $value): string {
        return strtolower(trim($value));
    }
}
