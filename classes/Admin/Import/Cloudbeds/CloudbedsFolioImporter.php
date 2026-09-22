<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\Payment\CashTX;
use HHK\Payment\CheckTX;
use HHK\Payment\ExternalTX;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\Invoice\InvoiceLine\OneTimeInvoiceLine;
use HHK\Payment\Invoice\InvoiceLine\RecurringInvoiceLine;
use HHK\Payment\PaymentManager\PaymentManagerPayment;
use HHK\Payment\PaymentResponse\CashResponse;
use HHK\Payment\PaymentResponse\CheckResponse;
use HHK\Payment\PaymentResponse\ExternalResponse;
use HHK\Payment\PaymentResponse\TransferResponse;
use HHK\Payment\TransferTX;
use HHK\Purchase\Item;
use HHK\sec\Session;
use HHK\SysConst\ItemId;
use HHK\SysConst\PayType;

/**
 * Turns a Cloudbeds folio into an HHK invoice with its payments.
 *
 * Charges become invoice lines (room revenue lines are merged into one line per run of nights at the same rate) and payments are
 * recorded against the invoice. This builds on the same classes the rest of HHK uses to take payments (Invoice, PaymentManagerPayment,
 * the *TX and *Response classes), only receipts are not emailed (see ImportPaymentResult) since these are historical payments.
 * Each charge transaction's own Cloudbeds notes (distinct from its description) are collected into the invoice's Notes field.
 *
 * Voided/refunded/transferred transactions, refunds and authorizations are not imported and are reported as warnings.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsFolioImporter {

    /** transaction states that mean the transaction no longer counts */
    protected const INACTIVE_STATES = ['VOIDED', 'TRANSFERRED', 'CANCELLED', 'REFUNDED', 'DELETED'];

    /** payment transaction types that don't represent money received */
    protected const SKIPPED_PAYMENT_TYPES = ['Refund', 'Void', 'Authorize'];

    public function __construct(protected \PDO $dbh, protected CloudbedsConfig $config) {
    }

    /**
     * Create the invoice and payments for a folio
     *
     * @param array $payload staged folio payload ["folio", "transactions", "reservationId"]
     * @param int $idVisit visit the invoice is attached to (invoice Order_Number)
     * @param int $idRegistration registration the invoice is attached to (invoice idGroup)
     * @param int $idPayor person the invoice is sold to
     * @param string $username
     * @return array{idInvoice: int, invoiceNumber: string, amount: float, paid: float, warnings: string[]}|null null if the folio has nothing to invoice
     */
    public function import(array $payload, int $idVisit, int $idRegistration, int $idPayor, string $username): ?array {

        $warnings = [];
        [$charges, $payments] = $this->partition((array) ($payload['transactions'] ?? []), $warnings);

        $lines = $this->buildLines($charges, $warnings);
        $total = round(array_sum(array_column($lines, 'amount')), 2);

        if (count($lines) === 0) {
            return null;
        }

        if ($total <= 0) {
            $warnings[] = 'Folio total is ' . number_format($total, 2) . ', not imported as an invoice';
            return ['idInvoice' => 0, 'invoiceNumber' => '', 'amount' => $total, 'paid' => 0.0, 'warnings' => $warnings];
        }

        $folioId = (string) ($payload['folio']['id'] ?? '');
        $invoiceDate = $this->invoiceDate($charges, $payments);
        $notes = $this->invoiceNotes($folioId, (string) ($payload['reservationId'] ?? ''), $charges);

        $invoice = new Invoice($this->dbh);
        $invoice->newInvoice(
            $this->dbh,
            0,
            $idPayor,
            $idRegistration,
            $idVisit,
            0,
            $notes,
            $invoiceDate,
            $username,
            'Cloudbeds folio ' . $folioId
        );

        foreach ($lines as $line) {
            $invoice->addLine($this->dbh, $this->makeLine($line), $username);
        }

        $paid = 0.0;
        usort($payments, fn($a, $b) => strcmp($a['date'], $b['date']));

        foreach ($payments as $payment) {
            $balance = round($invoice->getBalance(), 2);
            $amount = min($payment['amount'], $balance);

            if ($amount <= 0) {
                $warnings[] = 'Payment ' . $payment['id'] . ' (' . number_format($payment['amount'], 2) . ') exceeds the folio charges and was not imported';
                continue;
            }
            if ($amount < $payment['amount']) {
                $warnings[] = 'Payment ' . $payment['id'] . ' reduced from ' . number_format($payment['amount'], 2) . ' to ' . number_format($amount, 2) . ' to match the folio charges';
            }

            $this->recordPayment($invoice, $payment, $amount, $username);
            $paid += $amount;
        }

        return [
            'idInvoice' => $invoice->getIdInvoice(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
            'amount' => $total,
            'paid' => round($paid, 2),
            'warnings' => $warnings,
        ];
    }

    /**
     * Split transactions into charges and payments, dropping the ones that don't count
     *
     * @return array{0: array[], 1: array[]} [charges, payments]
     */
    protected function partition(array $transactions, array &$warnings): array {
        $charges = [];
        $payments = [];

        foreach ($transactions as $t) {
            $id = (string) ($t['id'] ?? '');

            if (in_array(strtoupper((string) ($t['state'] ?? '')), self::INACTIVE_STATES, true)) {
                continue;
            }

            if (($t['transactionType'] ?? '') === 'payment' || ($t['internalCodeGroup'] ?? '') === 'PAYMENT') {
                $details = (array) ($t['paymentDetails'] ?? []);

                if (in_array($details['transactionType'] ?? '', self::SKIPPED_PAYMENT_TYPES, true)) {
                    $warnings[] = 'Skipped ' . strtolower($details['transactionType']) . ' transaction ' . $id;
                    continue;
                }

                $amount = abs((float) ($details['amountPaid'] ?? $t['amount'] ?? 0));
                if ($amount > 0) {
                    $payments[] = [
                        'id' => $id,
                        'amount' => round($amount, 2),
                        'date' => $this->dateTime($t['transactionDatetimePropertyTime'] ?? $t['transactionDatetime'] ?? ''),
                        'method' => (string) ($details['paymentMethod'] ?? ''),
                        'card' => trim(($details['creditCardType'] ?? '') . ' ' . ($details['cardTrailingDigits'] ?? '')),
                        'notes' => (string) ($t['notes'] ?? $t['description'] ?? ''),
                    ];
                }
                continue;
            }

            $amount = round((float) ($t['amount'] ?? 0), 2);
            if ($amount != 0) {
                $charges[] = [
                    'id' => $id,
                    'amount' => $amount,
                    'type' => (string) ($t['transactionType'] ?? ''),
                    'description' => trim((string) ($t['description'] ?? '')),
                    'notes' => trim((string) ($t['notes'] ?? '')),
                    'serviceDate' => substr((string) ($t['serviceDate'] ?? ''), 0, 10),
                    'date' => $this->dateTime($t['transactionDatetimePropertyTime'] ?? $t['transactionDatetime'] ?? ''),
                ];
            }
        }

        return [$charges, $payments];
    }

    /**
     * Group charges into invoice lines. Lodging charges on consecutive service dates at the same rate become a single line.
     *
     * Charges whose type is mapped to "Do not import" are left out and reported in the warnings.
     *
     * @param array[] $charges
     * @param string[] $warnings
     * @return array[] lines ["itemId", "description", "quantity", "price", "amount", "start", "end"]
     */
    protected function buildLines(array $charges, array &$warnings = []): array {
        $lodging = [];
        $lines = [];
        $skipped = [];

        foreach ($charges as $c) {
            $itemId = $this->config->mapItem($c['type'], $c['amount']);

            if ($itemId === null) {
                $skipped[$c['type']] = ($skipped[$c['type']] ?? 0) + $c['amount'];
                continue;
            }

            if ($itemId === ItemId::Lodging && $c['serviceDate'] !== '') {
                $lodging[] = $c;
                continue;
            }

            $lines[] = [
                'itemId' => $itemId,
                'description' => $c['description'] !== '' ? $c['description'] : ucfirst(str_replace('_', ' ', $c['type'])),
                'quantity' => 1,
                'price' => $c['amount'],
                'amount' => $c['amount'],
                'start' => '',
                'end' => '',
            ];
        }

        usort($lodging, fn($a, $b) => strcmp($a['serviceDate'], $b['serviceDate']));

        $run = null;
        foreach ($lodging as $c) {
            $nextDay = $run ? date('Y-m-d', strtotime($run['end'] . ' +1 day')) : '';

            if ($run && $c['amount'] == $run['price'] && $c['serviceDate'] === $nextDay) {
                $run['quantity']++;
                $run['amount'] = round($run['quantity'] * $run['price'], 2);
                $run['end'] = $c['serviceDate'];
                continue;
            }

            if ($run) {
                $lines[] = $run;
            }

            $run = [
                'itemId' => ItemId::Lodging,
                'description' => '',
                'quantity' => 1,
                'price' => $c['amount'],
                'amount' => $c['amount'],
                'start' => $c['serviceDate'],
                'end' => $c['serviceDate'],
            ];
        }
        if ($run) {
            $lines[] = $run;
        }

        foreach ($skipped as $type => $amount) {
            $warnings[] = 'Not imported (charge item mapping): ' . $type . ' charges of ' . number_format($amount, 2);
        }

        return $lines;
    }

    /**
     * The invoice's Notes field: the standard "imported from" line, plus every distinct note found on the folio's charge
     * transactions (payment notes go on the payment itself, see recordPayment()). Capped to fit the column.
     */
    protected function invoiceNotes(string $folioId, string $reservationId, array $charges): string {
        $notes = 'Imported from Cloudbeds folio ' . $folioId . ' (reservation ' . $reservationId . ')';

        $chargeNotes = [];
        foreach ($charges as $c) {
            $text = trim((string) ($c['notes'] ?? ''));
            if ($text !== '' && !in_array($text, $chargeNotes, true)) {
                $chargeNotes[] = $text;
            }
        }

        if (count($chargeNotes) > 0) {
            $notes .= '. Folio notes: ' . implode('; ', $chargeNotes);
        }

        // Invoice.Notes is a 450 character column; truncate cleanly rather than let the DB layer cut mid-word
        if (strlen($notes) > 450) {
            $notes = substr($notes, 0, 447) . '...';
        }

        return $notes;
    }

    /**
     * @return \HHK\Payment\Invoice\InvoiceLine\AbstractInvoiceLine
     */
    protected function makeLine(array $line) {
        $item = new Item($this->dbh, $line['itemId'], $line['price']);

        if ($line['start'] !== '') {
            // a night's charge covers service date until the next day
            $end = date('Y-m-d', strtotime($line['end'] . ' +1 day'));
            $invLine = new RecurringInvoiceLine();
            $invLine->createNewLine($item, $line['quantity'], $line['start'], $end, $line['quantity']);
            return $invLine;
        }

        $invLine = new OneTimeInvoiceLine();
        $invLine->createNewLine($item, $line['quantity'], $line['description']);
        return $invLine;
    }

    /**
     * Record a payment on the invoice the same way PaymentSvcs::payAmount does for cash, check, transfer and external payments
     */
    protected function recordPayment(Invoice $invoice, array $payment, float $amount, string $username): void {
        $uS = Session::getInstance();
        $payType = $this->config->mapPayType($payment['method']);
        $notes = trim('Imported from Cloudbeds payment ' . $payment['id'] . ($payment['card'] !== '' ? ' (' . $payment['card'] . ')' : ''));

        $pmp = new PaymentManagerPayment($payType);
        $pmp->setPayDate($payment['date']);
        $pmp->setPayNotes($notes);

        $invoice->setAmountToPay($amount);
        $soldTo = $invoice->getSoldToId();
        $invNum = $invoice->getInvoiceNumber();

        switch ($payType) {

            case PayType::Cash:
                $response = new CashResponse($amount, $soldTo, $invNum, $pmp->getPayNotes(), $amount);
                CashTX::cashSale($this->dbh, $response, $username, $pmp->getPayDate());
                break;

            case PayType::Check:
                $pmp->setCheckNumber($payment['id']);
                $response = new CheckResponse($amount, $soldTo, $invNum, $pmp->getCheckNumber(), $pmp->getPayNotes());
                CheckTX::checkSale($this->dbh, $response, $username, $pmp->getPayDate());
                break;

            case PayType::Transfer:
                $pmp->setTransferAcct($payment['id']);
                $response = new TransferResponse($amount, $soldTo, $invNum, $pmp->getTransferAcct(), $pmp->getPayNotes());
                TransferTX::sale($this->dbh, $response, $username, $pmp->getPayDate());
                break;

            default:
                $pmp->setExternalId($payment['id']);
                $pmp->setExternalPaymentType(PayType::External, $this->externalTitle($payment));
                $response = new ExternalResponse($amount, $soldTo, $invNum, $pmp->getExternalId(), $pmp->getPayNotes(), $pmp->getExternalPaymentTypeCode(), $pmp->getExternalPaymentTypeTitle());
                ExternalTX::sale($this->dbh, $response, $username, $pmp->getPayDate());
        }

        // update invoice, then create the payment-invoice record. Same as PaymentSvcs::payAmount, minus the emailed receipt.
        $invoice->updateInvoiceBalance($this->dbh, $response->getAmount(), $username);

        $result = new ImportPaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
        $result->feePaymentAccepted($this->dbh, $uS, $response, $invoice);

        // keep the Cloudbeds transaction id on the payment
        $stmt = $this->dbh->prepare("update `payment` set `External_Id` = :externalId where `idPayment` = :idPayment");
        $stmt->execute([':externalId' => substr($payment['id'], 0, 45), ':idPayment' => $response->getIdPayment()]);
    }

    protected function externalTitle(array $payment): string {
        $method = trim(str_replace('_', ' ', $payment['method']));
        $title = $method !== '' ? ucwords($method) : 'Cloudbeds';
        return $payment['card'] !== '' ? $title . ' - ' . $payment['card'] : $title;
    }

    /**
     * Cloudbeds timestamps to Y-m-d H:i:s, empty if missing/unparseable
     */
    protected function dateTime(string $value): string {
        if (trim($value) === '') {
            return '';
        }
        try {
            return (new \DateTime($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return '';
        }
    }

    protected function invoiceDate(array $charges, array $payments): string {
        $dates = array_filter(array_merge(array_column($charges, 'date'), array_column($payments, 'date')));
        return count($dates) > 0 ? max($dates) : date('Y-m-d H:i:s');
    }
}
