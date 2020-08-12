<?php

namespace HHK\House\Resource;

use HHK\House\Room\Room;
use HHK\SysConst\{AttributeTypes, RoomType, VisitStatus};
use HHK\TableLog\RoomLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\{ResourceRS, RoomRS};
use HHK\Exception\RuntimeException;

/**
 * AbstractResource.php
 *
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of AbstractResource
 */

abstract class AbstractResource {

    /**
     *
     * @var ResourceRS
     */
    public $resourceRS;
    public $optGroup;
    protected $rooms = array();
    protected $currentOccupantsLoaded = FALSE;
    protected $merchant = '';

    //protected $roomOcc = array();

    public function __construct(\PDO $dbh, $idResource = 0, $resourceRecord = NULL, $loadCurrentOccupants = FALSE) {

        if (is_null($resourceRecord)) {
            $this->resourceRS = $this->loadResourceRS($dbh, $idResource);
        } else {
            $this->resourceRS = $resourceRecord;
        }

        if ($this->isNewResource() === FALSE) {

            $this->rooms = $this->loadRooms($dbh, $this->getIdResource());

            // Flag to load current occupants.
            if ($loadCurrentOccupants) {
                $this->loadCurrentOccupants($dbh);
            }
        }
    }

    public function loadRooms(\PDO $dbh, $idResource) {

        $rooms = array();

         // Load rooms if not a new resource
        $stmt = $dbh->prepare("select r.*, ifnull(l.Merchant, '') as `Merchant`
from resource_room rr join room r on rr.idRoom = r.idRoom
left join location l on r.idLocation = l.idLocation
where rr.idResource = :idr
order by r.Util_Priority;", array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));

        $stmt->execute(array(':idr' => $idResource));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $r) {

            $roomRs = new RoomRs();
            EditRS::loadRow($r, $roomRs);
            $rm = new Room($dbh, 0, $roomRs, RoomType::Room, $r['Merchant']);

            $rooms[$r['idRoom']] = $rm;
            $this->merchant = $r['Merchant'];
        }

        return $rooms;

    }

    public function saveRooms(\PDO $dbh, $roomId) {

        $id = intval($roomId);

        if ($id < 1 || $this->getIdResource() < 1) {
            return;
        }

        $rooms = $this->getRooms();

        // set the room priority
        $dbh->query("update room set Util_Priority = '" . $this->getUtilPriority() . "' where idRoom = $id");

        if (count($rooms) == 0) {
            // add room
            $dbh->query("insert into resource_room (idResource, idRoom) values (" . $this->getIdResource() . "," . $id . ")");

        } else if (isset($rooms[$id]) === FALSE) {
            // update to new room id.
            $dbh->query("update resource_room set idRoom = " . $id . " where idResource = " . $this->getIdResource());

        } else {
            return;
        }

        unset($this->rooms);
        $this->rooms = $this->loadRooms($dbh, $this->getIdResource());
    }

    public static function getThisFromRS(\PDO $dbh, ResourceRS $resRS, $loadCurrentOccupants = FALSE) {

        $t = $resRS->Type->getStoredVal();
        switch ($t) {
            case ResourceTypes::Partition:
                return new PartitionResource($dbh, 0, $resRS, $loadCurrentOccupants);
                break;

            case ResourceTypes::Room:
                return new RoomResource($dbh, 0, $resRS, $loadCurrentOccupants);
                break;

            case ResourceTypes::RmtRoom:
                return new RemoteResource($dbh, 0, $resRS, $loadCurrentOccupants);
                break;

//            case ResourceTypes::Block:
//                return new BlockResource($dbh, 0, $resRS, $loadCurrentOccupants);
//                break;

            default:
                break;
        }
        return NULL;
    }

    public static function getResourceObj(\PDO $dbh, $idResource, $defaultResourceType = ResourceTypes::Room, $loadCurrentOccupants = FALSE) {

        $resRS = AbstractResource::loadResourceRS($dbh, $idResource);

        if ($resRS->Type->getStoredVal() == '') {
            $resRS->Type->setStoredVal($defaultResourceType);
        }

        return self::getThisFromRS($dbh, $resRS, $loadCurrentOccupants);

    }

    protected static function loadResourceRS(\PDO $dbh, $idResource) {
        $nRS = new ResourceRS();

        if ($idResource > 0) {

            $nRS->idResource->setStoredVal($idResource);
            $rows = EditRS::select($dbh, $nRS, array($nRS->idResource));

            if (count($rows) == 1) {

                EditRS::loadRow($rows[0], $nRS);
            } else {
                // Error, id > 0 and no record
                throw new RuntimeException("There is no record for Resource id = $idResource");
            }
        }
        return $nRS;
    }

    public function getBeds() {
        $beds = new Beds();
        foreach ($this->rooms as $rm) {

            $beds->fullQty += $rm->roomRS->Beds_Full->getStoredVal();
            $beds->kingQty += $rm->roomRS->Beds_King->getStoredVal();
            $beds->twinQty += $rm->roomRS->Beds_Twin->getStoredVal();
            $beds->queenQty += $rm->roomRS->Beds_Queen->getStoredVal();
        }
        return $beds;
    }

    public function getMaxOccupants() {
        // Look for other resources sharing my rooms
        $occp = 0;
        foreach ($this->rooms as $rm) {
            $occp += $rm->getMaxOccupants();
        }
        return $occp;
    }

    public function getVisitFee($visitFeeCodes) {
        // Look for other resources sharing my rooms
        $rate = 0;
        foreach ($this->rooms as $rm) {
            if (isset($visitFeeCodes[$rm->getVisitFeeCode()])) {
                $rate += $visitFeeCodes[$rm->getVisitFeeCode()][2];
            }
        }
        return $rate;
    }

    public function isNewResource() {
        if ($this->getIdResource() == 0) {
            return TRUE;
        }

        return FALSE;
    }

    public function saveRecord(\PDO $dbh, $username = "") {
        // Doesn't save any linked rooms

        $this->resourceRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $this->resourceRS->Updated_By->setNewVal($username);

        if ($this->isNewResource($this->resourceRS)) {

            $idResc = EditRS::insert($dbh, $this->resourceRS);

            if ($idResc > 0) {
                $logText = RoomLog::getInsertText($this->resourceRS);
                RoomLog::logResource($dbh, $idResc, $logText, "insert", $username);
            }

            $this->resourceRS->idResource->setNewVal($idResc);

        } else {

            $num = EditRS::update($dbh, $this->resourceRS, array($this->resourceRS->idResource));

            if ($num > 0) {
                $logText = RoomLog::getUpdateText($this->resourceRS);
                RoomLog::logResource($dbh, $this->getIdResource(), $logText, "update", $username);
            }
        }

        EditRS::updateStoredVals($this->resourceRS);
        return;
    }

    public function deleteResource(\PDO $dbh, $username) {

        $query = "delete from resource_room where idResource = :id";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':id'=>$this->getIdResource()));

        // Delete from attribure entities
        $query = "delete from attribute_entity where idEntity = :id and Type = :tpe";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':id' => $this->getIdResource(), ':tpe' => AttributeTypes::Resource));

        $cnt = EditRS::delete($dbh, $this->resourceRS, array($this->resourceRS->idResource));

        if ($cnt) {
            $logText = RoomLog::getDeleteText($this->resourceRS, $this->getIdResource());
            RoomLog::logResource($dbh, $this->getIdResource(), $logText, "delete", $username);

            $this->resourceRS = new ResourceRS();
        }

        return $cnt;
    }

    public function getTitle() {
        return htmlspecialchars_decode($this->resourceRS->Title->getStoredVal(), ENT_QUOTES);
    }

    public function getUtilizationCategory() {
        return $this->resourceRS->Utilization_Category->getStoredVal();
    }

    public function setUtilizationCategory($v) {
        $this->resourceRS->Utilization_Category->setNewVal($v);
        return $this;
    }

    public function getRateAdjCode() {
        return $this->resourceRS->Rate_Adjust_Code->getStoredVal();
    }

    public function getMerchant() {
        return $this->merchant;
    }

    public function getRate($rateCodes = array()) {
        // computed from room availablity and inService
        $rate = 0.0;
        foreach ($this->rooms as $rm) {
            if (isset($rateCodes[$rm->getRateCode()])) {
                $rate += $rateCodes[$rm->getRateCode()][2];
            }
        }
        return $rate;
    }

    public function getKeyDeposit($rateCodes = array()) {
        $rate = 0.0;
        foreach ($this->rooms as $rm) {
            if (isset($rateCodes[$rm->getKeyDepositCode()])) {
                $rate += $rateCodes[$rm->getKeyDepositCode()][2];
            } else {
                $rate += $rm->getKeyDeposit();
            }
        }
        return $rate;
    }

    public function getType() {
        return $this->resourceRS->Type->getStoredVal();
    }

    public function getCategory() {
        return $this->resourceRS->Category->getStoredVal();
    }

    public function getUtilPriority() {
        return $this->resourceRS->Util_Priority->getStoredVal();
    }

    public function getIdResource() {
        return $this->resourceRS->idResource->getStoredVal();
    }

    public function getIdSponsor() {
        return $this->resourceRS->idSponsor->getStoredVal();
    }

    public function setIdSponsor($id) {
        $this->resourceRS->idSponsor->setNewVal($id);
    }

    public abstract function allocateRoom($numGuests, $overRideMax = FALSE);

    public abstract function testAllocateRoom($numGuests);

    public function loadCurrentOccupants(\PDO $dbh) {

        // Reset occupants counters
        foreach ($this->rooms as $room) {
            $room->setCurrentOcc(0);
        }

        // Get fresh counters
        $stmt = $dbh->prepare("select r.idRoom, count(s.idStays)  as `Stays`
from resource_room rr join room r on rr.idRoom = r.idRoom left join stays s on r.idRoom = s.idRoom and s.Status = :stat
where rr.idResource = :idr group by rr.idRoom;", array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));

        $stmt->execute(array(':idr' => $this->getIdResource(), ':stat' => VisitStatus::CheckedIn));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $r) {

            if (isset($this->rooms[$r['idRoom']])) {
                $room = $this->rooms[$r['idRoom']];
                $room->setCurrentOcc($r['Stays']);
            }
        }

        $this->currentOccupantsLoaded = TRUE;

    }

    public function getCurrantOccupants($dbh = null) {

        if ($this->isNewResource()) {
            return 0;
        }

        if ($this->currentOccupantsLoaded === FALSE) {

            if (is_null($dbh)) {
                throw new RuntimeException("Room current occupants not loaded.  ");
            }

            $this->loadCurrentOccupants($dbh);
        }

        $occp = 0;
        foreach ($this->rooms as $rm) {
            $occp += $rm->getCurrentOcc();
        }

        return $occp;
    }

    public function getRooms() {
        return $this->rooms;
    }

}
?>