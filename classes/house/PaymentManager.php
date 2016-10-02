<?php
/**
 * PaymentManager.php
 *
 *
 * @category  House
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
  */

/**
 * Description of PaymentManager
 *
 * @author Eric
 */
class PaymentManager {

    public $result = '';
    public $refundAmt = 0;
    public $depositRefundAmt = 0;
    public $guestCreditAmt = 0;
    public $moaRefundAmt = 0;

    /**
     *
     * @var PaymentManagerPayment
     */
    public $pmp;

    /**
     *
     * @var Invoice
     */
    protected $invoice;


    public function __construct($pmp) {
        $this->pmp = $pmp;
        $this->invoice = NULL;
    }


    /**
     *
     * @param \PDO $dbh
     * @param PaymentManagerPayment $pmp
     * @param Visit $visit
     * @param int $idPayor
     * @param str $notes
     * @return Invoice
     * @throws Hk_Exception_Runtime
     */
    public function createInvoice(\PDO $dbh, $visit, $idPayor, $notes = '') {

        $uS = Session::getInstance();
        $this->invoice = NULL;
        $roomCharges = 0;


        if ($idPayor <= 0) {
            $this->result = 'Undefined Payor.  ';
            return $this->invoice;
        }

        $this->pmp->setIdInvoicePayor($idPayor);

        // Process a visit payment
        if (is_null($visit) === FALSE) {


            // Visit Fee Payments
            if ($this->pmp->getVisitFeePayment() > 0) {
                // cleaning fee

                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());

                $visitFeeItem = new Item($dbh, ItemId::VisitFee, $this->pmp->getVisitFeePayment());
                $invLine = new OneTimeInvoiceLine();
                $invLine->createNewLine($visitFeeItem, 1);

                $this->invoice->addLine($dbh, $invLine, $uS->username);

            }


            // Deposit payments
            if ($this->pmp->getKeyDepositPayment() > 0) {

                $invLine = new HoldInvoiceLine();
                $invLine->createNewLine(new Item($dbh, ItemId::KeyDeposit, $this->pmp->getKeyDepositPayment()), 1);

                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);

            }


            // Deposit Refunds
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut && abs($this->pmp->getDepositRefundAmt()) > 0) {
                // Return the deposit
                $this->depositRefundAmt = abs($this->pmp->getDepositRefundAmt());

                $invLine = new ReimburseInvoiceLine();
                $invLine->createNewLine(new Item($dbh, ItemId::DepositRefund, (0 - $this->depositRefundAmt)), 1);
                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);
            }


            // MOA refunds
            if ($this->pmp->getRetainedAmtPayment() > 0) {

                // Do we still have any MOA?
                $amtMOA = Registration::loadLodgingBalance($dbh, $visit->getIdRegistration());

                if ($amtMOA >= abs($this->pmp->getRetainedAmtPayment())) {

                    // Refund the MOA amount
                    $this->moaRefundAmt = abs($this->pmp->getRetainedAmtPayment());
                    $invLine = new ReimburseInvoiceLine();
                    $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, (0 - $this->moaRefundAmt)), 1, 'Payout');

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                    $this->invoice->addLine($dbh, $invLine, $uS->username);
                }
            }

            // Room Charges
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut) {
                // Checked out or checking out...

                if ($this->pmp->getTotalRoomChg() > 0 && $this->pmp->getFinalPaymentFlag() == FALSE && $this->pmp->getTotalPayment() == 0) {
                    $roomCharges = min(array(($this->depositRefundAmt + $this->moaRefundAmt - $this->pmp->getVisitFeePayment()), $this->pmp->getTotalRoomChg()));

                } else if ($this->pmp->getTotalRoomChg() > 0 && $this->pmp->getFinalPaymentFlag() == FALSE) {
                    $roomCharges = min(array($this->pmp->getRatePayment(), $this->pmp->getTotalRoomChg()));
                } else {
                    $roomCharges = $this->pmp->getTotalRoomChg();
                }

            } else {
                $roomCharges = $this->pmp->getRatePayment();
            }

            // Any charges?
            if ($roomCharges > 0) {
                // lodging

                // Collect room fees
                $this->pmp->visitCharges->sumPayments($dbh)
                        ->sumCurrentRoomCharge($dbh, $this->pmp->priceModel, $roomCharges, TRUE);


                $paidThruDT = new DateTime($visit->getArrivalDate());
                $paidThruDT->add(new DateInterval('P' . $this->pmp->visitCharges->getNightsPaid() . 'D'));
                $paidThruDT->setTime(0, 0, 0);

                $endPricingDT = new DateTime($paidThruDT->format('Y-m-d H:i:s'));
                $endPricingDT->setTime(0, 0, 0);
                $endPricingDT->add(new DateInterval('P' . $this->pmp->visitCharges->getNightsToPay() . "D"));

                $lodging = new Item($dbh, ItemId::Lodging, $roomCharges);
                $invLine = new RecurringInvoiceLine();
                $invLine->setUseDetail($uS->ShowLodgDates);
                $invLine->appendDescription($notes);
                $invLine->createNewLine($lodging, 1, $paidThruDT->format('Y-m-d H:i:s'), $endPricingDT->format('Y-m-d H:i:s'));

                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);

            }


            // Processing for checked out visits.
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut) {

                $housePaymentAmt = abs($this->pmp->getHouseDiscPayment());

                // Check for house payment
                if ($housePaymentAmt > 0 && $this->pmp->getFinalPaymentFlag()) {

                    $lodging = new Item($dbh, ItemId::Waive, (0 - $housePaymentAmt));
                    $invLine = new OneTimeInvoiceLine();
                    $invLine->createNewLine($lodging, 1, '');

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                    $this->invoice->addLine($dbh, $invLine, $uS->username);
                }



                // Overpayments
                $this->guestCreditAmt = abs($this->pmp->getGuestCredit());
                $overPaymemntAmt = abs($this->pmp->getOverPayment());
                $balanceWithCode = $this->pmp->getBalWith();

                // Credit some room fees?
                if ($this->guestCreditAmt > 0 &&
                        (($balanceWithCode != '' && $balanceWithCode != ExcessPay::Ignore) || $overPaymemntAmt == 0)) {

                    $invLine = new OneTimeInvoiceLine();
                    $invLine->createNewLine(new Item($dbh, ItemId::LodgingReversal, (0 - $this->guestCreditAmt)), 1, 'Lodging');
                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, $notes);
                    $this->invoice->addLine($dbh, $invLine, $uS->username);

                }


                // Process overpayments
                if ($overPaymemntAmt > 0 && $balanceWithCode != '' && $balanceWithCode != ExcessPay::Ignore) {

                    $excessPayTitle = readGenLookupsPDO($dbh, 'ExcessPays');
                    $invLineDesc = 'Donation';
                    if (isset($excessPayTitle[$balanceWithCode])) {
                        $invLineDesc = $excessPayTitle[$balanceWithCode][1];
                    }

                    if ($balanceWithCode == ExcessPay::Hold) {
                        // Money on accoount

                        $invLine = new HoldInvoiceLine();
                        $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, $overPaymemntAmt), 1, $invLineDesc);

                        $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, $notes);
                        $this->invoice->addLine($dbh, $invLine, $uS->username);

                    } else if ($balanceWithCode == ExcessPay::RoomFund) {
                        // make a donation payment
                        $invLine = new OneTimeInvoiceLine();
                        $invLine->createNewLine(new Item($dbh, ItemId::LodgingDonate, $overPaymemntAmt), 1);

                        $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, $notes);
                        $this->invoice->addLine($dbh, $invLine, $uS->username);

                    } else if ($balanceWithCode == ExcessPay::Refund) {

                        $credit = $this->guestCreditAmt + $this->depositRefundAmt + $this->moaRefundAmt;

                        // Any payment amount should be zero
                        if ($this->pmp->getTotalPayment() > 0) {
                            throw new Hk_Exception_Payment('Cannot make a payment and get a refund at the same time.  ');
                        }

                        // Don't pay out more than the specific credits.
                        if ($credit > 0 && $credit >= $overPaymemntAmt) {

                            $this->refundAmt = $overPaymemntAmt;
                            $this->pmp->setTotalPayment(0 - $overPaymemntAmt);

                            // Here is where we look for the reimbursment pay type.
                            $this->pmp->setPayType($this->pmp->getRtnPayType());
                            $this->pmp->setIdToken($this->pmp->getRtnIdToken());
                            $this->pmp->setChargeAcct($this->pmp->getRtnChargeAcct());
                            $this->pmp->setChargeCard($this->pmp->getRtnChargeCard());

                            if ($uS->returnId < 1) {
                                throw new Hk_Exception_Payment('ReturnPayorId not set in the site configuration file');
                            }
                            $this->pmp->setIdInvoicePayor($uS->returnId);

                        }
                    }
                }
            }
        }

        // Include other invoices?
        $unpaidInvoices = $this->pmp->getInvoicesToPay();

        if (count($unpaidInvoices) > 0) {

            if (is_null($this->invoice)) {
                $this->invoice = $unpaidInvoices[0];
                $this->invoice->setBillDate($dbh, NULL, $uS->username, $notes);

                unset($unpaidInvoices[0]);
            }

            // Combine any other invoices
            foreach ($unpaidInvoices as $u) {
                $u->delegateTo($dbh, $this->invoice, $uS->username);
            }

        }


        if (is_null($this->invoice) === FALSE) {

            // Money back?
            if ($this->pmp->getTotalPayment() < 0 && $this->pmp->getBalWith() != ExcessPay::Refund) {

                // Not authorized.
                try {
                    $this->invoice->deleteInvoice($dbh, $uS->username);
                } catch (Hk_Exception_Payment $hkex) {
                    // do nothing
                }

                $this->invoice = NULL;

            } else {

                $this->invoice->setAmountToPay($this->pmp->getTotalPayment());
                $this->invoice->updateInvoiceStatus($dbh, $uS->username);
            }

        }

        return $this->invoice;
    }


    protected function getInvoice(\PDO $dbh, $payor, $groupId, $orderNumber, $suborderNumber, $username, $desc = '', $notes = '', $payDate = '') {

        if (is_null($this->invoice)) {

            if ($payDate == '') {
                $payDate = date('Y-m-d H:i:s');
            }

            $this->invoice = new Invoice($dbh);

            $this->invoice->newInvoice(
                    $dbh,
                    0,
                    $payor,
                    $groupId,
                    $orderNumber,
                    $suborderNumber,
                    $notes,
                    $payDate,
                    $username,
                    $desc
            );
        }

        return $this->invoice;
    }

    public function makeHousePayment(\PDO $dbh, $postBackPage, $paymentDate = '') {

        if ($this->hasInvoice()) {

            try {

                // Make the payment
                $payResult = PaymentSvcs::payAmount($dbh, $this->invoice, $this->pmp, $postBackPage, $paymentDate);
                $payResult->setReplyMessage($payResult->getDisplayMessage() . '  ' . $this->result);


            } catch (SoapFault $sf) {

                $payResult = new PaymentResult(0, 0, 0);
                $payResult->setReplyMessage("Payment Error = " . $sf->getMessage());

            } catch (Exception $exPay) {

                $payResult = new PaymentResult(0, 0, 0);
                $payResult->setReplyMessage("Payment Error = " . $exPay->getMessage());

            }

        } else {

            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setReplyMessage($this->result);
        }

        return $payResult;

    }

    public function makeHouseReturn(\PDO $dbh, $paymentDate = '') {

        if (! $this->hasInvoice()) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setStatus(PaymentResult::ERROR);
            $rtnResult->setReplyMessage($this->result);

            return $rtnResult;
        }

        // Return from the Hosue
        


        try {

            $rtnResult = PaymentSvcs::returnAmount($dbh, $this->invoice, $this->pmp, $paymentDate);

        } catch (SoapFault $sf) {

            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage("Return Error = " . $sf->getMessage());

        } catch (Exception $exPay) {

            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage("Return Error = " . $exPay->getMessage());

        }

        return $rtnResult;

    }

    public function hasInvoice() {
        return !is_null($this->invoice);
    }

    public function getInvoiceObj() {
        return $this->invoice;
    }

    public function getInvoiceStatus() {
        if ($this->hasInvoice()) {
            return $this->invoice->getStatus();
        }
        return 'null';
    }
}

class PaymentManagerPayment {

    protected $visitFeePayment;
    protected $keyDepositPayment;
    protected $depositRefundAmt;
    protected $ratePayment;
    protected $retainedAmtPayment;
    protected $houseDiscPayment;
    protected $totalPayment;
    protected $overPayment;
    protected $guestCredit;
    protected $refundAmount;
    protected $refundPayType;
    protected $totalRoomChg;

    protected $payInvoices;
    protected $payType;
    protected $rtnPayType;
    protected $payNotes;
    protected $payDate;
    protected $idToken = 0;
    protected $rtnIdToken = 0;
    protected $idInvoicePayor;
    protected $checkNumber = '';
    protected $transferAcct = '';
    protected $chargeCard = '';
    protected $rtnChargeCard = '';
    protected $chargeAcct = '';
    protected $rtnChargeAcct = '';
    protected $finalPaymentFlag;
    protected $newCardOnFile;
    protected $cashTendered;
    protected $newInvoice;
    protected $invoiceNotes;
    protected $balWith;

    /**
     *
     * @var PriceModel
     */
    public $priceModel;

    /**
     *
     * @var VisitCharges
     */
    public $visitCharges;

    public function __construct($payType) {
        $this->visitFeePayment = 0;
        $this->keyDepositPayment = 0;
        $this->ratePayment = 0;
        $this->totalPayment = 0;
        $this->cashTendered = 0;
        $this->retainedAmtPayment = 0;
        $this->depositRefundAmt = 0;
        $this->totalRoomChg = 0;
        $this->refundAmount = 0;


        $this->payInvoices = array();
        $this->idInvoicePayor = 0;
        $this->setPayType($payType);
        $this->newCardOnFile = FALSE;
        $this->finalPaymentFlag = FALSE;
        $this->invoiceNotes = '';
        $this->balWith = '';
        $this->payDate = '';
        $this->payNotes = '';

    }


    public function getInvoicesToPay() {
        return $this->payInvoices;
    }

    public function addInvoiceByNumber(\PDO $dbh, $invoiceNumber) {

        if ($invoiceNumber != '' && $invoiceNumber != 0) {
            $invoice = new Invoice($dbh, $invoiceNumber);
            $this->addInvoice($invoice);
        }
    }

    public function addInvoice(Invoice $invoice) {

        if ($invoice->getStatus() == InvoiceStatus::Unpaid)  {
            $this->payInvoices[] = $invoice;
        }
    }

    public function getBalWith() {
        return $this->balWith;
    }

    public function setBalWith($balWith) {
        $this->balWith = $balWith;
        return $this;
    }

    public function getRetainedAmtPayment() {
        return $this->retainedAmtPayment;
    }

    public function setRetainedAmtPayment($retainedAmtPayment) {
        $this->retainedAmtPayment = abs($retainedAmtPayment);
        return $this;
    }

    public function getRefundAmount() {
        return $this->refundAmount;
    }

    public function setRefundAmount($refundAmount) {
        $this->refundAmount = $refundAmount;
        return $this;
    }

    public function getIdInvoicePayor() {
        return $this->idInvoicePayor;
    }

    public function setIdInvoicePayor($idInvoicePayor) {
        $this->idInvoicePayor = $idInvoicePayor;
        return $this;
    }

    public function setInvoiceNotes($invoiceNotes) {
        $this->invoiceNotes = $invoiceNotes;
        return $this;
    }

    public function getInvoiceNotes() {
        return $this->invoiceNotes;
    }

    public function getPriceModel() {
        return $this->priceModel;
    }

    public function setPriceModel(PriceModel $priceModel) {
        $this->priceModel = $priceModel;
        return $this;
    }

    public function getVisitCharges() {
        return $this->visitCharges;
    }

    public function setVisitCharges(VisitCharges $visitCharges) {
        $this->visitCharges = $visitCharges;
        return $this;
    }

    public function getTotalPayment() {
        return $this->totalPayment;
    }

    public function setTotalPayment($totalPayment) {
        $this->totalPayment = $totalPayment;
        return $this;
    }

    public function getNewInvoice() {
        return $this->newInvoice;
    }

    public function setNewInvoice($newInvoice) {
        $this->newInvoice = $newInvoice;
        return $this;
    }

    public function getOverPayment() {
        return $this->overPayment;
    }

    public function setOverPayment($overPayment) {
        $this->overPayment = $overPayment;
        return $this;
    }

    public function getGuestCredit() {
        return $this->guestCredit;
    }

    public function setGuestCredit($guestCredit) {
        $this->guestCredit = $guestCredit;
        return $this;
    }


    public function getNewCardOnFile() {
        return $this->newCardOnFile;
    }

    public function setNewCardOnFile($newCardOnFile) {
        $this->newCardOnFile = $newCardOnFile;
        return $this;
    }

    public function getCashTendered() {
        return $this->cashTendered;
    }

    public function setCashTendered($cashTendered) {
        $this->cashTendered = $cashTendered;
        return $this;
    }


    public function getChargeCard() {
        if ($this->getPayType() == PayType::ChargeAsCash) {
            return $this->chargeCard;
        }
        return '';
    }

    public function getChargeAcct() {
        if ($this->getPayType() == PayType::ChargeAsCash) {
            return $this->chargeAcct;
        }
        return '';
    }

    public function setChargeCard($chargeCard) {
        $this->chargeCard = trim($chargeCard);
        return $this;
    }

    public function setChargeAcct($v) {
        $chargeAcct = trim($v);

        if (strlen($chargeAcct) > 4) {
            $chargeAcct = substr($chargeAcct, strlen($chargeAcct) - 4, 4);
        }
        $this->chargeAcct = $chargeAcct;
        return $this;
    }


    public function getFinalPaymentFlag() {
        return $this->finalPaymentFlag;
    }

    public function setFinalPaymentFlag($finalPaymentFlag) {
        $this->finalPaymentFlag = $finalPaymentFlag;
        return $this;
    }

    public function getVisitFeePayment() {
        return $this->visitFeePayment;
    }

    public function getKeyDepositPayment() {
        return $this->keyDepositPayment;
    }

    public function getRatePayment() {
        return $this->ratePayment;
    }

    public function getPayType() {
        return $this->payType;
    }

    public function getPayDate() {

        if ($this->payDate != '') {
            $d = date('Y-m-d H:i:s', strtotime($this->payDate));
        } else {
            $d = date('Y-m-d H:i:s');
        }

        return $d;
    }

    public function getIdToken() {
        return $this->idToken;
    }

    public function getCheckNumber() {
        return $this->checkNumber;
    }

    public function getTransferAcct() {
        return $this->transferAcct;
    }

    public function setVisitFeePayment($visitFeePayment) {
        $this->visitFeePayment = $visitFeePayment;
        return $this;
    }

    public function getPayNotes() {
        return $this->payNotes;
    }

    public function setPayNotes($payNotes) {
        $this->payNotes = trim($payNotes);
        return $this;
    }


    public function setKeyDepositPayment($keyDepositPayment) {
        $this->keyDepositPayment = $keyDepositPayment;
        return $this;
    }

    public function setRatePayment($ratePayment) {
        $this->ratePayment = $ratePayment;
        return $this;
    }

    public function setPayType($payType) {

        $uS = Session::getInstance();

        // Check for Charge as Cash case.
        if ($payType == PayType::Charge && $uS->ccgw == ''){
           $payType = PayType::ChargeAsCash;
        }

        $this->payType = $payType;
        return $this;
    }

    public function getRtnPayType() {
        return $this->rtnPayType;
    }

    public function getRtnChargeCard() {
        return $this->rtnChargeCard;
    }

    public function getRtnChargeAcct() {
        return $this->rtnChargeAcct;
    }

    public function setRtnPayType($rtnPayType) {

        $uS = Session::getInstance();

        // Check for Charge as Cash case.
        if ($rtnPayType == PayType::Charge && $uS->ccgw == ''){
           $rtnPayType = PayType::ChargeAsCash;
        }

        $this->rtnPayType = $rtnPayType;
    }

    public function setRtnChargeCard($rtnChargeCard) {
        $this->rtnChargeCard = $rtnChargeCard;
        return $this;
    }

    public function setRtnChargeAcct($rtnChargeAcct) {

        $chargeAcct = trim($rtnChargeAcct);

        if (strlen($chargeAcct) > 4) {
            $chargeAcct = substr($chargeAcct, strlen($chargeAcct) - 4, 4);
        }

        $this->rtnChargeAcct = $chargeAcct;
        return $this;
    }


    public function setPayDate($payDate) {
        $this->payDate = $payDate;
        return $this;
    }

    public function setIdToken($idToken) {
        $this->idToken = $idToken;
        return $this;
    }

    public function getRtnIdToken() {
        return $this->rtnIdToken;
    }

    public function setRtnIdToken($rtnIdToken) {
        $this->rtnIdToken = $rtnIdToken;
        return $this;
    }

    public function setCheckNumber($checkNumber) {
        $this->checkNumber = trim($checkNumber);
        return $this;
    }

    public function setTransferAcct($transferAcct) {
        $this->transferAcct = trim($transferAcct);
        return $this;
    }

    public function getHouseDiscPayment() {
        return $this->houseDiscPayment;
    }

    public function setHouseDiscPayment($houseDiscPayment) {
        $this->houseDiscPayment = $houseDiscPayment;
        return $this;
    }

    public function getDepositRefundAmt() {
        return $this->depositRefundAmt;
    }

    public function setDepositRefundAmt($depositRefundAmt) {
        $this->depositRefundAmt = $depositRefundAmt;
        return $this;
    }

    public function getTotalRoomChg() {
        return $this->totalRoomChg;
    }

    public function setTotalRoomChg($totalRoomChg) {
        $this->totalRoomChg = $totalRoomChg;
        return $this;
    }


}