<?php

namespace HHK\House\Report;

use HHK\ExcelHelper;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\TaxedItem;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\SysConst\ItemId;
use HHK\SysConst\VolMemberType;
use HHK\TableLog\HouseLog;

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

    public array $demogs;
    public array $additionalCharges;
    public array $selectedAdditionalCharges = [];
    protected array $statsArray = [];


    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Additional Charges Report';
        $this->description = "This report shows all additional charges charged to patients and their demographics who stayed in the time period";
        $this->inputSetReportName = "additionalCharges";

        $this->demogs = readGenLookupsPDO($dbh, 'Demographics');
        $this->additionalCharges = array_merge($this->formatGenLookup(readGenLookupsPDO($dbh, 'Addnl_Charge'), "Additional Charges"), $this->formatGenLookup(readGenLookupsPDO($dbh, 'House_Discount'), "Discounts"));

        if (filter_has_var(INPUT_POST, 'selAdditionalCharges')) {
            $this->selectedAdditionalCharges = filter_input(INPUT_POST, 'selAdditionalCharges', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY);
            if($this->selectedAdditionalCharges[0] == ""){
                unset($this->selectedAdditionalCharges[0]);
            }
        }

        $this->filter = new ReportFilter();
        $this->filter->createBillingAgents($dbh);
        $this->filter->createDiagnoses($dbh);
        $this->filter->loadSelectedDiagnoses();
        $this->filter->loadSelectedBillingAgents();

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    protected function formatGenLookup(array $genLookups, string $group = ""){
        foreach($genLookups as $k=>&$v){
            $v["Substitute"] = $group;
            $v[2] = $group;
        }
        return $genLookups;
    }

    protected function getAdditionalChargesMarkup(){
        $additionalChargesSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->additionalCharges, $this->selectedAdditionalCharges), array('name' => 'selAdditionalCharges[]', 'size' => (count($this->additionalCharges) + 3), 'multiple' => 'multiple', 'style'=>'width: 100%;'));
        $tbl = new HTMLTable();
        $tr = '';
        
        $tbl->addHeaderTr( HTMLTable::makeTh("Additional Charges/Discounts"));
        
        $tbl->addBodyTr(HTMLTable::makeTd($additionalChargesSelector, array('style'=>'vertical-align: top;')));
        
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
        if(count($this->filter->getSelectedBillingAgents()) > 0 && !in_array("", $this->filter->getSelectedBillingAgents())){
            $billingAgents = implode(",", $this->filter->getSelectedBillingAgents());
            $whBilling = " and i.Sold_To_Id in (" . $billingAgents . ")";
        }

        $selectedDiags = $this->filter->getSelectedDiagnoses();
        $whDiags = "";
        if(count($selectedDiags) > 0 && !in_array("", $selectedDiags)){
            foreach($selectedDiags as $d){
                if ($d != '') {
                    if ($whDiags == '') {
                        $whDiags .= "'".$d."'";
                    } else {
                        $whDiags .= ",'". $d."'";
                    }
                }
            }

            $whDiags = " and hs.Diagnosis in (" . $whDiags . ")";
        }

        $selectedCharges = $this->selectedAdditionalCharges;
        $whCharges = "";
        if(count($selectedCharges) > 0){
            foreach($selectedCharges as $d){
                if ($d != '' && isset($this->additionalCharges[$d])) {
                    if ($whCharges == '') {
                        $whCharges .= "'".$this->additionalCharges[$d]["Description"]."'";
                    } else {
                        $whCharges .= ",'". $this->additionalCharges[$d]["Description"]."'";
                    }
                }
            }

            $whCharges = " and il.description in (" . $whCharges . ")";
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
    sum(ifnull(il.Amount, '')) as `Invoice_Amount`,
    if(trim(ba.Name_Full) != '', ba.Name_Full, ba.Company) as `Billed To`,
    il.Description as `Additional Charge/Discount`,
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
    invoice i on v.idVisit = i.Order_Number and v.Span = i.Suborder_Number
        join
    invoice_line il on i.idInvoice = il.Invoice_Id and il.Deleted = 0 and il.Item_Id IN (". ItemId::AddnlCharge .", " . ItemId::Discount . ")
        left join
    gen_lookups invs on invs.Table_Name = 'Invoice_Status' and invs.Code = i.Status
        join
    name ba on i.Sold_To_Id = ba.idName
where i.Deleted = 0 and " . $whDates . $whBilling . $whDiags . $whCharges . " group by i.idInvoice order by v.idVisit";
    }

    /**
     * Retrieves statistics for the additional charges report.
     *
     * This method executes the query to fetch the report data and calculates
     * the total number of unique patients served, the total amount billed, 
     * and the unique patient and visit IDs.
     *
     * @return array An associative array containing:
     *               - "TotalPatientsServed": The number of unique patients served.
     *               - "TotalBilled": The total amount billed.
     *               - "patientIds": An array of unique patient IDs.
     *               - "visitIds": An array of unique visit IDs.
     */
    public function getStats(){
        if(count($this->statsArray) == 0){

            $stmt = $this->dbh->query($this->query);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $patientIds = array();
            $visitIds = array();
            $totalBilled = 0.00;
            
            foreach ($rows as $row){
                $patientIds[] = $row["pId"];
                $visitIds[] = $row["visitId"] . "-" . $row["Span"];
                $totalBilled+= $row["Invoice_Amount"];
            }

            $patientIds = array_unique($patientIds);
            $visitIds = array_unique($visitIds);

            $this->statsArray = ["TotalPatientsServed" => count($patientIds), "TotalBilled"=>$totalBilled, "patientIds"=>$patientIds, "visitIds"=>$visitIds];
        }

        return $this->statsArray;
    }

    public function makeFilterMkup():void{
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->billingAgentMarkup()->generateMarkup();
        $this->filterMkup .= $this->getAdditionalChargesMarkup()->generateMarkup();
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
        $cFields[] = array("Additional Charge/Discount", 'Additional Charge/Discount', 'checked', '', 'string', '20');
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

        $mkup .= HTMLContainer::generateMarkup("p", 'Biling Agents: ' . $this->filter->getSelectedBillingAgentsString());

        $mkup .= HTMLContainer::generateMarkup("p", 'Additional Charges/Discounts: ' . $this->getSelectedAdditionalChargesString());

        $mkup .= HTMLContainer::generateMarkup("p", 'Diagnoses: ' . $this->filter->getSelectedDiagnosesString());
        
        if(isset($stats["TotalPatientsServed"])){
            $mkup .= HTMLContainer::generateMarkup("p", "Unique ".Labels::getString("MemberType", "patient", "Patient")."s Served: " . $stats["TotalPatientsServed"]);
        }

        if(isset($stats["TotalBilled"])){
            $mkup .= HTMLContainer::generateMarkup("p", "Total Amount Billed: $" . number_format($stats["TotalBilled"],2));
        }

        $totalsMkup = "";
        $totalsMkup .= $this->generateSummaryTable("Additional Charge", $this->getAdditionalChargeCounts())->generateMarkup(['class'=>'mx-2 mb-2']);
        

        foreach($this->colSelector->getFilteredFields() as $fld){
            if($fld[1] == "Age"){
                $totalsMkup .= $this->generateSummaryTable("Age", $this->getAgeCounts())->generateMarkup(['class'=>'mx-2 mb-2']);
            }

            if($fld[1] == "pAddr"){
                $totalsMkup .= $this->generateZipCodeSummaryTable($this->getZipCodeTotals())->generateMarkup(['class'=>'mx-2 mb-2']);
            }

            if (isset($this->demogs[$fld[1]]) && strtolower($this->demogs[$fld[1]][2]) == 'y'){
                $totalsMkup .= $this->generateSummaryTable($this->demogs[$fld[1]]["Description"], $this->getDemographicTotals($this->demogs[$fld[1]]["Code"]))->generateMarkup(['class'=>'mx-2 mb-2']);
            }
        }

        $this->statsMkup = HTMLContainer::generateMarkup("div",
        HTMLContainer::generateMarkup("h3", "Summary", ["class"=>"ui-widget-header ui-state-default ui-corner-all"]) . 
        HTMLContainer::generateMarkup("div", $totalsMkup, ["class"=>"hhk-flex hhk-tdbox hhk-visitdialog ui-widget-content ui-corner-bottom pt-3 pb-2", "style"=>"flex-flow:wrap;"])
        , ["class"=>"ui-widget my-3", "id"=>"summaryAccordion"]);

        return $mkup;

    }

    public function getSelectedAdditionalChargesString(){
        $chargeTitles = "";
        foreach ($this->selectedAdditionalCharges as $h) {
            if (isset($this->additionalCharges[$h])) {
                $chargeTitles .= $this->additionalCharges[$h][1] . ', ';
            }
        }
        if ($chargeTitles != '') {
            $h = trim($chargeTitles);
            return substr($h, 0, strlen($h) - 1);
        }else{
            return "All";
        }
    }

    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();
        $uS = Session::getInstance();

        foreach($this->resultSet as $k=>$r) {
            $this->resultSet[$k]["Invoice_Amount"] = "$" . number_format($r["Invoice_Amount"],2);
            if($outputType == ""){
                $this->resultSet[$k]["Invoice_Number"] = HTMLContainer::generateMarkup('a', $r['Invoice_Number'], array('href'=>'ShowInvoice.php?invnum='.$r['Invoice_Number'], 'target'=>'_blank'));
                $this->resultSet[$k]["idVisit"] = HTMLContainer::generateMarkup('div', $r['idVisit'], array('class'=>'hhk-viewVisit', 'data-gid'=>"", 'data-vid'=>$r['visitId'], 'data-span'=>$r['Span'], 'style'=>'display:inline-table;'));
                $this->resultSet[$k]['pId'] = HTMLContainer::generateMarkup('a', $r['pId'], array('href'=>'GuestEdit.php?id=' . $r['pId'] . '&psg=' . $r['idPsg']));
            }
        }

        return parent::generateMarkup($outputType);
    }

    protected function getAdditionalChargeCounts(){
        $visitIds = $this->getStats()["visitIds"];

        if(count($visitIds) == 0){
            $visitIds = ['null'];
        }else{
            foreach($visitIds as $k=>$v){
                $visitIds[$k] = "'".$v."'";
            }
        }

        $visitIds = implode(", ", $visitIds);

        $selectedCharges = $this->selectedAdditionalCharges;
        $whCharges = "";
        if(count($selectedCharges) > 0){
            foreach($selectedCharges as $d){
                if ($d != '' && isset($this->additionalCharges[$d])) {
                    if ($whCharges == '') {
                        $whCharges .= "'".$this->additionalCharges[$d]["Description"]."'";
                    } else {
                        $whCharges .= ",'". $this->additionalCharges[$d]["Description"]."'";
                    }
                }
            }

            $whCharges = " and il.description in (" . $whCharges . ")";
        }

        $query = 'select il.description, count(*) as `count` from invoice_line il
join invoice i on il.Invoice_Id = i.idInvoice
join visit v on i.Order_Number = v.idVisit and i.Suborder_Number = v.Span
where il.Item_Id = ' . ItemId::AddnlCharge . ' and 
il.Deleted = 0 and
concat(v.idVisit, "-", v.Span) in (' . $visitIds .') ' . $whCharges . '
group by il.Description';

        $stmt = $this->dbh->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    protected function getAgeCounts(){
        $patientIds = $this->getStats()["patientIds"];

        if(count($patientIds) == 0){
            $patientIds = [0];
        }

        $query = 'select concat(10*floor(timestampdiff(YEAR, n.BirthDate, CURDATE())/10), "-", 10*floor(timestampdiff(YEAR, n.BirthDate, CURDATE())/10) + 9) as `description`, count(*) as `count` from `name` n
where n.idName in (' . implode(", ", $patientIds) . ')
group by `description`
;';

        $stmt = $this->dbh->query($query);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //fill defined brackets
        $brackets = ["0-19"=>["description"=>"0-19", "count"=>0],
            "20-29"=>["description"=>"20-29", "count"=>0],
            "30-39"=>["description"=>"30-39", "count"=>0],
            "40-49"=>["description"=>"40-49", "count"=>0],
            "50-59"=>["description"=>"50-59", "count"=>0],
            "60-69"=>["description"=>"60-69", "count"=>0],
            "70-79"=>["description"=>"70-79", "count"=>0],
            "80+"=>["description"=>"80+", "count"=>0],
            "Unknown"=>["description"=>"Unknown", "count"=>0]
        ];

        foreach($results as $result){
            if($result["description"] == "0-9" || $result["description"] == "10-19"){
                $brackets["0-19"]["count"] += $result["count"];
            }else if(isset($brackets[$result["description"]])){
                $brackets[$result["description"]]["count"] = $result["count"];
            }else if($result["description"] == null){
                $brackets["Unknown"]["count"] = $result["count"];
            }else{
                $brackets["80+"]["count"] += $result["count"];
            }
        }
        return $brackets;
    }

    protected function getDemographicTotals(string $demographic){
        $patientIds = $this->getStats()["patientIds"];

        if(count($patientIds) == 0){
            $patientIds = [0];
        }

        $join = 'left join name_demog nd on nd.'.$demographic.' = de.Code and de.Table_Name = "' . $demographic . '" left join name n on nd.idName = n.idName and n.idName in (' . implode(", ", $patientIds) . ')';
        if($demographic == "Gender"){
            $join = 'left join name n on n.'.$demographic.' = de.Code and de.Table_Name = "' . $demographic . '" and n.idName in (' . implode(", ", $patientIds) . ')';
        }

        $query = 'select de.Description as "description", count(n.idName) as `count` from gen_lookups de '
        .$join.
        'where de.Table_Name = "'.$demographic.'"
group by de.`description` order by de.Order asc, de.`Code` asc
;';

        $stmt = $this->dbh->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function getZipCodeTotals(){
        $patientIds = $this->getStats()["patientIds"];

        if(count($patientIds) == 0){
            $patientIds = [0];
        }

        $query = 'select na.City, na.State_Province, na.Postal_Code, count(n.idName) as `count` from name n 
        join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose 
        where n.idName in (' . implode(", ", $patientIds) . ')' .
'group by na.Postal_Code;';

        $stmt = $this->dbh->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Summary of generateSummaryTable
     * @param string $header
     * @param array $data
     * @return HTMLTable
     */
    protected function generateSummaryTable(string $header, array $data){
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh($header) . HTMLTable::makeTh("Count"));
        $total = 0;
        
        foreach($data as $row){
            $tbl->addBodyTr(
                HTMLTable::makeTd($row["description"], ['style'=>'white-space: nowrap;'])
                .HTMLTable::makeTd($row["count"])
            );
            $total += $row["count"];
        }

        $tbl->addBodyTr(
            HTMLTable::makeTd("Total", ['style'=>'white-space: nowrap;'])
            .HTMLTable::makeTd($total)
        , ["style"=>"font-weight: bold; border-top: 2px solid #2E99DD"]);

        return $tbl;
    }

    protected function generateZipCodeSummaryTable(array $data){
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh("City") . HTMLTable::makeTh("State") .  HTMLTable::makeTh("Zip Code") . HTMLTable::makeTh("Count"));
        $total = 0;
        
        foreach($data as $row){
            $tbl->addBodyTr(
                HTMLTable::makeTd($row["City"], ['style'=>'white-space: nowrap;'])
                .HTMLTable::makeTd($row["State_Province"], ['style'=>'white-space: nowrap;'])
                .HTMLTable::makeTd($row["Postal_Code"], ['style'=>'white-space: nowrap;'])
                .HTMLTable::makeTd($row["count"])
            );
            $total += $row["count"];
        }

        $tbl->addBodyTr(
            HTMLTable::makeTd("Total", ['style'=>'white-space: nowrap;', 'colspan'=>'3'])
            .HTMLTable::makeTd($total)
        , ["style"=>"font-weight: bold; border-top: 2px solid #2E99DD"]);

        return $tbl;
    }

    protected function generateExcelSummaryTable(string $header, array $data, ExcelHelper &$writer){
        
        $writer->writeSheetHeader("Summary", [$header=>"string", "Count"=>"integer"], $writer->getHdrStyle([20, 10]));
        $total = 0;
        
        foreach($data as $row){
            $writer->writeSheetRow("Summary", [$row["description"],$row["count"]]);
            $total += $row["count"];
        }
        $writer->writeSheetRow("Summary", ["Total",$total], ['font-style'=>'bold']);
        $writer->writeSheetRow("Summary", []);
    }

    protected function generateExcelZipCodeSummaryTable(array $data, ExcelHelper &$writer){
        $writer->writeSheetHeader("Zip Codes", ["City"=>"string", "State"=>"string", "Zip Code"=>"string", "Count"=>"integer"], $writer->getHdrStyle([20, 10, 10, 10]));
        $total = 0;
        
        foreach($data as $row){
            $writer->writeSheetRow("Zip Codes", [$row["City"],$row["State_Province"],$row["Postal_Code"],$row["count"]]);
            $total += $row["count"];
        }
        $writer->writeSheetRow("Zip Codes", ["Total","","",$total], ['font-style'=>'bold']);
    }

    public function downloadExcel(string $fileName = "HHKReport"):void {

        $uS = Session::getInstance();
        $writer = new ExcelHelper($fileName);
        $writer->setAuthor($uS->username);
        $writer->setTitle($this->reportTitle);

        // build header
        $hdr = array();
        $flds = array();
        $colWidths = array();


        foreach($this->filteredFields as $field){
            $hdr[$field[0]] = $field[4]; //set column header name and type;
            $colWidths[] = $field[5]; //set column width
        }

        $this->getResultSet();

        //summary sheet
        $this->generateExcelSummaryTable("Additional Charge", $this->getAdditionalChargeCounts(), $writer);

        foreach($this->colSelector->getFilteredFields() as $fld){
            if($fld[1] == "Age"){
                $this->generateExcelSummaryTable("Age", $this->getAgeCounts(), $writer);
            }

            if($fld[1] == "pAddr"){
                $this->generateExcelZipCodeSummaryTable($this->getZipCodeTotals(), $writer);
            }

            if (isset($this->demogs[$fld[1]]) && strtolower($this->demogs[$fld[1]][2]) == 'y'){
                $this->generateExcelSummaryTable($this->demogs[$fld[1]]["Description"], $this->getDemographicTotals($this->demogs[$fld[1]]["Code"]), $writer);
            }
        }

        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Raw Data", $hdr, $hdrStyle);

        foreach($this->resultSet as $r){

            $flds = array();

            foreach ($this->filteredFields as $f) {
                $flds[] = $r[$f[1]];
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Raw Data", $row);
        }

        HouseLog::logDownload($this->dbh, $this->reportTitle, "Excel", $this->reportTitle . " for " . $this->filter->getReportStart() . " - " . $this->filter->getReportEnd() . " downloaded", $uS->username);

        $writer->download();
    }
}