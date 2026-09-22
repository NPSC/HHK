<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsConfig;
use HHK\SysConst\ItemId;
use HHK\SysConst\PayType;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudbedsConfig::class)]
class CloudbedsConfigTest extends TestCase
{
    protected function config(array $extra = []): CloudbedsConfig
    {
        return new CloudbedsConfig($extra + ['apiKey' => 'cbat_x', 'organizationId' => '99', 'propertyIds' => [1, '2']]);
    }

    public function testRequiresCredentialsOrganizationAndProperties(): void
    {
        foreach ([
            ['organizationId' => '1', 'propertyIds' => [1]],
            ['apiKey' => 'k', 'propertyIds' => [1]],
            ['apiKey' => 'k', 'organizationId' => '1', 'propertyIds' => []],
            ['apiKey' => 'k', 'organizationId' => '1', 'propertyIds' => ['abc']],
        ] as $bad) {
            try {
                new CloudbedsConfig($bad);
                $this->fail('accepted ' . json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAccessorsAndDefaults(): void
    {
        $c = $this->config();
        $this->assertSame(['1', '2'], $c->getPropertyIds());
        $this->assertSame('https://api.cloudbeds.com', $c->getBaseUrl());
        $this->assertTrue($c->importGuestNotes(), 'guest notes are imported unless turned off');
        $this->assertFalse($this->config(['importGuestNotes' => false])->importGuestNotes());
        $this->assertFalse($c->createMissing('rooms'));
        $this->assertSame(0, $c->getDefaultHospitalId());
        $this->assertSame(12, $this->config(['defaultHospital' => '12'])->getDefaultHospitalId());
        $this->assertSame(0, $this->config(['defaultHospital' => ''])->getDefaultHospitalId());
        $this->assertTrue($this->config(['createMissing' => ['rooms' => true]])->createMissing('rooms'));
    }

    public function testStatusMap(): void
    {
        $c = $this->config(['reservationStatusMap' => ['canceled' => ReservationStatus::NoShow, 'Not Confirmed' => 'skip', 'checked_in' => ReservationStatus::Checkedout]]);

        $this->assertSame(['reservation' => ReservationStatus::Checkedout, 'visit' => VisitStatus::CheckedOut], $c->mapStatus('Checked_Out'));
        $this->assertSame(['reservation' => ReservationStatus::Checkedout, 'visit' => VisitStatus::CheckedOut], $c->mapStatus('checked_in'), 'a mapping overrides the default');
        $this->assertSame(['reservation' => ReservationStatus::NoShow, 'visit' => null], $c->mapStatus('canceled'));
        $this->assertSame(['reservation' => null, 'visit' => null], $c->mapStatus('not_confirmed'), 'skip, and the key is normalized');
        $this->assertSame(['reservation' => ReservationStatus::Committed, 'visit' => null], $c->mapStatus('confirmed'));
        $this->assertNull($c->mapStatus('deleted')['reservation']);
        $this->assertNull($c->mapStatus('something_new'));
    }

    public function testPayTypeMap(): void
    {
        $c = $this->config(['paymentMethodMap' => ['venmo' => PayType::Transfer]]);

        $this->assertSame(PayType::Cash, $c->mapPayType('Cash'));
        $this->assertSame(PayType::Check, $c->mapPayType('check'));
        $this->assertSame(PayType::Transfer, $c->mapPayType('Bank Transfer'));
        $this->assertSame(PayType::Transfer, $c->mapPayType('Venmo'));
        $this->assertSame(PayType::External, $c->mapPayType('credit'));
        $this->assertSame(PayType::External, $c->mapPayType(''));
    }

    public function testItemMap(): void
    {
        $c = $this->config(['chargeItemMap' => [
            'product' => (string) ItemId::VisitFee,
            'Channel Commission' => 'skip',
            'roomRevenue_no_show' => (string) ItemId::AddnlCharge,
            'adjustment' => (string) ItemId::AddnlCharge,
        ]]);

        $this->assertSame(ItemId::Lodging, $c->mapItem('rate', 100), 'room rates are lodging unless mapped');
        $this->assertSame(ItemId::Lodging, $c->mapItem('roomRevenue_manual', 100));
        $this->assertSame(ItemId::AddnlCharge, $c->mapItem('roomRevenue_no_show', 100), 'a mapping overrides the default');
        $this->assertSame(ItemId::VisitFee, $c->mapItem('product', 5));
        $this->assertNull($c->mapItem('channel_commission', 12), 'do not import, and the type is normalized');
        $this->assertSame(ItemId::AddnlCharge, $c->mapItem('tax', 8), 'unmapped charges are additional charges');
        $this->assertSame(ItemId::Discount, $c->mapItem('fee', -10), 'or discounts when negative');
        $this->assertSame(ItemId::AddnlCharge, $c->mapItem('adjustment', -10), 'unless mapped');
    }

    public function testRoomMap(): void
    {
        $c = $this->config(['roomMap' => ['Suite A' => '101']]);
        $this->assertSame(101, $c->getMappedRoomId('suite a'));
        $this->assertSame(101, $c->getMappedRoomId(' SUITE A '));
        $this->assertSame(0, $c->getMappedRoomId('Suite B'), 'not mapped, matched by name');
    }

    public function testMethodAndStatusKeysAreNormalized(): void
    {
        $c = $this->config([
            'paymentMethodMap' => ['Apple Pay' => PayType::Cash, 'Bank-Wire' => PayType::Transfer],
            'reservationStatusMap' => ['CANCELED' => ReservationStatus::NoShow],
        ]);

        $this->assertSame(PayType::Cash, $c->mapPayType('apple pay'));
        $this->assertSame(PayType::Transfer, $c->mapPayType('BANK_WIRE'));
        $this->assertSame(ReservationStatus::NoShow, $c->mapStatus('canceled')['reservation']);
    }

    public function testSettingsValidation(): void
    {
        CloudbedsConfig::validateSettings([]);
        CloudbedsConfig::validateSettings([
            'customFields' => ['guest' => ['a' => 'note.member'], 'reservation' => ['b' => 'hospital']],
            'reservationStatusMap' => ['canceled' => 'ns', 'deleted' => 'skip'],
            'paymentMethodMap' => ['venmo' => 'tf'],
            'chargeItemMap' => ['product' => '2', 'tax' => 9, 'channel_commission' => 'skip'],
            'roomMap' => ['A' => '1'],
        ]);
        $this->addToAssertionCount(1);

        foreach ([
            ['customFields' => ['guest' => ['a' => 'hospital']]],
            ['unmappedCustomFields' => 'maybe'],
            ['roomMap' => 'nope'],
            ['roomMap' => ['A' => 'Suite']],
            ['reservationStatusMap' => ['canceled' => 'zz']],
            ['chargeItemMap' => ['product' => '5']],
            ['chargeItemMap' => ['product' => 'two']],
            ['reservationStatusMap' => ['canceled' => ['reservation' => 'ns', 'visit' => null]]],
            ['paymentMethodMap' => ['venmo' => 'bitcoin']],
            ['chargeItemMap' => ['product' => 'two']],
            ['defaultHospital' => 'Mercy'],
        ] as $bad) {
            try {
                CloudbedsConfig::validateSettings($bad);
                $this->fail('accepted ' . json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testInvalidSettingsFailTheWholeConfig(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->config(['paymentMethodMap' => ['x' => 'zz']]);
    }

    public function testRawExposesConnectionAndSettings(): void
    {
        $c = $this->config(['defaultHospital' => '7']);
        $this->assertSame('7', $c->getRaw()['defaultHospital']);
        $this->assertSame(['1', '2'], $c->getRaw()['propertyIds']);
    }
}
