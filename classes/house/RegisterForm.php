<?php
/**
 * RegisterForm.php
 *
 * Generates a printable registration form
 *
 * @category  House
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of RegisterForm
 * @package name
 * @author Eric
 */
class RegisterForm {

    protected static function titleBlock($roomTitle, $expectedDeparture, $rate, $title, $agent, $priceModelCode) {

        $mkup = "<h2>" . $title . " </h2>";

        $mkup .= "<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none'>
 <tr>
  <td width=77 style='width:46pt;border:solid windowtext 1pt;'>
  <p class='label'>Room</p>
  </td>
  <td width=383 colspan=2 style='width:229.5pt;border:solid windowtext 1pt; border-left:none;'>
  <p class='room'>" . $roomTitle . "</p>
  </td>
  <td width=180 style='width:1.5in;border:solid windowtext 1pt;border-left: none;'>
  <p class='label'>Expected Departure</p>
  </td>
  <td width=278 style='width:166.5pt;border:solid windowtext 1pt;border-left: none;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . ($expectedDeparture == '' ? '' : date("M j, Y", strtotime($expectedDeparture))) . "</p>
  </td>
 </tr>
 <tr>
  <td width=306 colspan=2 style='width:2.55in;border:solid windowtext 1pt; border-top:none;'>
  " . ($priceModelCode == ItemPriceCode::None ? '' : "<p class='label'>Pledged Fee</p>") ."</td>
  <td width=153 style='width:91.8pt;border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  " . ($priceModelCode == ItemPriceCode::None ? '' : "<p class=MsoNormal style='margin-bottom:0;line-height: normal'>"  . number_format($rate, 2) . "</p>") ."</td>
  <td width=180 style='width:1.5in;border-top:none;border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Agent</p>
  </td>
  <td width=278 style='width:166.5pt;border-top:none;border-left:none; border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>$agent</p>
  </td>
 </tr>
</table>";

        return $mkup;
    }

    protected static function patientBlock(\Role $patient, $hospital, $hospRoom) {

        $bd = '';
        if ($patient->getNameObj()->get_birthDate() != '') {
            $bd = ' (' . date('M j, Y', strtotime($patient->getNameObj()->get_birthDate())) . ')';
        }

        $mkup = "<h2>Patient</h2>
<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none'>
 <tr>
  <td style='width:.5in;border-top:1.5pt solid #98C723; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Name</p>
  </td>
  <td style='width:180pt;border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . $patient->getNameObj()->get_fullName() . $bd . "</p>
  </td>
  <td style='border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Hospital</p>
  </td>
  <td style='width:160pt;border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . $hospital . "</p>
  </td>
  <td style='border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class='label'>Hospital Room</p>
  </td>
  <td style='width:50pt;border-top:1.5pt solid #98C723; border-left:none;border-bottom:solid windowtext 1pt;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . $hospRoom . "</p>
  </td>
 </tr>
</table>";

        return $mkup;
    }

    protected static function vehicleBlock(array $vehs) {
        $mkup = "";

        if (count($vehs) > 0) {
            $s = '';
            if (count($vehs) > 1) {
                $s = 's';
            }
            $mkup .= "<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none;'>
                <tr><td colspan='11' style='border:none;border-bottom:1.5pt solid #98C723;padding-left:0'><h2>Vehicle$s</h2></td></tr>";

            foreach ($vehs as $v) {
                $veh = new VehicleRs();
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
            }

            $mkup .= "</table>";

        } else {
            $mkup = "<table cellspacing=0 cellpadding=0 style='border-collapse:collapse;border:none;width:100%;'>
                <tr><td colspan='9' style='border:none;border-bottom:1.5pt solid #98C723;padding-left:0'><h2>Vehicles Not Registered</h2></td></tr></table>";

        }

        return $mkup;

    }

    protected static function AgreementBlock(array $guestNames) {

        $mkup = HTMLContainer::generateMarkup('h2', 'Agreement', array('style'=>'border:none;border-bottom:1.5pt solid #98C723'));

        require REL_BASE_DIR . 'conf' . DS . 'regSection.php';

        $mkup .= HTMLContainer::generateMarkup('div', $instructions, array('class'=>'MsoNormal', 'style'=>'width:900px;margin-left:auto;margin-right:auto;'));

        $usedNames = array();

        foreach ($guestNames as $gname) {

            if (!isset($usedNames[$gname])) {

                $mkup .= "<p class=MsoNormal style='margin-top:12pt;margin-right:0;margin-bottom:0;margin-left:.5in;line-height:normal'>
                    <span style='font-size:10pt'>" . $gname . "&nbsp;&nbsp; ___________________________________</span></p>";
                $usedNames[$gname] = 'y';
            }
        }

        // one more blank line
        $mkup .= "<p class=MsoNormal style='margin-top:12pt;margin-right:0;margin-bottom:0;margin-left:.5in;line-height:normal'>
            <span style='font-size:10pt'>________________________________&nbsp;&nbsp; ___________________________________</span></p>";


        return $mkup;
    }

    protected static function paymentRecord($feesRecord) {

        $mkup = HTMLContainer::generateMarkup('div',  HTMLContainer::generateMarkup('h2', 'Payment Record'), array('style'=>'border:none;border-bottom:1.5pt solid #98C723;padding-left:0;')) . $feesRecord;
        return $mkup;
    }

    protected static function creditBlock($creditRecord) {

        $mkup = HTMLContainer::generateMarkup('div',  HTMLContainer::generateMarkup('h2', 'Payment Information'), array('style'=>'border:none;border-bottom:1.5pt solid #98C723;padding-left:0;'));
        $mkup .= $creditRecord;

        return $mkup;
    }

    protected static function notesBlock($notes) {

        $mkup = '';

        if ($notes != '') {

            $mkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h2', 'Check-in Notes'), array('style'=>'border:none;border-bottom:1.5pt solid #98C723;padding-left:0;'));
            $mkup .= HTMLContainer::generateMarkup('p', $notes);
        }

        return $mkup;
    }

    protected static function guestBlock(array $guests, array $relationText) {
        $mkup = "<table style='border-collapse:collapse;border:none'>
            <tr><td colspan='6' style='border:none;border-bottom:1.5pt solid #98C723;padding-left:0;'><h2>Guests</h2></td></tr>";

        // for each guest
        foreach ($guests as $guest) {

            $name = $guest->getNameObj();

            $addr = $guest->getAddrObj()->get_data($guest->getAddrObj()->get_preferredCode());
            //$phoneHome = $guest->phones->get_data(Phone_Purpose::Home);
            $phoneCell = $guest->getPhonesObj()->get_data(Phone_Purpose::Cell);
            if ($phoneCell["Phone_Num"] == '' || $guest->getHousePhone() == 1) {
                $phoneCell["Phone_Num"] = 'House Phone';
            }
            $email = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
            $emrg = $guest->getEmergContactObj();

            $mkup .= "
 <tr>
  <td style='width:5%;border-top:none; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class=MsoNormal align=right style='margin-bottom:0; text-align:right;line-height:normal'>&nbsp;</p>
  </td>
  <td style='width:14%;border-top:solid windowtext 1.5pt; border-left:none;border-bottom:none;border-right:solid windowtext 1pt;'>
  <p class='label'>Name</p>
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
  <p class='label'>Relationship to Guest</p>
  </td>
  <td style='border-top:none;border-left: none;border-bottom:solid windowtext 1.5pt;border-right:solid windowtext 1.5pt;'>
  <p class=MsoNormal style='margin-bottom:0;line-height: normal'>" . (isset($relationText[$emrg->getEcRelationship()]) ? $relationText[$emrg->getEcRelationship()][1] : '') . "</p>
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
  <p class='label'>Relationship to Patient</p>
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

    public static function generateDocument($title, \Role $patient, array $guests,  $houseName, $hospital, $hospRoom, $patientRelCodes, $vehicles, $agent, $rate, $roomTitle, $expectedDeparture, $creditRecord = '', $notes = '') {

        $uS = Session::getInstance();

        $mkup = "<div style='width:900px;margin-left:auto;margin-right:auto;margin-bottom:30px;'>";
        $mkup .= self::titleBlock($roomTitle, $expectedDeparture, $rate, $title, $agent, $uS->RoomPriceModel);

        $mkup .= self::notesBlock($notes);

        $mkup .= self::guestBlock($guests, $patientRelCodes);

        $guestNames = array();
        // for each guest
        foreach ($guests as $guest) {
            $guestNames[] = $guest->getNameObj()->get_fullName();
        }

        // Patient
        $mkup .= self::patientBlock($patient, $hospital, $hospRoom);

        // Vehicles
        if (is_null($vehicles) === FALSE) {
            $mkup .= self::vehicleBlock($vehicles);
        }

        // Credit card
        if ($creditRecord != '') {
            $mkup .= self::creditBlock($creditRecord);
        }

        // Agreement
        $mkup .= self::AgreementBlock($guestNames);

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

    public static function prepareReceipt(PDO $dbh, $idVisit, $idReservation = 0) {

        $uS = Session::getInstance();
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

        if ($idVisit > 0) {

            $query = "select idName, Span_Start_Date, Expected_Co_Date, Span_End_Date, Status  from stays where idVisit = :reg and Status in ('" . VisitStatus::CheckedIn . "', '" . VisitStatus::CheckedOut . "')";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->bindValue(':reg', $idVisit, PDO::PARAM_INT);
            $stmt->execute();
            $stays = $stmt->fetchAll(PDO::FETCH_NAMED);

            // If still 0, then ?
            if (count($stays) == 0) {
                throw new Hk_Exception_Runtime("No guests were found for this visit.  ");
            }

            // visit
            $visit = new Visit($dbh, 0, $idVisit);
            $reg = new Registration($dbh, 0, $visit->getIdRegistration());
            $visit->getResource($dbh);

            $depDate = $visit->getActualDeparture();
            if ($depDate == '') {
                $depDate = $visit->getExpectedDeparture();
            }

            $primaryGuestId = $visit->getPrimaryGuestId();
            $idResc = $visit->getidResource();
            $idRate = $visit->getIdRoomRate();
            $rateCat = $visit->getRateCategory();
            $pledgedRate = $visit->getPledgedRate();
            $rateAdj = $visit->getRateAdjust();

            // psg
            $psg = new Psg($dbh, $reg->getIdPsg());

            // Guests
            foreach ($stays as $s) {
                $gst = new Guest($dbh, '', $s['idName']);
                $gst->setCheckinDate($s['Span_Start_Date']);
                $gst->status = $s['Status'];

                if ($s['Status'] == VisitStatus::CheckedOut) {
                    $gst->setExpectedCheckOut($s['Span_End_Date']);
                } else {
                    $gst->setExpectedCheckOut($s['Expected_Co_Date']);
                }

                $gst->setPatientRelationshipCode($psg->psgMembers[$gst->getIdName()]->Relationship_Code->getStoredVal());
                $guests[] = $gst;
            }

        } else if ($idReservation > 0) {

            $stmt = $dbh->query("Select rg.idGuest as GuestId, r.* from reservation_guest rg left join reservation r on rg.idReservation = r.idReservation where rg.idReservation = $idReservation");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            $psg = new Psg($dbh, $reg->getIdPsg());

            foreach ($rows as $r) {

                $gst = new Guest($dbh, '', $r['GuestId']);
                $gst->setCheckinDate($arrival);
                $gst->setExpectedCheckOut($depDate);
                $gst->status = VisitStatus::CheckedIn;
                $gst->setPatientRelationshipCode($psg->psgMembers[$gst->getIdName()]->Relationship_Code->getStoredVal());
                $guests[] = $gst;

            }

        } else {
            return 'No Data';
        }

        $hospRoom = '';
        $idHospital = '';
        $hospital = "";
        $patient = null;

        $query = "select h.idPatient, h.Room, h.idHospital from hospital_stay h where h.idPsg = " . intval($psg->getIdPsg());
        $stmt = $dbh->query($query);
        $psgs = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($psgs) == 1) {
            $patient = new Patient($dbh, '', $psgs[0][0]);
            $hospRoom = $psgs[0][1];
            $idHospital = $psgs[0][2];
        }


        // Title
        $title = $uS->siteName . " Registration Form for Overnight Guests";

        // Hospital Name
        $hospList = array();
        if (isset($uS->guestLookups[GL_TableNames::Hospital])) {
            $hospList = $uS->guestLookups[GL_TableNames::Hospital];
        }

        foreach ($hospList as $r) {
            if ($r[0] == $idHospital) {
                $hospital = $r[1];
            }
        }

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

        $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        $rate = (1 + $rateAdj) * $priceModel->amountCalculator(1, $idRate, $rateCat, $pledgedRate);

        return RegisterForm::generateDocument($title, $patient, $guests, $uS->siteName, $hospital, $hospRoom, $uS->guestLookups[GL_TableNames::PatientRel], $vehs, $agent, $rate, $roomTitle, $depDate, $creditReport, $notes);

    }
}



