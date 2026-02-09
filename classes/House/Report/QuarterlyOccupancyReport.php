<?php
namespace HHK\House\Report;

use HHK\Common;
use HHK\ExcelHelper;
use HHK\sec\Session;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Labels;

class QuarterlyOccupancyReport extends AbstractReport implements ReportInterface {

    const NOT_INDICATED = "Not Indicated";
    const NO_CAT = "No Category";
    const NO_DIAGNOSIS = "No Diagnosis";
    
    protected $roomTypes;
    protected $rmtroomTitle;
    protected $diagCats;

    protected $dispType;
    
    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Occupancy Report';
        $this->inputSetReportName = "occupancy";

        $this->roomTypes = Common::readGenLookupsPDO($dbh, "Resource_Type");
        $this->rmtroomTitle = (isset($this->roomTypes['rmtroom']['Description']) ? $this->roomTypes['rmtroom']['Description']: "Remote Room");
        $this->diagCats = Common::readGenLookupsPDO($dbh, "Diagnosis_Category");

        $this->dispType = (filter_has_var(INPUT_POST, "btnExcel-" . $this->inputSetReportName) ? "excel":"here");

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeFilterMkup(): void {
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
    }

    public function makeSummaryMkup(): string {

        if($this->filter->getReportStart() == $this->filter->getQueryEnd()){
            return HTMLContainer::generateMarkup("div", "<strong>Error:</strong> report start and end dates cannot be the same", ["class"=>"ui-state-highlight ui-corner-all p-2"]);
        }

        $summaryData = $this->getMainSummaryData($this->filter->getReportStart(), $this->filter->getQueryEnd());
        $ageDist = $this->getAgeDistribution($this->filter->getReportStart(), $this->filter->getQueryEnd());

        $summaryTbl = new HTMLTable();
        $summaryTbl->addBodyTr($summaryTbl->makeTd("Prepared at", array("class"=>"tdlabel")) . $summaryTbl->makeTd((new \DateTime())->format("M j, Y h:i a")));
        $summaryTbl->addBodyTr($summaryTbl->makeTd("Report Dates", array("class"=>"tdlabel")) . $summaryTbl->makeTd((new \DateTime($this->filter->getReportStart()))->format("M j, Y") . " - " . (new \DateTime($this->filter->getReportEnd()))->format("M j, Y")));

        foreach($summaryData[0] as $key=>$val){
            $summaryTbl->addBodyTr($summaryTbl->makeTd($key . (isset($summaryData[1][$key]) ? '<span class="hhk-tooltip ui-icon ui-icon-help" title="' . $summaryData[1][$key] . '"></span>' : ''), array("class"=>"tdlabel")) . $summaryTbl->makeTd($val));
        }

        $ageDistTbl = new HTMLTable();
        $ageDistTbl->addHeaderTr($ageDistTbl->makeTh("Unique Guests <span class='ui-icon ui-icon-help hhk-tooltip' title='Unique guests split by guest age at check-in time. Guest age is determined by birth date'></span>", array("colspan"=>"2")));
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

        return HTMLContainer::generateMarkup("div", '<div class="hhk-flex hhk-flex-wrap hhk-print-row">' . $summaryTbl->generateMarkup(array("class"=>"mr-3 mb-3","style"=>"min-width: fit-content")) . $ageDistTbl->generateMarkup(array("class"=>"mb-3", "style"=>"min-width: fit-content")) . '</div><div class="hhk-flex hhk-flex-wrap hhk-print-row">' . '<div class="hhk-pieChart"><p style="text-align:center;"><strong>Average Number of Guests per Night</strong></p><div id="guestsPerNight" class=""></div></div><div class="hhk-pieChart"><p style="text-align:center; font-size: 1.1em"><strong>Visit-Nights by Diagnosis<span class="hhk-tooltip ui-icon ui-icon-help" title="Counts number of nights in a visit by diagnosis, whether or not the patient stayed"></span></strong></p><div id="diagnosisCategoryTotals"></div></div></div>', array("class"=>"hhk-flex hhk-flex-wrap hhk-visitdialog"));

    }

    public function makeCFields(): array {
        $cFields = [];

        if($this->dispType == "excel"){
            $cFields[] = array("Date", "Date", 'checked', '', 'date', '20');
            $cFields[] = array("Room-nights available", "Room-nights available", 'checked', '', 'string', '20');
            $cFields[] = array("Room-nights occupied", "Room-nights occupied", 'checked', '', 'string', '20');
            $cFields[] = array("Occupancy Rate", "Occupancy Rate", 'checked', '', 'string', '20');
            $cFields[] = array($this->rmtroomTitle . "-nights occupied", $this->rmtroomTitle . "-nights occupied", 'checked', '', 'string', '20');
            $cFields[] = array("Unique " . Labels::getString("Statement", "psgPlural", "PSGs"), "Unique " . Labels::getString("Statement", "psgPlural", "PSGs"), 'checked', '', 'string', '20');
            $cFields[] = array("New " . Labels::getString("Statement", "psgPlural", "PSGs"), "New " . Labels::getString("Statement", "psgPlural", "PSGs"), 'checked', '', 'string', '20');
            $cFields[] = array("Total Visits", "Total Visits", 'checked', '', 'string', '20');
            $cFields[] = array("Average Visit Length", "Average Visit Length", 'checked', '', 'string', '20');
            $cFields[] = array("Median Visit Length", "Median Visit Length", 'checked', '', 'string', '20');
            $cFields[] = array("Average First Visit Length", "Average First Visit Length", 'checked', '', 'string', '20');
            $cFields[] = array("Median First Visit Length", "Median First Visit Length", 'checked', '', 'string', '20');

            //age distribution
            $cFields[] = array("Adult", "Adult", 'checked', '', 'string', '20');
            $cFields[] = array("Child", "Child", 'checked', '', 'string', '20');
            $cFields[] = array(self::NOT_INDICATED, self::NOT_INDICATED, 'checked', '', 'string', '20');
            $cFields[] = array("Total Guests", "Total Guests", 'checked', '', 'string', '20');

            //diagCategories
            foreach($this->diagCats as $cat){
                $cFields[] = array($cat[1], $cat[1], 'checked', '', 'string', '20');
            }
            $cFields[] = array(self::NO_CAT, self::NO_CAT, 'checked', '', 'string', '20');
            $cFields[] = array(self::NO_DIAGNOSIS, self::NO_DIAGNOSIS, 'checked', '', 'string', '20');
        }
        return $cFields;

    }

    public function makeQuery(): void
    {



    }

    public function downloadExcel(string $fileName = 'HHKReport', string $action = "download", string $to = ""): void{
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

        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);

        //daily data
        $curDate = new \DateTime($this->filter->getReportStart());
        $end = new \DateTime($this->filter->getQueryEnd());
        $yesterday = (new \DateTime())->sub(new \DateInterval("P1D"));

        //loop each day until end
        for ($curDate; $curDate < $end && $curDate < $yesterday; $curDate->add(new \DateInterval("P1D"))){
            $curEnd = (new \DateTimeImmutable($curDate->format("Y-m-d")))->add(new \DateInterval("P1D"));
            $summaryData = $this->getMainSummaryData($curDate->format("Y-m-d"), $curEnd->format("Y-m-d"));
            $ageDistribution = $this->getAgeDistribution($curDate->format("Y-m-d"), $curEnd->format("Y-m-d"));
            $diagCategoryTotals = $this->getDiagnosisCategoryTotals($curDate->format("Y-m-d"), $curEnd->format("Y-m-d"), true);

            //write row
            $flds = array();
            $flds[] = $curDate->format("Y-m-d");
    
            //loop summaryData
            foreach ($summaryData[0] as $s) {
                $flds[] = $s;
            }

            //age distribution
            $ageFields = array("Adult", "Child", self::NOT_INDICATED, "Total Guests");
            foreach ($ageFields as $title) {
                $found = false;
                foreach ($ageDistribution as $dist) {
                    if (isset($dist[0]) && $dist[0] === $title) {
                        $flds[] = $dist[1];
                        $found = true;
                    }
                }
                if(!$found){
                    $flds[] = 0;
                }
            }

            //diagnosis categories
            $diagCats = $this->diagCats;
            $diagCats[] = ["noCat", self::NO_CAT];
            $diagCats[] = ["noDiag", self::NO_DIAGNOSIS];

            foreach ($diagCats as $cat){
                $found = false;
                foreach($diagCategoryTotals as $val){
                    if($val[0] === $cat[1]){
                        $flds[] = (isset($val[1]) ? $val[1] : 0);
                        $found = true;
                    }
                }
                if(!$found){
                    $flds[] = 0;
                }
                
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }

        //HouseLog::logDownload($this->dbh, $this->reportTitle, "Excel", $this->reportTitle . " for " . $this->filter->getReportStart() . " - " . $this->filter->getReportEnd() . " downloaded", $uS->username);

        /*
        switch($action){
            case ExcelHelper::ACTION_SAVE_DOC:
                $writer->saveDoc($this->dbh, $uS->username, $this->getInputSetReportName());
                break;
            case ExcelHelper::ACTION_EMAIL:
                $writer->emailDoc($this->dbh, $to);
                break;
            case ExcelHelper::ACTION_DOWNLOAD:
                $writer->download();
                break;
            default:
                $writer->download();
                break;
        }
                */
        $writer->download();
    }

    public function getAgeDistribution(string $start, string $end){

        $query = 'select if(n.BirthDate is not null, if(timestampdiff(YEAR, n.BirthDate, s.Span_Start_Date) < 18, "Child", "Adult"), if(nd.Is_Minor, "Child", "' . self::NOT_INDICATED . '")) as `Key`, count(distinct n.idName) as "count" from stays s join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span join name n on s.idName = n.idName join name_demog nd on n.idName = nd.idName where s.Span_Start_Date < :endDate and ifnull(s.Span_End_Date, now()) >= DATE_ADD(:startDate, INTERVAL 1 DAY) and ((s.Span_End_Date is null) <> (s.Span_Start_Date is null) or (s.Span_End_Date is not null and s.Span_Start_Date is not null and DATEDIFF(s.Span_End_Date, s.Span_Start_Date) <> 0)) and ((v.Span_End is null) <> (v.Span_Start is null) or (v.Span_End is not null and v.Span_Start is not null and DATEDIFF(v.Span_End, v.Span_Start) <> 0)) group by `key`';
        $stmt = $this->dbh->prepare($query);
        $stmt->execute([":startDate"=>$start, ":endDate"=>$end]);
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

    public function getMainSummaryData(string $start, string $end){

        $roomTypes = Common::readGenLookupsPDO($this->dbh, "Resource_Type");
        $rmtroomTitle = (isset($roomTypes['rmtroom']['Description']) ? $roomTypes['rmtroom']['Description']: "Remote Room");

        $retiredRescSql = "(re.Retired_At is null or re.Retired_At >= DATE_ADD('$start', INTERVAL 1 DAY))";

        $query = '
SELECT
(select count(*) from resource re where re.Type = "room" and ' .$retiredRescSql .')*datediff("' . $end . '", "' . $start . '") as "Room-nights available",
(select SUM(DATEDIFF(least(ifnull(v.Span_End, date("' . $end . '")), date("' . $end . '")), greatest(v.Span_Start, date("' . $start . '")))) from visit v where v.Span_Start < "' . $end . '" and ifnull(v.Span_End, curdate()) >= DATE_ADD("' . $start . '", INTERVAL 1 DAY) and ((v.Span_End is null) <> (v.Span_Start is null) or (v.Span_End is not null and v.Span_Start is not null and DATEDIFF(v.Span_End, v.Span_Start) <> 0))) as "Room-nights occupied",
CONCAT(ROUND((select SUM(DATEDIFF(least(ifnull(v.Span_End, date("' . $end . '")), date("' . $end . '")), greatest(v.Span_Start, date("' . $start . '")))) from visit v where v.Span_Start < "' . $end . '" and ifnull(v.Span_End, curdate()) >= DATE_ADD("' . $start . '", INTERVAL 1 DAY))/((select count(*) from resource re where re.Type = "room" and ' . $retiredRescSql . ')*datediff("' . $end . '", "' . $start . '"))*100,1), "%") as "Occupancy Rate",
ifnull((select SUM(DATEDIFF(least(ifnull(v.Span_End, date("' . $end . '")), date("' . $end . '")), greatest(v.Span_Start, date("' . $start . '")))) from visit v join resource re on v.idResource = re.idResource where re.Type = "rmtroom" and v.Span_Start < "' . $end . '" and ifnull(v.Span_End, curdate()) >= DATE_ADD("' . $start . '", INTERVAL 1 DAY)), "0") as "' . $rmtroomTitle . '-nights occupied",
(select count(distinct reg.idPsg) from visit v join registration reg on v.idRegistration = reg.idRegistration where v.Span_Start < :endDate6 and ifnull(v.Span_End, date_add(curdate(), interval 1 day)) >= DATE_ADD(:startDate6, INTERVAL 1 DAY) and ((v.Span_End is null) <> (v.Span_Start is null) or (v.Span_End is not null and v.Span_Start is not null and DATEDIFF(v.Span_End, v.Span_Start) <> 0))) as "Unique ' . Labels::getString("Statement", "psgPlural", "PSGs") . '",
(select count(distinct reg.idPsg) from visit v join registration reg on v.idRegistration = reg.idRegistration where idVisit in (select fv.idVisit from vlist_first_visit fv where ifnull(fv.Span_End, date_add(curdate(), interval 1 day)) >= DATE_ADD(:startDate14, INTERVAL 1 DAY) and fv.Span_Start < :endDate14 and ((fv.Span_End is null) <> (fv.Span_Start is null) or (fv.Span_End is not null and fv.Span_Start is not null and DATEDIFF(fv.Span_End, fv.Span_Start) <> 0)))) as "New ' . Labels::getString("Statement", "psgPlural", "PSGs") . '",
(select count(distinct v.idVisit) from visit v where v.Span_Start < :endDate8 and ifnull(v.Span_End, date_add(curdate(), interval 1 day)) >= DATE_ADD(:startDate8, INTERVAL 1 DAY) and ((v.Span_End is null) <> (v.Span_Start is null) or (v.Span_End is not null and v.Span_Start is not null and DATEDIFF(v.Span_End, v.Span_Start) <> 0))) as "Total Visits",
(select ROUND(AVG(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date)),1) from visit v where v.Arrival_Date < :endDate9 and ifnull(v.Actual_Departure, date_add(curdate(), interval 1 day)) >= DATE_ADD(:startDate9, INTERVAL 1 DAY) and ((v.Actual_Departure is null) <> (v.Arrival_Date is null) or (v.Actual_Departure is not null and v.Arrival_Date is not null and DATEDIFF(v.Actual_Departure, v.Arrival_Date) <> 0)) and v.Status in ("a","co")) as "Average Visit Length",
(select ROUND(MEDIAN(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date)) over (),1) from visit v where v.Arrival_Date < :endDate10 and ifnull(v.Actual_Departure, date_add(curdate(), interval 1 day)) >= DATE_ADD(:startDate10, INTERVAL 1 DAY) and ((v.Actual_Departure is null) <> (v.Arrival_Date is null) or (v.Actual_Departure is not null and v.Arrival_Date is not null and DATEDIFF(v.Actual_Departure, v.Arrival_Date) <> 0)) and v.Status in ("a","co") limit 1) as "Median Visit Length",
(select round(AVG(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date))) from visit v where idVisit in (select fv.idVisit from vlist_first_visit fv where ifnull(fv.Actual_Departure, date_add(curdate(), interval 1 day)) >= DATE_ADD(:startDate11, INTERVAL 1 DAY) and fv.Arrival_Date < :endDate11 and ((fv.Actual_Departure is null) <> (fv.Arrival_Date is null) or (fv.Actual_Departure is not null and fv.Arrival_Date is not null and DATEDIFF(fv.Actual_Departure, fv.Arrival_Date) <> 0))) and v.Status in ("a","co")) as "Average First Visit Length",
(select round(MEDIAN(DATEDIFF(ifnull(v.Actual_Departure, curdate()), v.Arrival_Date)) over (),1) from visit v where idVisit in (select fv.idVisit from vlist_first_visit fv where ifnull(fv.Actual_Departure, date_add(curdate(), interval 1 day)) >= DATE_ADD(:startDate12, INTERVAL 1 DAY) and fv.Arrival_Date < :endDate12 and ((fv.Actual_Departure is null) <> (fv.Arrival_Date is null) or (fv.Actual_Departure is not null and fv.Arrival_Date is not null and DATEDIFF(fv.Actual_Departure, fv.Arrival_Date) <> 0))) and v.Status in ("a","co") limit 1) as "Median First Visit Length";
';
        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ":startDate6"=>$start, ":endDate6"=>$end,
            ":startDate8"=>$start, ":endDate8"=>$end,
            ":startDate9"=>$start, ":endDate9"=>$end,
            ":startDate10"=>$start, ":endDate10"=>$end,
            ":startDate11"=>$start, ":endDate11"=>$end,
            ":startDate12"=>$start, ":endDate12"=>$end,
            ":startDate14"=>$start, ":endDate14"=>$end,
        ]);

        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $helptexts = array();

        //add help text
        $helptexts["Room-nights available"] = "Number of nights in time frame * number of regular rooms";
        $helptexts["Room-nights occupied"] = "Number of nights each room was occupied, including " . $rmtroomTitle;
        $helptexts["Occupancy Rate"] = "Room-nights occupied / Room-nights available";
        $helptexts[$rmtroomTitle . "-nights occupied"] = "Number of nights each " . $rmtroomTitle . " was occupied";
        $helptexts["Unique " . Labels::getString("Statement", "psgPlural", "PSGs")] = "Number of unique " . Labels::getString("Statement", "psgPlural", "PSGs") . " where anyone in the " . Labels::getString("Statement", "psgAbrev", "PSG") . " stayed";
        $helptexts["New " . Labels::getString("Statement", "psgPlural", "PSGs")] = "Number of unique " . Labels::getString("Statement", "psgPlural", "PSGs") . " whose first visit was in the time frame";
        $helptexts["Total Visits"] = "Number of visits with at least one night in the time frame";
        $helptexts["Average Visit Length"] = "Average length of an entire visit with at least one night in the time frame";
        $helptexts["Median Visit Length"] = "Median length of an entire visit with at least one night in the time frame";
        $helptexts["Average First Visit Length"] = "Average length of a " . Labels::getString("Statement", "psgPlural", "PSGs") . " FIRST visit with at least one night in the time frame";
        $helptexts["Median First Visit Length"] = "Median length of a " . Labels::getString("Statement", "psgPlural", "PSGs") . " FIRST visit with at least one night in the time frame";

        return array($data[0], $helptexts);
    }

    public function getGuestAvgPerNight(){
        $query = 'select
	if(n.BirthDate is not null, if(timestampdiff(YEAR, n.BirthDate, s.Span_Start_Date) < 18, "Child", "Adult"), if(nd.Is_Minor, "Child", "' . self::NOT_INDICATED . '")) as "child/adult",
    round(sum(DATEDIFF(IF(date(ifnull(s.Span_End_Date, now())) > date("' . $this->filter->getQueryEnd() . '"), date("' . $this->filter->getQueryEnd() . '"), date(ifnull(s.Span_End_Date, now()))), IF(date(s.Span_Start_Date) < date("' . $this->filter->getReportStart() . '"), date("' . $this->filter->getReportStart() . '"), date(s.Span_Start_Date))))/datediff(date("' . $this->filter->getQueryEnd() . '"), date("' . $this->filter->getReportStart() . '")),1) as "avg guests per night"
from stays s
join name n on s.idName = n.idName
join name_demog nd on n.idName = nd.idName
where ifnull(s.Span_End_Date, now()) >= '" . $this->filter->getReportStart() . "' and s.Span_Start_Date < '" . $this->filter->getQueryEnd() . "'
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

    public function getDiagnosisCategoryTotals(string $start, string $end, bool $isExcel = false){

        $query = 'select if(d.Code is not null, ifnull(dc.Description, "' . self::NO_CAT . '"), "' . self::NO_DIAGNOSIS . '") as "Category", sum(DATEDIFF(least(ifnull(v.Span_End, date("' . $end . '")), date("' . $end . '")), greatest(v.Span_Start, date("' . $start . '")))) as "count"
from visit v
join hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
left join gen_lookups d on hs.Diagnosis = d.Code and d.Table_Name = "Diagnosis"
left join gen_lookups dc on d.Substitute = dc.Code and dc.Table_Name = "Diagnosis_Category"
where (v.Span_End >= "' . $start . ' 00:00:00" || (v.Span_End is null and now() >= "' . $start . ' 00:00:00")) and v.Span_Start < "' . $end . ' 00:00:00"
group by `Category` order by `count` desc;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_NUM);

        if($isExcel == false){
            $total = $this->sumTotal($data);
            foreach($data as $key=>$value){
                $value[1] = (float) $value[1];
                $value[0] = $value[0] . " - " . number_format(($total > 0 ? $value[1]/$total*100 : 0), 1) . "%";
                $data[$key] = $value;
            }
        }

        array_unshift($data, ["Category", "Value"]);
        return $data;
    }

    /**
     * Sum up results from getDiagnosisCategoryTotals()
     * @param array $catTotals
     * @return int
     */
    private function sumTotal(array $catTotals):int
    {
        $total = 0;
        foreach($catTotals as $cat){
            $total+= $cat[1];
        }
        return $total;
    }

}
