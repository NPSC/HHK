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
     * @throws RuntimeException::
     */
    public function createInvoice(\PDO $dbh, $visit, $idPayor, $notes = '') {

        $uS = Session::getInstance();
        $this->invoice = NULL;

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

                if ($roomChargesTaxable > 0 && $this->pmp->getInvoicePayorTaxExempt() == false) {

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

    public function makeHousePayment(\PDO $dbh, $postBackPage) {

        if ($this->hasInvoice()) {

            try {

                // Make the payment
                $payResult = PaymentSvcs::payAmount($dbh, $this->invoice, $this->pmp, $postBackPage);
                $payResult->setReplyMessage($payResult->getDisplayMessage() . '  ' . $this->result);


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

    public function makeHouseReturn(\PDO $dbh, $paymentDate) {

        if (! $this->hasInvoice()) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setStatus(PaymentResult::ERROR);
            $rtnResult->setReplyMessage($this->result);

            return $rtnResult;
        }

        // Return from the Hosue
        try {

            $rtnResult = PaymentSvcs::returnAmount($dbh, $this->invoice, $this->pmp, $paymentDate);


        } catch (\Exception $exPay) {

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
?>