<?php
/**
 * Room.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Room
 * @author Eric
 */
class Room {

    /**
     *
     * @var RoomRs
     */
    public $roomRS;

    protected $currOccupants = 0;
    protected $idRoom;

    public function __construct(PDO $dbh, $idRoom = 0, $roomRecordSource = NULL, $roomType = RoomType::Room) {

        if (is_null($roomRecordSource)) {

            $this->loadRoomRS($dbh, $idRoom, $roomType);

        } else {

            $this->roomRS = $roomRecordSource;

        }

        $this->idRoom = $this->getIdRoom();

    }

    protected function loadRoomRS(PDO $dbh, $idRoom, $roomType = RoomType::Room) {

        $nRS = new RoomRS();

        if ($idRoom > 0) {

            $nRS->idRoom->setStoredVal($idRoom);
            $rows = EditRS::select($dbh, $nRS, array($nRS->idRoom));

            if (count($rows) == 1) {

                EditRS::loadRow($rows[0], $nRS);

            } else {
                // Error, id > 0 and no record
                throw new Hk_Exception_Runtime("There is no record for Room id = $idRoom");
            }
        } else {
            $nRS->Type->setNewVal($roomType);
        }

        $this->roomRS = $nRS;

        return $nRS;
    }

    public function getTitle() {
        return $this->roomRS->Title->getStoredVal();
    }

    public function getType() {
        return $this->roomRS->Type->getStoredVal();
    }

    public function getRoomCategory() {
        return $this->roomRS->Category->getStoredVal();
    }

    public function getRate() {
        return $this->roomRS->Rate->getStoredVal();
    }

    public function getRateCode() {
        return $this->roomRS->Rate_Code->getStoredVal();
    }

    public function getKeyDeposit() {
        return $this->roomRS->Key_Deposit->getStoredVal();
    }


    public function getKeyDepositCode() {
        return $this->roomRS->Key_Deposit_Code->getStoredVal();
    }

    public function getVisitFeeCode() {
        return $this->roomRS->Visit_Fee_Code->getStoredVal();
    }

    public function getIdRoom() {
        return $this->roomRS->idRoom->getStoredVal();
    }

    public function getRoomRS() {
        return $this->roomRS;
    }

    public function getMaxOccupants() {
        return $this->roomRS->Max_Occupants->getStoredVal();
    }

    public function isClean() {
        return $this->roomRS->Status->getStoredVal() == RoomState::Clean;
    }

    public function putDirty() {

        $this->setStatus(RoomState::Dirty);
        return;
    }

    public function putTurnOver() {

        $this->setStatus(RoomState::TurnOver);
        return;
    }

    public function putClean($date = '') {

        $this->setStatus(RoomState::Clean);

        if ($date == ''){
            $date = date('Y-m-d H:i:s');
        } else {
            $date = date('Y-m-d H:i:s', strtotime($date));
        }

        $this->roomRS->Last_Cleaned->setNewVal($date);

        return;
    }

    public function setStatus($roomState) {

        $this->roomRS->Status->setNewVal($roomState);
        return;
    }

    public function getStatus() {
        return $this->roomRS->Status->getStoredVal();
    }

    public function setCurrentOcc($val) {
        $this->currOccupants = $val;
    }

    public function getCurrentOcc() {
        return $this->currOccupants;
    }

    public function getNotes() {
        return $this->roomRS->Notes->getStoredVal();
    }

    public function setNotes($v) {
        $this->roomRS->Notes->setNewVal($v);
    }

    public function getCleaningCycleCode() {
        return $this->roomRS->Cleaning_Cycle_Code->getStoredVal();
    }

    public function setCleaningCycleCode($v) {
        $this->roomRS->Cleaning_Cycle_Code->setNewVal($v);
        return $this;
    }


    public function deleteRoom(PDO $dbh, $username) {

        $stmt1 = $dbh->prepare("delete from resource_room where idRoom = :id");
        $stmt1->execute(array(':id' => $this->getIdRoom()));

        $stmt = $dbh->prepare("delete from attribute_entity where idEntity = :id and Type = :tpe");
        $stmt->execute(array(':id' => $this->getIdRoom(), ':tpe' => Attribute_Types::Room));

        if (EditRS::delete($dbh, $this->roomRS, array($this->roomRS->idRoom))) {
            $logText = RoomLog::getDeleteText($this->roomRS, $this->getIdRoom());
            RoomLog::logRoom($dbh, $this->roomRS->idRoom->getStoredVal(), $logText, "delete", $username);

            $this->roomRS = new RoomRs();
            return true;
        }

        return false;
    }


    public function saveRoom(\PDO $dbh, $username, $cleaning = FALSE, $cleanType = '') {

        $this->roomRS->Last_Updated->setNewVal(date("y-m-d H:i:s"));
        $this->roomRS->Updated_By->setNewVal($username);

        if ($this->roomRS->idRoom->getStoredVal() > 0) {
            // update
            $num = EditRS::update($dbh, $this->roomRS, array($this->roomRS->idRoom));

            if ($num > 0) {

                EditRS::updateStoredVals($this->roomRS);

                if ($cleaning) {
                    RoomLog::logCleaning($dbh, 0, $this->roomRS->idRoom->getStoredVal(), $cleanType, $this->roomRS->Status->getStoredVal(), $this->roomRS->Notes->getStoredVal(), $this->roomRS->Last_Cleaned->getStoredVal(), $username);
                } else {
                    $logText = RoomLog::getUpdateText($this->roomRS);
                    RoomLog::logRoom($dbh, $this->roomRS->idRoom->getStoredVal(), $logText, "update", $username);
                }

            }
        } else {
            // insert
            $idRoom = EditRS::insert($dbh, $this->roomRS);

            if ($idRoom > 0) {

                $logText = RoomLog::getInsertText($this->roomRS);
                RoomLog::logRoom($dbh, $idRoom, $logText, "insert", $username);

                $this->roomRS->idRoom->setNewVal($idRoom);
                EditRS::updateStoredVals($this->roomRS);
                $this->idRoom = $idRoom;

            }
        }
    }

}


