<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Session;
use HHK\sec\Labels;

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
    public array $selectedBillingAgents;


    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Billing Agent Report';
        $this->description = "This report shows all patients who stayed in the time period and was invoiced to a specific billing agent";
        $this->inputSetReportName = "billingAgent";

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeQuery(): void{

        $arrivalCase = "CASE WHEN s.Span_Start_Date IS NOT NULL THEN s.Span_Start_Date
        WHEN r.Actual_Arrival IS NOT NULL THEN r.Actual_Arrival
        WHEN r.Expected_Arrival IS NOT NULL THEN r.Expected_Arrival ELSE NULL END";

        $departureCase = "CASE WHEN s.Span_End_Date IS NOT NULL THEN s.Span_End_Date
         WHEN s.Expected_Co_Date IS NOT NULL AND s.Expected_Co_Date > NOW() THEN s.Expected_Co_Date
         WHEN r.Actual_Departure IS NOT NULL THEN r.Actual_Departure
         WHEN s.Status = 'a' THEN ''
         WHEN r.Expected_Departure IS NOT NULL THEN r.Expected_Departure ELSE '' END";

        $whDepartureCase = "CASE WHEN s.Span_End_Date IS NOT NULL THEN s.Span_End_Date
         WHEN s.Expected_Co_Date IS NOT NULL AND s.Expected_Co_Date > NOW() THEN s.Expected_Co_Date
         WHEN r.Actual_Departure IS NOT NULL THEN r.Actual_Departure
         WHEN s.Status IS NOT NULL THEN NOW()
         WHEN r.Expected_Departure IS NOT NULL THEN r.Expected_Departure ELSE NOW() END";

        $whDates = $arrivalCase . " <= '" . $this->filter->getReportEnd() . "' and " . $whDepartureCase . " >= '" . $this->filter->getReportStart() . "' ";

        $whBilling = "";
        if(count($this->selectedBillingAgents) > 0){
            $billingAgents = implode(",", $this->selectedBillingAgents);
            $whBilling = " and i.Sold_To_Id in (" . $billingAgents . ")";
        }

        $this->query = "select
    CONCAT(v.idVisit, '-', v.Span) as idVisit,
    ifnull(p.Name_Last, '') as Name_Last,
    ifnull(p.Name_First, '') as Name_First,
    concat(ifnull(pa.Address_1, ''), '', ifnull(pa.Address_2, ''))  as pAddr,
    ifnull(pa.City, '') as pCity,
    ifnull(pa.County, '') as pCounty,
    ifnull(pa.State_Province, '') as pState,
    ifnull(pa.Country_Code, '') as pCountry,
    ifnull(pa.Postal_Code, '') as pZip,
    " . $arrivalCase . " as `Arrival`,
    " . $departureCase . " as `Departure`,
    DATEDIFF(ifnull(v.Actual_Departure, v.Expected_Departure), ifnull(v.Actual_Arrival, v.Expected_Arrival)) as `Nights`,
    (DATEDIFF(ifnull(v.Actual_Departure, v.Expected_Departure), ifnull(v.Actual_Arrival, v.Expected_Arrival))+1) as `Days`,
    ifnull(pgn.Name_First) as `pgFirst`,
    ifnull(pgn.Name_Last) as `pgLast`,
from
    visit v
        left join
    hospital_stay hs on v.idHospital_Stay = hs.idHospital_Stay
        left join
    name p ON hs.idPatient = p.idName
        left join
    name_address pa ON p.idName = pa.idName and p.Preferred_Mail_Address = pa.Purpose
        left join
    name pgn ON v.idPrimary_Guest = pgn.idName
        left join
    lookups g ON g.Category = 'ReservStatus'
        and g.`Code` = r.`Status`
        left join
    gen_lookups vs on vs.Table_Name = 'Visit_Status' and vs.Code = s.Status
where " . $whDates . $whBilling . " order by v.idVisit";
    }

    public function makeFilterMkup():void{
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    public function makeFilterOptsMkup():void{

    }

    public function makeCFields():array{
        $labels = Labels::getLabels();
        $uS = Session::getInstance();
        
        $cFields[] = array("Visit ID", 'idVisit', 'checked', '', 'string', '20');
        $cFields[] = array("Patient ID", 'Name_First', 'checked', '', 'string', '20');
        $cFields[] = array("Patient First", 'Name_First', 'checked', '', 'string', '20');
        $cFields[] = array("Patient Last", 'Name_Last', 'checked', '', 'string', '20');

        // Address.
        $pFields = array('pAddr', 'pCity');
        $pTitles = array('Address', 'City');

        if ($uS->county) {
            $pFields[] = 'pCounty';
            $pTitles[] = 'County';
        }

        $pFields = array_merge($pFields, array('pState', 'pCountry', 'pZip'));
        $pTitles = array_merge($pTitles, array('State', 'Country', 'Zip'));

        $cFields[] = array($pTitles, $pFields, '', '', 'string', '15', array());

        $cFields[] = array("Diagnosis", 'Diagnosis', '', '', 'string', '20');
        $cFields[] = array("Diagnosis Category", 'DiagnosisCategory', '', '', 'string', '20');
        $cFields[] = array("Arrive", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Depart", 'Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Primary Guest First", 'pgFirst', '', '', 'string', '20');
        $cFields[] = array("Parimary Guest Last", 'pgLast', '', '', 'string', '20');
        $cFields[] = array("Status", 'Status_Title', 'checked', '', 'string', '15');
        $cFields[] = array("Payment Status", 'Payment_Status_Title', 'checked', '', 'string', '15');


        return $cFields;
    }

    public function makeSummaryMkup():string {

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        return $mkup;

    }

    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();
        $uS = Session::getInstance();

        foreach($this->resultSet as $k=>$r) {
            $this->resultSet[$k]['Name_Last'] = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>$uS->resourceURL . 'house/GuestEdit.php?id=' . $r['idGuest'] . '&psg=' . $r['idPsg']));
        }

        return parent::generateMarkup($outputType);
    }
}
?>