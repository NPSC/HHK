<?php

use HHK\Common;
use HHK\House\Distance\ZipDistance;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\GLTableNames;
use HHK\HTMLControls\HTMLContainer;
use HHK\CreateMarkupFromDB;
use HHK\SysConst\RelLinkType;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLSelector;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\House\Report\ReportFilter;
use HHK\House\Distance\DistanceFactory;
use HHK\TableLog\HouseLog;

/**
 * PSG_Report.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;


// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

$labels = Labels::getLabels();



function getPeopleReport(\PDO $dbh, $local, $showRelationship, $whClause, $start, $end, $showAddr, $showFullName, $showNoReturn, $showUnique, $showAssoc, $labels, $showDiagnosis, $showLocation) {

    $uS = Session::getInstance();

    $query = '';
    $agentTitle = $labels->getString('hospital', 'referralAgent', 'Referral Agent');
    $diagTitle = $labels->getString('hospital', 'diagnosis', 'Diagnosis');
    $diagDetailTitle = $labels->getString('hospital', 'diagnosisDetail', 'Diagnosis Details');
    $locTitle = $labels->getString('hospital', 'location', 'Location');
    $patTitle = $labels->getString('MemberType', 'patient', 'Patient');

    $guestFirst = $labels->getString('MemberType', 'visitor', 'Guest') . ' First';
    $guestLast = $labels->getString('MemberType', 'visitor', 'Guest') . ' Last';

    $totalDistance = 0;

    if($showUnique){
        $spanDates = " ifnull(max(s.Span_End_Date), '') as `Last Departure`, ";
        $docSql = " group_concat(DISTINCT n.Name_Full SEPARATOR ', ') as `Doctor`, ";
        $hospAssocSql = "group_concat(DISTINCT h.Title SEPARATOR ', ') as `Hospital`, group_concat(DISTINCT a.Title SEPARATOR ', ') as `Association`, ";
        $agentSql = "group_concat(DISTINCT nr.Name_Full SEPARATOR', ') as `$agentTitle` ";
        $locSql = "group_concat(DISTINCT gl.Description SEPARATOR ', ') as `$locTitle`, ";
        $diagSql = " group_concat(DISTINCT ifnull(g.Description, hs.Diagnosis)) as `$diagTitle`, ";
    }else{
        $spanDates = " ifnull(s.Span_Start_Date, '') as `Arrival`, ifnull(s.Span_End_Date, '') as `Departure`, ";
        $docSql = " ifnull(n.Name_Full, '') as `Doctor`, ";
        $hospAssocSql = "h.Title as `Hospital`, a.Title as `Association`, ";
        $agentSql = "ifnull(nr.Name_Full, '') as `$agentTitle` ";
        $locSql = "ifnull(gl.Description, '') as `$locTitle`, ";
        $diagSql = " ifnull(g.Description, hs.Diagnosis) as `$diagTitle`, " . ($uS->ShowDiagTB ? "ifnull(hs.Diagnosis2, '') as `$diagDetailTitle`, ": "");
    }

    $queryStatus = " CASE WHEN s.On_Leave > 0 and s.`Status` = 'a' THEN 'On Leave' ELSE ifnull(g2.Description,'') END as `Status`, ";

    if ($showAddr && $showFullName) {

        $query = "select s.idName as Id, hs.idPsg, ng.Relationship_Code, " . ($showUnique ? "" : "v.idReservation as `Resv ID`, ")
            . "g3.Description as `Patient Rel.`, vn.Prefix, vn.First as `$guestFirst`, vn.Last as `$guestLast`, vn.Suffix, ifnull(vn.BirthDate, '') as `Birth Date`, if(vn.Member_Status = 'd', ifnull(vn.Date_Deceased, 'Deceased'), '') as `Deceased Date`, "
                . "np.Name_First as `$patTitle First` , np.Name_Last as `$patTitle Last`, "
                . " vn.Address, vn.City, vn.County, vn.State, vn.Zip, vn.Country, vn.Meters_From_House as `Distance (miles)`, vn.Bad_Address, vn.Phone, vn.Email, "
                    . ($showUnique ? "" : $queryStatus  . "r.title as `Room`,")
                . $spanDates
                //. " ifnull(rr.Title, '') as `Rate Category`, 0 as `Total Cost`, "
                . $hospAssocSql
                . $diagSql . $locSql
                . $docSql . $agentSql;

    } else if ($showAddr && !$showFullName) {

        $query = "select s.idName as Id, hs.idPsg, ng.Relationship_Code,
            vn.Last as `$guestLast`, vn.First as `$guestFirst`, ifnull(vn.BirthDate, '') as `Birth Date`, if(vn.Member_Status = 'd', if(vn.Date_Deceased != '', vn.Date_Deceased, 'Deceased'), '') as `Deceased Date`, g3.Description as `Patient Rel.`, vn.Phone, vn.Email, vn.`Address`, vn.City, vn.County, vn.State, vn.Zip, case when vn.Country = '' then 'US' else vn.Country end as Country, vn.Meters_From_House as `Distance (miles)`, vn.Bad_Address, `nd`.`No_Return`, "
            . ($showUnique ? "" : $queryStatus . "r.title as `Room`," )
                    . $spanDates
                    . $hospAssocSql
                    . "np.Name_Last as `$patTitle Last`, np.Name_First as `$patTitle First`, "
                    . $diagSql . $locSql . $docSql . $agentSql;

    } else if (!$showAddr && $showFullName) {

        $query = "select s.idName as Id, hs.idPsg, ng.Relationship_Code, vn.Prefix, vn.First as `$guestFirst`, vn.Middle, vn.Last as `$guestLast`, vn.Suffix, ifnull(vn.BirthDate, '') as `Birth Date`, if(vn.Member_Status = 'd', if(vn.Date_Deceased != '', vn.Date_Deceased, 'Deceased'), '') as `Deceased Date`, g3.Description as `Patient Rel.`, `nd`.`No_Return`, "
            . ($showUnique ? "" :$queryStatus . "r.title as `Room`," )
                    . $spanDates
                    . "np.Name_Last as `$patTitle Last`, np.Name_First as `$patTitle First` , "
                    . $diagSql . $locSql . $hospAssocSql . $docSql . $agentSql;

    } else {

        $query = "select s.idName as Id, hs.idPsg, ng.Relationship_Code, vn.Last as `$guestLast`, vn.First as `$guestFirst`, ifnull(vn.BirthDate, '') as `Birth Date`, if(vn.Member_Status = 'd', if(vn.Date_Deceased != '', vn.Date_Deceased, 'Deceased'), '') as `Deceased Date`, g3.Description as `Patient Rel.`, `nd`.`No_Return`, "
            . ($showUnique ? "" : $queryStatus . "r.title as `Room`, ") . $spanDates
                . "np.Name_Last as `$patTitle Last`, np.Name_First as `$patTitle First`, "
                . $diagSql . $locSql . $hospAssocSql . $docSql . $agentSql;
    }

    if ($showNoReturn) {
        $whClause .= " and `nd`.`No_Return` != '' ";
    }

    $query .= " from stays s
        JOIN
    vname_list vn on vn.Id = s.idName
        JOIN
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
		JOIN
	registration rg on v.idRegistration = rg.idRegistration
		JOIN
	name_guest `ng` on s.idName = ng.idName and ng.idPsg = rg.idPsg
		JOIN
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
        LEFT JOIN
    hospital h ON hs.idHospital = h.idHospital and h.Type = 'h'
        LEFT JOIN
    hospital a ON hs.idAssociation = h.idHospital and h.Type = 'a'
		LEFT JOIN
	name_demog nd on s.idName = nd.idName
        LEFT JOIN
    name np on hs.idPatient = np.idName
        LEFT JOIN
    name n on hs.idDoctor = n.idName
        LEFT JOIN
    name nr on hs.idReferralAgent = nr.idName
        LEFT JOIN
    gen_lookups g on g.Table_Name = 'Diagnosis' and g.Code = hs.Diagnosis
        LEFT JOIN
    gen_lookups gl on gl.Table_Name = 'Location' and gl.Code = hs.Location
        LEFT JOIN
    gen_lookups g2 on g2.Code = s.Status and g2.Table_Name = 'Visit_Status'
        LEFT JOIN
    `gen_lookups` `g3` ON `g3`.`Table_Name` = 'Patient_Rel_Type' AND `g3`.`Code` = `ng`.`Relationship_Code`
		LEFT JOIN
    room_rate rr on v.idRoom_rate = rr.idRoom_rate
    	JOIN
    room r on s.idRoom = r.idRoom
where  DATE(ifnull(s.Span_End_Date, now())) >= DATE('$start') and DATE(s.Span_Start_Date) < DATE('$end') and DATEDIFF(DATE(ifnull(s.Span_End_Date, now())), DATE(s.Span_Start_Date)) > 0 $whClause";

    if ($showUnique) {
        $query .= " GROUP BY hs.idPsg, s.idName";
    }

    $stmt = $dbh->query($query);

    if (!$local) {

        $reportRows = 1;
        $file = 'PeopleReport';
        $writer = new ExcelHelper($file);
        $writer->setTitle("People Report");
    }

    $rows = array();
    $firstRow = TRUE;

    $distanceCalculator = DistanceFactory::make();

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {


        if ($uS->county === FALSE) {
            unset($r['County']);
        }

        //convert Meters_From_House to Miles
        if(isset($r["Distance (miles)"]) && $r["Distance (miles)"] > 0){
            $r["Distance (miles)"] = $distanceCalculator->meters2miles($r["Distance (miles)"]);
            $totalDistance += $r["Distance (miles)"];
        }else if(isset($r["Distance (miles)"])){
            $r["Distance (miles)"] = '';
        }

        if (!$uS->ShowBirthDate) {
            unset($r['Birth Date']);
        }

        if (!$showNoReturn) {
            unset($r['No_Return']);
        }

        if ($showRelationship === FALSE) {
            unset($r[$patTitle.' First']);
            unset($r[$patTitle.' Last']);
            unset($r['Patient Rel.']);
        } else if ($patTitle != 'Patient') {
            $r[$patTitle . ' Rel.'] = $r['Patient Rel.'];
            unset($r['Patient Rel.']);
        }

        unset($r['Relationship_Code']);

        if ($showDiagnosis === FALSE) {
            unset($r['Diagnosis']);
            unset($r['Diagnosis2']);
        }else{
            if(!$uS->ShowDiagTB){
                unset($r['Diagnosis2']);
            }
        }

        if ($showLocation === FALSE) {
            unset($r['Location']);
        }

        if ($uS->Doctor === FALSE) {
            unset($r['Doctor']);
        }

        if ($uS->ReferralAgent === FALSE) {
            unset($r[$agentTitle]);
        }

//         if ($showAssoc === FALSE) {
//             $r['idAssociation'] = 0;
//         } else {
//             $r['Association'] = '';
//         }

//         // Hospital
//         $r[$labels->getString('hospital', 'hospital', 'Hospital')] = '';


//         if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
//             $r['Association'] = $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1];
//         }
//         if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
//             $r[$labels->getString('hospital', 'hospital', 'Hospital')] = $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
//         }

//         if (count($uS->guestLookups[GLTableNames::Hospital]) < 2) {
//             unset($r[$labels->getString('hospital', 'hospital', 'Hospital')]);
//         }

//         unset($r['idHospital']);
//         unset($r['idAssociation']);


        if ($firstRow) {

            $firstRow = FALSE;

            if ($local === FALSE) {

                // build header
                $hdr = array();
                $colWidths = array();

                $noReturn = '';

                // Header row
                $keys = array_keys($r);
                foreach ($keys as $k) {

                    if ($k == 'No_Return') {
                        $noReturn = 'No Return';
                        continue;
                    }



                    if($k == 'Arrival' || $k == 'Departure' || $k == 'First Arrival' || $k == 'Last Departure'|| $k == 'Birth Date'){
                        $hdr[$k] = "MM/DD/YYYY";
                    }else{
                        $hdr[$k] = "string";
                    }

                    if($k == 'idPsg' || $k == "Id" || $k == "Resv ID" || $k == "Prefix" || $k == "Suffix" || $k == "State" || $k == "Zip" || $k == "Country"){
                        $colWidths[] = "10";
                    }else{
                        $colWidths[] = "20";
                    }
                }

                if ($noReturn != '') {
                    $hdr[$noReturn] = "string";
                }

                $hdrStyle = $writer->getHdrStyle($colWidths);

                $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
            }
        }

        if ($local) {

            $r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'GuestEdit.php?id=' . $r['Id'] . '&psg=' . $r['idPsg']));

            if (isset($r['Birth Date']) && $r['Birth Date'] != '') {
                $r['Birth Date'] = date('n/d/Y', strtotime($r['Birth Date']));
            }
            if (isset($r['Deceased Date']) && $r['Deceased Date'] != '' && $r['Deceased Date'] != "Deceased") {
                $r['Deceased Date'] = date('n/d/Y', strtotime($r['Deceased Date']));
            }
            if (isset($r['Arrival']) && $r['Arrival'] != '') {
                $r['Arrival'] = date('n/d/Y', strtotime($r['Arrival']));
            }
            if (isset($r['Departure']) && $r['Departure'] != '') {
                $r['Departure'] = date('n/d/Y', strtotime($r['Departure']));
            }
            if (isset($r['First Arrival']) && $r['First Arrival'] != '') {
                $r['First Arrival'] = date('n/d/Y', strtotime($r['First Arrival']));
            }
            if (isset($r['Last Departure']) && $r['Last Departure'] != '') {
                $r['Last Departure'] = date('n/d/Y', strtotime($r['Last Departure']));
            }
            unset($r['idPsg']);

            if (isset($r['No_Return'])) {

                if ($r['No_Return'] != '' && isset($uS->nameLookups['NoReturnReason'][$r['No_Return']])) {
                    $r['No Return'] = $uS->nameLookups['NoReturnReason'][$r['No_Return']][1];
                } else {
                    $r['No Return'] = ' ';
                }

                unset($r['No_Return']);
            }

            // Manage bad address.
            if (isset($r['Bad_Address']) && $r['Bad_Address'] == 'true' && isset($r['Address'])) {
                $r['Address'] = HTMLContainer::generateMarkup(
                    'div',
                    HTMLContainer::generateMarkup("span", $r["Address"], ['title'=>'Bad Address']) . HTMLContainer::generateMarkup("span", "", array("class" => 'ui-icon ui-icon-notice ml-2')),
                    array('class' => 'hhk-flex', 'style' => 'justify-content: space-between;', 'title' => 'Bad Address')
                );
            }

            unset($r['Bad_Address']);

            $rows[] = $r;

        } else {

            $n = 0;
            $flds = array();

            if (isset($r['No_Return'])) {

                if ($r['No_Return'] != '' && isset($uS->nameLookups['NoReturnReason'][$r['No_Return']])) {
                    $r['No Return'] = $uS->nameLookups['NoReturnReason'][$r['No_Return']][1];
                } else {
                    $r['No Return'] = '';
                }

                unset($r['No_Return']);
            }

            foreach ($r as $key => $col) {

                if (($key == 'Arrival' or $key == 'Departure' || $key == 'Birth Date') && $col != '') {
                    $flds[] = $col;
                } else {
                    $flds[] = $col;
                }
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }

    }

    if ($local) {

        $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
        return array("table"=>$dataTable, "TotalDistance"=>$totalDistance);


    } else {
        HouseLog::logDownload($dbh, 'People Report', "Excel", "People Report for " . $start . " - " . $end . " downloaded", $uS->username);
        $writer->download();
    }
}

function getPsgReport(\PDO $dbh, $local, $whFields, $start, $end, $relCodes, $hospCodes, $labels, $showAssoc, $showDiagnosis, $showDiagDetails, $showLocation, $patBirthDate, $patAsGuest = true, $showCounty = FALSE) {

    $diagTitle = $labels->getString('hospital', 'diagnosis', 'Diagnosis');
    $diagDetailTitle = $labels->getString('hospital', 'diagnosisDetail', 'Diagnosis Details');
    $locTitle = $labels->getString('hospital', 'location', 'Location');
    $psgLabel = $labels->getString('statement', 'psgAbrev', 'PSG') . ' Id';
    $patRelTitle = $labels->getString('MemberType', 'patient', 'Patient') . " Relationship";
    $hospTitle = $labels->getString('hospital', 'hospital', 'Hospital');

    $query = "Select DISTINCT
    ng.idPsg as `$psgLabel`,
    ifnull(ng.idName, 0) as `Id`,
    ifnull(n.Name_First,'') as `First`,
    ifnull(n.Name_Last,'') as `Last`,
    ifnull(na.Address_1,'') as `Street`,
    ifnull(na.Address_2,'') as `Apt`,
    ifnull(na.City,'') as `City`,
    ifnull(na.County, '') as `County`,
    ifnull(na.State_Province, '') as `State`,
    ifnull(na.Postal_Code, '') as `Zip Code`,
    ifnull(na.Country_Code, '') as `Country`,
    ifnull(ng.Relationship_Code,'') as `$patRelTitle`,
    ifnull(n.BirthDate, '') as `Birth Date`,
    if(n.Member_Status = 'd', 'Deceased', '') as `Status`,
    ifnull(hs.idHospital, '') as `$hospTitle`,
    ifnull(hs.idAssociation, '') as `Association`,
    ifnull(g.Description, hs.Diagnosis) as `$diagTitle`,
    ifnull(hs.Diagnosis2, '') as `$diagDetailTitle`,
    ifnull(g1.Description, '') as `$locTitle`,
	case when ng.Relationship_Code = 'slf' then 0 else 1 end as `ispat`
from
    visit v
        join
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
		join
	name_guest ng on hs.idPsg = ng.idPsg
        join
    `name` n ON ng.idName = n.idname
        left join
    name_address na on na.idName = n.idName and na.Purpose = n.Preferred_Mail_Address
        left join
    gen_lookups g on g.`Table_Name` = 'Diagnosis' and g.`Code` = hs.Diagnosis
        left join
    gen_lookups g1 on g1.`Table_Name` = 'Location' and g1.`Code` = hs.Location

where n.Member_Status != 'TBD' and DATE(ifnull(v.Span_End, now())) >= DATE('$start') and DATE(v.Span_Start) < DATE('$end')
 $whFields
order by ng.idPsg, `ispat`, `Id`";

	if (!$local) {

	     $reportRows = 1;
	     $file = $psgLabel . 'Report';
	     $writer = new ExcelHelper($file);
	     $writer->setTitle("PSG Report");

	}

	 $psgId = 0;
	 $rows = array();
	 $firstRow = TRUE;
	 $separatorClassIndicator = '))+class';
	 $numberPSGs = 0;
	 $guestId = 0;

	 $stmt = $dbh->query($query);
	 $rowCount = $stmt->rowCount();

	 while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

	 	unset($r['ispat']);

	     $relCode = $r[$patRelTitle];

	     if ($relCode != RelLinkType::Self && $guestId == $r['Id']) {
	     	continue;
	     }

	     $guestId = $r['Id'];

	     if (isset($relCodes[$relCode])) {
	         $r[$patRelTitle] = $relCodes[$relCode][1];
	     } else {
	         $r[$patRelTitle] = '';
	     }

	     // Hospital
	     if (!$showAssoc) {
	         unset($r['Association']);
	     } else if ($showAssoc && $r['Association'] > 0 && isset($hospCodes[$r['Association']]) && $hospCodes[$r['Association']][1] != '(None)') {
	         $r['Association'] = $hospCodes[$r['Association']][1];
	     } else {
	         $r['Association'] = '';
	     }

	     if ($r[$hospTitle] > 0 && isset($hospCodes[$r[$hospTitle]])) {
	     	$r[$hospTitle] = $hospCodes[$r[$labels->getString('hospital', 'hospital', 'Hospital')]][1];
	     } else {
	     	$r[$hospTitle] = '';
	     }

	     if ($showCounty === FALSE) {
	     	unset($r['County']);
	     }
	     if (count($hospCodes) < 2) {
	     	unset($r[$hospTitle]);
	     }

	     if ($showDiagnosis === FALSE) {
	         unset($r[$diagTitle]);
	         unset($r[$diagDetailTitle]);
	     }else{
	         if(!$showDiagDetails){
	             unset($r[$diagDetailTitle]);
	         }
	     }

	     if ($showLocation === FALSE) {
	         unset($r[$locTitle]);
	     }

	     if (!$patBirthDate) {
	         unset($r['Birth Date']);
	     }

	     if ($firstRow) {

	         $firstRow = FALSE;

	         if ($local === FALSE) {

	             // build header
	             $hdr = array();
	             $colWidths = array();

	             // Header row
	             $keys = array_keys($r);
	             foreach ($keys as $k) {
	                 if($k == 'Arrival' || $k == 'Departure' || $k == 'Birth Date'){
	                     $hdr[$k] = "MM/DD/YYYY";
	                 }else{
	                    $hdr[$k] =  "string";
	                 }

	                 if($k == 'PSG Id' || $k == "Id" || $k == "State" || $k == "Country"){
	                     $colWidths[] = "10";
	                 }else{
	                     $colWidths[] = "20";
	                 }
	             }

	             $hdrStyle = $writer->getHdrStyle($colWidths);

	             $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
	         }
	     }

	     if ($psgId != $r[$psgLabel]) {
	         $firstTd = $r[$psgLabel];
	         $psgId = $r[$psgLabel];
	         $numberPSGs++;
	     } else {
	         $firstTd = '';
	     }


	     if ($local) {

	         $r[$psgLabel] = $firstTd;

	         if (isset($r['Birth Date'])) {
	             $r['Birth Date'] = $r['Birth Date'] == '' ? '' : date('M j, Y', strtotime($r['Birth Date']));
	         }
	         $r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'GuestEdit.php?id=' . $r['Id'] . '&psg=' . $r[$psgLabel]));

	         if ($firstTd != '') {
	             $r[$separatorClassIndicator] = 'hhk-rowseparater';
	         }

	         if ($relCode == RelLinkType::Self) {

	             $r[$patRelTitle] = HTMLContainer::generateMarkup('span', $r[$patRelTitle], array('style'=>'font-weight:bold;'));

	         } else if ($patAsGuest) {
	             // Not a patient
	             if (isset($r[$diagTitle])) {
	                 $r[$diagTitle] = '';
	             }
	             if (isset($r[$diagDetailTitle])) {
	                 $r[$diagDetailTitle] = '';
	             }
	             if (isset($r[$locTitle])) {
	                 $r[$locTitle] = '';
	             }

	             if (isset($r[$hospTitle])) {
	             	$r[$hospTitle] = '';
	             }

	             if (isset($r['Association'])) {
	                 $r['Association'] = '';
	             }
	         }

	         $rows[] = $r;

	     } else {

	         $flds = array();

	         foreach ($r as $key => $col) {
	             $flds[] = $col;
	         }

	         $row = $writer->convertStrings($hdr, $flds);
	         $writer->writeSheetRow("Sheet1", $row);
	     }
 	}

    if ($local) {

        $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblroom', $separatorClassIndicator);

        return array('table'=>$dataTable, 'rows'=>$rowCount, 'psgs'=>$numberPSGs);

    } else {
        $uS = Session::getInstance();
        HouseLog::logDownload($dbh, 'PSG Report', "Excel", "PSG Report for " . $start . " - " . $end . " downloaded", $uS->username);
        $writer->download();
    }

}

function getNoReturn(\PDO $dbh, $local){


    $query = "SELECT N.idName AS `Id`, N.Name_First AS `First Name`, N.Name_Last AS `Last Name`, NRT.Description AS `No Return Reason` FROM `name` N
    JOIN `name_demog` ND on N.idName = ND.idName
    LEFT JOIN `gen_lookups` NRT on ND.`No_Return` = NRT.`Code` AND NRT.`Table_Name` = 'NoReturnReason'
    WHERE ND.`No_Return` != '';";

    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if($local){

        foreach($rows as $key=>$row){
            $rows[$key]["Id"] = HTMLContainer::generateMarkup('a', $row['Id'], array('href'=>'GuestEdit.php?id=' . $row['Id']));
        }

        $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
        return $dataTable;

    }else{
        $file = 'NoReturnPeopleReport';
        $writer = new ExcelHelper($file);
        $writer->setTitle("No Return People");

        $firstRow = true;
        $reportRows = 1;

        foreach($rows as $key=>$row){

            if ($firstRow) {

                $firstRow = FALSE;

                // build header
                $hdr = array();
                $colWidths = array();

                // Header row
                $keys = array_keys($row);
                foreach ($keys as $k) {
                    $hdr[$k] = "string";
                }

                $colWidths = ["10", "20", "20", "20"];

                $hdrStyle = $writer->getHdrStyle($colWidths);
                $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
            }

            $flds = array_values($row);
            $row = $writer->convertStrings($hdr, $flds);

            $writer->writeSheetRow("Sheet1", $row);
        }
        $uS = Session::getInstance();
        HouseLog::logDownload($dbh, 'No-Return Guest Report', "Excel", "No-Return Guest Report downloaded", $uS->username);
        $writer->download();

    }
}

function getIncidentsReport(\PDO $dbh, $local, $irSelection) {

	$whStatus = array(
			0=>'',
			1=>'',
			2=>'',
	        3=>'',
	);

	$ctr = 0;

	foreach ($irSelection as $s) {
		$whStatus[$ctr] = $s;
		$ctr++;
	}

	$stmt = $dbh->query("CALL incidents_report('" . $whStatus[0] . "','" . $whStatus[1] . "','" . $whStatus[2]. "','" . $whStatus[3]. "')");
	$nested = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$stmt->nextRowset();


	if($local){

		$tbl = new HTMLTable();
		$ctr = 0;
		$psgId = 0;

		foreach ($nested as $r) {

			if ($ctr != $r['count_idPsg']) {

				if ($r['count_idPsg'] == 1) {
					$txt = ' report each';
				} else {
					$txt = ' reports each';
				}
				$tbl->addBodyTr(
						HTMLTable::makeTh($r['count_idPsg'] . $txt, array('colspan'=>'10', 'style'=>'text-align:left;')));
				$ctr = $r['count_idPsg'];
				$psgId = 0;
			}

			if ($psgId != $r['Psg_Id']) {

				$tbl->addBodyTr(
						HTMLTable::makeTh(HTMLContainer::generateMarkup('a', $r['Psg_Id'], array('href'=>'GuestEdit.php?id=' . $r['idName'] . '&psg='.$r['Psg_Id'] . '&tab=8')), array('rowspan'=>$ctr + 1))
				);

				$psgId = $r['Psg_Id'];
			}

			$tbl->addBodyTr(
					HTMLTable::makeTd($r['Name_Full'])
					.HTMLTable::makeTd($r['Status'])
					.HTMLTable::makeTd($r['Title'])
					.HTMLTable::makeTd(date('M j, Y', strtotime($r['Report_Date'])))
					.HTMLTable::makeTd(date($r['Resolution_Date'] == '' ? '' : 'M j, Y', strtotime($r['Resolution_Date'])))
			);

		}

		$tbl->addHeaderTr(HTMLTable::makeTh('Psg Id'). HTMLTable::makeTh(Labels::getString('memberType', 'patient', 'Patient') . ' Name'). HTMLTable::makeTh('Status'). HTMLTable::makeTh('Title'). HTMLTable::makeTh('Report Date'). HTMLTable::makeTh('Resolution Date'));

		$dataTable = $tbl->generateMarkup(array('id'=>'tblrpt'));
		return $dataTable;

	}else{

		$file = 'Incidents_Report';
		$writer = new ExcelHelper($file);
		$writer->setTitle("Incidents Report");

		$firstRow = true;

		foreach($nested as $key=>$row){

			if ($firstRow) {

				$firstRow = FALSE;

				// build header
				$hdr = array();
				$colWidths = array();

				// Header row
				$keys = array_keys($row);
				foreach ($keys as $k) {
					$hdr[$k] =  "string";
				}

				$colWidths = ["10", "20", "15", "20", "15", "15"];

				$hdrStyle = $writer->getHdrStyle($colWidths);

				$writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
			}

			$flds = array_values($row);

			$row = $writer->convertStrings($hdr, $flds);

			$writer->writeSheetRow("Sheet1", $row);
		}

        $uS = Session::getInstance();
        HouseLog::logDownload($dbh, 'Incidents Report', "Excel", "Incidents Report downloaded", $uS->username);
		$writer->download();
	}

}

$assocSelections = array();
$hospitalSelections = array();
$stateSelection = '';
$countrySelection = '';
$countySelection = '';
$diagSelections = array();
$locSelections = array();
$statusSelections = array();
$showAddressSelection = '';
$showFullNameSelection = '';
$showNoReturnSelection = '';
$showUniqueSelection = '';
$mkTable = '';
$dataTable = '';
$settingstable = '';
$rptSetting = 'psg';
$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$calSelection = '19';
$irSelection = array('0'=>'a', '1'=>'r', '2'=>'h');

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();

$incidentStatuses = Common::readGenLookupsPDO($dbh, 'Incident_Status', 'Order');


// Diagnosis
$diags = Common::readGenLookupsPDO($dbh, 'Diagnosis', 'Description');
$diagCats = Common::readGenLookupsPDO($dbh, 'Diagnosis_Category', 'Description');
//prepare diag categories for doOptionsMkup
foreach($diags as $key=>$diag){
    if(!empty($diag['Substitute'])){
        $diags[$key][2] = $diagCats[$diag['Substitute']][1];
        $diags[$key][1] = $diagCats[$diag['Substitute']][1] . ": " . $diags[$key][1];
    }
}

$locs = Common::readGenLookupsPDO($dbh, 'Location', 'Description');

if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // gather input

    if (isset($_POST['selIrStat'])) {
    	$reqs = $_POST['selIrStat'];
    	if (is_array($reqs)) {
    		$irSelection = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    	}
    }


    if (isset($_POST['selResvStatus'])) {
        $statusSelections = filter_var_array($_POST['selResvStatus'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    if (isset($_POST['adrcountry'])) {
        $countrySelection = filter_Var($_POST['adrcountry'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    if (isset($_POST['adrstate']) && $countrySelection) {
        $stateSelection = filter_Var($_POST['adrstate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    if (isset($_POST['adrCounty']) && $stateSelection) {
        $countySelection = filter_Var($_POST['adrCounty'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    if (isset($_POST['selDiag'])) {

        $reqs = $_POST['selDiag'];

        if (is_array($reqs)) {
            $diagSelections = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    if (isset($_POST['selLoc'])) {

        $reqs = $_POST['selLoc'];

        if (is_array($reqs)) {
            $locSelections = filter_var_array($reqs, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();
    $filter->loadSelectedResourceGroups();

    $start = $filter->getReportStart();
    $end = $filter->getReportEnd();

    // Hospitals
    $whHosp = '';
    $tdHosp = $filter->getSelectedHospitalsString();
    foreach ($filter->getSelectedHosptials() as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
            } else {
                $whHosp .= ",". $a;
            }
        }
    }

    $whAssoc = '';
    $tdAssoc = $filter->getSelectedAssocString();
    foreach ($filter->getSelectedAssocs() as $a) {

        if ($a != '') {

            if ($whAssoc == '') {
                $whAssoc .= $a;
            } else {
                $whAssoc .= ",". $a;
            }
        }

    }

    if ($whHosp != '') {
        $whHosp = " and hs.idHospital in (".$whHosp.") ";
    }

    if ($whAssoc != '') {
        $whAssoc = " and hs.idAssociation in (".$whAssoc.") ";
    }

    $whDiags = '';
    $tdDiags = '';

    foreach ($diagSelections as $a) {
        if ($a != '') {
            if ($whDiags == '') {
                $whDiags .= "'" . $a . "'";
                $tdDiags .= $diags[$a][1];
            } else {
                $whDiags .= ",'". $a . "'";
                $tdDiags .= ', ' . $diags[$a][1];
            }
        }
    }

    if ($whDiags != '') {
        $whDiags = " and hs.Diagnosis in (".$whDiags.") ";
    } else {
        $tdDiags = 'All';
    }

    $whLocs = '';
    $tdLocs = '';

    foreach ($locSelections as $a) {
        if ($a != '') {
            if ($whLocs == '') {
                $whLocs .= "'" . $a . "'";
                $tdLocs .= $locs[$a][1];
            } else {
                $whLocs .= ",'". $a . "'";
                $tdLocs .= ', ' . $locs[$a][1];
            }
        }
    }

    if ($whLocs != '') {
        $whDiags .= " and hs.Location in (".$whLocs.") ";
    } else {
        $tdLocs = 'All';
    }


    $whCountry = '';
    $tdState = $stateSelection;
    $tdCounty = $countySelection;

    if ($countySelection != '') {
        $whCountry .= " and vn.County = '$countySelection' ";
    }else{
        $tdCounty = 'All';
    }

    if ($stateSelection != '') {
        $whCountry .= " and vn.State = '$stateSelection' ";
    } else {
        $tdState = 'All';
    }

    $tdCountry = $countrySelection;

    if ($countrySelection != '') {

        if ($countrySelection == 'US') {
            $whCountry .= " and (vn.Country = '$countrySelection' or vn.Country = '')  ";
        } else {
            $whCountry .= " and vn.Country = '$countrySelection' ";
        }
    } else {
        $tdCountry = 'All';
    }

    // Visit status selections
    $whStatus = '';
    $tdStatus = '';

    foreach ($statusSelections as $s) {
        if ($s != '') {
            if ($whStatus == '') {
                $whStatus = "'" . $s . "'";
                $tdStatus .= $uS->guestLookups['Visit_Status'][$s][1];
            } else {
                $whStatus .= ",'".$s . "'";
                $tdStatus .= ', ' . $uS->guestLookups['Visit_Status'][$s][1];
            }
        }
    }
    if ($whStatus != '') {
        $whStatus = " and v.Status in (" . $whStatus . ") ";
    } else {
        $tdStatus = 'All';
    }



    if (isset($_POST['rbReport'])) {

        $showAddr = FALSE;
        $showFullName = FALSE;
        $showNoReturn = FALSE;
        $showDiag = TRUE;
        $showLocation = FALSE;
        $showUnique = FALSE;

        if (count($diags) == 0) {
            $showDiag = FALSE;
        }

        if (count($locs) > 0) {
            $showLocation = TRUE;
        }

        if (isset($_POST['cbAddr'])) {
            $showAddr = TRUE;
            $showAddressSelection = 'checked="checked"';
        }
        if (isset($_POST['cbFullName'])) {
            $showFullName = TRUE;
            $showFullNameSelection = 'checked="checked"';
        }

        if (isset($_POST['cbNoReturn'])) {
            $showNoReturn = TRUE;
            $showNoReturnSelection = 'checked="checked"';
        }

        if (isset($_POST['cbUnique'])) {
            $showUnique = TRUE;
            $showUniqueSelection = 'checked="checked"';
        }


        // Create settings markup
        $sTbl = new HTMLTable();


        $whPeople = $whHosp . $whCountry . $whDiags . $whStatus;

        $rptSetting = filter_var($_POST['rbReport'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $showAssoc = FALSE;
        if (count($filter->getAList()) > 0) {
            $showAssoc = TRUE;
        }

        $patTitle = $labels->getString('MemberType', 'patient', 'Patient');
        $mkTable = 1;

        switch ($rptSetting) {

        	case 'psg':
        	    $rptArry = getPsgReport($dbh, $local, $whHosp . $whDiags, $start, $filter->getQueryEnd(), $uS->guestLookups['Patient_Rel_Type'], $uS->guestLookups[GLTableNames::Hospital], $labels, $showAssoc, $showDiag, $uS->ShowDiagTB, $showLocation, $uS->ShowBirthDate, $uS->PatientAsGuest, $uS->county);
                $dataTable = $rptArry['table'];
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' ' . $labels->getString('statement', 'psgLabel', 'PSG') . ' Report', array('colspan'=>'4')));
                $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
                $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdHosp) . ($showAssoc ? HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdAssoc) : ''));
                if ($showDiag) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'diagnosis', 'Diagnoses'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdDiags, array('colspan'=>'3')));
                }
                if ($showLocation) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'location', 'Locations'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdLocs, array('colspan'=>'3')));
                }

                $sTbl->addBodyTr(HTMLTable::makeTd('Rows Returned', array('class'=>'tdlabel')) . HTMLTable::makeTd($rptArry['rows'], array('colspan'=>'3')));
                $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('statement', 'psgAbrev', 'PSG')." count", array('class'=>'tdlabel')) . HTMLTable::makeTd($rptArry['psgs'], array('colspan'=>'3')));

                $settingstable = $sTbl->generateMarkup();
                break;


            case 'p':
                $rptArry = getPeopleReport($dbh, $local, FALSE, $whPeople . " and s.idName = hs.idPatient ", $start, $filter->getQueryEnd(), $showAddr, $showFullName, $showNoReturn, $showUnique, $showAssoc, $labels, $showDiag, $showLocation);
                $dataTable = $rptArry['table'];
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' Just '.$patTitle, array('colspan'=>'4')));
                $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
                $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdHosp) . ($showAssoc ? HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdAssoc) : ''));
                if ($showDiag) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'diagnosis', 'Diagnoses'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdDiags, array('colspan'=>'3')));
                }
                if ($showLocation) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'location', 'Locations'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdLocs, array('colspan'=>'3')));
                }
                $sTbl->addBodyTr(HTMLTable::makeTd('State/Province', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdState) . HTMLTable::makeTd('Country', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdCountry));
                if($uS->county){
                    $sTbl->addBodyTr(HTMLTable::makeTd('County', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdCounty, array('colspan'=>'3')));
                }
                if($showAddr){
                    $sTbl->addBodyTr(HTMLTable::makeTd('Distance Traveled', array('class'=>'tdlabel')) . HTMLTable::makeTd($rptArry["TotalDistance"] . " miles", array('colspan'=>'3')));
                }
                $sTbl->addBodyTr(HTMLTable::makeTd('Visit Status', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdStatus, array('colspan'=>'3')));
                $settingstable = $sTbl->generateMarkup();
                break;

            case 'g':
                $rptArry = getPeopleReport($dbh, $local, TRUE, $whPeople, $start, $filter->getQueryEnd(), $showAddr, $showFullName, $showNoReturn, $showUnique, $showAssoc, $labels, $showDiag, $showLocation);
                $dataTable = $rptArry['table'];
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' ' . $patTitle.' & '.$labels->getString('MemberType', 'guest', 'Guest').'s', array('colspan'=>'4')));
                $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
                $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdHosp) . ($showAssoc ? HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdAssoc) : ''));
                if ($showDiag) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'diagnosis', 'Diagnoses'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdDiags, array('colspan'=>'3')));
                }
                if ($showLocation) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'location', 'Locations'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdLocs, array('colspan'=>'3')));
                }
                $sTbl->addBodyTr(HTMLTable::makeTd('State/Province', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdState) . HTMLTable::makeTd('Country', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdCountry));
                if($showAddr){
                    $sTbl->addBodyTr(HTMLTable::makeTd('Distance Traveled', array('class'=>'tdlabel')) . HTMLTable::makeTd($rptArry["TotalDistance"] . " miles", array('colspan'=>'3')));
                }
                $sTbl->addBodyTr(HTMLTable::makeTd('Visit Status', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdStatus, array('colspan'=>'3')));
                $settingstable = $sTbl->generateMarkup();
                break;

            case 'nr':
            	$dataTable = getNoReturn($dbh, $local);
            	$sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' No Return People', array('colspan'=>'4')));
            	$settingstable = $sTbl->generateMarkup();
            	break;

            case 'in':
            	$dataTable = getIncidentsReport($dbh, $local, $irSelection);
            	$sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' Incidents Report', array('colspan'=>'4')));
            	$settingstable = '';
            	$mkTable = 2;
            	break;

        }


    }


}

// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup(array('style'=>'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));

// Visit status
$statusList = HTMLSelector::removeOptionGroups($uS->guestLookups['Visit_Status']);

// remove unused visit statuses
unset($statusList['p']);
unset($statusList['c']);

$statusSelector = HTMLSelector::generateMarkup(
    HTMLSelector::doOptionsMkup($statusList, $statusSelections), array('name' => 'selResvStatus[]', 'size'=>count($statusList) + 1, 'multiple'=>'multiple'));

$selDiag = '';
if (count($diags) > 0) {

    $selDiag = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($diags, $diagSelections, TRUE),
        array('name'=>'selDiag[]', 'multiple'=>'multiple', 'size'=>min(count($diags)+1, 13)));
}

$selLoc = '';
if (count($locs) > 0) {

    $selLoc = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($locs, $locSelections, TRUE),
        array('name'=>'selLoc[]', 'multiple'=>'multiple', 'size'=>min(count($locs)+1, 13)));
}

// State
$stAttr = array();
$stAttr['id'] = 'adrstate';
$stAttr['name'] = 'adrstate';
$stAttr['title'] = 'Select State or Province';
$stAttr["class"] = "input-medium bfh-states psgsel";
$stAttr['data-country'] = 'adrcountry';
$stAttr['data-state'] = $stateSelection;

$selState = HTMLSelector::generateMarkup('', $stAttr);

// Country
$coAttr['id'] = 'adrcountry';
$coAttr['name'] = 'adrcountry';
$coAttr['title'] = 'Select Country';
$coAttr['class'] = 'input-medium bfh-countries psgsel';
$coAttr['data-country'] = $countrySelection;

$selCountry = HTMLSelector::generateMarkup('', $coAttr);

//county
$countyAttr = array();
$countyAttr['id'] = 'adrcounty';
$countyAttr['name'] = 'adrCounty';
$countyAttr['title'] = 'Select County';
$countyAttr["class"] = "input-medium bfh-county psgsel";
$countyAttr['data-country'] = 'adrcountry';
$countyAttr['data-state'] = 'adrstate';
$countyAttr['data-county'] = $countySelection;

$selCounty = HTMLSelector::generateMarkup('<option></option>', $countyAttr);


// incidents report
$selirStat = '';
if ($uS->UseIncidentReports) {

	$selirStat = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($incidentStatuses, $irSelection, FALSE), array('name' => 'selIrStat[]', 'size'=>'4', 'multiple'=>'multiple'));
}


// $dateFormat = $labels->getString("momentFormats", "report", "MMM D, YYYY");

// if ($uS->CoTod) {
//     $dateFormat .= ' H:mm';
// }


?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo FAVICON; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
<script type="text/javascript">
    $(document).ready(function() {

        var makeTable = '<?php echo $mkTable; ?>';
        $('#btnHere, #btnExcel').button();
        if (makeTable >= 1) {
            $('div#printArea').addClass("d-block");
            $('#divPrintButton').show();

            if (makeTable == 1) {
                try{
                $('#tblrpt').dataTable({
	                "displayLength": 50,
	                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
	                "dom": "<\"top ui-toolbar ui-helper-clearfix\"if><\"hhk-overflow-x\"rt><\"bottom ui-toolbar ui-helper-clearfix\"lp>",
	                "order": [[1, 'asc']]
            	});
                } catch (error) {}
            }
            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
        }

        <?php echo $filter->getTimePeriodScript(); ?>;

        $('input[name="rbReport"]').change(function () {
        	$('.hhk-IncdtRpt').hide();
            if ($('#rbpsg').prop('checked')) {
                $('.psgsel').hide();
                $('.filters').show();
                $('.checkboxesShow').hide();
                $('.showStateCountry').hide();
            } else if($('#nrp').prop('checked')) {
                $('.filters').hide();
                $('.checkboxesShow').hide();
                $('.showStateCountry').hide();
            } else if($('#incdt').prop('checked')) {
                $('.filters').hide();
                $('.hhk-IncdtRpt').show();
                $('.checkboxesShow').hide();
                $('.showStateCountry').hide();
            } else {
                $('.filters').show();
                $('.psgsel').show();
                $('.checkboxesShow').show();
                $('.showStateCountry').show();
        }
        });

        $(document).on('change', '#cbUnique', function(){
        	if($('#cbUnique').prop('checked')){
        		$('#visitStatusFilter select').val('');
        		$('#visitStatusFilter').hide();
        	}else{
        		$('#visitStatusFilter').show();
        	}
        });

        $('#cbUnique').change();
        $('input[name="rbReport"]').change();
        $('#adrstate').change();

    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        	<div class="title mb-3">
            	<h2 class="d-inline-block"><?php echo $wInit->pageHeading; ?></h2><span class="ml-3">This report shows people who stayed in the time frame selected below</span>
            </div>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog p-2" style="font-size:0.9em; max-width:fit-content;">
                <form id="fcat" action="PSGReport.php" method="post">
                    <fieldset class="hhk-panel mb-3"><legend style='font-weight:bold;'>Report Type</legend>
                     <table style="width:100%">
                        <tr>
                            <th><label for='rbpsg'><?php echo $labels->getString('guestEdit', 'psgTab', 'Patient Support Group'); ?></label><input type="radio" id='rbpsg' name="rbReport" value="psg" style='margin-left:.5em;' <?php if ($rptSetting == 'psg') {echo 'checked="checked"';} ?>/></th>
                            <?php if ($uS->PatientAsGuest) { ?><th><label for='rbp'>Just <?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>s</label><input type="radio" id='rbp' name="rbReport" value="p" style='margin-left:.5em;' <?php if ($rptSetting == 'p') {echo 'checked="checked"';} ?>/></th><?php } ?>
                            <th><label for='rbg'><?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?>s &amp; <?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>s</label><input type="radio" id='rbg' name="rbReport" value="g" style='margin-left:.5em;' <?php if ($rptSetting == 'g') {echo 'checked="checked"';} ?>/></th>
                            <th><label for='nrp'>No-Return <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s</label><input type="radio" id='nrp' name="rbReport" value="nr" style='margin-left:.5em;' <?php if ($rptSetting == 'nr') {echo 'checked="checked"';} ?>/></th>
                            <?php if ($uS->UseIncidentReports) { ?><th><label for='incdt'>Incidents Report</label><input type="radio" id='incdt' name="rbReport" value="in" style='margin-left:.5em;' <?php if ($rptSetting == 'in') {echo 'checked="checked"';} ?>/></th><?php } ?>
                        </tr>
                    </table>
                    </fieldset>
                    <table class="hhk-IncdtRpt" style="display:none;">
                        <tr>
                            <th > Incident Reports Status</th>
                        </tr>
                        <tr>
                        	<td><?php echo $selirStat; ?></td>
                        </tr>
                    </table>
                    <div class="filters hhk-flex hhk-flex-wrap">
                    <?php
                        echo $timePeriodMarkup;

                        if (count($filter->getHospitals()) > 1) {
                            echo $hospitalMarkup;
                        }
                    ?>
                    <?php if ($selDiag != '') { ?>
                    <table style="margin-left:5px;">
                        <tr>
                            <th><?php echo $labels->getString('hospital', 'diagnosis', 'Diagnosis') ?></th>
                        </tr>
                        <tr>
                            <td><?php echo $selDiag; ?></td>
                        </tr>
                    </table>
                    <?php } if ($selLoc != '') { ?>
                    <table style="margin-left: 5px;">
                        <tr>
                            <th><?php echo $labels->getString('hospital', 'location', 'Location') ?></th>
                        </tr>
                        <tr>
                            <td><?php echo $selLoc; ?></td>
                        </tr>
                    </table>
                    <?php } ?>
                    <table style="margin-left: 5px;" class="psgsel" id="visitStatusFilter">
                        <tr>
                            <th>Visit Status</th>
                        </tr>
                        <tr>
                            <td><?php echo $statusSelector; ?></td>
                        </tr>
                    </table>
                    </div>
					<div class="hhk-flex">
                    <table style="display:none;" class="showStateCountry mt-3">
                        <tr>
                            <th>Country</th>
                            <th>State</th>
                            <?php
                                if($uS->county) {
                                    echo "<th>County</th>";
                                }
                            ?>
                        </tr>
                        <tr>
                            <td><?php echo $selCountry; ?></td>
                            <td><?php echo $selState; ?></td>
                            <?php
                                if($uS->county) {
                                    echo "<td>" . $selCounty . "</td>";
                                }
                            ?>
                        </tr>
                    </table>
                    </div>
                    <table class="mt-3" style="width:100%;">
                        <tr>
                            <td class="checkboxesShow"><input type="checkbox" name="cbAddr" class="psgsel" id="cbAddr" <?php echo $showAddressSelection; ?>/><label for="cbAddr" class="psgsel"> Show Address</label></td>
                            <td class="checkboxesShow"><input type="checkbox" name="cbFullName" class="psgsel" id="cbFullName" <?php echo $showFullNameSelection; ?>/><label for="cbFullName" class="psgsel"> Show Full Name</label></td>
                            <td class="checkboxesShow" id="cbNoRtntd"><input type="checkbox" name="cbNoReturn" class="psgsel" id="cbNoReturn" <?php echo $showNoReturnSelection; ?>/><label for="cbNoReturn" class="psgsel"> Show No Return Only</label></td>
                            <td class="checkboxesShow"><input type="checkbox" name="cbUnique" class="psgsel" id="cbUnique" <?php echo $showUniqueSelection; ?>/><label for="cbUnique" class="psgsel"> Show Unique People</label></td>
                            <td style="text-align: right;"><input type="submit" name="btnHere" id="btnHere" value="Run Here"/>
                                <input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div id="divPrintButton" class="my-3" style="display:none;"><input id="printButton" value="Print" type="button" /></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox hhk-visitdialog ui-corner-all p-2 pb-3 hhk-overflow-x" style="display:none; font-size: .8em; max-width:fit-content">
                <div class="mb-2"><?php echo $settingstable; ?></div>
                <form autocomplete="off">
                <?php echo $dataTable; ?>
                </form>
            </div>
        </div>
    </body>
</html>
