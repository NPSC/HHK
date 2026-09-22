<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsConfig;
use HHK\Admin\Import\Cloudbeds\CloudbedsConfigStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudbedsConfigStore::class)]
class CloudbedsConfigStoreTest extends TestCase
{
    public function testParsePropertyIds(): void
    {
        $this->assertSame(['12', '34', '56'], CloudbedsConfigStore::parsePropertyIds('12, 34 ;56'));
        $this->assertSame(['7', '8'], CloudbedsConfigStore::parsePropertyIds([7, '8', 'x', '']));
        $this->assertSame(['5'], CloudbedsConfigStore::parsePropertyIds('5,5'));
        $this->assertSame([], CloudbedsConfigStore::parsePropertyIds('none'));
    }

    public function testSettingsFromFormBuildsOptions(): void
    {
        $settings = CloudbedsConfigStore::settingsFromForm([
            'defaultHospital' => ' 12 ',
            'createMissing' => ['hospitals' => '1', 'genLookups' => '1'],
            'unmappedCustomFields' => 'ignore',

        ]);

        $this->assertSame('12', $settings['defaultHospital']);
        $this->assertSame(['hospitals' => true, 'rooms' => false, 'genLookups' => true], $settings['createMissing']);
        $this->assertSame('ignore', $settings['unmappedCustomFields']);
        $this->assertFalse($settings['importGuestNotes'], 'an unchecked box is not posted');
        $this->assertTrue(CloudbedsConfigStore::settingsFromForm(['importGuestNotes' => '1'])['importGuestNotes']);
        $this->assertArrayNotHasKey('customFields', $settings, 'the mapping is in crm_field_map, not a setting');
        foreach (['roomMap', 'paymentMethodMap', 'reservationStatusMap', 'chargeItemMap', 'customFields'] as $mapped) {
            $this->assertArrayNotHasKey($mapped, $settings, "$mapped is a mapping, not a setting");
        }
        CloudbedsConfig::validateSettings($settings);
    }

    public function testOnlySettingsAreReturned(): void
    {
        $existing = [
            'defaultHospital' => '3',
            'roomMap' => ['A' => '1'],
            'apiKey' => 'must not be copied into settings',
        ];

        $settings = CloudbedsConfigStore::settingsFromForm(['defaultHospital' => ''], $existing);

        $this->assertSame('', $settings['defaultHospital'], 'what is in the form wins');
        $this->assertArrayNotHasKey('roomMap', $settings, 'mappings are not settings');
        $this->assertArrayNotHasKey('apiKey', $settings);
    }

    public function testFieldMapFromFormParsesRowsInOrderAndDropsIncompleteOnes(): void
    {
        $rows = CloudbedsConfigStore::fieldMapFromForm([
            'fldmap_posted' => ['reservation' => '1', 'guest' => '1'],
            'fldmap_hhk' => ['reservation' => [0 => 'hospital', 2 => 'patient.full', 3 => '', 4 => 'mrn'], 'guest' => []],
            'fldmap_crm' => ['reservation' => [0 => ' hosp ', 2 => 'Patient Name', 3 => 'orphan', 4 => '']],
        ]);

        $this->assertSame(['hhk' => 'hospital', 'crm' => 'hosp'], $rows['reservation'][0]);
        $this->assertSame(['hhk' => 'patient.full', 'crm' => 'Patient Name'], $rows['reservation'][1]);
        $this->assertCount(2, $rows['reservation']);
        $this->assertSame([], $rows['guest'], 'posted with no rows clears the object');
    }

    public function testFieldMapFromFormIgnoresObjectsThatWerentPosted(): void
    {
        $rows = CloudbedsConfigStore::fieldMapFromForm([
            'fldmap_posted' => ['guest' => '1', 'other' => '1'],
            'fldmap_hhk' => ['guest' => ['guest.Email'], 'reservation' => ['hospital']],
            'fldmap_crm' => ['guest' => ['e-mail'], 'reservation' => ['hosp']],
        ]);

        $this->assertSame(['guest'], array_keys($rows));
    }

    public function testValidFieldMapPasses(): void
    {
        CloudbedsConfigStore::validateFieldMap([
            'guest' => [['hhk' => 'guest.Ethnicity', 'crm' => 'Ethnicity'], ['hhk' => 'note.member', 'crm' => 'Notes']],
            'reservation' => [['hhk' => 'hospital', 'crm' => 'hosp']],
        ]);
        CloudbedsConfigStore::validateFieldMap([]);

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{0: array, 1: string}>
     */
    public static function invalidFieldMaps(): array
    {
        return [
            'field from the other object' => [['guest' => [['hhk' => 'hospital', 'crm' => 'x']]], "'hospital' is not an HHK field for guest"],
            'unknown HHK field' => [['reservation' => [['hhk' => 'ignore', 'crm' => 'x']]], "'ignore' is not an HHK field"],
            'HHK field twice' => [['reservation' => [['hhk' => 'mrn', 'crm' => 'a'], ['hhk' => 'mrn', 'crm' => 'b']]], "'MRN' is mapped more than once"],
            'Cloudbeds field twice' => [['reservation' => [['hhk' => 'mrn', 'crm' => 'Field'], ['hhk' => 'hospital', 'crm' => 'field']]], "'field' is mapped to more than one HHK field"],
            'unknown object' => [['stay' => []], "Unknown custom field object 'stay'"],
            'too long' => [['reservation' => [['hhk' => 'mrn', 'crm' => str_repeat('x', 101)]]], 'is too long'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFieldMaps')]
    public function testInvalidFieldMapsAreRejected(array $rows, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        CloudbedsConfigStore::validateFieldMap($rows);
    }

    public function testLoadedMappingBecomesAValidConfigMapping(): void
    {
        // load() gives the config [Cloudbeds field => HHK field], which the mapper and config validation accept
        $customFields = ['guest' => ['Ethnicity' => 'guest.Ethnicity'], 'reservation' => ['hosp' => 'hospital', 'Patient Name' => 'patient.full']];

        CloudbedsConfig::validateSettings(['customFields' => $customFields]);
        $out = (new \HHK\Admin\Import\Cloudbeds\CloudbedsFieldMapper($customFields))->apply('reservation', [['customFieldID' => '1', 'shortcode' => 'hosp', 'customFieldName' => 'Hospital', 'customFieldValue' => 'Mercy']]);

        $this->assertSame(['hospital' => 'Mercy'], $out['values']);
    }
}
