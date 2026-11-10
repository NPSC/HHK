<?php

namespace HHK\House\Report;

use HHK\Excel\ExcelHelper;
use HHK\Exception\RuntimeException;
use HHK\House\GLCodes\GLCodes;
use HHK\House\GLCodes\GLParameters;
use HHK\House\GLCodes\GLTemplateRecord;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Session;
use HHK\TableLog\HouseLog;

/**
 * GLReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * GL Report
 *
 * One row per journal line of the GL file for the selected fiscal month(s). The same mapping (GLCodes) builds the
 * file for Download CSV, Transfer via SFTP and the monthly cron job (house/GenLedger.php).
 * Settings are on Admin > Configure > General Ledger.
 */
class GLReport extends AbstractReport implements ReportInterface {

    protected GLParameters $glParm;

    /**
     * Day of the month each fiscal month starts on.
     */
    protected int $startDay = 1;

    /**
     * Setting problems found before the report runs.
     */
    protected array $notices = [];

    /**
     * Messages from the GL mapping.
     */
    protected array $errors = [];

    /**
     * Invoice number => debits - credits, for the invoices whose journal lines don't balance.
     */
    protected array $unbalanced = [];

    protected float $totalDebit = 0.0;
    protected float $totalCredit = 0.0;

    public function __construct(\PDO $dbh, array $request = []) {

        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' GL Report';
        $this->description = 'GL settings and billing agent GL codes are on Admin &gt; Configure &gt; General Ledger.';
        $this->inputSetReportName = 'gl';
        $this->defaultSortCol = 0;

        $this->glParm = new GLParameters($dbh, 'Gl_Code');

        try {
            $this->startDay = intval($this->glParm->getStartDay());
        } catch (RuntimeException $ex) {
            $this->notices[] = $ex->getMessage() . '. Using calendar months.';
        }

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    /**
     * Fiscal Month is the only interval: the GL file always covers whole fiscal months.
     */
    protected function makeTimePeriod(): void {

        $uS = Session::getInstance();

        $this->filter->createTimePeriod(date('Y'), ReportFilter::FISCAL_MONTH, $uS->fy_diff_Months,
            [ReportFilter::DATES, ReportFilter::MONTHS, ReportFilter::FISCAL_YEAR, ReportFilter::CAL_YEAR, ReportFilter::YEAR_2_DATE],
            $this->startDay);
    }

    public function makeFilterMkup(): void {
        // 'gl' id prefix: the invoice report's time period is on the same page.
        $this->filterMkup .= $this->filter->timePeriodMarkup('', 'gl')->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    protected function makeExtraButtonsMkup(): string {

        $attrs = ['type' => 'submit', 'class' => 'ui-button ui-corner-all ui-widget'];

        return HTMLInput::generateMarkup('Download CSV', $attrs + ['name' => 'btnGlcsv'])
            . ($this->glParm->isSftpConfigured()
                ? HTMLInput::generateMarkup(($this->glParm->getSftpTitle() != '' ? 'Transfer to ' . $this->glParm->getSftpTitle() : 'Transfer via SFTP'), $attrs + ['name' => 'btnGlTx'])
                : '');
    }

    public function makeFields(): array {

        $right = ['style' => 'text-align:right;'];

        $fields[] = ['Invoice', 'Invoice', 'checked', '', 'string', '12', []];
        $fields[] = ['Payment Id', 'idPayment', 'checked', '', 'string', '12', []];
        $fields[] = ['Date', 'Date', 'checked', '', 'MM/DD/YYYY', '15', [], 'date'];
        $fields[] = ['Pay Method', 'Pay_Method', 'checked', '', 'string', '15', []];
        $fields[] = ['Source', 'Source', 'checked', '', 'string', '30', []];
        $fields[] = ['GL Code', 'GL_Code', 'checked', '', 'string', '25', []];
        $fields[] = ['Company', 'Company', '', '', 'string', '10', []];
        $fields[] = ['Cost Center', 'Cost_Center', '', '', 'string', '12', []];
        $fields[] = ['Account', 'Account', '', '', 'string', '12', []];
        $fields[] = ['Debit', 'Debit', 'checked', '', 'dollar', '15', $right];
        $fields[] = ['Credit', 'Credit', 'checked', '', 'dollar', '15', $right];

        return $fields;
    }

    protected function getCellAttrs(array $field): array {
        return (isset($field[6]) && is_array($field[6])) ? $field[6] : [];
    }

    /**
     * The rows come from the GL mapping, not a query.
     */
    public function makeQuery(): void {
        $this->query = '';
    }

    /**
     * The GL mapping for the selected period.
     */
    protected function makeGlCodes(): GLCodes {

        return new GLCodes($this->dbh, 0, 0, $this->glParm, new GLTemplateRecord(),
            new \DateTimeImmutable($this->filter->getReportStart()),
            new \DateTimeImmutable($this->filter->getQueryEnd()));
    }

    /**
     * Map the whole period (no stop at the first unbalanced invoice) and make one row per journal line.
     * Amounts are left as numbers for Excel; generateMarkup() formats them.
     */
    public function getResultSet(): array {

        $glCodes = $this->makeGlCodes();
        $glCodes->mapRecords(FALSE);

        // Payment id => payment method id
        $payMethodIds = [];
        foreach ($glCodes->getInvoices() as $r) {
            foreach ($r['p'] as $p) {
                $payMethodIds[$p['idPayment']] = $p['pMethod'];
            }
        }

        $payMethodNames = $this->loadPayMethodNames();

        $this->resultSet = [];
        $this->totalDebit = 0.0;
        $this->totalCredit = 0.0;
        $invoiceBalances = [];

        foreach ($glCodes->getLines() as $l) {

            $invoice = (string)$l['in'];
            $debit = round(floatval($l['debit']), 2);
            $credit = round(floatval($l['credit']), 2);
            $glCode = trim((string)$l['glCode']);

            $this->resultSet[] = [
                'Invoice' => $invoice,
                'idPayment' => $l['idPayment'],
                'Date' => $l['date'],
                'Pay_Method' => $payMethodNames[$payMethodIds[$l['idPayment']] ?? ''] ?? '',
                'Source' => $l['source'],
                'GL_Code' => $glCode,
                'Company' => $l['l'][GLTemplateRecord::COMPANY_CODE],
                'Cost_Center' => $l['l'][GLTemplateRecord::COST_CENTER],
                'Account' => $l['l'][GLTemplateRecord::ACCOUNT],
                'Debit' => $debit,
                'Credit' => $credit,
            ];

            $this->totalDebit += $debit;
            $this->totalCredit += $credit;
            $invoiceBalances[$invoice] = ($invoiceBalances[$invoice] ?? 0) + $debit - $credit;
        }

        $this->unbalanced = [];
        foreach ($invoiceBalances as $invoice => $diff) {
            if (round($diff, 2) != 0) {
                $this->unbalanced[$invoice] = round($diff, 2);
            }
        }

        // The summary shows the period; GLCodes also lists it as a message for the cron job's email.
        $this->errors = array_values(array_filter($glCodes->getErrors(), fn($e) => !str_starts_with((string)$e, 'Report Dates:')));

        return $this->resultSet;
    }

    public function generateMarkup(string $outputType = "") {

        $this->getResultSet();

        foreach ($this->resultSet as $k => $r) {

            $invoice = HTMLContainer::generateMarkup('a', $r['Invoice'], ['href' => 'ShowInvoice.php?invnum=' . urlencode($r['Invoice']), 'target' => '_blank']);

            if (isset($this->unbalanced[$r['Invoice']])) {
                $invoice = HTMLContainer::generateMarkup('span', $invoice, ['class' => 'ui-state-error', 'title' => 'Out of balance by ' . number_format($this->unbalanced[$r['Invoice']], 2)]);
            }

            $this->resultSet[$k]['Invoice'] = $invoice;
            $this->resultSet[$k]['Debit'] = ($r['Debit'] == 0 ? '' : number_format($r['Debit'], 2));
            $this->resultSet[$k]['Credit'] = ($r['Credit'] == 0 ? '' : number_format($r['Credit'], 2));
        }

        return parent::generateMarkup($outputType);
    }

    /**
     * Debit and credit totals under their columns.
     */
    protected function makeFooterMkup(HTMLTable $tbl): void {

        $style = 'text-align:right;font-weight:bold;border-top:2px solid black;';
        $tds = '';
        $labeled = FALSE;

        foreach ($this->filteredFields as $f) {

            if ($f[1] == 'Debit') {
                $tds .= HTMLTable::makeTd(number_format($this->totalDebit, 2), ['style' => $style]);
            } else if ($f[1] == 'Credit') {
                $tds .= HTMLTable::makeTd(number_format($this->totalCredit, 2), ['style' => $style]);
            } else if ($labeled === FALSE) {
                $tds .= HTMLTable::makeTd('Totals', ['style' => 'font-weight:bold;']);
                $labeled = TRUE;
            } else {
                $tds .= HTMLTable::makeTd('');
            }
        }

        $tbl->addFooterTr($tds);
    }

    protected function writeExcelFooter(ExcelHelper $writer): void {

        $row = [];
        $labeled = FALSE;

        foreach ($this->filteredFields as $f) {

            if ($f[1] == 'Debit') {
                $row[] = round($this->totalDebit, 2);
            } else if ($f[1] == 'Credit') {
                $row[] = round($this->totalCredit, 2);
            } else if ($labeled === FALSE && $f[4] == 'string') {
                $row[] = 'Totals';
                $labeled = TRUE;
            } else {
                $row[] = '';
            }
        }

        $writer->writeSheetRow("Sheet1", $row);
    }

    public function makeSummaryMkup(): string {

        $difference = round($this->totalDebit - $this->totalCredit, 2);

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd()))
            . ($this->startDay > 1 ? ' (fiscal months start on day ' . $this->startDay . ')' : ''));
        $mkup .= HTMLContainer::generateMarkup('p', 'Journal Lines: ' . count($this->resultSet));
        $mkup .= HTMLContainer::generateMarkup('p', 'Total Debits: ' . number_format($this->totalDebit, 2) . ', Total Credits: ' . number_format($this->totalCredit, 2));

        if ($difference == 0 && count($this->unbalanced) == 0) {
            $mkup .= HTMLContainer::generateMarkup('p', 'Balanced');
        } else {

            $mkup .= HTMLContainer::generateMarkup('p', 'Out of balance by ' . number_format($difference, 2), ['style' => 'color:red;font-weight:bold;']);

            $invoices = [];
            foreach ($this->unbalanced as $invoice => $diff) {
                $invoices[] = HTMLContainer::generateMarkup('a', $invoice, ['href' => 'ShowInvoice.php?invnum=' . urlencode((string)$invoice), 'target' => '_blank', 'title' => 'Off by ' . number_format($diff, 2)]);
            }

            if (count($invoices) > 0) {
                $mkup .= HTMLContainer::generateMarkup('p', 'Unbalanced Invoices: ' . implode(', ', $invoices));
            }
        }

        foreach (array_merge($this->notices, $this->errors) as $e) {
            $mkup .= HTMLContainer::generateMarkup('p', $e, ['class' => 'ui-state-error ui-corner-all p-1 mt-1']);
        }

        return $mkup;
    }

    /**
     * Send the GL file for the selected period to the browser. Returns the messages when there's nothing to send.
     */
    public function downloadGlFile(): string {

        $uS = Session::getInstance();
        $glCodes = $this->makeGlCodes()->mapRecords();

        if (count($glCodes->getLines()) > 0) {
            HouseLog::logDownload($this->dbh, $this->reportTitle, "CSV", "GL file for " . $this->filter->getReportStart() . " - " . $this->filter->getReportEnd() . " downloaded", $uS->username);
        }

        $glCodes->downloadCSV();  // exits once the file is sent

        return $this->makeMessagesMkup($glCodes->getErrors());
    }

    /**
     * Upload the GL file for the selected period to the SFTP server.
     */
    public function transferGlFile(): string {

        if ($this->glParm->isSftpConfigured() === FALSE) {
            return $this->makeMessagesMkup(array_merge($this->notices, ['SFTP is not set up.']));
        }

        $glCodes = $this->makeGlCodes();
        $bytesWritten = $glCodes->mapRecords()->transferRecords();

        $messages = $glCodes->getErrors();

        if ($bytesWritten !== FALSE && $bytesWritten != '') {
            $messages[] = 'Bytes Written: ' . number_format(floatval($bytesWritten));
        }

        return $this->makeMessagesMkup(array_merge($this->notices, $messages));
    }

    protected function makeMessagesMkup(array $messages): string {

        $mkup = '';

        foreach ($messages as $m) {
            $mkup .= HTMLContainer::generateMarkup('p', $m);
        }

        return HTMLContainer::generateMarkup('div', $mkup, ['class' => 'ui-widget ui-widget-content ui-corner-all hhk-tdbox mt-3 p-2']);
    }

    /**
     * Payment method id => name
     */
    protected function loadPayMethodNames(): array {

        $stmt = $this->dbh->prepare("SELECT `idPayment_method`, `Method_Name` FROM `payment_method`");
        $stmt->execute();

        $names = [];
        while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
            $names[$r[0]] = $r[1];
        }

        return $names;
    }
}
