<?php
namespace HHK\Payment\PaymentManager;

use HHK\sec\Labels;
use HHK\SysConst\ExcessPay;
use HHK\SysConst\ReservationStatus;
use HHK\Exception\PaymentException;
use HHK\Payment\Invoice\InvoiceLine\{HoldInvoiceLine, ReimburseInvoiceLine};
use HHK\Purchase\Item;
use HHK\SysConst\ItemId;
use HHK\sec\Session;
use HHK\House\Reservation\Reservation_1;

/**
 *
 * @author Eric
 *
 */
class ResvPaymentManager extends PaymentManager
{

    /**
     *
     * @param
     *            $pmp
     *
     */
    public function __construct($pmp)
    {
        parent::__construct($pmp);
    }

    /**
     * Summary of createInvoice
     * @param \PDO $dbh
     * @param mixed $resv
     * @param mixed $idPayor
     * @param mixed $notes
     * @return \HHK\Payment\Invoice\Invoice|null
     */
    public function createInvoice(\PDO $dbh, $resv, $idPayor, $notes = '') {

        $uS = Session::getInstance();

        if ($resv->getIdRegistration() < 1 || $resv->getIdReservation() < 1) {
            return NULL;
        }

        $this->pmp->setIdInvoicePayor($idPayor);

        if ($this->pmp->getRatePayment() > 0) {

            // Make Reservation only invoice
            $invLine = new HoldInvoiceLine(TRUE);
            $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, $this->pmp->getRatePayment()), 1, Labels::getString("GuestEdit", "reservationTitle", "Reservation") . ' Pre-Payment');

            $this->getInvoice($dbh, $idPayor, $resv->getIdRegistration(), 0, 0, $uS->username, '', $notes, $this->pmp->getPayDate());
            $this->invoice->addLine($dbh, $invLine, $uS->username);

        }

        // Pre-payment refunds
        if (abs($this->pmp->getOverPayment()) > 0) {

            // Do we still have any MOA?
            $amtMOA = Reservation_1::getPrePayment($dbh, $resv->getIdReservation());

            if ($amtMOA >= abs($this->pmp->getOverPayment())) {

                // Refund the MOA amount
                $this->moaRefundAmt = abs($this->pmp->getOverPayment());
                $invLine = new ReimburseInvoiceLine($uS->ShowLodgDates);
                $invLine->appendDescription($notes);
                $invLine->createNewLine(new Item($dbh, ItemId::LodgingMOA, (0 - $this->moaRefundAmt)), 1, Labels::getString("GuestEdit", "reservationTitle", "Reservation") . ' Pre-Payment Payout');

                $this->getInvoice($dbh, $idPayor, $resv->getIdRegistration(), 0, 0, $uS->username, '', $notes, $this->pmp->getPayDate());
                $this->invoice->addLine($dbh, $invLine, $uS->username);
            }
        }

        $this->processOverpayments($dbh, abs($this->pmp->getOverPayment()), $idPayor, $resv->getIdRegistration(), 0, 0, $notes);

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
                $this->invoice->setSoldToId($idPayor);
                $this->invoice->updateInvoiceStatus($dbh, $uS->username);
            }

        }


        return $this->invoice;
    }

}

