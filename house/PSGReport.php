<?php
/**
 * PSG_Report.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'nameRS.php');
require CLASSES . 'CreateMarkupFromDB.php';
require CLASSES . 'OpenXML.php';

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

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();

$labels = new Config_Lite(LABEL_FILE);



function getPeopleReport(\PDO $dbh, $local, $showRelationship, $whClause, $start, $end, $showAddr, $showFullName, $showNoReturn, $showAssoc, \Config_Lite $labels, $showDiagnosis, $showLocation) {
    
    $uS = Session::getInstance();
    
    $query = '';
    $agentTitle = $labels->getString('hospital', 'referralAgent', 'Referral Agent');
    $diagTitle = $labels->getString('hospital', 'diagnosis', 'Diagnosis');
    $locTitle = $labels->getString('hospital', 'location', 'Location');
    $patTitle = $labels->getString('MemberType', 'patient', 'Patient');
    
    
    if ($showAddr && $showFullName) {
        
        $query = "select s.idName, hs.idPsg, vg.Relationship_Code, v.idReservation as `Resv ID`, "
            . "vg.`Patient Rel.`, vg.Prefix, vg.First as `Guest First`, vg.Last as `Guest Last`, vg.Suffix, ifnull(vg.BirthDate, '') as `Birth Date`, "
                . "np.Name_First as `$patTitle First` , np.Name_Last as `$patTitle Last`, "
                . " vg.Address, vg.City, vg.County, vg.State, vg.Zip, vg.Country, vg.Phone, vg.Email, "
                    . "r.title as `Room`,"
                        . " ifnull(s.Span_Start_Date, '') as `Arrival`, ifnull(s.Span_End_Date, '') as `Departure`, "
                            . " ifnull(rr.Title, '') as `Rate Category`, 0 as `Total Cost`, "
                                . "hs.idHospital, hs.idAssociation, "
                                    . "  ifnull(g.Description, hs.Diagnosis) as `$diagTitle`, ifnull(gl.Description, '') as `$locTitle`, "
                                    . " ifnull(n.Name_Full, '') as `Doctor`, ifnull(nr.Name_Full, '') as `$agentTitle`, ifnull(g2.Description,'') as `Status`";
                                    
    } else if ($showAddr && !$showFullName) {
        
        $query = "select s.idName, hs.idPsg, vg.Relationship_Code,
            vg.Last as `Guest Last`, vg.First as `Guest First`, ifnull(vg.BirthDate, '') as `Birth Date`, vg.`Patient Rel.`, vg.Phone, vg.Email, vg.`Address`, vg.City, vg.County, vg.State, vg.Zip, case when vg.Country = '' then 'US' else vg.Country end as Country, vg.ngStatus, "
            . "ifnull(g2.Description,'') as `Status`, "
                . "r.title as `Room`,"
                    . " ifnull(s.Span_Start_Date, '') as `Arrival`, ifnull(s.Span_End_Date, '') as `Departure`, "
                        . "hs.idHospital, hs.idAssociation, "
                            . "np.Name_Last as `$patTitle Last`, np.Name_First as `$patTitle First`, "
                            ." ifnull(g.Description, hs.Diagnosis) as `$diagTitle`, ifnull(gl.Description, '') as `$locTitle`, "
                            . " ifnull(n.Name_Full, '') as `Doctor`, ifnull(nr.Name_Full, '') as `$agentTitle` ";
                            
    } else if (!$showAddr && $showFullName) {
        
        $query = "select s.idName, hs.idPsg, vg.Relationship_Code, vg.Prefix, vg.First as `Guest First`, vg.Middle, vg.Last as `Guest Last`, vg.Suffix, ifnull(vg.BirthDate, '') as `Birth Date`, vg.`Patient Rel.`, vg.ngStatus, "
            . "ifnull(g2.Description,'') as `Status`, "
                . "r.title as `Room`,"
                    . " ifnull(s.Span_Start_Date, '') as `Arrival`, ifnull(s.Span_End_Date, '') as `Departure`, "
                        . "np.Name_Last as `$patTitle Last`, np.Name_First as `$patTitle First` , "
                        . " ifnull(g.Description, hs.Diagnosis) as `$diagTitle`, ifnull(gl.Description, '') as `$locTitle`, "
                        . "hs.idHospital, hs.idAssociation,
          ifnull(n.Name_Full, '') as `Doctor`, ifnull(nr.Name_Full, '') as `$agentTitle` ";
                        
    } else {
        
        $query = "select s.idName, hs.idPsg, vg.Relationship_Code, vg.Last as `Guest Last`, vg.First as `Guest First`, ifnull(vg.BirthDate, '') as `Birth Date`, vg.`Patient Rel.`, vg.ngStatus, "
            . "ifnull(g2.Description,'') as `Status`, r.title as `Room`, ifnull(s.Span_Start_Date, '') as `Arrival`, ifnull(s.Span_End_Date, '') as `Departure`, "
                . "np.Name_Last as `$patTitle Last`, np.Name_First as `$patTitle First`, "
                . " ifnull(g.Description, hs.Diagnosis) as `$diagTitle`, ifnull(gl.Description, '') as `$locTitle`, "
                . "hs.idHospital, hs.idAssociation, "
                    . " ifnull(n.Name_Full, '') as `Doctor`, ifnull(nr.Name_Full, '') as `$agentTitle` ";
    }
    
    if ($showNoReturn) {
        $whClause .= " and vg.ngStatus != '' ";
    }
    
    $query .= " from stays s
        join
    vguest_listing vg on vg.Id = s.idName
        join
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
	join
    hospital_stay hs ON vg.idPsg = hs.idPsg
        left join
    name np on hs.idPatient = np.idName
        left join
    name n on hs.idDoctor = n.idName
        left join
    name nr on hs.idReferralAgent = nr.idName
        left join
    gen_lookups g on g.Table_Name = 'Diagnosis' and g.Code = hs.Diagnosis
        left join
    gen_lookups gl on gl.Table_Name = 'Location' and gl.Code = hs.Location
        left join
    gen_lookups g2 on g2.Code = s.Status and g2.Table_Name = 'Visit_Status'
        left join
    room_rate rr on v.idRoom_rate = rr.idRoom_rate
    	join
    room r on s.idRoom = r.idRoom
where  DATE(ifnull(s.Span_End_Date, now())) > DATE('$start') and DATE(s.Span_Start_Date) < DATE('$end') and DATEDIFF(DATE(ifnull(s.Span_End_Date, now())), DATE(s.Span_Start_Date)) > 0 $whClause";
    
    $stmt = $dbh->query($query);
    
    if (!$local) {
        
        $reportRows = 1;
        $file = 'PeopleReport';
        $sml = OpenXML::createExcel('Guest Tracker', 'People Report');
    }
    
    $rows = array();
    $firstRow = TRUE;
    
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        
        if ($uS->county === FALSE) {
            unset($r['County']);
        }
        
        if (!$uS->ShowBirthDate) {
            unset($r['Birth Date']);
        }
        
        if (!$showNoReturn) {
            unset($r['ngStatus']);
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
        
        if ($showAssoc === FALSE) {
            $r['idAssociation'] = 0;
        } else {
            $r['Association'] = '';
        }
        
        // Hospital
        $r[$labels->getString('hospital', 'hospital', 'Hospital')] = '';
        
        
        if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] != '(None)') {
            $r['Association'] = $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1];
        }
        if ($r['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']])) {
            $r[$labels->getString('hospital', 'hospital', 'Hospital')] = $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
        }
        
        if (count($uS->guestLookups[GL_TableNames::Hospital]) < 2) {
            unset($r[$labels->getString('hospital', 'hospital', 'Hospital')]);
        }
        
        unset($r['idHospital']);
        unset($r['idAssociation']);
        
        
        if ($firstRow) {
            
            $firstRow = FALSE;
            
            if ($local === FALSE) {
                
                // build header
                $hdr = array();
                $n = 0;
                $noReturn = '';
                
                // HEader row
                $keys = array_keys($r);
                foreach ($keys as $k) {
                    
                    if ($k == 'ngStatus') {
                        $noReturn = 'No Return';
                        continue;
                    }
                    
                    $hdr[$n++] =  $k;
                }
                
                if ($noReturn != '') {
                    $hdr[$n++] =  $noReturn;
                }
                
                OpenXML::writeHeaderRow($sml, $hdr);
                $reportRows++;
            }
        }
        
        if ($local) {
            
            $r['Id'] = HTMLContainer::generateMarkup('a', $r['idName'], array('href'=>'GuestEdit.php?id=' . $r['idName'] . '&psg=' . $r['idPsg']));
            
            if (isset($r['Birth Date']) && $r['Birth Date'] != '') {
                $r['Birth Date'] = date('n/d/Y', strtotime($r['Birth Date']));
            }
            if ($r['Arrival'] != '') {
                $r['Arrival'] = date('n/d/Y', strtotime($r['Arrival']));
            }
            if ($r['Departure'] != '') {
                $r['Departure'] = date('n/d/Y', strtotime($r['Departure']));
            }
            unset($r['idPsg']);
            
            if (isset($r['ngStatus'])) {
                
                if ($r['ngStatus'] != '' && isset($uS->nameLookups['NoReturnReason'][$r['ngStatus']])) {
                    $r['No Return'] = $uS->nameLookups['NoReturnReason'][$r['ngStatus']][1];
                } else {
                    $r['No Return'] = ' ';
                }
                
                unset($r['ngStatus']);
            }
            
            $rows[] = $r;
            
        } else {
            
            $n = 0;
            $flds = array();
            
            if (isset($r['ngStatus'])) {
                
                if ($r['ngStatus'] != '' && isset($uS->nameLookups['NoReturnReason'][$r['ngStatus']])) {
                    $r['No Return'] = $uS->nameLookups['NoReturnReason'][$r['ngStatus']][1];
                } else {
                    $r['No Return'] = '';
                }
                
                unset($r['ngStatus']);
            }
            
            foreach ($r as $key => $col) {
                
                if (($key == 'Arrival' or $key == 'Departure' || $key == 'Birth Date') && $col != '') {
                    
                    $flds[$n++] = array('type' => "n",
                        'value' => PHPExcel_Shared_Date::PHPToExcel(new DateTime($col)),
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14);
                    
                } else {
                    $flds[$n++] = array('type' => "s", 'value' => $col);
                }
            }
            
            $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
        }
        
    }
    
    if ($local) {
        
        $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
        return $dataTable;
        
        
    } else {
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        OpenXML::finalizeExcel($sml);
        exit();
        
    }
}

function getPsgReport(\PDO $dbh, $local, $whHosp, $start, $end, $relCodes, $hospCodes, \Config_Lite $labels, $showAssoc, $showDiagnosis, $showLocation, $patBirthDate, $patAsGuest = true) {
    
    $diagTitle = $labels->getString('hospital', 'diagnosis', 'Diagnosis');
    $locTitle = $labels->getString('hospital', 'location', 'Location');
    $psgLabel = $labels->getString('statement', 'psgAbrev', 'PSG') . ' Id';
    $patRelTitle = $labels->getString('MemberType', 'patient', 'Patient') . " Relationship";
    
    $query = "Select DISTINCT
    ng.idPsg as `$psgLabel`,
    ifnull(ng.idName, 0) as `Id`,
    ifnull(n.Name_First,'') as `First`,
    ifnull(n.Name_Last,'') as `Last`,
    ifnull(na.County, '') as `County`,
    ifnull(na.State_Province, '') as `State`,
    ifnull(na.Country_Code, '') as `Country`,
    ifnull(ng.Relationship_Code,'') as `$patRelTitle`,
    ifnull(n.BirthDate, '') as `Birth Date`,
    ifnull(hs.idHospital, '') as `" . $labels->getString('hospital', 'hospital', 'Hospital') . "`,
    ifnull(hs.idAssociation, '') as `Association`,
    ifnull(g.Description, hs.Diagnosis) as `$diagTitle`,
    ifnull(g1.Description, '') as `$locTitle`
from
    name_guest ng
        join
    `name` n ON ng.idName = n.idname
        left join
    name_address na on na.idName = n.idName and na.Purpose = n.Preferred_Mail_Address
        join
    hospital_stay hs ON ng.idPsg = hs.idPsg
        left join
    gen_lookups g on g.`Table_Name` = 'Diagnosis' and g.`Code` = hs.Diagnosis
        left join
    gen_lookups g1 on g1.`Table_Name` = 'Location' and g1.`Code` = hs.Location
        join
    visit v on hs.idHospital_stay = v.idHospital_stay and ifnull(v.Span_End, now()) > '$start' and v.Span_Start < '$end'
        join
    stays s on s.idName = ng.idName and s.idVisit = v.idVisit and s.Visit_Span = v.span
where n.Member_Status != 'TBD'
 $whHosp
order by ng.idPsg";
 
 
 if (!$local) {
     
     $reportRows = 1;
     $file = $psgLabel . 'Report';
     $sml = OpenXML::createExcel('GuestTracker', 'PSG Report');
     
 }
 
 $psgId = 0;
 $rows = array();
 $firstRow = TRUE;
 $separatorClassIndicator = '))+class';
 $numberPSGs = 0;
 
 $stmt = $dbh->query($query);
 $rowCount = $stmt->rowCount();
 
 while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
     
     $relCode = $r[$patRelTitle];
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
     
     if ($r[$labels->getString('hospital', 'hospital', 'Hospital')] > 0 && isset($hospCodes[$r[$labels->getString('hospital', 'hospital', 'Hospital')]])) {
         $r[$labels->getString('hospital', 'hospital', 'Hospital')] = $hospCodes[$r[$labels->getString('hospital', 'hospital', 'Hospital')]][1];
     } else {
         $r[$labels->getString('hospital', 'hospital', 'Hospital')] = '';
     }
     
     if (count($hospCodes) < 2) {
         unset($r[$labels->getString('hospital', 'hospital', 'Hospital')]);
     }
     
     if ($showDiagnosis === FALSE) {
         unset($r[$diagTitle]);
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
             $n = 0;
             
             // HEader row
             $keys = array_keys($r);
             foreach ($keys as $k) {
                 $hdr[$n++] =  $k;
             }
             
             OpenXML::writeHeaderRow($sml, $hdr);
             $reportRows++;
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
             if (isset($r[$locTitle])) {
                 $r[$locTitle] = '';
             }
             
             if (isset($r[$labels->getString('hospital', 'hospital', 'Hospital')])) {
                 $r[$labels->getString('hospital', 'hospital', 'Hospital')] = '';
             }
             
             if (isset($r['Association'])) {
                 $r['Association'] = '';
             }
         }
         
         $rows[] = $r;
         
     } else {
         
         $n = 0;
         $flds = array();
         
         foreach ($r as $key => $col) {
             
             if (($key == 'Arrival' || $key == 'Departure' || $key == 'Birth Date') && $col != '') {
                 $flds[$n++] = array('type' => "n",
                     'value' => PHPExcel_Shared_Date::PHPToExcel(new DateTime($col)),
                     'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14);
             } else {
                 
                 $flds[$n++] = array('type' => "s", 'value' => $col);
             }
         }
         
         $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
         
     }
 }
 
 if ($local) {
     
     $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblroom', $separatorClassIndicator);
     
     return array('table'=>$dataTable, 'rows'=>$rowCount, 'psgs'=>$numberPSGs);
     
     
 } else {
     
     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
     header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
     header('Cache-Control: max-age=0');
     
     OpenXML::finalizeExcel($sml);
     exit();
     
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
        $sml = OpenXML::createExcel('Guest Tracker', 'People Report');
        $firstRow = true;
        $reportRows = 1;
        
        foreach($rows as $key=>$row){
            
            if ($firstRow) {
                
                $firstRow = FALSE;
                
                // build header
                $hdr = array();
                $n = 0;
                
                // Header row
                $keys = array_keys($row);
                foreach ($keys as $k) {
                    
                    $hdr[$n++] =  $k;
                }
                
                OpenXML::writeHeaderRow($sml, $hdr);
                $reportRows++;
            }
            
            $n = 0;
            $flds = array();
            
            foreach ($row as $key => $col) {
                $flds[$n++] = array('type' => "s", 'value' => $col);
            }
            
            $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        OpenXML::finalizeExcel($sml);
        exit();
    }
}

function getIncidentsReport(\PDO $dbh, $local, $irSelection) {

	$whStatus = array(
			0=>'',
			1=>'',
			2=>'',
	);

	$ctr = 0;

	foreach ($irSelection as $s) {
		$whStatus[$ctr] = $s;
		$ctr++;
	}

	$stmt = $dbh->query("CALL incidents_report('" . $whStatus[0] . "','" . $whStatus[1] . "','" . $whStatus[2]. "')");
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
		
		$tbl->addHeaderTr(HTMLTable::makeTh('Psg Id'). HTMLTable::makeTh('Patient Name'). HTMLTable::makeTh('Status'). HTMLTable::makeTh('Title'). HTMLTable::makeTh('Report Date'). HTMLTable::makeTh('Resolution Date'));
		
		$dataTable = $tbl->generateMarkup(array('id'=>'tblrpt'));
		return $dataTable;
		
	}else{
		
		$file = 'Incidents_Report';
		$sml = OpenXML::createExcel('Guest Tracker', 'People Report');
		$firstRow = true;
		$reportRows = 1;
		
		foreach($nested as $key=>$row){
			
			if ($firstRow) {
				
				$firstRow = FALSE;
				
				// build header
				$hdr = array();
				$n = 0;
				
				// Header row
				$keys = array_keys($row);
				foreach ($keys as $k) {
					
					$hdr[$n++] =  $k;
				}
				
				OpenXML::writeHeaderRow($sml, $hdr);
				$reportRows++;
			}
			
			$n = 0;
			$flds = array();
			
			foreach ($row as $col) {
				$flds[$n++] = array('type' => "s", 'value' => $col);
			}
			
			$reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
		}
		
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
		header('Cache-Control: max-age=0');
		
		OpenXML::finalizeExcel($sml);
		exit();
	}
	
}

$assocSelections = array();
$hospitalSelections = array();
$stateSelection = '';
$countrySelection = '';
$diagSelections = array();
$locSelections = array();
$statusSelections = array();
$showAddressSelection = '';
$showFullNameSelection = '';
$showNoReturnSelection = '';
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


$monthArray = array(
    1 => array(1, 'January'), 2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}

// Hospital and association lists
$hospList = array();
if (isset($uS->guestLookups[GL_TableNames::Hospital])) {
    $hospList = $uS->guestLookups[GL_TableNames::Hospital];
}

$hList = array();
$aList = array();
foreach ($hospList as $h) {
    if ($h[2] == 'h') {
        $hList[] = array(0=>$h[0], 1=>$h[1]);
    } else if ($h[2] == 'a' && $h[1] != '(None)') {
        $aList[] = array(0=>$h[0], 1=>$h[1]);
    }
}


$incidentStatuses = readGenLookupsPDO($dbh, 'Incident_Status', 'Order');


// Diagnozis
$diags = readGenLookupsPDO($dbh, 'Diagnosis', 'Description');
$locs = readGenLookupsPDO($dbh, 'Location', 'Description');

if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {
    
    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }
    
    // gather input
    
    if (isset($_POST['selIrStat'])) {
    	$reqs = $_POST['selIrStat'];
    	if (is_array($reqs)) {
    		$irSelection = filter_var_array($reqs, FILTER_SANITIZE_STRING);
    	}
    }
    
    
    if (isset($_POST['selResvStatus'])) {
        $statusSelections = filter_var_array($_POST['selResvStatus'], FILTER_SANITIZE_STRING);
    }
    
    if (isset($_POST['selCalendar'])) {
        $calSelection = intval(filter_var($_POST['selCalendar'], FILTER_SANITIZE_NUMBER_INT), 10);
    }
    
    if (isset($_POST['selIntMonth'])) {
        $months = filter_var_array($_POST['selIntMonth'], FILTER_SANITIZE_NUMBER_INT);
    }
    
    if (isset($_POST['selIntYear'])) {
        $year = intval(filter_var($_POST['selIntYear'], FILTER_SANITIZE_NUMBER_INT), 10);
    }
    
    if (isset($_POST['stDate'])) {
        $txtStart = filter_var($_POST['stDate'], FILTER_SANITIZE_STRING);
    }
    
    if (isset($_POST['enDate'])) {
        $txtEnd = filter_var($_POST['enDate'], FILTER_SANITIZE_STRING);
    }
    if (isset($_POST['selAssoc'])) {
        $reqs = $_POST['selAssoc'];
        if (is_array($reqs)) {
            $assocSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }
    
    if (isset($_POST['selHospital'])) {
        $reqs = $_POST['selHospital'];
        if (is_array($reqs)) {
            $hospitalSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }
    
    if (isset($_POST['adrstate'])) {
        $stateSelection = filter_Var($_POST['adrstate'], FILTER_SANITIZE_STRING);
    }
    
    if (isset($_POST['adrcountry'])) {
        $countrySelection = filter_Var($_POST['adrcountry'], FILTER_SANITIZE_STRING);
    }
    
    if (isset($_POST['selDiag'])) {
        
        $reqs = $_POST['selDiag'];
        
        if (is_array($reqs)) {
            $diagSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }
    
    if (isset($_POST['selLoc'])) {
        
        $reqs = $_POST['selLoc'];
        
        if (is_array($reqs)) {
            $locSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }
    
    if ($calSelection == 20) {
        // fiscal year
        $adjustPeriod = new DateInterval('P' . $uS->fy_diff_Months . 'M');
        $startDT = new DateTime($year . '-01-01');
        $startDT->sub($adjustPeriod);
        $start = $startDT->format('Y-m-d');
        
        $endDT = new DateTime(($year + 1) . '-01-01');
        $end = $endDT->sub($adjustPeriod)->format('Y-m-d');
        
    } else if ($calSelection == 21) {
        // Calendar year
        $startDT = new DateTime($year . '-01-01');
        $start = $startDT->format('Y-m-d');
        
        $end = ($year + 1) . '-01-01';
        
    } else if ($calSelection == 18) {
        // Dates
        if ($txtStart != '') {
            $startDT = new DateTime($txtStart);
        } else {
            $startDT = new DateTime();
        }
        
        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
        } else {
            $endDT = new DateTime();
        }
        
        $start = $startDT->format('Y-m-d');
        $end = $endDT->format('Y-m-d');
        
    } else if ($calSelection == 22) {
        // Year to date
        $start = date('Y') . '-01-01';
        
        $endDT = new DateTime();
        $endDT->add(new DateInterval('P1D'));
        $end = $endDT->format('Y-m-d');
        
    } else {
        // Months
        $interval = 'P' . count($months) . 'M';
        $month = $months[0];
        $start = $year . '-' . $month . '-01';
        
        $endDate = new DateTime($start);
        $endDate->add(new DateInterval($interval));
        
        $end = $endDate->format('Y-m-d');
    }
    
    
    
    // Hospitals
    $whHosp = '';
    $tdHosp = '';
    
    foreach ($hospitalSelections as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
                $tdHosp .= $hospList[$a][1];
            } else {
                $whHosp .= ",". $a;
                $tdHosp .= ', '. $hospList[$a][1];
            }
        }
    }
    
    if ($tdHosp == '') {
        $tdHosp = 'All';
    }
    
    
    // Associations.
    $whAssoc = '';
    $tdAssoc = '';
    
    // Only if there are any.
    if (count($aList) > 0) {
        
        foreach ($assocSelections as $a) {
            if ($a != '') {
                if ($whAssoc == '') {
                    $whAssoc .= $a;
                    $tdAssoc .= $hospList[$a][1];
                } else {
                    $whAssoc .= ",". $a;
                    $tdAssoc .= ', '. $hospList[$a][1];
                }
            }
        }
        
        if ($tdAssoc == '') {
            $tdAssoc = 'All';
        }
        
        $tdAssoc = HTMLTable::makeTd($tdAssoc);
    }
    
    
    if ($whHosp != '') {
        $whHosp = " and hs.idHospital in (".$whHosp.") ";
    }
    
    if ($whAssoc != '') {
        $whHosp .= " and hs.idAssociation in (".$whAssoc.") ";
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
    
    if ($stateSelection != '') {
        $whCountry .= " and vg.State = '$stateSelection' ";
    } else {
        $tdState = 'All';
    }
    
    $tdCountry = $countrySelection;
    
    if ($countrySelection != '') {
        
        if ($countrySelection == 'US') {
            $whCountry .= " and (vg.Country = '$countrySelection' or vg.Country = '')  ";
        } else {
            $whCountry .= " and vg.Country = '$countrySelection' ";
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
        
        
        // Create settings markup
        $sTbl = new HTMLTable();
        
        
        
        $whPeople = $whHosp . $whCountry . $whDiags . $whStatus;
        
        $rptSetting = filter_var($_POST['rbReport'], FILTER_SANITIZE_STRING);
        
        $showAssoc = FALSE;
        if (count($aList) > 0) {
            $showAssoc = TRUE;
        }
        
        $patTitle = $labels->getString('MemberType', 'patient', 'Patient');
        $mkTable = 1;
        
        switch ($rptSetting) {

        	case 'psg':
                $rptArry = getPsgReport($dbh, $local, $whHosp . $whDiags, $start, $end, readGenLookupsPDO($dbh, 'Patient_Rel_Type'), $uS->guestLookups[GL_TableNames::Hospital], $labels, $showAssoc, $showDiag, $showLocation, $uS->ShowBirthDate, $uS->PatientAsGuest);
                $dataTable = $rptArry['table'];
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' ' . $labels->getString('statement', 'psgLabel', 'PSG') . ' Report', array('colspan'=>'4')));
                $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
                $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdHosp) . ($showAssoc ? HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . $tdAssoc : ''));
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
                $dataTable = getPeopleReport($dbh, $local, FALSE, $whPeople . " and vg.Id = hs.idPatient ", $start, $end, $showAddr, $showFullName, $showNoReturn, $showAssoc, $labels, $showDiag, $showLocation);
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' Just '.$patTitle, array('colspan'=>'4')));
                $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
                $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdHosp) . ($showAssoc ? HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . $tdAssoc : ''));
                if ($showDiag) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'diagnosis', 'Diagnoses'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdDiags, array('colspan'=>'3')));
                }
                if ($showLocation) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'location', 'Locations'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdLocs, array('colspan'=>'3')));
                }
                $sTbl->addBodyTr(HTMLTable::makeTd('State/Province', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdState) . HTMLTable::makeTd('Country', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdCountry));
                $sTbl->addBodyTr(HTMLTable::makeTd('Visit Status', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdStatus, array('colspan'=>'3')));
                $settingstable = $sTbl->generateMarkup();
                break;
                
            case 'g':
                $dataTable = getPeopleReport($dbh, $local, TRUE, $whPeople, $start, $end, $showAddr, $showFullName, $showNoReturn, $showAssoc, $labels, $showDiag, $showLocation);
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' ' . $patTitle.' & Guests', array('colspan'=>'4')));
                $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
                $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital').'s', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdHosp) . ($showAssoc ? HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . $tdAssoc : ''));
                if ($showDiag) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'diagnosis', 'Diagnoses'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdDiags, array('colspan'=>'3')));
                }
                if ($showLocation) {
                    $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'location', 'Locations'), array('class'=>'tdlabel')) . HTMLTable::makeTd($tdLocs, array('colspan'=>'3')));
                }
                $sTbl->addBodyTr(HTMLTable::makeTd('State/Province', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdState) . HTMLTable::makeTd('Country', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdCountry));
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
$assocs = '';
if (count($aList) > 0) {
    $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($aList, $assocSelections, TRUE),
        array('name'=>'selAssoc[]', 'size'=>min(count($aList)+1, 5), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
}



$hospSize = min(count($hList)+1, 12);

$hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($hList, $hospitalSelections, TRUE),
    array('name'=>'selHospital[]', 'size'=>$hospSize, 'multiple'=>'multiple', 'style'=>'min-width:60px;'));

if ($hospSize < 5) {
    $hospSize = 5;
}

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>$hospSize,'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, '2010', $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>count($calOpts)));

// Visit status
$statusList = removeOptionGroups($uS->guestLookups['Visit_Status']);
$statusSelector = HTMLSelector::generateMarkup(
    HTMLSelector::doOptionsMkup($statusList, $statusSelections), array('name' => 'selResvStatus[]', 'size'=>count($statusList) + 1, 'multiple'=>'multiple'));

$selDiag = '';
if (count($diags) > 0) {
    
    $selDiag = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($diags, $diagSelections, TRUE),
        array('name'=>'selDiag[]', 'multiple'=>'multiple', 'size'=>min(count($diags)+1, $hospSize)));
}

$selLoc = '';
if (count($locs) > 0) {
    
    $selLoc = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($locs, $locSelections, TRUE),
        array('name'=>'selLoc[]', 'multiple'=>'multiple', 'size'=>min(count($locs)+1, $hospSize)));
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
        <title><?php echo $pageTitle; ?></title>
        <?php echo FAVICON; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <style>.hhk-rowseparater { border-top: 2px #0074c7 solid !important; }</style>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
<script type="text/javascript">
    $(document).ready(function() {

        var makeTable = '<?php echo $mkTable; ?>';
        $('#btnHere, #btnExcel').button();
        if (makeTable >= 1) {
            $('div#printArea').show();
            $('#divPrintButton').show();

            if (makeTable === 1) {
                $('#tblrpt').dataTable({
	                "displayLength": 50,
	                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
	                "dom": '<"top"ilf>rt<"bottom"lp><"clear">',
            	});
            }
            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
        }
        $('.ckdate').datepicker({
            yearRange: '-05:+01',
            changeMonth: true,
            changeYear: true,
            autoSize: true,
            numberOfMonths: 1,
            dateFormat: 'M d, yy'
        });
        $('#selCalendar').change(function () {
            if ($(this).val() && $(this).val() != '19') {
                $('#selIntMonth').hide();
            } else {
                $('#selIntMonth').show();
            }
            if ($(this).val() && $(this).val() != '18') {
                $('.dates').hide();
            } else {
                $('.dates').show();
            }
        });
        $('#selCalendar').change();

        $('.hhk-IncdtRpt').hide();
        
        $('input[name="rbReport"]').change(function () {
            if ($('#rbpsg').prop('checked')) {
                $('.psgsel').hide();
                $('.filters').show();
                $('.checkboxesShow').show();
            } else if($('#nrp').prop('checked')) {
                $('.filters').hide();
                $('.checkboxesShow').hide();
            } else if($('#incdt').prop('checked')) {
                $('.filters').hide();
                $('.hhk-IncdtRpt').show();
                $('.checkboxesShow').hide();
            } else {
                $('.filters').show();
                $('.psgsel').show();
                $('.checkboxesShow').show();
        }
        });
        $('input[name="rbReport"]').change();
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        	<div class="title" style="margin-bottom: 1em;">
            	<h2 style="display: inline-block"><?php echo $wInit->pageHeading; ?></h2><span style="margin-left: 1em;">Report shows people who stayed in the time frame selected below</span>
            </div>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="PSGReport.php" method="post">
                    <fieldset class="hhk-panel" style="margin-bottom: 15px;"><legend style='font-weight:bold;'>Report Type</legend>
                     <table style="width:100%">
                        <tr>
                            <th><label for='rbpsg'><?php echo $labels->getString('guestEdit', 'psgTab', 'Patient Support Group'); ?></label><input type="radio" id='rbpsg' name="rbReport" value="psg" style='margin-left:.5em;' <?php if ($rptSetting == 'psg') {echo 'checked="checked"';} ?>/></th>
                            <?php if ($uS->PatientAsGuest) { ?><th><label for='rbp'>Just <?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>s</label><input type="radio" id='rbp' name="rbReport" value="p" style='margin-left:.5em;' <?php if ($rptSetting == 'p') {echo 'checked="checked"';} ?>/></th><?php } ?>
                            <th><label for='rbg'>Guests & <?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>s</label><input type="radio" id='rbg' name="rbReport" value="g" style='margin-left:.5em;' <?php if ($rptSetting == 'g') {echo 'checked="checked"';} ?>/></th>
                            <th><label for='nrp'>No-Return Guests</label><input type="radio" id='nrp' name="rbReport" value="nr" style='margin-left:.5em;' <?php if ($rptSetting == 'nr') {echo 'checked="checked"';} ?>/></th>
                            <?php if ($uS->UseIncidentReports) { ?><th><label for='incdt'>Incidents Report</label><input type="radio" id='incdt' name="rbReport" value="in" style='margin-left:.5em;' <?php if ($rptSetting == 'in') {echo 'checked="checked"';} ?>/></th><?php } ?>
                        </tr>
                    </table>
                    </fieldset>
                    <table class="hhk-IncdtRpt" style="clear:left;float:left;display:none;">
                        <tr>
                            <th > Incident Reports Status</th>
                        </tr>
                        <tr>
                        	<td><?php echo $selirStat; ?></td>
                        </tr>
                    </table>
                    <div class="filters" style="display:none;">
                    <table style="clear:left;float: left;">
                        <tr>
                            <th colspan="3">Time Period</th>
                        </tr>
                        <tr>
                            <th>Interval</th>
                            <th style="min-width:100px; ">Month</th>
                            <th>Year</th>
                        </tr>
                        <tr>
                            <td><?php echo $calSelector; ?></td>
                            <td><?php echo $monthSelector; ?></td>
                            <td><?php echo $yearSelector; ?></td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span class="dates" style="margin-right:.3em;">Start:</span>
                                <input type="text" value="<?php echo $txtStart; ?>" name="stDate" id="stDate" class="ckdate dates" style="margin-right:.3em;"/>
                                <span class="dates" style="margin-right:.3em;">End:</span>
                                <input type="text" value="<?php echo $txtEnd; ?>" name="enDate" id="enDate" class="ckdate dates"/></td>
                        </tr>
                    </table>
                    <?php if (count($hList) > 1) { ?>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2"><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>s</th>
                        </tr>
                        <?php if (count($aList) > 0) { ?><tr>
                            <th>Associations</th>
                            <th><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>s</th>
                        </tr><?php } ?>
                        <tr>
                            <?php if (count($aList) > 0) { ?><td><?php echo $assocs; ?></td><?php } ?>
                            <td><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <?php } ?>
                    <?php if ($selDiag != '') { ?>
                    <table style="float: left;">
                        <tr>
                            <th><?php echo $labels->getString('hospital', 'diagnosis', 'Diagnosis') ?></th>
                        </tr>
                        <tr>
                            <td><?php echo $selDiag; ?></td>
                        </tr>
                    </table>
                    <?php } if ($selLoc != '') { ?>
                    <table style="float: left;">
                        <tr>
                            <th><?php echo $labels->getString('hospital', 'location', 'Location') ?></th>
                        </tr>
                        <tr>
                            <td><?php echo $selLoc; ?></td>
                        </tr>
                    </table>
                    <?php } ?>
                    <table style="float: left;" class="psgsel">
                        <tr>
                            <th>Visit Status</th>
                        </tr>
                        <tr>
                            <td><?php echo $statusSelector; ?></td>
                        </tr>
                    </table>

                    <table style="clear:left;">
                        <tr>
                            <th>State</th>
                            <th>Country</th>
                        </tr>
                        <tr>
                            <td><?php echo $selState; ?></td>
                            <td><?php echo $selCountry; ?></td>
                        </tr>
                    </table>
                    </div>
                    <table style="width:100%; margin-top: 15px;">
                        <tr>
                            <td class="checkboxesShow"><input type="checkbox" name="cbAddr" class="psgsel" id="cbAddr" <?php echo $showAddressSelection; ?>/><label for="cbAddr" class="psgsel"> Show Address</label></td>
                            <td class="checkboxesShow"><input type="checkbox" name="cbFullName" class="psgsel" id="cbFullName" <?php echo $showFullNameSelection; ?>/><label for="cbFullName" class="psgsel"> Show Full Name</label></td>
                            <td class="checkboxesShow" id="cbNoRtntd"><input type="checkbox" name="cbNoReturn" class="psgsel" id="cbNoReturn" <?php echo $showNoReturnSelection; ?>/><label for="cbNoReturn" class="psgsel"> Show No Return Only</label></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="divPrintButton" style="display:none;"><input id="printButton" value="Print" type="button" /></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox hhk-visitdialog" style="float:left;display:none; font-size: .8em; padding: 5px; padding-bottom:25px;">
                <div style="margin-bottom:.5em;"><?php echo $settingstable; ?></div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
