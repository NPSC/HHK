<?php

namespace HHK\Purchase;

/**
 * CheckinCharges.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of CheckinCharges
 *
 * @author Eric
 */
 
class CheckinCharges extends VisitCharges {
    
    /**
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @param float $visitFeeCharged
     * @param float $depositCharged
     */
    public function __construct($idVisit, $visitFeeCharged, $depositCharged) {
        parent::__construct($idVisit);
        
        $this->DepositCharged = $depositCharged;
        $this->visitFeeCharged = $visitFeeCharged;
    }
    
    public function getRoomFeesPaid() {
        
        // TODO:  look for retained lodging fees.
        return 0;
    }
    
}
?>