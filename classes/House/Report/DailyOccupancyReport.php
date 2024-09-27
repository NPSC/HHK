<?php
namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\SysConst\{VisitStatus,ReservationStatus};
use HHK\sec\Session;
use HHK\HTMLControls\HTMLTable;

class DailyOccupancyReport extends AbstractReport implements ReportInterface {

    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Daily Occupancy Report';
        $this->inputSetReportName = "dailyoccupancy";

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeFilterMkup(): void
    {}

    public function makeSummaryMkup(): string {
        $summaryData = $this->getMainSummaryData();

        $summaryTbl = new HTMLTable();
        $summaryTbl->addBodyTr($summaryTbl->makeTd("Prepared at", array("class"=>"tdlabel")) . $summaryTbl->makeTd((new \DateTime())->format("M j, Y h:i a")));

        foreach($summaryData[0] as $key=>$val){
            $summaryTbl->addBodyTr($summaryTbl->makeTd($key . (isset($summaryData[1][$key]) ? '<span class="hhk-tooltip ui-icon ui-icon-help" title="' . $summaryData[1][$key] . '"></span>' : ''), array("class"=>"tdlabel")) . $summaryTbl->makeTd($val));
        }

        return HTMLContainer::generateMarkup("div",$summaryTbl->generateMarkup(array("class"=>"mr-3 mb-3","style"=>"min-width: fit-content")), array("class"=>"hhk-flex hhk-flex-wrap hhk-visitdialog"));

    }

    public function makeCFields(): array
    {
        return array();
    }

    public function makeQuery(): void
    {}

    public function getMainSummaryData(){

        $roomTypes = readGenLookupsPDO($this->dbh, "Resource_Type");
        $rmtroomTitle = (isset($roomTypes['rmtroom']['Description']) ? $roomTypes['rmtroom']['Description']: "Remote Room");

        $resvStatuses = readLookups($this->dbh, "reservStatus", "Code");
        $resvStatusList = (isset($resvStatuses[ReservationStatus::Committed]['Title']) ? $resvStatuses[ReservationStatus::Committed]['Title'] . ", " : "") . 
                (isset($resvStatuses[ReservationStatus::UnCommitted]['Title']) ? $resvStatuses[ReservationStatus::UnCommitted]['Title'] . ", " : "") . 
                (isset($resvStatuses[ReservationStatus::Waitlist]['Title']) ? "and " . $resvStatuses[ReservationStatus::Waitlist]['Title'] : "");
        
        $query = "select
                    (select count(*) from resource where Type = 'room') as 'Total Rooms',
                    (select count(*) from resource r
                        left join resource_use ru on
	                       r.idResource = ru.idResource and
                           date(ru.Start_Date) <= date(now()) and
                           date(ru.End_Date) > date(now())
                        where r.Type = 'rmtroom' and ru.idResource_use is null) as 'Total " . $rmtroomTitle . "s',
                    (select count(*) from resource r
                        left join resource_use ru on
	                       r.idResource = ru.idResource and
                           date(ru.Start_Date) <= date(now()) and
                           date(ru.End_Date) > date(now())
                        where ru.idResource_use is not null) as 'Out of Order/unavailable Rooms',
                    (select count(distinct r.idResource) from resource r
                        left join visit v ON r.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
                        where v.idVisit is null) as 'Vacant Rooms',
                    (select count(distinct r.idResource) from resource r
                        left join visit v ON r.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
                        where v.idVisit is not null) as 'Occupied Rooms',
                    (select count(*) from reservation where date(Expected_Arrival) = date(now()) and Status in ('" . ReservationStatus::Committed . "', '" . ReservationStatus::UnCommitted . "', '" . ReservationStatus::Waitlist . "')) as 'Anticipated Arrivals',
                   (select count(*) from visit where date(Expected_Departure) = date(now()) and Status = 'a') as 'Anticipated Departures',
                    (concat(ROUND((select count(distinct r.idResource) from resource r
                        left join visit v ON r.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
                        where v.idVisit is not null)/(select count(*) from resource r
                        left join resource_use ru on
	                       r.idResource = ru.idResource and
                           date(ru.Start_Date) <= date(now()) and
                           date(ru.End_Date) > date(now())
                        where ru.idResource_use is null and r.Type = 'room')*100,2), '%')) as 'Available Room Occupancy',
                    (concat(ROUND((select count(distinct r.idResource) from resource r
                        left join visit v ON r.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
                        where v.idVisit is not null)/(select count(*) from resource where Type = 'room')*100,2), '%')) as 'Total Room Occupancy'

                ";
        $stmt = $this->dbh->prepare($query);
        $stmt->execute();

        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $helptexts = array();

        //add help text
        $helptexts["Total Rooms"] = "Total regular rooms";
        $helptexts["Total " . $rmtroomTitle . "s"] = "Total " . $rmtroomTitle . "s available";
        $helptexts["Vacant Rooms"] = "Number of vacant available rooms";
        $helptexts["Occupied Rooms"] = "Number of rooms with active visits";
        $helptexts["Anticipated Arrivals"] = "Number of " . $resvStatusList . " reservations with an arrival date of today";
        $helptexts["Anticipated Departures"] = "Number of checked in visits with an expected departure of today";
        $helptexts["Available Room Occupancy"] = "Total Occupied Rooms / (Total Regular Rooms - Out of service regular rooms)*100";
        $helptexts["Total Room Occupancy"] = "Total Occupied Rooms / Total Regular Rooms*100";

        return array($data[0], $helptexts);
    }


}

?>