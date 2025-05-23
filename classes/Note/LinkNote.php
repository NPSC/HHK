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
    public static function findIdPsg(\PDO $dbh, $linkType, $linkId) {

        $query = '';
        $idPsgs = [];

        if ($linkType == Note::ResvLink) {
            $query = "select reg.idPsg from registration reg join reservation r on reg.idRegistration = r.idRegistration "
                    . "where r.idReservation = $linkId";
        } else if ($linkType == Note::VisitLink) {
            $query = "select reg.idPsg from registration reg join visit r on reg.idRegistration = r.idRegistration "
                    . "where r.idVisit = $linkId";
        } else if ($linkType == Note::PsgLink) {
            return [$linkId];
        }else if ($linkType == "curguests") {
            $query = "select reg.idPsg from registration reg join reservation r on reg.idRegistration = r.idRegistration "
                    . "where r.Status = 's'";
        }else if ($linkType == "confirmed") {
            $query = "select reg.idPsg from registration reg join reservation r on reg.idRegistration = r.idRegistration "
                    . "where r.Status = 'a'";
        }else if ($linkType == "unconfirmed") {
            $query = "select reg.idPsg from registration reg join reservation r on reg.idRegistration = r.idRegistration "
                    . "where r.Status = 'uc'";
        }else if ($linkType == "waitlist") {
            $query = "select reg.idPsg from registration reg join reservation r on reg.idRegistration = r.idRegistration "
                    . "where r.Status = 'w'";
        }

        if ($query != '') {

            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if(count($rows) > 0){
                foreach($rows as $k=>$v){
                    $idPsgs[] = $v[0];
                }
            }
        }
        return $idPsgs;
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
