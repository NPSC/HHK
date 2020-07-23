<?php

namespace HHK\Payment\PaymentResponse;

use HHK\SysConst\PaymentMethod;
use HHK\HTMLControls\HTMLTable;

/**
 * TransferResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class TransferResponse extends CheckResponse {


    public function getPaymentMethod() {
        return PaymentMethod::Transfer;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Transfer:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd('Transfer Acct:', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->getCheckNumber()));

    }

}
?>