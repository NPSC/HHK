<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\SysConst\ItemId;
use HHK\SysConst\VolMemberType;

/**
 * BillingAgentReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Billing Agent Report
 *
 * Lists all stays that a specific billing agent paid for
 *
 * @author Will
 */

class BillingAgentReport extends AbstractReport implements ReportInterface {

    public array $diags;
    public array $demogs;
    public array $billingAgents;
    public array $selectedBillingAgents;
    public array $selectedBillingAgentNames;


    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Billing Agent Report';
        $this->description = "This report shows all patients who stayed in the time period and were invoiced to a specific billing agent";
        $this->inputSetReportName = "billingAgent";

        $this->demogs = readGenLookupsPDO($dbh, 'Demographics');
        $this->billingAgents = $this->loadBillingAgents($dbh);
        if (filter_has_var(INPUT_POST, 'selBillingAgents')) {
            $this->selectedBillingAgents = filter_input(INPUT_POST, 'selBillingAgents', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
            if($this->selectedBillingAgents[0] == ""){
                unset($this->selectedBillingAgents[0]);
            }
        }else{
            $this->selectedBillingAgents = [];
        }
        $this->selectedBillingAgentNames = $this->getSelectedBillingAgentNames();

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    protected function loadBillingAgents(\PDO $dbh){
        $stmt = $dbh->query("SELECT n.idName, if(trim(n.Name_Full) != '', n.Name_Full, n.Company) as `Title`" .
            " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
            " where n.Member_Status='a' and n.Record_Member = 1 order by n.Company");
        return $stmt->fetchAll(\PDO::FETCH_NUM);
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

        $departureCase = "CASE WHEN v.Actual_Departure IS NOT NULL THEN v.Actual_Departure
         WHEN v.Expected_Departure IS NOT NULL AND v.Expected_Departure > NOW() THEN v.Expected_Departure
         WHEN v.Status = 'a' THEN ''
         ELSE '' END";

        $whDepartureCase = "CASE WHEN v.Actual_Departure IS NOT NULL THEN v.Actual_Departure
        WHEN v.Expected_Departure IS NOT NULL AND v.Expected_Departure > NOW() THEN v.Expected_Departure
        ELSE NOW() END";

        $whDates =  "v.Arrival_Date <= '" . $this->filter->getReportEnd() . "' and " . $whDepartureCase . " >= '" . $this->filter->getReportStart() . "' ";

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
    ifnull(p.idName, '') as `pId`,
    ifnull(p.Name_Last, '') as Name_Last,
    ifnull(p.Name_First, '') as Name_First,
    concat(ifnull(pa.Address_1, ''), '', ifnull(pa.Address_2, ''))  as pAddr,
    ifnull(pa.City, '') as pCity,
    ifnull(pa.County, '') as pCounty,
    ifnull(pa.State_Province, '') as pState,
    ifnull(pa.Country_Code, '') as pCountry,
    ifnull(pa.Postal_Code, '') as `pZip`,
    concat(if(dc.Description is not null, concat(dc.Description, ': '), ''), ifnull(d.Description, '')) as `Diagnosis`,
    " . $listDemos . "
    ifnull(v.Arrival_Date, '') as `Arrival`,
    " . $departureCase . " as `Departure`,
    DATEDIFF(ifnull(v.Actual_Departure, v.Expected_Departure), v.Arrival_Date) as `Nights`,
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
where " . $whDates . $whBilling . " group by v.idVisit, v.Span, i.Sold_To_Id order by v.idVisit";
    }

    public function getStats(){
        $stmt = $this->dbh->query($this->query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $patientIds = array();
        $totalBilled = 0.00;
        $paidNights = 0;
        foreach ($rows as $row){
            $patientIds[] = $row["pId"];
            $totalBilled+= $row["Invoice_Amount"];
            $paidNights+= $row["PaidNights"];
        }
        return ["TotalPatientsServed" => count(array_unique($patientIds)), "TotalBilled"=>$totalBilled, "PaidNights"=>$paidNights];
    }

    public function makeFilterMkup():void{
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->getBillingAgentMarkup()->generateMarkup();
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

        $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " Diagnosis", 'Diagnosis', '', '', 'string', '20');

        //demographics
        foreach ($this->demogs as $d) {
            if (strtolower($d[2]) == 'y'){
                $cFields[] = array($labels->getString("MemberType", "patient", "Patient") . " " . $d[1], $d[0], '', '', 'string', '20');
            }
        }

        $cFields[] = array("Visit Arrival", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Visit Departure", 'Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array($labels->getString("MemberType", "primaryGuest", "Primary Guest") . " First", 'pgFirst', 'checked', '', 'string', '20');
        $cFields[] = array($labels->getString("MemberType", "primaryGuest", "Primary Guest") . " Last", 'pgLast', 'checked', '', 'string', '20');
        $cFields[] = array("Visit Status", 'Status_Title', 'checked', '', 'string', '15');
        $cFields[] = array("Billed To", 'Billed To', 'checked', '', 'string', '20');
        $cFields[] = array("Nights Billed", "PaidNights", 'checked', '', 'string', '20');
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

        if(isset($stats["PaidNights"])){
            $mkup .= HTMLContainer::generateMarkup("p", "Total Nights Billed: " . $stats["PaidNights"]);
        }

        return $mkup;

    }

    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();
        $uS = Session::getInstance();

        foreach($this->resultSet as $k=>$r) {
            //$this->resultSet[$k]['Name_Last'] = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>$uS->resourceURL . 'house/GuestEdit.php?id=' . $r['idGuest'] . '&psg=' . $r['idPsg']));
        }

        return parent::generateMarkup($outputType);
    }
}
?>