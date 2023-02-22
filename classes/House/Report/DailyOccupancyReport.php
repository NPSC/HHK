<?php
namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\SysConst\VisitStatus;
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
        $summaryTbl->addHeaderTr($summaryTbl->makeTh("Parameter"). $summaryTbl->makeTh("Value"));
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

        $query = "select
                    (select count(*) from resource where Type = 'room') as 'Total Rooms',
                    (select count(*) from resource r
                        left join resource_use ru on
	                       r.idResource = ru.idResource and
                           date(ru.Start_Date) <= date(now()) and
                           date(ru.End_Date) > date(now())
                        where r.Type = 'rmtroom' and ru.idResource_use is null) as 'Total Burst Rooms',
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
                    (select count(*) from reservation where date(Expected_Arrival) = date(now()) and Status = 'a') as 'Anticipated Arrivals',
                    (select count(*) from visit where date(Expected_Departure) = date(now()) and Status = 'a') as 'Anticipated Departures',
                    (ROUND((select count(distinct r.idResource) from resource r
                        left join visit v ON r.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
                        where v.idVisit is not null)/(select count(*) from resource r
                        left join resource_use ru on
	                       r.idResource = ru.idResource and
                           date(ru.Start_Date) <= date(now()) and
                           date(ru.End_Date) > date(now())
                        where ru.idResource_use is null and r.Type = 'room')*100,2)) as 'Available Room Occupancy',
                    (ROUND((select count(distinct r.idResource) from resource r
                        left join visit v ON r.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
                        where v.idVisit is not null)/(select count(*) from resource where Type = 'room')*100,2)) as 'Total Room Occupancy'

                ";
        $stmt = $this->dbh->prepare($query);
        $stmt->execute();

        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $helptexts = array();

        //add help text
        $helptexts["Room-nights available"] = "Number of nights in time frame * number of regular rooms";
        $helptexts["Room-nights occupied"] = "Number of nights each room was occupied, including Burst rooms";
        $helptexts["Occupancy Rate"] = "Room-nights occupied / Room-nights available";
        $helptexts["Burst Room-nights occupied"] = "Number of nights each Burst room was occupied";
        $helptexts["Unique PSGs"] = "Number of unique PGSs where anyone in the PSG stayed";
        $helptexts["New PSGs"] = "Number of unique PSGs whose first visit was in the time frame";
        $helptexts["Total Visits"] = "Number of visits with at least one ngiht in the time frame";
        $helptexts["Average Visit Length"] = "Average length of an entire visit with at least one night in the time frame";
        $helptexts["Median Visit Length"] = "Median length of an entire visit with at least one night in the time frame";
        $helptexts["Average First Visit Length"] = "Average length of a PSG's FIRST visit with at least one night in the time frame";
        $helptexts["Median First Visit Length"] = "Median length of a PSG's FIRST visit with at least one night in the time frame";

        return array($data[0], $helptexts);
    }


}

?>