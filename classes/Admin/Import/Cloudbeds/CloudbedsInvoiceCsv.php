<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\Common;

/**
 * Parses and stages the $0 "additional charge" invoice CSV. Columns (header row required, order irrelevant):
  - Reservation Number (required): the Cloudbeds reservation id the invoice's visit is looked up by, see CloudbedsInvoiceImporter
  - Item and Service Name (required): matched to an existing HHK item by name (case insensitive); see unmatchedItems()/createMissingItems()
  - Service Date (required): the invoice date, any format PHP's DateTime can parse
  - Transaction Notes (optional): appended to the invoice's Notes
  - Quantity (optional): how many of the item the invoice line is for - a positive number, 1 when the column is absent or blank
 *
 * One row is one invoice with one $0 line - there is no grouping of rows into a multi-line invoice.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsInvoiceCsv {

    /** gen lookup table of the additional charges an invoice line can be for (see CloudbedsInvoiceImporter) */
    public const ADDNL_CHARGE_TABLE = 'Addnl_Charge';

    public const REQUIRED_COLUMNS =['Reservation Number', 'Item and Service Name', 'Service Date'];

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

            $reservationId = trim((string) ($r['Reservation Number'] ?? ''));
            $item = trim((string) ($r['Item and Service Name'] ?? ''));
            $dateRaw = trim((string) ($r['Service Date'] ?? ''));
            $notes = trim((string) ($r['Transaction Notes'] ?? ''));
            $quantityRaw = trim((string) ($r['Quantity'] ?? ''));

            if ($reservationId === '' || $item === '' || $dateRaw === '') {
                $errors[] = "Line $lineNum: Reservation Number, Item and Service Name, and Service Date are required";
                continue;
            }

            $date = $this->parseDate($dateRaw);
            if ($date === null) {
                $errors[] = "Line $lineNum: could not parse date '$dateRaw'";
                continue;
            }

            $quantity = $quantityRaw === '' ? '1' : $quantityRaw;
            if (!is_numeric($quantity) || (float) $quantity <= 0) {
                $errors[] = "Line $lineNum: Quantity must be a positive number, got '$quantityRaw'";
                continue;
            }

            // the row's position in the file is part of the hash so two genuinely identical rows in one file both
            // stage, while re-uploading the same file is a no-op
            $rowHash = sha1($reservationId . "\x1f" . $item . "\x1f" . $date . "\x1f" . $notes . "\x1f" . $quantity . "\x1f" . $i);

            if ($this->staging->upsert($rowHash, $reservationId, $item, $date, $notes, (float) $quantity)) {
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
     * Additional-charge names on not-yet-imported rows that don't match an existing Addnl_Charge gen lookup value (the
     * HHK additional charges shown on visit invoices, see GuestEdit's charge dialog), case insensitive, with how many
     * such rows use each.
     *
     * @return array<string, int>
     */
    public function unmatchedItems(): array {
        $known = [];
        foreach (Common::readGenLookupsPDO($this->dbh, self::ADDNL_CHARGE_TABLE) as $charge) {
            $known[$this->normalize($charge['Description'])] = true;
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
     * Create an Addnl_Charge gen lookup value for every currently-unmatched name (see unmatchedItems()), the same way
     * ResourceBuilder adds one: type 'ca' (an additional charge), with a new 'g' code.
     *
     * @return int number created
     */
    public function createMissingItems(): int {
        $names = array_keys($this->unmatchedItems());
        $created = 0;

        $this->dbh->beginTransaction();
        try {
            // Substitute is the charge's default amount (the charge dialog reads it as a number) - a blank one shows as NaN
            $stmt = $this->dbh->prepare("insert into `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) values (:table, :code, :description, '0', 'ca', 0)");
            foreach ($names as $name) {
                $stmt->execute([
                    ':table' => self::ADDNL_CHARGE_TABLE,
                    ':code' => 'g' . Common::incCounter($this->dbh, 'codes'),
                    ':description' => mb_substr($name, 0, 255),
                ]);
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
