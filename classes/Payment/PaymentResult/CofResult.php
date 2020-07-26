<?php

namespace HHK\Payment\PaymentResult;

/**
 * PaymentResult.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CofResult extends PaymentResult {
    
    function __construct($displayMessage, $status, $idName, $idRegistration) {
        
        parent::__construct(0, $idRegistration, $idName);
        
        $this->displayMessage = $displayMessage;
        $this->status = $status;
        
    }
    
}
?>