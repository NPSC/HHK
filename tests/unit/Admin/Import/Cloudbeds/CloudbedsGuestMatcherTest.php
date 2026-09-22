<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsGuestMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudbedsGuestMatcher::class)]
class CloudbedsGuestMatcherTest extends TestCase
{
    protected function profiles(string ...$ids): array
    {
        return array_map(fn($id) => ['id' => $id, 'firstName' => 'Same', 'lastName' => 'Name'], $ids);
    }

    protected function guestList(string ...$ids): array
    {
        return array_fill_keys($ids, ['guestFirstName' => 'Same', 'guestLastName' => 'Name']);
    }

    public function testMainGuestIsPairedByTheReservation(): void
    {
        $this->assertSame(['P1' => 'G1'], CloudbedsGuestMatcher::match([], [], 'P1', 'G1'), 'the guests needn\'t be listed');
    }

    public function testTheOnlyGuestLeftOnEachSideIsTheSamePerson(): void
    {
        $matches = CloudbedsGuestMatcher::match($this->profiles('P1', 'P2'), $this->guestList('G1', 'G2'), 'P1', 'G1');

        $this->assertSame(['P1' => 'G1', 'P2' => 'G2'], $matches);
    }

    public function testASingleGuestReservationNeedsNoMainGuestPairing(): void
    {
        $this->assertSame(['P1' => 'G1'], CloudbedsGuestMatcher::match($this->profiles('P1'), $this->guestList('G1'), '', ''));
    }

    public function testSeveralOtherGuestsAreNotGuessed(): void
    {
        $matches = CloudbedsGuestMatcher::match($this->profiles('P1', 'P2', 'P3'), $this->guestList('G1', 'G2', 'G3'), 'P1', 'G1');

        $this->assertSame(['P1' => 'G1'], $matches, 'two guests are left on each side, and nothing but their names says which is which');
    }

    public function testNamesEmailsAndBirthDatesAreNeverUsed(): void
    {
        $profiles = [
            ['id' => 'P1', 'firstName' => 'Jane', 'lastName' => 'Doe'],
            ['id' => 'P2', 'firstName' => 'Sam', 'lastName' => 'Lee', 'email' => 's@example.com', 'birthday' => '2000-01-01'],
            ['id' => 'P3', 'firstName' => 'Kim', 'lastName' => 'Ng', 'email' => 'k@example.com'],
        ];
        $guestList = [
            'G1' => ['guestFirstName' => 'Jane', 'guestLastName' => 'Doe'],
            'G2' => ['guestFirstName' => 'Kim', 'guestLastName' => 'Ng', 'guestEmail' => 'k@example.com'],
            'G3' => ['guestFirstName' => 'Sam', 'guestLastName' => 'Lee', 'guestEmail' => 's@example.com', 'guestBirthdate' => '2000-01-01'],
        ];

        $this->assertSame(['P1' => 'G1'], CloudbedsGuestMatcher::match($profiles, $guestList, 'P1', 'G1'), 'even when every name, email and birth date agree');
    }

    public function testUnequalListsAreNotPaired(): void
    {
        // a guest Cloudbeds lists in only one API leaves nothing to conclude
        $this->assertSame(['P1' => 'G1'], CloudbedsGuestMatcher::match($this->profiles('P1', 'P2'), $this->guestList('G1'), 'P1', 'G1'));
        $this->assertSame(['P1' => 'G1'], CloudbedsGuestMatcher::match($this->profiles('P1'), $this->guestList('G1', 'G2'), 'P1', 'G1'));
    }

    public function testNumericIdsAreHandledAsStrings(): void
    {
        // array keys that look like integers are ints in PHP
        $matches = CloudbedsGuestMatcher::match([['id' => 555], ['id' => 666]], [111 => [], 222 => []], '555', '111');

        $this->assertSame(['555' => '111', '666' => '222'], $matches);
    }
}
