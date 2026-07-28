<?php

namespace HHK\House\Report;

use HHK\Common;
use HHK\ExcelHelper;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\ItemId;
use HHK\SysConst\VolMemberType;
use HHK\TableLog\HouseLog;

/**
 * ItemReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Item Report
 *
 * Lists invoice line items with guest, patient and diagnosis details
 *
 * @author Will
 */

class ItemReport extends AbstractReport implements ReportInterface {

    protected float $reportTotal = 0.0;

    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Item Report';
        $this->inputSetReportName = "item";

        $this->filterOpts = [
            "cbShoDel" => [
                "title" => "Show Deleted Invoices",
                "type" => "checkbox"
            ]
        ];

        $this->filter = new ReportFilter();
        $this->filter->createInvoiceStatuses($dbh);
        $this->filter->loadSelectedInvoiceStatuses();
        $this->filter->createItems($dbh);
        $this->filter->loadSelectedItems();
        $this->filter->createDiagnoses($dbh);
        $this->filter->loadSelectedDiagnoses();

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeFields(): array {
        $uS = Session::getInstance();
        $labels = Labels::getLabels();

        $fields[] = array('Visit Id', 'vid', 'checked', '', 'string', '15', array());
        $fields[] = array("Organization", 'Company', 'checked', '', 'string', '20', array());
        $fields[] = array($labels->getString('memberType', 'guest', 'Guest') . ' Last', 'Last', 'checked', '', 'string', '20', array());
        $fields[] = array($labels->getString('memberType', 'guest', 'Guest') . " First", 'First', 'checked', '', 'string', '20', array());

        $pFields = array('Address', 'City');
        $pTitles = array($labels->getString('memberType', 'guest', 'Guest') . ' Address', 'City');
        $paFields = array('Patient_Address', 'Patient_City');
        $paTitles = array($labels->getString('memberType', 'patient', 'Patient') . ' Address', $labels->getString('memberType', 'patient', 'Patient') . ' City');

        if ($uS->county) {
            $pFields[] = 'County';
            $pTitles[] = $labels->getString('memberType', 'guest', 'Guest') . ' County';
            $paFields[] = 'Patient_County';
            $paTitles[] = $labels->getString('memberType', 'patient', 'Patient') . ' County';
        }

        $pFields = array_merge($pFields, array('State_Province', 'Postal_Code', 'Country'));
        $pTitles = array_merge($pTitles, array('State', 'Zip', 'Country'));
        $paFields = array_merge($paFields, array("Patient_State_Province", "Patient_Postal_Code", "Patient_Country"));
        $paTitles = array_merge($paTitles, array($labels->getString('memberType', 'patient', 'Patient') . " State", $labels->getString('memberType', 'patient', 'Patient') . " Zip", $labels->getString('memberType', 'patient', 'Patient') . " Country"));

        $fields[] = array($pTitles, $pFields, '', '', 'string', '20', array());
        $fields[] = array("Date", 'Date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15', array());
        $fields[] = array("Description", 'Description', 'checked', '', 'string', '20', array());
        $fields[] = array("Invoice Notes", 'Invoice_Notes', '', '', 'string', '20', array());
        $fields[] = array("Payment Notes", 'Payment_Notes', '', '', 'string', '20', array());
        $fields[] = array($labels->getString('memberType', 'patient', 'Patient') . " Id", 'Patient_Id', '', '', 'string', '20', array());
        $fields[] = array($labels->getString('memberType', 'patient', 'Patient') . " Last", 'Patient_Name_Last', '', '', 'string', '20', array());
        $fields[] = array($labels->getString('memberType', 'patient', 'Patient') . " First", 'Patient_Name_First', '', '', 'string', '20', array());
        $fields[] = array($paTitles, $paFields, '', '', 'string', '20', array());

        $locations = Common::readGenLookupsPDO($this->dbh, 'Location');
        if (count($locations) > 0) {
            $fields[] = array($labels->getString('hospital', 'location', 'Location'), 'Location', '', '', 'string', '20', array());
        }

        if (count($this->filter->getDiagnoses()) > 0) {
            $fields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', '', '', 'string', '20', array());
        }

        $fields[] = array("Updated By", 'Updated_By', '', '', 'string', '15', array());
        $fields[] = array("Status", 'Status', 'checked', '', 'string', '15', array());
        $fields[] = array("Amount", 'Amount', 'checked', '', 'dollar', '15', array('style'=>'text-align:right;'));

        return $fields;
    }

    public function makeFilterMkup(): void {
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->invoiceStatusMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->itemsMarkup()->generateMarkup();
        $this->filterMkup .= (count($this->filter->getDiagnoses()) > 0 ? $this->filter->diagnosisMarkup()->generateMarkup() : '');
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    public function makeQuery(): void {

        $whDates = " and DATE(i.Invoice_Date) < DATE('" . $this->filter->getQueryEnd() . "') and DATE(i.Invoice_Date) >= DATE('" . $this->filter->getReportStart() . "') ";

        $whStatus = '';
        foreach ($this->filter->getSelectedInvoiceStatuses() as $s) {
            if ($s != '') {
                $whStatus = ($whStatus == '' ? "'" . $s . "'" : $whStatus . ",'" . $s . "'");
            }
        }
        if ($whStatus != '') {
            $whStatus = " and i.Status in (" . $whStatus . ") ";
        }

        $whDeleted = isset($this->request['cbShoDel']) ? ' 1=1 ' : ' i.Deleted = 0 and il.Deleted = 0 ';

        $whItem = '';
        foreach ($this->filter->getSelectedItems() as $s) {
            if ($s != '') {
                $whItem = ($whItem == '' ? $s : $whItem . "," . $s);
            }
        }
        if ($whItem != '') {
            $whItem = " and il.Item_Id in (" . $whItem . ") ";
        }

        $whDiags = '';
        foreach ($this->filter->getSelectedDiagnoses() as $a) {
            if ($a != '') {
                $whDiags = ($whDiags == '' ? "'" . $a . "'" : $whDiags . ",'" . $a . "'");
            }
        }
        if ($whDiags != '') {
            $whDiags = " and hs.Diagnosis in (" . $whDiags . ") ";
        }

        $this->query = "select
    il.idInvoice_Line,
    i.idInvoice,
    i.Invoice_Number,
    i.`Amount` as `Invoice_Amount`,
    i.Sold_To_Id,
    i.Order_Number,
    i.Suborder_Number,
    i.Invoice_Date,
    i.`Balance`,
    i.`Deleted` as `Invoice_Deleted`,
    i.`Updated_By`,
    i.`Notes` as `Invoice_Notes`,
    ifnull(p.`Notes`, '') as `Payment_Notes`,
    il.`Amount`,
    il.`Description`,
    ifnull(pn.Name_Last, '') as `Patient_Name_Last`,
    ifnull(pn.Name_First, '') as `Patient_Name_First`,
    ifnull(pn.idName, '') as `Patient_Id`,
    concat(if(dc.Description is not null, concat(dc.Description, ': '), ''), ifnull(d.Description, '')) as `Diagnosis`,
    ifnull(loc.Description, '') as `Location`,
    ifnull(vs.Description, '') as `Status`,
    CASE when IFNULL(pa.Address_2, '') = '' THEN IFNULL(pa.Address_1, '') ELSE CONCAT(IFNULL(pa.Address_1, ''), ' ', IFNULL(pa.Address_2, '')) END AS `Patient_Address`,
    IFNULL(pa.City, '') AS `Patient_City`,
    IFNULL(pa.County, '') AS `Patient_County`,
    IFNULL(pa.State_Province, '') AS `Patient_State_Province`,
    IFNULL(pa.Postal_Code, '') AS `Patient_Postal_Code`,
    IFNULL(pa.Country_Code, '') AS `Patient_Country`,
    ifnull(n.Name_Last, '') as `Name_Last`,
    ifnull(n.Name_First, '') as `Name_First`,
    CASE when IFNULL(na.Address_2, '') = '' THEN IFNULL(na.Address_1, '') ELSE CONCAT(IFNULL(na.Address_1, ''), ' ', IFNULL(na.Address_2, '')) END AS `Address`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.County, '') AS `County`,
    IFNULL(na.State_Province, '') AS `State_Province`,
    IFNULL(na.Postal_Code, '') AS `Postal_Code`,
    IFNULL(na.Country_Code, '') AS `Country`,
    ifnull(n.`Company`, '') as `Company`,
    ifnull(nv.Vol_Code, '') as `Billing_Agent`
from
    invoice_line il join invoice i ON il.Invoice_Id = i.idInvoice
    left join `payment_invoice` pi on i.idInvoice = pi.Invoice_Id
    left join `payment` p on pi.Payment_Id = p.idPayment
    left join `name` n on i.Sold_To_Id = n.idName
    left join `name_address` na ON n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
    left join visit v on i.Order_Number = v.idVisit and i.Suborder_Number = v.Span
    left join hospital_stay hs on hs.idHospital_stay = v.idHospital_stay
    left join `name` pn on hs.idPatient = pn.idName
    left join `name_address` pa on pn.idName = pa.idName
    left join name_volunteer2 nv on nv.idName = n.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '" . VolMemberType::BillingAgent . "'
    left join gen_lookups d on hs.Diagnosis = d.Code and d.Table_Name = 'Diagnosis'
    left join gen_lookups dc on d.Substitute = dc.Code and dc.Table_Name = 'Diagnosis_Category'
    left join gen_lookups loc on hs.Location = loc.Code and loc.Table_Name = 'Location'
    left join gen_lookups vs on i.Status = vs.Code and vs.Table_Name = 'Invoice_Status'
where $whDeleted $whDates $whItem and il.Item_Id != " . ItemId::InvoiceDue . " $whStatus $whDiags group by il.idInvoice_Line order by i.idInvoice, il.idInvoice_Line";
    }

    protected function formatRow(array $r, bool $forExcel): array {

        $uS = Session::getInstance();
        $g = $r;

        $returnId = $uS->returnId;

        if ($r['Sold_To_Id'] == $uS->subsidyId) {
            $g['Company'] = $r['Company'];
            $g['First'] = '';
            $g['Last'] = '';
        } else if ($r['Billing_Agent'] == VolMemberType::BillingAgent || $returnId = $r['Sold_To_Id']) {
            $g['Company'] = $r['Company'];
            $g['First'] = $r['Name_First'];
            $g['Last'] = $r['Name_Last'];
        } else {
            $g['Company'] = '';
            $g['First'] = $r['Name_First'];
            $g['Last'] = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href' => 'GuestEdit.php?id=' . $r['Sold_To_Id'], 'title' => 'Click to go to the ' . Labels::getString('MemberType', 'visitor', 'Guest') . ' Edit page.'));
        }

        $g['vid'] = $r['Order_Number'] . '-' . $r['Suborder_Number'];

        if ($forExcel) {
            $g['Date'] = $r['Invoice_Date'];
        } else {

            $dateDT = new \DateTime($r['Invoice_Date']);
            $g['Date'] = $dateDT->format('c');

            $invNumber = $r['Invoice_Number'];

            if ($invNumber != '') {

                $iAttr = array('href' => 'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'style' => 'float:left;', 'target' => '_blank');

                if ($r['Invoice_Deleted'] > 0) {
                    $iAttr['style'] .= 'color:red;';
                    $iAttr['title'] = 'Invoice is Deleted.';
                } else if ($r['Balance'] != 0 && $r['Balance'] != $r['Invoice_Amount']) {
                    $iAttr['title'] = 'Partial payment.';
                    $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
                }

                $invNumber = HTMLContainer::generateMarkup('a', $invNumber, $iAttr)
                    . HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-comment invAction', 'id' => 'invicon' . $r['idInvoice_Line'], 'data-stat' => 'view', 'data-iid' => $r['idInvoice'], 'style' => 'cursor:pointer;', 'title' => 'View Items'));
            }

            $g['Invoice_Number'] = HTMLContainer::generateMarkup('span', $invNumber, array('style' => 'white-space:nowrap'));
            $g['Amount'] = HTMLContainer::generateMarkup('span', number_format($r['Amount'], 2), array('style' => 'text-align:right;'));
        }

        return $g;
    }

    public function generateMarkup(string $outputType = ""): string {
        $this->getResultSet();

        $this->reportTotal = array_sum(array_column($this->resultSet, 'Amount'));
        $sortCol = array_search("Last", $this->filteredTitles);
        $this->defaultSortCol = ($sortCol === false ? 0 : $sortCol);

        foreach ($this->resultSet as $k => $r) {
            $this->resultSet[$k] = $this->formatRow($r, false);
        }

        return parent::generateMarkup($outputType);
    }

    protected function makeFooterMkup(HTMLTable $tbl): void {

        $fltrdTitles = $this->filteredTitles;

        if (in_array("Amount", $fltrdTitles)) {

            $footercolspan = count($fltrdTitles) - 2;
            $footerspace = '';

            if ($footercolspan > 0) {
                $footerspace = HTMLTable::makeTd('', array('colspan' => $footercolspan));
            }

            $tbl->addFooterTr($footerspace
                . HTMLTable::makeTd('Total:', array('style' => 'text-align:right;font-weight:bold; border-top:2px solid black;'))
                . HTMLTable::makeTd('$' . number_format($this->reportTotal, 2), array('style' => 'text-align:right;font-weight:bold; border-top:2px solid black;'))
            );
        } else {
            $tbl->addFooterTr(HTMLTable::makeTd('', array('colspan' => count($fltrdTitles))));
        }
    }

    public function downloadExcel(string $fileName = "ItemReport"): void {

        $uS = Session::getInstance();
        $writer = new ExcelHelper($fileName);
        $writer->setAuthor($uS->username);
        $writer->setTitle($this->reportTitle);

        $hdr = array();
        $colWidths = array();

        foreach ($this->filteredFields as $field) {
            $hdr[$field[0]] = $field[4];
            $colWidths[] = $field[5];
        }

        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);

        $this->getResultSet();

        foreach ($this->resultSet as $r) {

            $g = $this->formatRow($r, true);

            $flds = array();
            foreach ($this->filteredFields as $f) {
                $flds[] = $g[$f[1]];
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }

        HouseLog::logDownload($this->dbh, $this->reportTitle, "Excel", $this->reportTitle . " for " . $this->filter->getReportStart() . " - " . $this->filter->getReportEnd() . " downloaded", $uS->username);

        $writer->download();
    }

    public function makeSummaryMkup(): string {

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
        $mkup .= HTMLContainer::generateMarkup('p', 'Reporting Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));
        $mkup .= HTMLContainer::generateMarkup('p', 'Invoice Statuses: ' . $this->filter->getSelectedInvoiceStatusesString());
        $mkup .= HTMLContainer::generateMarkup('p', 'Items: ' . $this->filter->getSelectedItemsString());

        if (count($this->filter->getDiagnoses()) > 0) {
            $mkup .= HTMLContainer::generateMarkup('p', Labels::getString('hospital', 'diagnosis', 'Diagnosis') . ': ' . $this->filter->getSelectedDiagnosesString());
        }

        return $mkup;
    }

}
