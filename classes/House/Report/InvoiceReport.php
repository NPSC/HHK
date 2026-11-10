<?php
namespace HHK\House\Report;

use DateTime;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemId;

/**
 * InvoiceReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Invoice Report
 *
 * Lists invoices with payor, visit and patient details
 */
class InvoiceReport extends AbstractReport implements ReportInterface
{

    protected string $invNum = '';
    protected bool $forExcel = false;
    protected float $totalAmount = 0.0;
    protected float $totalPaid = 0.0;
    protected float $totalBalance = 0.0;

    public function __construct(\PDO $dbh, array $request = []) {

        $uS = Session::getInstance();
        $this->reportTitle = $uS->siteName . ' Invoice Report';
        $this->inputSetReportName = "invoice";

        $this->filterOpts = [
            "divider1" => [
                "type" => "divider"
            ],
            "rbDateType" => [
                "type" => "radio",
                "options" => [
                    "invoice" => "Invoice Dates",
                    "visit" => ["label" => "Visit Dates", "title" => "Show all invoices for any Visit having days within this date range."]
                ],
                "default" => "invoice"
            ],
            "divider2" => [
                "type" => "divider"
            ],
            "cbShoDel" => [
                "title" => "Show Deleted Invoices",
                "type" => "checkbox"
            ]
        ];

        if (isset($request['invNum'])) {
            $this->invNum = filter_var($request['invNum'], FILTER_SANITIZE_NUMBER_INT);
        }

        $this->filter = new ReportFilter();
        $this->filter->createInvoiceStatuses($dbh);
        $this->filter->loadSelectedInvoiceStatuses();
        $this->filter->createBillingAgents($dbh);
        $this->filter->loadSelectedBillingAgents();

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function getInvNum(): string {
        return $this->invNum;
    }

    /**
     * Used by the old page (house/InvoiceReportOld.php); the report itself uses formatRow().
     */
    public static function doMarkupRow($fltrdFields, $r, $isLocal, $hospital, $statusTxt, &$tbl, &$writer, $hdr, &$reportRows, $subsidyId) {

        $g = array();

        $g['Payor'] = $r['Company'];
        if ($r['Sold_To_Name'] != '') {

            if ($r['Company'] != '') {
                $g['Payor'] = $r['Company'] . ' c/o ';
            }

            $g['Payor'] .= $r['Sold_To_Name'];
        }


        $g['Patient'] = $r['Patient_Name'];
        if ($r['idPatient'] > 0) {
            $g['Patient'] = HTMLContainer::generateMarkup('a', $g['Patient'], ['href' => 'GuestEdit.php?id=' . $r['idPatient']]);
        }

        $g['payments'] = HTMLContainer::generateMarkup('span', number_format(($r['Amount'] - $r['Balance']), 2), ['style' => 'float:right;']);
        if (($r['Amount'] - $r['Balance']) != 0 && $r['Sold_To_Id'] != $subsidyId) {
            $g['payments'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'vwpmt' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'vpmt', 'style' => 'cursor:pointer;', 'title' => 'View Payments']);
        }

        $invoiceNumber = $r['Invoice_Number'];
        if ($invoiceNumber != '') {

            $invAttr = ['href' => 'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'target' => '_blank'];

            if ($r['Balance'] != 0 && $r['Balance'] != $r['Amount']) {
                $invoiceNumber .= HTMLContainer::generateMarkup('sup', '-p');
                $invAttr['title'] = 'Partial Payment';
            }

            $invoiceNumber = HTMLContainer::generateMarkup('a', $invoiceNumber, $invAttr)
                .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction ml-2', 'id' => 'invicon' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items']);
        }

        //$g['invoiceMkup'] = HTMLContainer::generateMarkup('span', $invoiceNumber, ["style" => 'white-space:nowrap;']);
        $g['invoiceMkup'] = $invoiceNumber;

        if ($r['Status'] == InvoiceStatus::Carried && $r['Deleted'] == 0) {

            $r['Balance'] = 0;

            $g['Status'] = HTMLContainer::generateMarkup('span',
                    HTMLContainer::generateMarkup('span', $statusTxt . ' by ' . HTMLContainer::generateMarkup('a', $r['Delegated_Invoice_Number'], ['href' => 'ShowInvoice.php?invnum=' . $r['Delegated_Invoice_Number'], 'target' => '_blank']))
                    .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'invicond' . $r['Delegated_Invoice_Id'], 'data-iid' => $r['Delegated_Invoice_Id'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items'])
                    , array("style"=>'white-space:nowrap;'));
        } else {

            $g['Status']= HTMLContainer::generateMarkup('span', $statusTxt, ["style" => 'white-space:nowrap;']);
        }

        $dateDT = new DateTime($r['Invoice_Date']);
        $g['date'] = $dateDT->format('c');

        $billDateStr = ($r['BillDate'] == '' ? '' : date('M j, Y', strtotime($r['BillDate'])));

        $g['billed'] = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('span', $billDateStr, ['id' => 'trBillDate' . $r['Invoice_Number']])
            . HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-calendar invSetBill', 'data-name' => $g['Payor'], 'data-inb' => $r['Invoice_Number'], 'style' => 'cursor:pointer;margin-left:5px;', 'title' => 'Set Billing Date']),
            ["class"=>"hhk-flex", "style"=>"justify-content: space-between; align-items: end;"]);

        $g['emailed'] = ($r["EmailDate"] == '' ? '' : (new DateTime($r["EmailDate"]))->format("M j, Y"));

        $g['Amount'] = number_format($r['Amount'], 2);

        // Show Delete Icon?
        if (($r['Amount'] == 0 || $r['Item_Id'] == ItemId::Discount) && $r['Deleted'] != 1) {
            $g['Amount'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-trash invAction', 'id' => 'invdel' . $r['idInvoice'], 'data-inv' => $r['Invoice_Number'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'del', 'style' => 'cursor:pointer;', 'title' => 'Delete This Invoice']);
        }

        $g['County'] = $r['County'];
        $g['Zip'] = $r['Zip'];
        $g['Title'] = $r['Title'];
        $g['Arrival'] = $r['Arrival'];
        $g['Departure'] = $r['Departure'];
        $g['hospital'] = $hospital;
        $g['Balance'] = number_format($r['Balance'], 2);
        $g['Notes'] = HTMLContainer::generateMarkup('div', $r['Notes'], ['id' => 'divInvNotes' . $r['Invoice_Number'], 'style' => 'max-width:190px; white-space:normal;']);

    //add invoice notes to payment report
        if ($isLocal) {

            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTd($g[$f[1]], $f[6]);
            }

            $tbl->addBodyTr($tr);

        } else {

            $g['invoiceMkup'] = $r['Invoice_Number'];
            $g['date'] = $r['Invoice_Date'];
            $g['Status'] = $statusTxt;
            $g['billed'] = $billDateStr;
            $g['Patient'] = $r['Patient_Name'];
            $g['Notes'] = $r['Notes'];
            $g['payments'] = $r['Amount'] - $r['Balance'];
            $g['Amount'] = $r['Amount'];
            $g['Balance'] = $r['Balance'];

            $n = 0;
            $flds = array();

            foreach ($fltrdFields as $f) {
                //$flds[$n++] = array('type' => $f[4], 'value' => $g[$f[1]], 'style'=>$f[5]);
                $flds[] = $g[$f[1]];
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }
    }

    /**
     * @inheritDoc
     */
    public function makeFields(): array {

        $uS = Session::getInstance();
        $labels = Labels::getLabels();
        $fields = [];

        // array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms, DT Type
        $fields[] = ['Inv #', 'invoiceMkup', 'checked', '', 'string', '20', array('style' => 'text-align:center;')];
        $fields[] = array("Date", 'date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array("Status", 'Status', 'checked', '', 'string', '20', array());
        $fields[] = array("Payor", 'Payor', 'checked', '', 'string', '20', array());
        $fields[] = array("Billed", 'billed', 'checked', '', 'string', '20', array());
        $fields[] = array("Emailed", 'emailed', 'checked', '', 'string', '20', array());
        $fields[] = array("Room", 'Title', 'checked', '', 'string', '15', array('style'=>'text-align:center;'));
        $fields[] = array("Visit Arrival", 'Arrival', '', '', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array("Visit Departure", 'Departure', '', '', 'MM/DD/YYYY', '15', array(), 'date');

        if (count($this->filter->getHList()) > 1) {
            $fields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'hospital', 'checked', '', 'string', '20', array());
        }

        $fields[] = array($labels->getString('MemberType', 'patient', 'Patient'), 'Patient', '', '', 'string', '20', array());

        if ($uS->county) {
            $fields[] = array($labels->getString('MemberType', 'patient', 'Patient') . ' County', 'County', '', '', 'string', '20', array());
        }

        $fields[] = array("Zip Code", 'Zip', '', '', 'string', '10', array());

        // Old number format - '_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)'
        $fields[] = array("Amount", 'Amount', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));
        $fields[] = array("Payments", 'payments', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));
        $fields[] = array("Balance", 'Balance', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));

        $fields[] = array("Notes", 'Notes', 'checked', '', 'string', '20', array());

        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function makeFilterMkup(): void {

        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->hospitalMarkup()->generateMarkup(array('style'=>'display: inline-block; vertical-align: top;'));
        $this->filterMkup .= $this->filter->invoiceStatusMarkup()->generateMarkup();

        if (count($this->filter->getBillingAgents()) > 0) {
            $this->filterMkup .= $this->filter->billingAgentMarkup()->generateMarkup();
        }

        $this->filterMkup .= $this->getColSelectorMkup();
    }

    protected function makeExtraButtonsMkup(): string {
        return  HTMLContainer::generateMarkup('label', 'Search Invoice #: ', array('for' => 'invNum', 'class' => 'mr-0'))
            . HTMLInput::generateMarkup($this->invNum, array('type' => 'text', 'id' => 'invNum', 'name' => 'invNum', 'size' => '8', 'class' => 'mr-3 ml-1 p-1'));
    }

    protected function getCellAttrs(array $field, array $row = [], string $outputType = ""): array {
        return (isset($field[6]) && is_array($field[6])) ? $field[6] : [];
    }

    /**
     * @inheritDoc
     */
    public function makeQuery(): void {

        $this->queryParams = [];
        $whHosp = '';
        $whAssoc = '';
        $whStatus = '';
        $whBillAgent = '';
        $whDeleted = '';

        if ($this->invNum != '') {

            // An invoice number search ignores the other filters.
            $whDates = " `i`.`Invoice_Number` = :invNum ";
            $this->queryParams[':invNum'] = $this->invNum;

        } else {

            if ($this->useVisitDates()) {
                $whDates = " AND DATE(`v`.`Arrival_Date`) < DATE(:queryEnd) AND IFNULL(DATE(`v`.`Actual_Departure`), DATE(`v`.`Expected_Departure`)) >= DATE(:reportStart) ";
            } else {
                $whDates = " AND DATE(`i`.`Invoice_Date`) < DATE(:queryEnd) AND DATE(`i`.`Invoice_Date`) >= DATE(:reportStart) ";
            }
            $this->queryParams[':queryEnd'] = $this->filter->getQueryEnd();
            $this->queryParams[':reportStart'] = $this->filter->getReportStart();

            $whHosp = $this->makeInClause('`hs`.`idHospital`', 'hosp', $this->filter->getSelectedHosptials());
            $whAssoc = $this->makeInClause('`hs`.`idAssociation`', 'assoc', $this->filter->getSelectedAssocs());
            $whStatus = $this->makeInClause('`i`.`Status`', 'stat', $this->filter->getSelectedInvoiceStatuses());
            $whBillAgent = $this->makeInClause('`i`.`Sold_To_Id`', 'ba', $this->filter->getSelectedBillingAgents());

            $whDeleted = (isset($this->request['cbShoDel']) ? '1=1 ' : '`i`.`Deleted` = 0 ');
        }

        $this->query = "SELECT
            `i`.`idInvoice`,
            `i`.`Delegated_Invoice_Id`,
            IFNULL(`di`.`Invoice_Number`, '') AS `Delegated_Invoice_Number`,
            IFNULL(`di`.`Status`, '') AS `Delegated_Invoice_Status`,
            `i`.`Deleted`,
            `i`.`Invoice_Number`,
            `i`.`Amount`,
            `i`.`Carried_Amount`,
            `i`.`Balance`,
            `i`.`Order_Number`,
            `i`.`Sold_To_Id`,
            `i`.`Notes`,
            `n`.`Name_Full` AS `Sold_To_Name`,
            `n`.`Company`,
            IFNULL(`np`.`Name_Full`, '') AS `Patient_Name`,
            IFNULL(`nap`.`County`, '') AS `County`,
            IFNULL(`nap`.`Postal_Code`, '') AS `Zip`,
            IFNULL(`re`.`Title`, '') AS `Title`,
            IFNULL(`v`.`Arrival_Date`, '') AS `Arrival`,
            IFNULL(`v`.`Actual_Departure`, '') AS `Departure`,
            IFNULL(`hs`.`idHospital`, 0) AS `idHospital`,
            IFNULL(`hs`.`idAssociation`, 0) AS `idAssociation`,
            IFNULL(`hs`.`idPatient`, 0) AS `idPatient`,
            `i`.`idGroup`,
            `i`.`Invoice_Date`,
            `i`.`BillStatus`,
            `i`.`BillDate`,
            `i`.`EmailDate`,
            `i`.`Payment_Attempts`,
            `i`.`Status`,
            `i`.`Updated_By`,
            `i`.`Last_Updated`,
            IFNULL(`il`.`Item_Id`, 0) AS `Item_Id`
            FROM `invoice` `i` LEFT JOIN `name` `n` ON `i`.`Sold_To_Id` = `n`.`idName`
                LEFT JOIN `invoice_line` `il` ON `il`.`Invoice_Id` = `i`.`idInvoice` AND `il`.`Item_Id` = 6
                LEFT JOIN `visit` `v` ON `i`.`Order_Number` = `v`.`idVisit` AND `i`.`Suborder_Number` = `v`.`Span`
                LEFT JOIN `hospital_stay` `hs` ON `hs`.`idHospital_stay` = `v`.`idHospital_stay`
                LEFT JOIN `name` `np` ON `hs`.`idPatient` = `np`.`idName`
                LEFT JOIN `name_address` `nap` ON `np`.`idName` = `nap`.`idName` AND `nap`.`Purpose` = `np`.`Preferred_Mail_Address`
                LEFT JOIN `resource` `re` ON `v`.`idResource` = `re`.`idResource`
                LEFT JOIN `invoice` `di` ON `i`.`Delegated_Invoice_Id` = `di`.`idInvoice`
            WHERE $whDeleted $whDates $whHosp $whAssoc $whStatus $whBillAgent ";
    }

    /**
     * Visit dates: every invoice for a visit with days in the period. Otherwise invoices dated in the period.
     */
    protected function useVisitDates(): bool {
        return ($this->request['rbDateType'] ?? '') == 'visit';
    }

    /**
     * Build " AND column IN (:p0, :p1...) " for the non-empty values, adding them to the query parameters.
     */
    protected function makeInClause(string $column, string $prefix, array $values): string {

        $placeholders = [];

        foreach ($values as $v) {
            if ($v != '') {
                $ph = ':' . $prefix . count($placeholders);
                $placeholders[] = $ph;
                $this->queryParams[$ph] = $v;
            }
        }

        return (count($placeholders) > 0 ? " AND $column IN (" . implode(', ', $placeholders) . ") " : '');
    }

    /**
     * Run the query, total the amounts and format each row for the page or Excel.
     */
    public function getResultSet(): array {

        parent::getResultSet();

        $this->totalAmount = 0.0;
        $this->totalPaid = 0.0;
        $this->totalBalance = 0.0;

        foreach ($this->resultSet as $k => $r) {

            if ($r['Deleted'] == 1) {
                $r['Balance'] = 0;
                $r['Amount'] = 0;
            } else if ($r['Status'] == InvoiceStatus::Carried) {
                $this->totalPaid += $r['Amount'] - $r['Balance'];
            } else {
                $this->totalPaid += $r['Amount'] - $r['Balance'];
                $this->totalBalance += $r['Balance'];
                $this->totalAmount += $r['Amount'];
            }

            $this->resultSet[$k] = $this->formatRow($r, $this->forExcel);
        }

        return $this->resultSet;
    }

    protected function formatRow(array $r, bool $forExcel): array {

        $uS = Session::getInstance();
        $invoiceStatuses = $this->filter->getInvoiceStatuses();
        $g = array();

        // Hospital
        $hospital = '';

        if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
        }
        if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
        }

        $statusTxt = '';
        if (isset($invoiceStatuses[$r['Status']])) {
            $statusTxt = $invoiceStatuses[$r['Status']][1];
        }

        if ($r['Deleted'] == 1) {
            $statusTxt = ($forExcel ? 'Deleted' : HTMLContainer::generateMarkup('span', 'Deleted', array('style'=>'color:red;')));
        }

        $g['Payor'] = $r['Company'];
        if ($r['Sold_To_Name'] != '') {

            if ($r['Company'] != '') {
                $g['Payor'] = $r['Company'] . ' c/o ';
            }

            $g['Payor'] .= $r['Sold_To_Name'];
        }

        if ($r['Status'] == InvoiceStatus::Carried && $r['Deleted'] == 0) {
            $r['Balance'] = 0;
        }

        $billDateStr = ($r['BillDate'] == '' ? '' : date('M j, Y', strtotime($r['BillDate'])));

        $g['emailed'] = ($r["EmailDate"] == '' ? '' : (new DateTime($r["EmailDate"]))->format("M j, Y"));
        $g['County'] = $r['County'];
        $g['Zip'] = $r['Zip'];
        $g['Title'] = $r['Title'];
        $g['Arrival'] = $r['Arrival'];
        $g['Departure'] = $r['Departure'];
        $g['hospital'] = $hospital;

        if ($forExcel) {

            $g['invoiceMkup'] = $r['Invoice_Number'];
            $g['date'] = $r['Invoice_Date'];
            $g['Status'] = $statusTxt;
            $g['billed'] = $billDateStr;
            $g['Patient'] = $r['Patient_Name'];
            $g['Notes'] = $r['Notes'];
            $g['payments'] = $r['Amount'] - $r['Balance'];
            $g['Amount'] = $r['Amount'];
            $g['Balance'] = $r['Balance'];

            return $g;
        }

        $g['Patient'] = $r['Patient_Name'];
        if ($r['idPatient'] > 0) {
            $g['Patient'] = HTMLContainer::generateMarkup('a', $g['Patient'], ['href' => 'GuestEdit.php?id=' . $r['idPatient']]);
        }

        $g['payments'] = HTMLContainer::generateMarkup('span', number_format(($r['Amount'] - $r['Balance']), 2), ['style' => 'float:right;']);
        if (($r['Amount'] - $r['Balance']) != 0 && $r['Sold_To_Id'] != $uS->subsidyId) {
            $g['payments'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'vwpmt' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'vpmt', 'style' => 'cursor:pointer;', 'title' => 'View Payments']);
        }

        $invoiceNumber = $r['Invoice_Number'];
        if ($invoiceNumber != '') {

            $invAttr = ['href' => 'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'target' => '_blank'];

            if ($r['Balance'] != 0 && $r['Balance'] != $r['Amount']) {
                $invoiceNumber .= HTMLContainer::generateMarkup('sup', '-p');
                $invAttr['title'] = 'Partial Payment';
            }

            $invoiceNumber = HTMLContainer::generateMarkup('a', $invoiceNumber, $invAttr)
                .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction ml-2', 'id' => 'invicon' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items']);
        }

        $g['invoiceMkup'] = $invoiceNumber;

        if ($r['Status'] == InvoiceStatus::Carried && $r['Deleted'] == 0) {

            $g['Status'] = HTMLContainer::generateMarkup('span',
                    HTMLContainer::generateMarkup('span', $statusTxt . ' by ' . HTMLContainer::generateMarkup('a', $r['Delegated_Invoice_Number'], ['href' => 'ShowInvoice.php?invnum=' . $r['Delegated_Invoice_Number'], 'target' => '_blank']))
                    .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'invicond' . $r['Delegated_Invoice_Id'], 'data-iid' => $r['Delegated_Invoice_Id'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items'])
                    , array("style"=>'white-space:nowrap;'));
        } else {

            $g['Status']= HTMLContainer::generateMarkup('span', $statusTxt, ["style" => 'white-space:nowrap;']);
        }

        $dateDT = new DateTime($r['Invoice_Date']);
        $g['date'] = $dateDT->format('c');

        $g['billed'] = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('span', $billDateStr, ['id' => 'trBillDate' . $r['Invoice_Number']])
            . HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-calendar invSetBill', 'data-name' => $g['Payor'], 'data-inb' => $r['Invoice_Number'], 'style' => 'cursor:pointer;margin-left:5px;', 'title' => 'Set Billing Date']),
            ["class"=>"hhk-flex", "style"=>"justify-content: space-between; align-items: end;"]);

        $g['Amount'] = number_format($r['Amount'], 2);

        // Show Delete Icon?
        if (($r['Amount'] == 0 || $r['Item_Id'] == ItemId::Discount) && $r['Deleted'] != 1) {
            $g['Amount'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-trash invAction', 'id' => 'invdel' . $r['idInvoice'], 'data-inv' => $r['Invoice_Number'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'del', 'style' => 'cursor:pointer;', 'title' => 'Delete This Invoice']);
        }

        $g['Balance'] = number_format($r['Balance'], 2);
        $g['Notes'] = HTMLContainer::generateMarkup('div', $r['Notes'], ['id' => 'divInvNotes' . $r['Invoice_Number'], 'style' => 'max-width:190px; white-space:normal;']);

        return $g;
    }

    public function downloadExcel(string $fileName = "InvoiceReport"): void {
        $this->forExcel = true;
        parent::downloadExcel($fileName);
    }

    /**
     * @inheritDoc
     */
    public function makeSummaryMkup(): string {

        $labels = Labels::getLabels();

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        if ($this->invNum != '') {

            $mkup .= HTMLContainer::generateMarkup('p', 'For Invoice Number: ' . $this->invNum);

        } else {

            $mkup .= HTMLContainer::generateMarkup('p', 'Reporting Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())) . ($this->useVisitDates() ? ' (Visit Dates)' : ' (Invoice Dates)'));
            $mkup .= HTMLContainer::generateMarkup('p', $labels->getString('hospital', 'hospitals', 'Hospitals') . ': ' . $this->filter->getSelectedHospitalsString());

            if (count($this->filter->getAList()) > 0) {
                $mkup .= HTMLContainer::generateMarkup('p', 'Associations: ' . $this->filter->getSelectedAssocString());
            }

            $mkup .= HTMLContainer::generateMarkup('p', 'Invoice Statuses: ' . $this->filter->getSelectedInvoiceStatusesString());

            if (count($this->filter->getSelectedBillingAgents()) > 0) {
                $mkup .= HTMLContainer::generateMarkup('p', 'Billing Agents: ' . $this->filter->getSelectedBillingAgentsString());
            }
        }

        $totTable = new HTMLTable();

        $totTable->addBodyTr(
                HTMLTable::makeTd('Total Amount:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($this->totalAmount,2), array('style'=>'text-align:right;')));

        $totTable->addBodyTr(
                HTMLTable::makeTd('Total Payment:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($this->totalPaid,2), array('style'=>'text-align:right;')));

        $totTable->addBodyTr(
                HTMLTable::makeTd('Total Balance:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($this->totalBalance,2), array('style'=>'text-align:right;')));

        return $mkup . $totTable->generateMarkup(array('class'=>'mt-2'));
    }
}
