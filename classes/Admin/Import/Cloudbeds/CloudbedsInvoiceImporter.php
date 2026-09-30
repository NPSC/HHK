<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\Payment\CashTX;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\Invoice\InvoiceLine\OneTimeInvoiceLine;
use HHK\Payment\PaymentResponse\CashResponse;
use HHK\Purchase\Item;
use HHK\sec\Session;

/**
 * Creates a $0, already-paid HHK invoice from one staged CSV row (see CloudbedsInvoiceCsv), on the visit the
 * Cloudbeds import already created for the row's Cloudbeds reservation id - the same lookup CloudbedsImport::importFolio()
 * uses against CloudbedsStaging, so this only works for a reservation the Cloudbeds import already staged and imported.
 *
 * The invoice is built with the same classes the rest of HHK uses to take payments (Invoice, CashResponse, CashTX,
 * ImportPaymentResult), just for a $0 amount: HHK's own convention for a $0 invoice (see PaymentSvcs::payAmount(),
 * "pay 0 amounts as cash") is a $0 cash payment, which is what makes updateInvoiceBalance() flip the invoice to Paid.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsInvoiceImporter {

    public function __construct(protected \PDO $dbh, protected CloudbedsInvoiceStaging $staging, protected CloudbedsStaging $cloudbedsStaging) {
        $this->staging->ensureTables();
    }

    /**
     * Import up to $limit staged rows, each in its own transaction so one bad row does not roll back the rest - same
     * pattern as CloudbedsImport::startImport().
     *
     * @return array{success: bool, batch: int, errors: int, progress: array}
     */
    public function startImport(int $limit = 100): array {
        $workerId = bin2hex(random_bytes(16));
        $errors = 0;

        $rows = $this->staging->claimBatch($workerId, $limit);

        foreach ($rows as $row) {
            try {
                $this->dbh->beginTransaction();

                $result = $this->importRow($row);

                if ($result['status'] === CloudbedsInvoiceStaging::SKIPPED) {
                    $this->staging->markSkipped((int) $row['id'], $result['message']);
                } else {
                    $this->staging->markDone((int) $row['id'], $result['hhkId'], $result['data'], $result['message']);
                }

                $this->dbh->commit();
            } catch (\Throwable $e) {
                if ($this->dbh->inTransaction()) {
                    $this->dbh->rollBack();
                }

                $this->staging->markError((int) $row['id'], $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
                $errors++;
            }
        }

        return ['success' => true, 'batch' => count($rows), 'errors' => $errors, 'progress' => $this->staging->getProgress()];
    }

    /**
     * @param array $row a claimed CloudbedsInvoiceStaging row
     * @return array{status: string, hhkId: ?int, data: array, message: string}
     */
    public function importRow(array $row): array {
        $reservationId = (string) $row['reservationId'];

        $resv = $this->cloudbedsStaging->getRow(CloudbedsStaging::RESERVATION, $reservationId);
        if ($resv === null) {
            return $this->result(CloudbedsInvoiceStaging::SKIPPED, null, [], "Cloudbeds reservation $reservationId was not fetched by the Cloudbeds import");
        }
        if ($resv['status'] !== CloudbedsStaging::DONE || empty($resv['hhkData']['visits'])) {
            return $this->result(CloudbedsInvoiceStaging::SKIPPED, null, [], "Cloudbeds reservation $reservationId was not imported as a visit");
        }

        $idItem = $this->findItemId((string) $row['item']);
        if ($idItem === 0) {
            return $this->result(CloudbedsInvoiceStaging::SKIPPED, null, [], "Item '{$row['item']}' does not exist in HHK - use Create Missing Items first");
        }

        $data = $resv['hhkData'];
        $idVisit = (int) $data['visits'][0];
        $idRegistration = (int) $data['registration'];
        $idPayor = (int) $data['primaryGuest'];
        $uS = Session::getInstance();
        $username = $uS->username ?? 'admin';

        $notes = 'Imported from CSV, Cloudbeds reservation ' . $reservationId . (trim((string) $row['notes']) !== '' ? '. ' . trim((string) $row['notes']) : '');
        // Invoice.Notes is a 450 character column; truncate cleanly rather than let the DB layer cut mid-word
        if (mb_strlen($notes) > 450) {
            $notes = mb_substr($notes, 0, 447) . '...';
        }

        $invoice = new Invoice($this->dbh);
        $invoice->newInvoice($this->dbh, 0, $idPayor, $idRegistration, $idVisit, 0, $notes, (string) $row['invoiceDate'], $username, 'CSV additional charge');

        $item = new Item($this->dbh, $idItem, 0);
        $line = new OneTimeInvoiceLine();
        $line->createNewLine($item, 1);
        $invoice->addLine($this->dbh, $line, $username);

        // record a $0 cash payment so the invoice reads as Paid rather than sitting Unpaid at a $0 balance
        $invoice->setAmountToPay(0);
        $response = new CashResponse(0, $idPayor, $invoice->getInvoiceNumber(), 'CSV import', 0);
        CashTX::cashSale($this->dbh, $response, $username, (string) $row['invoiceDate']);
        $invoice->updateInvoiceBalance($this->dbh, $response->getAmount(), $username);

        $result = new ImportPaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
        $result->feePaymentAccepted($this->dbh, $uS, $response, $invoice);

        return $this->result(CloudbedsInvoiceStaging::DONE, $invoice->getIdInvoice(), [
            'invoiceNumber' => $invoice->getInvoiceNumber(), 'visit' => $idVisit,
        ]);
    }

    /**
     * Match an item name to an existing, non-deleted HHK item, case insensitive. 0 if none matches -
     * CloudbedsInvoiceCsv::createMissingItems() is what creates one.
     */
    protected function findItemId(string $name): int {
        $needle = strtolower(trim($name));

        foreach (Item::loadItems($this->dbh) as $item) {
            if (strtolower(trim($item['Description'])) === $needle) {
                return (int) $item['idItem'];
            }
        }

        return 0;
    }

    protected function result(string $status, ?int $hhkId, array $data = [], string $message = ''): array {
        return ['status' => $status, 'hhkId' => $hhkId, 'data' => $data, 'message' => $message];
    }
}
