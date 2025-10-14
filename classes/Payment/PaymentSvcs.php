<?php

namespace HHK\Payment;

use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\Notification\Mail\HHKMailer;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\GatewayResponse\StandInGwResponse;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentManager\PaymentManagerPayment;
use HHK\Payment\PaymentResponse\{CashResponse, CheckResponse, TransferResponse};
use HHK\Payment\PaymentResult\{PaymentResult, ReturnResult, CofResult};
use HHK\SysConst\{InvoiceStatus, PayType, PaymentStatusCode, PaymentMethod};
use HHK\sec\Session;
use HHK\Tables\EditRS;
use HHK\Tables\Payment\{PaymentRS, Payment_AuthRS, PaymentInfoCheckRS};
use HHK\HTMLControls\{HTMLContainer};
use HHK\Exception\PaymentException;
use HHK\Tables\Payment\TransRS;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use RuntimeException;


/**
 * PaymentSvcs.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 * Description of PaymentSvcs
 *
 * @author Eric
 */

class PaymentSvcs {


    /**
     * Summary of payAmount
     * @param \PDO $dbh
     * @param \HHK\Payment\Invoice\Invoice $invoice
     * @param \HHK\Payment\PaymentManager\PaymentManagerPayment $pmp
     * @param string $postbackUrl
     * @return PaymentResult|null
     */
    public static function payAmount(\PDO $dbh, Invoice $invoice, PaymentManagerPayment $pmp, $postbackUrl) {

        $uS = Session::getInstance();

        // Check the status
        if ($invoice->getStatus() != InvoiceStatus::Unpaid) {
            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setReplyMessage('Error:  The payment status must be unpaid, instead it is: '.$invoice->getStatus());
            return $payResult;
        }

        // Check the payment amount.
        if ($invoice->getAmountToPay() == 0) {

            // Pay 0 amounts as cash.
            $pmp->setPayType(PayType::Cash);
        }

        if ($invoice->getAmountToPay() < 0) {
            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setReplyMessage('warning:  Cannot Pay a negative amount. ');
            return $payResult;
        }

        // Check balance
        if (abs($invoice->getAmountToPay()) > abs($invoice->getBalance())) {
            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setReplyMessage('error:  Payment (' . $invoice->getAmountToPay() . ') cannot be larger than the remaining balance (' . $invoice->getBalance() . ') on an invoice.');
            return $payResult;
        }

        $amount = $invoice->getAmountToPay();
        $payResult = NULL;


        switch ($pmp->getPayType()) {

          case PayType::Charge:

            // Payment Gateway
            $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $pmp->getMerchant());

            $payResult = $gateway->CreditSale($dbh, $pmp, $invoice, $postbackUrl);


            break;

          case PayType::Cash:

            $cashResp = new CashResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getPayNotes(), $pmp->getCashTendered());

            CashTX::cashSale($dbh, $cashResp, $uS->username, $pmp->getPayDate());

            // Update invoice
            $invoice->updateInvoiceBalance($dbh, $cashResp->getAmount(), $uS->username);

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentAccepted($dbh, $uS, $cashResp, $invoice);
            $payResult->setDisplayMessage('Cash Payment.  ');

            break;

          case PayType::Check:

            $ckResp = new CheckResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getCheckNumber(), $pmp->getPayNotes());

            CheckTX::checkSale($dbh, $ckResp, $uS->username, $pmp->getPayDate());

            // Update invoice
            $invoice->updateInvoiceBalance($dbh, $ckResp->getAmount(), $uS->username);

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentAccepted($dbh, $uS, $ckResp, $invoice);
            $payResult->setDisplayMessage('Payment by Check.  ');

            break;

          case PayType::Transfer:

            $ckResp = new TransferResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getTransferAcct(), $pmp->getPayNotes());

            TransferTX::sale($dbh, $ckResp, $uS->username, $pmp->getPayDate());

            // Update invoice
            $invoice->updateInvoiceBalance($dbh, $ckResp->getAmount(), $uS->username);

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentAccepted($dbh, $uS, $ckResp, $invoice);
            $payResult->setDisplayMessage('Payment by Transfer.  ');

            break;

          case PayType::Invoice:

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentInvoiced($dbh, $invoice);
            $payResult->setDisplayMessage('Amount Invoiced.  ');
            break;

        }

        return $payResult;
    }

    /**
     * Return an Amount directly from an invoice.  No payment record needed.
     * @param \PDO $dbh
     * @param Invoice $invoice
     * @param PaymentManagerPayment $pmp
     * @param string $postPage
     * @param string $paymentDate
     * @return ReturnResult
     */
    public static function returnAmount(\PDO $dbh, Invoice $invoice, PaymentManagerPayment $pmp, $paymentDate = '', $resvId = 0) {

        $uS = Session::getInstance();

        // Check the status
        if ($invoice->getStatus() != InvoiceStatus::Unpaid) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('Error:  The return status must be "unpaid", instead it is: '.$invoice->getStatus());
            return $rtnResult;
        }

        // Check the payment amount.
        if ($invoice->getAmountToPay() == 0) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('warning:  Invoice\'s Amount to Return is 0');
            return $rtnResult;
        }

        if ($invoice->getAmountToPay() > 0) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('warning:  Cannot Return this amount. ');
            return $rtnResult;
        }

        // Check balance
        if (abs($invoice->getAmountToPay()) > abs($invoice->getBalance())) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('error:  Return (' . $invoice->getAmountToPay() . ') cannot be larger than the remaining balance (' . $invoice->getBalance() . ') on the invoice.');
            return $rtnResult;
        }

        // Use positive amounts for return amount (This is not return payment.)
        $amount = abs($invoice->getAmountToPay());
        $rtnResult = NULL;

        switch ($pmp->getRtnPayType()) {

            case PayType::Charge:

                // Load gateway 
                $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $pmp->getMerchant(), $pmp->getRtnIdToken());
                $rtnResult = $gateway->returnAmount($dbh, $invoice, $pmp->getRtnIdToken(), $pmp->getPayNotes(), $resvId, $pmp->getPayDate());

                break;

            case PayType::Cash:

                $cashResp = new CashResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getPayNotes());

                CashTX::returnAmount($dbh, $cashResp, $uS->username, $paymentDate);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, (0 - $cashResp->getAmount()), $uS->username);

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentAccepted($dbh, $uS, $cashResp, $invoice);
                $rtnResult->setDisplayMessage('Cash Return.  ');
                break;

            case PayType::Check:

                $ckResp = new CheckResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getRtnCheckNumber(), $pmp->getPayNotes());

                CheckTX::returnAmount($dbh, $ckResp, $uS->username, $paymentDate);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, (0 - $ckResp->getAmount()), $uS->username);

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentAccepted($dbh, $uS, $ckResp, $invoice);
                $rtnResult->setDisplayMessage('Check Cut for Return.  ');
                break;

            case PayType::Invoice:

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentInvoiced($dbh, $invoice);
                $rtnResult->setDisplayMessage('Return Amount Invoiced.  ');
                break;
            
            case PayType::Transfer:

                $ckResp = new TransferResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getTransferAcct(), $pmp->getPayNotes());

                TransferTX::returnAmount($dbh, $ckResp, $uS->username, $pmp->getPayDate());

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, (0 - $ckResp->getAmount()), $uS->username);

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentAccepted($dbh, $uS, $ckResp, $invoice);
                $rtnResult->setDisplayMessage('Return by Transfer.  ');

            break;

        }

        return $rtnResult;
    }

    /**
     * Summary of voidFees
     * @param \PDO $dbh
     * @param int $idPayment
     * @param string $bid  html id of the originating control
     * @return array
     */
    public static function voidFees(\PDO $dbh, $idPayment, $bid) {

        $uS = Session::getInstance();

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
            return ['warning' => 'Payment record not found for Void/Reverse.  ', 'bid' => $bid];
        }

        EditRS::loadRow($pments[0], $payRs);

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid) {
            return ['warning' => 'Payment is ineligable for Void/Reverse.  ', 'bid' => $bid];
        }

        // Find the Payment detail record.
        $pAuthRs = new Payment_AuthRS();
        $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
        $pAuths = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment), 'and', array($pAuthRs->idPayment_auth));

        if (count($pAuths) < 1) {
            return ['warning' => 'Payment Auth record not found for Void/Reverse.  ', 'bid' => $bid];
        }

        EditRS::loadRow(array_pop($pAuths), $pAuthRs);

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        if ($payRs->idPayment_Method->getStoredVal() != PaymentMethod::Charge) {
            return ['warning' => 'Use Return instead.  ', 'bid' => $bid];
        }

        // Load gateway
        $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $pAuthRs->Merchant->getStoredVal());

        $dataArray = $gateway->voidSale($dbh, $invoice, $payRs, $$pAuths, $bid);

        if(isset($dataArray['receipt'], $dataArray['success'], $invoice) && $invoice instanceof Invoice){
            $autoEmailAr = PaymentResult::isAutoEmailEligible($dbh, $invoice->getIdGroup(), $invoice->getSoldToId());

            if ($autoEmailAr['autoEmail'] == true) {
                $toAddr = $autoEmailAr['email'];
                $emResult = PaymentSvcs::sendReceiptEmail($dbh, $dataArray['receipt'], $invoice, $toAddr);
                if(isset($emResult['success'])){
                    $dataArray['success'] .= " " . $emResult['success'];
                }
                
                if(isset($emResult['error'])){
                    $dataArray['error'] = $emResult['error'];
                }
            }

            $dataArray["billToEmail"] = $invoice->getBillToEmail($dbh);
            $dataArray["idPayment"] = $idPayment;
        }

        return $dataArray;

    }

    /**
     * Summary of reversalFees
     * @param \PDO $dbh
     * @param int $idPayment
     * @param string $bid  html id of the originating control
     * @return array
     */
    public static function reversalFees(\PDO $dbh, $idPayment, $bid) {

        $uS = Session::getInstance();

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
            return ['warning' => 'Payment record not found.  Unable to Reverse/Void this purchase.  ', 'bid' => $bid];
        }

        EditRS::loadRow($pments[0], $payRs);

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid) {
            return ['warning' => 'Payment is ineligable for Reversal/Void.  ', 'bid' => $bid];
        }

        // Find the Payment detail record.
        $pAuthRs = new Payment_AuthRS();
        $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
        $pAuths = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment), 'and', array($pAuthRs->idPayment_auth));

        if (count($pAuths) < 1) {
            return ['warning' => 'Payment Auth record not found for Void/Reverse.  ', 'bid' => $bid];
        }

        EditRS::loadRow(array_pop($pAuths), $pAuthRs);

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        if ($payRs->idPayment_Method->getStoredVal() != PaymentMethod::Charge) {
            return ['warning' => 'Use Return instead.  ', 'bid' => $bid];
        }

        // Load gateway
        $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $pAuthRs->Merchant->getStoredVal());

        $dataArray = $gateway->reverseSale($dbh, $invoice, $payRs, $pAuthRs, $bid);

        if(isset($dataArray['receipt'], $dataArray['success'], $invoice) && $invoice instanceof Invoice){
            $autoEmailAr = PaymentResult::isAutoEmailEligible($dbh, $invoice->getIdGroup(), $invoice->getSoldToId());

            if ($autoEmailAr['autoEmail'] == true) {
                $toAddr = $autoEmailAr['email'];
                $emResult = PaymentSvcs::sendReceiptEmail($dbh, $dataArray['receipt'], $invoice, $toAddr);
                if(isset($emResult['success'])){
                    $dataArray['success'] .= " " . $emResult['success'];
                }
                
                if(isset($emResult['error'])){
                    $dataArray['error'] = $emResult['error'];
                }
            }

            $dataArray["billToEmail"] = $invoice->getBillToEmail($dbh);
            $dataArray["idPayment"] = $idPayment;
        }

        return $dataArray;

    }

    /**
     * Summary of returnPayment
     * @param \PDO $dbh
     * @param int $idPayment
     * @param string $bid
     * @throws \HHK\Exception\PaymentException
     * @return array
     */
    public static function returnPayment(\PDO $dbh, $idPayment, $bid) {

        $uS = Session::getInstance();
        $dataArray = array('bid' => $bid);


        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
             return array('warning' => 'Payment record not found.  ', 'bid' => $bid);
        }

        EditRS::loadRow($pments[0], $payRs);

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid) {
            return array('warning' => 'This Payment is ineligable for return.  ', 'bid' => $bid);
        }

        // is already a refund?
        if ($payRs->Is_Refund->getStoredVal() > 0) {
            return array('error' => 'This is already a return.  ', 'bid' => $bid);
        }


        // Get the invoice record
        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        switch ($payRs->idPayment_Method->getStoredVal()) {

            case PaymentMethod::Charge:

                // Find the Payment detail record.
                $pAuthRs = new Payment_AuthRS();
                $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $pAuths = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment), 'and', array($pAuthRs->idPayment_auth));

                if (count($pAuths) < 1) {
                    return array('warning' => 'Payment detail record not found.  Unable to return this payment. ', 'bid' => $bid);
                }

                EditRS::loadRow(array_pop($pAuths), $pAuthRs);

                // Load gateway
                $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $pAuthRs->Merchant->getStoredVal());
                $dataArray = $gateway->returnPayment($dbh, $invoice, $payRs, $pAuthRs, $bid);

                break;

            case PaymentMethod::Cash:

                $cashResp = new CashResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber());

                CashTX::returnPayment($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $cashResp->getAmount(), $uS->username);

                $dataArray['success'] = 'Payment is Returned.  ';

                $cashResp->idVisit = $invoice->getOrderNumber();
                
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $cashResp, $uS->siteName, $uS->sId)));

                break;

            case PaymentMethod::Check:

                // Find hte detail record.
                $pAuthRs = new PaymentInfoCheckRS();
                $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $arows = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment));

                if (count($arows) != 1) {
                    throw new PaymentException('Payment Detail record not found. ');
                }

                EditRS::loadRow($arows[0], $pAuthRs);

                $cashResp = new CheckResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $pAuthRs->Check_Number->getStoredVal());

                CheckTX::checkReturn($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $cashResp->getAmount(), $uS->username);

                $dataArray['success'] = 'Payment is Returned.  ';

                $cashResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $cashResp, $uS->siteName, $uS->sId)));
                break;

            case PaymentMethod::Transfer:

                // Find hte detail record.
                $pAuthRs = new PaymentInfoCheckRS();
                $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $arows = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment));

                if (count($arows) != 1) {
                    throw new PaymentException('Payment Detail record not found. ');
                }

                EditRS::loadRow($arows[0], $pAuthRs);

                $cashResp = new TransferResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $pAuthRs->Check_Number->getStoredVal());

                TransferTX::transferReturn($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $cashResp->getAmount(), $uS->username);

                $dataArray['success'] = 'Payment is Returned.  ';

                $cashResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $cashResp, $uS->siteName, $uS->sId)));
                break;

            default:
                throw new PaymentException('Unknown pay type.  ');
        }

        if(isset($dataArray['receipt'], $dataArray['success'], $invoice) && $invoice instanceof Invoice){
            $autoEmailAr = PaymentResult::isAutoEmailEligible($dbh, $invoice->getIdGroup(), $invoice->getSoldToId());

            if ($autoEmailAr['autoEmail'] == true) {
                $toAddr = $autoEmailAr['email'];
                $emResult = PaymentSvcs::sendReceiptEmail($dbh, $dataArray['receipt'], $invoice, $toAddr);
                if(isset($emResult['success'])){
                    $dataArray['success'] .= " " . $emResult['success'];
                }
                
                if(isset($emResult['error'])){
                    $dataArray['error'] = $emResult['error'];
                }
            }

            $dataArray["billToEmail"] = $invoice->getBillToEmail($dbh);
            $dataArray["idPayment"] = $idPayment;
        }

        return $dataArray;
    }

    /**
     * Both return payment and return (random) amount come here
     *
     * @param \PDO $dbh
     * @param int $idPayment
     * @param string $bid
     * @return array
     */
    public static function voidReturnFees(\PDO $dbh, $idPayment, $bid) {

        $uS = Session::getInstance();
        $dataArray = array('bid' => $bid);

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
            return array('warning' => 'Payment record not found. ', 'bid' => $bid);
        }

        EditRS::loadRow($pments[0], $payRs);

        // only available to charge cards.
        if ($payRs->idPayment_Method->getStoredVal() != PaymentMethod::Charge) {
            return array('warning' => 'Void-Return is Not Available.  ', 'bid' => $bid);
        }

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Retrn && $payRs->Is_Refund->getStoredVal() === 0) {
            return array('warning' => 'Return is ineligable for Voiding.  ', 'bid' => $bid);
        }

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Void this Return. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        // Payment Gateway
        $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $pAuthRs->Merchant->getStoredVal());
        $dataArray =  array_merge($dataArray,  $gateway->voidReturn($dbh, $invoice, $payRs, $pAuthRs, $bid));

        if(isset($dataArray['receipt'], $dataArray['success'], $invoice) && $invoice instanceof Invoice){
            $autoEmailAr = PaymentResult::isAutoEmailEligible($dbh, $invoice->getIdGroup(), $invoice->getSoldToId());

            if ($autoEmailAr['autoEmail'] == true) {
                $toAddr = $autoEmailAr['email'];
                $emResult = PaymentSvcs::sendReceiptEmail($dbh, $dataArray['receipt'], $invoice, $toAddr);
                if(isset($emResult['success'])){
                    $dataArray['success'] .= " " . $emResult['success'];
                }
                
                if(isset($emResult['error'])){
                    $dataArray['error'] = $emResult['error'];
                }
            }

            $dataArray["billToEmail"] = $invoice->getBillToEmail($dbh);
            $dataArray["idPayment"] = $idPayment;
        }

        return $dataArray;
    }

    /**
     * Summary of undoReturnFees
     * @param \PDO $dbh
     * @param mixed $idPayment
     * @param mixed $bid
     * @throws \HHK\Exception\PaymentException
     * @return array
     */
    public static function undoReturnFees(\PDO $dbh, $idPayment, $bid) {

        $uS = Session::getInstance();
        $dataArray = array('bid' => $bid);

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
            return array('warning' => 'Payment record not found.  Unable to Undo this return.  ', 'bid' => $bid);
        }

        EditRS::loadRow($pments[0], $payRs);

        // Filter out return amounts here
        if ($payRs->Is_Refund->getStoredVal() > 0) {
            return self::undoReturnAmount($dbh, $idPayment, $payRs->idPayment_Method->getStoredVal(),$payRs->Amount->getStoredVal(), $bid);
        }

        // ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Retrn) {
            return array('warning' => 'Undo Payment is ineligable.  ', 'bid' => $bid);
        }

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        // Check the invoice status
        if ($invoice->getStatus() != InvoiceStatus::Unpaid) {
        	return array('warning' => 'The invoice is already paid, Undo Return failed. ', 'bid' => $bid);
        }

        // Record transaction

        switch ($payRs->idPayment_Method->getStoredVal()) {

            case PaymentMethod::Check:

                $ckResp = new CheckResponse($payRs->Amount->getStoredVal(), $invoice->getSoldToId(), $invoice->getInvoiceNumber());

                CheckTX::undoReturnPayment($dbh, $ckResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, $ckResp->getAmount(), $uS->username);

                $ckResp->idVisit = $invoice->getOrderNumber();

                $dataArray['success'] = 'Check return is undone.  ';
                $dataArray['receipt'] = Receipt::createSaleMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $ckResp);

                break;

            case PaymentMethod::Transfer:

                $ckResp = new TransferResponse($payRs->Amount->getStoredVal(), $invoice->getSoldToId(), $invoice->getInvoiceNumber());

                TransferTX::undoTransferReturn($dbh, $ckResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, $ckResp->getAmount(), $uS->username);

                $ckResp->idVisit = $invoice->getOrderNumber();

                $dataArray['success'] = 'Transfer return is undone.  ';
                $dataArray['receipt'] = Receipt::createSaleMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $ckResp);

                break;

            case PaymentMethod::Cash:

                $cashResp = new CashResponse($payRs->Amount->getStoredVal(), $invoice->getSoldToId(), $invoice->getInvoiceNumber());

                CashTX::undoReturnPayment($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, $cashResp->getAmount(), $uS->username);

                $cashResp->idVisit = $invoice->getOrderNumber();

                $dataArray['success'] = 'Cash Return is undone.  ';
                $dataArray['receipt'] = Receipt::createSaleMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $cashResp);

                break;

            case PaymentMethod::Charge:

            	// Find the detail record.
            	$stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
            	$arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            	if (count($arows) < 1) {
            		$dataArray['warning'] = 'Payment Detail record not found.  Unable to Undo this Return. ';
            		return $dataArray;
            	}

            	$pAuthRs = new Payment_AuthRS();
            	EditRS::loadRow(array_pop($arows), $pAuthRs);

            	if ($pAuthRs->Status_Code->getStoredVal() !== PaymentStatusCode::Retrn) {
            		$dataArray['warning'] = 'Return is ineligable for Undoing.  ';
            		return $dataArray;
            	}

            	// Payment Gateway
            	$gateway = AbstractPaymentGateway::factory($dbh, $pAuthRs->Processor->getStoredVal(), $pAuthRs->Merchant->getStoredVal());
            	$dataArray = $gateway->undoReturnPayment($dbh, $invoice, $payRs, $pAuthRs, $bid);

            	break;

            default:
                throw new PaymentException('The pay type is ineligible.  ');
        }

        if(isset($dataArray['receipt'], $dataArray['success'], $invoice) && $invoice instanceof Invoice){
            $autoEmailAr = PaymentResult::isAutoEmailEligible($dbh, $invoice->getIdGroup(), $invoice->getSoldToId());

            if ($autoEmailAr['autoEmail'] == true) {
                $toAddr = $autoEmailAr['email'];
                $emResult = PaymentSvcs::sendReceiptEmail($dbh, $dataArray['receipt'], $invoice, $toAddr);
                if(isset($emResult['success'])){
                    $dataArray['success'] .= " " . $emResult['success'];
                }
                
                if(isset($emResult['error'])){
                    $dataArray['error'] = $emResult['error'];
                }
            }

            $dataArray["billToEmail"] = $invoice->getBillToEmail($dbh);
            $dataArray["idPayment"] = $idPayment;
        }

        return $dataArray;
    }

    /**
     * Summary of undoReturnAmount
     * @param \PDO $dbh
     * @param mixed $idPayment
     * @param mixed $idPaymentMethod
     * @param mixed $paymentAmount
     * @param mixed $bid
     * @throws \HHK\Exception\PaymentException
     * @return array
     */
    protected static function undoReturnAmount(\PDO $dbh, $idPayment, $idPaymentMethod, $paymentAmount, $bid) {

        $uS = Session::getInstance();
        $dataArray = array('bid' => $bid);

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        // Record transaction
        // THese always have their own invoice.
        switch ($idPaymentMethod) {

            case PaymentMethod::Check:

                $ckResp = new CheckResponse($paymentAmount, $invoice->getSoldToId(), $invoice->getInvoiceNumber());

                CheckTX::undoReturnAmount($dbh, $ckResp, $idPayment);

                $invoice->updateInvoiceBalance($dbh, $ckResp->getAmount(), $uS->username);
                // delete invoice
                $invoice->deleteInvoice($dbh, $uS->username);

                $dataArray['success'] = 'Check refund is undone.  ';

                break;

            case PaymentMethod::Transfer:

                $txResp = new TransferResponse($paymentAmount, $invoice->getSoldToId(), $invoice->getInvoiceNumber());

                TransferTX::undoReturnAmount($dbh, $txResp, $idPayment);

                $invoice->updateInvoiceBalance($dbh, $txResp->getAmount(), $uS->username);
                // delete invoice
                $invoice->deleteInvoice($dbh, $uS->username);

                $dataArray['success'] = 'Transfer refund is undone.  ';

                break;

            case PaymentMethod::Cash:

                $cashResp = new CashResponse($paymentAmount, $invoice->getSoldToId(), $invoice->getInvoiceNumber());

                CashTX::undoReturnAmount($dbh, $cashResp, $idPayment);

                $invoice->updateInvoiceBalance($dbh, $cashResp->getAmount(), $uS->username);
                // delete invoice
                $invoice->deleteInvoice($dbh, $uS->username);

                $dataArray['success'] = 'Cash Refund is undone.  ';

                break;

            case PaymentMethod::Charge:

            	$payRs = new PaymentRS();
            	$payRs->idPayment->setStoredVal($idPayment);
            	$pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

            	if (count($pments) != 1) {
            		return array('warning' => 'Payment record not found.  Unable to Undo this refund.  ', 'bid' => $bid);
            	}

            	EditRS::loadRow($pments[0], $payRs);

            	// ineligible
            	if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid) {
            		return array('warning' => 'Undo Refund is ineligable.  ', 'bid' => $bid);
            	}

            	// Find the detail record.
            	$stmt = $dbh->query("Select * from payment_auth where idPayment = $idPayment order by idPayment_auth");
            	$arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            	if (count($arows) < 1) {
            		$dataArray['warning'] = 'Payment Detail record not found.  Unable to Undo this Refund. ';
            		return $dataArray;
            	}

            	$pAuthRs = new Payment_AuthRS();
            	EditRS::loadRow(array_pop($arows), $pAuthRs);

            	if ($pAuthRs->Status_Code->getStoredVal() !== PaymentStatusCode::Paid) {
            		$dataArray['warning'] = 'Refund is ineligable for Undoing.  ';
            		return $dataArray;
            	}

            	// Payment Gateway
            	$gateway = AbstractPaymentGateway::factory($dbh, $pAuthRs->Processor->getStoredVal(), $pAuthRs->Merchant->getStoredVal());
            	$dataArray = $gateway->undoReturnAmount($dbh, $invoice, $payRs, $pAuthRs, $bid);

            	break;

            default:
                throw new PaymentException('This pay type is ineligible for Undo Refund Amount.  ');
        }

        if(isset($dataArray['receipt'], $dataArray['success'], $invoice) && $invoice instanceof Invoice){
            $autoEmailAr = PaymentResult::isAutoEmailEligible($dbh, $invoice->getIdGroup(), $invoice->getSoldToId());

            if ($autoEmailAr['autoEmail'] == true) {
                $toAddr = $autoEmailAr['email'];
                $emResult = PaymentSvcs::sendReceiptEmail($dbh, $dataArray['receipt'], $invoice, $toAddr);
                if(isset($emResult['success'])){
                    $dataArray['success'] .= " " . $emResult['success'];
                }
                
                if(isset($emResult['error'])){
                    $dataArray['error'] = $emResult['error'];
                }
            }

            $dataArray["billToEmail"] = $invoice->getBillToEmail($dbh);
            $dataArray["idPayment"] = $idPayment;
        }

        return $dataArray;
    }

    /**
     * Summary of processWebhook
     * @param \PDO $dbh
     * @param mixed $data
     * @return bool
     */
    public static function processWebhook(\PDO $dbh, $data) {

        $uS = Session::getInstance();

        $stmt = $dbh->query("Select cc_name from cc_hosted_gateway where Gateway_Name = 'instamed'");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) == 1) {

	        // Payment Gateway
	        $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $rows[0][0]);

	        $payNotes = '';

	        return $gateway->processWebhook($dbh, $data, $payNotes, $uS->username);
        }

        return FALSE;

    }

    /**
     * Summary of processSiteReturn
     * @param \PDO $dbh
     * @param mixed $post
     * @return PaymentResult|PaymentResult|CofResult|null
     */
    public static function processSiteReturn(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        //Quick exti?
        if ($uS->PaymentGateway == '' || $uS->ccgw == '') {
            return NULL;
        }

        // Payment Gateway
        $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

        $payNotes = '';
        $payDate = date('Y-m-d H:i:s');
        $idInv = 0;
        $tokenId = '';

        if (isset($uS->paymentNotes)) {
            $payNotes = $uS->paymentNotes;
            unset($uS->paymentNotes);
        }

        if (isset($uS->paymentDate)) {
            $payDate = $uS->paymentDate;
            unset($uS->paymentDate);
        }

        if (isset($uS->imtoken)) {
            $tokenId = $uS->imtoken;
            unset($uS->imtoken);
        }

        if (isset($uS->paymentIds[$tokenId])) {
            $idInv = $uS->paymentIds[$tokenId];
        }

        if (isset($uS->imcomplete)) {
            $post = $uS->imcomplete;
            unset($uS->imcomplete);
        }

        return $gateway->processHostedReply($dbh, $post, $tokenId, $idInv, $payNotes, $payDate);

    }

    /**
     * Summary of generateReceipt
     * @param \PDO $dbh
     * @param int $idPayment
     */
    public static function generateReceipt(\PDO $dbh, $idPayment) {

        $uS = Session::getInstance();

        if ($idPayment < 1) {
            return array('warning'=>'Payment Id is not rational: ' . $idPayment);
        }

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
             return array('warning' => 'Payment record not found.  ');
        }

        EditRS::loadRow($pments[0], $payRs);

        // Get the invoice record
        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        $payResp = NULL;

        switch ($payRs->idPayment_Method->getStoredVal()) {

            case PaymentMethod::Cash:
                $transRS = Transaction::getTransactionRS($dbh, $payRs->idTrans->getStoredVal());

                $payResp = new CashResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $payRs->Notes, $transRS->Amount_Tendered->getStoredVal());
                $payResp->paymentRs = $payRs;
                break;

            case PaymentMethod::Check:

                $ckRs = new PaymentInfoCheckRS();
                $ckRs->idPayment->setStoredVal($idPayment);
                $rows = EditRS::select($dbh, $ckRs, array($ckRs->idPayment));

                if (count($rows) != 1) {
                    return array('warning'=>'Check payment record not found.');
                }

                EditRS::loadRow($rows[0], $ckRs);
                $payResp = new CheckResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $rows[0]['Check_Number']);
                $payResp->paymentRs = $payRs;
                break;

            case PaymentMethod::Transfer:

                $ckRs = new PaymentInfoCheckRS();
                $ckRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $rows = EditRS::select($dbh, $ckRs, array($ckRs->idPayment));

                if (count($rows) != 1) {
                    return array('warning'=>'Transfer payment record not found.');
                }

                EditRS::loadRow($rows[0], $ckRs);
                $payResp = new TransferResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $rows[0]['Check_Number']);
                $payResp->paymentRs = $payRs;
                break;

            case PaymentMethod::Charge:

                $stmt = $dbh->query("SELECT * FROM payment_auth where idPayment = $idPayment order by `Timestamp`");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (count($rows) < 1) {
                    return array('warning'=>'Charge payment record not found.');
                }

                $pAuthRs = new Payment_AuthRS();
                EditRS::loadRow($rows[count($rows)-1], $pAuthRs);

                $gTRs = CreditToken::getTokenRsFromId ( $dbh, $payRs->idToken->getStoredVal () );

                $gwResp = new StandInGwResponse($pAuthRs, $gTRs->OperatorID->getStoredVal(), $gTRs->CardHolderName->getStoredVal(), $gTRs->ExpDate->getStoredVal(), $gTRs->Token->getStoredVal(), $invoice->getInvoiceNumber(), $payRs->Amount->getStoredVal());

                try {
                    $gateway = AbstractPaymentGateway::factory($dbh, $pAuthRs->Processor->getStoredVal(), $pAuthRs->Merchant->getStoredVal());
                } catch (\Exception $ex) {
                    // Grab the local gateway
                    $gateway = AbstractPaymentGateway::factory($dbh, '', '');
                }

                $payResp = $gateway->getPaymentResponseObj($gwResp, $payRs->idPayor->getStoredVal(), $invoice->getIdGroup(), $invoice->getInvoiceNumber());
                $payResp->paymentRs = $payRs;
                break;

            default:
                return array('warning'=>'Payment Method not recognized: '.$payRs->idPayment_Method->getStoredVal());

        }

        $dataArray = array();

        $statusCode = $payRs->Status_Code->getStoredVal();
        $payResp->setPaymentDate($payRs->Payment_Date->getStoredVal());

        switch ($statusCode) {

            case PaymentStatusCode::Paid:

                if ($payRs->Is_Refund->getStoredVal() > 0) {
                    // Refund amount
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createRefundAmtMarkup($dbh, $payResp, $uS->siteName, $uS->sId)));
                } else {
                    // Pay Amount
                    $dataArray['receipt'] = Receipt::createSaleMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);
                }
                break;

            case PaymentStatusCode::Declined:

                $dataArray['receipt'] = Receipt::createDeclinedMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);
                break;

            case PaymentStatusCode::VoidSale:
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $payResp, $uS->siteName, $uS->sId)));
                break;

            case PaymentStatusCode::VoidReturn:
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $payResp, $uS->siteName, $uS->sId, 'Void Return')));
                break;

            case PaymentStatusCode::Reverse:
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $payResp, $uS->siteName, $uS->sId, 'Reverse Sale')));
                break;

            case PaymentStatusCode::Retrn:
                $payResp->setPaymentDate($payRs->Last_Updated->getStoredVal());
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $payResp, $uS->siteName, $uS->sId)));
                break;

        }

        if(isset($dataArray['receipt'])){
            $dataArray["invoice"] = $invoice;
            $dataArray["billToEmail"] = $invoice->getBillToEmail($dbh);
        }

        return $dataArray;
    }

    public static function sendReceiptEmail(\PDO $dbh, string $receiptMkup, Invoice $invoice, string $emailAddress){
        $uS = Session::getInstance();
        $fromAddr = $uS->FromAddress;
        
        $emBody = nl2br( $uS->ReceiptEmailBody);

        $toAddrSan = filter_var($emailAddress, FILTER_SANITIZE_EMAIL);

        if ($toAddrSan === FALSE || $toAddrSan == '') {
            // Config data not set.

            return ["error"=>"Send Email failed: Email address invalid"];
        }


        try{
            $mail = new HHKMailer($dbh);

            $mail->From = $fromAddr;
            $mail->addReplyTo($uS->ReplyTo);
            $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);

            $mail->addAddress($toAddrSan);     // Add a recipient

            $bccEntry = $uS->BccAddress;
            $bccs = explode(',', $bccEntry);

            foreach ($bccs as $b) {

                $bcc = filter_var($b, FILTER_SANITIZE_EMAIL);

                if ($bcc !== FALSE && $bcc != '') {
                    $mail->addBCC($bcc);
                }
            }

            $mail->isHTML(true);

            $mail->Subject = htmlspecialchars_decode($uS->siteName, ENT_QUOTES) . ' Payment Receipt';
            $mail->msgHTML($emBody);
            $mail->addStringAttachment(Receipt::makePDF($receiptMkup), "receipt.pdf", PHPMailer::ENCODING_BASE64, "application/pdf");

            $mail->send();

            $invoiceNumber = $invoice->getInvoiceNumber();
            $idPSG = $invoice->getIdGroup();
            LinkNote::save($dbh, "Receipt" . ($invoiceNumber != '' ? " for invoice <a href='ShowInvoice.php?invnum=" . $invoiceNumber . "' target='_blank'>" . $invoiceNumber . "</a>" : '') . " emailed to " . $toAddrSan, $idPSG, Note::PsgLink, '', $uS->username, $uS->ConcatVisitNotes);

            return ["success"=>"Email sent to " . $toAddrSan];
                    

        }catch(PHPMailerException $e){
            return ["error"=>"Send Email failed: " . $mail->ErrorInfo];
        }catch(\Exception $e){
            return ["error"=>"Send Email Failed: " . $e->getMessage()];
        }
    }

    public static function downloadReceipt(\PDO $dbh, $idPayment){
        $receiptMkup = PaymentSvcs::generateReceipt($dbh, $idPayment);
        
        if(isset($receiptMkup["receipt"])){
            $receiptMkup = $receiptMkup["receipt"];
            Receipt::makePDF($receiptMkup, true);
        }else{
            throw new RuntimeException("Reciept not found");
        }
    }

}
