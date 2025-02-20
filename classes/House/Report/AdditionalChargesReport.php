<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\TaxedItem;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\SysConst\ItemId;
use HHK\SysConst\VolMemberType;

/**
 * AdditionalChargesReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Additional Charges Report
 *
 * Lists additional charges, associated patients, and their demographics
 *
 * @author Will
 */

class AdditionalChargesReport extends AbstractReport implements ReportInterface {

    public array $diags;
    public array $demogs;
    public array $additionalCharges;
    public array $discounts;
    public array $items;
    public array $selectedItems;


    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Additional Charges Report';
        $this->description = "This report shows all additional charges charged to patients and their demographics who stayed in the time period";
        $this->inputSetReportName = "additionalCharges";

        $this->demogs = readGenLookupsPDO($dbh, 'Demographics');
        $this->additionalCharges = $this->formatGenLookup(readGenLookupsPDO($dbh, 'Addnl_Charge'));
        $this->discounts = $this->formatGenLookup(readGenLookupsPDO($dbh, 'House_Discount'));
        $this->items = $this->loadItems($dbh);

        if (filter_has_var(INPUT_POST, 'selItems')) {
            $this->selectedItems = filter_input(INPUT_POST, 'selItems', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
            if($this->selectedItems[0] == ""){
                unset($this->selectedItems[0]);
            }
        }else{
            $this->selectedItems = [];
        }

        parent::__construct($dbh, $this->inputSetReportName, $request);
        $this->filter->createBillingAgents($dbh);
        $this->filter->createDiagnoses($dbh);
    }

    protected function formatGenLookup(array $genLookups){
        foreach($genLookups as $k=>&$v){
            $v["Substitute"] = "";
            $v[2] = "";
        }
        return $genLookups;
    }

    protected function loadBillingAgents(\PDO $dbh){
        $stmt = $dbh->query("SELECT n.idName, if(trim(n.Name_Full) != '', n.Name_Full, n.Company) as `Title`" .
            " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
            " where n.Member_Status='a' and n.Record_Member = 1 order by n.Company");
        return $stmt->fetchAll(\PDO::FETCH_NUM);
    }

    protected function loadItems(\PDO $dbh){
        $uS = Session::getInstance();

        $stmt = $dbh->query("SELECT idItem, Description, Percentage, Last_Order_Id from item where Deleted = 0");
        $itemList = array();

        while($r = $stmt->fetch(\PDO::FETCH_NUM)) {

            if ($r[0] == ItemId::LodgingDonate) {
                $r[1] = "Lodging Donation";
            } else if ($r[0] == ItemId::AddnlCharge) {
                $r[1] = "Additional Charges";
            }

            if ($r[2] != 0) {
                $r[1] .= ' '.TaxedItem::suppressTrailingZeros($r[2]);

                if ($r[3] != 0) {
                    $r[2] = 'Old Rates';
                } else {
                    $r[2] = '';
                }
            } else {
                $r[2] = '';
            }

            if ($r[0] == ItemId::DepositRefund && $uS->KeyDeposit === FALSE) {
                continue;
            } else if ($r[0] == ItemId::KeyDeposit && $uS->KeyDeposit === FALSE) {
                continue;
            } else if ($r[0] == ItemId::VisitFee && $uS->VisitFee === FALSE) {
                continue;
            } else if ($r[0] == ItemId::AddnlCharge && count($this->additionalCharges) == 0) {
                continue;
            } else if ($r[0] == ItemId::InvoiceDue) {
                continue;
            }

            $itemList[$r[0]] = $r;
        }
        return $itemList;
    }

    protected function getSelectedBillingAgentNames(){
        $billingAgentNames = [];
        if(count($this->selectedBillingAgents) > 0){
            foreach($this->billingAgents as $ba){
                if(in_array($ba[0],$this->selectedBillingAgents)){
                    $billingAgentNames[] = $ba[1];
                }
            }
        }else{
            $billingAgentNames = ["All"];
        }
        return $billingAgentNames;
    }

    protected function getBillingAgentMarkup(){

        $billingAgentSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->billingAgents, $this->selectedBillingAgents), array('name' => 'selBillingAgents[]', 'size'=>(count($this->billingAgents) < 13 ? count($this->billingAgents) + 1 : '13'), 'multiple'=>'multiple'));
    
        $tbl = new HTMLTable();
        $tr = '';
    
        $tbl->addHeaderTr(HTMLTable::makeTh("Billing Agent"));
    
        $tbl->addBodyTr($tr . HTMLTable::makeTd($billingAgentSelector, array('style'=>'vertical-align: top;')));
    
        return $tbl;
    }

    protected function getItemMarkup(){
        $itemSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->items, $this->selectedItems), array('name' => 'selItems[]', 'size' => (count($this->items) + 1), 'multiple' => 'multiple'));
        $additionalChargesSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->additionalCharges, $this->selectedItems), array('name' => 'selAdditionalCharges[]', 'size' => (count($this->additionalCharges) + 1), 'multiple' => 'multiple'));
        $discountsSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->discounts, $this->selectedItems), array('name' => 'selItems[]', 'size' => (count($this->discounts) + 1), 'multiple' => 'multiple'));
        $tbl = new HTMLTable();
        $tr = '';
        
        $tbl->addHeaderTr(HTMLTable::makeTh("Invoice Items") . HTMLTable::makeTh("Additional Charges") . HTMLTable::makeTh("Discounts"));
        
        $tbl->addBodyTr($tr . HTMLTable::makeTd($itemSelector, array('style'=>'vertical-align: top;')) . HTMLTable::makeTd($additionalChargesSelector, array('style'=>'vertical-align: top;')) . HTMLTable::makeTd($discountsSelector, array('style'=>'vertical-align: top;')));
        
        return $tbl;
    }

    public function makeQuery(): void{

        //demographics
        $joinDemos = "";
        $listDemos = "";
        foreach ($this->demogs as $d) {
            if (strtolower($d[2]) == 'y'){
                if($d[0] == "Gender"){
                    $joinDemos .= "left join gen_lookups Gender on p.Gender = Gender.Code and Gender.Table_Name = 'Gender'";
                }else{
                    $joinDemos .= "left join gen_lookups " . $d[0] . " on pd.".$d[0]." = ".$d[0].".Code and ".$d[0].".Table_Name = '".$d[0]."'";
                }
                $listDemos .= "ifnull(".$d[0].".Description, '') as `".$d[0]."`,";

            }
        }

        $departureCase = "CASE WHEN v.Span_End IS NOT NULL THEN v.Span_End
         WHEN v.Expected_Departure IS NOT NULL AND v.Expected_Departure > NOW() THEN v.Expected_Departure
         WHEN v.Status = 'a' THEN ''
         ELSE '' END";

        $whDepartureCase = "CASE WHEN v.Span_End IS NOT NULL THEN v.Span_End
        WHEN v.Expected_Departure IS NOT NULL AND v.Expected_Departure > NOW() THEN v.Expected_Departure
        ELSE NOW() END";

        $whDates =  "v.Span_Start <= '" . $this->filter->getReportEnd() . "' and " . $whDepartureCase . " >= '" . $this->filter->getReportStart() . "' ";

        $whBilling = "";
        if(count($this->selectedBillingAgents) > 0){
            $billingAgents = implode(",", $this->selectedBillingAgents);
            $whBilling = " and i.Sold_To_Id in (" . $billingAgents . ")";
        }
        $baIds = array();
        if(count($this->billingAgents) > 0){
            foreach($this->billingAgents as $ba){
                $baIds[] = $ba[0];
            }
        }

        $this->query = "select
    CONCAT(v.idVisit, '-', v.Span) as idVisit,
    v.idVisit as `visitId`,
    v.Span as `Span`,
    ifnull(p.idName, '') as `pId`,
    ifnull(hs.idPsg, '') as `idPsg`,
    ifnull(p.Name_Last, '') as Name_Last,
    ifnull(p.Name_First, '') as Name_First,
    concat(ifnull(pa.Address_1, ''), '', ifnull(pa.Address_2, ''))  as pAddr,
    ifnull(pa.City, '') as pCity,
    ifnull(pa.County, '') as pCounty,
    ifnull(pa.State_Province, '') as pState,
    ifnull(pa.Country_Code, '') as pCountry,
    ifnull(pa.Postal_Code, '') as `pZip`,
    concat(if(dc.Description is not null, concat(dc.Description, ': '), ''), ifnull(d.Description, '')) as `Diagnosis`,
    ifnull(p.BirthDate, '') as `DOB`,
    TIMESTAMPDIFF(YEAR, p.BirthDate, CURDATE()) as `Age`,
    " . $listDemos . "
    ifnull(v.Span_Start, '') as `Arrival`,
    " . $departureCase . " as `Departure`,
    DATEDIFF(ifnull(v.Span_End, date(now())), v.Span_Start) as `Nights`,
    SUM(DATEDIFF(il.Period_End, il.Period_Start)) as `PaidNights`,
    ifnull(pgn.Name_First, '') as `pgFirst`,
    ifnull(pgn.Name_Last, '') as `pgLast`,
    ifnull(vs.Description, '') as `Status_Title`,
    ifnull(i.Invoice_Number, '') as `Invoice_Number`,
    sum(ifnull(i.Amount, '')) as `Invoice_Amount`,
    if(trim(ba.Name_Full) != '', ba.Name_Full, ba.Company) as `Billed To`,
    ifnull(invs.Description, '') as `Invoice_Status_Title`
from
    visit v
        left join
    hospital_stay hs on v.idHospital_Stay = hs.idHospital_Stay
        left join
    gen_lookups d on hs.Diagnosis = d.Code and d.`Table_Name` = 'Diagnosis'
        left join
    gen_lookups dc on d.Substitute = dc.Code and dc.Table_Name = 'Diagnosis_Category'
        left join
    name p ON hs.idPatient = p.idName
        left join
    name_address pa ON p.idName = pa.idName and p.Preferred_Mail_Address = pa.Purpose
        left join
    name_demog pd ON p.idName = pd.idName
    ".$joinDemos."
        left join
    name pgn ON v.idPrimaryGuest = pgn.idName
        left join
    gen_lookups vs on vs.Table_Name = 'Visit_Status' and vs.Code = v.Status
        join
    invoice i on v.idVisit = i.Order_Number and v.Span = i.Suborder_Number and i.Sold_To_Id in (".implode(",",$baIds).")
        left join
    invoice_line il on i.idInvoice = il.Invoice_Id and il.Deleted = 0 and il.Item_Id = ". ItemId::Lodging ."
        left join
    gen_lookups invs on invs.Table_Name = 'Invoice_Status' and invs.Code = i.Status
        join
    name ba on i.Sold_To_Id = ba.idName and ba.idName in (".implode(",",$baIds).")
where i.Deleted = 0 and " . $whDates . $whBilling . " group by v.idVisit, v.Span, i.Sold_To_Id order by v.idVisit";
    }

    public function getStats(){
        $stmt = $this->dbh->query($this->query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $patientIds = array();
        $totalBilled = 0.00;
        foreach ($rows as $row){
            $patientIds[] = $row["pId"];
            $totalBilled+= $row["Invoice_Amount"];
        }
        return ["TotalPatientsServed" => count(array_unique($patientIds)), "TotalBilled"=>$totalBilled];
    }

    public function makeFilterMkup():void{
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->billingAgentMarkup()->generateMarkup();
        $this->filterMkup .= $this->getItemMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->diagnosisMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    

    public function makeFilterOptsMkup():void{

    }

    public function makeCFields():array{
        $labels = Labels::getLabels();
        $uS = Session::getInstance();
        
        //$cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15');
        $cFields[] = array("Visit ID", 'idVisit', 'checked', '', 'string', '20');
        $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " ID", 'pId', 'checked', '', 'string', '20');
        $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " First", 'Name_First', 'checked', '', 'string', '20');
        $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " Last", 'Name_Last', 'checked', '', 'string', '20');

        // Address.
        $pFields = array('pAddr', 'pCity');
        $pTitles = array($labels->getString("MemberType", "patient", "Patient") . ' Address', $labels->getString("MemberType", "patient", "Patient") . ' City');

        if ($uS->county) {
            $pFields[] = 'pCounty';
            $pTitles[] = $labels->getString("MemberType", "patient", "Patient") . ' County';
        }

        $pFields = array_merge($pFields, array('pState', 'pCountry', 'pZip'));
        $pTitles = array_merge($pTitles, array($labels->getString("MemberType", "patient", "Patient") . ' State', $labels->getString("MemberType", "patient", "Patient") . ' Country', $labels->getString("MemberType", "patient", "Patient") . ' Zip'));

        $cFields[] = array($pTitles, $pFields, '', '', 'string', '15', array());

        $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " DOB", 'DOB', '', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " Age", 'Age', '', '', 'string', '15');
        $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " Diagnosis", 'Diagnosis', '', '', 'string', '20');

        //demographics
        foreach ($this->demogs as $d) {
            if (strtolower($d[2]) == 'y'){
                $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " " . $d[1], $d[0], '', '', 'string', '20');
            }
        }

        $cFields[] = array("Visit Span Arrival", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Visit Span Departure", 'Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array($labels->getString("MemberType", "primaryGuest", "Primary Guest") . " First", 'pgFirst', 'checked', '', 'string', '20');
        $cFields[] = array($labels->getString("MemberType", "primaryGuest", "Primary Guest") . " Last", 'pgLast', 'checked', '', 'string', '20');
        $cFields[] = array("Visit Status", 'Status_Title', 'checked', '', 'string', '15');
        $cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15');
        $cFields[] = array("Billed To", 'Billed To', 'checked', '', 'string', '20');
        //$cFields[] = array("Nights Billed", "PaidNights", 'checked', '', 'string', '20');
        $cFields[] = array("Amount", 'Invoice_Amount', 'checked', '', 'string', '15');
        //$cFields[] = array("Invoice Status", 'Invoice_Status_Title', 'checked', '', 'string', '15');


        return $cFields;
    }

    public function makeSummaryMkup():string {
        $stats = $this->getStats();

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        $mkup .= HTMLContainer::generateMarkup("p", 'Biling Agents: ' . implode(", ", $this->selectedBillingAgentNames));
        
        if(isset($stats["TotalPatientsServed"])){
            $mkup .= HTMLContainer::generateMarkup("p", "Unique ".Labels::getString("MemberType", "patient", "Patient")."s Served: " . $stats["TotalPatientsServed"]);
        }

        if(isset($stats["TotalBilled"])){
            $mkup .= HTMLContainer::generateMarkup("p", "Total Amount Billed: $" . number_format($stats["TotalBilled"],2));
        }

        return $mkup;

    }

    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();
        $uS = Session::getInstance();

        foreach($this->resultSet as $k=>$r) {
            $this->resultSet[$k]["Invoice_Amount"] = "$" . number_format($r["Invoice_Amount"],2);
            $this->resultSet[$k]["Invoice_Number"] = HTMLContainer::generateMarkup('a', $r['Invoice_Number'], array('href'=>'ShowInvoice.php?invnum='.$r['Invoice_Number'], 'target'=>'_blank'));
            $this->resultSet[$k]["idVisit"] = HTMLContainer::generateMarkup('div', $r['idVisit'], array('class'=>'hhk-viewVisit', 'data-gid'=>"", 'data-vid'=>$r['visitId'], 'data-span'=>$r['Span'], 'style'=>'display:inline-table;'));
            $this->resultSet[$k]['pId'] = HTMLContainer::generateMarkup('a', $r['pId'], array('href'=>'GuestEdit.php?id=' . $r['pId'] . '&psg=' . $r['idPsg']));
        }

        return parent::generateMarkup($outputType);
    }
}
?>