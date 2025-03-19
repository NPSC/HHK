<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Purchase\RoomRate;
use HHK\Purchase\ValueAddedTax;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\{ReservationStatus, ItemId};
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\ItemType;
use HHK\SysConst\RoomRateCategories;
use HHK\SysConst\VolMemberType;


/**
 * ReservationReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of VisitIntervalReport
 *
 * @author Eric
 */

class VisitIntervalReport extends AbstractReport implements ReportInterface {

    public array $locations;
    public array $diags;
    protected array $adjusts;
    protected array $rescGroups;
    protected $useTaxes;
    protected array $selectedRescGroups;

    public function __construct(\PDO $dbh, array $request = [])
    {
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Visit Report';
        $this->inputSetReportName = "visit";
        $this->locations = readGenLookupsPDO($dbh, 'Location');
        $this->diags = readGenLookupsPDO($dbh, 'Diagnosis');
        $this->adjusts = readGenLookupsPDO($dbh, 'Addnl_Charge');
        $this->useTaxes = FALSE;

        $tstmt = $dbh->query("Select count(idItem) from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = " . ItemType::Tax . " where i.Deleted = 0");
        $taxItems = $tstmt->fetchAll(\PDO::FETCH_NUM);
        if ($taxItems[0][0] > 0) {
            $this->useTaxes = TRUE;
        }

        $this->filter = new ReportFilter();
        $this->filter->createResourceGroups($this->dbh);

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }


    /**
     */
    public function makeCFields():array {
        $uS = Session::getInstance();
        $labels = Labels::getLabels();

        $cFields[] = array('Visit Id', 'idVisit', 'checked', 'f', 'n', '', array('style' => 'text-align:center;'));
        $cFields[] = array($labels->getString('MemberType', 'primaryGuest', 'Primary Guest'), 'idPrimaryGuest', 'checked', '', 's', '', array());
        $cFields[] = array($labels->getString('MemberType', 'patient', 'Patient'), 'idPatient', 'checked', '', 's', '', array());

        // Patient address.
        if ($uS->PatientAddr) {

            $pFields = array('pAddr', 'pCity');
            $pTitles = array($labels->getString('MemberType', 'patient', 'Patient') . ' Address', $labels->getString('MemberType', 'patient', 'Patient') . ' City');

            if ($uS->county) {
                $pFields[] = 'pCounty';
                $pTitles[] = $labels->getString('MemberType', 'patient', 'Patient') . ' County';
            }

            $pFields = array_merge($pFields, array('pState', 'pCountry', 'pZip'));
            $pTitles = array_merge($pTitles, array($labels->getString('MemberType', 'patient', 'Patient') . ' State', $labels->getString('MemberType', 'patient', 'Patient') . ' Country', $labels->getString('MemberType', 'patient', 'Patient') . ' Zip'));

            $cFields[] = array($pTitles, $pFields, '', '', 's', '', array());
        }

        if ($uS->ShowBirthDate) {
            $cFields[] = array($labels->getString('MemberType', 'patient', 'Patient') . ' DOB', 'pBirth', '', '', 'n', "", array(), 'date');
        }

        // Referral Agent
        if ($uS->ReferralAgent) {
            $cFields[] = array($labels->getString('hospital', 'referralAgent', 'Ref. Agent'), 'Referral_Agent', 'checked', '', 's', '', array());
        }

        // Hospital
        if ((count($this->filter->getAList()) + count($this->filter->getHList())) > 1) {

            $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'Hospital', 'checked', '', 'string', '20');

            if (count($this->filter->getAList()) > 0) {
                $cFields[] = array($labels->getString('hospital', 'association', 'Association'), 'Assoc', 'checked', '', 'string', '20');
            }
        }


        if ($uS->Doctor) {
            $cFields[] = array("Doctor", 'Doctor', '', '', 's', '', array());
        }

                if (count($this->locations) > 0) {
            $cFields[] = array($labels->getString('hospital', 'location', 'Location'), 'Location', 'checked', '', 's', '', array());
        }

        if (count($this->diags) > 0) {
            $cFields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', 'checked', '', 's', '', array());
        }

        if ($uS->ShowDiagTB) {
            $cFields[] = array($labels->getString('hospital', 'diagnosisDetail', 'Diagnosis Details'), 'Diagnosis2', 'checked', '', 's', '20', array());
        }

        if ($uS->InsuranceChooser) {
            $cFields[] = array($labels->getString('MemberType', 'patient', 'Patient') . " Insurance", 'Insurance', '', '', 's', '', array());
        }

        $cFields[] = array("Arrive", 'Arrival', 'checked', '', 'n', '', array(), 'date');
        $cFields[] = array("Depart", 'Departure', 'checked', '', 'n', '', array(), 'date');
        $cFields[] = array("Room", 'Title', 'checked', '', 's', '', array('style' => 'text-align:center;'));

        if ($uS->VisitFee) {
            $cFields[] = array($labels->getString('statement', 'cleaningFeeLabel', "Clean Fee"), 'visitFee', 'checked', '', 's', '', array('style' => 'text-align:right;'));
        }


        if (count($this->adjusts) > 0) {
            $cFields[] = array("Addnl Charge", 'adjch', 'checked', '', 's', '', array('style' => 'text-align:right;'));

            if ($this->useTaxes) {
                $cFields[] = array("Addnl Tax", 'adjchtx', 'checked', '', 's', '', array('style' => 'text-align:right;'));
            }
        }


        $cFields[] = array("Nights", 'nights', 'checked', '', 'n', '', array('style' => 'text-align:center;'));
        $cFields[] = array("Days", 'days', '', '', 'n', '', array('style' => 'text-align:center;'));

        $amtChecked = 'checked';

        if ($uS->RoomPriceModel !== ItemPriceCode::None) {

            if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {

                $cFields[] = array($labels->getString('MemberType', 'guest', 'Guest') . " Nights", 'gnights', 'checked', '', 'n', '', array('style' => 'text-align:center;'));
                $cFields[] = array("Rate Per " . $labels->getString('MemberType', 'guest', 'Guest'), 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());
                $cFields[] = array("Mean Rate Per " . $labels->getString('MemberType', 'guest', 'Guest'), 'meanGstRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

            } else {

                $cFields[] = array("Rate", 'rate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array());

                if ($uS->RoomPriceModel == ItemPriceCode::NdayBlock) {
                    $cFields[] = array("Adj. Rate", 'rateAdj', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
                }

                $cFields[] = array("Mean Rate", 'meanRate', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            }

            $cFields[] = array("Lodging Charge", 'lodg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

            if ($this->useTaxes) {
                $cFields[] = array('Tax Charged', 'taxcgd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            }

            $cFields[] = array($labels->getString('MemberType', 'visitor', 'Guest') . " Paid", 'gpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            $cFields[] = array("3rd Party Paid", 'thdpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            $cFields[] = array("House Paid", 'hpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            $cFields[] = array("Lodging Paid", 'totpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

            if ($this->useTaxes) {
                $cFields[] = array('Tax Paid', 'taxpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            }

            $cFields[] = array("Unpaid", 'unpaid', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            $cFields[] = array("Pending", 'pndg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));

            if ($this->useTaxes) {
                $cFields[] = array('Tax Pending', 'taxpndg', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            }

            if ($uS->RoomPriceModel != ItemPriceCode::NdayBlock) {
                $cFields[] = array("Rate Subsidy", 'sub', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
            }

            $cFields[] = array("Contribution", 'donpd', $amtChecked, '', 's', '_(* #,##0.00_);_(* \(#,##0.00\);_(* "-"??_);_(@_)', array('style' => 'text-align:right;'));
        }

        return $cFields;
    }

    /**
     */
    public function makeFilterMkup():void {

        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->hospitalMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->resourceGroupsMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();

    }

    /**
     */
    function makeQuery():void {

        $uS = Session::getInstance();

        // Hospitals
        $whHosp = implode(",", $this->filter->getSelectedHosptials());
        $whAssoc = implode(",", $this->filter->getSelectedAssocs());

        if ($whHosp != '') {
            $whHosp = " and hs.idHospital in (" . $whHosp . ") ";
        }

        if ($whAssoc != '') {
            $whAssoc = " and hs.idAssociation in (" . $whAssoc . ") ";
        }

        $query = "select
    v.idVisit,
    v.Span,
    v.idPrimaryGuest,
    ifnull(hs.idPatient, 0) as idPatient,
    v.idResource,
    v.Expected_Departure,
    ifnull(v.Actual_Departure, '') as Actual_Departure,
    ifnull(v.Actual_Departure, v.Expected_Departure) as Departure,
    v.Arrival_Date as Arrival,
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
            DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('" . $this->filter->getReportStart() . "')
        THEN 0
        WHEN
            DATE(v.Span_Start) >= DATE('" . $this->filter->getReportEnd() . "')
        THEN 0
        ELSE
            DATEDIFF(
                CASE
                    WHEN
                        DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) > DATE('" . $this->filter->getReportEnd() . "')
                    THEN
                        DATE('" . $this->filter->getReportEnd() . "')
                    ELSE DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure)))
                END,
                CASE
                    WHEN DATE(v.Span_Start) < DATE('" . $this->filter->getReportStart() . "') THEN DATE('" . $this->filter->getReportStart() . "')
                    ELSE DATE(v.Span_Start)
                END
            )
        END AS `Actual_Month_Nights`,
    CASE
        WHEN DATE(v.Span_Start) >= DATE('" . $this->filter->getReportStart() . "') THEN 0
        WHEN
            DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) <= DATE('" . $this->filter->getReportStart() . "')
        THEN
            DATEDIFF(DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))),
                    DATE(v.Span_Start))
        ELSE DATEDIFF(CASE
                    WHEN
                        DATE(IFNULL(v.Span_End, datedefaultnow(v.Expected_Departure))) > DATE('" . $this->filter->getReportStart() . "')
                    THEN
                        DATE('" . $this->filter->getReportStart() . "')
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
    concat_ws(', ', nd.Name_Last, nd.Name_First) as Doctor,
    ifnull(hs.idPsg, 0) as idPsg,
    ifnull(hs.idHospital, 0) as idHospital,
    ifnull(hs.idAssociation, 0) as idAssociation,
    ifnull(h.Title, '') as Hospital,
    ifnull(a.Title, '') as Assoc,
    ifnull(nra.Name_Full, '') as Referral_Agent,
    ifnull(g.Description, hs.Diagnosis) as Diagnosis,
    ifnull(hs.Diagnosis2, '') as Diagnosis2,
    ifnull(group_concat(i.Title order by it.List_Order separator ', '), '') as Insurance,
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
    hospital h ON hs.idHospital = h.idHospital
        left join
    hospital a on hs.idAssociation = a.idHospital
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
    DATE(v.Span_Start) < DATE('" . $this->filter->getReportEnd() . "')
    and v.idVisit in (select
        idVisit
        from
            visit
        where
            `Status` not in ('p', 'c')
                and DATE(Arrival_Date) < DATE('" . $this->filter->getReportEnd() . "')
                and DATE(ifnull(Span_End,
                    case
                        when now() > Expected_Departure then now()
                        else Expected_Departure
                end)) >= DATE('" . $this->filter->getReportStart() . "')) "
            . $whHosp . $whAssoc . " group by v.idVisit, v.Span order by v.idVisit, v.Span";

        $this->query = $query;
    }

    /**
     */
    function makeSummaryMkup():string {
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
            $mkup .= HTMLContainer::generateMarkup('p', Labels::getString('hospital', 'hospital', 'Hospital') . 's: ' . $hospitalTitles);
        } else {
            $mkup .= HTMLContainer::generateMarkup('p', 'All ' . Labels::getString('hospital', 'hospital', 'Hospital') . 's');
        }

        return $mkup;

    }

    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();

        foreach($this->resultSet as &$r) {
            $r['Insurance'] = ($r['Insurance'] != '' ? HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment insAction', 'style'=>'cursor:pointer;', 'data-idName'=>$r['idPatient'], 'id'=>'insAction' . $r['idPatient'], 'title'=>'View Insurance')) . $r["Insurance"] : $r["Insurance"]);
            $r['idVisit'] = HTMLContainer::generateMarkup('div', $r['idVisit'], array('class'=>'hhk-viewVisit', 'data-gid'=>$r['idPrimaryGuest'], 'data-vid'=>$r['idVisit'], 'data-span'=>$r['Span'], 'style'=>'display:inline-table;'));
            $r['idPrimaryGuest'] = HTMLContainer::generateMarkup('a', $r['Name_Last'] . ', ' . $r['Name_First'], array('href'=>'GuestEdit.php?id=' . $r['idPrimaryGuest'] . '&psg=' . $r['idPsg']));
            $r['idPatient'] = HTMLContainer::generateMarkup('a', $r['Patient_Last'] . ', ' . $r['Patient_First'], array('href'=>'GuestEdit.php?id=' . $r['idPatient'] . '&psg=' . $r['idPsg']));

        }

        //$this->generateNumbers();

        return parent::generateMarkup($outputType);
    }

    protected function generateNumbers(){
        $uS = Session::getInstance();

        $now = (new \DateTime())->setTime(0,0,0);
        $reportEndDT = new \DateTime($this->filter->getReportEnd() . ' 00:00:00');

        $priceModel = AbstractPriceModel::priceModelFactory($this->dbh, $uS->RoomPriceModel);

        // Make titles for all the rates
        $rateTitles = RoomRate::makeDescriptions($this->dbh);
        $vat = new ValueAddedTax($this->dbh);

        // Get Guest days
        $actualGuestNights = [];
        $piGuestNights = [];

        if($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily){
            // routine defines acutalGuestNights and piGuestNights.
            $this->getGuestNights($actualGuestNights, $piGuestNights);

        }

        $rescGroup = $this->filter->getResourceGroups()[$this->filter->getSelectedResourceGroups()];

        $curVisit = 0;
        $curRoom = 0;
        $curRate = '';
        $curAmt = 0;

        $visit = array();
        $savedr = array();
        $nites = array();
        $rates = [];
        $chargesAr = [];

        foreach($this->resultSet as &$r){

            // records ordered by idVisit.
            if ($curVisit != $r['idVisit']) {

                // If i did not just start
                if (count($visit) > 0 && $visit['nit'] > 0) {

                    $totalLodgingCharge += $visit['chg'];
                    $chargesAr[] = $visit['chg']/$visit['nit'];
                    $totalAddnlCharged += ($visit['addch']);

                    $totalTaxCharged += $visit['taxcgd'];
                    $totalAddnlTax += $visit['adjchtx'];
                    $totalTaxPaid += $visit['taxpd'];
                    $totalTaxPending += $visit['taxpndg'];

                    if ($visit['nit'] > $uS->VisitFeeDelayDays) {
                        $totalVisitFee += $visit['vfa'];
                    }

                    $totalCharged += $visit['chg'];
                    $totalAmtPending += $visit['pndg'];
                    $totalNights += $visit['nit'];
                    $totalGuestNights += max($visit['gnit'] - $visit['nit'], 0);

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
                    $totalDonationPaid += $visit['donpd'];
                    $totalUnpaid += $unpaid;
                    $totalSubsidy += ($visit['fcg'] - $visit['chg']);
                    $nites[] = $visit['nit'];

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

                    $r['meanGstRate'] = 0;
                    if ($visit['gnit'] > 0) {
                        $r['meanGstRate'] = number_format(($visit['chg'] / $visit['gnit']), 2);
                    }
    /*
                    if (!$statsOnly) {
                        try{
                            doMarkup($fltrdFields, $savedr, $visit, $dPaid, $unpaid, $departureDT, $tbl, $local, $writer, $header, $reportRows, $rateTitles, $uS, $visitFee);
                        }catch(\Exception $e){
                            if(isset($writer)){
                                die();
                            }
                        }
                    }*/
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

            $days = $r['Actual_Month_Nights'];
            $gdays = $actualGuestNights[$r['idVisit']][$r['Span']] ?? 0;

            $visit['gnit'] += $gdays;

            // $gdays contains all the guests.
            if ($gdays >= $days) {
                $gdays -= $days;
            }

            $visit['nit'] += $days;
            $totalCatNites[$r[$rescGroup[0]]] += $days;

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

                $priceModel->setCreditDays($r['Rate_Glide_Credit'] + $piDays);
                $fullCharge = ($priceModel->amountCalculator($days, 0, RoomRateCategories::FullRateCategory, $uS->guestLookups['Static_Room_Rate'][$r['Rate_Code']][2], $gdays));

                if ($adjRatio > 0) {
                    // Only adjust when the charge will be more.
                    $fullCharge = $fullCharge * $adjRatio;
                }

                // Only Positive values.
                $visit['fcg'] += ($fullCharge > 0 ? $fullCharge : 0);
            }

            $savedr = $r;



        }
    }

    /**
     * Summary of getGuestNights
     * @param mixed $actual
     * @param mixed $preInterval
     * @return void
     */
    protected function getGuestNights(&$actual, &$preInterval) {

        $start = $this->filter->getReportStart();
        $end = $this->filter->getReportEnd();
        $uS = Session::getInstance();
        $ageYears = $uS->StartGuestFeesYr;
        $parm = "NOW()";
        if ($end != '') {
            $parm = "'$end'";
        }

        $stmt = $this->dbh->query("SELECT
        s.idVisit,
        s.Visit_Span,
        CASE
            WHEN
                DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) < DATE('$start')
            THEN 0
            WHEN
                DATE(s.Span_Start_Date) >= DATE('$end')
            THEN 0
            ELSE
                SUM(DATEDIFF(
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
                ))
            END AS `Actual_Guest_Nights`,
        CASE
            WHEN DATE(s.Span_Start_Date) >= DATE('$start') THEN 0
            WHEN
                DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) <= DATE('$start')
            THEN
                SUM(DATEDIFF(DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))),
                        DATE(s.Span_Start_Date)))
            ELSE SUM(DATEDIFF(CASE
                        WHEN
                            DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) > DATE('$start')
                        THEN
                            DATE('$start')
                        ELSE DATE(IFNULL(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date)))
                    END,
                    DATE(s.Span_Start_Date)))
        END AS `PI_Guest_Nights`

    FROM stays s JOIN name n ON s.idName = n.idName

    WHERE  IFNULL(DATE(n.BirthDate), DATE('1901-01-01')) < DATE(DATE_SUB(DATE(s.Checkin_Date), INTERVAL $ageYears YEAR))
        AND DATE(s.Span_Start_Date) < DATE('$end')
        and DATE(ifnull(s.Span_End_Date,
        case
        when now() > s.Expected_Co_Date then now()
        else s.Expected_Co_Date
        end)) >= DATE('$start')
    GROUP BY s.idVisit, s.Visit_Span");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $actual[$r['idVisit']][$r['Visit_Span']] = $r['Actual_Guest_Nights'] < 0 ? 0 : $r['Actual_Guest_Nights'];
            $preInterval[$r['idVisit']][$r['Visit_Span']] = $r['PI_Guest_Nights'] < 0 ? 0 : $r['PI_Guest_Nights'];
        }

    }
}