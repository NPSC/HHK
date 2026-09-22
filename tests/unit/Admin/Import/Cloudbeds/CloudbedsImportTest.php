<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use HHK\Admin\Import\Cloudbeds\CloudbedsFieldMapper;
use HHK\Admin\Import\Cloudbeds\CloudbedsImport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * CloudbedsImport itself is not unit tested: its constructor alone runs several real queries (loadGenLookups(), getHospitals(),
 * getRooms()), and its import* methods write people, reservations, invoices and payments through the rest of HHK, so there is no
 * way to exercise it without a live database. This only covers what can be checked without one: that GEN_LOOKUP_TARGETS (the
 * "Gen Lookup Values" review on the settings page, see CloudbedsStaging::summarizeGenLookupValues()) stays in sync with the
 * custom field catalog it reads from.
 */
#[CoversClass(CloudbedsImport::class)]
class CloudbedsImportTest extends TestCase
{
    public function testGenLookupTargetsAreValidCustomFieldTargets(): void
    {
        $guestTargets = CloudbedsFieldMapper::targetsFor(CloudbedsFieldMapper::SCOPE_GUEST);
        $reservationTargets = CloudbedsFieldMapper::targetsFor(CloudbedsFieldMapper::SCOPE_RESERVATION);

        foreach (CloudbedsImport::GEN_LOOKUP_TARGETS as $target => $table) {
            $expected = str_starts_with($target, 'guest.') ? $guestTargets : $reservationTargets;
            $this->assertContains($target, $expected, "GEN_LOOKUP_TARGETS has a target '$target' that no longer exists in the custom field catalog");
            $this->assertNotSame('', trim($table));
        }
    }

    public function testEveryGenLookupTargetsTableIsUnique(): void
    {
        // duplicates would be harmless (createMissingGenLookupValues() filters by table before use) but would make the
        // settings page render the same review table twice
        $tables = array_values(CloudbedsImport::GEN_LOOKUP_TARGETS);
        $this->assertCount(count(array_unique($tables)), $tables);
    }
}
