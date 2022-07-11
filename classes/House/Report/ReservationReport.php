<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\HTMLControls\HTMLTable;

/**
 * ReservationReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ReservationReport
 *
 * @author Will
 */

class ReservationReport extends AbstractReport implements ReportInterface {

    public array $locations;
    public array $diags;
    public array $resvStatuses;
    public array $selectedResvStatuses;


    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' Reservation Report';
        $this->inputSetReportName = "reserv";
        $this->locations = readGenLookupsPDO($dbh, 'Location');
        $this->diags = readGenLookupsPDO($dbh, 'Diagnosis');
        $this->resvStatuses = removeOptionGroups(readLookups($dbh, "ReservStatus", "Code", FALSE));

        if (isset($_POST['selResvStatus'])) {
            $this->selectedResvStatuses = filter_var_array($_POST['selResvStatus'], FILTER_SANITIZE_STRING);
        }else{
            $this->selectedResvStatuses = [];
        }

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeQuery(): void{
        $whDates = " r.Expected_Arrival <= '" . $this->filter->getReportEnd() . "' and ifnull(r.Actual_Departure, r.Expected_Departure) >= '" . $this->filter->getReportStart() . "' ";

        // Hospitals
        $whHosp = implode(",", $this->filter->getSelectedHosptials());
        $whAssoc = implode(",", $this->filter->getSelectedAssocs());

        if ($whHosp != '') {
            $whHosp = " and hs.idHospital in (".$whHosp.") ";
        }

        if ($whAssoc != '') {
            $whAssoc = " and hs.idAssociation in (".$whAssoc.") ";
        }

        // Reservation status selections
        $whStatus = implode(",", preg_filter(["/^(?!($))/", "/(?!(^))$/"], ["'", "'"], $this->selectedResvStatuses)); //add quotes to selected non empty statuses and build comma delimited string

        if ($whStatus != '') {
            $whStatus = "and r.Status in (" . $whStatus . ") ";
        }

        $this->query = "select
    r.idReservation,
    r.idGuest,
    concat(ifnull(na.Address_1, ''), '', ifnull(na.Address_2, ''))  as gAddr,
    ifnull(na.City, '') as gCity,
    ifnull(na.County, '') as gCounty,
    ifnull(na.State_Province, '') as gState,
    ifnull(na.Country_Code, '') as gCountry,
    ifnull(na.Postal_Code, '') as gZip,
    CASE WHEN np.Phone_Code = 'no' THEN 'No Phone' ELSE np.Phone_Num END as Phone_Num,
    ne.Email,
    rm.Phone,
    ifnull(r.Actual_Arrival, r.Expected_Arrival) as `Arrival`,
    ifnull(r.Actual_Departure, r.Expected_Departure) as `Departure`,
    r.Fixed_Room_Rate,
    r.`Status` as `ResvStatus`,
    DATEDIFF(ifnull(r.Actual_Departure, r.Expected_Departure), ifnull(r.Actual_Arrival, r.Expected_Arrival)) as `Nights`,
    (DATEDIFF(ifnull(r.Actual_Departure, r.Expected_Departure), ifnull(r.Actual_Arrival, r.Expected_Arrival))+1) as `Days`,
    ifnull(n.Name_Last, '') as Name_Last,
    ifnull(n.Name_First, '') as Name_First,
    re.Title as `Room`,
    re.`Type`,
    re.`Status` as `RescStatus`,
    re.`Category`,
    IF(rr.FA_Category='f', r.Fixed_Room_Rate, rr.`Title`) as `FA_Category`,
    rr.`Title` as `Rate`,
    g.Title as 'Status_Title',
    hs.idPsg,
    hs.idHospital,
    ifnull(h.Title, '') as 'Hospital',
    hs.idAssociation,
    ifnull(a.Title, '') as 'Assoc',
    nd.Name_Full as `Name_Doctor`,
    nr.Name_Full as `Name_Agent`,
    ifnull(gl.`Description`, hs.Diagnosis) as `Diagnosis`,
    hs.Diagnosis2,
    ifnull(g2.`Description`, '') as `Location`,
    r.`Timestamp` as `Created_Date`,
    r.Last_Updated,
	CASE WHEN r.Status not in ('s','co','im') THEN count(rg.idReservation) ELSE '' END as `numGuests`

from
    reservation r
        left join
	reservation_guest rg on r.idReservation = rg.idReservation
		left join
    resource re ON re.idResource = r.idResource
        left join
    name n ON r.idGuest = n.idName
        left join
    name_address na ON n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
        left join
    name_phone np ON n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
        left join
    name_email ne ON n.idName = ne.idName and n.Preferred_Email = ne.Purpose
        left join
    hospital_stay hs ON r.idHospital_Stay = hs.idHospital_stay
        left join
    name nd ON hs.idDoctor = nd.idName
        left join
    name nr ON hs.idReferralAgent = nr.idName
        left join
    room_rate rr ON r.idRoom_rate = rr.idRoom_rate
        left join resource_room rer on r.idResource = rer.idResource
        left join room rm on rer.idRoom = rm.idRoom
        left join
    lookups g ON g.Category = 'ReservStatus'
        and g.`Code` = r.`Status`
        left join
    gen_lookups gl ON gl.Table_Name = 'Diagnosis'
        and gl.`Code` = hs.Diagnosis
        LEFT JOIN
    gen_lookups g2 ON g2.Table_Name = 'Location'
        and g2.`Code` = hs.`Location`
    LEFT JOIN hospital h on hs.idHospital = h.idHospital and h.Type = 'h'
    LEFT JOIN hospital a on hs.idAssociation = a.idHospital and a.Type = 'a'
where " . $whDates . $whHosp . $whAssoc . $whStatus . " Group By rg.idReservation order by r.idRegistration";
    }

    public function makeFilterMkup():void{
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->getResvStatusMkup()->generateMarkup();
        $this->filterMkup .= $this->filter->hospitalMarkup()->generateMarkup();
        $this->filterMkup .= $this->colSelector->makeSelectorTable(TRUE)->generateMarkup(array('id'=>'includeFields'));
    }

    protected function getResvStatusMkup(){

        $resvStatusSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->resvStatuses, $this->selectedResvStatuses), array('name' => 'selResvStatus[]', 'size'=>'6', 'multiple'=>'multiple'));

        $tbl = new HTMLTable();
        $tr = '';

        $tbl->addHeaderTr(HTMLTable::makeTh(Labels::getString('referral', 'statusLabel', 'Reservation Status')));

        $tbl->addBodyTr($tr . HTMLTable::makeTd($resvStatusSelector, array('style'=>'vertical-align: top;')));

        return $tbl;

    }

    public function makeCFields():array{
        $labels = Labels::getLabels();
        $uS = Session::getInstance();

        $cFields[] = array('Resv Id', 'idReservation', 'checked', 'f', 'string', '10');
        $cFields[] = array("Room", 'Room', 'checked', '', 'string', '15');

        if ((count($this->filter->getAList()) + count($this->filter->getHList())) > 1) {

            $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'Hospital', 'checked', '', 'string', '20');

            if (count($this->filter->getAList()) > 0) {
                $cFields[] = array("Association", 'Assoc', 'checked', '', 'string', '20');
            }
        }


        if (count($this->locations) > 0) {
            $cFields[] = array($labels->getString('hospital', 'location', 'Location'), 'Location', 'checked', '', 'string', '20', array());
        }


        if (count($this->diags) > 0) {
            $cFields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', 'checked', '', 'string', '20', array());
        }

        if($uS->ShowDiagTB){
            $cFields[] = array($labels->getString('hospital', 'diagnosisDetail', 'Diagnosis Details'), 'Diagnosis2', 'checked', '', 'string', '20', array());
        }

        if ($uS->Doctor) {
            $cFields[] = array("Doctor", 'Name_Doctor', '', '', 'string', '20');
        }

        if ($uS->ReferralAgent) {
            $cFields[] = array($labels->getString('hospital', 'referralAgent', 'Referral Agent'), 'Name_Agent', '', '', 'string', '20');
        }

        $cFields[] = array("First", 'Name_First', 'checked', '', 'string', '20');
        $cFields[] = array("Last", 'Name_Last', 'checked', '', 'string', '20');

        // Address.
        $pFields = array('gAddr', 'gCity');
        $pTitles = array('Address', 'City');

        if ($uS->county) {
            $pFields[] = 'gCounty';
            $pTitles[] = 'County';
        }

        $pFields = array_merge($pFields, array('gState', 'gCountry', 'gZip'));
        $pTitles = array_merge($pTitles, array('State', 'Country', 'Zip'));

        $cFields[] = array($pTitles, $pFields, '', '', 'string', '15', array());

        $cFields[] = array("Room Phone", 'Phone', '', '', 'string', '20');
        $cFields[] = array($labels->getString('MemberType', 'visitor', 'Guest')." Phone", 'Phone_Num', '', '', 'string', '20');
        $cFields[] = array($labels->getString('MemberType', 'visitor', 'Guest')." Email", 'Email', '', '', 'string', '20');
        $cFields[] = array("Arrive", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Depart", 'Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Nights", 'Nights', 'checked', '', 'integer', '10');
        $cFields[] = array("Days", 'Days', '', '', 'integer', '10');
        $cFields[] = array("Rate", 'FA_Category', 'checked', '', 'string', '20');
        $cFields[] = array($labels->getString('MemberType', 'visitor', 'Guest').'s', 'numGuests', 'checked', '', 'integer', '10');
        $cFields[] = array("Status", 'Status_Title', 'checked', '', 'string', '15');
        $cFields[] = array("Created Date", 'Created_Date', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Last Updated", 'Last_Updated', '', '', 'MM/DD/YYYY', '15', array(), 'date');

        return $cFields;
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
            $mkup .= HTMLContainer::generateMarkup('p', Labels::getString('hospital', 'hospital', 'Hospital').'s: ' . $hospitalTitles);
        } else {
            $mkup .= HTMLContainer::generateMarkup('p', 'All '. Labels::getString('hospital', 'hospital', 'Hospital').'s');
        }

        $statusTitles = '';
        foreach ($this->selectedResvStatuses as $s) {
            if (isset($this->resvStatuses[$s])) {
                $statusTitles .= $this->resvStatuses[$s][1] . ', ';
            }
        }

        if ($statusTitles != '') {
            $s = trim($statusTitles);
            $statusTitles = substr($s, 0, strlen($s) - 1);
            $mkup .= HTMLContainer::generateMarkup('p', 'Statuses: ' . $statusTitles);
        } else {
            $mkup .= HTMLContainer::generateMarkup('p', 'All Statuses');
        }

        return $mkup;

    }

    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();
        $uS = Session::getInstance();

        foreach($this->resultSet as $k=>$r) {
            $this->resultSet[$k]['Status_Title'] = HTMLContainer::generateMarkup('a', $r['Status_Title'], array('href'=>$uS->resourceURL . 'house/Reserve.php?rid=' . $r['idReservation']));
            $this->resultSet[$k]['Name_Last'] = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>$uS->resourceURL . 'house/GuestEdit.php?id=' . $r['idGuest'] . '&psg=' . $r['idPsg']));
        }

        return parent::generateMarkup($outputType);
    }
}
?>