<?php

use HHK\sec\{Session, WebInit};
use HHK\House\Resource\ResourceTypes;
use HHK\SysConst\ResourceStatus;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLContainer;
use HHK\SysConst\RoomRateCategories;
use HHK\SysConst\GLTableNames;
use HHK\ColumnSelectors;
use HHK\SysConst\ItemPriceCode;
use HHK\Purchase\RoomRate;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemType;
use HHK\SysConst\ItemId;
use HHK\SysConst\VolMemberType;
use HHK\Purchase\ValueAddedTax;
use HHK\Config_Lite\Config_Lite;
use HHK\Payment\PaymentSvcs;
use HHK\Exception\RuntimeException;
use HHK\House\Report\ReportFilter;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\House\Report\ReportFieldSet;


/**
 * VisitInterval.php
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

// get session instance
$uS = Session::getInstance();
creditIncludes($uS->PaymentGateway);


function statsPanel(\PDO $dbh, $visitNites, $totalCatNites, $start, $end, $categories, $avDailyFee, $rescGroup, $siteName) {

    // Stats panel
    if (count($visitNites) < 1) {
        return '';
    }

    $totalVisitNites = 0;
    $numCategoryRooms = array();

    $oosNights = array();
    $totalOOSNites = 0;

    $stDT = new DateTime($start . ' 00:00:00');
    $enDT = new DateTime($end . ' 00:00:00');
    $numNights = $enDT->diff($stDT, TRUE)->days;

    foreach ($visitNites as $v) {
        $totalVisitNites += $v;
    }

    foreach ($categories as $cat) {
        $numCategoryRooms[$cat[0]] = 0;
        $oosNights[$cat[0]] = 0;
    }



    $qu = "select r.idResource, rm.Category, rm.Type, rm.Report_Category, ifnull(ru.Start_Date,'') as `Start_Date`, ifnull(ru.End_Date, '') as `End_Date`, ifnull(ru.Status, 'a') as `RU_Status`
        from resource r left join
resource_use ru on r.idResource = ru.idResource and DATE(ru.Start_Date) < DATE('" . $enDT->format('Y-m-d') . "') and DATE(ru.End_Date) > DATE('" . $stDT->format('Y-m-d') . "')
left join resource_room rr on r.idResource = rr.idResource
left join room rm on rr.idRoom = rm.idRoom
where r.`Type` in ('" . ResourceTypes::Room . "','" . ResourceTypes::RmtRoom . "')
order by r.idResource;";

    $rstmt = $dbh->query($qu);

    $rooms = array();

    // Get rooms and oos days
    while ($r = $rstmt->fetch(PDO::FETCH_ASSOC)) {

        $nites = 0;

        if ($r['Start_Date'] != '' && $r['End_Date'] != '') {
            $arriveDT = new DateTime($r['Start_Date']);
            $arriveDT->setTime(0, 0, 0);
            $departDT = new DateTime($r['End_Date']);
            $departDT->setTime(0,0,0);

            // Only collect days within the time period.
            if ($arriveDT < $stDT) {
                $arriveDT = new \DateTime($stDT->format('Y-m-d H:i:s'));
            }

            if ($departDT > $enDT) {
                $departDT = new \DateTime($enDT->format('Y-m-d H:i:s'));
            }

            // Collect 0-day events as one day
            if ($arriveDT == $departDT) {
                $nites = 1;
            } else {
                $nites = $departDT->diff($arriveDT, TRUE)->days;
            }
        }

        if (isset($rooms[$r['idResource']][$r[$rescGroup]][$r['RU_Status']]) === FALSE) {
            $rooms[$r['idResource']][$r[$rescGroup]][$r['RU_Status']] = $nites;
        } else {
            $rooms[$r['idResource']][$r[$rescGroup]][$r['RU_Status']] += $nites;
        }

    }

    // Filter out unavailalbe rooms and add up the nights
    $availableRooms = 0;
    $unavailableRooms = 0;

    foreach($rooms as $r) {

        foreach ($r as $cId => $c) {

            if (isset($c[ResourceStatus::Unavailable]) && $c[ResourceStatus::Unavailable] >= $numNights) {
                $unavailableRooms++;
                continue;
            }

            $numCategoryRooms[$cId]++;
            $availableRooms++;

            foreach ($c as $k => $v) {

                if ($k != ResourceStatus::Available) {
                    $oosNights[$cId] += $v;
                    $totalOOSNites += $v;
                }
            }
        }
    }


    $numRoomNights = $availableRooms * $numNights;
    $numUsefulNights = $numRoomNights - $totalOOSNites;
    $avStay = $totalVisitNites / count($visitNites);

    // Median
    array_multisort($visitNites);
    $entries = count($visitNites);
    $emod = $entries % 2;

    if ($emod > 0) {
        // odd number of entries
        $median = $visitNites[(ceil($entries / 2) - 1)];
    } else {
        $median = ($visitNites[($entries / 2) - 1] + $visitNites[($entries / 2)]) / 2;
    }


    $trs[4] = HTMLTable::makeTd('Useful Nights (Room-Nights &ndash; Room-Nights OOS):', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($numRoomNights . ' &ndash; ' . $totalOOSNites . ' = '  . HTMLContainer::generateMarkup('span', $numUsefulNights, array('style'=>'font-weight:bold;')));

    $trs[5] = HTMLTable::makeTd('Room Utilization (Nights &divide; Useful Nights):', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($totalVisitNites . ' &divide; ' . $numUsefulNights . ' = ' . HTMLContainer::generateMarkup('span', ($numUsefulNights <= 0 ? '0' : number_format($totalVisitNites * 100 / $numUsefulNights, 1)) . '%', array('style'=>'font-weight:bold;')));

    $hdTr = HTMLTable::makeTh('Parameter') . HTMLTable::makeTh('All Rooms (' . $availableRooms . ')');

    foreach ($categories as $c) {

        if (!isset($numCategoryRooms[$c[0]]) || $numCategoryRooms[$c[0]] == 0){
            continue;
        }

        $hdTr .= HTMLTable::makeTh($c[1] . ' (' . $numCategoryRooms[$c[0]] . ')');
        $numRoomNights = $numCategoryRooms[$c[0]] * $numNights;
        $numUsefulNights = $numRoomNights - $oosNights[$c[0]];

        $trs[4] .= HTMLTable::makeTd($numRoomNights . ' &ndash; ' . $oosNights[$c[0]] . ' = '  . HTMLContainer::generateMarkup('span', $numUsefulNights, array('style'=>'font-weight:bold;')));
        $trs[5] .= HTMLTable::makeTd($totalCatNites[$c[0]] . ' &divide; ' . $numUsefulNights . ' = ' . HTMLContainer::generateMarkup('span', ($numUsefulNights <= 0 ? '0' : number_format($totalCatNites[$c[0]] * 100 / $numUsefulNights, 1)) . '%', array('style'=>'font-weight:bold;')));
    }

    $sTbl = new HTMLTable();

    $sTbl->addHeaderTr($hdTr);

    $sTbl->addBodyTr(HTMLTable::makeTd('Mean visit length in days:', array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($avStay, 2)));
    $sTbl->addBodyTr(HTMLTable::makeTd('Median visit length in days:', array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($median,2)));

    $sTbl->addBodyTr(HTMLTable::makeTd('Mean Room Charge per visit day:', array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format($avDailyFee,2)));

    $sTbl->addBodyTr($trs[4]);

    $sTbl->addBodyTr($trs[5]);

    return HTMLContainer::generateMarkup('h3', $siteName . ' Visit Report Statistics')
            . HTMLContainer::generateMarkup('p', 'These numbers are specific to this report\'s selected filtering parameters.')
            . $sTbl->generateMarkup();

}

/**
 * Prettify a row.
 *
 * @param array $r  db record row
 * @param array $visit
 * @param float $unpaid
 * @param \DateTime $departureDT
 * @param HTMLTable $tbl
 * @param boolean $local  Flag for Excel output
 * @param PHPExcel $sml
 * @param Object $reportRows  PHPExecl object
 * @param array $rateTitles  Room rates
 * @param Session $uS
 * @param Boolean $visitFee  Flag to show/hide visit fees

 */
function doMarkup($fltrdFields, $r, $visit, $paid, $unpaid, \DateTime $departureDT, HTMLTable &$tbl, $local, &$sml, $header, &$reportRows, $rateTitles, $uS, $visitFee = FALSE) {

    $arrivalDT = new DateTime($r['Arrival_Date']);

    if ($r['Rate_Category'] == RoomRateCategories::Fixed_Rate_Category) {

        $r['rate'] = $r['Pledged_Rate'];

    } else if (isset($visit['rateId']) && isset($rateTitles[$visit['rateId']])) {

        $rateTxt = $rateTitles[$visit['rateId']];

        if ($visit['adj'] != 0) {
            $parts = explode('$', $rateTxt);

            if (count($parts) == 2) {
                $amt = floatval($parts[1]) * (1 + $visit['adj']/100);
                $rateTxt = $parts[0] . '$' . number_format($amt, 2);
            }
        }

        $r['rate'] = $rateTxt;

    } else {
        $r['rate'] = '';
    }

    // Average rate
    $r['meanRate'] = 0;
    if ($visit['nit'] > 0) {
        $r['meanRate'] = number_format(($visit['chg'] / $visit['nit']), 2);
    }


    // Hospital
    $hospital = '';
    $assoc = '';
    $hosp = '';

    if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
        $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
        $assoc = $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1];
    }
    if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
        $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
        $hosp = $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
    }

    $r['Doctor'] = $r['Doctor_Last'] . ($r['Doctor_First'] == '' ? '' : ', ' . $r['Doctor_First']);


    $r['hospitalAssoc'] = $hospital;
    $r['assoc'] = $assoc;
    $r['hosp'] = $hosp;

    $r['nights'] = $visit['nit'];
    $r['gnights'] = $visit['gnit'];
    $r['lodg'] = number_format($visit['chg'],2);
    $r['days'] = $visit['day'];


    $sub = $visit['fcg'] - $visit['chg'];
    $r['sub'] = ($sub == 0 ? '' : number_format($sub,2));
    
    $rateAdj = $visit['adj'];
    $r['rateAdj'] = ($rateAdj == 0 ? '' : number_format($rateAdj, 2) . '%');

    $r['gpaid'] = ($visit['gpd'] == 0 ? '': number_format($visit['gpd'], 2));
    $r['hpaid'] = ($visit['hpd'] == 0 ? '': number_format($visit['hpd'], 2));
    $r['totpd'] = ($paid == 0 ? '' : number_format($paid,2));
    $r['unpaid'] = ($unpaid == 0 ? '' : number_format($unpaid,2));
    $r['adjch'] = ($visit['addpd'] == 0 ? '': number_format($visit['addpd'], 2));
    $r['adjchtx'] = ($visit['adjchtx'] == 0 ? '': number_format($visit['adjchtx'], 2));
    $r['pndg'] = ($visit['pndg'] == 0 ? '': number_format($visit['pndg'], 2));
    $r['thdpaid'] = ($visit['thdpd'] == 0 ? '': number_format($visit['thdpd'], 2));
    $r['donpd'] = ($visit['donpd'] == 0 ? '': number_format($visit['donpd'], 2));

    $r['taxcgd'] = ($visit['taxcgd'] == 0 ? '': number_format($visit['taxcgd'], 2));
    $r['taxpd'] = ($visit['taxpd'] == 0 ? '': number_format($visit['taxpd'], 2));
    $r['taxpndg'] = ($visit['taxpndg'] == 0 ? '': number_format($visit['taxpndg'], 2));


    $visitFeePaid = '';

    if ($visitFee) {

        if ($visit['vfa'] > 0 && $visit['vfa'] == $visit['vfpd']) {

            $r['visitFee'] = number_format($visit['vfa'],2);
            $visitFeePaid = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-circle-check', 'style'=>'float:left;', 'title'=>'Fees paid'));

        } else if ($visit['vfa'] > 0 && $uS->VisitFeeDelayDays < $visit['nit']) {

            $r['visitFee'] = number_format($visit['vfa'],2);

        } else {

            $r['visitFee'] = '';
        }
    }

    $addPaidIcon = '';

    if ($visit['addch'] > 0 && $visit['addch'] <= $visit['addpd']) {

        $r['adjch'] = number_format($visit['addch'],2);
        $addPaidIcon = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-circle-check', 'style'=>'float:left;', 'title'=>'Charges paid'));

    } else if ($visit['addch'] > 0) {

        $r['adjch'] = number_format($visit['addch'],2);

    } else {

        $r['adjch'] = '';
    }


    $changeRoomIcon = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-info', 'style'=>'float:left;', 'title'=>'Changed Rooms'));
    $changeRateIcon = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-info', 'style'=>'float:left;', 'title'=>'Room Rate Changed'));

    if ($local) {

        $r['idVisit'] = HTMLContainer::generateMarkup('div', $r['idVisit'], array('class'=>'hhk-viewVisit', 'data-gid'=>$r['idPrimaryGuest'], 'data-vid'=>$r['idVisit'], 'data-span'=>$r['Span'], 'style'=>'display:inline-table;'));
        $r['idPrimaryGuest'] = HTMLContainer::generateMarkup('a', $r['Name_Last'] . ', ' . $r['Name_First'], array('href'=>'GuestEdit.php?id=' . $r['idPrimaryGuest'] . '&psg=' . $r['idPsg']));
        $r['idPatient'] = HTMLContainer::generateMarkup('a', $r['Patient_Last'] . ', ' . $r['Patient_First'], array('href'=>'GuestEdit.php?id=' . $r['idPatient'] . '&psg=' . $r['idPsg']));
        $r['Arrival'] = $arrivalDT->format('c');
        $r['Departure'] = $departureDT->format('c');

        if ($r['pBirth'] != '') {
            $pBirthDT = new DateTime($r['pBirth']);
            $r['pBirth'] = $pBirthDT->format('c');
        }

        $now = new DateTime();
        $now->setTime(0,0,0);
        $expDepart = new DateTime($r['Expected_Departure']);
        $expDepart->setTime(0, 0, 0);

        $r['Status'] = HTMLContainer::generateMarkup('span', $uS->guestLookups['Visit_Status'][$r['Status']][1], array('class'=>'hhk-getVDialog', 'style'=>'cursor:pointer;width:100%;text-decoration: underline;', 'data-vid'=>$r['idVisit'], 'data-span'=>$r['Span']));

        if ($visitFeePaid != '') {
            $r['visitFee'] = $visitFeePaid . $r['visitFee'];
        }

        if ($addPaidIcon != '') {
            $r['adjch'] = $addPaidIcon . $r['adjch'];
        }

        if ($visit['rtc'] > 1) {
            $r['rate'] = $changeRateIcon . $r['rate'];
        }

        if ($visit['rmc'] > 1) {
            $r['Title'] = $changeRoomIcon . $r['Title'];
        }

        $tr = '';
        foreach ($fltrdFields as $f) {
            $tr .= HTMLTable::makeTd($r[$f[1]], $f[6]);
        }

        $tbl->addBodyTr($tr);

    } else {
        
        $r['Status'] = $uS->guestLookups['Visit_Status'][$r['Status']][1];
        $r['idPrimaryGuest'] = $r['Name_Last'] . ', ' . $r['Name_First'];
        $r['Arrival'] = $arrivalDT->format('Y-m-d');
        $r['Departure'] = $departureDT->format('Y-m-d');
        $r['idPatient'] = $r['Patient_Last'] . ', ' . $r['Patient_First'];

        if ($r['pBirth'] != '') {
            $pBirthDT = new DateTime($r['pBirth']);
            $r['pBirth'] = $pBirthDT->format('Y-m-d');
        } else {
            $r['pBirth'] = '';
        }

        $n = 0;
        $flds = array();

        foreach ($fltrdFields as $f) {

            //$flds[$n++] = array('type' => $f[4], 'value' => $r[$f[1]], 'style'=>$f[5]);
            if ($r[$f[1]] != '' && $f[5] != ''){
                if($r[$f[1]] == ""){
                    $flds[$n++] = "0.00";
                }else{
                    $flds[$n++] = strval(str_replace(',', '', $r[$f[1]]));
                }
            }else{
                $flds[$n++] = html_entity_decode(strval($r[$f[1]]), ENT_QUOTES, 'UTF-8');
            }
        }
        $row = ExcelHelper::convertStrings($header, $flds);
        $sml->writeSheetRow('Sheet1',$row);

    }
}

/**
 *
 * @param \PDO $dbh
 * @param string $start
 * @param string $end
 * @param string $whHosp  SQL fragment
 * @param string $whAssoc  SQL fragment
 * @param array $aList
 * @param boolean $local
 * @param boolean $visitFee  Flag to show/hide visit fees
 * @return array
 */
function doReport(\PDO $dbh, ColumnSelectors $colSelector, $start, $end, $whHosp, $whAssoc, $numberAssocs, $local, $visitFee, $statsOnly, $rescGroup, $labels) {

    // get session instance
    $uS = Session::getInstance();

    $categories = readGenLookupsPDO($dbh, $rescGroup[2], 'Description');
        // add default category
    $categories[] = array(0=>'', 1=>'(default)');


    $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

    // Make titles for all the rates
    $rateTitles = RoomRate::makeDescriptions($dbh);
    
    $guestNightsSql = "0 as `Actual_Guest_Nights`, 0 as `PI_Guest_Nights`,";
    
    if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
    	$guestNightsSql = "CASE WHEN DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start') THEN 0
        WHEN DATE(v.Span_Start) >= DATE('$end') THEN 0
        ELSE (SELECT SUM(DATEDIFF(CASE WHEN DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) > DATE('$end')
        THEN DATE('$end') ELSE DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) END,
        CASE WHEN DATE(s.Span_Start_Date) < DATE('$start') THEN DATE('$start') ELSE DATE(s.Span_Start_Date) END))
        FROM stays s WHERE s.idVisit = v.idVisit AND s.Visit_Span = v.Span)
    	END AS `Actual_Guest_Nights`,
    	CASE WHEN DATE(v.Span_Start) >= DATE('$start') THEN 0 WHEN DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start')
    	THEN (SELECT SUM(DATEDIFF(DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))), DATE(s.Span_Start_Date)))
    	FROM stays s WHERE s.idVisit = v.idVisit AND s.Visit_Span = v.Span)ELSE (SELECT SUM(DATEDIFF(CASE
      	WHEN DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) > DATE('$start') THEN DATE('$start')
      	ELSE DATE(IFNULL(s.Span_End_Date, datedefaultnow(v.Expected_Departure))) END, DATE(s.Span_Start_Date)))
        FROM stays s WHERE s.idVisit = v.idVisit AND s.Visit_Span = v.Span) END AS `PI_Guest_Nights`, ";
    }

    $query = "select
    v.idVisit,
    v.Span,
    v.idPrimaryGuest,
    ifnull(hs.idPatient, 0) as idPatient,
    v.idResource,
    v.Expected_Departure,
    ifnull(v.Actual_Departure, '') as Actual_Departure,
    v.Arrival_Date,
    v.Span_Start,
    ifnull(v.Span_End, '') as Span_End,
    v.Pledged_Rate,
    v.Expected_Rate,
    v.Rate_Category,
    v.idRoom_Rate,
    v.Status,
    v.Rate_Glide_Credit,
    DATEDIFF(DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))),DATE(v.Span_Start)) as `Visit_Age`,
    CASE
        WHEN
            DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start')
        THEN 0
        WHEN
            DATE(v.Span_Start) >= DATE('$end')
        THEN 0
        ELSE
            DATEDIFF(
                CASE
                    WHEN
                        DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) > DATE('$end')
                    THEN
                        DATE('$end')
                    ELSE DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure)))
                END,
                CASE
                    WHEN DATE(v.Span_Start) < DATE('$start') THEN DATE('$start')
                    ELSE DATE(v.Span_Start)
                END
            )
        END AS `Actual_Month_Nights`,
    CASE
        WHEN DATE(v.Span_Start) >= DATE('$start') THEN 0
        WHEN
            DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('$start')
        THEN
            DATEDIFF(DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))),
                    DATE(v.Span_Start))
        ELSE DATEDIFF(CASE
                    WHEN
                        DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) > DATE('$start')
                    THEN
                        DATE('$start')
                    ELSE DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure)))
                END,
                DATE(v.Span_Start))
    END AS `Pre_Interval_Nights`,

	$guestNightsSql

    ifnull(rv.Visit_Fee, 0) as `Visit_Fee_Amount`,
    ifnull(n.Name_Last,'') as Name_Last,
    ifnull(n.Name_First,'') as Name_First,
    concat(ifnull(na.Address_1, ''), '', ifnull(na.Address_2, ''))  as pAddr,
    ifnull(na.City, '') as pCity,
    ifnull(na.County, '') as pCounty,
    ifnull(na.State_Province, '') as pState,
    ifnull(na.Country_Code, '') as pCountry,
    ifnull(na.Postal_Code, '') as pZip,
    ifnull(na.Bad_Address, '') as pBad_Address,
    ifnull(rm.Title, '') as Title,
    ifnull(np.Name_Last,'') as Patient_Last,
    ifnull(np.Name_First,'') as Patient_First,
    ifnull(np.BirthDate, '') as pBirth,
    ifnull(nd.Name_Last,'') as Doctor_Last,
    ifnull(nd.Name_First,'') as Doctor_First,
    ifnull(hs.idPsg, 0) as idPsg,
    ifnull(hs.idHospital, 0) as idHospital,
    ifnull(hs.idAssociation, 0) as idAssociation,
    ifnull(nra.Name_Full, '') as Referral_Agent,
    ifnull(g.Description, hs.Diagnosis) as Diagnosis,
    ifnull(gl.Description, '') as Location,
    ifnull(rm.Rate_Code, '') as Rate_Code,
    ifnull(rm.Category, '') as Category,
    ifnull(rm.Type, '') as Type,
    ifnull(rm.Report_Category, '') as Report_Category,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ") and i.Sold_To_Id != " . $uS->subsidyId . "  and i.Order_Number = v.idVisit),
            0) as `AmountPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Type_Id = " . ItemType::Tax . " and il.Source_Item_Id in ( " . ItemId::Lodging . ", " . ItemId::LodgingReversal . ") and i.Order_Number = v.idVisit),
            0) as `TaxPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id = " . ItemId::LodgingDonate . " and i.Order_Number = v.idVisit),
            0) as `ContributionPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
            LEFT JOIN
        name_volunteer2 nv ON i.Sold_To_Id = nv.idName AND nv.Vol_Category = 'Vol_Type' AND nv.Vol_Code = '" . VolMemberType::BillingAgent . "'
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ") and ifnull(nv.idName, 0) > 0 and i.Sold_To_Id != " . $uS->subsidyId . " and i.Order_Number = v.idVisit),
            0) as `ThrdPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id in (" . ItemId::Discount . ", " . ItemId::Waive . ") and i.Order_Number = v.idVisit),
            0) as `HouseDiscount`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
    where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id = " . ItemId::AddnlCharge . " and i.Order_Number = v.idVisit),
            0) as `AddnlPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
    where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Type_Id = " . ItemType::Tax . " and il.Source_Item_Id = " . ItemId::AddnlCharge . " and  i.Order_Number = v.idVisit),
            0) as `AddnlTaxPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
    where il.Deleted = 0 and i.Deleted = 0 and il.Item_Id = " . ItemId::AddnlCharge . " and i.Order_Number = v.idVisit),
            0) as `AddnlCharged`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status = '" . InvoiceStatus::Unpaid . "' and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ") and i.Order_Number = v.idVisit),
            0) as `AmountPending`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status = '" . InvoiceStatus::Unpaid . "' and il.Type_Id = " . ItemType::Tax . "  and il.Source_Item_Id in (" . ItemId::Lodging . ", " . ItemId::LodgingReversal . ") and i.Order_Number = v.idVisit),
            0) as `TaxPending`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
    where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id = " . ItemId::VisitFee . " and i.Order_Number = v.idVisit),
            0) as `VisitFeePaid`
from
    visit v
        left join
    reservation rv ON v.idReservation = rv.idReservation
        left join
    resource_room rr ON v.idResource = rr.idResource
        left join
    room rm ON rr.idRoom = rm.idRoom
        left join
    name n ON v.idPrimaryGuest = n.idName
        left join
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
        left join
    name np ON hs.idPatient = np.idName
        left join
    name nd ON hs.idDoctor = nd.idName
        left join
    name nra ON hs.idReferralAgent = nra.idName
        left join
    gen_lookups g ON g.`Table_Name` = 'Diagnosis' and g.`Code` = hs.Diagnosis
        left join
    gen_lookups gl ON gl.`Table_Name` = 'Location' and gl.`Code` = hs.Location
        left join
    name_address na on ifnull(hs.idPatient, 0) = na.idName and np.Preferred_Mail_Address = na.Purpose
where
    DATE(v.Span_Start) < DATE('$end')
    and v.idVisit in (select
        idVisit
        from
            visit
        where
            `Status` <> 'p'
                and DATE(Arrival_Date) <= DATE('$end')
                and DATE(ifnull(Span_End,
                    case
                        when now() > Expected_Departure then now()
                        else Expected_Departure
                end)) >= DATE('$start')) "
    . $whHosp . $whAssoc . " order by v.idVisit, v.Span";


    $tbl = new HTMLTable();
    $sml = null;
    $reportRows = 0;


    $fltrdTitles = $colSelector->getFilteredTitles();
    $fltrdFields = $colSelector->getFilteredFields();
    $header = array();
    
    if ($local) {

        $th = '';

        foreach ($fltrdTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }
        $tbl->addHeaderTr($th);

    } else {

        //ini_set('memory_limit', "380M");
        ini_set('max_execution_time', "60");
        $reportRows = 1;

        $fileName = 'VisitReport';
        $writer = new ExcelHelper($fileName);
        $types = [
            's'=>'string',
            'n'=>'integer',
            'money'=>'money', // '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)'
            'date'=>'MM/DD/YYYY'
        ];
        
        
        //build header
        $colWidths = array();
        
        
        foreach($fltrdFields as $field){
              if($field[5] != "" && $field[5] == '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)'){ //if format is money
                  $header[$field[0]] = $types['s'];
                  $colWidths[] = 15;
             }elseif(isset($field[7]) && $field[7] == "date"){ //if format is date
                 $header[$field[0]] = $types['date'];
                 $colWidths[] = 15;
             }elseif($field[4] == 'n'){ //if format is integer
                 $header[$field[0]] = 'integer';
                 $colWidths[] = 10;
             }else{ //otherwise set format as string
                $header[$field[0]] = 'string';
                $colWidths[] = 20;
             }
        }
        
        try{
            $hdrStyle = $writer->getHdrStyle($colWidths);
            $writer->writeSheetHeader('Sheet1', $header, $hdrStyle);
        }catch(\Exception $e){
            $writer->download();
        }
        
        $reportRows++;

    }

    $curVisit = 0;
    $curRoom = 0;
    $curRate = '';
    $curAmt = 0;

    $totalCharged = 0;
    $totalVisitFee = 0;
    $totalLodgingCharge = 0;
    //$totalFullCharge = 0;
    $totalAddnlCharged = 0;

    $totalAddnlTax = 0;
    $totalTaxCharged = 0;
    $totalTaxPaid = 0;
    $totalTaxPending = 0;

    $totalPaid = 0;
    $totalHousePaid = 0;
    $totalAmtPending = 0;
    $totalGuestPaid = 0;
    $totalthrdPaid = 0;
    $totalSubsidy = 0;
    $totalUnpaid = 0;
    //$totalAddnlPaid = 0;
    $totalDonationPaid = 0;

    $totalNights = 0;
    $totalGuestNights = 0;
    $totalDays = 0;

    $totalCatNites[] = array();

    foreach ($categories as $c) {
        $totalCatNites[$c[0]] = 0;
    }

    $visit = array();
    $savedr = array();
    $nites = array();

    //$reportStartDT = new DateTime($start . ' 00:00:00');
    $reportEndDT = new \DateTime($end . ' 00:00:00');
    $now = new \DateTime();
    $now->setTime(0, 0, 0);

    $vat = new ValueAddedTax($dbh);
    
    $dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);
    
    $stmt = $dbh->query($query);

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        // records ordered by idVisit.
        if ($curVisit != $r['idVisit']) {

            // If i did not just start
            if (count($visit) > 0 && $visit['nit'] > 0) {

                $totalLodgingCharge += $visit['chg'];
                $totalAddnlCharged += ($visit['addch']);

                $totalTaxCharged += $visit['taxcgd'];
                $totalAddnlTax += $visit['adjchtx'];
                $totalTaxPaid += $visit['taxpd'];
                $totalTaxPending += $visit['taxpndg'];

                if ($visit['nit'] > $uS->VisitFeeDelayDays) {
                    $totalVisitFee += $visit['vfa'];
                }

                $totalCharged += $visit['chg'];
                //$totalFullCharge += $visit['fcg'];
                $totalAmtPending += $visit['pndg'];
                $totalNights += $visit['nit'];
                $totalGuestNights += $visit['gnit'];

                // Set expected departure to now if earlier than "today"
                $expDepDT = new \DateTime($savedr['Expected_Departure']);
                $expDepDT->setTime(0,0,0);

                if ($expDepDT < $now) {
                    $expDepStr = $now->format('Y-m-d');
                } else {
                    $expDepStr = $expDepDT->format('Y-m-d');
                }

                $paid = $visit['gpd'] + $visit['thdpd'] + $visit['hpd'];
                $unpaid = ($visit['chg'] + $visit['preCh']) - $paid;
                $preCharge = $visit['preCh'];
                $charged = $visit['chg'];

                // Reduce all payments by precharge
                if ($preCharge >= $visit['gpd']) {
                    $preCharge -= $visit['gpd'];
                    $visit['gpd'] = 0;
                } else if ($preCharge > 0) {
                    $visit['gpd'] -= $preCharge;
                }

                if ($preCharge >= $visit['thdpd']) {
                    $preCharge -= $visit['thdpd'];
                    $visit['thdpd'] = 0;
                } else if ($preCharge > 0) {
                    $visit['thdpd'] -= $preCharge;
                }

                if ($preCharge >= $visit['hpd']) {
                    $preCharge -= $visit['hpd'];
                    $visit['hpd'] = 0;
                } else if ($preCharge > 0) {
                    $visit['hpd'] -= $preCharge;
                }


                $dPaid = $visit['hpd'] + $visit['gpd'] + $visit['thdpd'];

                $departureDT = new \DateTime($savedr['Actual_Departure'] != '' ? $savedr['Actual_Departure'] : $expDepStr);

                if ($departureDT > $reportEndDT) {

                    // report period ends before the visit
                    $visit['day'] = $visit['nit'];

                    if ($unpaid < 0) {
                        $unpaid = 0;
                    }

                    if ($visit['gpd'] >= $charged) {
                        $dPaid -= $visit['gpd'] - $charged;
                        $visit['gpd'] = $charged;
                        $charged = 0;
                    } else if ($charged > 0) {
                        $charged -= $visit['gpd'];
                    }

                    if ($visit['thdpd'] >= $charged) {
                        $dPaid -= $visit['thdpd'] - $charged;
                        $visit['thdpd'] = $charged;
                        $charged = 0;
                    } else if ($charged > 0) {
                        $charged -= $visit['thdpd'];
                    }

                    if ($visit['hpd'] >= $charged) {
                        $dPaid -= $visit['hpd'] - $charged;
                        $visit['hpd'] = $charged;
                        $charged = 0;
                    } else if ($charged > 0) {
                        $charged -= $visit['hpd'];
                    }

                } else {
                    // visit ends in this report period
                    $visit['day'] = $visit['nit'] + 1;
                }

                $totalDays += $visit['day'];
                $totalPaid += $dPaid;
                $totalHousePaid += $visit['hpd'];
                $totalGuestPaid += $visit['gpd'];
                $totalthrdPaid += $visit['thdpd'];
                //$totalAddnlPaid += $visit['addpd'];
                $totalDonationPaid += $visit['donpd'];
                $totalUnpaid += $unpaid;
                $totalSubsidy += ($visit['fcg'] - $visit['chg']);
                $nites[] = $visit['nit'];

                if (!$statsOnly) {
                    try{
                        doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $tbl, $local, $writer, $header, $reportRows, $rateTitles, $uS, $visitFee);
                    }catch(\Exception $e){
                        if(isset($writer)){
                            die();
                        }
                    }
                }
            }

            $curVisit = $r['idVisit'];
            $curRate = '';
            $curRateId = 0;
            $curAdj = 0;
            $curAmt = 0;
            $curRoom = 0;

            $addChgTax = 0;
            $lodgeTax = 0;

            $taxSums = $vat->getTaxedItemSums($r['idVisit'], $r['Visit_Age']);

            if (isset($taxSums[ItemId::AddnlCharge])) {
                $addChgTax = $taxSums[ItemId::AddnlCharge];
            }

            if (isset($taxSums[ItemId::Lodging])) {
                $lodgeTax = $taxSums[ItemId::Lodging];
            }


            $visit = array(
                'id' => $r['idVisit'],
                'chg' => 0, // charges
                'fcg' => 0, // Flat Rate Charge (For comparison)
                'adj' => 0,
                'taxcgd' => 0,
                'gpd' => $r['AmountPaid'] - $r['ThrdPaid'],
                'pndg' => $r['AmountPending'],
                'taxpndg' => $r['TaxPending'],
                'hpd' => abs($r['HouseDiscount']),
                'thdpd' => $r['ThrdPaid'],
                'addpd' => $r['AddnlPaid'],
                'addtxpd'=> $r['AddnlTaxPaid'],
                'taxpd' => $r['TaxPaid'],
                'addch' => $r['AddnlCharged'],
                'adjchtx' => round($r['AddnlCharged'] * $addChgTax, 2),
                'donpd' => $r['ContributionPaid'],
                'vfpd' => $r['VisitFeePaid'],  // Visit fees paid
                'plg' => 0, // Pledged rate
                'vfa' => $r['Visit_Fee_Amount'], // visit fees amount
                'nit' => 0, // Nights
                'day' => 0,  // Days
                'gnit' => 0, // guest nights
                'pin' => 0, // Pre-interval nights
                'gpin' => 0, // Guest pre-interval nights
                'preCh' => 0,
                'rmc' => 0, // Room change counter
                'rtc' => 0  // Rate Category counter
                );

        }

        // Count rate changes
        if ($curRateId != $r['idRoom_Rate']
                || ($curRate == RoomRateCategories::Fixed_Rate_Category && $curAmt != $r['Pledged_Rate'])
                || ($curRate != RoomRateCategories::Fixed_Rate_Category && $curAdj != $r['Expected_Rate'])) {

            $curRate = $r['Rate_Category'];
            $curRateId = $r['idRoom_Rate'];
            $curAdj = $r['Expected_Rate'];
            $curAmt = $r['Pledged_Rate'];
            $visit['rateId'] = $r['idRoom_Rate'];
            $visit['rtc']++;
        }

        // Count room changes
        if ($curRoom != $r['idResource']) {
            $curRoom = $r['idResource'];
            $visit['rmc']++;
        }

        $adjRatio = (1 + $r['Expected_Rate']/100);
        $visit['adj'] = $r['Expected_Rate'];

        //  Add up any pre-interval charges
        if ($r['Pre_Interval_Nights'] > 0) {

            // collect all pre-charges
            $priceModel->setCreditDays($r['Rate_Glide_Credit']);
            $visit['preCh'] += ($priceModel->amountCalculator($r['Pre_Interval_Nights'], $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $r['PI_Guest_Nights']) * $adjRatio);

        }


        $days = $r['Actual_Month_Nights'];
        $gdays = $r['Actual_Guest_Nights'];

        $visit['nit'] += $days;
        $totalCatNites[$r[$rescGroup[0]]] += $days;
        $visit['gnit'] += $gdays;
        $visit['pin'] += $r['Pre_Interval_Nights'];
        $visit['gpin'] += $r['PI_Guest_Nights'];

        if ($days > 0) {

            $priceModel->setCreditDays($r['Rate_Glide_Credit'] + $r['Pre_Interval_Nights']);
            $visit['chg'] += ($priceModel->amountCalculator($days, $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $gdays) * $adjRatio);
            $visit['taxcgd'] += round($visit['chg'] * $lodgeTax, 2);

            $priceModel->setCreditDays($r['Rate_Glide_Credit'] + $r['Pre_Interval_Nights']);
            $fullCharge = ($priceModel->amountCalculator($days, 0, RoomRateCategories::FullRateCategory, $uS->guestLookups['Static_Room_Rate'][$r['Rate_Code']][2], $gdays));

            if ($adjRatio > 0) {
                // Only adjust when the charge will be more.
                $fullCharge = $fullCharge * $adjRatio;
            }

            // Only Positive values.
            $visit['fcg'] += ($fullCharge > 0 ? $fullCharge : 0);
        }

        $savedr = $r;

    }   // End of while
    
    $dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE);
    


    // Print the last visit.
    if (count($savedr) > 0 && $visit['nit'] > 0) {

        $totalLodgingCharge += $visit['chg'];
        $totalAddnlCharged += ($visit['addch']);
        $totalVisitFee += $visit['vfa'];
        $totalCharged += $visit['chg'];
        //$totalFullCharge += $visit['fcg'];
        $totalAmtPending += $visit['pndg'];
        $totalNights += $visit['nit'];
        $totalGuestNights += $visit['gnit'];

        $totalTaxCharged += $visit['taxcgd'];
        $totalAddnlTax += $visit['adjchtx'];
        $totalTaxPaid += $visit['taxpd'];
        $totalTaxPending += $visit['taxpndg'];

        // Set expected departure to now if earlier than "today"
        $expDepDT = new \DateTime($savedr['Expected_Departure']);
        $expDepDT->setTime(0,0,0);

        if ($expDepDT < $now) {
            $expDepStr = $now->format('Y-m-d');
        } else {
            $expDepStr = $expDepDT->format('Y-m-d');
        }

        $paid = $visit['gpd'] + $visit['thdpd'] + $visit['hpd'];

        $unpaid = ($visit['chg'] + $visit['preCh']) - $paid;
        $preCharge = $visit['preCh'];
        $charged = $visit['chg'];

        // Reduce all payments by precharge
        if ($preCharge >= $visit['gpd']) {
            $preCharge -= $visit['gpd'];
            $visit['gpd'] = 0;
        } else if ($preCharge > 0) {
            $visit['gpd'] -= $preCharge;
        }

        if ($preCharge >= $visit['thdpd']) {
            $preCharge -= $visit['thdpd'];
            $visit['thdpd'] = 0;
        } else if ($preCharge > 0) {
            $visit['thdpd'] -= $preCharge;
        }

        if ($preCharge >= $visit['hpd']) {
            $preCharge -= $visit['hpd'];
            $visit['hpd'] = 0;
        } else if ($preCharge > 0) {
            $visit['hpd'] -= $preCharge;
        }


        $dPaid = $visit['hpd'] + $visit['gpd'] + $visit['thdpd'];

        $departureDT = new \DateTime($savedr['Actual_Departure'] != '' ? $savedr['Actual_Departure'] : $expDepStr);

        if ($departureDT > $reportEndDT) {

            // report period ends before the visit
            $visit['day'] = $visit['nit'];

            if ($unpaid < 0) {
                $unpaid = 0;
            }

            if ($visit['gpd'] >= $charged) {
                $dPaid -= $visit['gpd'] - $charged;
                $visit['gpd'] = $charged;
                $charged = 0;
            } else if ($charged > 0) {
                $charged -= $visit['gpd'];
            }

            if ($visit['thdpd'] >= $charged) {
                $dPaid -= $visit['thdpd'] - $charged;
                $visit['thdpd'] = $charged;
                $charged = 0;
            } else if ($charged > 0) {
                $charged -= $visit['thdpd'];
            }

            if ($visit['hpd'] >= $charged) {
                $dPaid -= $visit['hpd'] - $charged;
                $visit['hpd'] = $charged;
                $charged = 0;
            } else if ($charged > 0) {
                $charged -= $visit['hpd'];
            }

        } else {
            // visit ends in this report period
            $visit['day'] = $visit['nit'] + 1;
        }


        $totalDays += $visit['day'];
        $totalPaid += $dPaid;
        $totalHousePaid += $visit['hpd'];
        $totalGuestPaid += $visit['gpd'];
        $totalthrdPaid += $visit['thdpd'];
        //$totalAddnlPaid += $visit['addpd'];
        $totalDonationPaid += $visit['donpd'];
        $totalUnpaid += $unpaid;
        $totalSubsidy += ($visit['fcg'] - $visit['chg']);
        $nites[] = $visit['nit'];

        if (!$statsOnly) {
            if(!$local){
                try{
                    doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $tbl, $local, $writer, $header, $reportRows, $rateTitles, $uS, $visitFee);
                }catch(\Exception $e){
                    die();
                }
            }else{
                doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $tbl, $local, $writer, $header, $reportRows, $rateTitles, $uS, $visitFee);
            }
        }
    }


    // Finalize and print.
    if ($local) {

        $avDailyFee = 0;
        $avGuestFee = 0;

        if ($totalNights > 0) {
            $avDailyFee = $totalCharged / $totalNights;
        }

        if ($totalGuestNights > 0 && $uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
            $avGuestFee = $totalCharged / $totalGuestNights;
        }


        // totals footer
        $tr = '';
        foreach ($fltrdFields as $f) {

            $entry = '';

            switch ($f[1]) {
                case 'nights':
                    $entry = $totalNights;
                    break;

                case 'days':
                    $entry = $totalDays;
                    break;

                case 'gnights':
                    $entry = $totalGuestNights;
                    break;

                case 'lodg':
                    $entry = '$' . number_format($totalLodgingCharge,2);
                    break;

                case 'visitFee':
                    $entry = '$' . number_format($totalVisitFee,2);
                    break;

                case 'adjch':
                    $entry = '$' . number_format($totalAddnlCharged,2);
                    break;

                case 'adjchtx':
                    $entry = '$' . number_format($totalAddnlTax,2);
                    break;

                case 'taxcgd':
                    $entry = '$' . number_format($totalTaxCharged,2);
                    break;

                case 'taxpd':
                    $entry = '$' . number_format($totalTaxPaid,2);
                    break;

                case 'taxpndg':
                    $entry = '$' . number_format($totalTaxPending,2);
                    break;

                case 'totch':
                    $entry = '$' . number_format($totalCharged,2);
                    break;

                case 'gpaid':
                    $entry = '$' . number_format($totalGuestPaid,2);
                    break;

                case 'thdpaid':
                    $entry = '$' . number_format($totalthrdPaid,2);
                    break;

                case 'hpaid':
                    $entry = '$' . number_format($totalHousePaid,2);
                    break;

                case 'totpd':
                    $entry = '$' . number_format($totalPaid,2);
                    break;

                case 'unpaid':
                    $entry = '$' . number_format($totalUnpaid,2);
                    break;

                case 'pndg':
                    $entry = '$' . number_format($totalAmtPending,2);
                    break;

                case 'sub':
                	$entry = '$' . number_format($totalSubsidy,2);
                	break;
                	
                case 'rateAdj':
                	$entry = ' ';
                	break;
                	
                case 'meanRate':
                	$entry = '$' . number_format($avDailyFee,2);
                	break;

                case 'meanGstRate':
                	$entry = '$' . number_format($avGuestFee,2);
                	break;

                case 'donpd':
                    $entry = '$' . number_format($totalDonationPaid,2);
                    break;
            }

            if ($entry != '') {
                $entry = HTMLContainer::generateMarkup('p', $entry, Array('style'=>'font-weight:bold;text-decoration: underline;'));
            }

            $tr .= HTMLTable::makeTd($entry . ($statsOnly ? '' : ' ' . $f[0]), array('style'=>'vertical-align:top;'));
        }

        if ($statsOnly) {
            $tbl->addBodyTr($tr);
        } else {
            $tbl->addFooterTr($tr);
        }

        // Main data table
        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display compact'));

        // Stats panel
        $statsTable = statsPanel($dbh, $nites, $totalCatNites, $start, $end, $categories, $avDailyFee, $rescGroup[0], $uS->siteName);

        return array('data'=>$dataTable, 'stats'=>$statsTable);

    } else {
        $writer->download();

    }

}


// Get labels
$labels = Labels::getLabels();
$paymentMarkup = '';
$receiptMarkup = '';

$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Visit Report Detail', array('style'=>'margin-top: .5em;'))
        .HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }
    }

} catch (RuntimeException $ex) {
    $paymentMarkup = $ex->getMessage();
}


$dataTable = '';
$statsTable = '';
$errorMessage = '';
$cFields = array();
$rescGroups = readGenLookupsPDO($dbh, 'Room_Group');
$useTaxes = FALSE;

$tstmt = $dbh->query("Select count(idItem) from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = " . ItemType::Tax . " where i.Deleted = 0");
$taxItems = $tstmt->fetchAll(\PDO::FETCH_NUM);
if ($taxItems[0][0] > 0) {
    $useTaxes = TRUE;
}


$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();
$filter->createResoourceGroups($rescGroups, $uS->CalResourceGroupBy);

// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel Style, td parms
$cFields[] = array('Visit Id', 'idVisit', 'checked', 'f', 'n', '', array('style'=>'text-align:center;'));
$cFields[] = array("Primary ".$labels->getString('MemberType', 'guest', 'Guest'), 'idPrimaryGuest', 'checked', '', 's', '', array());
$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient'), 'idPatient', 'checked', '', 's', '', array());

// Patient address.
if ($uS->PatientAddr) {

    $pFields = array('pAddr', 'pCity');
    $pTitles = array($labels->getString('MemberType', 'patient', 'Patient').' Address', $labels->getString('MemberType', 'patient', 'Patient').' City');

    if ($uS->county) {
        $pFields[] = 'pCounty';
        $pTitles[] = $labels->getString('MemberType', 'patient', 'Patient').' County';
    }

    $pFields = array_merge($pFields, array('pState', 'pCountry', 'pZip'));
    $pTitles = array_merge($pTitles, array($labels->getString('MemberType', 'patient', 'Patient').' State', $labels->getString('MemberType', 'patient', 'Patient').' Country', $labels->getString('MemberType', 'patient', 'Patient').' Zip'));

    $cFields[] = array($pTitles, $pFields, '', '', 's', '', array());
}

if ($uS->ShowBirthDate) {
    $cFields[] = array($labels->getString('MemberType', 'patient', 'Patient').' DOB', 'pBirth', '', '', 'n', "", array(), 'date');
}

// Referral Agent
if ($uS->ReferralAgent) {
    $cFields[] = array($labels->getString('hospital', 'referralAgent', 'Ref. Agent'), 'Referral_Agent', 'checked', '', 's', '', array());
}

// Hospital
if (count($filter->getHospitals()) > 1) {

    if (count($filter->getAList()) > 0) {
        $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital')." / Assoc", 'hospitalAssoc', 'checked', '', 's', '', array());
    } else {
        $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'hospitalAssoc', 'checked', '', 's', '', array());
    }
}

if ($uS->Doctor) {
    $cFields[] = array("Doctor", 'Doctor', '', '', 's', '', array());
}

$locations = readGenLookupsPDO($dbh, 'Location');
if (count($locations) > 0) {
    $cFields[] = array($labels->getString('hospital', 'location', 'Location'), 'Location', 'checked', '', 's', '', array());
}

$diags = readGenLookupsPDO($dbh, 'Diagnosis');
if (count($diags) > 0) {
    $cFields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', 'checked', '', 's', '', array());
}


$cFields[] = array("Arrive", 'Arrival', 'checked', '', 'n', '', array(), 'date');
$cFields[] = array("Depart", 'Departure', 'checked', '', 'n', '', array(), 'date');
$cFields[] = array("Room", 'Title', 'checked', '', 's', '', array('style'=>'text-align:center;'));

if ($uS->VisitFee) {
    $cFields[] = array($labels->getString('statement', 'cleaningFeeLabel', "Clean Fee"), 'visitFee', 'checked', '', 's', '', array('style'=>'text-align:right;'));
}

$adjusts = readGenLookupsPDO($dbh, 'Addnl_Charge');
if (count($adjusts) > 0) {
    $cFields[] = array("Addnl Charge", 'adjch', 'checked', '', 's', '', array('style'=>'text-align:right;'));

    if ($useTaxes) {
        $cFields[] = array("Addnl Tax", 'adjchtx', 'checked', '', 's', '', array('style'=>'text-align:right;'));
    }
}


$cFields[] = array("Nights", 'nights', 'checked', '', 'n', '', array('style'=>'text-align:center;'));
$cFields[] = array("Days", 'days', '', '', 'n', '', array('style'=>'text-align:center;'));

$amtChecked = 'checked';

if ($uS->RoomPriceModel !== ItemPriceCode::None) {

    if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {

        $cFields[] = array($labels->getString('MemberType', 'guest', 'Guest')." Nights", 'gnights', 'checked', '', 'n', '', array('style'=>'text-align:center;'));
        $cFields[] = array("Rate Per ".$labels->getString('MemberType', 'guest', 'Guest'), 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());
        $cFields[] = array("Mean Rate Per ".$labels->getString('MemberType', 'guest', 'Guest'), 'meanGstRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

    } else {

        $cFields[] = array("Rate", 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());
        
        if ($uS->RoomPriceModel == ItemPriceCode::NdayBlock) {
        	$cFields[] = array("Adj. Rate", 'rateAdj', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
        }
        
        $cFields[] = array("Mean Rate", 'meanRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    }

    $cFields[] = array("Lodging Charge", 'lodg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

    if ($useTaxes) {
        $cFields[] = array('Tax Charged', 'taxcgd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    }

    $cFields[] = array($labels->getString('MemberType', 'guest', 'Guest')." Paid", 'gpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("3rd Party Paid", 'thdpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("House Paid", 'hpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("Lodging Paid", 'totpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

    if ($useTaxes) {
        $cFields[] = array('Tax Paid', 'taxpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    }

    $cFields[] = array("Unpaid", 'unpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("Pending", 'pndg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

    if ($useTaxes) {
        $cFields[] = array('Tax Pending', 'taxpndg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    }

    if ($uS->RoomPriceModel != ItemPriceCode::NdayBlock) {
    	$cFields[] = array("Rate Subsidy", 'sub', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    }
    
    $cFields[] = array("Contribution", 'donpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
}

$fieldSets = ReportFieldSet::listFieldSets($dbh, 'visit', true);
$fieldSetSelection = (isset($_REQUEST['fieldset']) ? $_REQUEST['fieldset']: '');

$defaultFields = array();

foreach($cFields as $field){
    if($field[2] == 'checked'){
        $defaultFields[] = $field[1];
    }
}

$colSelector = new ColumnSelectors($cFields, 'selFld', true, $fieldSets, $fieldSetSelection);


if (isset($_POST['btnHere']) || isset($_POST['btnExcel']) || isset($_POST['btnStatsOnly'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    $statsOnly = FALSE;
    if (isset($_POST['btnStatsOnly'])) {
        $statsOnly = TRUE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);

    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();
    $filter->loadSelectedResourceGroups();

    // Hospitals
    $whHosp = '';
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

    if ($filter->getReportStart() != '' && $filter->getReportEnd() != '') {

        $tblArray = doReport($dbh, $colSelector, $filter->getReportStart(), $filter->getReportEnd(), $whHosp, $whAssoc, count($filter->getAList()), $local, $uS->VisitFee, $statsOnly, $rescGroups[$filter->getSelectedResourceGroups()],$labels);

        $dataTable = $tblArray['data'];
        $statsTable = $tblArray['stats'];
        $mkTable = 1;


        $headerTable .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($filter->getReportEnd())));

        $hospitalTitles = '';
        $hospList = $filter->getHospitals();

        foreach ($filter->getSelectedAssocs() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }
        foreach ($filter->getSelectedHosptials() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }

        if ($hospitalTitles != '') {
            $h = trim($hospitalTitles);
            $hospitalTitles = substr($h, 0, strlen($h) - 1);
            $headerTable .= HTMLContainer::generateMarkup('p', $labels->getString('hospital', 'hospital', 'Hospital').'s: ' . $hospitalTitles);
        } else {
            $headerTable .= HTMLContainer::generateMarkup('p', 'All '.$labels->getString('hospital', 'hospital', 'Hospital').'s');
        }

    } else {
        $errorMessage = 'Missing the dates.';
    }

}

// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup(array('style'=>'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));
$roomGroupMarkup = $filter->resourceGroupsMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;margin-left:5px', 'id'=>'includeFields'));

$dateFormat = $labels->getString("momentFormats", "report", "MMM D, YYYY");

if ($uS->CoTod) {
    $dateFormat .= ' H:mm';
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>

<script type="text/javascript">
    var fixedRate = '<?php echo RoomRateCategories::Fixed_Rate_Category; ?>';
    var rctMkup, pmtMkup;
    var dateFormat = '<?php echo $dateFormat; ?>';
    $(document).ready(function() {
        var makeTable = '<?php echo $mkTable; ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
		pmtMkup = $('#pmtMkup').val(),
        rctMkup = $('#rctMkup').val();
        <?php echo $filter->getTimePeriodScript(); ?>;

        $('#btnHere, #btnExcel, #btnStatsOnly, #cbColClearAll, #cbColSelAll').button();
        $('#btnHere, #btnExcel').click(function () {
            $('#paymentMessage').hide();
        });
        $('#cbColClearAll').click(function () {
            $('#selFld option').each(function () {
                $(this).prop('selected', false);
            });
        });
        $('#cbColSelAll').click(function () {
            $('#selFld option').each(function () {
                $(this).prop('selected', true);
            });
        });
        $('#keysfees').dialog({
            autoOpen: false,
            resizable: true,
            modal: true,
            close: function () {$('div#submitButtons').show();},
            open: function () {$('div#submitButtons').hide();}
        });
        $('#pmtRcpt').dialog({
            autoOpen: false,
            resizable: true,
            width: 530,
            modal: true,
            title: 'Payment Receipt'
        });
        $("#faDialog").dialog({
            autoOpen: false,
            resizable: true,
            width: 650,
            modal: true,
            title: 'Income Chooser'
        });

        $('.hhk-viewVisit').button();
        $('.hhk-viewVisit').click(function () {
            var vid = $(this).data('vid');
            var gid = $(this).data('gid');
            var span = $(this).data('span');

            var buttons = {
                "Show Statement": function() {
                    window.open('ShowStatement.php?vid=' + vid, '_blank');
                },
                "Show Registration Form": function() {
                    window.open('ShowRegForm.php?vid=' + vid + '&span=' + span, '_blank');
                },
                "Save": function() {
                    saveFees(gid, vid, span, false, 'VisitInterval.php');
                },
                "Cancel": function() {
                    $(this).dialog("close");
                }
            };
             viewVisit(gid, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
        });

        if (makeTable === '1') {
            $('div#printArea').css('display', 'block');

            $('#tblrpt').dataTable({
                'columnDefs': [
                    {'targets': columnDefs,
                     'type': 'date',
                     'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                    }
                 ],
                "displayLength": 50,
                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                "dom": '<"top"ilf>rt<"bottom"ilp><"clear">',
            });
            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
        }
        if (rctMkup !== '') {
            showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
        }
    if (pmtMkup !== '') {
        $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
    }

    $('#keysfees').mousedown(function (event) {
        var target = $(event.target);
        if ( target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

	$('#includeFields').fieldSets({'reportName': 'visit', 'defaultFields': <?php echo json_encode($defaultFields); ?>});

    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="paymentMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; display:none;" class="hhk-alert ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox"></div>

            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="VisitInterval.php" method="post">
                    <?php
                        echo $timePeriodMarkup;

                        if (count($filter->getHospitals()) > 1) {
                            echo $hospitalMarkup;
                        }
                        echo $roomGroupMarkup;
                        echo $columSelector;
                    ?>
                    <table style="width:100%; clear:both;">
                        <tr>
                            <td style="width:50%;"><span style="color:red;"><?php echo $errorMessage; ?></span></td>
                            <td><input type="submit" name="btnStatsOnly" id="btnStatsOnly" value="Stats Only"/></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div id="stats" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="padding:10px;clear:left;">
                <?php echo $statsTable; ?>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox" style="display:none; font-size: .8em; padding: 5px; padding-bottom:25px;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                    <?php echo $headerTable; ?>
                </div>
                <?php echo $dataTable; ?>
            </div>
        </div>
        <input  type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
        <input  type="hidden" id="pmtMkup" value='<?php echo $paymentMarkup; ?>' />
        <div id="keysfees" style="font-size: .9em;"></div>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        <div id="hsDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
        <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
        <form name="xform" id="xform" method="post"></form>
    </body>
</html>
