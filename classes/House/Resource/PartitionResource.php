<?php

namespace HHK\House\Resource;

use HHK\Exception\RuntimeException;
use HHK\SysConst\VisitStatus;
use HHK\House\Room\Room;

/**
 * PartitionResource.php
 *
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PartitionResource
 */

class PartitionResource extends AbstractResource {

    protected $currentOccupants;

    public function getBeds() {
        $beds = new Beds();

        return $beds;
    }

    public function testAllocateRoom($numGuests, $overRideMax = FALSE) {

        if ($this->isNewResource()) {
            throw new RuntimeException('Allocating rooms in an invalid Resource at Resource->allocateRoom.');
        }

        $room = reset($this->rooms);

        if ($room instanceof Room === FALSE) {
            return FALSE;
        }

        $leftOver = $room->getMaxOccupants() - $room->getCurrentOcc();

        if (($numGuests <= $this->getMaxOccupants() && $leftOver >= $numGuests) || $overRideMax) {
            return TRUE;
        }

        return FALSE;
    }


    public function allocateRoom($numGuests, $overRideMax = FALSE) {

        if ($this->isNewResource()) {
            throw new RuntimeException('Allocating rooms in an invalid Resource at Resource->allocateRoom.');
        }

        $room = reset($this->rooms);

        if ($room instanceof Room === FALSE) {
            return NULL;
        }

        $leftOver = $room->getMaxOccupants() - $room->getCurrentOcc();

        if (($numGuests <= $this->getMaxOccupants() && $leftOver >= $numGuests) || $overRideMax) {
            $room->setCurrentOcc($room->getCurrentOcc() + $numGuests);
            return $room;
        }

        return NULL;
    }


    public function getMaxOccupants() {
        return $this->resourceRS->Partition_Size->getStoredVal();
    }

    public function getCurrantOccupants($dbh = NULL) {


        if ($this->isNewResource() === FALSE && is_null($this->currentOccupants)) {
            $stmt = $dbh->prepare("SELECT COUNT(`s`.`idStays`) FROM `visit` `v` LEFT JOIN `stays` `s` ON `v`.`idVisit` = `s`.`idVisit` AND `s`.`Status` = :status1
                    WHERE `v`.`idResource` = :idResource AND `v`.`Status` = :status2");
            $stmt->execute([':status1' => VisitStatus::CheckedIn, ':idResource' => $this->getIdResource(), ':status2' => VisitStatus::CheckedIn]);
            $rows = $stmt->fetchAll();
            $this->currentOccupants = $rows[0][0];
        }

        return $this->currentOccupants;
    }

}
?>