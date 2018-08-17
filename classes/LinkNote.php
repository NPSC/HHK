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

    public static function save(\PDO $dbh, $noteText, $linkId, $linkType, $userName) {

        if ($linkType == '' || $linkId < 1) {
            return array('error'=>'The Link Type is missing.');
        }

        // Create a new note.
        $note = Note::createNew($noteText, $userName);
        $note->saveNew($dbh);

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
                    $stmt = $dbh->query("select `idReservation` from `visit` where `Span` = 0 and `idVisit` = " . $linkId);
                    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                    if (count($rows) > 0) {

                        // Update the visit text
                        $newText = 'Visit ' . $linkId . '; ' . $note->getNoteText();
                        $note->updateContents($dbh, $newText, $userName);

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
                    return array('error'=>'The Link Type is not found: ' . $linkType);
            }

            if ($table != '' && $field != '') {

                $dbh->exec("insert into `$table` (`$field`, Note_Id) values ('$linkId', '" . $note->getIdNote() . "');");
            }
        }

        return $note->getIdNote();

    }

}
