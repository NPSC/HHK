<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Session;
use HHK\sec\Labels;

/**
 * GuestVehiclesReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of GuestVehiclesReport
 *
 * @author Will
 */

class CurrentGuestReport extends AbstractReport implements ReportInterface {

    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();
        $this->reportTitle = $uS->siteName . " Resident ".Labels::getString('MemberType', 'visitor', 'Guest'). "s for " . date('D M j, Y');
        $this->inputSetReportName = "GuestView";

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    public function makeFilterMkup(): void
    {
        $this->filterMkup .= $this->colSelector->makeSelectorTable(TRUE)->generateMarkup(array('id'=>'includeFields'));
    }

    public function makeSummaryMkup(): string
    {

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: Guets staying ' . date('M j, Y'));

        return $mkup;
    }

    public function makeCFields(): array
    {
        $uS = Session::getInstance();
        $cFields = array();

        // Report column selector
        // array: title, ColumnName, checked, fixed, Excel Type, Excel Style
        $cFields[] = array('Last Name', 'Last Name', 'checked', '', 'string', '20');
        $cFields[] = array("First Name", 'First Name', 'checked', '', 'string', '20');
        $cFields[] = array("Room", 'Room', 'checked', '', 'string', '15');
        $cFields[] = array("Phone", 'Phone', 'checked', '', 'string', '15');
        $cFields[] = array("Arrival", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Expected Departure", 'Expected Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        if ($uS->EmptyExtendLimit > 0) {
            $cFields[] = array("On Leave", 'On_Leave', 'checked', '', 'string', '15');
        }
        $cFields[] = array("Nights", 'Nights', '', '', 'integer', '10');
        $cFields[] = array(Labels::getString('hospital', 'hospital', 'Hospital'), 'Hospital', '', '', 'string', '20');
        $cFields[] = array(Labels::getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', '', '', 'string', '20');
        $cFields[] = array(Labels::getString('hospital', 'location', 'Location'), 'Location', '', '', 'string', '20');

        $eFields = array('EC Name', 'EC Phone Home', 'EC Phone Alternate');
        $eTitles = array('Emergency Contact', 'Emergency Contact Home Phone', 'Emergency Contact Alternate Phone');

        $cFields[] = array($eTitles, $eFields, '', '', 's', '', array());

        if ($uS->TrackAuto) {
            $cFields[] = array('Make', 'Make', 'checked', '', 'string', '20');
            $cFields[] = array('Model', 'Model', 'checked', '', 'string', '20');
            $cFields[] = array('Color', 'Color', 'checked', '', 'string', '20');
            $cFields[] = array('State Reg.', 'State Reg.', 'checked', '', 'string', '20');
            $cFields[] = array(Labels::getString('referral', 'licensePlate', 'License Plate'), 'License Plate', 'checked', '', 'string', '20');
            $cFields[] = array('Notes', 'Note', 'checked', '', 'string', '20');
        }

        return $cFields;
    }

    public function makeQuery(): void
    {
        $this->query = "select * from vguest_view";
    }

}

?>