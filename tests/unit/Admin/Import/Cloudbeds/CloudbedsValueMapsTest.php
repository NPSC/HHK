<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsConfig;
use HHK\Admin\Import\Cloudbeds\CloudbedsValueMaps;
use HHK\SysConst\PayType;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudbedsValueMaps::class)]
class CloudbedsValueMapsTest extends TestCase
{
    public function testStatusChoicesImportAsReservationAndVisitStatuses(): void
    {
        $this->assertSame(['reservation' => ReservationStatus::Checkedout, 'visit' => VisitStatus::CheckedOut], CloudbedsValueMaps::statusPair(ReservationStatus::Checkedout));
        $this->assertSame(['reservation' => ReservationStatus::Staying, 'visit' => VisitStatus::CheckedIn], CloudbedsValueMaps::statusPair(ReservationStatus::Staying));
        $this->assertSame(['reservation' => ReservationStatus::Canceled, 'visit' => null], CloudbedsValueMaps::statusPair(ReservationStatus::Canceled));
        $this->assertSame(['reservation' => null, 'visit' => null], CloudbedsValueMaps::statusPair(CloudbedsValueMaps::SKIP));
    }

    public function testStatusChoiceIsTheReverseOfStatusPair(): void
    {
        foreach (array_keys(CloudbedsValueMaps::STATUS_CHOICES) as $choice) {
            $this->assertSame($choice, CloudbedsValueMaps::statusChoice(CloudbedsValueMaps::statusPair($choice)));
        }
    }

    public function testEveryDefaultStatusIsOneOfTheChoices(): void
    {
        foreach (CloudbedsConfig::DEFAULT_STATUS_MAP as $status => $pair) {
            $this->assertContains($status, CloudbedsValueMaps::CLOUDBEDS_STATUSES);
            $this->assertArrayHasKey(CloudbedsValueMaps::statusChoice($pair), CloudbedsValueMaps::STATUS_CHOICES);
            $this->assertSame($pair, CloudbedsValueMaps::statusPair(CloudbedsValueMaps::statusChoice($pair)), $status);
        }
        $this->assertEqualsCanonicalizing(CloudbedsValueMaps::CLOUDBEDS_STATUSES, array_keys(CloudbedsConfig::DEFAULT_STATUS_MAP));
    }

    public function testChargeItemChoicesAreItemsThatMakeAnOrdinaryLine(): void
    {
        $this->assertSame([1, 2, 9, 6, 'skip'], array_keys(CloudbedsValueMaps::CHARGE_ITEM_CHOICES));
        $this->assertContains('roomRevenue_manual', CloudbedsValueMaps::CLOUDBEDS_CHARGE_TYPES);
        $this->assertNotContains('payment', CloudbedsValueMaps::CLOUDBEDS_CHARGE_TYPES, 'payments are mapped by payment method');

        $this->assertTrue(CloudbedsValueMaps::isValidValue('charge_item', '9'));
        $this->assertTrue(CloudbedsValueMaps::isValidValue('charge_item', 'skip'));
        $this->assertFalse(CloudbedsValueMaps::isValidValue('charge_item', '3'), 'key deposits are hold lines, not charge lines');
        $this->assertSame('roomrevenue_manual', CloudbedsValueMaps::normalize('charge_item', 'roomRevenue_manual'));
    }

    public function testNormalize(): void
    {
        $this->assertSame('bank_transfer', CloudbedsValueMaps::normalize('payment_method', ' Bank-Transfer '));
        $this->assertSame('checked_out', CloudbedsValueMaps::normalize('reservation_status', 'Checked Out'));
        $this->assertSame('suite a - 2', CloudbedsValueMaps::normalize('room', ' Suite A - 2 '), 'room names keep their punctuation');
    }

    public function testValidValues(): void
    {
        $this->assertTrue(CloudbedsValueMaps::isValidValue('payment_method', PayType::Cash));
        $this->assertFalse(CloudbedsValueMaps::isValidValue('payment_method', PayType::Charge), 'cards are external payments');
        $this->assertTrue(CloudbedsValueMaps::isValidValue('reservation_status', 'skip'));
        $this->assertFalse(CloudbedsValueMaps::isValidValue('reservation_status', 'zz'));
        $this->assertTrue(CloudbedsValueMaps::isValidValue('room', '12'));
        $this->assertFalse(CloudbedsValueMaps::isValidValue('room', 'Suite'));
        $this->assertFalse(CloudbedsValueMaps::isValidValue('room', ''));
    }

    public function testFromFormParsesRowsAndDropsUnmappedOnes(): void
    {
        $rows = CloudbedsValueMaps::fromForm([
            'valmap_posted' => ['room' => '1', 'payment_method' => '1', 'other' => '1'],
            'valmap_cb' => ['room' => [0 => 'Suite A', 1 => 'Suite B', 5 => ' New '], 'payment_method' => []],
            'valmap_hhk' => ['room' => [0 => '101', 1 => '', 5 => '102']],
        ]);

        $this->assertSame([['cb' => 'Suite A', 'hhk' => '101'], ['cb' => 'New', 'hhk' => '102']], $rows['room']);
        $this->assertSame([], $rows['payment_method'], 'posted with no rows clears the mapping');
        $this->assertArrayNotHasKey('reservation_status', $rows);
        $this->assertArrayNotHasKey('other', $rows);
    }

    public function testValidMapsPass(): void
    {
        CloudbedsValueMaps::validate([
            'room' => [['cb' => 'Suite A', 'hhk' => '101']],
            'payment_method' => [['cb' => 'Venmo', 'hhk' => 'tf'], ['cb' => 'Credit', 'hhk' => 'ex'], ['cb' => 'Debit', 'hhk' => 'ex']],
            'reservation_status' => [['cb' => 'inquiry', 'hhk' => 'skip']],
            'charge_item' => [['cb' => 'product', 'hhk' => '2'], ['cb' => 'channel_commission', 'hhk' => 'skip']],
        ]);
        CloudbedsValueMaps::validate([]);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{0: array, 1: string}>
     */
    public static function invalidMaps(): array
    {
        return [
            'unknown type' => [['stay' => []], "Unknown mapping type 'stay'"],
            'bad room' => [['room' => [['cb' => 'A', 'hhk' => 'Suite']]], "'Suite' is not a valid HHK value for the Cloudbeds room 'A'"],
            'bad pay type' => [['payment_method' => [['cb' => 'Credit', 'hhk' => 'cc']]], 'is not a valid HHK value'],
            'bad charge item' => [['charge_item' => [['cb' => 'tax', 'hhk' => '3']]], 'is not a valid HHK value'],
            'bad status' => [['reservation_status' => [['cb' => 'canceled', 'hhk' => 'zz']]], 'is not a valid HHK value'],
            'duplicate' => [['payment_method' => [['cb' => 'Bank Transfer', 'hhk' => 'tf'], ['cb' => 'bank_transfer', 'hhk' => 'ex']]], 'mapped more than once'],
            'blank' => [['room' => [['cb' => ' ', 'hhk' => '1']]], 'is blank'],
            'too long' => [['room' => [['cb' => str_repeat('x', 101), 'hhk' => '1']]], 'is too long'],
        ];
    }

    #[DataProvider('invalidMaps')]
    public function testInvalidMapsAreRejected(array $rows, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        CloudbedsValueMaps::validate($rows);
    }
}
