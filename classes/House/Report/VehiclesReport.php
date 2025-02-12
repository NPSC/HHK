<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Session;
use HHK\sec\Labels;

/**
 * VehiclesReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of VehiclesReport
 *
 * @author Will
 */

class VehiclesReport extends AbstractReport implements ReportInterface {

    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();
        $this->reportTitle = $uS->siteName . " Vehicles for " . date('D M j, Y');
        $this->inputSetReportName = "vehicles";
        $this->excelFileName = "VehiclesReport";

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeFilterMkup(): void
    {
        //no filters
    }

    public function makeSummaryMkup(): string
    {

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', "Report Period: ".Labels::getString('MemberType', 'visitor', 'Guest'). "s staying " . date('M j, Y'));

        return $mkup;
    }

    public function makeCFields(): array
    {
        $cFields = array();

        // Report column selector
        // array: title, ColumnName, checked, fixed, Excel Type, Excel Style
        $cFields[] = array('Last Name', 'Last Name', 'checked', '', 'string', '20');
        $cFields[] = array("First Name", 'First Name', 'checked', '', 'string', '20');
        $cFields[] = array("Room", 'Room', 'checked', '', 'string', '15');
        $cFields[] = array("Phone", 'Phone', 'checked', '', 'string', '15');
        $cFields[] = array("Arrival", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Expected Departure", 'Expected Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Status", 'Status', 'checked', '', 'string', '15');
        $cFields[] = array('Make', 'Make', 'checked', '', 'string', '20');
        $cFields[] = array('Model', 'Model', 'checked', '', 'string', '20');
        $cFields[] = array('Color', 'Color', 'checked', '', 'string', '20');
        $cFields[] = array('State Reg.', 'State Reg.', 'checked', '', 'string', '20');
        $cFields[] = array(Labels::getString('referral', 'licensePlate', 'License Plate'), 'License Plate', 'checked', '', 'string', '20');
        $cFields[] = array('Notes', 'Note', 'checked', '', 'string', '20');

        return $cFields;
    }

    public function makeQuery(): void
    {
        $this->query = "SELECT
    ifnull((case when n.Name_Suffix = '' then n.Name_Last else concat(n.Name_Last, ' ', g.`Description`) end), '') as `Last Name`,
    ifnull(n.Name_First, '') as `First Name`,
    ifnull(rm.Title, '')as `Room`,
    CASE WHEN n.Preferred_Phone = 'no' THEN 'No Phone' ELSE ifnull(np.Phone_Num, '') END as `Phone`,
    ifnull(r.Actual_Arrival, r.Expected_Arrival) as `Arrival`,
    case when r.Expected_Departure < now() then now() else r.Expected_Departure end as `Expected Departure`,
	l.Title as `Status`,
    ifnull(v.Make, '') as `Make`,
    ifnull(v.Model, '') as `Model`,
    ifnull(v.Color, '') as `Color`,
    ifnull(v.State_Reg, '') as `State Reg.`,
    ifnull(v.License_Number, '') as `" . Labels::getString('referral', 'licensePlate', 'License Plate') . "`,
	ifnull(v.Note, '') as `Note`
from
	vehicle v join reservation r on v.idRegistration = r.idRegistration
        left join
    `name` n ON n.idName = r.idGuest
        left join
    name_phone np ON n.idName = np.idName
        and n.Preferred_Phone = np.Phone_Code
        left join
    resource rm ON r.idResource = rm.idResource
        left join
    gen_lookups g on g.`Table_Name` = 'Name_Suffix' and g.`Code` = n.Name_Suffix
		left join
	lookups l on l.Category = 'ReservStatus' and l.`Code` = r.`Status`
where r.`Status` in ('a', 's', 'uc')
order by l.Title, `Arrival`";
    }

}