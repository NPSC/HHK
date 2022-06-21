<?php

namespace HHK\House\Report;

use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\ColumnSelectors;

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
 * @author Eric
 */
class GuestVehicleReport {

    protected $title;
    protected \PDO $dbh;
    public ReportFilter $filter;
    public ColumnSelectors $colSelector;
    public $defaultFields;

    public function __construct(\PDO $dbh){
        $uS = Session::getInstance();
        $this->dbh = $dbh;
        $this->title = HTMLContainer::generateMarkup('h3', $uS->siteName . " Resident ".Labels::getString('MemberType', 'visitor', 'Guest'). "s for " . date('D M j, Y'), array('style'=>'margin-top: .5em;'));
    }

    public function setupGuestFields(){

        $uS = Session::getInstance();
        $this->filter = new ReportFilter();

        // Guest listing

        // Report column selector
        // array: title, ColumnName, checked, fixed, Excel Type, Excel Style
        $cFields[] = array('Last Name', 'Last Name', 'checked', '', 'string', '20');
        $cFields[] = array("First Name", 'First Name', 'checked', '', 'string', '20');
        $cFields[] = array("Room", 'Room', 'checked', '', 'string', '15');
        $cFields[] = array("Phone", 'Phone', 'checked', '', 'string', '15');
        $cFields[] = array("Arrive", 'Arrival', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $cFields[] = array("Expected Departure", 'Expected Departure', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        if ($uS->EmptyExtendLimit > 0) {
            $cFields[] = array("On Leave", 'On_Leave', 'checked', '', 'string', '15');
        }
        $cFields[] = array("Nights", 'Nights', '', '', 'integer', '10');
        $cFields[] = array(Labels::getString('hospital', 'hospital', 'Hospital'), 'Hospital', '', '', 'string', '20');

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

        $fieldSets = ReportFieldSet::listFieldSets($this->dbh, 'GuestView', true);
        $fieldSetSelection = (isset($_REQUEST['fieldset']) ? $_REQUEST['fieldset']: '');
        $this->colSelector = new ColumnSelectors($cFields, 'selFld', true, $fieldSets, $fieldSetSelection);
        $this->defaultFields = array();
        foreach($cFields as $field){
            if($field[2] == 'checked'){
                $this->defaultFields[] = $field[1];
            }
        }
        return $this->colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'margin-bottom:0.5em', 'id'=>'includeFields'));
    }

    public function getGuestMkup(){
        $guests = array();
        $this->colSelector->setColumnSelectors($_POST);
        $fltrdTitles = $this->colSelector->getFilteredTitles();
        $fltrdFields = $this->colSelector->getFilteredFields();

        $stmt = $this->dbh->query("select * from vguest_view");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $g = array();
            foreach ($fltrdFields as $f) {
                if(isset($f[7]) && $f[7] == "date"){
                    $g[$f[0]] = date('c', strtotime($r[$f[1]]));
                }else{
                    $g[$f[0]] = $r[$f[1]];
                }
            }
            $guests[] = $g;
        }

        if (count($guests) > 0) {
            $guestTable = CreateMarkupFromDB::generateHTML_Table($guests, 'tblList');
        } else {
            $guestTable = HTMLContainer::generateMarkup('h2', 'House is Empty.');
        }

        return $this->title . $guestTable;
    }

    public function getVehicleMkup(){

        $vehicleTable = '';
        $uS = Session::getInstance();
        $labels = Labels::getLabels();

        if ($uS->TrackAuto) {
            // Vehicle listing
            $vstmt = $this->dbh->query("SELECT
    ifnull((case when n.Name_Suffix = '' then n.Name_Last else concat(n.Name_Last, ' ', g.`Description`) end), '') as `Last Name`,
    ifnull(n.Name_First, '') as `First Name`,
    ifnull(rm.Title, '')as `Room`,
    ifnull(np.Phone_Num, '') as `Phone`,
    ifnull(r.Actual_Arrival, r.Expected_Arrival) as `Arrival`,
    case when r.Expected_Departure < now() then now() else r.Expected_Departure end as `Expected Departure`,
	l.Title as `Status`,
    ifnull(v.Make, '') as `Make`,
    ifnull(v.Model, '') as `Model`,
    ifnull(v.Color, '') as `Color`,
    ifnull(v.State_Reg, '') as `State Reg.`,
    ifnull(v.License_Number, '') as `" . $labels->getString('referral', 'licensePlate', 'License Plate') . "`,
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
order by l.Title, `Arrival`");

            $vrows = $vstmt->fetchAll(\PDO::FETCH_ASSOC);

            for ($i = 0; $i < count($vrows); $i++) {

                $vrows[$i]['Arrival'] = date('c', strtotime($vrows[$i]['Arrival']));
                $vrows[$i]['Expected Departure'] =  date('c', strtotime($vrows[$i]['Expected Departure']));
            }

            if (count($vrows) > 0) {
                $vehicleTable = CreateMarkupFromDB::generateHTML_Table($vrows, 'tblListv');
            } else {
                $vehicleTable = HTMLContainer::generateMarkup('h2', 'No vehicles present.');
            }

        }

        return $this->title . $vehicleTable;
    }

    public function sendEmail(string $reportName, string $subject, string $emailAddress, bool $cronDryRun = false){
        $uS = Session::getInstance();
        switch ($reportName){
            case "vehicles":
                $body = $this->getVehicleMkup();
                break;
            case "guests":
                $body = $this->getGuestMkup();
                break;
            default:
                return false;
        }

        if ($emailAddress == ''){
            return array("error"=>"Email Address is required");
        }elseif($subject == ''){
            return array("error"=>"Subject is required");
        }elseif($emailAddress != '' && $subject != '' && $body !=''){

            try{
                $mail = prepareEmail();

                $mail->From = $uS->NoReplyAddr;
                $mail->FromName = $uS->siteName;

                $tos = explode(',', $emailAddress);
                foreach ($tos as $t) {
                    $bcc = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($bcc !== FALSE && $bcc != '') {
                        $mail->addAddress($bcc);
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = $subject;

                $mail->msgHTML($body);
                if($cronDryRun == false){
                    $mail->send();
                    return array("success"=>"Email sent to " . $emailAddress . " successfully");
                }else{
                    return array("success"=>"Email would be sent to " . $emailAddress);
                }

            }catch(\Exception $e){
                return array("error"=>"Email failed!  " . $mail->ErrorInfo);
            }

        }
    }

}
?>