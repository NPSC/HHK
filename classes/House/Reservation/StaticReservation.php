<?php

namespace HHK\House\Reservation;

/**
 * Description of StaticReservation
 *
 * @author Eric
 */

class StaticReservation extends ActiveReservation {

    /**
     * Summary of addPerson
     * @param \PDO $dbh
     * @return array
     */
    public function addPerson(\PDO $dbh) {
        return array();
    }

}
?>