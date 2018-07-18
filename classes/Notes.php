<?php
/**
 * Notes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Notes
 *
 * @author Eric
 */

class Note {

    private $idNote;
    private $noteRS;

    public function __construct($idNote) {
        $this->idNote = $idNote;
        $this->noteRS = new NoteRs();
    }

    protected function loadNote(\PDO $dbh) {

        if ($this->idNote > 0) {
            $noteRS = new NoteRs();
            $noteRS->idNote->setStoredVal($this->idNote);
            $rows = EditRS::select($dbh, $this->noteRS, array($this->noteRS->idNote));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $this->noteRS);
                return TRUE;
            }
        }

        return FALSE;
    }


    public function createNote(\PDO $dbh, $username, $noteText, $category, $noteType ) {

        $this->noteRS = new NoteRs();
        $this->noteRS->User_Name->setNewVal($username);
        $this->noteRS->Note_Text->setNewVal($noteText);
        $this->noteRS->Note_Category->setNewVal($category);
        $this->noteRS->Note_Type->setNewVal($noteType);

        $this->idNote = EditRS::insert($dbh, $this->noteRS);

    }

    public function updateNote(\PDO $dbh, $username, $noteText, $category) {

        if ($this->loadNote($dbh)) {

            $this->noteRS->Updated_By->setNewVal($username);
        }
    }
}
class Notes {


    public static function markupShell($notesText, $taId, $txtboxRows = '1', $taClass = 'hhk-feeskeys') {

        if (is_null($notesText)) {
            $notesText = '';
        }

        $inputTa = HTMLContainer::generateMarkup(
                     'textarea',
                     '',
                     array('name'=>$taId, 'rows'=>$txtboxRows, 'class'=>$taClass)
                     );

        // reverse output
        $lines = explode("\n", $notesText);
        $reverse = "";

        for ($i = (count($lines) - 1); $i >= 0; $i--) {
            $reverse .= $lines[$i] . "<br/>";
        }

        $output = HTMLContainer::generateMarkup('div', $reverse, array('id'=>'hhk-existgNotes'));

        return HTMLContainer::generateMarkup('div', $output . $inputTa, array('class'=>'hhk-noteBox'));

    }


    public static function getNotesDiv($notesText, $class = 'hhk-currentNotes') {

        // reverse output
        $lines = explode("\n", $notesText);
        $reverse = "";

        for ($i = (count($lines) - 1); $i >= 0; $i--) {
            $reverse .= $lines[$i] . "<br/>";
        }


        return HTMLContainer::generateMarkup('div', $reverse, array('class'=>$class));
    }
}
