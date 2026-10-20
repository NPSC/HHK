<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsInvoiceCsv;
use HHK\Admin\Import\Cloudbeds\CloudbedsInvoiceStaging;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The staging table, in memory
 */
class InMemoryInvoiceStaging extends CloudbedsInvoiceStaging {

    /** @var array<int, array> */
    public array $rows = [];

    public function ensureTables(): void {}

    public function upsert(string $rowHash, string $reservationId, string $item, string $invoiceDate, string $notes, float $quantity = 1): bool {
        foreach ($this->rows as $row) {
            if ($row['rowHash'] === $rowHash) {
                return false;
            }
        }

        $id = count($this->rows) + 1;
        $this->rows[$id] = [
            'id' => $id, 'rowHash' => $rowHash, 'reservationId' => $reservationId, 'item' => $item,
            'invoiceDate' => $invoiceDate, 'notes' => $notes, 'quantity' => $quantity, 'status' => self::PENDING,
            'hhkId' => null, 'hhkData' => null, 'message' => null,
        ];
        return true;
    }

    public function pendingItemNames(): array {
        $out = [];
        foreach ($this->rows as $row) {
            if ($row['status'] === self::PENDING) {
                $out[$row['item']] = ($out[$row['item']] ?? 0) + 1;
            }
        }
        return $out;
    }
}

/**
 * CloudbedsInvoiceCsv::unmatchedItems()/createMissingItems() need a live database (Item::loadItems() and the item/item_type_map
 * inserts), so only upload()'s parsing/validation/staging is unit tested here - same limitation as CloudbedsImportTest.
 */
#[CoversClass(CloudbedsInvoiceCsv::class)]
class CloudbedsInvoiceCsvTest extends TestCase
{
    protected string $csvPath;

    protected function setUp(): void
    {
        $this->csvPath = tempnam(sys_get_temp_dir(), 'cbinv');
    }

    protected function tearDown(): void
    {
        @unlink($this->csvPath);
    }

    protected function writeCsv(string $content): void
    {
        file_put_contents($this->csvPath, $content);
    }

    /**
     * @return array{0: CloudbedsInvoiceCsv, 1: InMemoryInvoiceStaging}
     */
    protected function csv(): array
    {
        $staging = new InMemoryInvoiceStaging($this->createStub(\PDO::class));
        return [new CloudbedsInvoiceCsv($this->createStub(\PDO::class), $staging), $staging];
    }

    public function testValidRowsAreStaged(): void
    {
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date,Transaction Notes\nR1,Pet Fee,2024-01-15,late checkout\nR2,Damage,2024-02-01,\n");
        [$csv, $staging] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(2, $result['staged']);
        $this->assertSame(0, $result['duplicates']);
        $this->assertSame([], $result['errors']);

        $rows = array_values($staging->rows);
        $this->assertSame('R1', $rows[0]['reservationId']);
        $this->assertSame('Pet Fee', $rows[0]['item']);
        $this->assertSame('2024-01-15 00:00:00', $rows[0]['invoiceDate']);
        $this->assertSame('late checkout', $rows[0]['notes']);
        $this->assertSame('', $rows[1]['notes'], 'Notes is optional');
    }

    public function testQuantityIsStagedAndDefaultsToOne(): void
    {
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date,Quantity\nR1,Pet Fee,2024-01-15,3\nR2,Damage,2024-02-01,\nR3,Damage,2024-02-02,1.5\n");
        [$csv, $staging] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(3, $result['staged']);
        $this->assertSame([], $result['errors']);
        $rows = array_values($staging->rows);
        $this->assertSame(3.0, $rows[0]['quantity']);
        $this->assertSame(1.0, $rows[1]['quantity'], 'a blank Quantity cell means 1');
        $this->assertSame(1.5, $rows[2]['quantity']);
    }

    public function testQuantityColumnIsOptional(): void
    {
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date\nR1,Pet Fee,2024-01-15\n");
        [$csv, $staging] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(1, $result['staged']);
        $this->assertSame(1.0, array_values($staging->rows)[0]['quantity']);
    }

    public function testInvalidQuantityIsReportedAndSkipped(): void
    {
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date,Quantity\nR1,Pet Fee,2024-01-15,0\nR2,Pet Fee,2024-01-15,-2\nR3,Pet Fee,2024-01-15,two\nR4,Pet Fee,2024-01-15,4\n");
        [$csv, $staging] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(1, $result['staged'], 'only the valid row stages');
        $this->assertCount(3, $result['errors']);
        $this->assertStringContainsString('Line 2: Quantity must be a positive number', $result['errors'][0]);
        $this->assertStringContainsString("got 'two'", $result['errors'][2]);
    }

    public function testColumnOrderDoesNotMatter(): void
    {
        $this->writeCsv("Item and Service Name,Service Date,Reservation Number\nPet Fee,2024-01-15,R1\n");
        [$csv, $staging] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(1, $result['staged']);
        $row = current($staging->rows);
        $this->assertSame('R1', $row['reservationId']);
        $this->assertSame('Pet Fee', $row['item']);
    }

    public function testMissingRequiredColumnThrows(): void
    {
        $this->writeCsv("Item and Service Name,Service Date\nPet Fee,2024-01-15\n");
        [$csv] = $this->csv();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reservation Number');
        $csv->upload($this->csvPath);
    }

    public function testEmptyFileThrows(): void
    {
        $this->writeCsv('');
        [$csv] = $this->csv();

        $this->expectException(\RuntimeException::class);
        $csv->upload($this->csvPath);
    }

    public function testRowsMissingARequiredFieldAreReportedAndSkipped(): void
    {
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date\n,Pet Fee,2024-01-15\nR2,,2024-01-15\nR3,Pet Fee,\nR4,Pet Fee,2024-01-15\n");
        [$csv] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(1, $result['staged'], 'only R4 is complete');
        $this->assertCount(3, $result['errors']);
        $this->assertStringContainsString('Line 2', $result['errors'][0]);
        $this->assertStringContainsString('Line 3', $result['errors'][1]);
        $this->assertStringContainsString('Line 4', $result['errors'][2]);
    }

    public function testUnparseableDateIsReportedAndSkipped(): void
    {
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date\nR1,Pet Fee,not-a-date\n");
        [$csv] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(0, $result['staged']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString("could not parse date 'not-a-date'", $result['errors'][0]);
    }

    public function testReuploadingTheSameFileStagesNothingTwice(): void
    {
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date\nR1,Pet Fee,2024-01-15\nR2,Damage,2024-02-01\n");
        [$csv, $staging] = $this->csv();

        $first = $csv->upload($this->csvPath);
        $this->assertSame(2, $first['staged']);

        $second = $csv->upload($this->csvPath);
        $this->assertSame(0, $second['staged']);
        $this->assertSame(2, $second['duplicates']);
        $this->assertCount(2, $staging->rows, 'no duplicate rows were created');
    }

    public function testTwoIdenticalRowsInOneFileBothStage(): void
    {
        // same reservation/item/date/notes twice - e.g. two separate $0 charges of the same kind on the same day
        $this->writeCsv("Reservation Number,Item and Service Name,Service Date\nR1,Pet Fee,2024-01-15\nR1,Pet Fee,2024-01-15\n");
        [$csv, $staging] = $this->csv();

        $result = $csv->upload($this->csvPath);

        $this->assertSame(2, $result['staged']);
        $this->assertCount(2, $staging->rows);
    }
}
