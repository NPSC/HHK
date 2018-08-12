<?php
/**
 * Note.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Note
 *
 * @author Eric
 */

class Note {

    const Reservation = 'reservation';
    const Visit = 'visit';
    const House = 'house';
    const PSG = 'psg';
    const Room = 'room';
    const Member = 'member';

    protected $idNote = 0;
    protected $text = '';
    protected $category = '';
    protected $title = '';
    protected $type = '';
    protected $userName = '';
    protected $status = '';
    protected $createdOn = null;
    protected $lastUpdated = null;
    protected $updatedBy = '';

    private $noteRS;

    /**
     *
     * @param int $idNote
     */
    public function __construct($idNote = 0) {

        $id = intval($idNote, 10);
        $this->idNote = $id;

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
            $rows = EditRS::select($dbh, $noteRS, array($noteRS->idNote));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $noteRS);

                $this->setCategory($noteRS->Note_Category->getStoredVal());
                $this->createdOn = $noteRS->Timestamp->getStoredVal();
                $this->setStatus($noteRS->Status->getStoredVal());
                $this->setText($noteRS->Note_Text->getStoredVal());
                $this->setTitle($noteRS->Title->getStoredVal());
                $this->setType($noteRS->Note_Type->getStoredVal());
                $this->setUserName($noteRS->User_Name->getStoredVal());
                $this->setUpdatedBy($noteRS->Updated_By->getstoredVal());
                $this->setLastUpdated($noteRS->Last_Updated->getStoredVal());

                $this->noteRS = $noteRS;

                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     *
     * @param string $userName
     * @param string $noteText
     * @param string $category
     * @param string $noteType
     * @param string $noteTitle
     */
    public static function createNote($userName, $noteText, $category = '', $noteType = NoteType::Text, $noteTitle = '' ) {

        if ($noteText != '' && $userName != '') {

            $note = new Note();

            $note->setText($noteText);
            $note->setCategory($category);
            $note->setType($noteType);
            $note->setTitle($noteTitle);
            $note->setUserName($userName);

            $note->setStatus(NoteStatus::Active);

        } else {
            throw new Hk_Exception_Runtime('Trying to create an invalid note.  ');
        }

        return $note;
    }

    public function save(\PDO $dbh) {

        if ($this->isValid()) {

            $noteRS = new NoteRs();
            $noteRS->User_Name->setNewVal($this->getUserName());
            $noteRS->Note_Text->setNewVal($this->getNoteText());
            $noteRS->Note_Category->setNewVal($this->getNoteCategory());
            $noteRS->Note_Type->setNewVal($this->getNoteType());
            $noteRS->Title->setNewVal($this->getNoteTitle());
            $noteRS->Status->setNewVal($this->getStatus());
            $noteRS->Last_Updated->setNewVal($this->getLastUpdated());
            $noteRS->Updated_By->setNewVal($this->getUpdatedBy());

            $this->idNote = EditRS::insert($dbh, $noteRS);
            $noteRS->idNote->setNewVal($this->idNote);
            EditRS::updateStoredVals($noteRS);

            $this->noteRS = $noteRS;

        } else {
            throw new Hk_Exception_Runtime('Trying to save an invalid note.  ');
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

    public function linkNote(\PDO $dbh, $idNote, $linkType, $linkId, $userName) {

        return 0;

        if ($linkId > 0 && $note->getIdNote() > 0) {

            $stmt = $dbh->query("Select count(*) from reservation_note where Note_Id = " . $note->getIdNote() . " and Reservation_Id = " . $rid);
            $rows = $stmt->fetchAll();

            if (count($rows) > 0 && $rows[0][0] == 0) {

                // add record
                $dbh->exec("insert into reservation_note (Reservation_Id, Note_Id) values ('$rid', '" . $note->getIdNote() . "');");

            }
        }

    }

    protected function isValid() {

        return TRUE;
    }

    public function getIdNote() {
        return $this->idNote;
    }

    public function getNoteText() {
        return $this->text;
    }

    public function getNoteCategory() {
        return $this->category;
    }

    public function getNoteType() {
        return $this->type;
    }

    public function getNoteTitle() {
        return $this->title;
    }

    public function getUserName() {
        return $this->userName;
    }

    public function getLastUpdated() {
        return $this->lastUpdated;
    }

    public function getUpdatedBy() {
        return $this->updatedBy;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getCreatedOn() {
        return $this->createdOn;
    }

    public function setText($text) {
        $this->text = $text;
    }

    public function setCategory($category) {
        $this->category = $category;
        return $this;
    }

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    public function setUserName($userName) {
        $this->userName = $userName;
        return $this;
    }

    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    public function setLastUpdated($lastUpdated) {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function setUpdatedBy($updatedBy) {
        $this->updatedBy = $updatedBy;
        return $this;
    }


}