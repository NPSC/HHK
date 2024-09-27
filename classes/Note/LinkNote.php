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
     * @return mixed
     */
    public static function findIdPsg(\PDO $dbh, $linkType, $linkId) {

        $query = '';
        $idPsg = 0;

        if ($linkType == Note::ResvLink) {
            $query = "select reg.idPsg from registration reg join reservation r on reg.idRegistration = r.idRegistration "
                    . "where r.idReservation = $linkId";
        } else if ($linkType == Note::VisitLink) {
            $query = "select reg.idPsg from registration reg join visit r on reg.idRegistration = r.idRegistration "
                    . "where r.idVisit = $linkId";
        } else if ($linkType == Note::PsgLink) {
            return $linkId;
        }

        if ($query != '') {

            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (is_array($rows) && isset($rows[0][0])) {
                $idPsg = intval($rows[0][0], 10);
            }
        }

        return $idPsg;
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

            $table = '';
            $field = '';

            switch ($linkType) {

                case Note::ResvLink:

                    $table = 'reservation_note';
                    $field = 'Reservation_Id';
                    break;

                case Note::VisitLink:

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
                        $table = 'reservation_note';
                        $field = 'Reservation_Id';
                    }

                    break;

                case Note::PsgLink:

                    $table = 'psg_note';
                    $field = 'Psg_Id';
                    break;

                case Note::DocumentLink:

                    $table = 'doc_note';
                    $field = 'Doc_Id';
                    break;

                case Note::StaffLink:

                    $table = 'staff_note';
                    $field = 'Link_Id';
                    break;

                case Note::MemberLink:
                    $table = 'member_note';
                    $field = 'idName';
                    break;

                case Note::RoomLink:

                    //break;

                default:
                    return 'The Link Type is not found: ' . $linkType;
            }

            if ($table != '' && $field != '') {

                $dbh->exec("insert into `$table` (`$field`, Note_Id) values ('$linkId', '" . $note->getIdNote() . "');");
            } else {
                return 'The link table or link field are missing ';
            }
        }

        return '';

    }

}
