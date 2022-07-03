<?php

namespace HHK\Note;

use HHK\Exception\RuntimeException;
use HHK\Tables\EditRS;
use HHK\Tables\Registration\NoteRS;

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

    // Link Type
    const ResvLink = 'reservation';
    const VisitLink = 'visit';
    const HouseLink = 'house';
    const PsgLink = 'psg';
    const RoomLink = 'room';
    const MemberLink = 'member';
    const DocumentLink = 'document';
    const StaffLink = 'staff';

    // Note Type
    const TextType = 'text';

    // Note Ststus
    const ActiveStatus = 'a';
    const DeletedStatus = 'd';


    // Note record field vars
    protected $idNote = 0;
    protected $text = '';
    protected $title = '';
    protected $type = '';
    protected $flag = false;
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

        $response = TRUE;

        if ($this->idNote > 0) {

            $noteRS = new NoteRS();
            $noteRS->idNote->setStoredVal($this->idNote);
            $rows = EditRS::select($dbh, $noteRS, array($noteRS->idNote));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $noteRS);

                $this->createdOn = $noteRS->Timestamp->getStoredVal();
                $this->setStatus($noteRS->Status->getStoredVal());
                $this->setText($noteRS->Note_Text->getStoredVal());
                $this->setTitle($noteRS->Title->getStoredVal());
                $this->setType($noteRS->Note_Type->getStoredVal());
                $this->setFlag($noteRS->Flag->getStoredVal());
                $this->setUserName($noteRS->User_Name->getStoredVal());
                $this->setUpdatedBy($noteRS->Updated_By->getstoredVal());
                $this->setLastUpdated($noteRS->Last_Updated->getStoredVal());

                $this->noteRS = $noteRS;

            } else {
                $response =  FALSE;
            }
        }

        return $response;
    }

    /**
     *
     * @param string $userName
     * @param string $noteText
     * @param string $noteType
     * @param string $noteTitle
     */
    public static function createNew($noteText, $userName, $noteType = self::TextType, $noteTitle = '', $noteStatus = Note::ActiveStatus ) {

        if ($noteText != '' && $userName != '') {

            $note = new Note();

            $note->setText($noteText);
            $note->setType($noteType);
            $note->setFlag(0);
            $note->setTitle($noteTitle);
            $note->setUserName($userName);
            $note->setStatus($noteStatus);
            $note->idNote = 0;

        } else {
            throw new RuntimeException('Trying to create an invalid note.  ');
        }

        return $note;
    }

    public function saveNew(\PDO $dbh) {

        if ($this->isValid() === FALSE) {
            return FALSE;
        }

        // Insert
        $noteRS = new NoteRs();
        $noteRS->User_Name->setNewVal($this->getUserName());
        $noteRS->Note_Text->setNewVal($this->getNoteText());
        $noteRS->Note_Type->setNewVal($this->getNoteType());
        $noteRS->Flag->setNewVal($this->getFlag());
        $noteRS->Title->setNewVal($this->getNoteTitle());
        $noteRS->Status->setNewVal($this->getStatus());
        $noteRS->Last_Updated->setNewVal($this->getLastUpdated());
        $noteRS->Updated_By->setNewVal($this->getUpdatedBy());

        $this->idNote = EditRS::insert($dbh, $noteRS);
        $noteRS->idNote->setNewVal($this->idNote);
        EditRS::updateStoredVals($noteRS);

        $this->noteRS = $noteRS;

    }

    /**
     *
     * @param \PDO $dbh
     * @param string $noteText
     * @param string $updatedBy
     * @return int the number of records updated.
     */
    public function updateContents(\PDO $dbh, $noteText, $updatedBy) {

        $counter = 0;

        if ($this->getIdNote() > 0 && $this->loadNote($dbh)) {

            $this->noteRS->Note_Text->setNewVal($noteText);
            $this->noteRS->Status->setNewVal(self::ActiveStatus);
            $this->noteRS->Updated_By->setNewVal($updatedBy);
            $this->noteRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->noteRS, array($this->noteRS->idNote));
            EditRS::updateStoredVals($this->noteRS);
        }

        return $counter;
    }

    public function saveTitle(\PDO $dbh, $title) {

        $counter = 0;

        if ($this->getIdNote() > 0 && $this->loadNote($dbh)) {

            $this->noteRS->Title->setNewVal($title);

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

        if ($this->getIdNote() > 0 && $this->loadNote($dbh)) {

            $this->noteRS->Status->setNewVal(self::DeletedStatus);
            $this->noteRS->Updated_By->setNewVal($username);
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
    public function undoDeleteNote(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->getIdNote() > 0 && $this->loadNote($dbh)) {

            $this->noteRS->Status->setNewVal(self::ActiveStatus);
            $this->noteRS->Updated_By->setNewVal($username);
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
    public function flagNote(\PDO $dbh, $flag, $username) {

        $counter = 0;

        if ($this->getIdNote() > 0 && $this->loadNote($dbh)) {

            $this->noteRS->Flag->setNewVal($flag);
            if($flag){
	            $this->noteRS->Status->setNewVal(self::ActiveStatus);
            }
            $this->noteRS->Updated_By->setNewVal($username);
            $this->noteRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->noteRS, array($this->noteRS->idNote));
            EditRS::updateStoredVals($this->noteRS);

        }

        return $counter;
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

    public function getNoteType() {
        return $this->type;
    }

    public function getFlag() {
        return $this->flag;
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

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    public function setFlag($flag) {
        $this->flag = $flag;
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