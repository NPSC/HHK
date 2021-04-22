<?php

namespace HHK\House;

use HHK\SysConst\GLTableNames;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\PhonePurpose;
use HHK\SysConst\VisitStatus;
use HHK\Member\Role\AbstractRole;
use HHK\Tables\Registration\VehicleRS;
use HHK\Tables\EditRS;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Session;
use HHK\House\Visit\Visit;
use HHK\Member\Role\Guest;
use HHK\House\Constraint\ConstraintsVisit;
use HHK\Member\Role\Patient;
use HHK\Payment\CreditToken;
use HHK\HTMLControls\HTMLTable;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Tables\Name\NameRS;
use HHK\sec\Labels;

/**
 * RegisterForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RegisterForm
 * @package name
 * @author Eric
 *
 * This form is used by all Houses except 1
 */
class RegisterForm {

    public $labels;

    protected function titleBlock($roomTitle, $expectedDeparture, $expDepartPrompt, $rate, $title, $agent, $priceModelCode, $houseAddr = '', $roomFeeTitle = 'Pledged Fee') {

        $mkup = "<h2>" . $title . " </h2>";

        if ($houseAddr != '') {
            $mkup .= '<p class="label" style="text-align:left;">' . $houseAddr . '</p>';
        }

        $mkup .= "<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none'>
 <tr>
  <td width=77 style='width:46pt;border:solid windowtext 1pt;'>
  <p class='label'>Room</p>
  </td>
  <td width=383 colspan=2 style='width:229.5pt;border:solid windowtext 1pt; border-left:none;'>
  <p class='room'>" . $roomTitle . "</p>
  </td>
  <td width=180 style='width:1.5in;border:solid windowtext 1pt;border-left: none;'>
  <p class='label'>$expDepartPrompt</p>
  </td>
  <td width=278 style='width:166.5pt;border:solid windowtext 1pt;border-left: none;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . ($expectedDeparture == '' ? '' : date("M j, Y", strtotime($expectedDeparture))) . "</p>
  </td>
 </tr>
 <tr>
  <td width=306 colspan=2 style='width:2.55in;border:solid windowtext 1pt; border-top:none;'>
  " . ($priceModelCode == ItemPriceCode::None ? '' : "<p class='label'>" . $this->labels->getString('register', 'rateTitle','Pledged Fee') . "</p>") ."</td>
  <td width=153 style='width:91.8pt;border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  " . ($priceModelCode == ItemPriceCode::None ? '' : "<p class=MsoNormal style='margin-bottom:0;line-height: normal'>$"  . number_format($rate, 2) . "</p>") ."</td>
  <td width=180 style='width:1.5in;border-top:none;border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Staff</p>
  </td>
  <td width=278 style='width:166.5pt;border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>$agent</p>
  </td>
 </tr>
</table>";

        return $mkup;
    }

    protected function patientBlock(AbstractRole $patient, $hospital, $hospRoom) {

        $bd = '';
        if ($patient->getRoleMember()->get_birthDate() != '') {
            $bd = ' (' . date('M j, Y', strtotime($patient->getRoleMember()->get_birthDate())) . ')';
        }

        $mkup = "<h2>" .$this->labels->getString('MemberType', 'patient', 'Patient'). "</h2>
<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none'>
 <tr>
  <td style='width:.5in;border-top:1.5pt solid #98C723; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Name</p>
  </td>
  <td style='width:180pt;border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . $patient->getRoleMember()->get_fullName() . $bd . "</p>
  </td>
  <td style='border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>" . $this->labels->getString('hospital', 'hospital', 'Hospital') . "</p>
  </td>
  <td style='width:160pt;border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . $hospital . "</p>
  </td>
  <td style='border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>" . $this->labels->getString('hospital', 'hospital', 'Hospital') . " Room</p>
  </td>
  <td style='width:50pt;border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . $hospRoom . "</p>
  </td>
 </tr>
</table>";

        return $mkup;
    }

    protected function vehicleBlock(array $vehs) {
        $mkup = "";

        if (count($vehs) > 0) {
            $s = '';
            if (count($vehs) > 1) {
                $s = 's';
            }
            $mkup .= "<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none;'>
                <tr><td colspan='11' style='border:none;border-bottom:1.5pt solid #98C723;padding-left:0'><h2>Vehicle$s</h2></td></tr>";

            foreach ($vehs as $v) {
                $veh = new VehicleRS();
                EditRS::loadRow($v, $veh);

                $mkup .= "<tr>
  <td style='width:.5in;border-top:none; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label' >Make</p>
  </td>
  <td style='width:100pt;border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;margin-bottom:.0001pt;line-height: normal'>". $veh->Make->getStoredVal() . "</p>
  </td>
  <td style='border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Model</p>
  </td>
  <td style='width:100pt;border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;margin-bottom:.0001pt;line-height: normal'>" .$veh->Model->getStoredVal() . "</p>
  </td>
  <td style='border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Color</p>
  </td>
  <td style='width:70pt;border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;margin-bottom:.0001pt;line-height: normal'>" . $veh->Color->getStoredVal() . "</p>
  </td>
  <td style='border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>State</p>
  </td>
  <td style='width:20pt;border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" .$veh->State_Reg->getStoredVal() . "</p>
  </td>
  <td style='border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>License</p>
  </td>
  <td style='width:50pt;border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" .$veh->License_Number->getStoredVal() . "</p>
  </td>
 </tr>";
                
                if ($veh->Note->getStoredVal() != '') {
                	$mkup .= "<tr><td style='width:.5in;border-top:none; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td><td style='border-top:none; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;' colspan='10'>".$veh->Note->getStoredVal()."</td></tr>";
                }
            }

            $mkup .= "</table>";

        } else {
            $mkup = "<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none;width:100%;'>
                <tr><td colspan='9' style='border:none;border-bottom:1.5pt solid #98C723;padding-left:0'><h2>Vehicles Not Registered</h2></td></tr></table>";

        }

        return $mkup;

    }

    protected function AgreementBlock(array $guests, $agreementLabel, $agreement) {

        $mkup = HTMLContainer::generateMarkup('h2', $agreementLabel, array('style'=>'border:none;border-bottom:1.5pt solid #98C723'));

        if ($agreement != '') {
            $mkup .= $agreement;
        } else {
            $mkup .= HTMLContainer::generateMarkup('div', "Your Registration Agreement is missing.  ", array('class'=>'ui-state-error'));
        }

        $usedNames = array();

        foreach ($guests as $g) {

            if (!isset($usedNames[$g->getIdName()])) {

                $sigCapture = HTMLContainer::generateMarkup('span', '___________________________________', array('name'=>'divSigCap_' . $g->getIdName(), 'data-gid'=>$g->getIdName(), 'class'=>'hhk-sigCapure'));

                $mkup .= "<p class=MsoNormal style='margin-top:14pt;margin-right:0;margin-bottom:0;margin-left:.5in;line-height:normal'>"
                    . "<span>" . $g->getRoleMember()->get_fullName() . $sigCapture . "</span></p>";
                $usedNames[$g->getIdName()] = 'y';
            }
        }

        // one more blank line
        $mkup .= "<p class=MsoNormal style='margin-top:14pt;margin-right:0;margin-bottom:0;margin-left:.5in;line-height:normal'>
            <span style='font-size:10pt'>________________________________&emsp; ___________________________________</span></p>";


        return $mkup;
    }

    protected function paymentRecord($feesRecord) {

        $mkup = HTMLContainer::generateMarkup('div',  HTMLContainer::generateMarkup('h2', 'Payment Record'), array('style'=>'border:none;border-bottom:1.5pt solid #98C723;padding-left:0;')) . $feesRecord;
        return $mkup;
    }

    protected function creditBlock($creditRecord) {

        $mkup = HTMLContainer::generateMarkup('div',  HTMLContainer::generateMarkup('h2', 'Payment Information'), array('style'=>'border:none;border-bottom:1.5pt solid #98C723;padding-left:0;'));
        $mkup .= $creditRecord;

        return $mkup;
    }

    protected function notesBlock($notes, $title = 'Check-in Notes') {

        $mkup = '';

        if ($notes != '') {

            $mkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h2', $title), array('style'=>'border:none;border-bottom:1.5pt solid #98C723;padding-left:0;'));
            $mkup .= HTMLContainer::generateMarkup('p', $notes);
        }

        return $mkup;
    }

    protected function guestBlock(\PDO $dbh, array $guests, array $relationText, int $primaryGuestId = 0) {

        $mkup = "<table style='border-collapse:collapse;border:none'>
            <tr><td colspan='6' style='border:none;border-bottom:1.5pt solid #98C723;padding-left:0;'><h2>" . Labels::getString('memberType', 'visitor', 'Guest') . "s</h2></td></tr>";

        $uS = Session::getInstance();
        $ecRels = $uS->nameLookups[GLTableNames::RelTypes];


        // for each guest
        foreach ($guests as $guest) {

            $name = $guest->getRoleMember();

            $addr = $guest->getAddrObj()->get_data($guest->getAddrObj()->get_preferredCode());
            //$phoneHome = $guest->phones->get_data(Phone_Purpose::Home);
            $phoneCell = $guest->getPhonesObj()->get_data(PhonePurpose::Cell);
            if ($phoneCell["Phone_Num"] == '' || $guest->getHousePhone() == 1) {
                $phoneCell["Phone_Num"] = 'House Phone';
            }
            $email = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
            $emrg = $guest->getEmergContactObj($dbh);

            $mkup .= "
 <tr>
  <td style='width:5%;border-top:none; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='width:14%;border-top:solid windowtext 1.5pt; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class='label'>" . ($guest->getIdName() == $primaryGuestId ? "Primary Guest": "Name") . "</p>
  </td>
  <td colspan=2 style='width:30%;border-top:solid windowtext 1.5pt; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1.5pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>". $name->get_fullName() ."</p>
  </td>
  <td  style='width:22%;border-top:solid windowtext 1.5pt; border-left:none;border-bottom:none;border-right:none;'>
  <p class='label'>Emergency Contact</p>
  </td>
  <td style='width:29%;border-top:solid windowtext 1.5pt; border-left:none;border-bottom:none;border-right:solid windowtext 1.5pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . $emrg->getEcNameFirst() . ' ' . $emrg->getEcNameLast() . "</p>
  </td>
 </tr>
 <tr>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class='label'>Address</p>
  </td>
  <td colspan=2 style='border-top:none;border-left: none;border-bottom:none;border-right:solid windowtext 1.5pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>". $addr["Address_1"] . " " .  $addr["Address_2"] ."</p>
  </td>
  <td style='border:none;'>
  <p class='label'>Phone</p>
  </td>
  <td style='border-top:none;border-left: none;border-bottom:none;border-right:solid windowtext 1.5pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" .$emrg->getEcPhone(). "</p>
  </td>
 </tr>
 <tr>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class='label'></p>
  </td>
  <td colspan=2 style='border-top:none;border-left: none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1.5pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>". $addr["City"] . ($addr["City"] == "" ? "" : ", ") . $addr["State_Province"] . "  ". $addr["Postal_Code"]. "</p>
  </td>
  <td style='border-top:none;border-left:none; border-bottom:solid windowtext 1.5pt;border-right:none;'>
  <p class='label'>Relationship to " . Labels::getString('memberType', 'visitor', "Guest") . "</p>
  </td>
  <td style='border-top:none;border-left: none;border-bottom:solid windowtext 1.5pt;border-right:solid windowtext 1.5pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . (isset($ecRels[$emrg->getEcRelationship()]) ? $ecRels[$emrg->getEcRelationship()][1] : '') . "</p>
  </td>
 </tr>
 <tr>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class='label'>Cell Phone</p>
  </td>
  <td colspan=2 style='border-top:none;border-left: none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>". $phoneCell["Phone_Num"] ."</p>
  </td>
  <td colspan=2 style='border-top:none;border-left: none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>&nbsp;</p>
  </td>
 </tr>
 <tr>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>E-mail</p>
  </td>
  <td colspan=2 style='border-top:none;border-left: none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>". $email["Email"] ."</p>
  </td>
  <td style='border-top:none;border-left: none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Check In Date</p>
  </td>
  <td style='border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>". $guest->getCheckinDT()->format('M j, Y'). "</p>
  </td>
 </tr>
 <tr>
  <td style='border:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td colspan=2 style='width:24%;border-top:none;border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Relationship to " . $this->labels->getString('MemberType', 'patient', 'Patient') . "</p>
  </td>
  <td style='border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . (isset($relationText[$guest->getPatientRelationshipCode()]) ? $relationText[$guest->getPatientRelationshipCode()][1] : ''). "</p>
  </td>
  <td style='border-top:none;border-left: none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>" . ($guest->status == VisitStatus::CheckedIn ? 'Expected Check Out' : 'Checked Out') . "</p>
  </td>
  <td style='border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>". $guest->getExpectedCheckOutDT()->format('M j, Y'). "</p>
  </td></tr>";
        }

        return $mkup . "</table>";

    }

    protected function generateDocument(\PDO $dbh, $title, AbstractRole $patient, array $guests,  $houseAddr, $hospital, $hospRoom, $patientRelCodes,
            $vehicles, $agent, $rate, $roomTitle, $expectedDeparture, $expDepartPrompt, $agreement, $creditRecord, $notes, $primaryGuestId = 0) {

        $uS = Session::getInstance();

        $mkup = "<div style='width:700px;margin-bottom:30px; margin-left:5px; margin-right:5px'>";
        $mkup .= self::titleBlock($roomTitle, $expectedDeparture, $expDepartPrompt, $rate, $title, $agent, $uS->RoomPriceModel, $houseAddr);

        // don't use notes if they are for the waitlist.
        if (!$uS->UseWLnotes) {
            $mkup .= self::notesBlock($notes);
        }

        $mkup .= $this->guestBlock($dbh, $guests, $patientRelCodes, $primaryGuestId);

        // Patient
        $mkup .= $this->patientBlock($patient, $hospital, $hospRoom);

        // Vehicles
        if (is_null($vehicles) === FALSE) {
            $mkup .= $this->vehicleBlock($vehicles);
        }

        // Credit card
        if ($creditRecord != '') {
            $mkup .= $this->creditBlock($creditRecord);
        }

        // Agreement
        $mkup .= $this->AgreementBlock($guests, $this->labels->getString('referral', 'agreementTitle','Agreement'), $agreement);

        $mkup .= "</div>";

        return $mkup;
    }

    public static function getStyling() {
        return '<style>
 /* Style Definitions */
 p.MsoNormal, li.MsoNormal, div.MsoNormal
	{margin:0;
	font-size:11pt;
	font-family:"Calibri","sans-serif";}
h1	{
	margin-top:24pt;
	margin-right:0;
	margin-bottom:0;
	margin-left:0;
	line-height:115%;
	page-break-after:avoid;
	font-size:14pt;
	font-family:"Cambria","serif";
	color:#365F91;}
h2 {
	margin-top:10pt;
	margin-right:0;
	margin-bottom:0;
	margin-left:0;
	line-height:115%;
	page-break-after:avoid;
	font-size:13pt;
	font-family:"Cambria","serif";
	color:#4F81BD;}
div.nextPage { page-break-before: always; }
td {padding:0 5pt 0 5pt; }
p.room {
    font-size:14pt;
    font-weight:700;}
p.label {
    color:#5B6973;
    text-align:right;
    font-size:9pt;
    font-family:"Calibri","sans-serif";}
</style>';
    }

    public function prepareRegForm(\PDO $dbh, $idVisit, $span, $idReservation, $agreement = '') {

        $uS = Session::getInstance();
        $this->labels = Labels::getLabels();
        $guests = array();
        $depDate = '';
        $reg = NULL;
        $primaryGuestId = 0;
        $idResc = 0;
        $idRate = 0;
        $rateCat = '';
        $pledgedRate = 0.0;
        $rateAdj = 0.0;
        $notes = '';
        $expectedDeparturePrompt = 'Expected Departure';
        $hospital = '';

        if ($idVisit > 0) {

            $query = "select s.idName, s.Span_Start_Date, s.Expected_Co_Date, s.Span_End_Date, s.`Status`, if(s.idName = v.idPrimaryGuest, 1, 0) as `primaryGuest`
					from stays s "
                    . " join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span "
                    . " where s.idVisit = :reg and s.Visit_Span = :spn "
                    . " and DATEDIFF(ifnull(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date)), s.Span_Start_Date) > 0 "
                    . " order by `primaryGuest` desc, `Status` desc";
            $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            $stmt->bindValue(':reg', $idVisit, \PDO::PARAM_INT);
            $stmt->bindValue(':spn', $span, \PDO::PARAM_INT);
            $stmt->execute();
            $stays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // visit
            $visit = new Visit($dbh, 0, $idVisit, NULL, NULL, NULL, '', $span);
            $reg = new Registration($dbh, 0, $visit->getIdRegistration());
            $visit->getResource($dbh);

            $depDate = $visit->getSpanEnd();
            if ($depDate == '') {
                $depDate = $visit->getExpectedDeparture();
            } else {
                switch ($visit->getVisitStatus()) {
                    case VisitStatus::ChangeRate:
                        $expectedDeparturePrompt= 'Room Rate Changed';
                        break;
                    case VisitStatus::NewSpan:
                        $expectedDeparturePrompt= 'Room Assignment Changed';
                        break;
                    case VisitStatus::CheckedOut:
                        $expectedDeparturePrompt= 'Actual Departure';
                        break;
                }
            }

            $primaryGuestId = $visit->getPrimaryGuestId();
            $idResc = $visit->getidResource();
            $idRate = $visit->getIdRoomRate();
            $rateCat = $visit->getRateCategory();
            $pledgedRate = $visit->getPledgedRate();
            $rateAdj = $visit->getRateAdjust();

            // psg
            $psg = new PSG($dbh, $reg->getIdPsg());

            // Guests
            foreach ($stays as $s) {
                $gst = new Guest($dbh, '', $s['idName']);
                $gst->setCheckinDate($s['Span_Start_Date']);
                $gst->status = $s['Status'];
                if ($s['Status'] != VisitStatus::CheckedIn) {
                    $gst->setExpectedCheckOut($s['Span_End_Date']);
                } else {
                    $gst->setExpectedCheckOut($s['Expected_Co_Date']);
                }

                $gst->setPatientRelationshipCode($psg->psgMembers[$gst->getIdName()]->Relationship_Code->getStoredVal());
                $guests[] = $gst;
            }
          
            $query = "select hs.idPatient, hs.Room, IFNULL(h.Title, '') from hospital_stay hs join visit v on hs.idHospital_stay = v.idHospital_Stay
				left join hospital h on hs.idHospital = h.idHospital  where v.idVisit = " . intval($idVisit) . " group by v.idVisit limit 1";

            $stmt = $dbh->query($query);
            $hospitalStay = $stmt->fetchAll(\PDO::FETCH_NUM);
            
        } else if ($idReservation > 0) {

            $stmt = $dbh->query("Select rg.idGuest as GuestId, rg.Primary_Guest, r.* from reservation_guest rg left join reservation r on rg.idReservation = r.idReservation
				where rg.idReservation = $idReservation order by rg.Primary_Guest desc");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $arrival = $rows[0]['Actual_Arrival'];
            if ($arrival == '') {
                $arrival = $rows[0]['Expected_Arrival'];
            }

            $depDate = $rows[0]['Actual_Departure'];
            if ($depDate == '') {
                $depDate = $rows[0]['Expected_Departure'];
            }

            $primaryGuestId = $rows[0]['idGuest'];
            $idResc = $rows[0]['idResource'];
            $idRate = $rows[0]['idRoom_rate'];
            $rateCat = $rows[0]['Room_Rate_Category'];
            $pledgedRate = $rows[0]['Fixed_Room_Rate'];
            $rateAdj = $rows[0]['Rate_Adjust'];
            $notes = $rows[0]['Checkin_Notes'];

            $reg = new Registration($dbh, 0, $rows[0]['idRegistration']);
            $psg = new PSG($dbh, $reg->getIdPsg());

            foreach ($rows as $r) {

                if ($r['Primary_Guest'] == '1') {
                    $primaryGuestId = $r['idGuest'];
                }

                $gst = new Guest($dbh, '', $r['GuestId']);
                $gst->setCheckinDate($arrival);
                $gst->setExpectedCheckOut($depDate);
                $gst->status = VisitStatus::CheckedIn;
                $gst->setPatientRelationshipCode($psg->psgMembers[$gst->getIdName()]->Relationship_Code->getStoredVal());
                $guests[] = $gst;

            }
            
            $query = "select hs.idPatient, hs.Room, IFNULL(h.Title, '') from hospital_stay hs join reservation r on hs.idHospital_stay = r.idHospital_Stay
				left join hospital h on hs.idHospital = h.idHospital where r.idReservation = " . intval($idReservation) . " limit 1";

            $stmt = $dbh->query($query);
            $hospitalStay = $stmt->fetchAll(\PDO::FETCH_NUM);

        } else {
            return 'No Data';
        }

        // Get constraints
        $constraints = new ConstraintsVisit($dbh, $idReservation, 0);

        if(count($constraints->getConstraints()) > 0) {
            $constrs = array();

            foreach ($constraints->getConstraints() as $c) {

                if ($c['isActive'] == 1) {
                    $constrs[] = $c['Title'];
                }
            }

            if (count($constrs) > 0) {
                $notes .= '  Visit preperation: ' . implode(', ', $constrs) . '.  ';
            }
        }

        // Patient and Hosptial
        if (count($hospitalStay) == 1) {
            $patient = new Patient($dbh, '', $hospitalStay[0][0]);
            $hospRoom = $hospitalStay[0][1];
            $hospital = $hospitalStay[0][2];
        }
        
        // Title
        $title = $uS->siteName . " Registration Form for Overnight " . $this->labels->getString('MemberType', 'visitor', 'Guest') . "s";

        // Vehicles
        if ($uS->TrackAuto) {

            $vehs = array();
            if ($reg->getNoVehicle() == 0) {
                // Remove unused vehicles from the array, if thsy somehow get in.
                $cars = Vehicle::getRecords($dbh, $reg->getIdRegistration());

                foreach ($cars as $c) {
                    if ($c['No_Vehicle'] != 1) {
                        $vehs[] = $c;
                    }
                }
            }
        } else {
            $vehs = NULL;
        }

        // Credit
        $creditReport = '';
        if ($primaryGuestId > 0 || $reg->getIdRegistration() > 0) {

            $tkRArray = CreditToken::getRegTokenRSs($dbh, $reg->getIdRegistration(), $primaryGuestId);

            if (count($tkRArray) > 0) {

                $tblPayment = new HTMLTable();

                foreach ($tkRArray as $tkRs) {

                    if (CreditToken::hasToken($tkRs)) {

                        $tblPayment->addBodyTr(
                                HTMLTable::makeTd('&nbsp;', array('style'=>'width:.5in;border:none;'))
                                . HTMLTable::makeTd("<p class='label'>Card on File</p>", array('style'=>'border:solid black 1pt;border-top:none;padding:1.5pt 5pt 1.5 5pt;'))
                                . HTMLTable::makeTd(
                                        $tkRs->CardType->getStoredVal() . ':  ...' .
                                        $tkRs->MaskedAccount->getStoredVal(), array('style'=>'border:solid black 1pt;border-top:none;padding:0 15pt 0 15pt;'))
                                . HTMLTable::makeTd("<p class='label'>Cardholder Name</p>", array('style'=>'border:solid black 1pt;border-top:none;padding:0 5pt 0 5pt;'))
                                . HTMLTable::makeTd($tkRs->CardHolderName->getStoredVal(), array('style'=>'border:solid black 1pt;border-top:none;padding:0 15pt 0 15pt;')));

                    }
                }

                $creditReport = $tblPayment->generateMarkup();
            }
        }


        $roomTitle = '';

        if ($uS->RegFormNoRm === FALSE) {

            $stmt2 = $dbh->query("select Title from resource where idResource = " . $idResc . ";");
            $rows2 = $stmt2->fetchAll();

            foreach ($rows2 as $rw) {
                $roomTitle = $rw['Title'];
            }
        }


        // get user name phrase
        $agent = '';
        $userRS = new NameRS();
        $userRS->idName->setStoredVal($uS->uid);
        $users = EditRS::select($dbh, $userRS, array($userRS->idName));

        if (count($users) > 0) {
            EditRS::loadRow($users[0], $userRS);

            $agent = $userRS->Name_First->getStoredVal() . ' ' . substr($userRS->Name_Last->getStoredVal(), 0, 2);
        }

        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        $rate = (1 + $rateAdj/100) * $priceModel->amountCalculator(1, $idRate, $rateCat, $pledgedRate);

        $houseAddr = '';

        $stmth = $dbh->query("select a.Address_1, a.Address_2, a.City, a.State_Province, a.Postal_Code
    from name n left join name_address a on n.idName = a.idName and n.Preferred_Mail_Address = a.Purpose where n.idName = " . $uS->sId);

        $rows = $stmth->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) == 1) {

            $street = $rows[0]['Address_1'];

            if ($rows[0]['Address_2'] != '') {
                $street .= ', ' . $rows[0]['Address_2'];
            }

            $houseAddr = $street . ' ' . $rows[0]['City'] . ', ' . $rows[0]['State_Province'] . ' ' . $rows[0]['Postal_Code'];

        }



        return RegisterForm::generateDocument(
                $dbh,
                $title,
                $patient,
                $guests,
                $houseAddr,
                $hospital,
                $hospRoom,
                $uS->guestLookups[GLTableNames::PatientRel],
                $vehs,
                $agent,
                $rate,
                $roomTitle,
                $depDate,
                $expectedDeparturePrompt,
                $agreement,
                $creditReport,
                $notes,
                $primaryGuestId
            );

    }
}



