<?php

namespace HHK\House\Visit;

use HHK\ColumnSelectors;
use HHK\ExcelHelper;
use HHK\House\Report\RoomReport;
use HHK\House\Resource\ResourceTypes;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Purchase\RoomRate;
use HHK\Purchase\ValueAddedTax;
use HHK\sec\{
    Session,
};
use HHK\SysConst\{
    ResourceStatus,
    RoomRateCategories,
    GLTableNames,
    ItemPriceCode,
    InvoiceStatus,
    ItemType,
    ItemId,
    VolMemberType
};

use HHK\TableLog\HouseLog;

class VisitIntervalOldRpt {

    protected $eachTaxPaid;

    public function __construct($eachTaxPaid)
    {
        $this->eachTaxPaid = $eachTaxPaid;
    }

    /**
     * Summary of getGuestNights - returns total of main guest nights plus any additional guest nights
     * @param \PDO $dbh
     * @param mixed $start
     * @param mixed $end
     * @param mixed $actual
     * @param mixed $preInterval
     * @return void
     */
    protected function getGuestNights(\PDO $dbh, $start, $end, &$actual, &$preInterval)
    {

        $uS = Session::getInstance();
        $ageYears = $uS->StartGuestFeesYr;

        $stmt = $dbh->query("SELECT
    s.idVisit,
    s.Visit_Span,
    SUM(
    CASE
        WHEN
            DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) < DATE('$start')
        THEN 0
        WHEN
            DATE(s.Span_Start_Date) >= DATE('$end')
        THEN 0
        ELSE
            DATEDIFF(
                CASE
                    WHEN
                        DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) >= DATE('$end')
                    THEN
                        DATE('$end')
                    ELSE DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date)))
                END,
                CASE
                    WHEN DATE(s.Span_Start_Date) < DATE('$start') THEN DATE('$start')
                    ELSE DATE(s.Span_Start_Date)
                END
            )
        END) AS `Actual_Guest_Nights`,
    SUM(
    CASE
        WHEN DATE(s.Span_Start_Date) >= DATE('$start') THEN 0
        WHEN
            DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) <= DATE('$start')
        THEN
            DATEDIFF(DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))),
                    DATE(s.Span_Start_Date))
        ELSE DATEDIFF(CASE
                    WHEN
                        DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) > DATE('$start')
                    THEN
                        DATE('$start')
                    ELSE DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date)))
                END,
                DATE(s.Span_Start_Date))
    END) AS `PI_Guest_Nights`

FROM stays s JOIN name n ON s.idName = n.idName

WHERE  IFNULL(DATE(n.BirthDate), DATE('1901-01-01')) < DATE(DATE_SUB(DATE(s.Checkin_Date), INTERVAL $ageYears YEAR))
        AND DATE(s.Span_Start_Date) < DATE('$end')
        AND s.idVisit IN (SELECT
            idVisit
        FROM
            visit
        WHERE
            `Status` NOT IN ('p' , 'c')
                AND DATE(Arrival_Date) < DATE('$end')
                AND DATE(IFNULL(Span_End,
                        CASE
                            WHEN NOW() > Expected_Departure THEN NOW()
                            ELSE Expected_Departure
                        END)) >= DATE('$start'))
GROUP BY s.idVisit , s.Visit_Span
ORDER BY s.idVisit , s.Visit_Span");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $actual[$r['idVisit']][$r['Visit_Span']] = $r['Actual_Guest_Nights'] < 0 ? 0 : $r['Actual_Guest_Nights'];
            $preInterval[$r['idVisit']][$r['Visit_Span']] = $r['PI_Guest_Nights'] < 0 ? 0 : $r['PI_Guest_Nights'];
        }

    }

    /**
     * Summary of newStatsPanel
     * @param \PDO $dbh
     * @param mixed $visitNites
     * @param mixed $rates
     * @param mixed $totalCatNites
     * @param mixed $start
     * @param mixed $end
     * @param mixed $categories
     * @param mixed $avDailyFee
     * @param mixed $medDailyFee
     * @param mixed $rescGroup
     * @param mixed $siteName
     * @return string
     */
    public function newStatsPanel(\PDO $dbh, $visitNites, $rates, $totalCatNites, $start, $end, $categories, $avDailyFee, $medDailyFee, $rescGroup, $siteName)
    {

        // Stats panel
        if (count($visitNites) < 1) {
            return '';
        }

        $uS = Session::getInstance();

        $totalVisitNites = 0;
        $numCategoryRooms = array();

        $oosNights = array();
        $totalOOSNites = 0;

        $stDT = new \DateTime($start . ' 00:00:00');
        $enDT = new \DateTime($end . ' 00:00:00');
        $numNights = $enDT->diff($stDT, TRUE)->days;

        foreach ($visitNites as $v) {
            $totalVisitNites += $v;
        }

        foreach ($categories as $cat) {
            $numCategoryRooms[$cat[0]] = 0;
            $oosNights[$cat[0]] = 0;
        }



        $qu = "select r.idResource, rm.Category, rm.Type, rm.Report_Category, ifnull(r.Retired_At, '') as `Retired_At`
        from resource r
        left join resource_room rr on r.idResource = rr.idResource
        left join room rm on rr.idRoom = rm.idRoom
        where r.`Type` in ('" . ResourceTypes::Room . "','" . ResourceTypes::RmtRoom . "') and (r.Retired_At is null or date(r.Retired_At) > '" . $stDT->format('Y-m-d') . "')
        order by r.idResource;";

        $rstmt = $dbh->query($qu);

        $rooms = array();

        $roomReport = new RoomReport();
        $rescStatuses = readGenLookupsPDO($dbh, "Resource_Status");
        $roomReport->collectUtilizationData($dbh, $start, $end, $rescStatuses);
        $daysAr = $roomReport->getDays();

        // transform room report data for visit report
        while ($r = $rstmt->fetch(\PDO::FETCH_ASSOC)) {
            if (isset($daysAr[$r['idResource']])) {
                $totals = array(ResourceStatus::Available => 0, ResourceStatus::OutOfService => 0, ResourceStatus::Delayed => 0, ResourceStatus::Unavailable => 0, ResourceStatus::Closed => 0);
                foreach ($daysAr[$r['idResource']] as $day) {
                    $totals[ResourceStatus::Available] += ($day['n'] + $day['o'] + $day['t'] + $day['u'] + $day['c'] == 0 ? 1 : 0);
                    $totals[ResourceStatus::OutOfService] += $day['o'];
                    $totals[ResourceStatus::Delayed] += $day['t'];
                    $totals[ResourceStatus::Unavailable] += $day['u'];
                    $totals[ResourceStatus::Closed] += $day['c'];
                }
                $rooms[$r['idResource']][$r[$rescGroup]] = $totals;
            }
        }

        // Filter out unavailalbe rooms and add up the nights
        $availableRooms = 0;
        $unavailableRooms = 0;

        foreach ($rooms as $r) {

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

        // Median visit nights
        array_multisort($visitNites);
        $entries = count($visitNites);
        $emod = $entries % 2;

        if ($emod > 0) {
            // odd number of entries
            $median = $visitNites[(ceil($entries / 2) - 1)];
        } else {
            $median = ($visitNites[($entries / 2) - 1] + $visitNites[($entries / 2)]) / 2;
        }


        $trs[4] = HTMLTable::makeTd('Useful Nights (Room-Nights &ndash; Room-Nights OOS):', array('class' => 'tdlabel'))
            . HTMLTable::makeTd($numRoomNights . ' &ndash; ' . $totalOOSNites . ' = ' . HTMLContainer::generateMarkup('span', $numUsefulNights, array('style' => 'font-weight:bold;')));

        $trs[5] = HTMLTable::makeTd('Room Utilization (Nights &divide; Useful Nights):', array('class' => 'tdlabel'))
            . HTMLTable::makeTd($totalVisitNites . ' &divide; ' . $numUsefulNights . ' = ' . HTMLContainer::generateMarkup('span', ($numUsefulNights <= 0 ? '0' : number_format($totalVisitNites * 100 / $numUsefulNights, 1)) . '%', array('style' => 'font-weight:bold;')));

        $hdTr = HTMLTable::makeTh('Parameter') . HTMLTable::makeTh('All Rooms (' . $availableRooms . ')');

        foreach ($categories as $c) {

            if (!isset($numCategoryRooms[$c[0]]) || $numCategoryRooms[$c[0]] == 0) {
                continue;
            }

            $hdTr .= HTMLTable::makeTh($c[1] . ' (' . $numCategoryRooms[$c[0]] . ')');
            $numRoomNights = $numCategoryRooms[$c[0]] * $numNights;
            $numUsefulNights = $numRoomNights - $oosNights[$c[0]];

            $trs[4] .= HTMLTable::makeTd($numRoomNights . ' &ndash; ' . $oosNights[$c[0]] . ' = ' . HTMLContainer::generateMarkup('span', $numUsefulNights, array('style' => 'font-weight:bold;')));
            $trs[5] .= HTMLTable::makeTd($totalCatNites[$c[0]] . ' &divide; ' . $numUsefulNights . ' = ' . HTMLContainer::generateMarkup('span', ($numUsefulNights <= 0 ? '0' : number_format($totalCatNites[$c[0]] * 100 / $numUsefulNights, 1)) . '%', array('style' => 'font-weight:bold;')));
        }

        $sTbl = new HTMLTable();

        $sTbl->addHeaderTr($hdTr);

        $sTbl->addBodyTr(HTMLTable::makeTd('Mean visit length in days:', array('class' => 'tdlabel')) . HTMLTable::makeTd(number_format($avStay, 2)));
        $sTbl->addBodyTr(HTMLTable::makeTd('Median visit length in days:', array('class' => 'tdlabel')) . HTMLTable::makeTd(number_format($median, 2)));

        $sTbl->addBodyTr(HTMLTable::makeTd('Mean Room Charge per visit day:', array('class' => 'tdlabel')) . HTMLTable::makeTd('$' . number_format($avDailyFee, 2)));

        if ($uS->RoomPriceModel == ItemPriceCode::Dailey) {
            $sTbl->addBodyTr(HTMLTable::makeTd('Median Room Charge per visit day:', array('class' => 'tdlabel')) . HTMLTable::makeTd('$' . number_format($medDailyFee, 2)));
        }

        $sTbl->addBodyTr($trs[4]);

        $sTbl->addBodyTr($trs[5]);

        return HTMLContainer::generateMarkup('h3', $siteName . ' Visit Report Statistics')
            . HTMLContainer::generateMarkup('p', 'These numbers are specific to this report\'s selected filtering parameters.')
            . $sTbl->generateMarkup();

    }


    /**
     * Summary of buildQuery
     * @param mixed $start  String start date
     * @param mixed $end    string end date
     * @return string
     */
    protected function buildQuery($start, $end, $subsidyId, $eachTaxSql = '')
    {
        return "select
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
    ifnull(hs.MRN, '') as MRN,
    ifnull(g.Description, hs.Diagnosis) as Diagnosis,
    ifnull(hs.Diagnosis2, '') as Diagnosis2,
    ifnull(group_concat(i.Title order by it.List_Order separator ', '), '') as Insurance,
    ifnull(gl.Description, '') as Location,
    ifnull(rm.Rate_Code, '') as Rate_Code,
    ifnull(rm.Category, '') as Category,
    ifnull(rm.Type, '') as Type,
    ifnull(rm.Report_Category, '') as Report_Category,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ") and i.Sold_To_Id != " . $subsidyId . "  and i.Order_Number = v.idVisit),
            0) as `AmountPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Type_Id = " . ItemType::Tax . " and il.Source_Item_Id in ( " . ItemId::Lodging . ", " . ItemId::LodgingReversal . ") and i.Sold_To_Id != " . $subsidyId . " and i.Order_Number = v.idVisit),
            0) as `TaxPaid`,
    $eachTaxSql
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id = " . ItemId::LodgingDonate . " and i.Order_Number = v.idVisit),
            0) as `ContributionPaid`,
    ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
            LEFT JOIN
        name_volunteer2 nv ON i.Sold_To_Id = nv.idName AND nv.Vol_Category = 'Vol_Type' AND nv.Vol_Code = '" . VolMemberType::BillingAgent . "'
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ") and ifnull(nv.idName, 0) > 0 and i.Sold_To_Id != " . $subsidyId . " and i.Order_Number = v.idVisit),
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
	name_insurance ni on np.idName = ni.idName
		left join
	insurance i on ni.Insurance_Id = i.idInsurance
		left join
	insurance_type it on i.idInsuranceType = it.idInsurance_type
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
            `Status` not in ('p', 'c')
                and DATE(Arrival_Date) < DATE('$end')
                and DATE(ifnull(Span_End,
                    case
                        when now() > Expected_Departure then now()
                        else Expected_Departure
                end)) >= DATE('$start')) ";

    }


    /**
     * Summary of doMarkup - Pretify a row
     * @param mixed $fltrdFields
     * @param mixed $r
     * @param mixed $visit
     * @param mixed $paid
     * @param mixed $unpaid
     * @param \DateTimeInterface $departureDT
     * @param mixed $matrix
     * @param mixed $local
     * @param mixed $rateTitles
     * @param mixed $uS
     * @param mixed $visitFee
     * @return void
     */
    protected function doMarkup($fltrdFields, $r, $visit, $paid, $unpaid, \DateTimeInterface $departureDT, &$matrix, $local, $rateTitles, $uS, $visitFee = FALSE)
    {

        $arrivalDT = new \DateTime($r['Arrival_Date']);

        if ($r['Rate_Category'] == RoomRateCategories::Fixed_Rate_Category) {

            $r['rate'] = $r['Pledged_Rate'];

        } else if (isset($visit['rateId']) && isset($rateTitles[$visit['rateId']])) {

            $rateTxt = $rateTitles[$visit['rateId']];

            if ($visit['adj'] != 0) {
                $parts = explode('$', $rateTxt);

                if (count($parts) == 2) {
                    $amt = floatval($parts[1]) * (1 + $visit['adj'] / 100);
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

        $r['meanGstRate'] = 0;
        if ($visit['gnit'] > 0) {
            $r['meanGstRate'] = number_format(($visit['chg'] / $visit['gnit']), 2);
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
        $r['gnights'] = max($visit['gnit'] - $visit['nit'], 0);
        $r['lodg'] = number_format($visit['chg'], 2);
        $r['days'] = $visit['day'];


        $sub = $visit['fcg'] - $visit['chg'];
        $r['sub'] = ($sub == 0 ? '' : number_format($sub, 2));

        $rateAdj = $visit['adj'];
        $r['rateAdj'] = ($rateAdj == 0 ? '' : number_format($rateAdj, 2) . '%');

        $r['gpaid'] = $visit['gpd'] == 0 ? '' : number_format($visit['gpd'], 2);
        $r['hpaid'] = $visit['hpd'] == 0 ? '' : number_format($visit['hpd'], 2);
        $r['totpd'] = ($paid == 0 ? '' : number_format($paid, 2));
        $r['unpaid'] = ($unpaid == 0 ? '' : number_format($unpaid, 2));
        $r['adjch'] = ($visit['addpd'] == 0 ? '' : number_format($visit['addpd'], 2));
        $r['adjchtx'] = ($visit['adjchtx'] == 0 ? '' : number_format($visit['adjchtx'], 2));
        $r['pndg'] = ($visit['pndg'] == 0 ? '' : number_format($visit['pndg'], 2));
        $r['thdpaid'] = ($visit['thdpd'] == 0 ? '' : number_format($visit['thdpd'], 2));
        $r['donpd'] = ($visit['donpd'] == 0 ? '' : number_format($visit['donpd'], 2));

        $r['taxcgd'] = $visit['taxcgd'] == 0 ? '' : number_format($visit['taxcgd'], 2);
        $r['taxpd'] = $visit['taxpd'] == 0 ? '' : number_format($visit['taxpd'], 2);
        $r['taxpndg'] = $visit['taxpndg'] == 0 ? '' : number_format($visit['taxpndg'], 2);

        foreach ($this->eachTaxPaid as $k => $v) {
            $r["paid_$k"] = $visit["paid_$k"] == 0 ? '' : number_format($visit["paid_$k"], 2);
            $r["chg_$k"] = $visit["chg_$k"] == 0 ? '' : number_format($visit["chg_$k"], 2);
        }

        $visitFeePaid = '';

        if ($visitFee) {

            if ($visit['vfa'] > 0 && $visit['vfa'] == $visit['vfpd']) {

                $r['visitFee'] = number_format($visit['vfa'], 2);
                $visitFeePaid = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-circle-check', 'style' => 'float:left;', 'title' => 'Fees paid'));

            } else if ($visit['vfa'] > 0 && $uS->VisitFeeDelayDays < $visit['nit']) {

                $r['visitFee'] = number_format($visit['vfa'], 2);

            } else {

                $r['visitFee'] = '';
            }
        }

        $addPaidIcon = '';

        if ($visit['addch'] > 0 && $visit['addch'] <= $visit['addpd']) {

            $r['adjch'] = number_format($visit['addch'], 2);
            $addPaidIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-circle-check', 'style' => 'float:left;', 'title' => 'Charges paid'));

        } else if ($visit['addch'] > 0) {

            $r['adjch'] = number_format($visit['addch'], 2);

        } else {

            $r['adjch'] = '';
        }


        $changeRoomIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-info', 'style' => 'float:left;', 'title' => 'Changed Rooms'));
        $changeRateIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-info', 'style' => 'float:left;', 'title' => 'Room Rate Changed'));
        $insInfoIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-comment insAction', 'style' => 'cursor:pointer;', 'data-idName' => $r['idPatient'], 'id' => 'insAction' . $r['idPatient'], 'title' => 'View Insurance'));

        if ($local) {

            $r['idVisit'] = HTMLContainer::generateMarkup('div', $r['idVisit'], ['class' => 'hhk-viewVisit', 'data-gid' => $r['idPrimaryGuest'], 'data-vid' => $r['idVisit'], 'data-span' => $r['Span'], 'style' => 'display:inline-table;']);
            $r['idPrimaryGuest'] = HTMLContainer::generateMarkup('a', $r['Name_Last'] . ', ' . $r['Name_First'], ['href' => 'GuestEdit.php?id=' . $r['idPrimaryGuest'] . '&psg=' . $r['idPsg']]);
            $r['idPatient'] = HTMLContainer::generateMarkup('a', $r['Patient_Last'] . ', ' . $r['Patient_First'], ['href' => 'GuestEdit.php?id=' . $r['idPatient'] . '&psg=' . $r['idPsg']]);
            $r['Arrival'] = $arrivalDT->format('c');
            $r['Departure'] = $departureDT->format('c');

            if ($r['pBirth'] != '') {
                $pBirthDT = new \DateTime($r['pBirth']);
                $r['pBirth'] = $pBirthDT->format('c');
            }

            $now = new \DateTime();
            $now->setTime(0, 0, 0);
            $expDepart = new \DateTime($r['Expected_Departure']);
            $expDepart->setTime(0, 0, 0);

            //$r['Status'] = HTMLContainer::generateMarkup('span', $uS->guestLookups['Visit_Status'][$r['Status']][1], array('class'=>'hhk-getVDialog', 'style'=>'cursor:pointer;width:100%;text-decoration: underline;', 'data-vid'=>$r['idVisit'], 'data-span'=>$r['Span']));

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

            if ($r['Insurance'] != '') {
                $r['Insurance'] = $insInfoIcon . $r['Insurance'];
            }

            // add to the matrix for the report
            foreach ($fltrdFields as $f) {
                $matrix[$f[1]][] = $r[$f[1]];
            }


        } else {

            $r['Status'] = $uS->guestLookups['Visit_Status'][$r['Status']][1];
            $r['idPrimaryGuest'] = $r['Name_Last'] . ', ' . $r['Name_First'];
            $r['Arrival'] = $arrivalDT->format('Y-m-d');
            $r['Departure'] = $departureDT->format('Y-m-d');
            $r['idPatient'] = $r['Patient_Last'] . ', ' . $r['Patient_First'];

            if ($r['pBirth'] != '') {
                $pBirthDT = new \DateTime($r['pBirth']);
                $r['pBirth'] = $pBirthDT->format('Y-m-d');
            } else {
                $r['pBirth'] = '';
            }

            foreach ($fltrdFields as $f) {

                //$flds[$n++] = array('type' => $f[4], 'value' => $r[$f[1]], 'style'=>$f[5]);
                if ($r[$f[1]] != '' && $f[5] != '') {
                    if ($r[$f[1]] == "") {
                        $matrix[$f[1]][] = "0.00";
                    } else {
                        $matrix[$f[1]][] = strval(str_replace(',', '', $r[$f[1]]));
                    }
                } else {
                    $matrix[$f[1]][] = html_entity_decode(strval($r[$f[1]]), ENT_QUOTES, 'UTF-8');
                }
            }
        }
    }

    /**
     * Summary of doReport
     * @param \PDO $dbh
     * @param \HHK\ColumnSelectors $colSelector
     * @param mixed $start
     * @param mixed $end
     * @param mixed $whHosp
     * @param mixed $whAssoc
     * @param mixed $local
     * @param mixed $visitFee
     * @param mixed $statsOnly
     * @param mixed $rescGroup
     * @param mixed $eachTaxSql
     * @return null | array{data: string, stats: string}
     */
    public function doReport(\PDO $dbh, ColumnSelectors $colSelector, $start, $end, $whHosp, $whAssoc, $local, $visitFee, $statsOnly, $rescGroup, $eachTaxSql)
    {

        // get session instance
        $uS = Session::getInstance();

        $categories = readGenLookupsPDO($dbh, $rescGroup[2], 'Description');
        // add default category
        $categories[] = [0 => '', 1 => '(default)'];


        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        // Make titles for all the rates
        $rateTitles = RoomRate::makeDescriptions($dbh);

        // Get Guest days
        $actualGuestNights = [];
        $piGuestNights = [];

        if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
            // routine defines acutalGuestNights and piGuestNights.
            $this->getGuestNights($dbh, $start, $end, $actualGuestNights, $piGuestNights);

        }

        $matrix = ['idVisit' => []];

        $fltrdTitles = $colSelector->getFilteredTitles();
        $fltrdFields = $colSelector->getFilteredFields();


        $curVisit = 0;
        $curRoom = 0;
        $curRate = '';
        $curAmt = 0;

        $totalCharged = 0;
        $totalVisitFee = 0;
        $totalLodgingCharge = 0;
        $totalAddnlCharged = 0;

        $totalAddnlTax = 0;
        $totalTaxCharged = 0;
        $totalTaxPaid = 0;
        $totalTaxPending = 0;
        $totalEachTaxPaid = [];
        $totalEachTaxCharged = [];

        foreach ($this->eachTaxPaid as $k => $v) {
            $totalEachTaxPaid[$k] = 0;
            $totalEachTaxCharged[$k] = 0;
        }

        $totalPaid = 0;
        $totalHousePaid = 0;
        $totalAmtPending = 0;
        $totalGuestPaid = 0;
        $totalthrdPaid = 0;
        $totalSubsidy = 0;
        $totalUnpaid = 0;
        $totalDonationPaid = 0;

        $totalNights = 0;
        $totalGuestNights = 0;
        $totalDays = 0;

        $totalCatNites[] = [];

        foreach ($categories as $c) {
            $totalCatNites[$c[0]] = 0;
        }

        $visit = [];
        $savedr = [];
        $nites = [];
        $rates = [];
        $chargesAr = [];

        $reportEndDT = new \DateTime("$end 00:00:00");
        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        $vat = new ValueAddedTax($dbh);

        $dbh->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);

        $query = $this->buildQuery($start, $end, $uS->subsidyId, $eachTaxSql) . $whHosp . $whAssoc . " group by v.idVisit, v.Span order by v.idVisit, v.Span";

        $stmt = $dbh->query($query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // records ordered by idVisit.
            if ($curVisit != $r['idVisit']) {

                // If i did not just start
                if (count($visit) > 0 && $visit['nit'] > 0) {

                    $totalLodgingCharge += $visit['chg'];
                    $chargesAr[] = $visit['chg'] / $visit['nit'];
                    $totalAddnlCharged += ($visit['addch']);

                    $totalTaxCharged += $visit['taxcgd'];
                    $totalAddnlTax += $visit['adjchtx'];
                    $totalTaxPaid += $visit['taxpd'];
                    $totalTaxPending += $visit['taxpndg'];

                    // Individual tax totals
                    foreach ($this->eachTaxPaid as $k => $v) {
                        $totalEachTaxPaid[$k] += isset($visit["paid_$k"]) ? $visit["paid_$k"] : 0;
                        $totalEachTaxCharged[$k] += isset($visit["chg_$k"]) ? $visit["chg_$k"] : 0;
                    }

                    if ($visit['nit'] > $uS->VisitFeeDelayDays) {
                        $totalVisitFee += $visit['vfa'];
                    }

                    $totalCharged += $visit['chg'];
                    $totalAmtPending += $visit['pndg'];
                    $totalNights += $visit['nit'];
                    $totalGuestNights += max($visit['gnit'] - $visit['nit'], 0);

                    // Set expected departure to now if earlier than "today"
                    $expDepDT = new \DateTime($savedr['Expected_Departure']);
                    $expDepDT->setTime(0, 0, 0);

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
                        $preCharge = 0;
                    }

                    if ($preCharge >= $visit['thdpd']) {
                        $preCharge -= $visit['thdpd'];
                        $visit['thdpd'] = 0;
                    } else if ($preCharge > 0) {
                        $visit['thdpd'] -= $preCharge;
                        $preCharge = 0;
                    }

                    if ($preCharge >= $visit['hpd']) {
                        $preCharge -= $visit['hpd'];
                        $visit['hpd'] = 0;
                    } else if ($preCharge > 0) {
                        $visit['hpd'] -= $preCharge;
                        $preCharge = 0;
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
                    $totalDonationPaid += $visit['donpd'];
                    $totalUnpaid += $unpaid;
                    $totalSubsidy += $visit['fcg'] - $visit['chg'];
                    $nites[] = $visit['nit'];

                    if (!$statsOnly) {
                        try {
                            $this->doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $matrix, $local, $rateTitles, $uS, $visitFee);
                        } catch (\Exception $e) {
                            if (isset($writer)) {
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


                $visit = [
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
                    'addtxpd' => $r['AddnlTaxPaid'],
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
                ];

                foreach ($this->eachTaxPaid as $k => $v) {
                    $visit["paid_$k"] = $r["paid_$k"];
                    $visit["chg_$k"] = 0;
                }

            }

            // Count rate changes
            if (
                $curRateId != $r['idRoom_Rate']
                || ($curRate == RoomRateCategories::Fixed_Rate_Category && $curAmt != $r['Pledged_Rate'])
                || ($curRate != RoomRateCategories::Fixed_Rate_Category && $curAdj != $r['Expected_Rate'])
            ) {

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

            $adjRatio = 1 + $r['Expected_Rate'] / 100;
            $visit['adj'] = $r['Expected_Rate'];

            $days = $r['Actual_Month_Nights'];
            $visit['nit'] += $days;
            $totalCatNites[$r[$rescGroup[0]]] += $days;

            $gdays = $actualGuestNights[$r['idVisit']][$r['Span']] ?? 0;    // Total of primary and additional guests for this span

            $visit['gnit'] += $gdays;

            // $gdays contains all the guest nights.
            if ($gdays >= $days) {
                $gdays -= $days;    // $gdays redefined as only additional guest days.
            }

            $piDays = $r['Pre_Interval_Nights'];
            $piGdays = $piGuestNights[$r['idVisit']][$r['Span']] ?? 0;
            $visit['pin'] += $piDays;
            $visit['gpin'] += $piGdays;

            if ($piGdays >= $piDays) {
                $piGdays -= $piDays;
            }


            //  Add up any pre-interval charges
            if ($piDays > 0) {

                // collect all pre-charges
                $priceModel->setCreditDays($r['Rate_Glide_Credit']);
                $visit['preCh'] += $priceModel->amountCalculator($piDays, $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $piGdays) * $adjRatio;

            }

            if ($days > 0) {

                $priceModel->setCreditDays($r['Rate_Glide_Credit'] + $piDays);
                $visit['chg'] += $priceModel->amountCalculator($days, $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $gdays) * $adjRatio;
                $visit['taxcgd'] += round($visit['chg'] * $lodgeTax, 2);

                // Zero the individual taxes
                foreach ($this->eachTaxPaid as $k => $v) {
                    $visit["chg_$k"] = 0;
                }

                // Get the (current) taxing items for this visit
                $taxedItems = $vat->getCurrentTaxingItems($r['idVisit'], $r['Visit_Age'], ItemId::Lodging);

                foreach ($taxedItems as $ti) {
                    $visit["chg_" . $ti->getIdTaxingItem()] = round($visit['chg'] * $ti->getPercentTax() / 100, 2);
                }


                $priceModel->setCreditDays($r['Rate_Glide_Credit'] + $piDays);
                $fullCharge = $priceModel->amountCalculator($days, 0, RoomRateCategories::FullRateCategory, $uS->guestLookups['Static_Room_Rate'][$r['Rate_Code']][2], $gdays);

                if ($adjRatio > 0) {
                    // Only adjust when the charge will be more.
                    $fullCharge *= $adjRatio;
                }

                // Only Positive values.
                $visit['fcg'] += ($fullCharge > 0 ? $fullCharge : 0);
            }

            $savedr = $r;

        }   // End of while

        $dbh->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, TRUE);



        // process the last visit.
        if (count($savedr) > 0 && $visit['nit'] > 0) {

            $totalLodgingCharge += $visit['chg'];
            $chargesAr[] = $visit['chg'] / $visit['nit'];
            $totalAddnlCharged += ($visit['addch']);

            $totalTaxCharged += $visit['taxcgd'];
            $totalAddnlTax += $visit['adjchtx'];
            $totalTaxPaid += $visit['taxpd'];
            $totalTaxPending += $visit['taxpndg'];

            // Individual tax totals
            foreach ($this->eachTaxPaid as $k => $v) {
                $totalEachTaxPaid[$k] += isset($visit["paid_$k"]) ? $visit["paid_$k"] : 0;
                $totalEachTaxCharged[$k] += isset($visit["chg_$k"]) ? $visit["chg_$k"] : 0;
            }

            if ($visit['nit'] > $uS->VisitFeeDelayDays) {
                $totalVisitFee += $visit['vfa'];
            }

            $totalCharged += $visit['chg'];
            $totalAmtPending += $visit['pndg'];
            $totalNights += $visit['nit'];
            $totalGuestNights += max($visit['gnit'] - $visit['nit'], 0);

            // Set expected departure to now if earlier than "today"
            $expDepDT = new \DateTime($savedr['Expected_Departure']);
            $expDepDT->setTime(0, 0, 0);

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
                $preCharge = 0;
            }

            if ($preCharge >= $visit['thdpd']) {
                $preCharge -= $visit['thdpd'];
                $visit['thdpd'] = 0;
            } else if ($preCharge > 0) {
                $visit['thdpd'] -= $preCharge;
                $preCharge = 0;
            }

            if ($preCharge >= $visit['hpd']) {
                $preCharge -= $visit['hpd'];
                $visit['hpd'] = 0;
            } else if ($preCharge > 0) {
                $visit['hpd'] -= $preCharge;
                $preCharge = 0;
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
            $totalDonationPaid += $visit['donpd'];
            $totalUnpaid += $unpaid;
            $totalSubsidy += $visit['fcg'] - $visit['chg'];
            $nites[] = $visit['nit'];

            if (!$statsOnly) {
                try {
                    $this->doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $matrix, $local, $rateTitles, $uS, $visitFee);
                } catch (\Exception $e) {
                    if (isset($writer)) {
                        die();
                    }
                }
            }
        } // End of last visit


        //
        // Finalize and print.
        //

        // Remove 0-total individual taxes
        foreach ($this->eachTaxPaid as $k => $v) {
            if (round($totalEachTaxPaid[$k], 2) == 0) {
                unset($totalEachTaxPaid[$k]);
                unset($matrix["paid_$k"]);
                unset($fltrdFields["paid_$k"]);
            }
            if (round($totalEachTaxCharged[$k], 2) == 0) {
                unset($totalEachTaxCharged[$k]);
                unset($matrix["chg_$k"]);
                unset($fltrdFields["chg_$k"]);
            }
        }


        if ($local) {

            $avDailyFee = 0;
            $avGuestFee = 0;
            $medDailyFee = 0;

            if ($totalNights > 0) {
                $avDailyFee = $totalCharged / $totalNights;

                array_multisort($chargesAr);
                $entries = count($chargesAr);
                $emod = $entries % 2;

                if ($emod > 0) {
                    // odd number of entries
                    $medDailyFee = $chargesAr[(ceil($entries / 2) - 1)];
                } else {
                    $medDailyFee = ($chargesAr[($entries / 2) - 1] + $chargesAr[($entries / 2)]) / 2;
                }
            }

            if (($totalNights + $totalGuestNights) > 0 && $uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
                $avGuestFee = $totalCharged / ($totalNights + $totalGuestNights); // $totalGuestNights is actually total additional nights: total guest nights = $toalNights + $totalGuestNights
            }

            // Header
            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTh($f[0]);
            }

            $tbl = new HTMLTable();
            $tbl->addHeaderTr($tr);


            // Body
            $rowcount = count($matrix['idVisit']);
            $counter = 0;

            // unwind the matrix into a table
            while($counter < $rowcount) {
                $tr = '';
                foreach ($matrix as $k=> $m) {
                    $tr .= HTMLTable::makeTd($m[$counter], isset($fltrdFields[$k][6]) ? $fltrdFields[$k][6] : []);
                }
                $tbl->addBodyTr($tr);
                $counter++;
            }


            // totals footer
            $tr = '';
            foreach ($fltrdFields as $f) {

                $entry = '';

                $entry = match ($f[1]) {
                    'nights' => $totalNights,
                    'days' => $totalDays,
                    'gnights' => $totalGuestNights,
                    'lodg' => '$' . number_format($totalLodgingCharge, 2),
                    'visitFee' => '$' . number_format($totalVisitFee, 2),
                    'adjch' => '$' . number_format($totalAddnlCharged, 2),
                    'adjchtx' => '$' . number_format($totalAddnlTax, 2),
                    'taxcgd' => '$' . number_format($totalTaxCharged, 2),
                    'taxpd' => '$' . number_format($totalTaxPaid, 2),
                    'taxpndg' => '$' . number_format($totalTaxPending, 2),
                    'totch' => '$' . number_format($totalCharged, 2),
                    'gpaid' => '$' . number_format($totalGuestPaid, 2),
                    'thdpaid' => '$' . number_format($totalthrdPaid, 2),
                    'hpaid' => '$' . number_format($totalHousePaid, 2),
                    'totpd' => '$' . number_format($totalPaid, 2),
                    'unpaid' => '$' . number_format($totalUnpaid, 2),
                    'pndg' => '$' . number_format($totalAmtPending, 2),
                    'sub' => '$' . number_format($totalSubsidy, 2),
                    'rateAdj' => ' ',
                    'meanRate' => '$' . number_format($avDailyFee, 2),
                    'meanGstRate' => '$' . number_format($avGuestFee, 2),
                    'donpd' => '$' . number_format($totalDonationPaid, 2),
                    default => '',
                };

                // Individual tax totals
                if (isset($totalEachTaxPaid[$f[1]])) {
                    $entry = '$' . number_format($totalEachTaxPaid[$f[1]], 2);
                }
                if (isset($totalEachTaxCharged[$f[1]])) {
                    $entry = '$' . number_format($totalEachTaxCharged[$f[1]], 2);
                }

                if ($entry != '') {
                    $entry = HTMLContainer::generateMarkup('p', $entry, ['style' => 'font-weight:bold;text-decoration: underline;']);
                }

                $tr .= HTMLTable::makeTd($entry . ($statsOnly ? '' : ' ' . $f[0]), ['style' => 'vertical-align:top;']);
            }

            if ($statsOnly) {
                $tbl->addBodyTr($tr);
            } else {
                $tbl->addFooterTr($tr);
            }

            // Main data table
            $dataTable = $tbl->generateMarkup(['id' => 'tblrpt', 'class' => 'display compact', 'style' => 'width:100%']);

            // Stats panel
            $statsTable = $this->newStatsPanel($dbh, $nites, $rates, $totalCatNites, $start, $end, $categories, $avDailyFee, $medDailyFee, $rescGroup[0], $uS->siteName);

            return ['data' => $dataTable, 'stats' => $statsTable];

        } else {
            // Setup for excel output
            $reportRows = 1;

            $fileName = 'VisitReport';
            $writer = new ExcelHelper($fileName);
            $types = [
                's' => 'string',
                'n' => 'integer',
                'money' => 'money', // '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)'
                'date' => 'MM/DD/YYYY',
            ];


            //build header
            $colWidths = [];
            $header = [];

            foreach ($fltrdFields as $field) {
                if ($field[5] != "" && $field[5] == '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)') { //if format is money
                    $header[$field[0]] = $types['s'];
                    $colWidths[] = 15;
                } elseif (isset($field[7]) && $field[7] == "date") { //if format is date
                    $header[$field[0]] = $types['date'];
                    $colWidths[] = 15;
                } elseif ($field[4] == 'n') { //if format is integer
                    $header[$field[0]] = 'integer';
                    $colWidths[] = 10;
                } else { //otherwise set format as string
                    $header[$field[0]] = 'string';
                    $colWidths[] = 20;
                }
            }

            try {
                $hdrStyle = $writer->getHdrStyle($colWidths);
                $writer->writeSheetHeader('Sheet1', $header, $hdrStyle);
            } catch (\Exception $e) {
                $writer->download();
            }

            // body
            $rowcount = count($matrix['idVisit']);
            $counter = 0;

            while($counter < $rowcount) {
                $flds = [];
                $n = 0;

                foreach ($matrix as $m) {
                    $flds[$n++] = $m[$counter];
                }

                $row = ExcelHelper::convertStrings($header, $flds);
                $writer->writeSheetRow('Sheet1', $row);
                $counter++;
            }

            // Finish
            HouseLog::logDownload($dbh, 'Visit Report', "Excel", "Visit Report for " . $start . " - " . $end . " downloaded", $uS->username);
            $writer->download();

        }

    }


}