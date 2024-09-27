<?php

namespace HHK\House\Reservation;

use HHK\sec\{Labels};

/**
 * Description of StaticReservation
 *
 * @author Eric
 */

class DeletedReservation extends Reservation {

    function createMarkup(\PDO $dbh) {

        $labels = Labels::getLabels();

        $dataArray['deleted'] = 'This ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' was Deleted.';

        return $dataArray;

    }
}
?>