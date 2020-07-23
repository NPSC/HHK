<?php

namespace HHK\Payment\Invoice\InvoiceLine;

use HHK\SysConst\InvoiceLineType;

/**
 * InvoiceInvoiceLine.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
 * Description of InvoiceInvoiceLine
 *
 * @author Eric
 */

class InvoiceInvoiceLine extends AbstractInvoiceLine {

    public function __construct($useDetail = TRUE) {
        parent::__construct($useDetail);
        $this->setTypeId(InvoiceLineType::Invoice);
    }
    
}
?>