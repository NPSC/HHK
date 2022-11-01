<?php

namespace HHK\House\Reservation;

/**
 * Description of StaticReservation
 *
 * @author Eric
 */

class StaticReservation extends ActiveReservation {

    public function addPerson(\PDO $dbh) {
        return array();
    }

}
?>