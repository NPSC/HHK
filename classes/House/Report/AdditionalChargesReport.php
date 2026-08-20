<?php

namespace HHK\House\Report;

use HHK\Common;
use HHK\ExcelHelper;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\Item;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\SysConst\ItemId;
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
    protected string $addnlChargeLabel;
    protected string $discountLabel;


    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();
        $this->addnlChargeLabel = (new Item($dbh, ItemId::AddnlCharge))->getDescription() . 's';
        $this->discountLabel = (new Item($dbh, ItemId::Discount))->getDescription() . 's';
        $this->reportTitle = $uS->siteName . ' ' . $this->addnlChargeLabel . ' Report';
        
        $this->description = "This report shows all " . strtolower($this->addnlChargeLabel) . " and " . strtolower($this->discountLabel) . " applied to patients and their demographics who stayed in the time period";
        $this->inputSetReportName = "additionalCharges";

        $this->demogs = Common::readGenLookupsPDO($dbh, 'Demographics');
        $this->additionalCharges = array_merge($this->formatGenLookup(Common::readGenLookupsPDO($dbh, 'Addnl_Charge'), $this->addnlChargeLabel), $this->formatGenLookup(Common::readGenLookupsPDO($dbh, 'House_Discount'), $this->discountLabel));

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

    protected function getAdditionalChargesMarkup(): HTMLTable{

        $additionalChargesSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->additionalCharges, $this->selectedAdditionalCharges), array('name' => 'selAdditionalCharges[]', 'size' => (count($this->additionalCharges) + 3), 'multiple' => 'multiple', 'style'=>'width: 100%;'));
        $tbl = new HTMLTable();
        $tr = '';
        
        $tbl->addHeaderTr( HTMLTable::makeTh($this->addnlChargeLabel . '/' . $this->discountLabel));
        
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
                    $joinDemos .= "LEFT JOIN `gen_lookups` `Gender` ON `p`.`Gender` = `Gender`.`Code` AND `Gender`.`Table_Name` = 'Gender'";
                }else{
                    $joinDemos .= "LEFT JOIN `gen_lookups` `" . $d[0] . "` ON `pd`.`".$d[0]."` = `".$d[0]."`.`Code` AND `".$d[0]."`.`Table_Name` = '".$d[0]."'";
                }
                $listDemos .= "IFNULL(`".$d[0]."`.`Description`, '') AS `".$d[0]."`,";

            }
        }

        $departureCase = "CASE WHEN `v`.`Span_End` IS NOT NULL THEN `v`.`Span_End`
         WHEN `v`.`Expected_Departure` IS NOT NULL AND `v`.`Expected_Departure` > NOW() THEN `v`.`Expected_Departure`
         WHEN `v`.`Status` = 'a' THEN ''
         ELSE '' END";

        $whDepartureCase = "CASE WHEN `v`.`Span_End` IS NOT NULL THEN `v`.`Span_End`
        WHEN `v`.`Expected_Departure` IS NOT NULL AND `v`.`Expected_Departure` > NOW() THEN `v`.`Expected_Departure`
        ELSE NOW() END";

        $whDates =  "`v`.`Span_Start` <= :reportEnd AND " . $whDepartureCase . " >= :reportStart ";
        $this->queryParams = [':reportEnd' => $this->filter->getReportEnd(), ':reportStart' => $this->filter->getReportStart()];

        $whBilling = "";
        if(count($this->filter->getSelectedBillingAgents()) > 0 && !in_array("", $this->filter->getSelectedBillingAgents())){
            $billingPh = [];
            foreach ($this->filter->getSelectedBillingAgents() as $i => $ba) {
                $ph = ':ba' . $i;
                $billingPh[] = $ph;
                $this->queryParams[$ph] = $ba;
            }
            $whBilling = " AND `i`.`Sold_To_Id` IN (" . implode(', ', $billingPh) . ")";
        }

        $selectedDiags = $this->filter->getSelectedDiagnoses();
        $whDiags = "";
        if(count($selectedDiags) > 0 && !in_array("", $selectedDiags)){
            $diagPh = [];
            foreach($selectedDiags as $i => $d){
                if ($d != '') {
                    $ph = ':diag' . $i;
                    $diagPh[] = $ph;
                    $this->queryParams[$ph] = $d;
                }
            }

            $whDiags = " AND `hs`.`Diagnosis` IN (" . implode(', ', $diagPh) . ")";
        }

        $selectedCharges = $this->selectedAdditionalCharges;
        $whCharges = "";
        if(count($selectedCharges) > 0){
            $chargePh = [];
            foreach($selectedCharges as $i => $d){
                if ($d != '' && isset($this->additionalCharges[$d])) {
                    $ph = ':chg' . $i;
                    $chargePh[] = $ph;
                    $this->queryParams[$ph] = $this->additionalCharges[$d]["Description"];
                }
            }

            $whCharges = " AND `il`.`description` IN (" . implode(', ', $chargePh) . ")";
        }


        $this->queryParams[':itemAddnlCharge'] = ItemId::AddnlCharge;
        $this->queryParams[':itemDiscount'] = ItemId::Discount;

        $this->query = "SELECT
    CONCAT(`v`.`idVisit`, '-', `v`.`Span`) AS `idVisit`,
    `v`.`idVisit` AS `visitId`,
    `v`.`Span` AS `Span`,
    IFNULL(`p`.`idName`, '') AS `pId`,
    IFNULL(`hs`.`idPsg`, '') AS `idPsg`,
    IFNULL(`p`.`Name_Last`, '') AS `Name_Last`,
    IFNULL(`p`.`Name_First`, '') AS `Name_First`,
    CONCAT(IFNULL(`pa`.`Address_1`, ''), '', IFNULL(`pa`.`Address_2`, ''))  AS `pAddr`,
    IFNULL(`pa`.`City`, '') AS `pCity`,
    IFNULL(`pa`.`County`, '') AS `pCounty`,
    IFNULL(`pa`.`State_Province`, '') AS `pState`,
    IFNULL(`pa`.`Country_Code`, '') AS `pCountry`,
    IFNULL(`pa`.`Postal_Code`, '') AS `pZip`,
    CONCAT(IF(`dc`.`Description` IS NOT NULL, CONCAT(`dc`.`Description`, ': '), ''), IFNULL(`d`.`Description`, '')) AS `Diagnosis`,
    IFNULL(`p`.`BirthDate`, '') AS `DOB`,
    TIMESTAMPDIFF(YEAR, `p`.`BirthDate`, CURDATE()) AS `Age`,
    " . $listDemos . "
    IFNULL(`v`.`Span_Start`, '') AS `Arrival`,
    " . $departureCase . " AS `Departure`,
    DATEDIFF(IFNULL(`v`.`Span_End`, DATE(NOW())), `v`.`Span_Start`) AS `Nights`,
    SUM(DATEDIFF(`il`.`Period_End`, `il`.`Period_Start`)) AS `PaidNights`,
    IFNULL(`pgn`.`Name_First`, '') AS `pgFirst`,
    IFNULL(`pgn`.`Name_Last`, '') AS `pgLast`,
    IFNULL(`vs`.`Description`, '') AS `Status_Title`,
    IFNULL(`i`.`Invoice_Number`, '') AS `Invoice_Number`,
    SUM(IFNULL(`il`.`Amount`, '')) AS `Invoice_Amount`,
    IF(TRIM(`ba`.`Name_Full`) != '', `ba`.`Name_Full`, `ba`.`Company`) AS `Billed To`,
    `il`.`Description` AS `Additional Charge/Discount`,
    IFNULL(`invs`.`Description`, '') AS `Invoice_Status_Title`
FROM
    `visit` `v`
        LEFT JOIN
    `hospital_stay` `hs` ON `v`.`idHospital_Stay` = `hs`.`idHospital_Stay`
        LEFT JOIN
    `gen_lookups` `d` ON `hs`.`Diagnosis` = `d`.`Code` AND `d`.`Table_Name` = 'Diagnosis'
        LEFT JOIN
    `gen_lookups` `dc` ON `d`.`Substitute` = `dc`.`Code` AND `dc`.`Table_Name` = 'Diagnosis_Category'
        LEFT JOIN
    `name` `p` ON `hs`.`idPatient` = `p`.`idName`
        LEFT JOIN
    `name_address` `pa` ON `p`.`idName` = `pa`.`idName` AND `p`.`Preferred_Mail_Address` = `pa`.`Purpose`
        LEFT JOIN
    `name_demog` `pd` ON `p`.`idName` = `pd`.`idName`
    ".$joinDemos."
        LEFT JOIN
    `name` `pgn` ON `v`.`idPrimaryGuest` = `pgn`.`idName`
        LEFT JOIN
    `gen_lookups` `vs` ON `vs`.`Table_Name` = 'Visit_Status' AND `vs`.`Code` = `v`.`Status`
        JOIN
    `invoice` `i` ON `v`.`idVisit` = `i`.`Order_Number` AND `v`.`Span` = `i`.`Suborder_Number`
        JOIN
    `invoice_line` `il` ON `i`.`idInvoice` = `il`.`Invoice_Id` AND `il`.`Deleted` = 0 AND `il`.`Item_Id` IN (:itemAddnlCharge, :itemDiscount)
        LEFT JOIN
    `gen_lookups` `invs` ON `invs`.`Table_Name` = 'Invoice_Status' AND `invs`.`Code` = `i`.`Status`
        JOIN
    `name` `ba` ON `i`.`Sold_To_Id` = `ba`.`idName`
WHERE `i`.`Deleted` = 0 AND " . $whDates . $whBilling . $whDiags . $whCharges . " GROUP BY `i`.`idInvoice` ORDER BY `v`.`idVisit`";
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

            $stmt = $this->dbh->prepare($this->query);
            $stmt->execute($this->queryParams);
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
        $this->filterMkup .= (count($this->filter->billingAgents) > 0 ? $this->filter->billingAgentMarkup()->generateMarkup() : '');
        $this->filterMkup .= (count($this->additionalCharges) > 0 ? $this->getAdditionalChargesMarkup()->generateMarkup() : '');
        $this->filterMkup .= (count($this->filter->diagnoses) > 0 ? $this->filter->diagnosisMarkup()->generateMarkup() : '');
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    public function makeFields():array{
        $labels = Labels::getLabels();
        $uS = Session::getInstance();
        
        //$fields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15');
        $fields[] = array("Visit ID", 'idVisit', 'checked', '', 'string', '20');
        $fields[] = array($labels->getString("MemberType", "patient", "Patient") . " ID", 'pId', 'checked', '', 'string', '20');
        $fields[] = array($labels->getString("MemberType", "patient", "Patient") . " First", 'Name_First', 'checked', '', 'string', '20');
        $fields[] = array($labels->getString("MemberType", "patient", "Patient") . " Last", 'Name_Last', 'checked', '', 'string', '20');

        // Address.
        $pFields = array('pAddr', 'pCity');
        $pTitles = array($labels->getString("MemberType", "patient", "Patient") . ' Address', $labels->getString("MemberType", "patient", "Patient") . ' City');

        if ($uS->county) {
            $pFields[] = 'pCounty';
            $pTitles[] = $labels->getString("MemberType", "patient", "Patient") . ' County';
        }

        $pFields = array_merge($pFields, array('pState', 'pCountry', 'pZip'));
        $pTitles = array_merge($pTitles, array($labels->getString("MemberType", "patient", "Patient") . ' State', $labels->getString("MemberType", "patient", "Patient") . ' Country', $labels->getString("MemberType", "patient", "Patient") . ' Zip'));

        $fields[] = array($pTitles, $pFields, '', '', 'string', '15', array());

        $fields[] = array($labels->getString("MemberType", "patient", "Patient") . " DOB", 'DOB', '', '', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array($labels->getString("MemberType", "patient", "Patient") . " Age", 'Age', '', '', 'string', '15');
        $fields[] = array($labels->getString("MemberType", "patient", "Patient") . " Diagnosis", 'Diagnosis', '', '', 'string', '20');

        //demographics
        foreach ($this->demogs as $d) {
            if (strtolower($d[2]) == 'y'){
                $fields[] = array($labels->getString("MemberType", "patient", "Patient") . " " . $d[1], $d[0], '', '', 'string', '20');
            }
        }

        $fields[] = array("Visit Span Arrival", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array("Visit Span Departure", 'Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array($labels->getString("MemberType", "primaryGuest", "Primary Guest") . " First", 'pgFirst', 'checked', '', 'string', '20');
        $fields[] = array($labels->getString("MemberType", "primaryGuest", "Primary Guest") . " Last", 'pgLast', 'checked', '', 'string', '20');
        $fields[] = array("Visit Status", 'Status_Title', 'checked', '', 'string', '15');
        $fields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15');
        $fields[] = array($this->addnlChargeLabel . '/' . $this->discountLabel, 'Additional Charge/Discount', 'checked', '', 'string', '20');
        $fields[] = array("Billed To", 'Billed To', 'checked', '', 'string', '20');
        //$fields[] = array("Nights Billed", "PaidNights", 'checked', '', 'string', '20');
        $fields[] = array("Amount", 'Invoice_Amount', 'checked', '', 'string', '15');
        //$fields[] = array("Invoice Status", 'Invoice_Status_Title', 'checked', '', 'string',('MemberType", "patient", "Patient") . " Last", 'Name_Last', '


        return $fields;
    }

    public function makeSummaryMkup():string {
        $stats = $this->getStats();

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        $mkup .= HTMLContainer::generateMarkup("p", 'Biling Agents: ' . $this->filter->getSelectedBillingAgentsString());

        $mkup .= HTMLContainer::generateMarkup("p", $this->addnlChargeLabel . '/' . $this->discountLabel . ': ' . $this->getSelectedAdditionalChargesString());

        $mkup .= HTMLContainer::generateMarkup("p", 'Diagnoses: ' . $this->filter->getSelectedDiagnosesString());
        
        if(isset($stats["TotalPatientsServed"])){
            $mkup .= HTMLContainer::generateMarkup("p", "Unique ".Labels::getString("MemberType", "patient", "Patient")."s Served: " . $stats["TotalPatientsServed"]);
        }

        if(isset($stats["TotalBilled"])){
            $mkup .= HTMLContainer::generateMarkup("p", "Total Amount Billed: $" . number_format($stats["TotalBilled"],2));
        }

        $totalsMkup = "";
        $totalsMkup .= $this->generateSummaryTable($this->addnlChargeLabel, $this->getAdditionalChargeCounts())->generateMarkup(['class'=>'mx-2 mb-2']);
        

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

        $params = [':itemAddnlCharge' => ItemId::AddnlCharge];

        $visitPh = [];
        if(count($visitIds) == 0){
            $visitPh[] = ':vid0';
            $params[':vid0'] = 'null';
        }else{
            foreach($visitIds as $i=>$v){
                $ph = ':vid' . $i;
                $visitPh[] = $ph;
                $params[$ph] = $v;
            }
        }

        $selectedCharges = $this->selectedAdditionalCharges;
        $whCharges = "";
        if(count($selectedCharges) > 0){
            $chargePh = [];
            foreach($selectedCharges as $i => $d){
                if ($d != '' && isset($this->additionalCharges[$d])) {
                    $ph = ':chg' . $i;
                    $chargePh[] = $ph;
                    $params[$ph] = $this->additionalCharges[$d]["Description"];
                }
            }

            $whCharges = " AND `il`.`description` IN (" . implode(', ', $chargePh) . ")";
        }

        $query = 'SELECT `il`.`description`, COUNT(*) AS `count` FROM `invoice_line` `il`
JOIN `invoice` `i` ON `il`.`Invoice_Id` = `i`.`idInvoice`
JOIN `visit` `v` ON `i`.`Order_Number` = `v`.`idVisit` AND `i`.`Suborder_Number` = `v`.`Span`
WHERE `il`.`Item_Id` = :itemAddnlCharge AND
`il`.`Deleted` = 0 AND
CONCAT(`v`.`idVisit`, "-", `v`.`Span`) IN (' . implode(', ', $visitPh) .') ' . $whCharges . '
GROUP BY `il`.`Description`';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    protected function getAgeCounts(){
        $patientIds = $this->getStats()["patientIds"];

        if(count($patientIds) == 0){
            $patientIds = [0];
        }

        $patientPh = [];
        $params = [];
        foreach ($patientIds as $i => $pid) {
            $ph = ':pid' . $i;
            $patientPh[] = $ph;
            $params[$ph] = $pid;
        }

        $query = 'SELECT CONCAT(10*FLOOR(TIMESTAMPDIFF(YEAR, `n`.`BirthDate`, CURDATE())/10), "-", 10*FLOOR(TIMESTAMPDIFF(YEAR, `n`.`BirthDate`, CURDATE())/10) + 9) AS `description`, COUNT(*) AS `count` FROM `name` `n`
WHERE `n`.`idName` IN (' . implode(', ', $patientPh) . ')
GROUP BY `description`
;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute($params);
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

        $patientPh = [];
        $params = [':demographic' => $demographic];
        foreach ($patientIds as $i => $pid) {
            $ph = ':pid' . $i;
            $patientPh[] = $ph;
            $params[$ph] = $pid;
        }
        $patientList = implode(', ', $patientPh);

        $join = 'LEFT JOIN `name_demog` `nd` ON `nd`.`'.$demographic.'` = `de`.`Code` AND `de`.`Table_Name` = :demographic LEFT JOIN `name` `n` ON `nd`.`idName` = `n`.`idName` AND `n`.`idName` IN (' . $patientList . ')';
        if($demographic == "Gender"){
            $join = 'LEFT JOIN `name` `n` ON `n`.`'.$demographic.'` = `de`.`Code` AND `de`.`Table_Name` = :demographic AND `n`.`idName` IN (' . $patientList . ')';
        }

        $query = 'SELECT `de`.`Description` AS "description", COUNT(`n`.`idName`) AS `count` FROM `gen_lookups` `de` '
        .$join.
        'WHERE `de`.`Table_Name` = :demographic
GROUP BY `de`.`description` ORDER BY `de`.`Order` ASC, `de`.`Code` ASC
;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function getZipCodeTotals(){
        $patientIds = $this->getStats()["patientIds"];

        if(count($patientIds) == 0){
            $patientIds = [0];
        }

        $patientPh = [];
        $params = [];
        foreach ($patientIds as $i => $pid) {
            $ph = ':pid' . $i;
            $patientPh[] = $ph;
            $params[$ph] = $pid;
        }

        $query = 'SELECT `na`.`City`, `na`.`State_Province`, `na`.`Postal_Code`, COUNT(`n`.`idName`) AS `count` FROM `name` `n`
        JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName` AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
        WHERE `n`.`idName` IN (' . implode(', ', $patientPh) . ')' .
'GROUP BY `na`.`Postal_Code`;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute($params);
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
        $this->generateExcelSummaryTable($this->addnlChargeLabel, $this->getAdditionalChargeCounts(), $writer);

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