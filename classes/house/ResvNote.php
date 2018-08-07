<?php

/**
 * Description of ResvNote
 *
 * @author Eric
 */
class ResvNote {

    public static function save(\PDO $dbh, $idNote, $noteText, $rid, $userName) {


        if ($idNote > 0) {

            // Update Existing note
            $note = new Note($idNote);
            $note->updateNote($dbh, $userName, $noteText);

        } else {

            // Create a new note.
            $note = Note::createNote($userName, $noteText);
            $note->save($dbh);

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
