<?php
namespace HHK\House\Report;

use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLContainer;

class QuarterlyOccupancyReport extends AbstractReport implements ReportInterface {

    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Occupancy Report';
        $this->inputSetReportName = "occupancy";

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeFilterMkup(): void {
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
    }

    public function makeSummaryMkup(): string {
        $summaryData = $this->getMainSummaryData();
        $ageDist = $this->getAgeDistribution();

        $summaryTbl = new HTMLTable();
        $summaryTbl->addHeaderTr($summaryTbl->makeTh("Parameter"). $summaryTbl->makeTh("Value"));
        $summaryTbl->addBodyTr($summaryTbl->makeTd("Report Dates", array("class"=>"tdlabel")) . $summaryTbl->makeTd((new \DateTime($this->filter->getReportStart()))->format("M j, Y") . " - " . (new \DateTime($this->filter->getReportEnd()))->format("M j, Y")));

        foreach($summaryData[0] as $key=>$val){
            $summaryTbl->addBodyTr($summaryTbl->makeTd($key, array("class"=>"tdlabel")) . $summaryTbl->makeTd($val));
        }

        $ageDistTbl = new HTMLTable();
        $ageDistTbl->addHeaderTr($ageDistTbl->makeTh("Unique Guests", array("colspan"=>"2")));
        foreach($ageDist as $row){
            if($row[0] == "Total Guests"){
                $ageDistTbl->addBodyTr(
                    $ageDistTbl->makeTd("<strong>" . $row[0] . "</strong>", array("class"=>"tdlabel")) . $ageDistTbl->makeTd("<strong>" . $row[1] . "</strong>")
                    );
            }else{
                $ageDistTbl->addBodyTr(
                    $ageDistTbl->makeTd($row[0], array("class"=>"tdlabel")) . $ageDistTbl->makeTd($row[1])
                );
            }
        }

        return HTMLContainer::generateMarkup("div", $summaryTbl->generateMarkup(array("class"=>"mr-3")) . $ageDistTbl->generateMarkup() . '<div id="guestsPerNight"></div><div id="diagnosisCategoryTotals"></div>', array("class"=>"hhk-flex hhk-visitdialog"));

    }

    public function makeCFields(): array {

        return array();

    }

    public function makeQuery(): void
    {



    }

    public function getAgeDistribution(){

        $query = 'select if(n.BirthDate is not null, if(timestampdiff(YEAR, n.BirthDate, CURDATE()) < 18, "Child", "Adult"), "Unknown") as `Key`, count(distinct n.idName) as "count" from stays s join name n on s.idName = n.idName where date(s.Span_Start_Date) < date(:endDate) and date(ifnull(s.Span_End_Date, now()+interval 1 day)) > date(:startDate) group by `Key`';
        $stmt = $this->dbh->prepare($query);
        $stmt->execute([":startDate"=>$this->filter->getReportStart(), ":endDate"=>$this->filter->getQueryEnd()]);
        $data = $stmt->fetchAll(\PDO::FETCH_NUM);
        $total = 0;
        foreach($data as $key=>$value){
            $value[1] = (int) $value[1];
            $data[$key] = $value;
            $total += $value[1];
        }
        $data[] = ["Total Guests", $total];
        return $data;

    }

    public function getMainSummaryData(){

        $query = '
SELECT
(select count(*) from resource re where re.Type = "room")*datediff("' . $this->filter->getQueryEnd() . '", "' . $this->filter->getReportStart() . '") as "Room-nights available",
(select SUM(DATEDIFF(least(ifnull(v.Span_End, date("' . $this->filter->getReportEnd() . '")), date("' . $this->filter->getReportEnd() . '")), greatest(v.Span_Start, date("' . $this->filter->getReportStart() . '")))) from visit v where date(v.Span_Start) < date("' . $this->filter->getQueryEnd() . '") and date(ifnull(v.Span_End, curdate())) > date("' . $this->filter->getReportStart() . '")) as "Room-nights occupied",
ROUND((select SUM(DATEDIFF(least(ifnull(v.Span_End, date("' . $this->filter->getReportEnd() . '")), date("' . $this->filter->getReportEnd() . '")), greatest(v.Span_Start, date("' . $this->filter->getReportStart() . '")))) from visit v where date(v.Span_Start) < date("' . $this->filter->getQueryEnd() . '") and date(ifnull(v.Span_End, curdate())) > date("' . $this->filter->getReportStart() . '"))/((select count(*) from resource re where re.Type = "room")*datediff("' . $this->filter->getQueryEnd() . '", "' . $this->filter->getReportStart() . '"))*100,1) as "Occupancy Rate",
(select SUM(DATEDIFF(least(ifnull(v.Span_End, date("' . $this->filter->getReportEnd() . '")), date("' . $this->filter->getReportEnd() . '")), greatest(v.Span_Start, date("' . $this->filter->getReportStart() . '")))) from visit v join resource re on v.idResource = re.idResource where re.Type = "rmtroom" and date(v.Span_Start) < date("' . $this->filter->getQueryEnd() . '") and date(ifnull(v.Span_End, curdate())) > date("' . $this->filter->getReportStart() . '")) as "Burst Room-nights occupied",
(select count(distinct ng.idPsg) from stays s join name_guest ng on s.idName = ng.idName where date(s.Span_Start_Date) < date(:endDate6) and date(ifnull(s.Span_End_Date, curdate())) > date(:startDate6)) as "Unique PSGs",
(select count(distinct ng.idPsg) from stays s join name_guest ng on s.idName = ng.idName where date(s.Span_Start_Date) < date(:endDate13) and date(ifnull(s.Span_End_Date, curdate())) > date(:startDate13)) as "Unique Patients",
(select count(distinct reg.idPsg) from visit v join registration reg on v.idRegistration = reg.idRegistration where idVisit in (select fv.idVisit from vlist_first_visit fv where date(ifnull(fv.Actual_Departure, curdate())) > date(:startDate14) and date(ifnull(fv.Arrival_Date, curdate())) < date(:endDate14))) as "New Families",
(select count(distinct v.idVisit) from visit v where date(v.Arrival_Date) < date(:endDate8) and date(ifnull(v.Actual_Departure, curdate())) > date(:startDate8)) as "Total Visits",
(select ROUND(AVG(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date)),1) from visit v where date(v.Arrival_Date) < date(:endDate9) and date(ifnull(v.Actual_Departure, curdate())) > date(:startDate9)) as "Average Visit Length",
(select ROUND(MEDIAN(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date)) over (),1) from visit v where date(v.Arrival_Date) < date(:endDate10) and date(ifnull(v.Actual_Departure, curdate())) > date(:startDate10) limit 1) as "Median Visit Length",
(select round(AVG(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date))) from visit v where idVisit in (select fv.idVisit from vlist_first_visit fv where date(ifnull(fv.Actual_Departure, curdate())) > date(:startDate11) and date(fv.Arrival_Date) < date(:endDate11))) as "Average First Visit Length",
(select round(MEDIAN(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date)) over (),1) from visit v where idVisit in (select fv.idVisit from vlist_first_visit fv where date(ifnull(fv.Actual_Departure, curdate())) > date(:startDate12) and date(fv.Arrival_Date) < date(:endDate12)) limit 1) as "Median First Visit Length";
';
        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ":startDate6"=>$this->filter->getReportStart(), ":endDate6"=>$this->filter->getQueryEnd(),
            ":startDate8"=>$this->filter->getReportStart(), ":endDate8"=>$this->filter->getQueryEnd(),
            ":startDate9"=>$this->filter->getReportStart(), ":endDate9"=>$this->filter->getQueryEnd(),
            ":startDate10"=>$this->filter->getReportStart(), ":endDate10"=>$this->filter->getQueryEnd(),
            ":startDate11"=>$this->filter->getReportStart(), ":endDate11"=>$this->filter->getQueryEnd(),
            ":startDate12"=>$this->filter->getReportStart(), ":endDate12"=>$this->filter->getQueryEnd(),
            ":startDate13"=>$this->filter->getReportStart(), ":endDate13"=>$this->filter->getQueryEnd(),
            ":startDate14"=>$this->filter->getReportStart(), ":endDate14"=>$this->filter->getQueryEnd(),
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getGuestAvgPerNight(){
        $query = 'select
	if(timestampdiff(YEAR, n.BirthDate, CURDATE()) < 18, "Child", "Adult") as "child/adult",
    round(sum(DATEDIFF(IF(date(s.Span_End_Date) > date("' . $this->filter->getQueryEnd() . '"), date("' . $this->filter->getQueryEnd() . '"), date(s.Span_End_Date)), IF(date(s.Span_Start_Date) < date("' . $this->filter->getReportStart() . '"), date("' . $this->filter->getReportStart() . '"), date(s.Span_Start_Date))))/datediff(date("' . $this->filter->getQueryEnd() . '"), date("' . $this->filter->getReportStart() . '")),1) as "avg guests per night"
from stays s
join name n on s.idName = n.idName
where date(ifnull(s.Span_End_Date, now()+interval 1 day)) > date("' . $this->filter->getReportStart() . '") and date(s.Span_Start_Date) < date("' . $this->filter->getQueryEnd() . '")
group by `child/adult`;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_NUM);

        foreach($data as $key=>$value){
            $value[1] = (float) $value[1];
            $data[$key] = $value;
        }

        array_unshift($data, ["Child/Adult", "Value"]);
        return $data;
    }

    public function getDiagnosisCategoryTotals(){
        $query = 'select ifnull(dc.Description, "Other") as "Category", count(distinct s.idName) as "count"
from stays s
join visit v on s.idVisit = v.idVisit
join hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
join gen_lookups d on hs.Diagnosis = d.Code and d.Table_Name = "Diagnosis"
left join gen_lookups dc on d.Substitute = dc.Code and dc.Table_Name = "Diagnosis_Category"
where date(ifnull(s.Span_End_Date, now()+interval 1 day)) > date("' . $this->filter->getReportStart() . '") and date(s.Span_Start_Date) < date("' . $this->filter->getQueryEnd() . '")
group by `Category`;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_NUM);

        foreach($data as $key=>$value){
            $value[1] = (float) $value[1];
            $data[$key] = $value;
        }

        array_unshift($data, ["Category", "Value"]);
        return $data;
    }

}

?>