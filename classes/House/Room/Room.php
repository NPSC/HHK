<?php

namespace HHK\House\Room;

use HHK\SysConst\{RoomState, RoomType};
use HHK\TableLog\RoomLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\RoomRS;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;

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

    /**
     * Summary of currOccupants
     * @var int
     */
    protected $currOccupants = 0;
    /**
     * Summary of idRoom
     * @var int
     */
    protected $idRoom;
    /**
     * Summary of merchant
     * @var string
     */
    protected $merchant;

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param int $idRoom
     * @param mixed $roomRecordSource
     * @param string $roomType
     * @param string $merchant
     */
    public function __construct(\PDO $dbh, $idRoom = 0, $roomRecordSource = NULL, $roomType = RoomType::Room, $merchant = '') {

        if (is_null($roomRecordSource)) {

            $this->loadRoomRS($dbh, $idRoom, $roomType);

        } else {

            $this->roomRS = $roomRecordSource;

        }

        $this->idRoom = $this->getIdRoom();
        $this->merchant = $merchant;

    }

    /**
     * Summary of loadRoomRS
     * @param \PDO $dbh
     * @param int $idRoom
     * @param string $roomType
     * @throws \HHK\Exception\RuntimeException
     * @return RoomRS
     */
    protected function loadRoomRS(\PDO $dbh, $idRoom, $roomType = RoomType::Room) {

        $nRS = new RoomRS();

        if ($idRoom > 0) {

            $nRS->idRoom->setStoredVal($idRoom);
            $rows = EditRS::select($dbh, $nRS, array($nRS->idRoom));

            if (count($rows) == 1) {

                EditRS::loadRow($rows[0], $nRS);

            } else {
                // Error, id > 0 and no record
                throw new RuntimeException("There is no record for Room id = $idRoom");
            }
        } else {
            $nRS->Type->setNewVal($roomType);
        }

        $this->roomRS = $nRS;

        return $nRS;
    }

    /**
     * Summary of getTitle
     * @return string
     */
    public function getTitle() {
        return htmlspecialchars_decode($this->roomRS->Title->getStoredVal(), ENT_QUOTES);
    }

    /**
     * Summary of getType
     * @return mixed
     */
    public function getType() {
        return $this->roomRS->Type->getStoredVal();
    }

    /**
     * Summary of getRoomCategory
     * @return mixed
     */
    public function getRoomCategory() {
        return $this->roomRS->Category->getStoredVal();
    }

    /**
     * Summary of getReportCategory
     * @return mixed
     */
    public function getReportCategory() {
        return $this->roomRS->Report_Category->getStoredVal();
    }

    /**
     * Summary of getDefaultRateCategory
     * @return mixed
     */
    public function getDefaultRateCategory() {
        return $this->roomRS->Default_Rate_Category->getStoredVal();
    }

    /**
     * Summary of getRateCode
     * @return mixed
     */
    public function getRateCode() {
        return $this->roomRS->Rate_Code->getStoredVal();
    }

    /**
     * Summary of getKeyDeposit
     * @return mixed
     */
    public function getKeyDeposit() {
        return $this->roomRS->Key_Deposit->getStoredVal();
    }


    /**
     * Summary of getKeyDepositCode
     * @return mixed
     */
    public function getKeyDepositCode() {
        return $this->roomRS->Key_Deposit_Code->getStoredVal();
    }

    /**
     * Summary of getVisitFeeCode
     * @return mixed
     */
    public function getVisitFeeCode() {
        return $this->roomRS->Visit_Fee_Code->getStoredVal();
    }

    /**
     * Summary of getIdRoom
     * @return mixed
     */
    public function getIdRoom() {
        return $this->roomRS->idRoom->getStoredVal();
    }

    /**
     * Summary of getMerchant
     * @return mixed
     */
    public function getMerchant() {
        return $this->merchant;
    }

    /**
     * Summary of getIdLocation
     * @return mixed
     */
    public function getIdLocation() {
        return $this->roomRS->idLocation->getStoredVal();
    }

    /**
     * Summary of getRoomRS
     * @return RoomRS
     */
    public function getRoomRS() {
        return $this->roomRS;
    }

    /**
     * Summary of getMaxOccupants
     * @return mixed
     */
    public function getMaxOccupants() {
        return $this->roomRS->Max_Occupants->getStoredVal();
    }

    /**
     * Summary of isClean
     * @return bool
     */
    public function isClean() {
        return $this->roomRS->Status->getStoredVal() == RoomState::Clean;
    }

    /**
     * Summary of isReady
     * @return bool
     */
    public function isReady() {
        return $this->roomRS->Status->getStoredVal() == RoomState::Ready;
    }

    /**
     * Summary of putDirty
     * @return bool
     */
    public function putDirty() {

        $this->setStatus(RoomState::Dirty);
        return TRUE;
    }

    /**
     * Summary of putTurnOver
     * @return bool
     */
    public function putTurnOver() {

        $this->setStatus(RoomState::TurnOver);
        return TRUE;
    }

    /**
     * Summary of putReady
     * @return bool
     */
    public function putReady() {

        if ($this->getStatus() == RoomState::Clean) {

            $this->setStatus(RoomState::Ready);
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Summary of putClean
     * @param string $date
     * @return bool
     */
    public function putClean($date = '') {

        $this->setStatus(RoomState::Clean);

        if ($date == ''){
            $date = date('Y-m-d H:i:s');
        } else {
            $date = date('Y-m-d H:i:s', strtotime($date));
        }

        $this->roomRS->Last_Cleaned->setNewVal($date);

        return TRUE;
    }

    /**
     * Summary of setCleanStatus
     * @param string $stat
     * @return mixed
     */
    public function setCleanStatus($stat) {

        $uS = Session::getInstance();
        $response = TRUE;

        switch ($stat) {

            case RoomState::Dirty:
                $response = $this->putDirty();
                break;

            case RoomState::Clean:
                $response = $this->putClean();
                break;

            case RoomState::Ready:
                if ($uS->HouseKeepingSteps > 1) {
                    $response = $this->putReady();
                }
                break;

            case RoomState::TurnOver:
                $response = $this->putTurnOver();
                break;

            default:
                $response = FALSE;

        }

        return $response;
    }

    /**
     * Summary of setLastDeepCleanDate
     * @param mixed $date
     * @return void
     */
    public function setLastDeepCleanDate($date = ''){
        $this->roomRS->Last_Deep_Clean->setNewVal($date);
    }

    /**
     * Summary of setStatus
     * @param mixed $roomState
     * @return void
     */
    public function setStatus($roomState) {

        $this->roomRS->Status->setNewVal($roomState);
        return;
    }

    /**
     * Summary of getStatus
     * @return mixed
     */
    public function getStatus() {
        return $this->roomRS->Status->getStoredVal();
    }

    /**
     * Summary of setCurrentOcc
     * @param mixed $val
     * @return void
     */
    public function setCurrentOcc($val) {
        $this->currOccupants = $val;
    }

    /**
     * Summary of getCurrentOcc
     * @return mixed
     */
    public function getCurrentOcc() {
        return $this->currOccupants;
    }

    /**
     * Summary of getNotes
     * @return mixed
     */
    public function getNotes() {
        return $this->roomRS->Notes->getStoredVal();
    }

    /**
     * Summary of setNotes
     * @param mixed $v
     * @return void
     */
    public function setNotes($v) {
        $this->roomRS->Notes->setNewVal($v);
    }

    /**
     * Summary of getCleaningCycleCode
     * @return mixed
     */
    public function getCleaningCycleCode() {
        return $this->roomRS->Cleaning_Cycle_Code->getStoredVal();
    }

    /**
     * Summary of setCleaningCycleCode
     * @param mixed $v
     * @return Room
     */
    public function setCleaningCycleCode($v) {
        $this->roomRS->Cleaning_Cycle_Code->setNewVal($v);
        return $this;
    }


    /**
     * Summary of deleteRoom
     * @param \PDO $dbh
     * @param mixed $username
     * @return bool
     */
    public function deleteRoom(\PDO $dbh, $username) {

//        $stmt1 = $dbh->prepare("delete from resource_room where idRoom = :id");
//        $stmt1->execute(array(':id' => $this->getIdRoom()));
//
//        $stmt = $dbh->prepare("delete from attribute_entity where idEntity = :id and Type = :tpe");
//        $stmt->execute(array(':id' => $this->getIdRoom(), ':tpe' => Attribute_Types::Room));
//
//        if (EditRS::delete($dbh, $this->roomRS, array($this->roomRS->idRoom))) {
//            $logText = RoomLog::getDeleteText($this->roomRS, $this->getIdRoom());
//            RoomLog::logRoom($dbh, $this->roomRS->idRoom->getStoredVal(), $logText, "delete", $username);
//
//            $this->roomRS = new RoomRs();
//            return true;
//        }

        return false;
    }


    /**
     * Summary of saveRoom
     * @param \PDO $dbh
     * @param string $username
     * @param bool $cleaning
     * @param string $cleanType
     * @return void
     */
    public function saveRoom(\PDO $dbh, $username, $cleaning = FALSE, $cleanType = '') {

        $this->roomRS->Last_Updated->setNewVal(date("y-m-d H:i:s"));
        $this->roomRS->Updated_By->setNewVal($username);

        if ($this->roomRS->idRoom->getStoredVal() > 0) {
            // update
            $num = EditRS::update($dbh, $this->roomRS, array($this->roomRS->idRoom));

            if ($num > 0) {
                EditRS::updateStoredVals($this->roomRS);
                if ($cleaning) {
                    RoomLog::logCleaning($dbh, 0, $this->roomRS->idRoom->getStoredVal(), $cleanType, $this->roomRS->Status->getStoredVal(), $this->roomRS->Notes->getStoredVal(), $this->roomRS->Last_Cleaned->getStoredVal(), $this->roomRS->Last_Deep_Clean->getStoredVal(), $username);
                } else {
                    $logText = RoomLog::getUpdateText($this->roomRS);
                    RoomLog::logRoom($dbh, $this->roomRS->idRoom->getStoredVal(), $logText, "update", $username);
                }

            }
        } else {
            // insert
            $this->roomRS->Status->setNewVal(RoomState::TurnOver);
            $this->roomRS->State->setNewVal('a');
            $this->roomRS->Availability->setNewVal('a');
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
?>