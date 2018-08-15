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
class LinkNote extends Note {

    protected $idLink;
    protected $linkType;

    public static function save(\PDO $dbh, $noteText, $linkId, $linkType, $userName) {

        // Create a new note.
        $note = Note::createNote($userName, $noteText);
        $note->save($dbh);


        if ($linkType == '') {
            return array('error'=>'The Link Type is missing.');
        }

        switch ($linkType) {

            case Note::ResvLink:

                $dbView = 'vresv_notes';
                $whereField = 'Reservation_Id';
                break;

            case Note::VisitLink:

                $dbView = 'vvisit_notes';
                $whereField = 'idVisit';
                break;

            case Note::PsgLink:

                $dbView = 'vpsg_notes';
                $whereField = 'Psg_Id';
                break;

            case Note::MemberLink:

                //break;

            case Note::RoomLink:

                //break;

            default:
                return array('error'=>'The Link Type is not found: ' . $linkType);
        }

        if ($rid > 0 && $note->getIdNote() > 0) {

            $stmt = $dbh->query("Select count(*) from reservation_note where Note_Id = " . $note->getIdNote() . " and Reservation_Id = " . $rid);
            $rows = $stmt->fetchAll();

            if (count($rows) > 0 && $rows[0][0] == 0) {

                // add record
                $dbh->exec("insert into reservation_note (Reservation_Id, Note_Id) values ('$rid', '" . $note->getIdNote() . "');");

            }
        }

        return $note->getIdNote();

    }

}
