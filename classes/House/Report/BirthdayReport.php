<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Session;
use HHK\sec\Labels;

/**
 * AllGuestReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * All Guest Report
 *
 * Lists all guests who stayed or are scheduled to stay in the time period
 *
 * @author Will
 */

class BirthdayReport extends AbstractReport implements ReportInterface {

    public array $locations;
    public array $diags;
    public array $resvStatuses;
    public array $selectedResvStatuses;


    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Birthday Report';
        $this->description = "This report shows all guests who are staying or scheduled to stay AND have a birthday during the selected time period";
        $this->inputSetReportName = "birthday";

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeQuery(): void{

        // Reservation status
        $resvStatus = ["'a'", "'s'", "'w'", "'uc'"];
        $stayStatus = ["'1'", "'a'", "'cp'", "'n'"];

        if(isset($this->request["cbIncCheckedOut"])){
            $resvStatus[] = "'co'";
            $stayStatus[] = "'co'";
        }

        $whResvStatus = '';
        $whStayStatus = implode(",", $stayStatus);
        if (count($resvStatus) > 0) {
            $whResvStatus = "and r.Status in (" . implode(",", $resvStatus) . ") ";
        }
        if (count($stayStatus) > 0){
            $whStayStatus = "and (s.Status in (" . implode(",", $stayStatus) . ") or s.Status IS NULL) ";
        }

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

        //birthday in timeframe
        $whDates .= "and 1 = (FLOOR(DATEDIFF('" . $this->filter->getReportEnd() . "', n.BirthDate) / 365.25)) - (FLOOR(DATEDIFF('" . $this->filter->getReportStart() . "', n.BirthDate) / 365.25)) ";

        $groupBy = " Group By r.idReservation, ifnull(s.idStays, n.idName)";

        $this->query = "select
    r.idReservation,
    CONCAT(v.idVisit, '-', v.Span) as idVisit,
    IFNULL(s.idName, rg.idGuest) as idGuest,
    concat(ifnull(na.Address_1, ''), '', ifnull(na.Address_2, ''))  as gAddr,
    ifnull(na.City, '') as gCity,
    ifnull(na.County, '') as gCounty,
    ifnull(na.State_Province, '') as gState,
    ifnull(na.Country_Code, '') as gCountry,
    ifnull(na.Postal_Code, '') as gZip,
    CASE WHEN n.Preferred_Phone = 'no' THEN 'No Phone' ELSE ifnull(np.Phone_Num, '') END as Phone_Num,
    CASE WHEN n.Preferred_Email = 'no' THEN 'No Email' ELSE ifnull(ne.Email, '') END as Email,
    rm.Phone,
    " . $arrivalCase . " as `Arrival`,
    " . $departureCase . " as `Departure`,
    r.Fixed_Room_Rate,
    r.`Status` as `ResvStatus`,
    DATEDIFF(ifnull(r.Actual_Departure, r.Expected_Departure), ifnull(r.Actual_Arrival, r.Expected_Arrival)) as `Nights`,
    (DATEDIFF(ifnull(r.Actual_Departure, r.Expected_Departure), ifnull(r.Actual_Arrival, r.Expected_Arrival))+1) as `Days`,
    ifnull(n.Name_Last, '') as Name_Last,
    ifnull(n.Name_First, '') as Name_First,
    ifnull(n.Name_Middle, '') as Name_Middle,
    ifnull(n.BirthDate, '') as BirthDate,
    re.Title as `Room`,
    re.`Type`,
    re.`Status` as `RescStatus`,
    re.`Category`,
    IF(rr.FA_Category='f', r.Fixed_Room_Rate, rr.`Title`) as `FA_Category`,
    rr.`Title` as `Rate`,
    if(s.On_Leave > 0, 'On Leave', ifnull(vs.Description, g.Title)) as 'Status_Title',
    hs.idPsg,
    hs.idHospital
from
    reservation r
        left join
	reservation_guest rg on r.idReservation = rg.idReservation
		left join
	visit v on v.idReservation = r.idReservation
		left join
	stays s on s.idVisit = v.idVisit and s.Visit_Span = v.Span
		left join
    resource re ON re.idResource = r.idResource
        left join
    name n ON ifnull(s.idName, r.idGuest) = n.idName
        left join
    name_address na ON n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
        left join
    name_phone np ON n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
        left join
    name_email ne ON n.idName = ne.idName and n.Preferred_Email = ne.Purpose
        left join
    hospital_stay hs ON r.idHospital_Stay = hs.idHospital_stay
        left join
    room_rate rr ON r.idRoom_rate = rr.idRoom_rate
        left join resource_room rer on r.idResource = rer.idResource
        left join room rm on rer.idRoom = rm.idRoom
        left join
    lookups g ON g.Category = 'ReservStatus'
        and g.`Code` = r.`Status`
        left join
    gen_lookups vs on vs.Table_Name = 'Visit_Status' and vs.Code = s.Status
where " . $whDates . $whResvStatus . $whStayStatus . $groupBy . " order by r.idReservation";
    }

    public function makeFilterMkup():void{
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    public function makeFilterOptsMkup():void{
        $birthdayAttrs = array("type"=>"checkbox", "name"=>"cbIncCheckedOut");
        if(isset($this->request["cbIncCheckedOut"])){
            $birthdayAttrs['checked'] = 'checked';
        }

        $this->filterOptsMkup .= HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("", $birthdayAttrs) .
            HTMLContainer::generateMarkup("label", "Include Checked Out " . Labels::getString('MemberType', 'visitor', "Guest") . 's', array("for"=>"cbIncCheckedOut"))
        );

    }

    public function makeCFields():array{
        $labels = Labels::getLabels();
        $uS = Session::getInstance();

        $cFields[] = array("First", 'Name_First', 'checked', '', 'string', '20');
        $cFields[] = array("Middle", 'Name_Middle', '', '', 'string', '20');
        $cFields[] = array("Last", 'Name_Last', 'checked', '', 'string', '20');
        $cFields[] = array("Birth Date", 'BirthDate', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');

        // Address.
        $pFields = array('gAddr', 'gCity');
        $pTitles = array('Address', 'City');

        if ($uS->county) {
            $pFields[] = 'gCounty';
            $pTitles[] = 'County';
        }

        $pFields = array_merge($pFields, array('gState', 'gCountry', 'gZip'));
        $pTitles = array_merge($pTitles, array('State', 'Country', 'Zip'));

        $cFields[] = array($pTitles, $pFields, '', '', 'string', '15', array());

        $cFields[] = array("Room Phone", 'Phone', '', '', 'string', '20');
        $cFields[] = array("Phone", 'Phone_Num', '', '', 'string', '20');
        $cFields[] = array("Email", 'Email', '', '', 'string', '20');
        $cFields[] = array("Room", 'Room', 'checked', '', 'string', '15');
        $cFields[] = array("Arrive", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Depart", 'Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Status", 'Status_Title', 'checked', '', 'string', '15');

        return $cFields;
    }

    public function makeSummaryMkup():string {

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        if(isset($this->request["cbIncCheckedOut"])){
            $mkup .= HTMLContainer::generateMarkup('p', "Includes Checked Out " . Labels::getString('MemberType', 'visitor', 'Guest') . "s");
        }

        return $mkup;

    }

    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();
        $uS = Session::getInstance();

        foreach($this->resultSet as $k=>$r) {
            //$this->resultSet[$k]['Status_Title'] = HTMLContainer::generateMarkup('a', $r['Status_Title'], array('href'=>$uS->resourceURL . 'house/Reserve.php?rid=' . $r['idReservation']));
            $this->resultSet[$k]['Name_Last'] = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>$uS->resourceURL . 'house/GuestEdit.php?id=' . $r['idGuest'] . '&psg=' . $r['idPsg']));
        }

        return parent::generateMarkup($outputType);
    }
}