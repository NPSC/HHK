<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsConfig;
use HHK\Admin\Import\Cloudbeds\CloudbedsFolioImporter;
use HHK\SysConst\ItemId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudbedsFolioImporter::class)]
class CloudbedsFolioImporterTest extends TestCase
{
    protected CloudbedsFolioImporter $importer;

    protected function setUp(): void
    {
        $config = new CloudbedsConfig(['apiKey' => 'k', 'organizationId' => '1', 'propertyIds' => [1]]);

        // expose the protected line building/partitioning/notes, these don't touch the database
        $this->importer = new class($this->createStub(\PDO::class), $config) extends CloudbedsFolioImporter {
            public function split(array $t, array &$w): array { return $this->partition($t, $w); }
            public function lines(array $c, array &$w = []): array { return $this->buildLines($c, $w); }
            public function notes(string $folioId, string $reservationId, array $c): string { return $this->invoiceNotes($folioId, $reservationId, $c); }
        };
    }

    protected function rate(string $id, string $date, float $amount, string $type = 'rate'): array
    {
        return ['id' => $id, 'transactionType' => $type, 'amount' => $amount, 'serviceDate' => $date, 'description' => 'Room', 'transactionDatetime' => $date . 'T12:00:00+00:00'];
    }

    public function testConsecutiveNightsAtTheSameRateBecomeOneLine(): void
    {
        $w = [];
        [$charges] = $this->importer->split([
            $this->rate('1', '2024-03-01', 100), $this->rate('2', '2024-03-02', 100), $this->rate('3', '2024-03-03', 100),
        ], $w);

        $lines = $this->importer->lines($charges);

        $this->assertCount(1, $lines);
        $this->assertSame(ItemId::Lodging, $lines[0]['itemId']);
        $this->assertSame(3, $lines[0]['quantity']);
        $this->assertEquals(100, $lines[0]['price']);
        $this->assertEquals(300, $lines[0]['amount']);
        $this->assertSame('2024-03-01', $lines[0]['start']);
        $this->assertSame('2024-03-03', $lines[0]['end']);
    }

    public function testRateChangesAndGapsStartNewLines(): void
    {
        $w = [];
        [$charges] = $this->importer->split([
            $this->rate('4', '2024-03-05', 90),   // out of order on purpose
            $this->rate('1', '2024-03-01', 100),
            $this->rate('2', '2024-03-02', 100),
            $this->rate('3', '2024-03-03', 80),
            $this->rate('5', '2024-03-06', 90),
        ], $w);

        $lines = $this->importer->lines($charges);

        $this->assertSame([2, 1, 2], array_column($lines, 'quantity'));
        $this->assertEquals([200, 80, 180], array_column($lines, 'amount'));
        $this->assertSame(['2024-03-01', '2024-03-03', '2024-03-05'], array_column($lines, 'start'));
    }

    public function testOtherChargesMapToAdditionalChargeOrDiscount(): void
    {
        $w = [];
        [$charges] = $this->importer->split([
            ['id' => 't', 'transactionType' => 'tax', 'amount' => 12.5, 'description' => 'City tax'],
            ['id' => 'a', 'transactionType' => 'adjustment', 'amount' => -20, 'description' => 'Goodwill'],
            ['id' => 'p', 'transactionType' => 'product', 'amount' => 7, 'description' => ''],
        ], $w);

        $lines = $this->importer->lines($charges);

        $this->assertSame([ItemId::AddnlCharge, ItemId::Discount, ItemId::AddnlCharge], array_column($lines, 'itemId'));
        $this->assertEquals([12.5, -20, 7], array_column($lines, 'amount'));
        $this->assertSame('City tax', $lines[0]['description']);
        $this->assertSame('Product', $lines[2]['description'], 'falls back to the transaction type');
    }

    public function testMappedChargesBecomeTheChosenItem(): void
    {
        $importer = $this->importerWith(['chargeItemMap' => ['product' => '2', 'gratuity' => 'skip', 'rate' => '9']]);
        $w = [];
        [$charges] = $importer->split([
            ['id' => 'p', 'transactionType' => 'product', 'amount' => 7.5, 'description' => 'Snacks'],
            ['id' => 'g1', 'transactionType' => 'gratuity', 'amount' => 3],
            ['id' => 'g2', 'transactionType' => 'gratuity', 'amount' => 4.25],
            $this->rate('r', '2024-03-01', 100),
        ], $w);

        $warnings = [];
        $lines = $importer->lines($charges, $warnings);

        $this->assertSame([ItemId::VisitFee, ItemId::AddnlCharge], array_column($lines, 'itemId'));
        $this->assertSame('', $lines[1]['start'], 'rates mapped to another item are not merged into lodging lines');
        $this->assertEquals([7.5, 100], array_column($lines, 'amount'));
        $this->assertSame(['Not imported (charge item mapping): gratuity charges of 7.25'], $warnings, 'skipped charges are reported once per type');
    }

    protected function importerWith(array $configExtra): object
    {
        $config = new CloudbedsConfig($configExtra + ['apiKey' => 'k', 'organizationId' => '1', 'propertyIds' => [1]]);

        return new class($this->createStub(\PDO::class), $config) extends CloudbedsFolioImporter {
            public function split(array $t, array &$w): array { return $this->partition($t, $w); }
            public function lines(array $c, array &$w = []): array { return $this->buildLines($c, $w); }
            public function notes(string $folioId, string $reservationId, array $c): string { return $this->invoiceNotes($folioId, $reservationId, $c); }
        };
    }

    public function testPaymentsAreSeparatedFromCharges(): void
    {
        $w = [];
        [$charges, $payments] = $this->importer->split([
            $this->rate('1', '2024-03-01', 100),
            ['id' => 'pay1', 'transactionType' => 'payment', 'amount' => -60, 'transactionDatetime' => '2024-03-01T10:00:00+00:00', 'notes' => 'deposit',
                'paymentDetails' => ['transactionType' => 'Purchase', 'paymentMethod' => 'credit', 'creditCardType' => 'visa', 'cardTrailingDigits' => '4242', 'amountPaid' => 60]],
            ['id' => 'pay2', 'internalCodeGroup' => 'PAYMENT', 'amount' => 40, 'paymentDetails' => ['paymentMethod' => 'cash']],
        ], $w);

        $this->assertCount(1, $charges);
        $this->assertCount(2, $payments);
        $this->assertSame(60.0, $payments[0]['amount']);
        $this->assertSame('credit', $payments[0]['method']);
        $this->assertSame('visa 4242', $payments[0]['card']);
        $this->assertSame('2024-03-01 10:00:00', $payments[0]['date']);
        $this->assertSame(40.0, $payments[1]['amount'], 'falls back to the amount, without a sign');
        $this->assertSame([], $w);
    }

    public function testChargeNotesAreKeptOnTheCharge(): void
    {
        $w = [];
        [$charges] = $this->importer->split([
            ['id' => 't', 'transactionType' => 'tax', 'amount' => 12.5, 'description' => 'City tax', 'notes' => 'Guest requested itemized tax'],
        ], $w);

        $this->assertSame('Guest requested itemized tax', $charges[0]['notes']);
    }

    public function testInvoiceNotesCollectDistinctChargeNotes(): void
    {
        $notes = $this->importer->notes('F1', 'R1', [
            ['notes' => 'Late checkout approved'],
            ['notes' => 'Late checkout approved'],
            ['notes' => ''],
            ['notes' => 'Comped parking'],
            [],
        ]);

        $this->assertSame('Imported from Cloudbeds folio F1 (reservation R1). Folio notes: Late checkout approved; Comped parking', $notes);
    }

    public function testInvoiceNotesWithoutChargeNotesIsJustTheImportLine(): void
    {
        $this->assertSame('Imported from Cloudbeds folio F1 (reservation R1)', $this->importer->notes('F1', 'R1', [['notes' => '']]));
    }

    public function testInvoiceNotesAreTruncatedToFitTheColumn(): void
    {
        $notes = $this->importer->notes('F1', 'R1', [['notes' => str_repeat('x', 500)]]);

        $this->assertSame(450, strlen($notes));
        $this->assertStringEndsWith('...', $notes);
        $this->assertStringStartsWith('Imported from Cloudbeds folio F1', $notes);
    }

    public function testVoidedRefundedAndAuthorizationTransactionsAreSkipped(): void
    {
        $warnings = [];
        [$charges, $payments] = $this->importer->split([
            ['id' => 'v', 'transactionType' => 'tax', 'amount' => 9, 'state' => 'VOIDED'],
            ['id' => 'x', 'transactionType' => 'tax', 'amount' => 9, 'state' => 'transferred'],
            ['id' => 'r', 'transactionType' => 'payment', 'amount' => 50, 'paymentDetails' => ['transactionType' => 'Refund', 'amountRefunded' => 50]],
            ['id' => 'a', 'transactionType' => 'payment', 'amount' => 50, 'paymentDetails' => ['transactionType' => 'Authorize', 'amountPaid' => 50]],
            ['id' => 'z', 'transactionType' => 'fee', 'amount' => 0],
        ], $warnings);

        $this->assertSame([], $charges);
        $this->assertSame([], $payments);
        $this->assertSame(['Skipped refund transaction r', 'Skipped authorize transaction a'], $warnings);
    }
}
