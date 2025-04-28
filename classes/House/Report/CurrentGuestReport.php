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

    /**
     * Summary of uniqueGuests
     * @var array
     */
    protected $uniqueGuests = [];

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param mixed $request
     */
    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();
        $this->reportTitle = $uS->siteName . " Resident ".Labels::getString('MemberType', 'visitor', 'Guest'). "s for " . date('D M j, Y');
        $this->inputSetReportName = "GuestView";

        parent::__construct($dbh, $this->inputSetReportName, $request);
    }

    /**
     * Summary of makeFilterMkup
     * @return void
     */
    public function makeFilterMkup(): void
    {
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    /**
     * Summary of makeSummaryMkup
     * @return string
     */
    public function makeSummaryMkup(): string
    {

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', "Report Period: " . Labels::getString('MemberType', 'visitor', 'Guest'). "s staying " . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Number of unique guests: ' . count($this->uniqueGuests));

        return $mkup;
    }

    /**
     * Summary of makeCFields
     * @return array
     */
    public function makeCFields(): array
    {
        $uS = Session::getInstance();
        $cFields = array();

        // Report column selector
        // array: title, ColumnName, checked, fixed, Excel Type, Excel Style
        $cFields[] = array("Room", 'Room', 'checked', '', 'string', '15');
        $cFields[] = array('Guest Last Name', 'Guest Last Name', 'checked', '', 'string', '20');
        $cFields[] = array("Guest First Name", 'Guest First Name', 'checked', '', 'string', '20');
        $cFields[] = array("Phone", 'Phone', 'checked', '', 'string', '15');
        $cFields[] = array('Patient Last Name', 'Patient Last Name', '', '', 'string', '20');
        $cFields[] = array("Patient First Name", 'Patient First Name', '', '', 'string', '20');
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

        $cFields[] = array($eTitles, $eFields, '', '', 'string', '20', array());

        if ($uS->TrackAuto) {
            $cFields[] = array('Vehicle', 'Vehicle', 'checked', '', 'string', '20');
            //$cFields[] = array('Make', 'Make', 'checked', '', 'string', '20');
            //$cFields[] = array('Model', 'Model', 'checked', '', 'string', '20');
            //$cFields[] = array('Color', 'Color', 'checked', '', 'string', '20');
            $cFields[] = array('State Reg.', 'State Reg.', 'checked', '', 'string', '20');
            $cFields[] = array(Labels::getString('referral', 'licensePlate', 'License Plate'), 'License Plate', 'checked', '', 'string', '20');
            $cFields[] = array(Labels::getString('referral', 'vehicleNotes', 'Notes'), 'Note', 'checked', '', 'string', '20');
        }

        return $cFields;
    }

    /**
     * Summary of makeQuery
     * @return void
     */
    public function makeQuery(): void
    {
        $this->query = "select * from vguest_view";
    }

    /**
     * Summary of generateMarkup
     * @param mixed $outputType
     * @return string
     */
    public function generateMarkup(string $outputType = ""){
        $this->getResultSet();

        $uniqueGuests = [];

        foreach($this->resultSet as $k=>$r) {
            $this->resultSet[$k]['On_Leave'] = ($this->resultSet[$k]['On_Leave'] > 0 ? "Yes":"");
            $this->uniqueGuests[$this->resultSet[$k]['idName']] = 'y';
            unset($this->resultSet[$k]['idName']);
        }

        return parent::generateMarkup($outputType);
    }


	/**
	 * @return mixed
	 */
	public function getUniqueGuests() {
		return $this->uniqueGuests;
	}
}

?>