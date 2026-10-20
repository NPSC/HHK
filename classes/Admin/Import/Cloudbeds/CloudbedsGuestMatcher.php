<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Works out which PMS guest id belongs to which guest profile on a reservation, without guessing.
 *
 * Cloudbeds has two ids for a person: the guest profile id (Guest Profiles API) and the PMS guest id (PMS API, and the id
 * getGuestNotes takes). A PMS reservation names the profile id and guest id of its main guest, and that is the only place
 * Cloudbeds pairs them. The other guests are listed by profile id in the Guest Profiles API's reservation and by guest id in the
 * PMS reservation, with nothing in common but their names, which are not used. So:
 *
 *  - the main guest is paired by the reservation
 *  - if that leaves exactly one guest in each list, they are the same person
 *  - any other guest can't be paired on this reservation. They are paired on another reservation where they are the main guest, or not at all.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
final class CloudbedsGuestMatcher {

    /**
     * @param array[] $profileGuests guests of the reservation from the Guest Profiles API, each with an "id" (profile id)
     * @param array<string|int, mixed> $guestList the PMS reservation's guest list, keyed by PMS guest id
     * @param string $mainProfileId profileID of the PMS reservation (its main guest), '' if unknown
     * @param string $mainGuestId guestID of the PMS reservation (its main guest), '' if unknown
     * @return array<string, string> [profile id => PMS guest id]
     */
    public static function match(array $profileGuests, array $guestList, string $mainProfileId, string $mainGuestId): array {
        $matches = [];

        if ($mainProfileId !== '' && $mainGuestId !== '') {
            $matches[$mainProfileId] = $mainGuestId;
        }

        $unpairedProfiles = [];
        foreach ($profileGuests as $g) {
            $id = (string) ($g['id'] ?? '');
            if ($id !== '' && !isset($matches[$id])) {
                $unpairedProfiles[$id] = true;
            }
        }

        $unpairedGuests = [];
        foreach (array_keys($guestList) as $guestId) {
            $guestId = (string) $guestId;
            if ($guestId !== '' && !in_array($guestId, $matches, true)) {
                $unpairedGuests[$guestId] = true;
            }
        }

        if (count($unpairedProfiles) === 1 && count($unpairedGuests) === 1) {
            $matches[(string) array_key_first($unpairedProfiles)] = (string) array_key_first($unpairedGuests);
        }

        return $matches;
    }
}
