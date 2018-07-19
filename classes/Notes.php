<?php
/**
 * Notes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Note
 *
 * @author Eric
 */

class Note {

    private $idNote;
    private $noteRS;

    /**
     *
     * @param int $idNote
     */
    public function __construct($idNote) {
        $this->idNote = $idNote;
        $this->noteRS = new NoteRs();

    }

    /**
     *
     * @param \PDO $dbh
     * @return boolean
     */
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

    /**
     *
     * @param \PDO $dbh
     * @param string $username
     * @param string $noteText
     * @param string $category
     * @param string $noteType
     */
    public function createNote(\PDO $dbh, $username, $noteText, $category = NoteCategory::Reservation, $noteType = NoteType::Text ) {

        if ($noteText != '' && $username != '') {
            $this->noteRS = new NoteRs();
            $this->noteRS->User_Name->setNewVal($username);
            $this->noteRS->Note_Text->setNewVal($noteText);
            $this->noteRS->Note_Category->setNewVal($category);
            $this->noteRS->Note_Type->setNewVal($noteType);
            $this->noteRS->Status->setNewVal(NoteStatus::Active);

            $this->idNote = EditRS::insert($dbh, $this->noteRS);
            $this->noteRS->idNote->setNewVal($this->idNote);
            EditRS::updateStoredVals($this->noteRS);
        }

    }

    /**
     *
     * @param \PDO $dbh
     * @param string $updatedBy
     * @param string $noteText
     * @param string $category
     * @return int the number of records updated.
     */
    public function updateNote(\PDO $dbh, $updatedBy, $noteText, $category = '') {

        $counter = 0;

        if ($this->loadNote($dbh)) {

            if ($category != '') {
                $this->noteRS->Note_Category->setNewVal($category);
            }
            $this->noteRS->Note_Text->setNewVal($noteText);
            $this->noteRS->Status->setNewVal(NoteStatus::Active);
            $this->noteRS->Updated_By->setNewVal($updatedBy);
            $this->noteRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->noteRS, array($this->noteRS->idNote));
            EditRS::updateStoredVals($this->noteRS);
        }

        return $counter;
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $username
     * @return int the number of rows affected
     */
    public function deleteNote(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->loadNote($dbh)) {

            $this->noteRS->Status->setNewVal(NoteStatus::Deleted);
            $this->noteRS->Updated_By->setNewVal($username);
            $this->noteRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->noteRS, array($this->noteRS->idNote));
            EditRS::updateStoredVals($this->noteRS);

        }

        return $counter;
    }

    public function getIdNote() {
        return $this->idNote;
    }

    public function getNoteRS() {
        return $this->noteRS;
    }

    public function getNoteText() {
        return $this->noteRS->Note_Text->getStoredVal();
    }

    public function getNoteCategory() {
        return $this->noteRS->Note_Category->getStoredVal();
    }

    public function getNoteType() {
        return $this->noteRS->Note_Type->getStoredVal();
    }

    public function getUserName() {
        return $this->noteRS->User_Name->getStoredVal();
    }

    public function getLastUpdated() {
        return $this->noteRS->Last_Updated->getStoredVal();
    }

    public function getUpdatedBy() {
        return $this->noteRS->Updated_By->getStoredVal();
    }

    public function getNoteStatus() {
        return $this->noteRS->Status->getStoredVal();
    }

    public function getNoteTimestamp() {
        return $this->noteRS->Timestamp->getStoredVal();
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
