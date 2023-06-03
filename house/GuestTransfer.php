<?php
use HHK\SysConst\WebPageCode;
use HHK\SysConst\MemStatus;
use HHK\sec\WebInit;
use HHK\sec\Session;

use HHK\HTMLControls\HTMLContainer;
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Labels;
use HHK\CrmExport\RelationshipMapper;
use HHK\CrmExport\AbstractExportManager;

/**
 * GuestTransfer.php
 * List and transfer guests to external CMS system
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


try {
    // Do not add CSP.
    $wInit = new WebInit(WebPageCode::Page, FALSE);
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$pageHeader = $wInit->pageHeading;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();


if ($uS->ContactManager !== '') {

    $CmsManager = AbstractExportManager::factory($dbh, $uS->ContactManager);

    if (is_null($CmsManager)) {
        exit('<h4>The Contact Manager is not properly configured. </h4>');
    }

} else {
    exit('<h4>The Contact Manager is not defined.  Set "ContactManager" in the system configuration </h4>');
}

if (function_exists('curl_version') === FALSE) {
    exit('<h4>PHP configuration error: cURL functions are missing. </h4>');
}


$labels = Labels::getLabels();

function getNonVisitors(\PDO $dbh, $visitIds) {

    $idList = [];
    $idNames = [];

    // clean up the visit ids
    foreach ($visitIds as $s) {
        if (intval($s, 10) > 0){
            $idList[] = intval($s, 10);
        }
    }

    if (count($idList) == 0) {
        return $idNames;
    }

    $stmt = $dbh->query("Select	DISTINCT
    ng.idName AS `hhkId`,
    IFNULL(ng.Relationship_Code, '') as `Relationship_Code`,
    IFNULL(n.External_Id, '') AS `accountId`,
    IFNULL(n.Name_Full, '') AS `Name`,
    IFNULL(h.Title, '') AS `Hospital`,
    IFNULL(g.Description, '') AS `Diagnosis`,
    IFNULL(hs.idPsg, 0) as `idPsg`,
    CONCAT_WS(' ', na.Address_1, na.Address_2) as `Address`,
    v.idPrimaryGuest

from
	visit v
		join
	hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        join
	name_guest ng on hs.idPsg = ng.idPsg
		left join
	stays s on ng.idName = s.idName
        LEFT JOIN
    name n on n.idName = ng.idName
        LEFT JOIN
    name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
        LEFT JOIN
    hospital h on hs.idHospital = h.idHospital
        LEFT JOIN
    gen_lookups g on g.Table_Name = 'Diagnosis' and g.Code = hs.Diagnosis
where
	s.idName is NULL
    AND n.External_Id != '" . AbstractExportManager::EXCLUDE_TERM . "'
    AND n.Member_Status = '" . MemStatus::Active ."'
    AND v.idVisit in (" . implode(',', $idList) . ")");

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $idNames[ $r['hhkId'] ] = $r;

    }

    return $idNames;
}

/**
 * Summary of getPaymentReport
 * @param PDO $dbh
 * @param mixed $start
 * @param mixed $end
 * @return bool|string
 */
function getPaymentReport(\PDO $dbh, $start, $end) {

    $uS = Session::getInstance();
    $whereClause = " DATE(`Payment Date`) >= DATE('$start') and DATE(`Payment Date`) <= DATE('$end') ";

    if (isset($uS->sId) && $uS->sId > 0) {
        $whereClause .= " and `HHK Id` != " . $uS->sId;
    }

    if (isset($uS->subsidyId) && $uS->subsidyId > 0) {
        $whereClause .= " and `HHK Id` != " . $uS->subsidyId;
    }

    $stmt = $dbh->query("Select * from `vneon_payment_display` where $whereClause");
    $rows = array();

    if ($stmt->rowCount() == 0) {
        return FALSE;
    }

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $r['HHK Id'] = HTMLContainer::generateMarkup('a', $r['HHK Id'], array('href'=>'GuestEdit.php?id=' . $r['HHK Id']));

        if (isset($r['Payment Date']) && $r['Payment Date'] != '') {
            $r['Payment Date'] = date('c', strtotime($r['Payment Date']));
        }

        if (isset($r['Amount'])) {
            $r['Amount'] = number_format($r['Amount'], 2);
        }

        $rows[] = $r;

    }

    return CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');

}

/**
 * Summary of searchVisits
 * @param PDO $dbh
 * @param mixed $start
 * @param mixed $end
 * @param mixed $maxGuests
 * @param HHK\CrmExport\AbstractExportManager $CmsManager
 * @return bool|string
 */
function searchVisits(\PDO $dbh, $start, $end, $maxGuests, AbstractExportManager $CmsManager) {

    $uS = Session::getInstance();
    $rows = array();
    $guestIds = [];
    $visits = [];
    $visitIds = [];
    $psgs = [];


    $stmt = $dbh->query("SELECT
    s.idStays,
    s.idVisit,
    s.Visit_Span,
    IFNULL(n.External_Id, '') AS `accountId`,
    s.idName AS `hhkId`,
    ng.Relationship_Code,
    IFNULL(h.Title, '') AS `Hospital`,
    IFNULL(g.Description, '') AS `Diagnosis`,
    IFNULL(n.Name_Full, '') as `Name`,
    IFNULL(DATE_FORMAT(s.Span_Start_Date, '%Y-%m-%d'), '') AS `Start_Date`,
    IFNULL(DATE_FORMAT(s.Span_End_Date, '%Y-%m-%d'), '') AS `End_Date`,
    (TO_DAYS(`s`.`Span_End_Date`) - TO_DAYS(`s`.`Span_Start_Date`)) AS `Nite_Counter`,
    CONCAT_WS(' ', na.Address_1, na.Address_2) as `Address`,
    v.idPrimaryGuest,
    hs.idPsg,
    hs.idPatient
FROM
    stays s
        LEFT JOIN
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
        LEFT JOIN
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        LEFT JOIN
	name_guest ng on s.idName = ng.idName and hs.idPsg = ng.idPsg
		LEFT JOIN
    `name` n ON s.idName = n.idName
        LEFT JOIN
    hospital h on hs.idHospital = h.idHospital
        LEFT JOIN
    gen_lookups g on g.Table_Name = 'Diagnosis' and g.Code = hs.Diagnosis
        LEFT JOIN
    name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
WHERE
    s.On_Leave = 0
    AND s.`Status` != 'a'
    AND s.`Recorded` = 0
    AND n.External_Id != '" . AbstractExportManager::EXCLUDE_TERM . "'
    AND n.Member_Status = '" . MemStatus::Active ."'
    AND DATE(s.Span_End_Date) < DATE('$end')
    AND datediff(DATE(`s`.`Span_End_Date`), DATE(`s`.`Span_Start_Date`)) > 0
ORDER BY hs.idPsg
LIMIT 500");

    if ($stmt->rowCount() == 0) {
        return FALSE;
    }

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $visitIds[$r['idVisit']] = $r['idVisit'];
        $psgs[$r['idPsg']] = $r['idPsg'];

        if (isset($guestIds[ $r['hhkId'] ])) {

            if ($r['Nite_Counter'] > 0) {

                $startDT = new \DateTime($r['Start_Date']);
                $endDT = new \DateTime($r['End_Date']);

                if ($guestIds[ $r['hhkId'] ]['Start 1st Visit'] > $startDT ) {
                    $guestIds[ $r['hhkId'] ]['Start 1st Visit'] = $startDT;
                }

                if ($guestIds[ $r['hhkId'] ]['End Last Visit'] < $endDT ) {
                    $guestIds[ $r['hhkId'] ]['End Last Visit'] = $endDT;
                }

                $guestIds[ $r['hhkId'] ]['Nights'] += $r['Nite_Counter'];
            }

            if ( $r['hhkId'] == $r['idPrimaryGuest']) {
                $visits[$r['hhkId']] = array('Relation_Code'=>$r['Relationship_Code'], 'idPsg'=>$r['idPsg'], 'Address'=>$r['Address']);
                $guestIds[ $r['hhkId'] ]['Name'] = HTMLContainer::generateMarkup('span', $guestIds[ $r['hhkId'] ]['Name'], array('style'=>'color:#ae00d1;'));
            }

        } else {

            $guestIds[ $r['hhkId'] ] = array(
                'Account Id' => $r['accountId'],
                'HHK Id' => $r['hhkId'],
                'Name' => $r['Name'],
                'Diagnosis' => $r['Diagnosis'],
                'Hospital' => $r['Hospital'],
                'Start 1st Visit' => new \DateTime($r['Start_Date']),
                'End Last Visit' => new \DateTime($r['End_Date']),
                'Nights' => $r['Nite_Counter'],
                'Relation_Code' => $r['Relationship_Code'],
                'Address' => $r['Address'],
                'PG Id' => $r['idPrimaryGuest'],
                'idPsg'=> $r['idPsg']
            );

            if ( $r['hhkId'] == $r['idPrimaryGuest']) {
                $visits[$r['hhkId']] = array('Relation_Code'=>$r['Relationship_Code'], 'idPsg'=>$r['idPsg'], 'Address'=>$r['Address']);
                $guestIds[ $r['hhkId'] ]['Name'] = HTMLContainer::generateMarkup('span', $guestIds[ $r['hhkId'] ]['Name'], array('style'=>'color:#ae00d1;'));
            }

            if ($maxGuests-- <= 0) {
                break;
            }
        }

    }  // End of while

    // Get non-visitors
    $idNames = getNonVisitors($dbh, $visitIds);

    if (count($idNames) > 0) {

        foreach ($idNames as $r) {

            // add them to the list of guests
            $guestIds[ $r['hhkId'] ] = array(
                'Account Id' => $r['accountId'],
                'HHK Id' => $r['hhkId'],
                'Name' => $r['Name'],
                'Diagnosis' => $r['Diagnosis'],
                'Hospital' => $r['Hospital'],
                'Start 1st Visit' => NULL,
                'End Last Visit' => NULL,
                'Nights' => '',
                'Relation_Code' => $r['Relationship_Code'],
                'Address' => $r['Address'],
                'PG Id' => $r['idPrimaryGuest'],
                'idPsg'=> $r['idPsg']
            );
        }
    }


    $rMapper = new RelationshipMapper($dbh);

    // Get Neon relationship code list
    $nstmt = $dbh->query("Select * from neon_lists where `Method` = 'account/listRelationTypes';");
    $method = $nstmt->fetchAll(PDO::FETCH_ASSOC);
    $neonRelList = getNeonTypes($CmsManager, $method[0]);

    foreach ($guestIds as $g) {

        if (is_null($g['Start 1st Visit']) === FALSE) {
            $g['Start 1st Visit'] = $g['Start 1st Visit']->format('M j, Y');
            $g['End Last Visit'] = $g['End Last Visit']->format('M j, Y');
        }

        $g['Guest to Patient'] = $uS->guestLookups['Patient_Rel_Type'][$g['Relation_Code']][1];

        if (isset($visits[$g['PG Id']]) === FALSE) {

            // Load Primary guest.
            $v = $CmsManager::findPrimaryGuest($dbh, $g['PG Id'], $g['idPsg'], $rMapper);

            if (count($v) > 0) {
                $visits[$g['PG Id']] = $v;
            }
        }

        $g['PG to Patient'] = $uS->guestLookups['Patient_Rel_Type'][$visits[$g['PG Id']]['Relation_Code']][1];

        // Address Match?
        if (strtolower($visits[$g['PG Id']]['Address']) == strtolower($g['Address']) && $g['Address'] != '') {

            // Map relationship.
            $rMapper
                ->clear()
                ->setPGtoPatient($visits[$g['PG Id']]['Relation_Code']);

            $g['Guest to PG'] = $rMapper->relateGuest($g['Relation_Code']);

        } else {
            // empty relationship means address mismatch
            $g['Guest to PG'] = '';
        }

        unset($g['Relation_Code']);

        $idPsg = $g['idPsg'];
        unset($g['idPsg']);

        // Collect by the PSG Id.
        $rows[$idPsg][] = $g;

    }

    // Show table
    $tbl = new HTMLTable();
    $tbl->addHeaderTr(HTMLTable::makeTh('PSG').HTMLTable::makeTh('HHK Id').HTMLTable::makeTh('Name').HTMLTable::makeTh('Address').HTMLTable::makeTh('Diagnosis').HTMLTable::makeTh('Hospital').HTMLTable::makeTh('Start 1st Visit')
        .HTMLTable::makeTh('End Last Visit').HTMLTable::makeTh('Nights').HTMLTable::makeTh('PG Id').HTMLTable::makeTh('Guest to Patient').HTMLTable::makeTh('PG to Patient').HTMLTable::makeTh('Guest to PG'));

    foreach ($rows as $idp => $r) {

        $first = TRUE;
        foreach ($r as $g) {

            if ($first) {
                $first = FALSE;
                $td = HTMLTable::makeTd( HTMLContainer::generateMarkup('label', $idp, array('for'=>'cbIdPSG'.$idp, 'style'=>'margin-right:3px;'))
                    .HTMLInput::generateMarkup($idp, array('type'=>'checkbox', 'class'=>'hhk-txPsgs', 'name'=>'cbIdPSG'.$idp, 'checked'=>'checked', 'data-idpsg'=>$idp, 'title'=>'Check to include in the transfer.'))
                    .'&#32;&#124;&#32;'
                    .HTMLInput::generateMarkup($idp, array('type'=>'checkbox', 'class'=>'hhk-exPsg', 'name'=>'cbExPSG'.$idp, 'data-idpsg'=>$idp, 'style'=>'margin-right:3px;', 'title'=>'Check to permanently exclude from Neon.'))
                    .HTMLContainer::generateMarkup('label', 'Excld', array('for'=>'cbExPSG'.$idp))
                    , array('rowspan'=>count($rows[$idp]), 'style'=>'vertical-align:top;'));

                $rowStyle = "border-top: 2px solid #2E99DD;";
            } else {
                $td = '';
                $rowStyle = '';
            }

            $tbl->addBodyTr($td
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $g['HHK Id'], array('href'=>'GuestEdit.php?id=' . $g['HHK Id'])))
                .HTMLTable::makeTd($g['Name'])
                .HTMLTable::makeTd($g['Address'])
                .HTMLTable::makeTd($g['Diagnosis'])
                .HTMLTable::makeTd($g['Hospital'])
                .HTMLTable::makeTd($g['Start 1st Visit'])
                .HTMLTable::makeTd($g['End Last Visit'])
                .HTMLTable::makeTd($g['Nights'])
                .HTMLTable::makeTd($g['PG Id'])
                .HTMLTable::makeTd($g['Guest to Patient'])
                .HTMLTable::makeTd($g['PG to Patient'])
                .HTMLTable::makeTd(
                    HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($neonRelList, $g['Guest to PG'], TRUE),
                        array('name'=>'selNeonRel' . $g['HHK Id'], 'data-idname'=>$g['HHK Id'], 'class'=>'hhk-selRel'.$idp)))
                , array('class'=>'hhk-'.$idp, 'style'=>$rowStyle));
        }

    }

    return $tbl->generateMarkup(array('name'=>'tblrpt'));
}

/**
 * Summary of getPeopleReport
 * @param PDO $dbh
 * @param mixed $start
 * @param mixed $end
 * @param mixed $excludeTerm
 * @return array|bool
 */
function getPeopleReport(\PDO $dbh, $start, $end, $excludeTerm) {


    $transferIds = [];

    $query = "SELECT *
    FROM `vguest_transfer`
    WHERE ifnull(DATE(`Departure`), DATE(now())) >= DATE('$start') and DATE(`Arrival`) < DATE('$end')";

    $stmt = $dbh->query($query);

    if ($stmt->rowCount() == 0) {
        return FALSE;
    }

    $rows = array();

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $transferIds[] = $r['HHK Id'];


        if ($r['Address'] == ', ,   ') {
        	$r['Address'] = '';
        }

        // Transfer opt-out
        if ($r['External Id'] == '') {

       		if ($r['Email'] !== '' || ($r['Address'] !== '' && $r['Bad Addr'] === '')) {
       			$r['External Id'] = HTMLInput::generateMarkup('', array('name'=>'tf_'.$r['HHK Id'], 'class'=>'hhk-txCbox hhk-tfmem', 'data-txid'=>$r['HHK Id'], 'type'=>'checkbox', 'checked'=>'checked'));
       		} else {
       			$r['External Id'] = HTMLInput::generateMarkup('', array('name'=>'tf_'.$r['HHK Id'], 'class'=>'hhk-txCbox hhk-tfmem', 'data-txid'=>$r['HHK Id'], 'type'=>'checkbox'));
        	}
        } else if ($r['External Id'] == $excludeTerm) {
            $r['External Id'] = 'Excluded';
        } else {
            $r['External Id'] .= HTMLInput::generateMarkup('', array('name'=>'tf_'.$r['HHK Id'], 'class'=>'hhk-txCbox hhk-tfmem', 'data-txid'=>$r['HHK Id'], 'type'=>'checkbox', 'checked'=>'checked', 'style'=>'display:none;'));
        }

        $r['HHK Id'] = HTMLContainer::generateMarkup('a', $r['HHK Id'], array('href'=>'GuestEdit.php?id=' . $r['HHK Id']));

        unset($r['Arrival']);
        unset($r['Departure']);
        unset($r['Bad Addr']);

        $rows[] = $r;

    }

    $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
    return array('mkup' =>$dataTable, 'xfer'=>$transferIds);

}

/**
 * Summary of getNeonTypes
 * @param AbstractExportManager $CmsManager
 * @param mixed $list
 * @return array<array>
 */
function getNeonTypes($CmsManager, $list) {

    $neonList = [];

    $rawList = $CmsManager->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

    foreach ($rawList as $k => $v) {
        $neonList[$k] = array(0=>$k, 1=>$v);
    }

    return $neonList;
}

/**
 * Summary of createKeyMap
 * @param PDO $dbh
 * @return string
 */
function createKeyMap(\PDO $dbh) {

    // get session instance
    $uS = Session::getInstance();

    $hospitalKeyTable = new HTMLTable();

    // Hospitals
    $hospitalKeyTable->addHeaderTr(HTMLTable::makeTh("Hospital Key", array('colspan'=>'2')));
    $hospitalKeyTable->addHeaderTr(HTMLTable::makeTh('Title').HTMLTable::makeTh("Id"));

    foreach ($uS->guestLookups['Hospitals'] as $h) {
        $hospitalKeyTable->addBodyTr(HTMLTable::makeTd($h[1]).HTMLTable::makeTd($h[0]));
    }

    // Diagnosis
    $diagKeyTable = new HTMLTable();

    $diagKeyTable->addBodyTr(HTMLTable::makeTh("Diagnosis Key", array('colspan'=>'2')));
    $diagKeyTable->addBodyTr(HTMLTable::makeTh("Code").HTMLTable::makeTh('Title'));

    $diags = readGenLookupsPDO($dbh, 'Diagnosis', 'Order');

    foreach ($diags as $d) {
        $diagKeyTable->addBodyTr(HTMLTable::makeTd($d[0]).HTMLTable::makeTd($d[1]));
    }

    $hdiv = HTMLContainer::generateMarkup('div', $hospitalKeyTable->generateMarkup(), array('style'=>'float:left; margin-left:10px;'));
    $ddiv = HTMLContainer::generateMarkup('div', $diagKeyTable->generateMarkup(), array('style'=>'float:left;'));

    return  HTMLContainer::generateMarkup('div', $ddiv . $hdiv, array('id'=>'divPrintKeys'));
}

$mkTable = '';
$dataTable = '';
$paymentsTable = '';
$settingstable = '';
$searchTabel = '';
$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$errorMessage = '';
$calSelection = '19';
$noRecordsMsg = '';
$maxGuests = 15;  // maximum guests to process for each post.
$btnVisits = '';
$btnGetKey = '';
$dboxMarkup = '';
$btnPayments = '';


$monthArray = array(
    1 => array(1, 'January'), 2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}


// Process report.
if (filter_has_var(INPUT_POST, 'btnHere') || filter_has_var(INPUT_POST, 'btnGetPayments') || filter_has_var(INPUT_POST, 'btnGetVisits')) {

    // gather input
 // Input arguements
    $rags = [
        'selCalendar'   => array('filter'=>FILTER_SANITIZE_NUMBER_INT, 'flags'=>FILTER_REQUIRE_SCALAR),
        'selIntMonth'   => array('filter'=>FILTER_SANITIZE_NUMBER_INT, 'flags'=>FILTER_FORCE_ARRAY),
        'selIntYear'   => array('filter'=>FILTER_SANITIZE_NUMBER_INT, 'flags'=>FILTER_REQUIRE_SCALAR),
        'stDate'       => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'enDate'       => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    ];

    $inputs = filter_input_array(INPUT_POST, $rags);

    $months = $inputs['selIntMonth'];
    $year = intval($inputs['selIntYear'], 10);
    $txtStart = $inputs['stDate'];
    $txtEnd = $inputs['enDate'];
    $selCal = intval($inputs['selCalendar'], 10);

   if ($selCal == 20) {
        // fiscal year
        $adjustPeriod = new DateInterval('P' . $uS->fy_diff_Months . 'M');
        $startDT = new DateTime($year . '-01-01');
        $startDT->sub($adjustPeriod);
        $start = $startDT->format('Y-m-d');

        $endDT = new DateTime(($year + 1) . '-01-01');
        $end = $endDT->sub($adjustPeriod)->format('Y-m-d');

    } else if ($selCal == 21) {
        // Calendar year
        $startDT = new DateTime($year . '-01-01');
        $start = $startDT->format('Y-m-d');

        $end = ($year + 1) . '-01-01';

    } else if ($selCal == 18) {
        // Dates
        if ($txtStart != '') {
            $startDT = new DateTime($txtStart);
            $start = $startDT->format('Y-m-d');
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
            $end = $endDT->format('Y-m-d');
        }

    } else if ($selCal == 22) {
        // Year to date
        $start = $year . '-01-01';

        $endDT = new DateTime($year . date('m') . date('d'));
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


    if (filter_has_var(INPUT_POST, 'btnHere')) {

        // Get HHK records result table.
        $results = getPeopleReport($dbh, $start, $end, $CmsManager::EXCLUDE_TERM, FALSE);

        if ($results === FALSE) {

            $noRecordsMsg = "No HHK member records found.";

        } else {

            $dataTable = $results['mkup'];


            // Create settings markup
            $sTbl = new HTMLTable();
            $sTbl->addBodyTr(HTMLTable::makeTh('Guest Selection Timeframe', array('colspan'=>'4')));
            $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
            $settingstable = $sTbl->generateMarkup(array('style'=>'float:left;'));

            // Create search criteria markup
            $searchCriteria = $CmsManager->getSearchFields($dbh, $CmsManager::SearchViewName);

            $tr = '';
            foreach ($searchCriteria as $s) {
                $tr .= HTMLTable::makeTd($s);
            }

            $scTbl = new HTMLTable();
            $scTbl->addHeaderTr(HTMLTable::makeTh($CmsManager->getServiceTitle() . ' Search Criteria', array('colspan'=>count($searchCriteria))));
            $scTbl->addBodyTr($tr);
            $searchTabel = $scTbl->generateMarkup(array('style'=>'float:left; margin-left:2em;'));

            $mkTable = 1;
        }

    } else if (filter_has_var(INPUT_POST, 'btnGetPayments')) {

        $dataTable = getPaymentReport($dbh, $start, $end);

        if ($dataTable === FALSE) {
            $noRecordsMsg = "No payment records found.";
        } else {
            $mkTable = 2;
        }

    } else if (filter_has_var(INPUT_POST, 'btnGetVisits')) {

        $dataTable = searchVisits($dbh, $start, $end, $maxGuests, $CmsManager);

        if ($dataTable === FALSE) {
            $noRecordsMsg = "No visit records found.";
        } else {
            $mkTable = 3;
        }

    }

}

// Get Hospitals and Diagnosis button.
$customFields = $CmsManager->getMyCustomFields($dbh);

if (isset($customFields['Hospital'])) {
    $btnGetKey = HTMLInput::generateMarkup('Show Diagnosis & Hospitals Key', array('id'=>'btnGetKey', 'type'=>'button', 'style'=>'margin-left:3px; font-size:small;'));
    $dboxMarkup = createKeyMap($dbh);
}

if (isset($customFields['First_Visit'])) {
    $btnVisits = HTMLInput::generateMarkup('Get HHK Visits', array('name'=>'btnGetVisits', 'id'=>'btnGetVisits', 'type'=>'submit', 'style'=>'margin-left:20px;'));
}

if (isset($customFields['Fund'])) {
    $btnPayments = HTMLInput::generateMarkup('Get HHK Payments', array('type'=>'submit', 'name'=>"btnGetPayments", 'id'=>"btnGetPayments", 'style'=>"margin-left:20px;"));
}

if ($noRecordsMsg != '') {
    $noRecordsMsg = HTMLContainer::generateMarkup('p', $noRecordsMsg, array('style'=>'font-size:large'));
}


// Setups for the page.
$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'5','multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, '2010', $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>count($calOpts)));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo FAVICON; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <style>
            .hhk-rowseparater { border-top: 2px #0074c7 solid !important; }
            #aLoginLink:hover {background-color: #337a8e; }
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo GUESTTRANSFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

    </head>
    <body <?php if ($wInit->testVersion) { echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>

            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="display:none; clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="GuestTransfer.php" method="post">
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
                    <table style="float:left;margin-left:10px;">
                        <tr>
                            <th><?php echo $CmsManager->getServiceTitle(); ?> <span style="font-weight: bold;">Last Name</span> Search</th>
                            <td><input id="txtRSearch" type="text" /></td>
                        </tr>
                        <tr>
                            <th>Local (HHK) Name Search</th>
                            <td><input id="txtSearch" type="text" /></td>
                        </tr>
                        <tr>
                            <th>Relationship</th>
                            <td><input id="btnRelat" type="button" value="click me" /></td>
                        </tr>
                    </table>
                    <table style="width:100%; margin-top: 15px;">
                        <tr>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Get HHK Records" style="margin-left:20px;"/>
				<?php echo $btnPayments . $btnVisits . $btnGetKey; ?>
                            </td>
                        </tr>
                    </table>
                </form>
                <div style="margin-top: 15px; margin-left:50px;" id="retrieve"><?php echo $noRecordsMsg; ?></div>
            </div>

            <div id="printArea" autocomplete="off" class="ui-widget ui-widget-content hhk-tdbox hhk-visitdialog" style="float:left;display:none; font-size: .8em; padding: 5px; padding-bottom:25px;">
                <div id="localrecords">
                    <div style="margin-bottom:.8em; float:left;">
                        <?php echo $settingstable . $searchTabel; ?>
                    </div>
                    <div id="divTable" style="clear:left;">
                        <?php echo $dataTable; ?>
                    </div>
                </div>
                <div id="divMembers"></div>
            </div>
            <div id="divPrintButton" style="clear:both; display:none;margin-top:6px;margin-bottom:6px;margin-left:20px;font-size:0.9em;">
                <input id="printButton" value="Print" type="button" />
                <input id="TxButton" value="" type="button" style="margin-left:4em;"/>
                <input id="btnPay" value="Transfer Payments" type="button" style="margin-left:2em;"/>
                <input id="btnVisits" value="" type="button" style="margin-left:2em;"/>
        	</div>
        </div>
        <div id="keyMapDiagBox" class="hhk-tdbox hhk-visitdialog" style="font-size: .85em; display:none;"><?php echo $dboxMarkup; ?></div>

        <input id='hmkTable' type="hidden" value='<?php echo $mkTable; ?>'/>
        <input id='hstart' type="hidden" value='<?php echo $start; ?>'/>
        <input id='hend' type="hidden" value='<?php echo $end; ?>'/>
        <input id='hdateFormat' type="hidden" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>'/>
	    <input id='maxGuests' type = 'hidden' value='<?php echo $maxGuests; ?>'/>
        <input id="cmsTitle" type="hidden" value="<?php echo $CmsManager->getServiceTitle(); ?>"/>

    </body>
</html>
