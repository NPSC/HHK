<?php

namespace HHK\House\Resource;

use HHK\Exception\RuntimeException;

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

    public function testAllocateRoom($numGuests) {

        if ($this->isNewResource()) {
            throw new RuntimeException('Test Allocating rooms in an invalid Resource at Resource->testAllocateRoom.');
        }

        $room = reset($this->rooms);

        if (is_a($room, 'Room') === FALSE) {
            return FALSE;
        }

        $reqOcc = $room->getCurrentOcc() + $numGuests;

        if ($reqOcc <= $this->getMaxOccupants()) {
            return TRUE;
        }

        return FALSE;
    }

    public function allocateRoom($numGuests, $overRideMax = FALSE) {

        if ($this->isNewResource()) {
            throw new RuntimeException('Allocating rooms in an invalid Resource at Resource->allocateRoom.');
        }

        $room = reset($this->rooms);

        if (is_a($room, 'Room') === FALSE) {
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