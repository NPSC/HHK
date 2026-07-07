<?php

namespace HHK\Note;

/**
 * LinkNote.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of LinkNote
 *
 * @author Eric
 */
class LinkNote {

    /**
     * Summary of save
     * @param \PDO $dbh
     * @param mixed $noteText
     * @param mixed $linkId
     * @param mixed $linkType
     * @param mixed $noteCategory
     * @param mixed $userName
     * @param mixed $concatNotes
     * @return int|string[]
     */
    public static function save(\PDO $dbh, $noteText, $linkId, $linkType, $noteCategory, $userName, $concatNotes = FALSE) {

        if ($linkType == '' || $linkId < 0) {
            return array('error'=>'The Link Type is missing.');
        }

        // Create a new note.
        $note = Note::createNew($noteText, $userName, $noteCategory);
        $note->saveNew($dbh);

        if ($note->getIdNote() > 0) {

            LinkNote::saveLink($dbh, $note, $linkId, $linkType);

        }

        return $note->getIdNote();

    }

    /**
     * Summary of findIdPsg
     * @param \PDO $dbh
     * @param mixed $linkType
     * @param mixed $linkId
     * @return array
     */
    public static function findIdPsg(\PDO $dbh, $linkType, $linkId): array {

        if ($linkType === Note::PsgLink) {
            return [$linkId];
        }

        $statusMap = [
            'curguests'   => 's',
            'confirmed'   => 'a',
            'unconfirmed' => 'uc',
            'waitlist'    => 'w',
        ];

        $query = "SELECT reg.idPsg FROM registration reg JOIN reservation r ON reg.idRegistration = r.idRegistration";

        if ($linkType === Note::ResvLink) {
            $stmt = $dbh->prepare("$query WHERE r.idReservation = ?");
            $stmt->execute([$linkId]);
        } elseif ($linkType === Note::VisitLink) {
            $stmt = $dbh->prepare("SELECT reg.idPsg FROM registration reg JOIN visit r ON reg.idRegistration = r.idRegistration WHERE r.idVisit = ?");
            $stmt->execute([$linkId]);
        } elseif (isset($statusMap[$linkType])) {
            $stmt = $dbh->prepare("$query WHERE r.Status = ?");
            $stmt->execute([$statusMap[$linkType]]);
        } else {
            return [];
        }

        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
        
    }

    /**
     * Summary of saveLink
     * @param \PDO $dbh
     * @param Note $note
     * @param mixed $linkId
     * @param mixed $linkType
     * @return string
     */
    protected static function saveLink(\PDO $dbh, Note $note, $linkId, $linkType) {

        if ($note->getIdNote() > 0) {

            if($linkType == Note::VisitLink) {

                // We actually need the reservation ID
                $stmt = $dbh->query("SELECT
    v.`idReservation`, IFNULL(r.Title, '(?)')
FROM
    `visit` v
        LEFT JOIN
    reservation rv on v.idReservation = rv.idReservation
	LEFT JOIN
    resource r ON rv.idResource = r.idResource
WHERE
    v.`Span` = 0 AND v.`idVisit` =" . $linkId);
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                if (count($rows) > 0) {

                    // Update the visit text
                    $title = 'Visit ' . $linkId . ', Room ' . $rows[0][1];
                    $note->saveTitle($dbh, $title);

                    $linkId = $rows[0][0];
                    $linkType = Note::ResvLink;
                }
            }

            if ($linkId >= 0) {

                $dbh->exec("insert into `link_note` (`linkType`, `idLink`, `idNote`) values ('$linkType', '$linkId', '" . $note->getIdNote() . "');");
            } else {
                return 'The link id is missing ';
            }
        }

        return '';

    }

}
