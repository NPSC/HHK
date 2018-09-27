<?php
/**
 * VisitInterval.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

require (CLASSES . 'ColumnSelectors.php');
require CLASSES . 'OpenXML.php';
require(CLASSES . 'Purchase/RoomRate.php');
require(HOUSE . 'Resource.php');
require(HOUSE . 'ReportFilter.php');


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


$config = new Config_Lite(ciCFG_FILE);

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();


function statsPanel(\PDO $dbh, $visitNites, $totalCatNites, $start, $end, $categories, $avDailyFee, $rescGroup) {

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

    return HTMLContainer::generateMarkup('h3', 'Report Statistics')
            . HTMLContainer::generateMarkup('p', 'These numbers are specific to this report\'s selected filtering parameters.')
            . $sTbl->generateMarkup();

}

/**
 * Prettify a row.
 *
 * @param array $r  db record row
 * @param array $visit
 * @param decimal $unpaid
 * @param \DateTime $departureDT
 * @param \HTMLTable $tbl
 * @param boolean $local  Flag for Excel output
 * @param \PHPExcel $sml
 * @param Object $reportRows  PHPExecl object
 * @param array $rateTitles  Room rates
 * @param \Session $uS
 * @param Boolean $visitFee  Flag to show/hide visit fees

 */
function doMarkup($fltrdFields, $r, $visit, $paid, $unpaid, \DateTime $departureDT, HTMLTable &$tbl, $local, &$sml, &$reportRows, $rateTitles, $uS, $visitFee = FALSE) {

    $arrivalDT = new DateTime($r['Arrival_Date']);

    if ($r['Rate_Category'] == RoomRateCategorys::Fixed_Rate_Category) {

        $r['rate'] = $r['Pledged_Rate'];

    } else if (isset($visit['rateId']) && isset($rateTitles[$visit['rateId']])) {

        $rateTxt = $rateTitles[$visit['rateId']];

        if ($visit['adj'] != 1) {
            $parts = explode('$', $rateTxt);

            if (count($parts) == 2) {
                $amt = floatval($parts[1]) * $visit['adj'];
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

    if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] != '(None)') {
        $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] . ' / ';
        $assoc = $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1];
    }
    if ($r['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']])) {
        $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
        $hosp = $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
    }

    $r['Doctor'] = $r['Doctor_Last'] . ($r['Doctor_First'] == '' ? '' : ', ' . $r['Doctor_First']);


    $r['hospitalAssoc'] = $hospital;
    $r['assoc'] = $assoc;
    $r['hosp'] = $hosp;

    $r['nights'] = $visit['nit'];
    $r['gnights'] = $visit['gnit'];
    $r['lodg'] = number_format($visit['chg'],2);


    $sub = $visit['fcg'] - $visit['chg'];
    $r['sub'] = ($sub == 0 ? '' : number_format($sub,2));

    $r['gpaid'] = ($visit['gpd'] == 0 ? '': number_format($visit['gpd'], 2));
    $r['hpaid'] = ($visit['hpd'] == 0 ? '': number_format($visit['hpd'], 2));
    $r['totpd'] = ($paid == 0 ? '' : number_format($paid,2));
    $r['unpaid'] = ($unpaid == 0 ? '' : number_format($unpaid,2));
    $r['adjch'] = ($visit['addpd'] == 0 ? '': number_format($visit['addpd'], 2));
    $r['pndg'] = ($visit['pndg'] == 0 ? '': number_format($visit['pndg'], 2));
    $r['thdpaid'] = ($visit['thdpd'] == 0 ? '': number_format($visit['thdpd'], 2));
    $r['donpd'] = ($visit['donpd'] == 0 ? '': number_format($visit['donpd'], 2));


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

    $addPaid = '';

    if ($visit['addch'] > 0 && $visit['addch'] <= $visit['addpd']) {

        $r['adjch'] = number_format($visit['addch'],2);
        $addPaid = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-circle-check', 'style'=>'float:left;', 'title'=>'Charges paid'));

    } else if ($visit['addch'] > 0) {

        $r['adjch'] = number_format($visit['addch'],2);

    } else {

        $r['adjch'] = '';
    }


    $changeRoomIcon = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-info', 'style'=>'float:left;', 'title'=>'Changed Rooms'));
    $changeRateIcon = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-info', 'style'=>'float:left;', 'title'=>'Room Rate Changed'));

    if ($local) {

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

        if ($addPaid != '') {
            $r['adjch'] = $addPaid . $r['adjch'];
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
        $r['Arrival'] = PHPExcel_Shared_Date::PHPToExcel($arrivalDT);
        $r['Departure'] = PHPExcel_Shared_Date::PHPToExcel($departureDT);
        $r['idPatient'] = $r['Patient_Last'] . ', ' . $r['Patient_First'];

        if ($r['pBirth'] != '') {
            $pBirthDT = new DateTime($r['pBirth']);
            $r['pBirth'] = PHPExcel_Shared_Date::PHPToExcel($pBirthDT);
        } else {
            $r['pBirth'] = '';
        }

        $n = 0;
        $flds = array();

        foreach ($fltrdFields as $f) {
            if ($r[$f[1]] == '' && $f[5] != '') {
                $f[5] = '';
                $f[4] = 's';
            }

            $flds[$n++] = array('type' => $f[4], 'value' => $r[$f[1]], 'style'=>$f[5]);
        }

        $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);

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
 * @return type
 */
function doReport(\PDO $dbh, ColumnSelectors $colSelector, $start, $end, $whHosp, $whAssoc, $numberAssocs, $local, $visitFee, $statsOnly, $rescGroup) {

    // get session instance
    $uS = Session::getInstance();

    $categories = readGenLookupsPDO($dbh, $rescGroup[2], 'Description');
        // add default category
    $categories[] = array(0=>'', 1=>'(default)');


    $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

    // Make titles for all the rates
    $rateTitles = RoomRate::makeDescriptions($dbh);

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
    v.idRoom_rate,
    v.Status,
    v.Rate_Glide_Credit,
    CASE
        WHEN
            DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure))) <= DATE('$start')
        THEN 0
        WHEN DATE(v.Span_Start) >= DATE('$end') THEN 0
        ELSE DATEDIFF(
                CASE
                    WHEN
                        DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure))) > DATE('$end')
                    THEN
                        DATE('$end')
                    ELSE DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure)))
                END,
                CASE
                    WHEN DATE(v.Span_Start) < DATE('$start') THEN DATE('$start')
                    ELSE DATE(v.Span_Start)
                END)
    END AS `Actual_Month_Nights`,

    CASE
        WHEN
            DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure))) <= DATE('$start')
        THEN 0
        WHEN DATE(v.Span_Start) >= DATE('$end') THEN 0
        ELSE (SELECT
                SUM(DATEDIFF(CASE
                                WHEN
                                    DATE(IFNULL(s.Span_End_Date, DATEDEFAULTNOW(v.Expected_Departure))) > DATE('$end')
                                THEN DATE('$end')
                                ELSE DATE(IFNULL(s.Span_End_Date, DATEDEFAULTNOW(v.Expected_Departure)))
                            END,
                            CASE
                                WHEN DATE(s.Span_Start_Date) < DATE('$start') THEN DATE('$start')
                                ELSE DATE(s.Span_Start_Date)
                            END))
            FROM
                stays s
            WHERE
                s.idVisit = v.idVisit
                    AND s.Visit_Span = v.Span)
    END AS `Actual_Guest_Nights`,

    CASE
        WHEN DATE(v.Span_Start) >= DATE('$start') THEN 0
        WHEN
            DATE(IFNULL(v.Span_End,
                        DATEDEFAULTNOW(v.Expected_Departure))) <= DATE('$start')
        THEN
            (SELECT
                    SUM(DATEDIFF(DATE(IFNULL(s.Span_End_Date, DATEDEFAULTNOW(v.Expected_Departure))),
                                DATE(s.Span_Start_Date)))
                FROM
                    stays s
                WHERE
                    s.idVisit = v.idVisit AND s.Visit_Span = v.Span)
        ELSE (SELECT
            SUM(DATEDIFF(CASE
                            WHEN
                                DATE(IFNULL(s.Span_End_Date, DATEDEFAULTNOW(v.Expected_Departure))) > DATE('$start')
                            THEN
                                DATE('$start')
                            ELSE DATE(IFNULL(s.Span_End_Date, DATEDEFAULTNOW(v.Expected_Departure)))
                        END,
                        DATE(s.Span_Start_Date)))
        FROM
            stays s
        WHERE
            s.idVisit = v.idVisit AND s.Visit_Span = v.Span)
    END AS `PI_Guest_Nights`,

    CASE
        WHEN DATE(v.Span_Start) >= DATE('$start') THEN 0
        WHEN
            DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure))) <= DATE('$start')
        THEN
            DATEDIFF(DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure))),
                    DATE(v.Span_Start))
        ELSE DATEDIFF(CASE
                    WHEN
                        DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure))) > DATE('$start')
                    THEN
                        DATE('$start')
                    ELSE DATE(IFNULL(v.Span_End, DATEDEFAULTNOW(v.Expected_Departure)))
                END,
                DATE(v.Span_Start))
    END AS `Pre_Interval_Nights`,

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
    ifnull(r.Title, '') as Title,
    ifnull(np.Name_Last,'') as Patient_Last,
    ifnull(np.Name_First,'') as Patient_First,
    ifnull(np.BirthDate, '') as pBirth,
    ifnull(nd.Name_Last,'') as Doctor_Last,
    ifnull(nd.Name_First,'') as Doctor_First,
    ifnull(hs.idPsg, 0) as idPsg,
    ifnull(hs.idHospital, 0) as idHospital,
    ifnull(hs.idAssociation, 0) as idAssociation,
    ifnull(nra.Name_Full, '') as Referral_Agent,
    ifnull(g.Description, '') as Diagnosis,
    ifnull(gl.Description, '') as Location,
    ifnull(rm.Rate_Code, '') as Rate_Code,
    ifnull(rm.Category, '') as Category,
    ifnull(rm.Type, '') as Type,
    ifnull(rm.Report_Category, '') as Report_Category,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ") and i.Sold_To_Id != " . $uS->subsidyId . "  and i.Order_Number = v.idVisit),
            0) as `AmountPaid`,
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
    where il.Deleted = 0 and i.Deleted = 0 and il.Item_Id = " . ItemId::AddnlCharge . " and i.Order_Number = v.idVisit),
            0) as `AddnlCharged`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status = '" . InvoiceStatus::Unpaid . "' and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ") and i.Order_Number = v.idVisit),
            0) as `AmountPending`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
    where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id = " . ItemId::VisitFee . " and i.Order_Number = v.idVisit),
            0) as `VisitFeePaid`
from
    visit v
        left join
    reservation rv ON v.idReservation = rv.idReservation
        left join
    resource r ON v.idResource = r.idResource
        left join
    resource_room rr ON r.idResource = rr.idResource
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
     v.`Status` <> 'p'
    and DATE(v.Span_Start) < DATE('$end')
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
                end)) >= DATE('$start')) " . $whHosp . $whAssoc . " order by v.idVisit, v.Span";


    $tbl = new HTMLTable();
    $sml = null;
    $reportRows = 0;

    if ($numberAssocs > 0) {
        $hospHeader = 'Hospital / Assoc';
    } else {
        $hospHeader = 'Hospital';
    }

    $fltrdTitles = $colSelector->getFilteredTitles();
    $fltrdFields = $colSelector->getFilteredFields();

    if ($local) {

        $th = '';

        foreach ($fltrdTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }
        $tbl->addHeaderTr($th);

    } else {

        ini_set('memory_limit', "380M");
        $reportRows = 1;

        $file = 'VisitReport';
        $sml = OpenXML::createExcel('', 'Visit Report');

        // build header
        $hdr = array();
        $n = 0;

        foreach ($fltrdTitles as $t) {
            $hdr[$n++] = $t;
        }

        OpenXML::writeHeaderRow($sml, $hdr);
        $reportRows++;

    }

    $curVisit = 0;
    $curRoom = 0;
    $curRate = '';
    $curAmt = 0;

    $totalCharged = 0;
    $totalVisitFee = 0;
    $totalLodgingCharge = 0;
    $totalFullCharge = 0;
    $totalAddnlCharged = 0;

    $totalPaid = 0;
    $totalHousePaid = 0;
    $totalAmtPending = 0;
    $totalGuestPaid = 0;
    $totalthrdPaid = 0;
    $totalSubsidy = 0;
    $totalUnpaid = 0;
    $totalAddnlPaid = 0;
    $totalDonationPaid = 0;

    $totalNights = 0;
    $totalGuestNights = 0;

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

    $stmt = $dbh->query($query);

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        // records ordered by idVisit.
        if ($curVisit != $r['idVisit']) {

            // If i did not just start
            if (count($visit) > 0 && $visit['nit'] > 0) {

                $totalLodgingCharge += $visit['chg'];
                $totalAddnlCharged += ($visit['addch']);

                if ($visit['nit'] > $uS->VisitFeeDelayDays) {
                    $totalVisitFee += $visit['vfa'];
                }

                $totalCharged += $visit['chg'];
                $totalFullCharge += $visit['fcg'];
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

                $departureDT = new \DateTime($savedr['Actual_Departure'] != '' ? $savedr['Actual_Departure'] : $expDepStr);

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

                if ($departureDT > $reportEndDT) {

                    // report period ends before the visit
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

                }


                $totalPaid += $dPaid;
                $totalHousePaid += $visit['hpd'];
                $totalGuestPaid += $visit['gpd'];
                $totalthrdPaid += $visit['thdpd'];
                $totalAddnlPaid += $visit['addpd'];
                $totalDonationPaid += $visit['donpd'];
                $totalUnpaid += $unpaid;
                $totalSubsidy += ($visit['fcg'] - $visit['chg']);
                $nites[] = $visit['nit'];

                if (!$statsOnly) {
                    doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $tbl, $local, $sml, $reportRows, $rateTitles, $uS, $visitFee);
                }
            }

            $curVisit = $r['idVisit'];
            $curRate = '';
            $curRateId = 0;
            $curAdj = 0;
            $curAmt = 0;
            $curRoom = 0;


            $visit = array(
                'id' => $r['idVisit'],
                'chg' => 0, // charges
                'fcg' => 0, // Flat Rate Charge (For comparison)
                'adj' => 0,
                'gpd' => $r['AmountPaid'] - $r['ThrdPaid'],
                'pndg' => $r['AmountPending'],
                'hpd' => abs($r['HouseDiscount']),
                'thdpd' => $r['ThrdPaid'],
                'addpd' => $r['AddnlPaid'],
                'addch' => $r['AddnlCharged'],
                'donpd' => $r['ContributionPaid'],
                'vfpd' => $r['VisitFeePaid'],  // Visit fees paid
                'plg' => 0, // Pledged rate
                'vfa' => $r['Visit_Fee_Amount'], // visit fees amount
                'nit' => 0, // Nights
                'gnit' => 0, // guest nights
                'pin' => 0, // Pre-interval nights
                'gpin' => 0, // Guest pre-interval nights
                'preCh' => 0,
                'rmc' => 0, // Room change counter
                'rtc' => 0  // Rate Category counter
                );

        }

        // Count rate changes
        if ($curRateId != $r['idRoom_rate']
                || ($curRate == RoomRateCategorys::Fixed_Rate_Category && $curAmt != $r['Pledged_Rate'])
                || ($curRate != RoomRateCategorys::Fixed_Rate_Category && $curAdj != $r['Expected_Rate'])) {

            $curRate = $r['Rate_Category'];
            $curRateId = $r['idRoom_rate'];
            $curAdj = $r['Expected_Rate'];
            $curAmt = $r['Pledged_Rate'];
            $visit['rateId'] = $r['idRoom_rate'];
            $visit['rtc']++;
        }

        // Count room changes
        if ($curRoom != $r['idResource']) {
            $curRoom = $r['idResource'];
            $visit['rmc']++;
        }

        $adjRatio = (1 + $r['Expected_Rate']/100);
        $visit['adj'] = $adjRatio;

        //  Add up any pre-interval charges
        if ($r['Pre_Interval_Nights'] > 0) {

            // collect all pre-charges
            $priceModel->setCreditDays($r['Rate_Glide_Credit']);
            $visit['preCh'] += ($priceModel->amountCalculator($r['Pre_Interval_Nights'], $r['idRoom_rate'], $r['Rate_Category'], $r['Pledged_Rate'], $r['PI_Guest_Nights']) * $adjRatio);

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
            $visit['chg'] += ($priceModel->amountCalculator($days, $r['idRoom_rate'], $r['Rate_Category'], $r['Pledged_Rate'], $gdays) * $adjRatio);

            $priceModel->setCreditDays($r['Rate_Glide_Credit'] + $r['Pre_Interval_Nights']);
            $fullCharge = ($priceModel->amountCalculator($days, 0, RoomRateCategorys::FullRateCategory, $uS->guestLookups['Static_Room_Rate'][$r['Rate_Code']][2], $gdays));

            if ($adjRatio > 0) {
                // Only adjust when the charge will be more.
                $fullCharge = $fullCharge * $adjRatio;
            }

            // Only Positive values.
            $visit['fcg'] += ($fullCharge > 0 ? $fullCharge : 0);
        }

        $savedr = $r;

    }   // End of while


    // Print the last visit.
    if (count($savedr) > 0 && $visit['nit'] > 0) {

        $totalLodgingCharge += $visit['chg'];
        $totalAddnlCharged += ($visit['addch']);
        $totalVisitFee += $visit['vfa'];
        $totalCharged += $visit['chg'];
        $totalFullCharge += $visit['fcg'];
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

        $departureDT = new \DateTime($savedr['Actual_Departure'] != '' ? $savedr['Actual_Departure'] : $expDepStr);

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

        if ($departureDT > $reportEndDT) {

            // report period ends before the visit
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

        }


        $totalPaid += $dPaid;
        $totalHousePaid += $visit['hpd'];
        $totalGuestPaid += $visit['gpd'];
        $totalthrdPaid += $visit['thdpd'];
        $totalAddnlPaid += $visit['addpd'];
        $totalDonationPaid += $visit['donpd'];
        $totalUnpaid += $unpaid;
        $totalSubsidy += ($visit['fcg'] - $visit['chg']);
        $nites[] = $visit['nit'];

        if (!$statsOnly) {
            doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $tbl, $local, $sml, $reportRows, $rateTitles, $uS, $visitFee);
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

                case 'meanRate':
                    $entry = '$' . number_format($avDailyFee,2);
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
        $statsTable = statsPanel($dbh, $nites, $totalCatNites, $start, $end, $categories, $avDailyFee, $rescGroup[0]);

        return array('data'=>$dataTable, 'stats'=>$statsTable);

    } else {

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();

    }

}


// Get labels
$labels = new Config_Lite(LABEL_FILE);

$receiptMarkup = '';
$paymentMarkup = '';
$payFailPage = '';

if (isset($_POST['CardID']) || isset($_POST['PaymentID'])) {

    $payFailPage = $wInit->page->getFilename();

    require (DB_TABLES . 'MercuryRS.php');
    require (DB_TABLES . 'PaymentsRS.php');
    require (DB_TABLES . 'nameRS.php');
    require (DB_TABLES . 'ActivityRS.php');
    require (DB_TABLES . 'visitRS.php');

    require (CLASSES . 'MercPay/MercuryHCClient.php');
    require (CLASSES . 'MercPay/Gateway.php');

    require (CLASSES . 'Purchase/Item.php');

    require (PMT . 'Payments.php');
    require (PMT . 'HostedPayments.php');
    require (PMT . 'Receipt.php');
    require (PMT . 'Invoice.php');
    require (PMT . 'InvoiceLine.php');
    require (PMT . 'CreditToken.php');
    require (PMT . 'CheckTX.php');
    require (PMT . 'CashTX.php');
    require (PMT . 'Transaction.php');

    require (MEMBER . 'Member.php');
    require (MEMBER . 'IndivMember.php');
    require (MEMBER . 'OrgMember.php');
    require (MEMBER . "Addresses.php");
    require (MEMBER . "EmergencyContact.php");

    require (CLASSES . 'PaymentSvcs.php');
    require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

    require (HOUSE . 'PaymentManager.php');
    require (HOUSE . 'PaymentChooser.php');

    $payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_POST);

    if (is_null($payResult) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
    }
}

$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
$dataTable = '';
$statsTable = '';
$errorMessage = '';

$rescGroups = readGenLookupsPDO($dbh, 'Room_Group');

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();
$filter->createResoourceGroups($rescGroups, $uS->CalResourceGroupBy);

// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel Style, td parms
$cFields[] = array('Visit Id', 'idVisit', 'checked', 'f', 'n', '', array('style'=>'text-align:center;'));
$cFields[] = array("Primary Guest", 'idPrimaryGuest', 'checked', '', 's', '', array());
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

$cFields[] = array("Patient DOB", 'pBirth', '', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14, array(), 'date');

$cFields[] = array($labels->getString('hospital', 'referralAgent', 'Ref. Agent'), 'Referral_Agent', 'checked', '', 's', '', array());

// Hospital
if (count($filter->getHospitals()) > 1) {

    if (count($filter->getAList()) > 0) {
        $cFields[] = array("Hospital / Assoc", 'hospitalAssoc', 'checked', '', 's', '', array());
    } else {
        $cFields[] = array($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'), 'hospitalAssoc', 'checked', '', 's', '', array());
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


$cFields[] = array("Arrive", 'Arrival', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14, array(), 'date');
$cFields[] = array("Depart", 'Departure', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14, array(), 'date');
$cFields[] = array("Room", 'Title', 'checked', '', 's', '', array('style'=>'text-align:center;'));

if ($uS->VisitFee) {
    $cFields[] = array($labels->getString('statement', 'cleaningFeeLabel', "Clean Fee"), 'visitFee', 'checked', '', 's', '', array('style'=>'text-align:right;'));
}

$adjusts = readGenLookupsPDO($dbh, 'Addnl_Charge');
if (count($adjusts) > 0) {
    $cFields[] = array("Addnl Charge", 'adjch', 'checked', '', 's', '', array('style'=>'text-align:right;'));
}


$cFields[] = array("Nights", 'nights', 'checked', '', 'n', '', array('style'=>'text-align:center;'));

$amtChecked = 'checked';

if ($uS->RoomPriceModel !== ItemPriceCode::None) {

    if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {

        $cFields[] = array("Guest Nights", 'gnights', 'checked', '', 'n', '', array('style'=>'text-align:center;'));
        $cFields[] = array("Rate Per Guest", 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());
        $cFields[] = array("Mean Rate Per Guest", 'meanRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

    } else {

        $cFields[] = array("Rate", 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());
        $cFields[] = array("Mean Rate", 'meanRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    }

    $cFields[] = array("Lodging Charge", 'lodg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

    $cFields[] = array("Guest Paid", 'gpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("3rd Party Paid", 'thdpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("House Paid", 'hpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("Lodging Paid", 'totpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));

    $cFields[] = array("Unpaid", 'unpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("Pending", 'pndg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("Rate Subsidy", 'sub', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
    $cFields[] = array("Contribution", 'donpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style'=>'text-align:right;'));
}

$colSelector = new ColumnSelectors($cFields, 'selFld');


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

        $tblArray = doReport($dbh, $colSelector, $filter->getReportStart(), $filter->getReportEnd(), $whHosp, $whAssoc, count($filter->getAList()), $local, $uS->VisitFee, $statsOnly, $rescGroups[$filter->getSelectedResourceGroups()]);

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
            $headerTable .= HTMLContainer::generateMarkup('p', 'Hospitals: ' . $hospitalTitles);
        } else {
            $headerTable .= HTMLContainer::generateMarkup('p', 'All Hospitals');
        }

    } else {
        $errorMessage = 'Missing the dates.';
    }

}

// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup($config)->generateMarkup(array('style'=>'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));
$roomGroupMarkup = $filter->resourceGroupsMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>

<!--        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>-->

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

<script type="text/javascript">
    var fixedRate = '<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>';
    $(document).ready(function() {
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var makeTable = '<?php echo $mkTable; ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
        var pmtMkup = "<?php echo $paymentMarkup; ?>";
        var rctMkup = '<?php echo $receiptMarkup; ?>';
        var payFailPage = '<?php echo $payFailPage; ?>';
        <?php echo $filter->getTimePeriodScript(); ?>;
        $('#btnHere, #btnExcel, #btnStatsOnly, #cbColClearAll, #cbColSelAll').button();
        $('#btnHere, #btnExcel').click(function () {
            $('#paymentMessage').hide();
        });
        if (pmtMkup !== '') {
            $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
        }
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
            $('div#printArea').on('click', '.hhk-getVDialog',
                function () {
                var buttons;
                var vid = $(this).data('vid');
                var span = $(this).data('span');
                buttons = {
                    "Show Statement": function() {
                        window.open('ShowStatement.php?vid=' + vid, '_blank');
                    },
                    "Show Registration Form": function() {
                        window.open('ShowRegForm.php?vid=' + vid, '_blank');
                    },
                    "Save": function() {
                        saveFees(0, vid, span, false, payFailPage);
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                };
                viewVisit(0, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
            });
            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
        }
        $('#keysfees').dialog({
            autoOpen: false,
            resizable: true,
            modal: true
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
        if (rctMkup !== '') {
            showReceipt('#pmtRcpt', rctMkup);
        }
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="paymentMessage" style="float:left; margin-top:5px;margin-bottom:5px; display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
            </div>
            <h2><?php echo $wInit->pageHeading; ?></h2>
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
        <form name="xform" id="xform" method="post"><input type="hidden" name="CardID" id="CardID" value=""/></form>
        <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
        <div id="keysfees" style="font-size: .85em;"></div>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
    </body>
</html>
