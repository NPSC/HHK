<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\{ReservationStatus, ItemId};
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\ItemType;
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
        $this->rescGroups = readGenLookupsPDO($dbh, 'Room_Group');
        $this->useTaxes = FALSE;

        $tstmt = $dbh->query("Select count(idItem) from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = " . ItemType::Tax . " where i.Deleted = 0");
        $taxItems = $tstmt->fetchAll(\PDO::FETCH_NUM);
        if ($taxItems[0][0] > 0) {
            $this->useTaxes = TRUE;
        }

        if (filter_has_var(INPUT_POST, 'selRoomGroup')) {
            $this->selectedRescGroups = filter_input(INPUT_POST, 'selRoomGroup', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY);
        } else {
            $this->selectedRescGroups = [];
        }

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }


    /**
     */
    public function makeCFields() {
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
                $cFields[] = array("Association", 'Assoc', 'checked', '', 'string', '20');
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
        $uS = Session::getInstance();

        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->createResourceGroups($this->rescGroups, $uS->CalResourceGroupBy);
        $this->filterMkup .= $this->filter->hospitalMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();

    }

    /**
     */
    function makeQuery() {

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
    ifnull(hs.idPsg, 0) as idPsg,
    ifnull(hs.idHospital, 0) as idHospital,
    ifnull(hs.idAssociation, 0) as idAssociation,
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
    function makeSummaryMkup() {
        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        if (isset($this->request["cbShowAll"])) {
            $mkup .= HTMLContainer::generateMarkup("p", "Showing All Guests");
        } else {
            $mkup .= HTMLContainer::generateMarkup("p", "Showing Primary Guests Only");
        }

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

        // $resourceTitles = '';
        // foreach ($this->selectedRescGroups as $s) {
        //     if (isset($this->rescGroups[$s])) {
        //         $resourceTitles .= $this->rescGroups[$s][1] . ', ';
        //     }
        // }

        // if ($resourceTitles != '') {
        //     $s = trim($resourceTitles);
        //     $resourceTitles = substr($s, 0, strlen($s) - 1);
        //     $mkup .= HTMLContainer::generateMarkup('p', 'Room Groups: ' . $resourceTitles);
        // } else {
        //     $mkup .= HTMLContainer::generateMarkup('p', 'All Room Groups');
        // }

        return $mkup;

    }
}