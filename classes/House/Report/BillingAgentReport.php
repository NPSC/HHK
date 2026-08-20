<?php

namespace HHK\House\Report;

use HHK\Common;
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

        $this->demogs = Common::readGenLookupsPDO($dbh, 'Demographics');
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
        $stmt = $dbh->prepare("SELECT `n`.`idName`, IF(TRIM(`n`.`Name_Full`) != '', `n`.`Name_Full`, `n`.`Company`) AS `Title`" .
            " FROM `name` `n` JOIN `name_volunteer2` `nv` ON `n`.`idName` = `nv`.`idName` AND `nv`.`Vol_Category` = 'Vol_Type'  AND `nv`.`Vol_Code` = :vcBillingAgent " .
            " WHERE `n`.`Member_Status` = 'a' AND `n`.`Record_Member` = 1 ORDER BY `n`.`Company`");
        $stmt->execute([':vcBillingAgent' => VolMemberType::BillingAgent]);
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
        if(count($this->selectedBillingAgents) > 0){
            $billingPh = [];
            foreach ($this->selectedBillingAgents as $i => $ba) {
                $ph = ':selBa' . $i;
                $billingPh[] = $ph;
                $this->queryParams[$ph] = $ba;
            }
            $whBilling = " AND `i`.`Sold_To_Id` IN (" . implode(', ', $billingPh) . ")";
        }
        $baIds = array();
        if(count($this->billingAgents) > 0){
            foreach($this->billingAgents as $ba){
                $baIds[] = $ba[0];
            }
        }
        $baPh = [];
        foreach ($baIds as $i => $bid) {
            $ph = ':allBa' . $i;
            $baPh[] = $ph;
            $this->queryParams[$ph] = $bid;
        }
        $baList = implode(', ', $baPh);
        $this->queryParams[':itemLodging'] = ItemId::Lodging;

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
    SUM(IFNULL(`i`.`Amount`, '')) AS `Invoice_Amount`,
    IF(TRIM(`ba`.`Name_Full`) != '', `ba`.`Name_Full`, `ba`.`Company`) AS `Billed To`,
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
    `invoice` `i` ON `v`.`idVisit` = `i`.`Order_Number` AND `v`.`Span` = `i`.`Suborder_Number` AND `i`.`Sold_To_Id` IN (" . $baList . ")
        LEFT JOIN
    `invoice_line` `il` ON `i`.`idInvoice` = `il`.`Invoice_Id` AND `il`.`Deleted` = 0 AND `il`.`Item_Id` = :itemLodging
        LEFT JOIN
    `gen_lookups` `invs` ON `invs`.`Table_Name` = 'Invoice_Status' AND `invs`.`Code` = `i`.`Status`
        JOIN
    `name` `ba` ON `i`.`Sold_To_Id` = `ba`.`idName` AND `ba`.`idName` IN (" . $baList . ")
WHERE `i`.`Deleted` = 0 AND " . $whDates . $whBilling . " GROUP BY `v`.`idVisit`, `v`.`Span`, `i`.`Sold_To_Id` ORDER BY `v`.`idVisit`";
    }

    public function getStats(){
        $stmt = $this->dbh->prepare($this->query);
        $stmt->execute($this->queryParams);
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
        $this->filterMkup .= $this->getBillingAgentMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    public function makeFields():array{
        $labels = Labels::getLabels();
        $uS = Session::getInstance();
        
        //$cFields[] = array("Invoice", 'Invoice_Number', 'checked', '', 'string', '15');
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
        $fields[] = array("Billed To", 'Billed To', 'checked', '', 'string', '20');
        //$fields[] = array("Nights Billed", "PaidNights", 'checked', '', 'string', '20');
        $fields[] = array("Amount", 'Invoice_Amount', 'checked', '', 'string', '15');
        //$fields[] = array("Invoice Status", 'Invoice_Status_Title', 'checked', '', 'string', '15');


        return $fields;
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