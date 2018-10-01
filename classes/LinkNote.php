<?php
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

    public static function save(\PDO $dbh, $noteText, $linkId, $linkType, $userName, $concatNotes = FALSE) {

        if ($linkType == '' || $linkId < 1) {
            return array('error'=>'The Link Type is missing.');
        }

        // Create a new note.
        $note = Note::createNew($noteText, $userName);
        $note->saveNew($dbh);

        if ($note->getIdNote() > 0) {

            $result = LinkNote::saveLink($dbh, $note, $linkId, $linkType, $userName);

            if ($concatNotes) {

                $idPsg = LinkNote::findIdPsg($dbh, $linkType, $linkId);

                if ($idPsg > 0) {
                    $psgResult = LinkNote::saveLink($dbh, $note, $idPsg, Note::PsgLink, $userName);
                }
            }
        }

        return $note->getIdNote();

    }

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
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if (is_array($rows) && isset($rows[0][0])) {
                $idPsg = intval($rows[0][0], 10);
            }
        }

        return $idPsg;
    }

    protected static function saveLink(\PDO $dbh, $note, $linkId, $linkType, $userName) {

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
                    $stmt = $dbh->query("SELECT v.`idReservation`, ifnull(r.Title, '(?)') FROM `visit` v LEFT JOIN resource r ON v.idResource = r.idResource WHERE `Span` = 0 AND `idVisit` =" . $linkId);
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

                case Note::MemberLink:

                    //break;

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
