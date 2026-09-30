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

    /**
     * A companion guest's HHK External_Id must never look like a bare Guest Profile id (a real one is always a bare
     * number, e.g. from the PMS reservation fields) - see ensureReservationPeople(). A live-data-confirmed bug: the
     * Guest Profiles API's reservation.guests[].id is a PMS guest id, not a profile id, and storing it unprefixed
     * caused it to be mistaken for a person's real profile id on a later import, creating a duplicate person.
     */
    public function testPmsGuestExternalIdIsNeverAPlainNumber(): void
    {
        $externalId = (new \ReflectionMethod(CloudbedsImport::class, 'pmsGuestExternalId'))->invoke(null, '182375451');

        $this->assertSame('pmsguest:182375451', $externalId);
        $this->assertFalse(ctype_digit($externalId), 'must not be confusable with a bare Guest Profile id');
    }
}
