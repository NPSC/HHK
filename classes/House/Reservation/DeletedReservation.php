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

        $dataArray['deleted'] = 'This ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' was deleted.  ';

        return $dataArray;

    }

    /**
     * Summary of save
     * A deleted reservation has no family or reservRs data to save; just report it as deleted.
     * @param \PDO $dbh
     * @return DeletedReservation
     */
    public function save(\PDO $dbh) {

        return $this;

    }

    /**
     * Summary of checkedinMarkup
     * Reports the save attempt's failure distinctly from a plain view of a deleted reservation.
     * @param \PDO $dbh
     * @return array
     */
    public function checkedinMarkup(\PDO $dbh) {

        $labels = Labels::getLabels();

        $dataArray['deleted'] = 'This ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' cannot be saved because it was deleted by another user.  ';

        return $dataArray;

    }

    /**
     * Summary of delete
     * There is nothing left to delete; report that plainly rather than running the base delete logic.
     * @param \PDO $dbh
     * @return array<string>
     */
    public function delete(\PDO $dbh) {

        $labels = Labels::getLabels();

        $dataArray['deleted'] = 'This ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' has already been deleted.  ';

        return $dataArray;

    }
}
?>