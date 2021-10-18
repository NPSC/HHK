<?php

namespace HHK\Payment\Invoice\InvoiceLine;

use HHK\SysConst\InvoiceLineType;

/**
 * WaiveInvoiceLine.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of WaiveInvoiceLine
 *
 * @author Eric
 */

class WaiveInvoiceLine extends AbstractInvoiceLine {

    public function __construct($useDetail = TRUE) {
        parent::__construct($useDetail);
        $this->setTypeId(InvoiceLineType::Waive);
    }

}
?>