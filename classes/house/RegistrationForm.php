<?php
class RegistrationForm {


    public function getDocument(\PDO $dbh, \Guest $priGuest, \Guest $billGuest, array $addtionalGuests, $patientName, $hospitalName, $roomTitle, $cardName, $cardType, $cardNumber, $logoUrl, $logoWidth, $expectedPayType = '', $note = '', $todaysDate = '') {

        // Adds several string variables to the function
        include REL_BASE_DIR . 'conf' . DS . 'regSections.php';

        $uS = Session::getInstance();

        $fullNames = array();

        $house = Member::GetDesignatedMember($dbh, $uS->sId, MemBasis::NonProfit);
        $address = new Address($dbh, $house, $uS->nameLookups[GL_TableNames::AddrPurpose]);
        $phones = new Phones($dbh, $house, $uS->nameLookups[GL_TableNames::PhonePurpose]);

        $doc = $this->makeHeader($logoUrl, $logoWidth, $uS->siteName, $house->get_webSite(), $address, $phones);

        $doc .= $this->makeCheckinDates(date('M j, Y', strtotime($priGuest->getCheckinDate())), $roomTitle, $todaysDate);

        $doc .= $this->makePatient($patientName, $hospitalName);

        $doc .= $this->makeNotes($note);

        $doc .= $this->makeGuestRegSection();

        $doc .= $this->makePrimaryGuest($priGuest);
        $fullNames[] = $priGuest->getNameObj()->get_fullName();

        // additional guests
        if (count($addtionalGuests) > 0) {

            // First additional guest
            $guest = array_shift($addtionalGuests);
            $doc .= $this->makeFirstAdditionalGuest($guest);

            $fullNames[] = $guest->getNameObj()->get_fullName();

            // set index to count the remainder
            $index = 3;

            // Make additional guests
            foreach ($addtionalGuests as $g) {

                $doc .= self::makeAdditionalGuest($g, $index++);
                $fullNames[] = $g->getNameObj()->get_fullName();
            }

            if ($index < 5) {
                $doc .= self::makeBlankGuest($index);
            }

        } else {

            // Make Emergency contact.
            $ec = $priGuest->getEmergContactObj();

            $doc .= $this->makeFirstAdditional($ec->getEcNameFirst(), $ec->getEcNameLast(), '', $ec->getEcRelationship(), $ec->getEcPhone(), '');

            // blank additional guest
            $doc .= $this->makeBlankGuest(3);
            $doc .= $this->makeBlankGuest(4);

        }

        if ($uS->RoomPriceModel != ItemPriceCode::None) {
            $doc .= $this->makePayor($billGuest, $cardName, $cardType, $cardNumber, ($cardNumber != '' ? 'xx/xx' : ''), $expectedPayType, $paymentInfoSection);
        }

        $doc .= HTMLContainer::generateMarkup('div', '', array('style'=>'page-break-before: always;'));

        $doc .= $this->makeHeader($logoUrl, $logoWidth, $uS->siteName, $house->get_webSite(), $address, $phones);

        $doc .= $this->makeInstructions($instructions);

        $doc .= $this->makeSigLine($fullNames);

        return HTMLContainer::generateMarkup('div', $doc, array('style'=>'max-width:860px;margin-left:5px;'));
    }

    public function getStyle() {

        return '<style>
table {border-collapse:collapse; border:none; padding: 0; margin: 0;}
td, span, p {font: 11pt "times new roman";}
ul li span {font: 11pt "times new roman";}
td.prompt {vertical-align: top; font: 9px/11px sans-serif; color:slategray; height:12px;}
.imd-prompt {font: 9px/11px sans-serif; color:slategray;}
.imd-subtle {font: 10p "times new Roman"; color: slategray;}
.imd-small {line-height: 1em; }
.imdsection {font: 12px sans-serif; text-decoration: underline; font-weight: bold;}
.imdlist {font: 12px/14px sans-serif; font-weight: bold;}
@media print {
    body {margin:0; padding:0; line-height: 1.2em; word-spacing:1px; letter-spacing:0.2px; font: 11px "times new Roman", serif; color: #000;}
}
@page { margin: 1cm; }
</style>';
    }

    public function makeHeader($imgSrc, $imgWidth, $houseName, $houseWebSite, $houseAddrObj, $housePhoneObj) {

        $adrData = $houseAddrObj->get_Data();
        $phoneData = $housePhoneObj->get_Data(Phone_Purpose::Office);
        $faxData = $housePhoneObj->get_Data(Phone_Purpose::Fax);


        $mkup =  "<div  style='float:left;'>
    <img src='$imgSrc' width='$imgWidth' alt='$houseName Logo'>
</div>
<div style='float:right;'>
    <table>
    <tr><td class='imd-subtle' style='font-size: 1.1em;font-weight: bold;'>$houseName</td></tr>
    <tr><td class='imd-subtle imd-small'>" . $adrData['Address_1'] . " " . $adrData['Address_2'] . "</td></tr>
    <tr><td class='imd-subtle imd-small'>" . $adrData['City'] . ", " . $adrData['State_Province'] . " " . $adrData['Postal_Code'] . "</td></tr>
    <tr><td class='imd-subtle imd-small'><span style='font-weight: bold;'>Phone </span>" . $phoneData['Phone_Num']  . "</td></tr>";

        if ($faxData['Phone_Num'] != '') {
            $mkup .= "<tr><td class='imd-subtle imd-small'><span style='font-weight: bold;'>Fax </span>" . $faxData['Phone_Num']  . "</td></tr>";
        }

        $mkup .= "<tr><td class='imd-subtle  imd-small'>$houseWebSite</td></tr>
    </table>
</div>
<div style='clear:both;'></div>
<div class='imd-subtle'>Registration Form - Required</div>";

        return $mkup;
    }

    public function makeCheckinDates($checkinDate, $roomTitle, $todaysDate = ' ') {

        return "<table style='margin-top:10px;'> <tr>
     <td>Today's Date:</td><td style='border-bottom: 1px solid black;width:15%;'>$todaysDate</td>
     <td style='padding-left:10px;'>Room:</td><td style='border-bottom: 1px solid black;width:15%;text-align:center;'>$roomTitle&nbsp;</td>
     <td style='padding-left:10px;'>Check-In:</td><td style='border-bottom: 1px solid black;width:15%;text-align:center;'>$checkinDate&nbsp;</td>
     <td style='padding-left:10px;'>Check-Out:</td><td style='border-bottom: 1px solid black;width:15%;text-align:center;'>&nbsp;</td>
 </tr></table>";

    }

    public function makePatient($patientName, $hospitalName) {

        return "<table style='margin-top:10px;'><tr>
     <td style=';width:12%;vertical-align: bottom;'>Patient:</td><td style='border-bottom: 1px solid black;width:35%;vertical-align: bottom;'>$patientName&nbsp;</td>
     <td style='padding-left:21px;width:19%;vertical-align: bottom;'>Hospital:</td><td style='border-bottom: 1px solid black;width:38%;vertical-align: bottom;'>$hospitalName&nbsp;</td>
 </tr></table>";

    }

    public function makeInstructions ($text) {

        return '<div style="margin-top:10px;">' . $text . '</div>';

    }

    public function makeNotes ($text) {

        $notes = '';
        if ($text != '') {
            $notes = '<div style="padding:5px;float:left;margin-top:10px;border:solid 2px black;"><h2>Check-in Notes</h2>' . $text . '</div><div style="clear:both;"></div>';
        }
        return $notes;
    }

    public function makeGuestRegSection($message = '') {

        return '<div class="imdsection" style="margin-top:14px;">Guest Registration</div>'. $message;

    }

    public function makeSigLine(array $fullNames) {

        $mkup = '<div id="divSigLine"><table style="width:100%">';

        foreach ($fullNames as $n) {
            $mkup .= '<tr><td style="padding-top: 2em;border-bottom: 1px solid black;width:30%">' . $n . '&nbsp;</td><td style="border-bottom: 1px solid black;">&nbsp;</td><td style="border-bottom: 1px solid black;">&nbsp;</td></tr>';
            $mkup .= '<tr><td class="prompt">&#9650;Printed Name</td><td class="prompt">&#9650;Signature</td><td class="prompt">&#9650;Date</td></tr>';
        }

        return $mkup . '</table></div>';
    }

    public function makePayor(\Guest $guest, $cardName, $cardType, $cardNumber, $expDate, $expectedPayType, $paymentMessage) {

        $addr = $guest->getAddrObj();
        $email = $guest->getEmailsObj();

        $adrData = $addr->get_Data();
        $emailData = $email->get_Data();

        $expPay = '';
        if ($expectedPayType != '') {
            $expPay = '<div><span>Expected Payment Type: ' . $expectedPayType . ' </span></div>';
        }

        return '<div class="imdsection" style="margin-top:10px;">Payment Information - <span class="imdsection" style="font-style: italic;">Please Complete Contact Info if Different than Primary Guest</span></div>' . $expPay . $paymentMessage . '<div id="divPayor">
       <table style="width:100%; margin-left:20px;">
           <tr><td style="border-bottom: 1px solid black;width:45%;">' . $cardName . '&nbsp;</td><td style="border-bottom: 1px solid black;">&nbsp;</td></tr>
            <tr><td class="prompt">&#9650;Payor First Name</td><td class="prompt">&#9650;Payor Last Name</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:45%;">' . $adrData['Address_1'] . '&nbsp;</td><td style="border-bottom: 1px solid black;">' . $adrData['Address_2'] . '&nbsp;</td></tr>
            <tr><td class="prompt">&#9650;Billing Address</td><td class="prompt">&#9650;Billing Address 2</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:35%;">' . $adrData['City'] . '&nbsp;</td><td style="border-bottom: 1px solid black;width:20%;">' . $adrData['State_Province'] . '&nbsp;</td><td style="border-bottom: 1px solid black;">' . $adrData['Postal_Code'] . '&nbsp;</td><td style="border-bottom: 1px solid black;width:30%;">' . $emailData['Email'] . '</td></tr>
            <tr><td class="prompt">&#9650;Billing City</td><td class="prompt">&#9650;Billing State</td><td class="prompt">&#9650;Billing Zip Code</td><td class="prompt">&#9650;Email for E-Receipt</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:25%;">' . $cardType . '&nbsp;</td><td style="border-bottom: 1px solid black;width:40%;">' . $cardNumber . '&nbsp;</td><td style="border-bottom: 1px solid black;">' .$expDate. '&nbsp;</td></tr>
            <tr><td class="prompt">&#9650;Card Type</td><td class="prompt">&#9650;Credit Card Number</td><td class="prompt">&#9650;Expiration Date</td></tr>
        </table>
    </div>
';
    }

    public function makeFirstAdditional($fName, $lName, $date, $relation, $phone, $email) {

        $uS = Session::getInstance();

        $relat = '';
        if (isset($uS->guestLookups[GL_TableNames::PatientRel][$relation])) {
            $relat = $uS->guestLookups[GL_TableNames::PatientRel][$relation][1];
        }

        return '<div class="imdlist">2. Additional Guest (optional) <span style="text-decoration:underline;">OR</span> Emergency Contact Information if only ONE guest checking in </div>
<div id="divEmergContact">
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:35%;">' . $fName . '&nbsp;</td><td style="border-bottom: 1px solid black;width:35%;">' . $lName .'</td><td style="border-bottom: 1px solid black;">' . ($date == '' ? '' : date('M j, Y', strtotime($date))) . '&nbsp;</td></tr>
            <tr><td class="prompt">&#9650;First Name</td><td class="prompt">&#9650;Last Name</td><td class="prompt">&#9650;Dates Staying (if applicable)</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
           <tr><td style="border-bottom: 1px solid black;width:40%;">' . $relat . '&nbsp;</td><td style="border-bottom: 1px solid black;width:20%;">&nbsp;</td><td style="border-bottom: 1px solid black;background-color: #EAEBFF; text-align:center;">&#9634; Copy of Picture ID Taken <span style="font-weight:bold;">(Guest Only)</span></td></tr>
            <tr><td class="prompt">&#9650;Relationship to Patient/Guest</td><td class="prompt">&#9650;Age</td><td class="prompt"></td></tr>
        </table>
   <table style="width:100%; margin-left:20px;">
       <tr><td style="border-bottom: 1px solid black;width:30%;">' . $phone . '&nbsp;</td><td style="border-bottom: 1px solid black;">' . $email . '&nbsp;</td></tr>
        <tr><td class="prompt">&#9650;Phone #</td><td class="prompt">&#9650;Email</td></tr>
    </table>
</div>';

    }

    public function makeFirstAdditionalGuest(\Guest $guest) {

        $name = $guest->getNameObj();

        return $this->makeFirstAdditional($name->get_firstName(), $name->get_lastName(), $guest->getCheckinDate(), $guest->getPatientRelationshipCode(), '', '');

    }

    public function makeAdditionalGuest(\Guest $guest, $index) {

        $name = $guest->getNameObj();
        $uS = Session::getInstance();

        $relat = '';
        if (isset($uS->guestLookups[GL_TableNames::PatientRel][$guest->getPatientRelationshipCode()])) {
            $relat = $uS->guestLookups[GL_TableNames::PatientRel][$guest->getPatientRelationshipCode()][1];
        }

        return '<div class="imdlist">' . $index . '. Additional Guest (optional)</div>
    <div id="divAdditionalGuest3">
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:35%;">' . $name->get_firstName() . '&nbsp;</td><td style="border-bottom: 1px solid black;width:35%;">' . $name->get_lastName() .'</td><td style="border-bottom: 1px solid black;">' . date('M j, Y', strtotime($guest->getCheckinDate())) . '&nbsp;</td></tr>
            <tr><td class="prompt">&#9650;Guest First Name</td><td class="prompt">&#9650;Guest Last Name</td><td class="prompt">&#9650;Dates Staying</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
           <tr><td style="border-bottom: 1px solid black;width:40%;">' . $relat . '&nbsp;</td><td style="border-bottom: 1px solid black;width:20%;">&nbsp;</td><td style="border-bottom: 1px solid black;background-color: #EAEBFF; text-align:center;">&#9634; Copy of Picture ID Taken (18 or older)</td></tr>
            <tr><td class="prompt">&#9650;Relationship to Patient/Guest</td><td class="prompt">&#9650;Age</td><td class="prompt"></td></tr>
        </table>
    </div>';
    }

    public function makeBlankGuest($index) {

        return '<div class="imdlist">' . $index . '. Additional Guest (optional)</div>
    <div id="divAdditionalGuest3">
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:35%;">&nbsp;</td><td style="border-bottom: 1px solid black;width:35%;">&nbsp;</td><td style="border-bottom: 1px solid black;"><addt>&nbsp;</td></tr>
            <tr><td class="prompt">&#9650;Guest First Name</td><td class="prompt">&#9650;Guest Last Name</td><td class="prompt">&#9650;Dates Staying</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
           <tr><td style="border-bottom: 1px solid black;width:40%;">&nbsp;</td><td style="border-bottom: 1px solid black;width:20%;">&nbsp;</td><td style="border-bottom: 1px solid black;background-color: #EAEBFF; text-align:center;">&#9634; Copy of Picture ID Taken (18 or older)</td></tr>
            <tr><td class="prompt">&#9650;Relationship to Patient/Guest</td><td class="prompt">&#9650;Age</td><td class="prompt"></td></tr>
        </table>
    </div>';
    }

    public function makePrimaryGuest(\Guest $guest) {

        $name = $guest->getNameObj();
        $addr = $guest->getAddrObj();
        $phone = $guest->getPhonesObj();
        $email = $guest->getEmailsObj();

        $adrData = $addr->get_Data();
        $phoneData = $phone->get_Data(Phone_Purpose::Cell);
        $emailData = $email->get_Data();

        return '<div class="imdlist">1. Primary Guest Information (Required)</div>
    <div id="divPrimaryGuest">
       <table style="width:100%; margin-left:20px;">
           <tr><td style="border-bottom: 1px solid black;width:45%;">' . $name->get_firstName() . '&nbsp;</td><td style="border-bottom: 1px solid black;">' . $name->get_lastName() . '</td></tr>
            <tr><td class="prompt">&#9650;Guest First Name</td><td class="prompt">&#9650;Guest Last Name</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:45%;">' . $adrData['Address_1'] . '&nbsp;</td><td style="border-bottom: 1px solid black;">' . $adrData['Address_2'] . '</td></tr>
            <tr><td class="prompt">&#9650;Address</td><td class="prompt">&#9650;Address 2</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
            <tr><td style="border-bottom: 1px solid black;width:40%;">' . $adrData['City'] . '&nbsp;</td><td style="border-bottom: 1px solid black;width:20%;">' . $adrData['State_Province'] . '</td><td style="border-bottom: 1px solid black;">' . $adrData['Postal_Code'] . '</td></tr>
            <tr><td class="prompt">&#9650;City</td><td class="prompt">&#9650;State</td><td class="prompt">&#9650;Zip Code</td></tr>
        </table>
       <table style="width:100%; margin-left:20px;">
           <tr><td style="border-bottom: 1px solid black;width:33%;">' . $phoneData['Phone_Num'] . '&nbsp;</td><td style="border-bottom: 1px solid black;width:33%;">' . $emailData['Email'] . '</td><td style="background-color: #EAEBFF; text-align:center;">&#9634; Copy of Picture ID Taken</td></tr>
            <tr><td class="prompt">&#9650;Mobile Phone #</td><td class="prompt">&#9650;Email</td><td></td></tr>
        </table>
    </div>';

    }
}