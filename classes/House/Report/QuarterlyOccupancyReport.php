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

        return HTMLContainer::generateMarkup("div", '<div class="hhk-flex hhk-flex-wrap hhk-print-row">' . $summaryTbl->generateMarkup(array("class"=>"me-3 mb-3","style"=>"min-width: fit-content")) . $ageDistTbl->generateMarkup(array("class"=>"mb-3", "style"=>"min-width: fit-content")) . '</div><div class="hhk-flex hhk-flex-wrap hhk-print-row">' . '<div class="hhk-pieChart"><p style="text-align:center;"><strong>Average Number of Guests per Night</strong></p><div id="guestsPerNight" class=""></div></div><div class="hhk-pieChart"><p style="text-align:center; font-size: 1.1em"><strong>Visit-Nights by Diagnosis<span class="hhk-tooltip ui-icon ui-icon-help" title="Counts number of nights in a visit by diagnosis, whether or not the patient stayed"></span></strong></p><div id="diagnosisCategoryTotals"></div></div></div>', array("class"=>"hhk-flex hhk-flex-wrap hhk-visitdialog"));

    }

    public function makeFields(): array {
        $fields = [];

        if($this->dispType == "excel"){
            $fields[] = array("Date", "Date", 'checked', '', 'date', '20');
            $fields[] = array("Room-nights available", "Room-nights available", 'checked', '', 'string', '20');
            $fields[] = array("Room-nights occupied", "Room-nights occupied", 'checked', '', 'string', '20');
            $fields[] = array("Occupancy Rate", "Occupancy Rate", 'checked', '', 'string', '20');
            $fields[] = array($this->rmtroomTitle . "-nights occupied", $this->rmtroomTitle . "-nights occupied", 'checked', '', 'string', '20');
            $fields[] = array("Unique " . Labels::getString("Statement", "psgPlural", "PSGs"), "Unique " . Labels::getString("Statement", "psgPlural", "PSGs"), 'checked', '', 'string', '20');
            $fields[] = array("New " . Labels::getString("Statement", "psgPlural", "PSGs"), "New " . Labels::getString("Statement", "psgPlural", "PSGs"), 'checked', '', 'string', '20');
            $fields[] = array("Total Visits", "Total Visits", 'checked', '', 'string', '20');
            $fields[] = array("Average Visit Length", "Average Visit Length", 'checked', '', 'string', '20');
            $fields[] = array("Median Visit Length", "Median Visit Length", 'checked', '', 'string', '20');
            $fields[] = array("Average First Visit Length", "Average First Visit Length", 'checked', '', 'string', '20');
            $fields[] = array("Median First Visit Length", "Median First Visit Length", 'checked', '', 'string', '20');

            //age distribution
            $fields[] = array("Adult", "Adult", 'checked', '', 'string', '20');
            $fields[] = array("Child", "Child", 'checked', '', 'string', '20');
            $fields[] = array(self::NOT_INDICATED, self::NOT_INDICATED, 'checked', '', 'string', '20');
            $fields[] = array("Total Guests", "Total Guests", 'checked', '', 'string', '20');

            //diagCategories
            foreach($this->diagCats as $cat){
                $fields[] = array($cat[1], $cat[1], 'checked', '', 'string', '20');
            }
            $fields[] = array(self::NO_CAT, self::NO_CAT, 'checked', '', 'string', '20');
            $fields[] = array(self::NO_DIAGNOSIS, self::NO_DIAGNOSIS, 'checked', '', 'string', '20');
        }
        return $fields;

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

        $query = 'SELECT IF(`n`.`BirthDate` IS NOT NULL, IF(TIMESTAMPDIFF(YEAR, `n`.`BirthDate`, `s`.`Span_Start_Date`) < 18, "Child", "Adult"), IF(`nd`.`Is_Minor`, "Child", "' . self::NOT_INDICATED . '")) AS `Key`, COUNT(DISTINCT `n`.`idName`) AS "count" FROM `stays` `s` JOIN `visit` `v` ON `s`.`idVisit` = `v`.`idVisit` AND `s`.`Visit_Span` = `v`.`Span` JOIN `name` `n` ON `s`.`idName` = `n`.`idName` JOIN `name_demog` `nd` ON `n`.`idName` = `nd`.`idName` WHERE DATE(`s`.`Span_Start_Date`) < DATE(:endDate) AND DATE(IFNULL(`s`.`Span_End_Date`, NOW())) > DATE(:startDate) AND NOT DATE(`s`.`Span_End_Date`) <=> DATE(`s`.`Span_Start_Date`) AND NOT DATE(`v`.`Span_End`) <=> DATE(`v`.`Span_Start`) GROUP BY `Key`';
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

        $retiredRescSql = "(`re`.`Retired_At` IS NULL OR `re`.`Retired_At` > DATE(:retiredStart))";

        $query = '
SELECT
(SELECT COUNT(*) FROM `resource` `re` WHERE `re`.`Type` = "room" AND ' .$retiredRescSql .')*DATEDIFF(:end1, :start1) AS "Room-nights available",
(SELECT SUM(DATEDIFF(LEAST(IFNULL(`v`.`Span_End`, DATE(:end2)), DATE(:end3)), GREATEST(`v`.`Span_Start`, DATE(:start2)))) FROM `visit` `v` WHERE DATE(`v`.`Span_Start`) < DATE(:end4) AND DATE(IFNULL(`v`.`Span_End`, CURDATE())) > DATE(:start3) AND NOT DATE(`v`.`Span_Start`) <=> DATE(`v`.`Span_End`)) AS "Room-nights occupied",
CONCAT(ROUND((SELECT SUM(DATEDIFF(LEAST(IFNULL(`v`.`Span_End`, DATE(:end5)), DATE(:end6)), GREATEST(`v`.`Span_Start`, DATE(:start4)))) FROM `visit` `v` WHERE DATE(`v`.`Span_Start`) < DATE(:end7) AND DATE(IFNULL(`v`.`Span_End`, CURDATE())) > DATE(:start5))/((SELECT COUNT(*) FROM `resource` `re` WHERE `re`.`Type` = "room" AND ' . $retiredRescSql . ')*DATEDIFF(:end8, :start6))*100,1), "%") AS "Occupancy Rate",'.
//ifnull((select SUM(DATEDIFF(least(ifnull(v.Span_End, date("' . $end . '")), date("' . $end . '")), greatest(v.Span_Start, date("' . $start . '")))) from visit v join resource re on v.idResource = re.idResource where re.Type = "rmtroom" and date(v.Span_Start) < date("' . $end . '") and date(ifnull(v.Span_End, curdate())) > date("' . $start . '")), "0") as "' . $rmtroomTitle . '-nights occupied",
'(SELECT COUNT(DISTINCT `reg`.`idPsg`) FROM `visit` `v` JOIN `registration` `reg` ON `v`.`idRegistration` = `reg`.`idRegistration` WHERE DATE(`v`.`Span_Start`) < DATE(:endDate6) AND DATE(IFNULL(`v`.`Span_End`, CURDATE()+INTERVAL 1 DAY)) > DATE(:startDate6) AND NOT DATE(`v`.`Span_Start`) <=> DATE(`v`.`Span_End`)) AS "Unique ' . Labels::getString("Statement", "psgPlural", "PSGs") . '",
(SELECT COUNT(DISTINCT `reg`.`idPsg`) FROM `visit` `v` JOIN `registration` `reg` ON `v`.`idRegistration` = `reg`.`idRegistration` WHERE `idVisit` IN (SELECT `fv`.`idVisit` FROM `vlist_first_visit` `fv` WHERE DATE(IFNULL(`fv`.`Span_End`, CURDATE()+INTERVAL 1 DAY)) > DATE(:startDate14) AND DATE(`fv`.`Span_Start`) < DATE(:endDate14) AND NOT DATE(`fv`.`Span_Start`) <=> DATE(`fv`.`Span_End`))) AS "New ' . Labels::getString("Statement", "psgPlural", "PSGs") . '",
(SELECT COUNT(DISTINCT `v`.`idVisit`) FROM `visit` `v` WHERE DATE(`v`.`Span_Start`) < DATE(:endDate8) AND DATE(IFNULL(`v`.`Span_End`, CURDATE()+INTERVAL 1 DAY)) > DATE(:startDate8) AND NOT DATE(`v`.`Span_Start`) <=> DATE(`v`.`Span_End`)) AS "Total Visits",
(SELECT ROUND(AVG(DATEDIFF(IFNULL(`v`.`Actual_Departure`, CURDATE()), `v`.`Arrival_Date`)),1) FROM `visit` `v` WHERE DATE(`v`.`Arrival_Date`) < DATE(:endDate9) AND DATE(IFNULL(`v`.`Actual_Departure`, CURDATE()+INTERVAL 1 DAY)) > DATE(:startDate9) AND NOT DATE(`v`.`Arrival_Date`) <=> DATE(`v`.`Actual_Departure`) AND `v`.`Status` IN ("a","co")) AS "Average Visit Length",
(SELECT ROUND(MEDIAN(DATEDIFF(IFNULL(`v`.`Actual_Departure`, CURDATE()), `v`.`Arrival_Date`)) OVER (),1) FROM `visit` `v` WHERE DATE(`v`.`Arrival_Date`) < DATE(:endDate10) AND DATE(IFNULL(`v`.`Actual_Departure`, CURDATE()+INTERVAL 1 DAY)) > DATE(:startDate10) AND NOT DATE(`v`.`Arrival_Date`) <=> DATE(`v`.`Actual_Departure`) AND `v`.`Status` IN ("a","co") LIMIT 1) AS "Median Visit Length",
(SELECT ROUND(AVG(DATEDIFF(IFNULL(`v`.`Actual_Departure`, CURDATE()), `v`.`Arrival_Date`))) FROM `visit` `v` WHERE `idVisit` IN (SELECT `fv`.`idVisit` FROM `vlist_first_visit` `fv` WHERE DATE(IFNULL(`fv`.`Actual_Departure`, CURDATE()+INTERVAL 1 DAY)) > DATE(:startDate11) AND DATE(`fv`.`Arrival_Date`) < DATE(:endDate11) AND NOT DATE(`fv`.`Arrival_Date`) <=> DATE(`fv`.`Actual_Departure`)) AND `v`.`Status` IN ("a","co")) AS "Average First Visit Length",
(SELECT ROUND(MEDIAN(DATEDIFF(IFNULL(`v`.`Actual_Departure`, CURDATE()), `v`.`Arrival_Date`)) OVER (),1) FROM `visit` `v` WHERE `idVisit` IN (SELECT `fv`.`idVisit` FROM `vlist_first_visit` `fv` WHERE DATE(IFNULL(`fv`.`Actual_Departure`, CURDATE() + INTERVAL 1 DAY)) > DATE(:startDate12) AND DATE(`fv`.`Arrival_Date`) < DATE(:endDate12) AND NOT DATE(`fv`.`Arrival_Date`) <=> DATE(`fv`.`Actual_Departure`)) AND `v`.`Status` IN ("a","co") LIMIT 1) AS "Median First Visit Length";
';
        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ":retiredStart"=>$start,
            ":start1"=>$start, ":end1"=>$end,
            ":start2"=>$start, ":end2"=>$end, ":end3"=>$end,
            ":start3"=>$start, ":end4"=>$end,
            ":start4"=>$start, ":end5"=>$end, ":end6"=>$end,
            ":start5"=>$start, ":end7"=>$end,
            ":start6"=>$start, ":end8"=>$end,
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
        $queryEnd = $this->filter->getQueryEnd();
        $reportStart = $this->filter->getReportStart();

        $query = 'SELECT
	IF(`n`.`BirthDate` IS NOT NULL, IF(TIMESTAMPDIFF(YEAR, `n`.`BirthDate`, `s`.`Span_Start_Date`) < 18, "Child", "Adult"), IF(`nd`.`Is_Minor`, "Child", "' . self::NOT_INDICATED . '")) AS "child/adult",
    ROUND(SUM(DATEDIFF(IF(DATE(IFNULL(`s`.`Span_End_Date`, NOW())) > DATE(:end1), DATE(:end2), DATE(IFNULL(`s`.`Span_End_Date`, NOW()))), IF(DATE(`s`.`Span_Start_Date`) < DATE(:start1), DATE(:start2), DATE(`s`.`Span_Start_Date`))))/DATEDIFF(DATE(:end3), DATE(:start3)),1) AS "avg guests per night"
FROM `stays` `s`
JOIN `name` `n` ON `s`.`idName` = `n`.`idName`
JOIN `name_demog` `nd` ON `n`.`idName` = `nd`.`idName`
WHERE DATE(IFNULL(`s`.`Span_End_Date`, NOW())) >= DATE(:start4) AND DATE(`s`.`Span_Start_Date`) < DATE(:end4)
GROUP BY `child/adult`;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ':end1' => $queryEnd, ':end2' => $queryEnd, ':end3' => $queryEnd, ':end4' => $queryEnd,
            ':start1' => $reportStart, ':start2' => $reportStart, ':start3' => $reportStart, ':start4' => $reportStart,
        ]);
        $data = $stmt->fetchAll(\PDO::FETCH_NUM);

        foreach($data as $key=>$value){
            $value[1] = (float) $value[1];
            $data[$key] = $value;
        }

        array_unshift($data, ["Child/Adult", "Value"]);
        return $data;
    }

    public function getDiagnosisCategoryTotals(string $start, string $end, bool $isExcel = false){

        $query = 'SELECT IF(`d`.`Code` IS NOT NULL, IFNULL(`dc`.`Description`, "' . self::NO_CAT . '"), "' . self::NO_DIAGNOSIS . '") AS "Category", SUM(DATEDIFF(LEAST(IFNULL(`v`.`Span_End`, DATE(:end1)), DATE(:end2)), GREATEST(`v`.`Span_Start`, DATE(:start1)))) AS "count"
FROM `visit` `v`
JOIN `hospital_stay` `hs` ON `v`.`idHospital_stay` = `hs`.`idHospital_stay`
LEFT JOIN `gen_lookups` `d` ON `hs`.`Diagnosis` = `d`.`Code` AND `d`.`Table_Name` = "Diagnosis"
LEFT JOIN `gen_lookups` `dc` ON `d`.`Substitute` = `dc`.`Code` AND `dc`.`Table_Name` = "Diagnosis_Category"
WHERE (`v`.`Span_End` >= :start2 || (`v`.`Span_End` IS NULL AND NOW() >= :start3)) AND `v`.`Span_Start` < :end3
GROUP BY `Category` ORDER BY `count` DESC;';

        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ':end1' => $end, ':end2' => $end, ':end3' => $end . ' 00:00:00',
            ':start1' => $start, ':start2' => $start . ' 00:00:00', ':start3' => $start . ' 00:00:00',
        ]);
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