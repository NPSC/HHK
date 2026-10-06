<?php

namespace HHK\House\Report;

use HHK\Common;
use HHK\ExcelHelper;
use HHK\House\Resource\ResourceTypes;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\Item;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Purchase\RoomRate;
use HHK\Purchase\ValueAddedTax;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\SysConst\{GLTableNames, InvoiceStatus, ItemId, ItemPriceCode, ItemType, ResourceStatus, RoomRateCategories, VolMemberType};
use HHK\TableLog\HouseLog;
use Pdo\Mysql;


/**
 * VisitIntervalReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Visit Report - one row per visit, with lodging charges and payments computed for the report period.
 *
 * Charges are computed in PHP by walking every visit span through the house price model,
 * so getResultSet() runs that aggregation instead of returning raw query rows.
 *
 * @author Eric
 */

class VisitIntervalReport extends AbstractReport implements ReportInterface {

    const MONEY_FORMAT = '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)';

    public array $locations;
    public array $diags;
    protected array $adjusts;
    protected array $rescGroups;
    protected bool $useTaxes = FALSE;
    protected array $eachTaxPaid = [];
    protected string $eachTaxSql = '';
    protected bool $statsOnly = FALSE;

    /** @var array one entry per reported visit: r (last span row), visit (accumulators), paid, unpaid, departureDT */
    protected array $visitRows = [];
    protected bool $visitsLoaded = FALSE;
    protected array $totals = [];
    protected array $nites = [];
    protected array $chargesAr = [];
    protected array $totalCatNites = [];
    protected array $categories = [];
    protected array $rateTitles = [];

    public function __construct(\PDO $dbh, array $request = [])
    {
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Visit Report';
        $this->inputSetReportName = "visit";
        $this->locations = Common::readGenLookupsPDO($dbh, 'Location');
        $this->diags = Common::readGenLookupsPDO($dbh, 'Diagnosis');
        $this->adjusts = Common::readGenLookupsPDO($dbh, 'Addnl_Charge');
        $this->rescGroups = Common::readGenLookupsPDO($dbh, 'Room_Group');
        $this->statsOnly = isset($request['btnStatsOnly-' . $this->inputSetReportName]);

        $this->filterOpts = [
            "cbZeroNight" => [
                "title" => "Include 0-night stays",
                "type" => "checkbox"
            ]
        ];

        // Look for taxes
        $tstmt = $dbh->query("Select i.idItem, i.Percentage, i.Description from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = " . ItemType::Tax . " where i.Deleted = 0");

        while ($taxItem = $tstmt->fetch(\PDO::FETCH_ASSOC)) {

            $this->eachTaxSql .= " ifnull((select sum(il.Amount) from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
        where il.Deleted = 0 and i.Deleted = 0 and i.Status in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "') and il.Item_Id = " . $taxItem['idItem'] . " and i.Sold_To_Id != " . $uS->subsidyId . " and il.Source_Item_Id = " . ItemId::Lodging . " and i.Order_Number = v.idVisit),
            0) as `paid_" . $taxItem['idItem'] . "`, ";

            $this->eachTaxPaid[$taxItem['idItem']]['desc'] = $taxItem['Description'];
            $this->eachTaxPaid[$taxItem['idItem']]['perc'] = $taxItem['Percentage'];

            $this->useTaxes = TRUE;
        }

        $this->filter = new ReportFilter();
        $this->filter->createResourceGroups($dbh);
        $this->filter->loadSelectedResourceGroups();

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }


    /**
     * array: title, ColumnName, checked, fixed, Excel Type, Excel Style, [td parms], [date]
     */
    public function makeFields():array {
        $uS = Session::getInstance();
        $labels = Labels::getLabels();

        $fields[] = ['Visit Id', 'idVisit', 'checked', 'f', 'n', '', ['style' => 'text-align:center;']];
        $fields[] = [$labels->getString('MemberType', 'primaryGuest', 'Primary Guest'), 'idPrimaryGuest', 'checked', '', 's', '', []];
        $fields[] = [$labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' Phone', 'pg_phone', '', '', 's', '', []];
        $fields[] = [$labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' Email', 'pg_email', '', '', 's', '', []];

        // PG address.
        $pgFields = ['pgAddr', 'pgCity'];
        $pgTitles = [$labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' Address', $labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' City'];

        if ($uS->county) {
            $pgFields[] = 'pgCounty';
            $pgTitles[] = $labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' County';
        }

        $pgFields = array_merge($pgFields, ['pgState', 'pgCountry', 'pgZip']);
        $pgTitles = array_merge($pgTitles, [$labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' State', $labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' Country', $labels->getString('MemberType', 'primaryGuest', 'Primary Guest') . ' Zip']);

        $fields[] = [$pgTitles, $pgFields, '', '', 's', '', []];


        $fields[] = [$labels->getString('MemberType', 'patient', 'Patient'), 'idPatient', 'checked', '', 's', '', []];
        $fields[] = [$labels->getString('MemberType', 'patient', 'Patient') . ' Phone', 'pa_phone', '', '', 's', '', []];
        $fields[] = [$labels->getString('MemberType', 'patient', 'Patient') . ' Email', 'pa_email', '', '', 's', '', []];

        // Patient address.
        if ($uS->PatientAddr) {

            $pFields = ['pAddr', 'pCity'];
            $pTitles = [$labels->getString('MemberType', 'patient', 'Patient') . ' Address', $labels->getString('MemberType', 'patient', 'Patient') . ' City'];

            if ($uS->county) {
                $pFields[] = 'pCounty';
                $pTitles[] = $labels->getString('MemberType', 'patient', 'Patient') . ' County';
            }

            $pFields = array_merge($pFields, ['pState', 'pCountry', 'pZip']);
            $pTitles = array_merge($pTitles, [$labels->getString('MemberType', 'patient', 'Patient') . ' State', $labels->getString('MemberType', 'patient', 'Patient') . ' Country', $labels->getString('MemberType', 'patient', 'Patient') . ' Zip']);

            $fields[] = [$pTitles, $pFields, '', '', 's', '', []];
        }

        if ($uS->ShowBirthDate) {
            $fields[] = [$labels->getString('MemberType', 'patient', 'Patient') . ' DOB', 'pBirth', '', '', 'n', "", [], 'date'];
        }

        // Referral Agent
        if ($uS->ReferralAgent) {
            $fields[] = [$labels->getString('hospital', 'referralAgent', 'Ref. Agent'), 'Referral_Agent', 'checked', '', 's', '', []];
        }

        // Hospital
        if (count($this->filter->getHospitals()) > 1) {

            if (count($this->filter->getAList()) > 0) {
                $fields[] = [$labels->getString('hospital', 'hospital', 'Hospital') . " / Assoc", 'hospitalAssoc', 'checked', '', 's', '', []];
            } else {
                $fields[] = [$labels->getString('hospital', 'hospital', 'Hospital'), 'hospitalAssoc', 'checked', '', 's', '', []];
            }
        }

        if ($uS->searchMRN) {
            $fields[] = [$labels->getString('hospital', 'MRN', 'MRN'), 'MRN', '', '', 's', '', []];
        }

        if ($uS->Doctor) {
            $fields[] = ["Doctor", 'Doctor', '', '', 's', '', []];
        }

        if (count($this->locations) > 0) {
            $fields[] = [$labels->getString('hospital', 'location', 'Location'), 'Location', 'checked', '', 's', '', []];
        }

        if (count($this->diags) > 0) {
            $fields[] = [$labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', 'checked', '', 's', '', []];
        }

        if ($uS->ShowDiagTB) {
            $fields[] = [$labels->getString('hospital', 'diagnosisDetail', 'Diagnosis Details'), 'Diagnosis2', 'checked', '', 's', '20', []];
        }

        if ($uS->InsuranceChooser) {
            $fields[] = [$labels->getString('MemberType', 'patient', 'Patient') . " Insurance", 'Insurance', '', '', 's', '', []];
        }

        $fields[] = ["Arrive", 'Arrival', 'checked', '', 'n', '', [], 'date'];
        $fields[] = ["Depart", 'Departure', 'checked', '', 'n', '', [], 'date'];
        $fields[] = ["Room", 'Title', 'checked', '', 's', '', []];

        if ($uS->VisitFee) {
            $fields[] = [$labels->getString('statement', 'cleaningFeeLabel', "Clean Fee"), 'visitFee', 'checked', '', 's', '', ['style' => 'text-align:right;']];
        }

        if (count($this->adjusts) > 0) {
            $addnlChargeLabel = (new Item($this->dbh, ItemId::AddnlCharge))->getDescription();
            $fields[] = [$addnlChargeLabel, 'adjch', 'checked', '', 's', '', ['style' => 'text-align:right;']];

            if ($this->useTaxes) {
                $fields[] = [$addnlChargeLabel . " Tax", 'adjchtx', 'checked', '', 's', '', ['style' => 'text-align:right;']];
            }
        }


        $fields[] = ["Nights", 'nights', 'checked', '', 'n', '', ['style' => 'text-align:center;']];
        $fields[] = ["Days", 'days', '', '', 'n', '', ['style' => 'text-align:center;']];

        $amtChecked = 'checked';

        if ($uS->RoomPriceModel !== ItemPriceCode::None) {

            if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {

                $fields[] = ['Extra ' . $labels->getString('MemberType', 'guest', 'Guest') . ' Nights', 'gnights', 'checked', '', 'n', '', ['style' => 'text-align:center;']];
                $fields[] = ["Rate Per " . $labels->getString('MemberType', 'guest', 'Guest'), 'rate', $amtChecked, '', 's', self::MONEY_FORMAT, []];
                $fields[] = ["Mean Rate Per " . $labels->getString('MemberType', 'guest', 'Guest'), 'meanGstRate', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];

            } else {

                $fields[] = ["Rate", 'rate', $amtChecked, '', 's', self::MONEY_FORMAT, []];

                if ($uS->RoomPriceModel == ItemPriceCode::NdayBlock) {
                    $fields[] = ["Adj. Rate", 'rateAdj', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
                }

                $fields[] = ["Mean Rate", 'meanRate', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            }

            $fields[] = ["Lodging Charge", 'lodg', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];

            if ($this->useTaxes) {
                $tFields = ['taxcgd'];
                $tTitles = ['Lodging Tax Charged'];

                foreach ($this->eachTaxPaid as $k => $t) {
                    $tTitles[] = rtrim(number_format($t['perc'], 3), "0.") . '% Tax Charged';
                    $tFields[] = "chg_$k";
                }

                $fields[] = [$tTitles, $tFields, $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            }

            $fields[] = [$labels->getString('MemberType', 'visitor', 'Guest') . " Paid", 'gpaid', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            $fields[] = ["3rd Party Paid", 'thdpaid', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            $fields[] = ["House Paid", 'hpaid', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            $fields[] = ["Lodging Paid", 'totpd', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];

            if ($this->useTaxes) {

                $tFields = ['taxpd'];
                $tTitles = ['Lodging Tax Paid'];

                foreach ($this->eachTaxPaid as $k => $t) {
                    $tTitles[] = rtrim(number_format($t['perc'], 3), "0.") . '% Tax Paid';
                    $tFields[] = "paid_$k";
                }

                $fields[] = [$tTitles, $tFields, $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            }

            $fields[] = ["Unpaid", 'unpaid', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            $fields[] = ["Pending", 'pndg', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];

            if ($this->useTaxes) {
                $fields[] = ['Tax Pending', 'taxpndg', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            }

            if ($uS->RoomPriceModel != ItemPriceCode::NdayBlock) {
                $fields[] = ["Rate Subsidy", 'sub', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
            }

            $fields[] = ["Contribution", 'donpd', $amtChecked, '', 's', self::MONEY_FORMAT, ['style' => 'text-align:right;']];
        }

        return $fields;
    }

    public function makeFilterMkup():void {

        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();

        if (count($this->filter->getHospitals()) > 1) {
            $this->filterMkup .= $this->filter->hospitalMarkup()->generateMarkup();
        }

        $this->filterMkup .= $this->filter->resourceGroupsMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    protected function makeExtraButtonsMkup(): string {
        return HTMLInput::generateMarkup("Stats Only", ["type" => "submit", "name" => "btnStatsOnly-" . $this->inputSetReportName, "class" => "ui-button ui-corner-all ui-widget"]);
    }

    protected function getCellAttrs(array $field): array {
        return (isset($field[6]) && is_array($field[6])) ? $field[6] : [];
    }

    /**
     * One row per visit span, ordered by visit.
     */
    public function makeQuery():void {

        $uS = Session::getInstance();
        $start = $this->filter->getReportStart();
        $end = $this->filter->getQueryEnd();
        $subsidyId = $uS->subsidyId;

        // Hospitals
        $whHosp = '';
        foreach ($this->filter->getSelectedHosptials() as $a) {
            if ($a != '') {
                $whHosp .= ($whHosp == '' ? '' : ',') . $a;
            }
        }

        $whAssoc = '';
        foreach ($this->filter->getSelectedAssocs() as $a) {
            if ($a != '') {
                $whAssoc .= ($whAssoc == '' ? '' : ',') . $a;
            }
        }

        if ($whHosp != '') {
            $whHosp = " and hs.idHospital in (" . $whHosp . ") ";
        }

        if ($whAssoc != '') {
            $whAssoc = " and hs.idAssociation in (" . $whAssoc . ") ";
        }

        $this->query = "select
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
    concat(ifnull(napg.Address_1, ''), '', ifnull(napg.Address_2, ''))  as pgAddr,
    ifnull(napg.City, '') as pgCity,
    ifnull(napg.County, '') as pgCounty,
    ifnull(napg.State_Province, '') as pgState,
    ifnull(napg.Country_Code, '') as pgCountry,
    ifnull(napg.Postal_Code, '') as pgZip,
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
    " . $this->eachTaxSql . "
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
            0) as `VisitFeePaid`,
    IFNULL(`nph`.`Phone_Num`,'') as 'pg_phone',
    IFNULL(`ne`.`Email`,'') as 'pg_email',
    IFNULL(`npp`.`Phone_Num`,'') as 'pa_phone',
    IFNULL(`npe`.`Email`,'') as 'pa_email'

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
        left join
    name_address napg on n.idName = napg.idName and n.Preferred_Mail_Address = napg.Purpose
        left join
    name_email npe on np.idName = npe.idName and np.Preferred_Email = npe.Purpose
        left join
    name_phone npp on np.idName = npp.idName and np.Preferred_Phone = npp.Phone_Code
        left join
    name_email ne on n.idName = ne.idName and n.Preferred_Email = ne.Purpose
        left join
    name_phone nph on n.idName = nph.idName and n.Preferred_Phone = nph.Phone_Code
where
    v.`Status` not in ('p', 'c')
    AND v.Arrival_Date < '$end'
  AND COALESCE(v.Span_End,
          CASE WHEN NOW() > v.Expected_Departure
               THEN NOW() ELSE v.Expected_Departure END
      ) >= '$start'
  AND v.Span_Start < '$end' "
            . $whHosp . $whAssoc . " group by v.idVisit, v.Span order by v.idVisit, v.Span";
    }

    /**
     * Summary of getGuestNights - returns total of main guest nights plus any additional guest nights
     * @param mixed $actual
     * @param mixed $preInterval
     * @return void
     */
    protected function getGuestNights(&$actual, &$preInterval)
    {

        $uS = Session::getInstance();
        $ageYears = $uS->StartGuestFeesYr;
        $start = $this->filter->getReportStart();
        $end = $this->filter->getQueryEnd();

        $stmt = $this->dbh->query("SELECT
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
     * Walk every visit span through the price model, collapsing spans into one entry per visit
     * in $this->visitRows and accumulating the report totals.  Runs once per request.
     */
    protected function loadVisits(): void
    {
        if ($this->visitsLoaded) {
            return;
        }
        $this->visitsLoaded = TRUE;

        $uS = Session::getInstance();
        $dbh = $this->dbh;
        $rescGroup = $this->rescGroups[$this->filter->getSelectedResourceGroups()];

        $this->categories = Common::readGenLookupsPDO($dbh, $rescGroup[2], 'Description');
        // add default category
        $this->categories[] = [0 => '', 1 => '(default)'];


        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        // Make titles for all the rates
        $this->rateTitles = RoomRate::makeDescriptions($dbh);

        // Get Guest days
        $actualGuestNights = [];
        $piGuestNights = [];

        if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
            // routine defines acutalGuestNights and piGuestNights.
            $this->getGuestNights($actualGuestNights, $piGuestNights);

        }

        $curVisit = 0;
        $curRoom = 0;
        $curRate = '';
        $curRateId = 0;
        $curAdj = 0;
        $curAmt = 0;

        $this->totals = [
            'charged' => 0,
            'visitFee' => 0,
            'lodgingCharge' => 0,
            'addnlCharged' => 0,
            'addnlTax' => 0,
            'taxCharged' => 0,
            'taxPaid' => 0,
            'taxPending' => 0,
            'eachTaxPaid' => [],
            'eachTaxCharged' => [],
            'paid' => 0,
            'housePaid' => 0,
            'amtPending' => 0,
            'guestPaid' => 0,
            'thrdPaid' => 0,
            'subsidy' => 0,
            'unpaid' => 0,
            'donationPaid' => 0,
            'nights' => 0,
            'guestNights' => 0,
            'days' => 0,
        ];

        foreach ($this->eachTaxPaid as $k => $v) {
            $this->totals['eachTaxPaid'][$k] = 0;
            $this->totals['eachTaxCharged'][$k] = 0;
        }

        $this->totalCatNites = [];

        foreach ($this->categories as $c) {
            $this->totalCatNites[$c[0]] = 0;
        }

        $visit = [];
        $savedr = [];

        $reportEndDT = new \DateTime($this->filter->getQueryEnd() . " 00:00:00");
        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        $vat = new ValueAddedTax($dbh);

        $this->makeQuery();

        $dbh->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, FALSE);

        $stmt = $dbh->query($this->query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // records ordered by idVisit.
            if ($curVisit != $r['idVisit']) {

                // If i did not just start
                if (count($visit) > 0 && ($visit['nit'] > 0 || $this->isReportedZeroNightStay($savedr))) {
                    $this->closeVisit($visit, $savedr, $now, $reportEndDT);
                }

                $curVisit = $r['idVisit'];
                $curRate = '';
                $curRateId = 0;
                $curAdj = 0;
                $curAmt = 0;
                $curRoom = 0;

                $addChgTax = 0;

                $taxSums = $vat->getTaxedItemSums($r['idVisit'], $r['Visit_Age']);

                if (isset($taxSums[ItemId::AddnlCharge])) {
                    $addChgTax = $taxSums[ItemId::AddnlCharge];
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
                    'rtc' => 0,  // Rate Category counter
                    'totVisitNights'=>0 //total visit nights for all spans
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
            $this->totalCatNites[$r[$rescGroup[0]]] += $days;
            $visit['totVisitNights'] += $r['Visit_Age'];

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

                $spanCharges = $priceModel->amountCalculator($days, $r['idRoom_Rate'], $r['Rate_Category'], $r['Pledged_Rate'], $gdays) * $adjRatio;
                $visit['chg'] += $spanCharges;

                // Get the (current) taxing items for this visit
                $taxedItems = $vat->getCurrentTaxingItems($r['idVisit'], $visit["totVisitNights"], ItemId::Lodging);

                foreach ($taxedItems as $ti) {
                    $thisTaxChg = round($spanCharges * $ti->getPercentTax() / 100, 2);
                    $visit["chg_" . $ti->getIdTaxingItem()] += $thisTaxChg;
                    $visit['taxcgd'] += $thisTaxChg;
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

        $dbh->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, TRUE);


        // process the last visit.
        if (count($savedr) > 0 && ($visit['nit'] > 0 || $this->isReportedZeroNightStay($savedr))) {
            $this->closeVisit($visit, $savedr, $now, $reportEndDT);
        }


        // Remove 0-total individual taxes
        foreach ($this->eachTaxPaid as $k => $v) {
            if (round($this->totals['eachTaxPaid'][$k], 2) == 0) {
                unset($this->totals['eachTaxPaid'][$k]);
                unset($this->filteredFields["paid_$k"]);
            }
            if (round($this->totals['eachTaxCharged'][$k], 2) == 0) {
                unset($this->totals['eachTaxCharged'][$k]);
                unset($this->filteredFields["chg_$k"]);
            }
        }

        $this->filteredTitles = array_values(array_map(fn($f) => $f[0], $this->filteredFields));

        // Mean and median charges per night
        $this->totals['avDailyFee'] = 0;
        $this->totals['avGuestFee'] = 0;
        $this->totals['medDailyFee'] = 0;

        if ($this->totals['nights'] > 0) {
            $this->totals['avDailyFee'] = $this->totals['charged'] / $this->totals['nights'];

            $chargesAr = $this->chargesAr;
            array_multisort($chargesAr);
            $entries = count($chargesAr);
            $emod = $entries % 2;

            if ($emod > 0) {
                // odd number of entries
                $this->totals['medDailyFee'] = $chargesAr[intdiv($entries, 2)];
            } else {
                $this->totals['medDailyFee'] = ($chargesAr[($entries / 2) - 1] + $chargesAr[($entries / 2)]) / 2;
            }
        }

        if (($this->totals['nights'] + $this->totals['guestNights']) > 0 && $uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
            $this->totals['avGuestFee'] = $this->totals['charged'] / ($this->totals['nights'] + $this->totals['guestNights']); // guestNights is actually total additional nights: total guest nights = nights + guestNights
        }
    }

    /**
     * True when the "Include 0-night stays" option is on and the visit checked in and out on the same day.
     *
     * @param array $savedr the visit's last span row
     */
    protected function isReportedZeroNightStay(array $savedr): bool
    {
        if (!isset($this->request['cbZeroNight']) || $savedr['Actual_Departure'] == '') {
            return FALSE;
        }

        return (new \DateTime($savedr['Arrival_Date']))->format('Y-m-d') == (new \DateTime($savedr['Actual_Departure']))->format('Y-m-d');
    }

    /**
     * Finish a visit: allocate payments against the in-period charges, add it to the totals and save it for output.
     *
     * @param array $visit accumulated values for the visit
     * @param array $savedr the visit's last span row
     */
    protected function closeVisit(array $visit, array $savedr, \DateTime $now, \DateTime $reportEndDT): void
    {
        $uS = Session::getInstance();
        $t = &$this->totals;

        $t['lodgingCharge'] += $visit['chg'];

        // 0-night stays have no per-night charge to add to the median
        if ($visit['nit'] > 0) {
            $this->chargesAr[] = $visit['chg'] / $visit['nit'];
        }
        $t['addnlCharged'] += ($visit['addch']);

        $t['taxCharged'] += $visit['taxcgd'];
        $t['addnlTax'] += $visit['adjchtx'];
        $t['taxPaid'] += $visit['taxpd'];
        $t['taxPending'] += $visit['taxpndg'];

        // Individual tax totals
        foreach ($this->eachTaxPaid as $k => $v) {
            $t['eachTaxPaid'][$k] += isset($visit["paid_$k"]) ? $visit["paid_$k"] : 0;
            $t['eachTaxCharged'][$k] += isset($visit["chg_$k"]) ? $visit["chg_$k"] : 0;
        }

        if ($visit['nit'] > $uS->VisitFeeDelayDays) {
            $t['visitFee'] += $visit['vfa'];
        }

        $t['charged'] += $visit['chg'];
        $t['amtPending'] += $visit['pndg'];
        $t['nights'] += $visit['nit'];
        $t['guestNights'] += max($visit['gnit'] - $visit['nit'], 0);

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

        $t['days'] += $visit['day'];
        $t['paid'] += $dPaid;
        $t['housePaid'] += $visit['hpd'];
        $t['guestPaid'] += $visit['gpd'];
        $t['thrdPaid'] += $visit['thdpd'];
        $t['donationPaid'] += $visit['donpd'];
        $t['unpaid'] += $unpaid;
        $t['subsidy'] += $visit['fcg'] - $visit['chg'];
        $this->nites[] = $visit['nit'];

        $this->visitRows[] = ['r' => $savedr, 'visit' => $visit, 'paid' => $dPaid, 'unpaid' => $unpaid, 'departureDT' => $departureDT];
    }

    /**
     * Pretify a visit into an output row.
     *
     * @param array $visitRow an entry of $this->visitRows
     * @param bool $html true for the web page (links, icons, ISO dates); false for Excel and email
     * @return array
     */
    protected function formatRow(array $visitRow, bool $html): array
    {
        $uS = Session::getInstance();
        $r = $visitRow['r'];
        $visit = $visitRow['visit'];
        $paid = $visitRow['paid'];
        $unpaid = $visitRow['unpaid'];
        $departureDT = $visitRow['departureDT'];

        $arrivalDT = new \DateTime($r['Arrival_Date']);

        if ($r['Rate_Category'] == RoomRateCategories::Fixed_Rate_Category) {

            $r['rate'] = $r['Pledged_Rate'];

        } else if (isset($visit['rateId']) && isset($this->rateTitles[$visit['rateId']])) {

            $rateTxt = $this->rateTitles[$visit['rateId']];

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

        if ($uS->VisitFee) {

            if ($visit['vfa'] > 0 && $visit['vfa'] == $visit['vfpd']) {

                $r['visitFee'] = number_format($visit['vfa'], 2);
                $visitFeePaid = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-circle-check', 'title' => 'Fees paid'));

            } else if ($visit['vfa'] > 0 && $uS->VisitFeeDelayDays < $visit['nit']) {

                $r['visitFee'] = number_format($visit['vfa'], 2);

            } else {

                $r['visitFee'] = '';
            }
        }

        $addPaidIcon = '';

        if ($visit['addch'] > 0 && $visit['addch'] <= $visit['addpd']) {

            $r['adjch'] = number_format($visit['addch'], 2);
            $addPaidIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-circle-check', 'title' => 'Charges paid'));

        } else if ($visit['addch'] > 0) {

            $r['adjch'] = number_format($visit['addch'], 2);

        } else {

            $r['adjch'] = '';
        }


        if ($html) {

            $changeRoomIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-info mr-2', 'title' => 'Changed Rooms'));
            $changeRateIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-info mr-2', 'title' => 'Room Rate Changed'));
            $insInfoIcon = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-comment insAction', 'style' => 'cursor:pointer;', 'data-idName' => $r['idPatient'], 'id' => 'insAction' . $r['idPatient'], 'title' => 'View Insurance'));

            $r['idVisit'] = HTMLContainer::generateMarkup('div', $r['idVisit'], ['class' => 'hhk-viewVisit', 'data-gid' => $r['idPrimaryGuest'], 'data-vid' => $r['idVisit'], 'data-span' => $r['Span'], 'style' => 'display:inline-table;']);
            $r['idPrimaryGuest'] = HTMLContainer::generateMarkup('a', $r['Name_Last'] . ', ' . $r['Name_First'], ['href' => 'GuestEdit.php?id=' . $r['idPrimaryGuest'] . '&psg=' . $r['idPsg']]);
            $r['idPatient'] = HTMLContainer::generateMarkup('a', $r['Patient_Last'] . ', ' . $r['Patient_First'], ['href' => 'GuestEdit.php?id=' . $r['idPatient'] . '&psg=' . $r['idPsg']]);
            $r['Arrival'] = $arrivalDT->format('c');
            $r['Departure'] = $departureDT->format('c');

            if ($r['pBirth'] != '') {
                $pBirthDT = new \DateTime($r['pBirth']);
                $r['pBirth'] = $pBirthDT->format('c');
            }

            if ($visitFeePaid != '') {
                $r['visitFee'] = "<div class='d-flex justify-content-between align-items-center'>" . $visitFeePaid . $r['visitFee'] . "</div>";
            }

            if ($addPaidIcon != '') {
                $r['adjch'] = "<div class='d-flex justify-content-between align-items-center'>" . $addPaidIcon . $r['adjch'] ."</div>";
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
        }

        return $r;
    }

    /**
     * @param bool $html
     * @return array formatted rows; rows that fail to format are left out
     */
    protected function formatRows(bool $html): array
    {
        $rows = [];

        foreach ($this->visitRows as $visitRow) {
            try {
                $rows[] = $this->formatRow($visitRow, $html);
            } catch (\Exception $e) {
            }
        }

        return $rows;
    }

    /**
     * Rows formatted for Excel/email (no markup).
     */
    public function getResultSet():array {
        $this->loadVisits();
        $this->resultSet = ($this->statsOnly ? [] : $this->formatRows(FALSE));
        return $this->resultSet;
    }

    public function generateMarkup(string $outputType = ""){
        $this->loadVisits();
        $this->resultSet = ($this->statsOnly ? [] : $this->formatRows($outputType != 'email'));
        $this->statsMkup = $this->makeStatsPanel();

        return parent::generateMarkup($outputType);
    }

    /**
     * Totals row.  Becomes the only body row in Stats Only mode.
     */
    protected function makeFooterMkup(HTMLTable $tbl): void {

        $t = $this->totals;

        //remap TotalEachTax to display totals
        $totalEachTaxPaid = [];
        foreach ($t['eachTaxPaid'] as $k => $v) {
            $totalEachTaxPaid["paid_" . $k] = $v;
        }

        $totalEachTaxCharged = [];
        foreach ($t['eachTaxCharged'] as $k => $v) {
            $totalEachTaxCharged["chg_" . $k] = $v;
        }

        $tr = '';
        foreach ($this->filteredFields as $f) {

            $entry = match ($f[1]) {
                'nights' => $t['nights'],
                'days' => $t['days'],
                'gnights' => $t['guestNights'],
                'lodg' => '$' . number_format($t['lodgingCharge'], 2),
                'visitFee' => '$' . number_format($t['visitFee'], 2),
                'adjch' => '$' . number_format($t['addnlCharged'], 2),
                'adjchtx' => '$' . number_format($t['addnlTax'], 2),
                'taxcgd' => '$' . number_format($t['taxCharged'], 2),
                'taxpd' => '$' . number_format($t['taxPaid'], 2),
                'taxpndg' => '$' . number_format($t['taxPending'], 2),
                'totch' => '$' . number_format($t['charged'], 2),
                'gpaid' => '$' . number_format($t['guestPaid'], 2),
                'thdpaid' => '$' . number_format($t['thrdPaid'], 2),
                'hpaid' => '$' . number_format($t['housePaid'], 2),
                'totpd' => '$' . number_format($t['paid'], 2),
                'unpaid' => '$' . number_format($t['unpaid'], 2),
                'pndg' => '$' . number_format($t['amtPending'], 2),
                'sub' => '$' . number_format($t['subsidy'], 2),
                'rateAdj' => ' ',
                'meanRate' => '$' . number_format($t['avDailyFee'], 2),
                'meanGstRate' => '$' . number_format($t['avGuestFee'], 2),
                'donpd' => '$' . number_format($t['donationPaid'], 2),
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

            $tr .= HTMLTable::makeTd($entry . ($this->statsOnly ? '' : ' ' . $f[0]), ['style' => 'vertical-align:top;']);
        }

        if ($this->statsOnly) {
            $tbl->addBodyTr($tr);
        } else {
            $tbl->addFooterTr($tr);
        }
    }

    /**
     * Room utilization and visit length statistics.
     */
    protected function makeStatsPanel(): string
    {
        $visitNites = $this->nites;

        // Stats panel
        if (count($visitNites) < 1) {
            return '';
        }

        $uS = Session::getInstance();
        $dbh = $this->dbh;
        $start = $this->filter->getReportStart();
        $end = $this->filter->getQueryEnd();
        $categories = $this->categories;
        $totalCatNites = $this->totalCatNites;
        $rescGroup = $this->rescGroups[$this->filter->getSelectedResourceGroups()][0];

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
        $rescStatuses = Common::readGenLookupsPDO($dbh, "Resource_Status");
        $roomReport->collectUtilizationData($dbh, $start, $end, $rescStatuses);
        $daysAr = $roomReport->getDays();

        // transform room report data for visit report
        while ($r = $rstmt->fetch(\PDO::FETCH_ASSOC)) {
            if (isset($daysAr[$r['idResource']])) {
                $totals = array(ResourceStatus::Available => 0, ResourceStatus::OutOfService => 0, ResourceStatus::Delayed => 0, ResourceStatus::Unavailable => 0, ResourceStatus::Closed => 0, 'nonClean' => 0);
                foreach ($daysAr[$r['idResource']] as $day) {
                    $totals[ResourceStatus::Available] += ($day['n'] + $day['o'] + $day['t'] + $day['u'] + $day['c'] + $day['b'] == 0 ? 1 : 0);
                    $totals[ResourceStatus::OutOfService] += $day['o'];
                    $totals[ResourceStatus::Delayed] += $day['t'];
                    $totals[ResourceStatus::Unavailable] += $day['u'];
                    $totals[ResourceStatus::Closed] += $day['c'];
                    $totals['nonClean'] += $day['b'];
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
            $median = $visitNites[intdiv($entries, 2)];
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

        $sTbl->addBodyTr(HTMLTable::makeTd('Mean Room Charge per visit day:', array('class' => 'tdlabel')) . HTMLTable::makeTd('$' . number_format($this->totals['avDailyFee'], 2)));

        if ($uS->RoomPriceModel == ItemPriceCode::Dailey) {
            $sTbl->addBodyTr(HTMLTable::makeTd('Median Room Charge per visit day:', array('class' => 'tdlabel')) . HTMLTable::makeTd('$' . number_format($this->totals['medDailyFee'], 2)));
        }

        $sTbl->addBodyTr($trs[4]);

        $sTbl->addBodyTr($trs[5]);

        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('h3', 'Statistics')
            . HTMLContainer::generateMarkup('p', 'These numbers are specific to this report\'s selected filtering parameters.')
            . $sTbl->generateMarkup()
            , ['id' => 'visitStats', 'class' => 'mb-3']);

    }

    public function makeSummaryMkup():string {
        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        $hospitalTitles = '';
        $hospList = $this->filter->getHospitals();

        foreach ($this->filter->getSelectedAssocs() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }
        foreach ($this->filter->getSelectedHosptials() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }

        if ($hospitalTitles != '') {
            $h = trim($hospitalTitles);
            $hospitalTitles = substr($h, 0, strlen($h) - 1);
            $mkup .= HTMLContainer::generateMarkup('p', Labels::getString('hospital', 'hospitals', 'Hospitals') . ': ' . $hospitalTitles);
        } else {
            $mkup .= HTMLContainer::generateMarkup('p', 'All ' . Labels::getString('hospital', 'hospitals', 'Hospitals'));
        }

        if (isset($this->request['cbZeroNight'])) {
            $mkup .= HTMLContainer::generateMarkup('p', 'Includes 0-night stays');
        }

        return $mkup;

    }

    /**
     * Excel output keeps the old visit report's column types: money columns as numeric strings, dates as dates.
     */
    public function downloadExcel(string $fileName = "VisitReport"):void {

        $uS = Session::getInstance();

        $this->loadVisits();
        $rows = $this->formatRows(FALSE);

        $writer = new ExcelHelper($fileName);
        $writer->setAuthor($uS->username);
        $writer->setTitle($this->reportTitle);

        //build header
        $colWidths = [];
        $header = [];

        foreach ($this->filteredFields as $field) {
            if ($field[5] == self::MONEY_FORMAT) { //if format is money
                $header[$field[0]] = 'string';
                $colWidths[] = 15;
            } elseif (isset($field[7]) && $field[7] == "date") { //if format is date
                $header[$field[0]] = 'MM/DD/YYYY';
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
        foreach ($rows as $r) {
            $flds = [];

            foreach ($this->filteredFields as $f) {
                if ($r[$f[1]] != '' && $f[5] != '') {
                    $flds[] = strval(str_replace(',', '', $r[$f[1]]));
                } else {
                    $flds[] = html_entity_decode(strval($r[$f[1]]), ENT_QUOTES, 'UTF-8');
                }
            }

            $row = ExcelHelper::convertStrings($header, $flds);
            $writer->writeSheetRow('Sheet1', $row);
        }

        // Finish
        HouseLog::logDownload($this->dbh, 'Visit Report', "Excel", "Visit Report for " . $this->filter->getReportStart() . " - " . $this->filter->getQueryEnd() . " downloaded", $uS->username);
        $writer->download();
    }

}
