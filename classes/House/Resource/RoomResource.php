<?php

namespace HHK\House\Resource;

use HHK\Exception\RuntimeException;
use HHK\House\Room\Room;

/**
 * RoomResource.php
 *
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoomResource
 */

class RoomResource extends AbstractResource {

    /**
     * Summary of testAllocateRoom
     * @param int $numGuests
     * @throws \HHK\Exception\RuntimeException
     * @return bool
     */
    public function testAllocateRoom($numGuests) {

        if ($this->isNewResource()) {
            throw new RuntimeException('Test Allocating rooms in an invalid Resource at Resource->testAllocateRoom.');
        }

        $room = reset($this->rooms);

        if ($room instanceof Room === FALSE) {
            return FALSE;
        }

        $reqOcc = $room->getCurrentOcc() + $numGuests;

        if ($reqOcc <= $this->getMaxOccupants()) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Summary of allocateRoom
     * @param int $numGuests
     * @param bool $overRideMax
     * @throws \HHK\Exception\RuntimeException
     * @return mixed
     */
    public function allocateRoom($numGuests, $overRideMax = FALSE) {

        if ($this->isNewResource()) {
            throw new RuntimeException('Allocating rooms in an invalid Resource at Resource->allocateRoom.');
        }

        $room = reset($this->rooms);

        if ($room instanceof Room === FALSE) {
            return NULL;
        }

        $reqOcc = $room->getCurrentOcc() + $numGuests;

        if ($reqOcc <= $this->getMaxOccupants() || $overRideMax) {
            $room->setCurrentOcc($reqOcc);
            return $room;
        }
        return NULL;
    }

}
?>