<?php
/**
 * PaymentManager.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
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
    public $vatReimburseAmt = 0;

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
     * @param string $notes
     * @return Invoice
     * @throws Hk_Exception_Runtime
     */
    public function createInvoice(\PDO $dbh, $visit, $idPayor, $notes = '') {

        $uS = Session::getInstance();
        $this->invoice = NULL;

        if ($idPayor <= 0) {
            $this->result = 'Undefined Payor.  ';
            return $this->invoice;
        }

        $this->pmp->setIdInvoicePayor($idPayor);


        // Process a visit payment
        if (is_null($visit) === FALSE) {

            // Collect room fees
            $this->pmp->visitCharges->sumPayments($dbh)
                    ->sumCurrentRoomCharge($dbh, $this->pmp->priceModel, 0, TRUE);


            // Taxed items
            $vat = new ValueAddedTax($dbh);
            $taxRates = $vat->getTaxedItemSums($visit->getIdVisit(), $this->pmp->visitCharges->getNightsStayed());
            $taxRate = isset($taxRates[ItemId::Lodging]) ? $taxRates[ItemId::Lodging]: 0;

            // Collect account information on visit.
            $roomAccount = new CurrentAccount(
                    $visit->getVisitStatus(),
                    ($uS->VisitFee &&  $this->pmp->visitCharges->getVisitFeeCharged() > 0 ? TRUE : FALSE),
                    TRUE,
                    ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily ? TRUE : FALSE)
            );


            $roomAccount->load($this->pmp->visitCharges, $vat);
            $roomAccount->setDueToday();


            // Visit Fee Payments
            if ($this->pmp->getVisitFeePayment() > 0) {
                // cleaning fee
                $visitFeeItem = new Item($dbh, ItemId::VisitFee, $this->pmp->getVisitFeePayment());
                $invLine = new OneTimeInvoiceLine($uS->ShowLodgDates);
                $invLine->createNewLine($visitFeeItem, 1, $notes);

                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);

            }

            // Deposit payments
            if ($this->pmp->getKeyDepositPayment() > 0) {

                $invLine = new HoldInvoiceLine($uS->ShowLodgDates);
                $invLine->createNewLine(new Item($dbh, ItemId::KeyDeposit, $this->pmp->getKeyDepositPayment()), 1, $notes);

                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);

            }

            // Deposit Refunds - only if checked out...
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut && abs($this->pmp->getDepositRefundAmt()) > 0 && $this->pmp->getBalWith() != ExcessPay::Ignore) {
                // Return the deposit
                $this->depositRefundAmt = abs($this->pmp->getDepositRefundAmt());

                $invLine = new ReimburseInvoiceLine($uS->ShowLodgDates);
                $invLine->createNewLine(new Item($dbh, ItemId::DepositRefund, (0 - $this->depositRefundAmt)), 1, $notes);
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
                    $invLine = new ReimburseInvoiceLine($uS->ShowLodgDates);
                    $invLine->appendDescription($notes);
                    $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, (0 - $this->moaRefundAmt)), 1, 'Payout');

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                    $this->invoice->addLine($dbh, $invLine, $uS->username);
                }
            }

            // VAT reimbursements
            if ($this->pmp->getReimburseTaxCb() && count($roomAccount->getReimburseTax()) > 0) {

                foreach ($roomAccount->getReimburseTax() as $taxingId => $sum) {

                    if (abs($sum) > 0) {

                        $this->vatReimburseAmt += abs($sum);

                            $invLine = new TaxInvoiceLine($uS->ShowLodgDates);
                            $invLine->createNewLine(new Item($dbh, $taxingId, (0 - $sum)), 1, 'Reimburse');
                            $invLine->setSourceItemId(ItemId::Lodging);
                            $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                            $this->invoice->addLine($dbh, $invLine, $uS->username);

                    }
                }
            }


            // Just use what they are willing to pay as the charge.
            $roomChargesPreTax = $this->pmp->getRatePayment();
            $housePaymentAmt = 0;

            // Room Charges are different for checked out
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut) {
                // Checked out or checking out... Room charges.

                if ($roomAccount->getRoomFeeBalance() > 0) {

                    if ($this->pmp->getFinalPaymentFlag() == TRUE) {    // means is house waive checked.

                        // House waive checked, charge the entire amount due
                        $roomChargesPreTax = $roomAccount->getRoomFeeBalance();

                    } else {

                        $depPreTax = round($this->depositRefundAmt / (1 + $taxRate), 2);
                        $moaPreTax = round($this->moaRefundAmt / (1 + $taxRate), 2);
                        $vatPreTax = round($this->vatReimburseAmt / (1 + $taxRate), 2);

                        // is there too much paid
                        if ($this->pmp->getRatePayment() + $depPreTax + $moaPreTax + $vatPreTax > $roomAccount->getRoomFeeBalance()) {
                            $roomChargesPreTax = $roomAccount->getRoomFeeBalance();
                        } else {
                            $roomChargesPreTax = $this->pmp->getRatePayment() + $depPreTax + $moaPreTax + $vatPreTax;
                        }
                    }

                } else {
                    // Checked out, and no room charges to pay.
                    $roomChargesPreTax = 0;
                }

                // Determine House Waive
                if ($this->pmp->getFinalPaymentFlag()) {
                    $housePaymentAmt = $this->pmp->getHouseDiscPayment();
                }
            }

            // Any charges?
            if ($roomChargesPreTax > 0) {
                // lodging

                // Collect room charges
                $this->pmp->visitCharges->sumCurrentRoomCharge($dbh, $this->pmp->priceModel, $roomChargesPreTax, TRUE);

                $nitesPaid = $this->pmp->visitCharges->getNightsPaid();

                if ($nitesPaid < 0) {
                    $nitesPaid = 0;
                }

                $paidThruDT = new \DateTime($visit->getArrivalDate());
                $paidThruDT->add(new \DateInterval('P' . $nitesPaid . 'D'));
                $paidThruDT->setTime(0, 0, 0);

                $endPricingDT = new \DateTime($paidThruDT->format('Y-m-d'));
                $endPricingDT->setTime(0, 0, 0);
                $endPricingDT->add(new \DateInterval('P' . $this->pmp->visitCharges->getNightsToPay() . "D"));

                $lodging = new Item($dbh, ItemId::Lodging, $roomChargesPreTax);

                $invLine = new RecurringInvoiceLine($uS->ShowLodgDates);

                $invLine->appendDescription($notes);
                $invLine->createNewLine($lodging, 1, $paidThruDT->format('Y-m-d H:i:s'), $endPricingDT->format('Y-m-d H:i:s'), $this->pmp->visitCharges->getNightsToPay());

                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);

                // Taxes
                if ($this->pmp->getFinalPaymentFlag() && $housePaymentAmt > 0) {
                    $roomChargesTaxable = $roomChargesPreTax - $housePaymentAmt - $this->pmp->getPayInvoicesAmt();
                } else {
                    // Add the tax reimbursement, if any, in order to tax it.
                    $roomChargesTaxable = $roomChargesPreTax;
                }

                if ($roomChargesTaxable > 0) {

                    foreach ($vat->getCurrentTaxedItems($visit->getIdVisit(), $this->pmp->visitCharges->getNightsStayed()) as $t) {

                        if ($t->getIdTaxedItem() == ItemId::Lodging) {
                            $taxInvoiceLine = new TaxInvoiceLine();
                            $taxInvoiceLine->createNewLine(new Item($dbh, $t->getIdTaxingItem(), $roomChargesTaxable), $t->getDecimalTax(), '(' . $t->getTextPercentTax() . ')');
                            $taxInvoiceLine->setSourceItemId(ItemId::Lodging);
                            $this->invoice->addLine($dbh, $taxInvoiceLine, $uS->username);
                        }
                    }
                }
            }

            // Processing for checked out visits.
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut) {


                // Check for house payment
                if ($housePaymentAmt > 0 && $this->pmp->getFinalPaymentFlag()) {

                    $waive = new Item($dbh, ItemId::Waive, (0 - $housePaymentAmt));
                    $invLine = new ReimburseInvoiceLine($uS->ShowLodgDates);
                    $invLine->createNewLine($waive, 1, $notes);

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                    $this->invoice->addLine($dbh, $invLine, $uS->username);
                }


                // Overpayments
                $this->guestCreditAmt = abs($this->pmp->getGuestCredit());
                $overPaymemntAmt = abs($this->pmp->getOverPayment());


                // Credit some room fees?
                if ($this->guestCreditAmt > 0 &&
                        ($this->pmp->getBalWith() != ExcessPay::Ignore || $overPaymemntAmt == 0)) {

                    $reversalAmt = $this->guestCreditAmt;
                    $revTaxAmt = array();

                    //$taxRates = $vat->getTaxedItemSums($visit->getIdVisit(), 0);  // Get all taxes, no timeouts.
                    foreach ($vat->getAllTaxedItems($visit->getIdVisit()) as $t) {

                        if ($t->getIdTaxedItem() == ItemId::Lodging) {
                            $revTaxAmt[$t->getIdTaxingItem()] = round($reversalAmt / (1 + $t->getDecimalTax()), 2);
                            $reversalAmt -= $revTaxAmt[$t];
                        }
                    }

                    if (count($revTaxAmt) > 0) {
                        // we caught taxes.  Reduce reversalAmt by the sum of tax rates.

                        $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes);

                        // Add the tax lines back into the mix
                        foreach ($vat->getAllTaxedItems($visit->getIdVisit()) as $t) {

                            if ($t->getIdTaxedItem() == ItemId::Lodging) {
                                $taxInvoiceLine = new TaxInvoiceLine();
                                $taxInvoiceLine->createNewLine(new Item($dbh, $t->getIdTaxingItem(), (0 - $revTaxAmt[$t->getIdTaxingItem()])), 1, '(' . $t->getTextPercentTax() . ')');
                                $taxInvoiceLine->setSourceItemId(ItemId::LodgingReversal);
                                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes);
                                $this->invoice->addLine($dbh, $taxInvoiceLine, $uS->username);
                            }
                        }
                    }

                    // Add reversal itself
                    $invLine = new OneTimeInvoiceLine();
                    $invLine->createNewLine(new Item($dbh, ItemId::LodgingReversal, (0 - $reversalAmt)), 1, 'Lodging');

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes);
                    $this->invoice->addLine($dbh, $invLine, $uS->username);

                }


                // Process overpayments
                if ($overPaymemntAmt > 0 && $this->pmp->getBalWith() != ExcessPay::Ignore) {

                    if ($this->pmp->getBalWith() == ExcessPay::Hold) {
                        // Money on accoount

                        $invLine = new HoldInvoiceLine($uS->ShowLodgDates);
                        $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, $overPaymemntAmt), 1, $notes);

                        $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes);
                        $this->invoice->addLine($dbh, $invLine, $uS->username);

                    } else if ($this->pmp->getBalWith() == ExcessPay::RoomFund) {
                        // make a donation payment
                        $invLine = new OneTimeInvoiceLine();
                        $invLine->createNewLine(new Item($dbh, ItemId::LodgingDonate, $overPaymemntAmt), 1);

                        $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes);
                        $this->invoice->addLine($dbh, $invLine, $uS->username);

                    } else if ($this->pmp->getBalWith() == ExcessPay::Refund && $this->hasInvoice()) {

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
                            $this->pmp->setTransferAcct($this->pmp->getRtnTransferAcct());

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


        if ($this->hasInvoice()) {

            if ($this->pmp->getTotalPayment() == 0 && $this->pmp->getBalWith() == ExcessPay::Refund) {
                // Adjust total payment
                $this->pmp->setTotalPayment(0 - $this->pmp->getRefundAmount());
            }

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
                $this->invoice->setSoldToId($this->pmp->getIdInvoicePayor());
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


        } catch (Exception $exPay) {

            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage("Return Error = " . $exPay->getMessage());

        }

        return $rtnResult;

    }

    public function setInvoice($invoice) {
        $this->invoice = $invoice;
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
    protected $rateTax;
    protected $retainedAmtPayment;
    protected $houseDiscPayment;
    protected $totalPayment;
    protected $overPayment;
    protected $guestCredit;
    protected $refundAmount;
    protected $totalRoomChg;
    protected $payInvoicesAmt;
    protected $totalCharges;

    protected $payInvoices;
    protected $payType;
    protected $rtnPayType;
    protected $payNotes;
    protected $payDate;
    protected $idToken = 0;
    protected $rtnIdToken = 0;
    protected $idInvoicePayor;
    protected $checkNumber = '';
    protected $rtnCheckNumber = '';
    protected $transferAcct = '';
    protected $rtnTransferAcct = '';
    protected $chargeCard = '';
    protected $rtnChargeCard = '';
    protected $chargeAcct = '';
    protected $rtnChargeAcct = '';
    protected $finalPaymentFlag;
    protected $reimburseTaxCb;
    protected $newCardOnFile;
    protected $cashTendered;
    protected $newInvoice;
    protected $invoiceNotes;
    protected $balWith;
    protected $manualKeyEntry;
    protected $cardHolderName = '';
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
        $this->rateTax = 0;
        $this->totalPayment = 0;
        $this->totalCharges = 0;
        $this->cashTendered = 0;
        $this->retainedAmtPayment = 0;
        $this->depositRefundAmt = 0;
        $this->totalRoomChg = 0;
        $this->refundAmount = 0;


        $this->payInvoices = array();
        $this->payInvoicesAmt = 0;
        $this->idInvoicePayor = 0;
        $this->setPayType($payType);
        $this->newCardOnFile = FALSE;
        $this->finalPaymentFlag = FALSE;
        $this->reimburseTaxCb = FALSE;
        $this->manualKeyEntry = FALSE;
        $this->reimburseTaxCb = FALSE;
        $this->invoiceNotes = '';
        $this->balWith = '';
        $this->payDate = '';
        $this->payNotes = '';

    }


    public function getInvoicesToPay() {
        return $this->payInvoices;
    }

    public function addInvoiceByNumber(\PDO $dbh, $invoiceNumber, $paymentAmt) {

        if ($invoiceNumber != '' && $invoiceNumber != 0) {
            $invoice = new Invoice($dbh, $invoiceNumber);
            if (abs($paymentAmt) > abs($invoice->getBalance())) {
                $paymentAmt = $invoice->getBalance();
            }
            $this->addInvoice($invoice, $paymentAmt);
        }
    }

    protected function addInvoice(Invoice $invoice, $paymentAmt) {

        if ($invoice->getStatus() == InvoiceStatus::Unpaid)  {
            $this->payInvoices[] = $invoice;
            $this->payInvoicesAmt += $paymentAmt;
        }
    }

    public function getPayInvoicesAmt() {
        return $this->payInvoicesAmt;
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

    public function getTotalCharges() {
        return $this->totalCharges;
    }

    public function setTotalCharges($totalCharges) {
        $this->totalCharges = $totalCharges;
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

    public function getReimburseTaxCb() {
        return $this->reimburseTaxCb;
    }

    public function setReimburseTaxCb($reimburseTaxCb) {
        $this->reimburseTaxCb = $reimburseTaxCb;
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

    public function getRateTax() {
        return $this->rateTax;
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

    public function getRtnCheckNumber() {
        return $this->rtnCheckNumber;
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

    public function setRateTax($rateTax) {
        $this->rateTax = $rateTax;
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

    public function getRtnTransferAcct() {
        return $this->rtnTransferAcct;
    }

    public function setRtnTransferAcct($rtnTransferAcct) {
        $this->rtnTransferAcct = $rtnTransferAcct;
        return $this;
    }


    public function setCheckNumber($checkNumber) {
        $this->checkNumber = trim($checkNumber);
        return $this;
    }

    public function setRtnCheckNumber($checkNumber) {
        $this->rtnCheckNumber = trim($checkNumber);
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

    function getManualKeyEntry() {
        return $this->manualKeyEntry;
    }

    function setManualKeyEntry($manualKeyEntry) {
        $this->manualKeyEntry = $manualKeyEntry;
        return $this;
    }

    public function getCardHolderName() {
        return $this->cardHolderName;
    }

    public function setCardHolderName($cardHolderName) {
        $this->cardHolderName = $cardHolderName;
        return $this;
    }



}
