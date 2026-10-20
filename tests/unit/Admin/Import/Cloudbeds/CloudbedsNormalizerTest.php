<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudbedsNormalizer::class)]
class CloudbedsNormalizerTest extends TestCase
{
    public function testGuestProfile(): void
    {
        $r = CloudbedsNormalizer::person([
            'id' => '123456788', 'firstName' => ' Jane ', 'lastName' => "O'Brien", 'email' => 'j@example.com', 'phone' => '5551234567', 'cellPhone' => '5559876543',
            'birthday' => '1970-01-01', 'gender' => 'F', 'address1' => '1 Main St', 'address2' => 'Apt 2', 'city' => 'Town', 'state' => 'NY', 'zip' => '12345', 'country' => 'US',
        ]);

        $this->assertSame('123456788', $r['externalId']);
        $this->assertSame('Jane', $r['FirstName']);
        $this->assertSame("O'Brien", $r['LastName']);
        $this->assertSame('5559876543', $r['Mobile']);
        $this->assertSame('1 Main St', $r['Address']);
        $this->assertSame('12345', $r['ZipCode']);
        $this->assertSame('Female', $r['Gender']);
        $this->assertSame('1970-01-01', $r['BirthDate']);
    }

    public function testReservationGuestWithNestedAddress(): void
    {
        $r = CloudbedsNormalizer::person([
            'id' => '55', 'firstName' => 'Sam', 'lastName' => 'Lee',
            'address' => ['address1' => '9 Oak', 'city' => 'Ames', 'state' => 'IA', 'zip' => '50010', 'country' => 'US'],
        ]);

        $this->assertSame('9 Oak', $r['Address']);
        $this->assertSame('Ames', $r['City']);
        $this->assertSame('', $r['Address2']);
        $this->assertSame('', $r['Gender']);
    }

    public function testEveryKeyAddGuestReadsIsPresent(): void
    {
        $r = CloudbedsNormalizer::person([]);
        foreach (['FirstName', 'Middle', 'LastName', 'Email', 'Phone', 'Mobile', 'Address', 'Address2', 'City', 'County', 'State', 'ZipCode', 'Country', 'BirthDate', 'Gender', 'Hospital', 'externalId'] as $key) {
            $this->assertArrayHasKey($key, $r);
        }
    }

    public function testPlaceholderBirthDatesAreBlank(): void
    {
        $this->assertSame('', CloudbedsNormalizer::birthDate('0000-00-00'));
        $this->assertSame('', CloudbedsNormalizer::birthDate('1800-01-01'));
        $this->assertSame('', CloudbedsNormalizer::birthDate(''));
        $this->assertSame('1985-07-04', CloudbedsNormalizer::birthDate(' 1985-07-04 '));
        $this->assertSame('', CloudbedsNormalizer::person(['birthday' => '0000-00-00'])['BirthDate']);
    }

    public function testParseCustomFieldDateHandlesBareMmddyy(): void
    {
        // the "door code" field repurposed to hold a birth date, e.g. "010150" for January 1, 1950
        $this->assertSame('1950-01-01', CloudbedsNormalizer::parseCustomFieldDate('010150'));
        $this->assertSame('2008-05-03', CloudbedsNormalizer::parseCustomFieldDate('050308'), 'a 2 digit year that would be in the future as 20YY falls back to 19YY, otherwise 20YY');
        $this->assertSame('', CloudbedsNormalizer::parseCustomFieldDate('130199'), '13 is not a valid month');
    }

    public function testParseCustomFieldDateNeverProducesAFutureDate(): void
    {
        $currentTwoDigitYear = (int) date('y');
        $futureTwoDigitYear = str_pad((string) (($currentTwoDigitYear + 5) % 100), 2, '0', STR_PAD_LEFT);

        $date = CloudbedsNormalizer::parseCustomFieldDate('0101' . $futureTwoDigitYear);

        $this->assertLessThanOrEqual(date('Y-m-d'), $date, 'a birth date can never be in the future, so this must resolve to the 1900s');
        $this->assertStringStartsWith('19', $date);
    }

    public function testParseCustomFieldDateHandlesSeparatedFormats(): void
    {
        $this->assertSame('1950-01-01', CloudbedsNormalizer::parseCustomFieldDate('01/01/1950'));
        $this->assertSame('1950-01-01', CloudbedsNormalizer::parseCustomFieldDate('1/1/1950'));
        $this->assertSame('1950-01-01', CloudbedsNormalizer::parseCustomFieldDate('01-01-1950'));
        $this->assertSame('1950-01-01', CloudbedsNormalizer::parseCustomFieldDate('01/01/50'));
        $this->assertSame('1950-01-01', CloudbedsNormalizer::parseCustomFieldDate('1950-01-01'));
        $this->assertSame('1950-01-01', CloudbedsNormalizer::parseCustomFieldDate('1950/01/01'));
    }

    public function testParseCustomFieldDateHandlesBareMmddyyyy(): void
    {
        $this->assertSame('1950-03-04', CloudbedsNormalizer::parseCustomFieldDate('03041950'));
    }

    public function testParseCustomFieldDateRejectsUnrecognizedInput(): void
    {
        $this->assertSame('', CloudbedsNormalizer::parseCustomFieldDate(''));
        $this->assertSame('', CloudbedsNormalizer::parseCustomFieldDate('   '));
        $this->assertSame('', CloudbedsNormalizer::parseCustomFieldDate('not a date'));
        $this->assertSame('', CloudbedsNormalizer::parseCustomFieldDate('1234'), 'too short to be any recognized format');
        $this->assertSame('', CloudbedsNormalizer::parseCustomFieldDate('99999999'), 'not a valid calendar date');
    }

    public function testGender(): void
    {
        $this->assertSame('Male', CloudbedsNormalizer::gender('m'));
        $this->assertSame('Female', CloudbedsNormalizer::gender('FEMALE'));
        $this->assertSame('', CloudbedsNormalizer::gender('N/A'));
    }

    public function testDateTimeAppliesDefaultTimeOnlyWhenMidnight(): void
    {
        $this->assertSame('2024-03-05 16:00:00', CloudbedsNormalizer::dateTime('2024-03-05', '16:00:00'));
        $this->assertSame('2024-03-05 10:00:00', CloudbedsNormalizer::dateTime('2024-03-05 00:00:00', '10:00:00'));
        $this->assertSame('2024-03-05 14:30:00', CloudbedsNormalizer::dateTime('2024-03-05T14:30:00+00:00', '16:00:00'));
        $this->assertNull(CloudbedsNormalizer::dateTime('', '16:00:00'));
        $this->assertNull(CloudbedsNormalizer::dateTime('not a date', '16:00:00'));
    }
}
