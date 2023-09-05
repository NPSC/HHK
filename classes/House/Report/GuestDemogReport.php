<?php

namespace HHK\House\Report;

use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput};
use HHK\Exception\RuntimeException;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\Exception\InvalidArgumentException;
use HHK\SysConst\VisitStatus;
use HHK\House\Distance\DistanceFactory;	
use HHK\House\Distance\ZipDistance;

/**
 * GuestReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of GuestReport
 *
 * @author Eric
 */
class GuestDemogReport {

    public static function demogReport(\PDO $dbh, $startDate, $endDate, $whHosp, $whAssoc, $whDiags, $whichGuests, $whPatient, $sourceZip, $roomGroupBy) {

        if ($startDate == '') {
            return;
        }

        if ($endDate == '') {
            $endDate = date('Y-m-01');
        }

        $uS = Session::getInstance();

        $stDT = new \DateTime($startDate);
        $stDT->setTime(0, 0, 0);

        $endDT = new \DateTime($endDate);
        $endDT->setTime(0, 0, 0);

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        $indxDT = new \DateTime($startDate);
        $indxDT->setTime(0, 0, 0);

        $periodFormat = 'Y-m';


        $accum = array();
        $periods = array();
//        $totalPSGs = array();

//        $firstPeriod = $thisPeriod = $stDT->format($periodFormat);
        $badZipCodes = array();

        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        // Set up user selected demographics categoryeis.
        $demoCategorys = array();

        $fields = '';

        foreach (readGenLookupsPDO($dbh, 'Demographics', 'Order') as $d) {

            if (strtolower($d[2]) == 'y') {


                if ($d[0] == 'Gender') {
                    $fields .= "ifnull(n.`Gender`,'') as `" . $d[1] . "`,";
                } else {
                    $fields .= "ifnull(nd.`" . $d[0] . "`, '') as `" . $d[1] . "`,";
                }

                $demoCategorys[$d[0]] = $d[1];
            }
        }

        //set up room grouping
        $roomGroupingTitle = "";
        $roomGroupings = readGenLookupsPDO($dbh, 'Room_Group', 'Order');
        switch ($roomGroupBy){
            case "Category":
                $roomGrouping = readGenLookupsPDO($dbh, 'Room_Category', 'Order');
                $roomGroupingTitle = (isset($roomGroupings["Category"]["Description"]) ? $roomGroupings["Category"]["Description"]: "Room Category");
                break;
            case "Report_Category":
                $roomGrouping = readGenLookupsPDO($dbh, 'Room_Rpt_Cat', 'Order');
                $roomGroupingTitle = (isset($roomGroupings["Report_Category"]["Description"]) ? $roomGroupings["Report_Category"]["Description"]: "Room Report Category");
                break;
            case "Type":
                $roomGrouping = readGenLookupsPDO($dbh, 'Room_Type', 'Order');
                $roomGroupingTitle = (isset($roomGroupings["Type"]["Description"]) ? $roomGroupings["Type"]["Description"]: "Room Type");
                break;
            default:
                $roomGrouping = [];
        }


        // Set up the Months array
        $th = HTMLTable::makeTh('');
        if($whichGuests != 'allStayed'){
            while ($indxDT < $endDT) {

                $thisPeriod = $indxDT->format($periodFormat);

                $th .= HTMLTable::makeTh($indxDT->format('M, Y'));

                $accum[$thisPeriod][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['cnt'] = 0;
                if ($whichGuests == 'new') {
                    $accum[$thisPeriod][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['title'] = 'New ' . Labels::getString('memberType', 'visitor', 'Guest') . 's';
                } else if($whichGuests == 'allStarted'){
                    $accum[$thisPeriod][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['title'] = 'All ' . Labels::getString('memberType', 'visitor', 'Guest') . 's starting in month';
                } else if($whichGuests == 'allStayed'){
                    $accum[$thisPeriod][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['title'] = 'Unique ' . Labels::getString('memberType', 'visitor', 'Guest') . 's staying in time period';
                }

                //room grouping
                $accum[$thisPeriod][Labels::getString('memberType', 'visitor', 'Guest') . 's by ' . $roomGroupingTitle] = self::makeCounters(removeOptionGroups($roomGrouping));

                // Demographics
                foreach ($demoCategorys as $k => $d) {
                    $accum[$thisPeriod][$d] = self::makeCounters(removeOptionGroups(readGenLookupsPDO($dbh, $k, 'Order')));
                }

                $accum[$thisPeriod]['Distance'] = self::makeCounters(removeOptionGroups(readGenLookupsPDO($dbh, 'Distance_Range', 'Substitute')));

                $periods[] = $thisPeriod;

                $indxDT->add(new \DateInterval('P1M'));
            }
        }
        $periods[] = 'Total';

        $accum['Total'][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['cnt'] = 0;
        if ($whichGuests == 'new') {
            $accum['Total'][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['title'] = 'New ' . Labels::getString('memberType', 'visitor', 'Guest') . 's';
        } else if($whichGuests == 'allStarted'){
            $accum['Total'][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['title'] = 'All ' . Labels::getString('memberType', 'visitor', 'Guest') . 's starting in month';
        } else if($whichGuests == 'allStayed'){
            $accum['Total'][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['title'] = 'Unique ' . Labels::getString('memberType', 'visitor', 'Guest') . 's staying in time period';
        }

        //room grouping
        $accum['Total'][Labels::getString('memberType', 'visitor', 'Guest') . 's by ' . $roomGroupingTitle] = self::makeCounters(removeOptionGroups($roomGrouping));

        // Totals
        foreach ($demoCategorys as $k => $d) {
            $accum['Total'][$d] = self::makeCounters(removeOptionGroups(readGenLookupsPDO($dbh, $k, 'Order')));
        }

        $accum['Total']['Distance'] = self::makeCounters(removeOptionGroups(readGenLookupsPDO($dbh, 'Distance_Range', 'Substitute')));

        if($uS->county){
            $accum['Total']['County'][''] = ['title'=>"Not Indicated", 'cnt'=>0];
        }

        $th .= HTMLTable::makeTh("Total");

        if ($whichGuests == 'new') {
            $query = "SELECT s.idName, MIN(s.Span_Start_Date) AS `minDate`,";
        } else if ($whichGuests == 'allStarted'){
            $query = "SELECT s.idName, DATE(s.Span_Start_Date) as `minDate`,";
        }else if ($whichGuests == 'allStayed'){
            $query = "SELECT DISTINCT s.idName,";
        }

        $query .= "na.idName_Address,	
        na.Purpose as `address_purpose`,	
        na.Address_1 as `address1`,	
        na.Address_2 as `address2`,	
        na.City as `city`,	
        na.State_Province as `state`,	
        na.State_Province,	
        na.Postal_Code as `zip`,	
        na.Postal_Code,
        $fields
        na.County,
        na.Meters_From_House,
        hs.idPsg,
        hs.idHospital,
        hs.idAssociation,
        r.Category,
        r.Report_Category,
        r.Type
    FROM
        stays s
            LEFT JOIN
        name n ON s.idName = n.idName
            LEFT JOIN
        name_demog nd ON n.idName = nd.idName
            LEFT JOIN
        name_address na ON n.idName = na.idName
            AND na.Purpose = n.Preferred_Mail_Address
            LEFT JOIN
        visit v ON s.idVisit = v.idVisit and s.Visit_Span = v.Span
            LEFT JOIN
        hospital_stay hs on v.idHospital_stay = hs.idHospital_stay $whPatient
            LEFT JOIN
        resource_room rr on v.idResource = rr.idResource
            LEFT JOIN
        room r on rr.idRoom = r.idRoom
    WHERE
        n.Member_Status IN ('a' , 'in', 'd') $whHosp $whAssoc $whDiags $whPatient
        AND DATE(s.Span_Start_Date) < DATE('" . $endDT->format('Y-m-d') . "') ";

        if ($whichGuests == 'new') {
            $query .= " GROUP BY s.idName HAVING DATE(`minDate`) >= DATE('" . $stDT->format('Y-m-01') . "')";
        } else if($whichGuests == 'allStarted'){
            $query .= " AND DATE(s.Span_Start_Date) >= DATE('" . $stDT->format('Y-m-01') . "') and s.Status in ('" . VisitStatus::Active . "','" . VisitStatus::CheckedOut . "')";
        } else if($whichGuests == 'allStayed'){
            $query .= " AND DATE(ifnull(s.Span_End_Date, now())) > DATE('" . $stDT->format('Y-m-01') . "')";
        }

        $currId = 0;
        $currPeriod = '';
        $stmt = $dbh->query($query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if($whichGuests != 'allStayed'){
                $spanStDT = new \DateTime($r['minDate']);
                $startPeriod = $spanStDT->format($periodFormat);
            }else{
                $startPeriod = 'Total';
            }
            if ($r['idName'] == $currId && $startPeriod == $currPeriod) {

                continue;
            }

            $currId = $r['idName'];
            $currPeriod = $startPeriod;

            foreach ($demoCategorys as $d) {
                if($whichGuests != 'allStayed'){
                    $accum[$startPeriod][$d][$r[$d]]['cnt']++;
                }
                $accum['Total'][$d][$r[$d]]['cnt']++;
            }

            //room grouping
            if($whichGuests != 'allStayed'){
                $accum[$startPeriod][Labels::getString('memberType', 'visitor', 'Guest') . 's by ' . $roomGroupingTitle][$r[$roomGroupBy]]['cnt']++;
            }
            $accum['Total'][Labels::getString('memberType', 'visitor', 'Guest') . 's by ' . $roomGroupingTitle][$r[$roomGroupBy]]['cnt']++;

            if($whichGuests != 'allStayed'){
                $accum[$startPeriod][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['cnt']++;
            }
            $accum['Total'][Labels::getString('memberType', 'visitor', 'Guest') . 's']['o']['cnt']++;


            try {
                $distanceCalculator = DistanceFactory::make();	
                if($r['Meters_From_House'] > 0){	
                    $miles = $distanceCalculator->meters2miles($r['Meters_From_House']);	
                }elseif ($uS->distCalculator == 'zip'){	
                    $miles = $distanceCalculator->getDistance($dbh, ['zip'=>$r["zip"]], $uS->houseAddr, "miles");	
                }else{    
                    throw new \RuntimeException("Distance not calculated");
                }

                foreach ($accum[$startPeriod]['Distance'] as $d => $val) {

                    if ($miles <= $d) {
                        if($whichGuests != 'allStayed'){
                            $accum[$startPeriod]['Distance'][$d]['cnt']++;
                        }
                        $accum['Total']['Distance'][$d]['cnt']++;
                        break;
                    }
                }

            } catch (\RuntimeException $hex) {

                $badZipCodes[$r['Postal_Code']] = 'y';
                if($whichGuests != 'allStayed'){
                    $accum[$startPeriod]['Distance']['']['cnt']++;
                }
                $accum['Total']['Distance']['']['cnt']++;

            }

            if($uS->county){
                $countyTitle = ($r["County"] != "" ? $r["County"] . ", " . $r['State_Province'] : "Not Indicated");
                $countyKey = ($r["County"] != "" ? $r['State_Province'] . " " . $r["County"] : ""); // used for sorting/grouping counties

                if($whichGuests != 'allStayed'){
                    if(!isset($accum[$startPeriod]['County'][$countyKey])){
                        $accum[$startPeriod]['County'][$countyKey]['title'] = $countyTitle;
                        $accum[$startPeriod]['County'][$countyKey]['cnt'] = 1;
                    }else{
                        $accum[$startPeriod]['County'][$countyKey]['cnt']++;
                    }
                }

                if(!isset($accum['Total']['County'][$countyKey])){
                    $accum['Total']['County'][$countyKey]['title'] = $countyTitle;
                    $accum['Total']['County'][$countyKey]['cnt'] = 1;
                }else{
                    $accum['Total']['County'][$countyKey]['cnt']++;
                }
            }
            //$totalPSGs[$r['idPsg']] = 'y';
        }

        if($uS->county){
            //fill in missing cells
            foreach($periods as $period){
                foreach($accum['Total']['County'] as $county=>$ar){
                    if(!isset($accum[$period]['County'][$county])){
                        $accum[$period]['County'][$county] = ['title'=>($county !="" ? $county : "Not Indicated"), 'cnt'=>0];
                    }
                }
            }

            //reorder counties
            foreach($accum as $period=>$ar){
                if(isset($accum[$period]['County'])){
                    //sort ascending by state, county
                    ksort($accum[$period]['County']);
                    //push Not Indicated to end
                    $notIndicated = $accum[$period]['County'][""];
                    unset($accum[$period]['County'][""]);
                    $accum[$period]['County'][""] = $notIndicated;
                }
            }
        }

        $trs = array();
        $rowCount = 0;

        // Title Column
        foreach ($accum['Total'] as $k => $demog) {

            $trs[$rowCount++] = HTMLTable::makeTd(str_replace('_', ' ', $k), array('class' => 'hhk-tdTitle'));

            foreach ($demog as $indx) {
                $trs[$rowCount++] = HTMLTable::makeTd($indx['title'], array('class' => 'tdlabel'));
            }
        }

        // Data columns
        foreach ($periods as $col) {

            $rowCount = 0;

            foreach ($accum[$col] as $demog) {

                if (isset($trs[$rowCount])) {

                    $trs[$rowCount++] .= HTMLTable::makeTd('', array('class' => 'hhk-tdTitle'));

                    foreach ($demog as $indx) {
                        $trs[$rowCount++] .= HTMLTable::makeTd($indx['cnt'] > 0 ? $indx['cnt'] : '');
                    }
                }
            }
        }


        // create table
        $tbl = new HTMLTable();
        $tbl->addHeaderTr($th);

        foreach ($trs as $tr) {
            $tbl->addBodyTr($tr);
        }

        if (count($badZipCodes) > 0) {

            $zipList = '';

            foreach ($badZipCodes as $k => $v) {
                if ($k != '') {
                    $zipList .= ", " . $k;
                }
            }

            if ($zipList != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("Bad zip codes") . HTMLTable::makeTd(substr($zipList, 2), array('colspan' => '12')) . HTMLTable::makeTd(count($badZipCodes)));
            }
        }

        return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
    }

    public static function generateFilterBtnMarkup(string $whichGuests = "", string $guestsvspatients = ""){
        $labels = Labels::getLabels();
        $newGuestsAttrs = ["type"=>"radio", "name"=>"rbAllGuests", "id"=>"rbnewG"];
        $allStartedAttrs = ["type"=>"radio", "name"=>"rbAllGuests", "id"=>"rbAllStartStay"];
        $allStayedAttrs = ["type"=>"radio", "name"=>"rbAllGuests", "id"=>"rbAllGStay"];
        $guestsandPatientsAttrs = ["type"=>"radio", "name"=>"rbGuestsPatients", "id"=>"rbGandP"];
        $justPatientsAttrs = ["type"=>"radio", "name"=>"rbGuestsPatients", "id"=>"rbP"];

        switch ($whichGuests){
            case "allStarted":
                $allStartedAttrs["checked"] = "checked";
                break;
            case "allStayed":
                $allStayedAttrs["checked"] = "checked";
                break;
            default:
                $newGuestsAttrs["checked"] = "checked";
        }

        if($guestsvspatients == "patients"){
            $justPatientsAttrs["checked"] = "checked";
        }else{
            $guestsandPatientsAttrs["checked"] = "checked";
        }

        $filterOptsMkup = HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("new", $newGuestsAttrs) .
            HTMLContainer::generateMarkup("label", "First Time " . $labels->getString('MemberType', 'visitor', 'Guest') . "s Only", ["for"=>"rbnewG"])
        );

        $filterOptsMkup .= HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("allStarted", $allStartedAttrs) .
            HTMLContainer::generateMarkup("label", "All " . $labels->getString('MemberType', 'visitor', 'Guest') . "s who started stay", ["for"=>"rbAllStartStay"])
        );

        $filterOptsMkup .= HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("allStayed", $allStayedAttrs) .
            HTMLContainer::generateMarkup("label", "All " . $labels->getString('MemberType', 'visitor', 'Guest') . "s who stayed", ["for"=>"rbAllGStay"])
        );

        $filterOptsMkup .= HTMLContainer::generateMarkup("div","", ["style"=>"background: #a6c9e2; width:1px; margin: 0 0.5em;"]);

        $filterOptsMkup .= HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("guestsandpatients", $guestsandPatientsAttrs) .
            HTMLContainer::generateMarkup("label", $labels->getString('MemberType', 'visitor', 'Guest') . "s & " . $labels->getString('MemberType', 'patient', 'Patient') . "s", ["for"=>"rbGandP"])
        );

        $filterOptsMkup .= HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("patients", $justPatientsAttrs) .
            HTMLContainer::generateMarkup("label", $labels->getString('MemberType', 'patient', 'Patient') . "s", ["for"=>"rbP"])
        );

        $btnSubmit = HTMLInput::generateMarkup("Run Report", ["type"=>"submit", "id"=>"btnSmt", "name"=>"btnSmt", "class"=>"ui-button ui-corner-all ui-widget"]);

        return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("div","<strong>Filter Options:</strong>". $filterOptsMkup, array("class"=>"ui-widget-content ui-corner-all hhk-flex mr-5", "id"=>"filterOpts", "style"=>"align-items: initial")) . $btnSubmit, ["id"=>"filterBtns", "class"=>"mt-3"]);
    }

    public static function calcZipDistance(\PDO $dbh, $sourceZip, $destZip) {

        if(empty($destZip)){
            throw new InvalidArgumentException("Destination ZIP code can't be empty");
        }
        
        if (strlen($destZip) > 5) {
            $destZip = substr($destZip, 0, 5);
        }

        if ($destZip == $sourceZip) {
            return 0;
        }

        $miles = 0;
        $stmt = $dbh->prepare("select Zip_Code, Lat, Lng from postal_codes where Zip_Code in (:src, :dest)");
        $stmt->execute(array(':src' => $sourceZip, ':dest' => $destZip));

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        if (count($rows) == 2) {
            $miles = self::calcDist($rows[0][1], $rows[0][2], $rows[1][1], $rows[1][2]);
        } else {
            throw new RuntimeException("One or both zip codes not found in zip table, source=$sourceZip, dest=$destZip.  ");
        }
        return $miles;
    }

    protected static function calcDist($lat_A, $long_A, $lat_B, $long_B) {

        $distance = sin(deg2rad((double)$lat_A)) * sin(deg2rad((double)$lat_B)) + cos(deg2rad((double)$lat_A)) * cos(deg2rad((double)$lat_B)) * cos(deg2rad((double)$long_A - (double)$long_B));
        $distance2 = (rad2deg(acos($distance))) * 69.09;

        return $distance2;
    }

    protected static function makeCounters($GL_Table, $includeBlank = TRUE) {
        $age = array();
        foreach ($GL_Table as $a) {
            $age[$a[0]]['cnt'] = 0;
            $age[$a[0]]['title'] = $a[1];
        }

        if ($includeBlank) {
            $age['']['cnt'] = 0;
            $age['']['title'] = 'Not Indicated';
        }

        return $age;
    }

}
?>