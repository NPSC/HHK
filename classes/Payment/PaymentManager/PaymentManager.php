<?php

namespace HHK\Payment\PaymentManager;

use HHK\House\Registration;
use HHK\House\Visit\Visit;
use HHK\Payment\PaymentResult\{PaymentResult, ReturnResult};
use HHK\Payment\PaymentSvcs;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\Invoice\InvoiceLine\{HoldInvoiceLine, OneTimeInvoiceLine, RecurringInvoiceLine, ReimburseInvoiceLine, TaxInvoiceLine};
use HHK\Purchase\{CurrentAccount, Item, ValueAddedTax};
use HHK\SysConst\ExcessPay;
use HHK\SysConst\ItemId;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\VisitStatus;
use HHK\SysConst\VolMemberType;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\Exception\PaymentException;


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

    /**
     * Summary of result
     * @var string
     */
    public $result = '';
    /**
     * Amount refunded to guest
     * @var float
     */
    public $refundAmt = 0.0;
    /**
     * deposit Refund Amount
     * @var float
     */
    public $depositRefundAmt = 0.0;
    /**
     * guest Credit Amount
     * @var float
     */
    public $guestCreditAmt = 0.0;
    /**
     * MOA Refund Amount
     * @var float
     */
    public $moaRefundAmt = 0.0;
    /**
     * Tax Reimbursement Amount
     * @var float
     */
    public $vatReimburseAmt = 0.0;

    /**
     * Data from paying today
     * @var PaymentManagerPayment
     */
    public $pmp;

    /**
     * My invoice
     * @var Invoice
     */
    protected $invoice;


    /**
     * Summary of __construct
     * @param PaymentManagerPayment $pmp
     */
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
     * @throws RuntimeException::
     */
    public function createInvoice(\PDO $dbh, $visit, $idPayor, $notes = '') {

        $uS = Session::getInstance();
        $this->invoice = NULL;

        // Short circuit "ignore" payments  9/22/2022 EKC
        if ($this->pmp->getBalWith() == ExcessPay::Ignore) {
            return $this->invoice;
        }

        // Define the invoice payor
        if ($idPayor <= 0) {
            $this->result = 'Undefined Payor.  ';
            return $this->invoice;
        }
        $this->pmp->setIdInvoicePayor($idPayor);

        //set Tax Exempt
        $stmt = $dbh->query("SELECT n.idName, nd.tax_exempt " .
            " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
            " JOIN name_demog nd on n.idName = nd.idName  ".
            " where n.Member_Status='a' and n.Record_Member = 1 and n.idName='" . $idPayor . "'");

        $payor = $stmt->fetch(\PDO::FETCH_ASSOC);

        if(isset($payor['tax_exempt']) && $payor['tax_exempt'] == 1){
            $this->pmp->setInvoicePayorTaxExempt(true);
        } else {
            $this->pmp->setInvoicePayorTaxExempt(false);
        }

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
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut && abs($this->pmp->getDepositRefundAmt()) > 0) {
                // Return the deposit
                $this->depositRefundAmt = abs($this->pmp->getDepositRefundAmt());

                $invLine = new ReimburseInvoiceLine($uS->ShowLodgDates);
                $invLine->createNewLine(new Item($dbh, ItemId::DepositRefund, (0 - $this->depositRefundAmt)), 1, $notes);
                $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);
            }

            // MOA refunds.
            if ($this->pmp->getRetainedAmtPayment() > 0) {

                // Do we still have any MOA?
                $amtMOA = Registration::loadLodgingBalance($dbh, $visit->getIdRegistration());
                $this->moaRefundAmt = $this->pmp->getRetainedAmtPayment();

                if ($amtMOA >= $this->moaRefundAmt) {

                    // Refund the MOA amount
                    $invLine = new ReimburseInvoiceLine($uS->ShowLodgDates);
                    $invLine->appendDescription($notes);
                    $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, (0 - $this->moaRefundAmt)), 1, 'Payout');

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                    $this->invoice->addLine($dbh, $invLine, $uS->username);

                } else {
                    $this->moaRefundAmt = 0;
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


            // Start with what they are willing to pay as the room charge.
            $roomChargesPreTax = $this->pmp->getRatePayment();
            $housePaymentAmt = 0;
            $roomTax = 0;

            // Room Charges are different for checked out
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut) {
                // Checked out or checking out... Room charges.

                if ($roomAccount->getRoomFeeBalance() > 0) {

                    if ($this->pmp->getFinalPaymentFlag() == TRUE) {    // means is house waive checked.

                        // House waive checked, charge the entire amount due
                        $roomChargesPreTax = $roomAccount->getRoomFeeBalance();

                    } else {

                        if ($this->pmp->getRatePayment() > $roomAccount->getRoomFeeBalance()) {
                            $roomChargesPreTax = $roomAccount->getRoomFeeBalance();
                        } else {
                            $roomChargesPreTax = $this->pmp->getRatePayment();  // + $depPreTax + $moaPreTax + $vatPreTax;
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

            // Invoice any room charges?
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

                // Taxes on room charges
                if ($this->pmp->getFinalPaymentFlag() && $housePaymentAmt > 0) {
                    $roomChargesTaxable = $roomChargesPreTax - $housePaymentAmt; // - $this->pmp->getPayInvoicesAmt();
                } else {
                    $roomChargesTaxable = $roomChargesPreTax;
                }

                if ($roomChargesTaxable > 0 && $this->pmp->getInvoicePayorTaxExempt() == false) {

                    foreach ($vat->getCurrentTaxedItems($visit->getIdVisit(), $this->pmp->visitCharges->getNightsStayed()) as $t) {

                        if ($t->getIdTaxedItem() == ItemId::Lodging) {
                            $taxInvoiceLine = new TaxInvoiceLine();
                            $taxInvoiceLine->createNewLine(new Item($dbh, $t->getIdTaxingItem(), $roomChargesTaxable), $t->getDecimalTax(), '(' . $t->getTextPercentTax() . ')');
                            $taxInvoiceLine->setSourceItemId(ItemId::Lodging);
                            $this->invoice->addLine($dbh, $taxInvoiceLine, $uS->username);

                            $roomTax += round($roomChargesTaxable * $t->getDecimalTax(), 2);
                        }
                    }
                }
            }

            // Final preperation
            if ($visit->getVisitStatus() == VisitStatus::CheckedOut) {
                // Processing for checked out visits.

                // Check for house payment
                if ($housePaymentAmt > 0 && $this->pmp->getFinalPaymentFlag()) {

                    $waive = new Item($dbh, ItemId::Waive, (0 - $housePaymentAmt));
                    $invLine = new ReimburseInvoiceLine($uS->ShowLodgDates);
                    $invLine->createNewLine($waive, 1, $notes);

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                    $this->invoice->addLine($dbh, $invLine, $uS->username);
                }


                // Guest credit amount - if any
                $this->guestCreditAmt = abs($this->pmp->getGuestCredit());


                // Credit some room fees?
                if ($this->guestCreditAmt > 0) {

                    $reversalAmt = $this->guestCreditAmt / (1 + $taxRate);

                    if ($reversalAmt !== $this->guestCreditAmt) {
                        // we caught taxes.  Reduce reversalAmt by the sum of tax rates.

                        $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes);

                        // Add the tax lines back into the mix
                        foreach ($vat->getCurrentTaxedItems($visit->getIdVisit(), $this->pmp->visitCharges->getNightsStayed()) as $t) {

                            if ($t->getIdTaxedItem() == ItemId::Lodging) {
                                $taxInvoiceLine = new TaxInvoiceLine();
                                $taxInvoiceLine->createNewLine(new Item($dbh, $t->getIdTaxingItem(), (0 - $reversalAmt)), $t->getDecimalTax(), '(' . $t->getTextPercentTax() . ')');
                                $taxInvoiceLine->setSourceItemId(ItemId::LodgingReversal);
                                $this->invoice->addLine($dbh, $taxInvoiceLine, $uS->username);
                            }
                        }
                    }

                    // Add reversal itself
                    $invLine = new OneTimeInvoiceLine();
                    $invLine->createNewLine(new Item($dbh, ItemId::LodgingReversal, (0 - $reversalAmt)), 1, $notes);
                    //$invLine->appendDescription($notes);
                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes);
                    $this->invoice->addLine($dbh, $invLine, $uS->username);

                }

                // Process overpayments
                $this->processOverpayments($dbh, abs($this->pmp->getOverPayment()), $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $notes);

            } else {
                // visit still checked in

                // Do we have any MOA overpayments?
                $payAmt = max($this->pmp->getPayInvoicesAmt(), 0) + $roomChargesPreTax + $roomTax + $this->pmp->getVisitFeePayment() + $this->pmp->getKeyDepositPayment();

                if ($this->moaRefundAmt > $payAmt) {

                    // invoice the remaining MOA as a new MOA payment
                    $remainingMOA = $this->moaRefundAmt - $payAmt;

                    $invLine = new HoldInvoiceLine($uS->ShowLodgDates);
                    $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, $remainingMOA), 1, 'Balance');

                    $this->getInvoice($dbh, $idPayor, $visit->getIdRegistration(), $visit->getIdVisit(), $visit->getSpan(), $uS->username, '', $notes, $this->pmp->getPayDate());
                    $this->invoice->addLine($dbh, $invLine, $uS->username);
                }

            }

        }  // end of visit

        // Include other invoices?
        $unpaidInvoices = $this->pmp->getInvoicesToPay();

        if (count($unpaidInvoices) > 0) {

            if ($this->invoice === null) {
                $this->invoice = $unpaidInvoices[0];
                $this->invoice->setBillDate($dbh, NULL, $uS->username, $notes);

                unset($unpaidInvoices[0]);
            }

            // Combine any other invoices
            foreach ($unpaidInvoices as $u) {
                $u->delegateTo($dbh, $this->invoice, $uS->username);
            }

        }

        // Final checks.
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
                } catch (PaymentException $hkex) {
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

    /**
     * Summary of processOverpayments
     * @param \PDO $dbh
     * @param mixed $overPaymemntAmt
     * @param int $idPayor
     * @param int $idRegistration
     * @param int $idVisit
     * @param int $visitSpan
     * @param string $notes
     * @throws \HHK\Exception\PaymentException
     * @return void
     */
    protected function processOverpayments(\PDO $dbh, $overPaymemntAmt, $idPayor, $idRegistration, $idVisit, $visitSpan, $notes) {

        $uS = Session::getInstance();

        if ($overPaymemntAmt > 0) {

            // Hold
            if ($this->pmp->getBalWith() == ExcessPay::Hold) {
                // Money on accoount

                $invLine = new HoldInvoiceLine($uS->ShowLodgDates);
                $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, $overPaymemntAmt), 1, $notes);

                $this->getInvoice($dbh, $idPayor, $idRegistration, $idVisit, $visitSpan, $uS->username, '', $notes);
                $this->invoice->addLine($dbh, $invLine, $uS->username);

                // Donation
            } else if ($this->pmp->getBalWith() == ExcessPay::RoomFund) {
                // make a donation payment
                $invLine = new OneTimeInvoiceLine();
                $invLine->createNewLine(new Item($dbh, ItemId::LodgingDonate, $overPaymemntAmt), 1);

                $this->getInvoice($dbh, $idPayor, $idRegistration, $idVisit, $visitSpan, $uS->username, '', $notes);
                $this->invoice->addLine($dbh, $invLine, $uS->username);

                // Refund
            } else if ($this->pmp->getBalWith() == ExcessPay::Refund && $this->hasInvoice()) {

                $credit = $this->guestCreditAmt + $this->depositRefundAmt + $this->moaRefundAmt;

                // Any payment amount should be zero
                if ($this->pmp->getTotalPayment() > 0) {
                    throw new PaymentException('Cannot make a payment and get a refund at the same time.  ');
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


    /**
     * Summary of getInvoice
     * @param \PDO $dbh
     * @param mixed $payor
     * @param mixed $groupId
     * @param mixed $orderNumber
     * @param mixed $suborderNumber
     * @param mixed $username
     * @param mixed $desc
     * @param mixed $notes
     * @param mixed $payDate
     * @return Invoice|mixed
     */
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
                    $desc,
                    $this->pmp->getInvoicePayorTaxExempt()
            );
        }

        return $this->invoice;
    }

    /**
     * Summary of makeHousePayment
     * @param \PDO $dbh
     * @param mixed $postBackPage
     * @return PaymentResult|null
     */
    public function makeHousePayment(\PDO $dbh, $postBackPage) {

        if ($this->hasInvoice()) {

            try {

                // Make the payment
                $payResult = PaymentSvcs::payAmount($dbh, $this->invoice, $this->pmp, $postBackPage);
                if (strlen($payResult->getDisplayMessage() . $this->result) > 0) {
                    $payResult->setReplyMessage($payResult->getDisplayMessage() . '  ' . $this->result);
                }

            } catch (\Exception $exPay) {

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

    /**
     * Summary of makeHouseReturn
     * @param \PDO $dbh
     * @param mixed $paymentDate
     * @return ReturnResult
     */
    public function makeHouseReturn(\PDO $dbh, $paymentDate, $resvId = 0) {

        if (! $this->hasInvoice()) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setStatus(PaymentResult::ERROR);
            $rtnResult->setReplyMessage($this->result);

            return $rtnResult;
        }

        // Return from the Hosue
        try {

            $rtnResult = PaymentSvcs::returnAmount($dbh, $this->invoice, $this->pmp, $paymentDate, $resvId);


        } catch (\Exception $exPay) {

            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage("Return Error = " . $exPay->getMessage());

        }

        return $rtnResult;

    }

    /**
     * Summary of setInvoice
     * @param mixed $invoice
     * @return void
     */
    public function setInvoice($invoice) {
        $this->invoice = $invoice;
    }

    /**
     * Summary of hasInvoice
     * @return bool
     */
    public function hasInvoice() {
        return !is_null($this->invoice);
    }

    /**
     * Summary of getInvoiceObj
     * @return Invoice|mixed
     */
    public function getInvoiceObj() {
        return $this->invoice;
    }

    /**
     * Summary of getInvoiceStatus
     * @return mixed
     */
    public function getInvoiceStatus() {
        if ($this->hasInvoice()) {
            return $this->invoice->getStatus();
        }
        return 'null';
    }
}
?>