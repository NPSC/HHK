<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsFieldMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudbedsFieldMapper::class)]
class CloudbedsFieldMapperTest extends TestCase
{
    public function testNormalizesBothCustomFieldShapes(): void
    {
        $pms = CloudbedsFieldMapper::normalizeField(['customFieldID' => '7', 'shortcode' => 'hosp', 'customFieldName' => 'Hospital', 'customFieldValue' => ' Mercy ']);
        $profile = CloudbedsFieldMapper::normalizeField(['customFieldId' => '8', 'name' => 'Ethnicity', 'value' => 'Asian']);

        $this->assertSame(['id' => '7', 'shortcode' => 'hosp', 'name' => 'Hospital', 'label' => '', 'value' => 'Mercy'], $pms);
        $this->assertSame(['id' => '8', 'shortcode' => '', 'name' => 'Ethnicity', 'label' => '', 'value' => 'Asian'], $profile);
    }

    public function testMapsByIdShortcodeOrNameCaseInsensitively(): void
    {
        $mapper = new CloudbedsFieldMapper(['reservation' => ['7' => 'hospital', 'DX' => 'diagnosis', 'Patient Name' => 'patient.full']]);

        $out = $mapper->apply('reservation', [
            ['customFieldID' => '7', 'customFieldName' => 'Anything', 'customFieldValue' => 'Mercy'],
            ['customFieldID' => '9', 'shortcode' => 'dx', 'customFieldName' => 'Dx', 'customFieldValue' => 'Flu'],
            ['customFieldID' => '10', 'customFieldName' => 'patient name', 'customFieldValue' => 'Doe, Jane'],
        ]);

        $this->assertSame(['hospital' => 'Mercy', 'diagnosis' => 'Flu', 'patient.full' => 'Doe, Jane'], $out['values']);
        $this->assertSame([], $out['unmapped']);
    }

    public function testNoteTargetsAndUnmappedFieldsBecomeNotes(): void
    {
        $mapper = new CloudbedsFieldMapper(['reservation' => ['special' => 'note.reservation', 'psgnote' => 'note.psg']]);

        $out = $mapper->apply('reservation', [
            ['customFieldName' => 'Special', 'customFieldValue' => 'Needs ground floor'],
            ['customFieldName' => 'PsgNote', 'customFieldValue' => 'Family of 4'],
            ['customFieldName' => 'Other', 'customFieldValue' => 'kept'],
            ['customFieldName' => 'Empty', 'customFieldValue' => ''],
        ]);

        $this->assertSame(['Special: Needs ground floor', 'Other: kept'], $out['notes']['note.reservation']);
        $this->assertSame(['PsgNote: Family of 4'], $out['notes']['note.psg']);
        $this->assertSame(['Other' => 'kept'], $out['unmapped']);
        $this->assertSame([], $out['values']);
    }

    public function testUnmappedFieldsCanBeDropped(): void
    {
        $mapper = new CloudbedsFieldMapper([], 'ignore');
        $out = $mapper->apply('guest', [['customFieldName' => 'Other', 'customFieldValue' => 'x']]);

        $this->assertSame([], $out['notes']);
        $this->assertSame(['Other' => 'x'], $out['unmapped']);
    }

    public function testUnmappedGuestFieldsGoToMemberNote(): void
    {
        $out = (new CloudbedsFieldMapper())->apply('guest', [['customFieldName' => 'Other', 'name' => 'Other', 'customFieldValue' => 'x']]);
        $this->assertSame(['Other: x'], $out['notes']['note.member']);
    }

    public function testRejectsTargetsFromTheWrongScope(): void
    {
        // reservation scope stays restricted to its own targets - a guest-only target is invalid there
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid target 'guest.Ethnicity' for reservation custom field 'h'");
        new CloudbedsFieldMapper(['reservation' => ['h' => 'guest.Ethnicity']]);
    }

    public function testAcceptsAPatientTargetFromGuestScope(): void
    {
        // guest scope is the expanded one - a reservation/patient target is valid there
        $mapper = new CloudbedsFieldMapper(['guest' => ['h' => 'hospital']]);
        $this->assertSame(['hospital' => 'Mercy'], $mapper->apply('guest', [['customFieldId' => '1', 'name' => 'h', 'value' => 'Mercy']])['values']);
    }

    public function testPatientDemographicTargetsAreOfferedAndExtracted(): void
    {
        $demographics = [
            'patient.Middle' => 'C', 'patient.Mobile' => '555-1212', 'patient.Ethnicity' => 'Asian',
            'patient.Address' => '1 Main St', 'patient.Address2' => 'Apt 2', 'patient.City' => 'Ames',
            'patient.County' => 'Story', 'patient.State' => 'IA', 'patient.ZipCode' => '50010', 'patient.Country' => 'US',
        ];

        foreach (array_keys($demographics) as $target) {
            $this->assertContains($target, CloudbedsFieldMapper::targetsFor('reservation'), "missing target $target");
            $this->assertContains($target, CloudbedsFieldMapper::targetsFor('guest'), "guest scope should offer $target too");
        }

        $config = [];
        $fields = [];
        $i = 0;
        foreach ($demographics as $target => $value) {
            $key = "cf$i";
            $config[$key] = $target;
            $fields[] = ['customFieldId' => (string) $i, 'name' => $key, 'value' => $value];
            $i++;
        }

        $mapper = new CloudbedsFieldMapper(['reservation' => $config]);
        $this->assertSame($demographics, $mapper->apply('reservation', $fields)['values']);
    }

    public function testIsValidTargetAcceptsAnyEnabledDemographicByPattern(): void
    {
        $this->assertTrue(CloudbedsFieldMapper::isValidTarget('guest', 'guest.demog.Special_Needs'));
        $this->assertTrue(CloudbedsFieldMapper::isValidTarget('guest', 'patient.demog.Special_Needs'), 'patient.demog.* is valid from guest scope too, like the rest of RESERVATION_FIELDS');
        $this->assertTrue(CloudbedsFieldMapper::isValidTarget('reservation', 'patient.demog.Special_Needs'));

        $this->assertFalse(CloudbedsFieldMapper::isValidTarget('reservation', 'guest.demog.Special_Needs'), 'guest.demog.* only makes sense on a guest-profile field');
        $this->assertFalse(CloudbedsFieldMapper::isValidTarget('guest', 'guest.demog.'), 'no code at all');
        $this->assertFalse(CloudbedsFieldMapper::isValidTarget('guest', 'guest.demog.Bad Code'), 'spaces are not a valid gen_lookups Code');
        $this->assertFalse(CloudbedsFieldMapper::isValidTarget('guest', 'guest.demograph.Special_Needs'), 'must be the demog segment exactly');
        $this->assertFalse(CloudbedsFieldMapper::isValidTarget('guest', 'not.a.real.target'));

        // still true for the fixed, hardcoded targets
        $this->assertTrue(CloudbedsFieldMapper::isValidTarget('guest', 'guest.Ethnicity'));
        $this->assertFalse(CloudbedsFieldMapper::isValidTarget('reservation', 'guest.Ethnicity'));
    }

    public function testConstructorAcceptsAnEnabledDemographicTarget(): void
    {
        $mapper = new CloudbedsFieldMapper(['guest' => ['sn' => 'guest.demog.Special_Needs'], 'reservation' => ['vet' => 'patient.demog.Branch_Of_Service']]);

        $out = $mapper->apply('guest', [['customFieldId' => '1', 'name' => 'sn', 'value' => 'Wheelchair accessible']]);
        $this->assertSame(['guest.demog.Special_Needs' => 'Wheelchair accessible'], $out['values']);
    }

    public function testConstructorRejectsAMalformedDemographicTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid target 'guest.demog.Bad Code'");
        new CloudbedsFieldMapper(['guest' => ['sn' => 'guest.demog.Bad Code']]);
    }

    public function testRejectsUnknownScopeAndUnmappedMode(): void
    {
        try {
            new CloudbedsFieldMapper(['stay' => []]);
            $this->fail('unknown scope accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("Unknown custom field scope 'stay'", $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        new CloudbedsFieldMapper([], 'maybe');
    }

    public function testResolveReportsWhichConfigKeyMatched(): void
    {
        $mapper = new CloudbedsFieldMapper(['reservation' => ['Hosp' => 'hospital']]);

        $this->assertSame(['key' => 'hosp', 'target' => 'hospital'], $mapper->resolve('reservation', ['id' => '9', 'shortcode' => 'HOSP', 'name' => 'Hospital', 'value' => '']));
        $this->assertNull($mapper->resolve('reservation', ['id' => '9', 'shortcode' => '', 'name' => 'Other', 'value' => '']));
        $this->assertNull($mapper->resolve('guest', ['id' => '9', 'shortcode' => 'hosp', 'name' => '', 'value' => '']), 'scopes are separate');
        $this->assertSame(['reservation' => ['hosp' => 'hospital']], $mapper->getMap());
    }

    public function testTargetsForScope(): void
    {
        $guest = CloudbedsFieldMapper::targetsFor('guest');
        $reservation = CloudbedsFieldMapper::targetsFor('reservation');

        $this->assertContains('guest.Ethnicity', $guest);
        $this->assertContains('note.member', $guest);
        $this->assertContains('hospital', $reservation);
        $this->assertContains('vehicle.license', $reservation);
        $this->assertNotContains('guest.Ethnicity', $reservation, 'a reservation field can not target a guest-only field');
        $this->assertNotContains('note.member', $reservation, 'a reservation field can not target a guest-only note');
        $this->assertNotContains('ignore', array_merge($guest, $reservation));
    }

    public function testAGuestFieldCanAlsoTargetAPatientOrReservationField(): void
    {
        // Cloudbeds has no separate concept for the patient, so a guest (profile-level) custom field can carry patient,
        // hospital stay, vehicle or PSG data too - the same targets a reservation custom field can use
        $guest = CloudbedsFieldMapper::targetsFor('guest');

        foreach (CloudbedsFieldMapper::targetsFor('reservation') as $reservationTarget) {
            $this->assertContains($reservationTarget, $guest, "guest scope should also allow '$reservationTarget'");
        }

        $mapper = new CloudbedsFieldMapper(['guest' => ['veteran_dob' => 'patient.BirthDate', 'veteran_name' => 'patient.full']]);
        $out = $mapper->apply('guest', [['customFieldId' => '1', 'name' => 'veteran_dob', 'value' => '1950-01-01'], ['customFieldId' => '2', 'name' => 'veteran_name', 'value' => 'Doe, John']]);

        $this->assertSame(['patient.BirthDate' => '1950-01-01', 'patient.full' => 'Doe, John'], $out['values']);
    }

    public function testEveryFieldHasALabelAndAGroup(): void
    {
        foreach (['guest', 'reservation'] as $scope) {
            foreach (CloudbedsFieldMapper::fieldsFor($scope) as $group => $fields) {
                $this->assertNotSame('', $group);
                foreach ($fields as $target => $label) {
                    $this->assertNotSame('', $label, "$scope $target");
                }
            }
        }
    }

    public function testFieldKeyPrefersShortcodeForReservationsAndNameForGuests(): void
    {
        $field = ['id' => '12', 'shortcode' => 'hosp', 'name' => 'Hospital'];

        $this->assertSame('hosp', CloudbedsFieldMapper::fieldKey('reservation', $field));
        $this->assertSame('Hospital', CloudbedsFieldMapper::fieldKey('guest', $field));
        $this->assertSame('Hospital', CloudbedsFieldMapper::fieldKey('reservation', ['shortcode' => '', 'name' => 'Hospital']));
        $this->assertSame('12', CloudbedsFieldMapper::fieldKey('guest', ['id' => '12']));
        $this->assertSame('', CloudbedsFieldMapper::fieldKey('guest', []));
    }

    public function testMatchesByLabelToo(): void
    {
        $mapper = new CloudbedsFieldMapper(['guest' => ['ethnic background' => 'guest.Ethnicity']]);
        $out = $mapper->apply('guest', [['customFieldId' => '1', 'name' => 'ethnicity', 'label' => 'Ethnic Background', 'value' => 'Asian']]);

        $this->assertSame(['guest.Ethnicity' => 'Asian'], $out['values']);
    }

    public function testSplitFullName(): void
    {
        $this->assertSame(['first' => 'Jane', 'last' => 'Doe'], CloudbedsFieldMapper::splitFullName('Doe, Jane'));
        $this->assertSame(['first' => 'Mary Ann', 'last' => 'Smith'], CloudbedsFieldMapper::splitFullName('  Mary  Ann Smith '));
        $this->assertSame(['first' => '', 'last' => 'Cher'], CloudbedsFieldMapper::splitFullName('Cher'));
        $this->assertSame(['first' => '', 'last' => ''], CloudbedsFieldMapper::splitFullName(''));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function junkNames(): array
    {
        return [
            'n/a' => ['n/a'], 'N/A' => ['N/A'], 'na' => ['na'], 'n.a.' => ['n.a.'],
            'none' => ['None'], 'false' => ['FALSE'], 'no' => ['no'], 'null' => ['NULL'],
            'dash' => ['-'], 'double dash' => ['--'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('junkNames')]
    public function testSplitFullNameTreatsPlaceholderValuesAsNoName(string $junk): void
    {
        // a "Veteran Name if different" custom field holding a placeholder like "n/a" must not become the patient's name
        $this->assertSame(['first' => '', 'last' => ''], CloudbedsFieldMapper::splitFullName($junk));
    }

    public function testSplitFullNameDoesNotTreatARealNameAsJunk(): void
    {
        $this->assertSame(['first' => 'Norma', 'last' => 'Ackerman'], CloudbedsFieldMapper::splitFullName('Norma Ackerman'));
    }
}
