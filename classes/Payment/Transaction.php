<?php

namespace HHK\Payment;

use HHK\Payment\PaymentResponse\AbstractPaymentResponse;
use HHK\Tables\Payment\TransRS;
use HHK\Tables\EditRS;
use HHK\Payment\PaymentResponse\CheckResponse;
use HHK\Payment\PaymentResponse\TransferResponse;

/**
 * Transaction.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 * Description of Transaction
 *
 * @author Eric
 */
class Transaction {

    public static function recordTransaction(\PDO $dbh, AbstractPaymentResponse $vr, $gwName, $transType, $transMethod) {

        // Record transaction
        $transRs = new TransRs();

        $transRs->Amount->setNewVal($vr->getAmount());
        $transRs->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $transRs->Date_Entered->setNewVal(date("Y-m-d H:i:s"));
        $transRs->Payment_Type->setNewVal($vr->getPaymentMethod());
        $transRs->idName->setNewVal($vr->getIdPayor());
        $transRs->Trans_Date->setNewVal(date("Y-m-d H:i:s"));
        $transRs->Gateway_Ref->setNewVal($gwName);
        $transRs->Trans_Type->setNewVal($transType);
        $transRs->Trans_Method->setNewVal($transMethod);

        if ($vr instanceof CheckResponse || $vr instanceof TransferResponse) {
            $transRs->Check_Number->setNewVal($vr->getCheckNumber());
        }


        if (isset($vr->response)) {
            $transRs->Card_Number->setNewVal($vr->response->getMaskedAccount());
            $transRs->Card_Expire->setNewVal($vr->response->getExpDate());
            $transRs->Card_Name->setNewVal($vr->response->getCardHolderName());
            $transRs->Payment_Status->setNewVal($vr->response->getResponseCode());
            $transRs->Card_Authorize->setNewVal($vr->response->getAuthCode());
            $transRs->RefNo->setNewVal($vr->response->getRefNo());
            $transRs->Process_Code->setNewVal($vr->response->getProcessData());
        }

        $idTrans = EditRS::insert($dbh, $transRs);
        $transRs->idTrans->setNewVal($idTrans);
        EditRS::updateStoredVals($transRs);

        return $transRs;

    }

}
?>