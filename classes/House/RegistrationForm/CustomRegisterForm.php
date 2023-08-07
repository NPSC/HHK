<?php

namespace HHK\House\RegistrationForm;

use HHK\House\Constraint\ConstraintsVisit;
use HHK\House\PSG;
use HHK\House\Registration;
use HHK\House\Vehicle;
use HHK\House\Visit\Visit;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLSelector;
use HHK\Member\Role\AbstractRole;
use HHK\Member\Role\Agent;
use HHK\Member\Role\Doctor;
use HHK\Member\Role\Guest;
use HHK\Member\Role\Patient;
use HHK\Payment\CreditToken;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\PhonePurpose;
use HHK\SysConst\VisitStatus;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameRS;
use HHK\Tables\Registration\VehicleRS;

/**
 * CustomRegisterForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of CustomRegisterForm
 * @author Will
 *
 * New Registration form with customizations
 *
 * Reg Form Type 3
 */
class CustomRegisterForm {

    public $settingTemplate = [
        "Header"=>[
            "title"=>[
                "label"=>"Title",
                "type"=>"string"
            ],
            "logo"=>[
                "label"=>"Logo",
                "type"=>"bool",
                "default"=>false,
            ],
            "houseAddr"=>[
                "label"=>"House Address",
                "type"=>"bool",
                "default"=>true
            ],
            "today"=>[
                "label"=>"Today's Date",
                "type"=>"bool",
                "default"=>false
            ],
            /* "layout"=>[
                "label"=>"Header Layout",
                "type"=>"select",
                "values"=>[
                    ["logoRight","Logo Right"],
                    ["logoLeft","Logo Left"],
                    ["center","Center Stacked"]
                ]
            ] */
        ],
        "Top"=>[
            "room"=>[
                "label"=>"Room",
                "type"=>"bool",
                "default"=>true
            ],
            "rate"=>[
                "label"=>"Rate",
                "type"=>"bool",
                "default"=>true
            ],
            "depart"=>[
                "label"=>"Expected Departure",
                "type"=>"bool",
                "default"=>true
            ],
            "staff"=>[
                "label"=>"Staff",
                "type"=>"select",
                "values"=>[
                    ["","Hide"],
                    ["shortname","First & 2 character last"],
                    ["username","Full username"],
                    ["nickname","Nickname field"]
                ],
                "default"=>"shortname"
            ],
            "checkinNotes"=>[
                "label"=>"Check in Notes",
                "type"=>"bool",
                "default"=>true
            ]
        ],
        "Guests"=>[
            "show"=>[
                "label"=>"Show Section",
                "type"=>"bool",
                "default"=>true
            ],
/*             "type"=>[
                "label"=>"Type",
                "type"=>"select",
                "values"=>[
                    ["checkedin","Only Checked in guests"],
                    ["allstays","All Guests staying"],
                ],
                "default"=>"checkedin"
            ], */
            "birthdate"=>[
                "label"=>"Date of Birth",
                "type"=>"bool",
                "default"=>false
            ],
            "emerg"=>[
                "label"=>"Emergency Contact",
                "type"=>"bool",
                "default"=>true
            ]
        ],
        "Patient"=>[
            "show"=>[
                "label"=>"Show Section",
                "type"=>"bool",
                "default"=>true
            ],
            "birthdate"=>[
                "label"=>"Date of Birth",
                "type"=>"bool",
                "default"=>true
            ],
            "mrn"=>[
                "label"=>"MRN",
                "type"=>"bool",
                "default"=>false
            ],
            "hospital"=>[
                "label"=>"Hospital",
                "type"=>"bool",
                "default"=>true
            ],
            "hospRoom"=>[
                "label"=>"Hospital Room",
                "type"=>"bool",
                "default"=>true
            ],
            "diagnosis"=>[
                "label"=>"Diagnosis",
                "type"=>"bool",
                "default"=>false
            ],
            "location"=>[
                "label"=>"Location",
                "type"=>"bool",
                "default"=>false
            ],
            "doctor"=>[
                "label"=>"Doctor",
                "type"=>"bool",
                "default"=>false
            ],
        ],
        "Referral Agent"=>[
            "show"=>[
                "label"=>"Show Section",
                "type"=>"bool",
                "default"=>false
            ],
            "workPhone"=>[
                "label"=>"Work Phone",
                "type"=>"bool",
                "default"=>false
            ],
            "cellPhone"=>[
                "label"=>"Cell Phone",
                "type"=>"bool",
                "default"=>false
            ],
            "Email"=>[
                "label"=>"Email",
                "type"=>"bool",
                "default"=>false
            ],
        ],
        "Vehicle"=>[
            "show"=>[
                "label"=>"Show Section",
                "type"=>"bool",
                "default"=>true
            ],
        ],
        "Credit Cards"=>[
            "show"=>[
                "label"=>"Show Section",
                "type"=>"bool",
                "default"=>false
            ]
        ],
        "Agreement"=>[
            "show"=>[
                "label"=>"Show Section",
                "type"=>"bool",
                "default"=>true
            ],
            "title"=>[
                "label"=>"Title",
                "type"=>"string"
            ],
            "pagebreak"=>[
                "label"=>"Page Break",
                "type"=>"select",
                "values"=>[
                    ["", "Never"],
                    ["page-break-inside:avoid", "Avoid"],
                    ["page-break-before:always", "Always"]
                ]
            ]
        ],
        "Signatures"=>[
            "show"=>[
                "label"=>"Show Section",
                "type"=>"bool",
                "default"=>true
            ],
            "type"=>[
                "label"=>"Guest Type",
                "type"=>"select",
                "values"=>[
                    ["all","All Guests"],
                    ["primary","Primary Guest Only"],
                    ["adults","Exclude minors"]
                ],
                "default"=>"all"
            ],
            "eSign"=>[
                "label"=>"Signature Type",
                "type"=>"select",
                "values"=>[
                    ["","Paper"],
                    ["jSign","Touch"],
                    ["topaz","Topaz Signature Pad"]
                ],
                "default"=>""
            ],
        ]
    ];

    public $settings;

    public $labels;

    public $pageTitle = '';

    public $docCode = "";

    public function __construct($docCode = "", array $settings = []){
        $this->labels = Labels::getLabels();
        $this->docCode = $docCode;

        if(count($settings) == 0){
            $this->settings = $this->getDefaultSettings();
        }else{
            $this->settings = $settings;
        }

        $this->settingTemplate["Patient"]["mrn"]["label"] = $this->labels->getString("hospital", "MRN", "MRN");
        $this->settingTemplate["Patient"]["hospital"]["label"] = $this->labels->getString("hospital", "hospital", "Hospital");
        $this->settingTemplate["Patient"]["hospRoom"]["label"] = $this->labels->getString("hospital", "roomNumber", "Hospital Room");
        $this->settingTemplate["Patient"]["diagnosis"]["label"] = $this->labels->getString("hospital", "diagnosis", "Diagnosis");
        $this->settingTemplate["Patient"]["location"]["label"] = $this->labels->getString("hospital", "location", "Location");

        //hide disabled features
        $uS = Session::getInstance();
        if($this->labels->getString("hospital", "MRN", "") == ''){
            unset($this->settingTemplate["Patient"]["mrn"]);
        }
        if($uS->TrackAuto === false){
            unset($this->settingTemplate["Vehicle"]);
        }
        if($uS->ReferralAgent === false){
            unset($this->settingTemplate["Referral Agent"]);
        }
        if($uS->Doctor === false){
            unset($this->settingTemplate["Patient"]['doctor']);
        }

    }

    protected function titleBlock($roomTitle, $expectedDeparture, $expDepartPrompt, $rate, $title, $agent, $priceModelCode, $notes = '', $houseAddr = '', $roomFeeTitle = 'Pledged Fee') {

        $uS = Session::getInstance();

        $staff = Labels::getString("memberType", "staff", "Staff");
        $mkup = '<div class="header row mb-3"><div class="col"><h2 class="title">' . $title . '</h2>';

        if ($houseAddr != '' && !empty($this->settings["Header"]["houseAddr"])) {
            $mkup .= '<p>' . $houseAddr . '</p>';
        }

        if (!empty($this->settings["Header"]["today"])){
            $today = new \DateTime();
            $mkup .= "<strong>Date: </strong>" . $today->format("M j, Y");
        }

        $mkup .= '</div>';

        if(!empty($this->settings["Header"]["logo"])){
            $mkup .= '<div class="col-4"><img src="../conf/' . $uS->statementLogoFile . '" style="max-width:100%"></div>';
        }
        $mkup .= '</div>';

        $mkup .= '<div class="row mb-3 ui-widget-content ui-corner-all py-2">';

        $mkup .= (!empty($this->settings["Top"]["room"]) ? '<div class="col" style="min-width: fit-content"><strong>Room:</strong> <span class="room">' . $roomTitle . '</span></div>': '');
        $mkup .= ($priceModelCode != ItemPriceCode::None && !empty($this->settings["Top"]["rate"]) ? "<div class='col' style='min-width: fit-content'><strong>" . $this->labels->getString('register', 'rateTitle','Pledged Fee') . ":</strong> $"  . number_format($rate, 2) . "</div>": '');
        $mkup .= (!empty($this->settings["Top"]["depart"]) ? '<div class="col" style="min-width: fit-content"><strong>' . $expDepartPrompt . ': </strong><span>' . ($expectedDeparture == '' ? '' : date("M j, Y", strtotime($expectedDeparture))) . '</span></div>': '');
        $mkup .= (!empty($this->settings["Top"]["staff"]) ? '<div class="col" style="min-width: fit-content"><strong>' . $staff . ": </strong>" . $agent . '</div>': '');

        // don't use notes if they are for the waitlist.
        if (!$uS->UseWLnotes && $notes != '' && !empty($this->settings["Top"]["checkinNotes"])) {
            $mkup .='<div class="col-12 mt-2"><strong>Check-in Notes: </strong>' . $notes . '</div>';
        }

        $mkup .= '</div>';

        return $mkup;
    }

    protected function patientBlock(AbstractRole $patient, $hospital, $mrn, $hospRoom, $diagnosis, $location = '', $doctor = '') {

        $bd = '';
        if ($patient->getRoleMember()->get_birthDate() != '' && !empty($this->settings["Patient"]["birthdate"])) {
            $bd = ' (' . date('M j, Y', strtotime($patient->getRoleMember()->get_birthDate())) . ')';
        }

        if ($mrn != '' && !empty($this->settings["Patient"]["mrn"])) {
            $mrn = ' (' . $mrn . ')';
        }else{
            $mrn = "";
        }

        if($doctor instanceof AbstractRole){
            $doctorName = $doctor->getRoleMember()->get_fullName();
        }else{
            $doctorName = '';
        }

        $mkup = "<h2 class='mb-2'>" .$this->labels->getString('MemberType', 'patient', 'Patient'). "</h2>";

        $mkup .= '<div class="row mb-3 ui-widget-content ui-corner-all py-2">';

        $mkup .= '<div class="col" style="min-width:fit-content"><strong>Name: </strong>' . $patient->getRoleMember()->get_fullName() . $bd . $mrn . '</div>';
        $mkup .= (!empty($this->settings["Patient"]["hospital"]) && !empty($hospital) ? '<div class="col" style="min-width:fit-content"><strong>' . $this->labels->getString('hospital', 'hospital', 'Hospital') . ': </strong>' . $hospital . '</div>': '');
        $mkup .= (!empty($this->settings["Patient"]["hospRoom"]) && !empty($hospRoom) ? '<div class="col" style="min-width:fit-content"><strong>' . $this->labels->getString('hospital', 'roomNumber', 'Room') . ": </strong>" . $hospRoom . '</div>': '');
        $mkup .= (!empty($this->settings["Patient"]["diagnosis"]) && !empty($diagnosis) ? '<div class="col" style="min-width:fit-content"><strong>' . $this->labels->getString('hospital', 'diagnosis', 'Diagnosis') . ": </strong>" . $diagnosis . '</div>': '');
        $mkup .= (!empty($this->settings["Patient"]["location"]) && !empty($location) ? '<div class="col" style="min-width:fit-content"><strong>' . $this->labels->getString('hospital', 'location', 'Hospital Location') . ": </strong>" . $location . '</div>': '');
        $mkup .= (!empty($this->settings["Patient"]["doctor"]) && !empty($doctorName) ? '<div class="col" style="min-width:fit-content"><strong>Doctor: </strong>' . $doctorName . '</div>': '');

        $mkup .= '</div>';

        return $mkup;
    }

    protected function referralAgentBlock(AbstractRole $referralAgent = null) {

        $mkup = "<h2 class='mb-2'>" .$this->labels->getString('Hospital', 'referralAgent', 'Referral Agent'). "</h2>";

        $mkup .= '<div class="row mb-3 ui-widget-content ui-corner-all py-2">';

        if($referralAgent instanceof AbstractRole){

            $workphone = $referralAgent->getPhonesObj()->get_Data(PhonePurpose::Work)["Phone_Num"];
            $cellphone = $referralAgent->getPhonesObj()->get_Data(PhonePurpose::Cell)["Phone_Num"];
            $email = $referralAgent->getEmailsObj()->get_Data()["Email"];

            $mkup .= '<div class="col" style="min-width:fit-content"><strong>Name: </strong>' . $referralAgent->getRoleMember()->get_fullName() . '</div>';
            $mkup .= (!empty($this->settings["Referral Agent"]["workPhone"]) && !empty($workphone) ? '<div class="col" style="min-width:fit-content"><strong>Work Phone: </strong>' . $workphone . '</div>': '');
            $mkup .= (!empty($this->settings["Referral Agent"]["cellPhone"]) && !empty($cellphone) ? '<div class="col" style="min-width:fit-content"><strong>Cell Phone: </strong>' . $cellphone . '</div>': '');
            $mkup .= (!empty($this->settings["Referral Agent"]["Email"]) && !empty($email) ? '<div class="col" style="min-width:fit-content"><strong>Email: </strong>' . $email . '</div>': '');
        }else{
            $mkup .= '<div class="col" style="min-width:fit-content">No ' . $this->labels->getString('Hospital', 'referralAgent', 'Referral Agent') . ' assigned</div>';
        }

        $mkup .= '</div>';

        return $mkup;
    }

    protected function vehicleBlock(array $vehs) {
        $mkup = "";

        if (count($vehs) > 0) {
            $s = '';
            if (count($vehs) > 1) {
                $s = 's';
            }

            $mkup .= "<h2 class='mb-2'>Vehicle$s</h2>";

            foreach ($vehs as $v) {
                $veh = new VehicleRS();
                EditRS::loadRow($v, $veh);

                $mkup .= '<div class="row mb-2 ui-widget-content ui-corner-all py-2">';

                $mkup .= (!empty($veh->Make->getStoredVal()) ? '<div class="col"><strong>Make: </strong>' . $veh->Make->getStoredVal() . '</div>':'');
                $mkup .= (!empty($veh->Model->getStoredVal()) ? '<div class="col"><strong>Model: </strong>' . $veh->Model->getStoredVal() . '</div>':'');
                $mkup .= (!empty($veh->Color->getStoredVal()) ? '<div class="col" style="max-width:fit-content"><strong>Color: </strong>' . $veh->Color->getStoredVal() . '</div>':'');
                $mkup .= (!empty($veh->State_Reg->getStoredVal()) ? '<div class="col" style="max-width:fit-content"><strong>State: </strong>' . $veh->State_Reg->getStoredVal() . '</div>':'');
                $mkup .= (!empty($veh->License_Number->getStoredVal()) ? '<div class="col" style="min-width:fit-content"><strong>License: </strong>' . $veh->License_Number->getStoredVal() . '</div>':'');
                $mkup .= (!empty($veh->Note->getStoredVal()) ? '<div class="col-12"><strong>Note: </strong>' . $veh->Note->getStoredVal() . '</div>':'');

                $mkup .= '</div>';

            }

        }

        return $mkup;

    }

    protected function AgreementBlock(array $guests, $primaryGuestId, $agreementLabel, $agreement) {
        $uS = Session::getInstance();

        $style = (!empty($this->settings["Agreement"]["pagebreak"]) ? $this->settings["Agreement"]["pagebreak"] : "");
        $mkup = '<div class="agreementContainer" style="' . $style . '">';

        if (!empty($this->settings['Agreement']['show'])){
            $mkup .= HTMLContainer::generateMarkup('h2', $agreementLabel, array('class'=>'mb-2', 'style'=>$style));
            if ($agreement != '') {
                $mkup .= '<div class="agreement">' . $agreement . '</div>';
            } else {
                $mkup .= HTMLContainer::generateMarkup('div', "Your Registration Agreement is missing.  ", array('class'=>'ui-state-error'));
            }
        }

        if (!empty($this->settings['Signatures']['show'])){

            $usedNames = array();

            foreach ($guests as $g) {

                if (!isset($usedNames[$g->getIdName()])) {
                    $sigMkup = '<div class="row mt-4 signWrapper" data-idname="' . $g->getIdName() . '">
                                    <div class="col-8 row" style="align-items:flex-end;">
                                        <div class="col pr-0 printName" style="max-width: fit-content;">' . $g->getRoleMember()->get_fullName() . '</div>
                                        <div class="col sigLine" style="border-bottom: 1px solid black; justify-content:end;">' . (!empty($this->settings["Signatures"]["eSign"]) && ($this->settings["Signatures"]["eSign"] == 'jSign' || $this->settings["Signatures"]["eSign"] == 'topaz') ? '<img src="" style="display:none; width:100%"></div>
                                        <button class="ui-button ui-corner-all mb-1 ml-2 btnSign" data-eSign="' . $this->settings["Signatures"]["eSign"] . '">Sign</button>' : '</div>') . '
                                    </div>
                                    <div class="col-4 row" style="align-items:flex-end;">
                                        <div class="col pr-0" style="max-width: fit-content;">Date</div>
                                        <div class="col" style="border-bottom: 1px solid black; text-align:center;"><span class="signDate" style="display:none;">' . (new \DateTime())->format('M j, Y') . '</span></div>
                                    </div>
                                </div>';
                    if(!empty($this->settings['Signatures']['type']) && $this->settings['Signatures']['type'] == 'primary' && $g->getRoleMember()->get_IdName() == $primaryGuestId){
                        //show primary guest signature line
                        $mkup .= $sigMkup;
                        $usedNames[$g->getIdName()] = 'y';
                        break;
                    }else if(!empty($this->settings['Signatures']['type']) && $this->settings['Signatures']['type'] == 'all'){
                        //if showing all guests
                        $mkup .= $sigMkup;
                    }else if(!empty($this->settings['Signatures']['type']) && $this->settings['Signatures']['type'] == 'adults'){
                        //if excluding minors
                        if ($uS->RegNoMinorSigLines && $g->getRoleMember()->get_demogRS()->Is_Minor->getStoredVal() > 0) {
                            // #816, EKC, 5/23/2023
                            continue;
                        } else if($g->getRoleMember()->get_birthDate() != ''){
                            $dob = new \DateTime($g->getRoleMember()->get_birthDate());
                            $now = new \DateTime();
                            $age = $dob->diff($now);
                            $age = $age->format("%y");

                            if($age < 18){
                                continue;
                            }
                        }
                        $mkup .= $sigMkup;
                        $usedNames[$g->getIdName()] = 'y';
                    }
                }
            }

        }

        $mkup .= "</div> <!-- end .agreementContainer -->";

        return $mkup;
    }

    protected function paymentRecord($feesRecord) {

        $mkup = HTMLContainer::generateMarkup('div',  HTMLContainer::generateMarkup('h2', 'Payment Record'), array('style'=>'border:none;border-bottom:1.5pt solid #98C723;padding-left:0;')) . $feesRecord;
        return $mkup;
    }

    protected function creditBlock(array $cardTokens = []) {

        $mkup = HTMLContainer::generateMarkup('h2', 'Payment Information', array('class'=>"mb-2"));

        if (count($cardTokens) > 0) {

            foreach ($cardTokens as $tkRs) {

                if (CreditToken::hasToken($tkRs)) {
                    $mkup .= '<div class="row mb-2 ui-widget-content ui-corner-all py-2">';

                    $mkup .= '<div class="col"><strong>Card on File: </strong>' . $tkRs->CardType->getStoredVal() . ($tkRs->MaskedAccount->getStoredVal() != '' ? ':  ...' . $tkRs->MaskedAccount->getStoredVal() : '') . '</div>';
                    $mkup .= '<div class="col"><strong>Cardholder Name: </strong>' . $tkRs->CardHolderName->getStoredVal() . '</div>';

                    $mkup .= '</div>';
                }
            }
        }

        return HTMLContainer::generateMarkup('div', $mkup);
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

        $uS = Session::getInstance();
        $ecRels = $uS->guestLookups[GLTableNames::PatientRel];

        $mkup = '<h2 class="mb-2">' . Labels::getString('memberType', 'visitor', 'Guest') . "s</h2>";

        foreach ($guests as $guest){

            $name = $guest->getRoleMember();

            $addr = $guest->getAddrObj()->get_data($guest->getAddrObj()->get_preferredCode());

            //$phoneHome = $guest->phones->get_data(Phone_Purpose::Home);
            $phone = $guest->getPhonesObj()->get_data($guest->getPhonesObj()->get_preferredCode());
            if ($guest->getHousePhone() == 1) {
                $phone["Phone_Num"] = 'House Phone';
            }
            $email = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
            $emrg = $guest->getEmergContactObj($dbh);

            $bd = '';
            if ($name->get_birthDate() != '' && !empty($this->settings["Guests"]["birthdate"])) {
                $bd = ' (' . date('M j, Y', strtotime($name->get_birthDate())) . ')';
            }


            $mkup .= '<div class="row mb-2 ui-widget-content ui-corner-all py-2">';

            $mkup .= '<div class="col-12">';
            $mkup .= '<div class="row">';
            $mkup .= '<div class="col">'; //start left guest col
            $mkup .= '<strong>' . ($guest->getIdName() == $primaryGuestId ? Labels::getString('MemberType', 'primaryGuest', 'Primary Guest'): "Name") . ': </strong>' . $name->get_fullName() . $bd . '<br>';
            $mkup .= (!empty($addr['Preferred_Address']) ? '<div class="hhk-flex"><div class="mr-2"><strong>Address: </strong></div><div>' . $addr["Address_1"] . " " .  $addr["Address_2"] . '<br>' . $addr["City"] . ($addr["City"] == "" ? "" : ", ") . $addr["State_Province"] . "  ". $addr["Postal_Code"] . '</div></div>':'');
            $mkup .= (!empty($phone["Phone_Num"]) ? '<strong>Phone: </strong>' . $phone["Phone_Num"] . '<br>':'');

            if(!empty($this->settings['Guests']["emerg"]) && !empty($emrg->getEcNameFirst() . $emrg->getEcNameLast() . $emrg->getEcPhone() . $emrg->getEcRelationship())){
                $mkup .= (!empty($email["Email"]) ? '<strong>E-Mail: </strong>' . $email["Email"] . '<br>':'');
                $mkup .= '<strong>Relationship to ' . $this->labels->getString('MemberType', 'patient', 'Patient') . ': </strong>' . (isset($relationText[$guest->getPatientRelationshipCode()]) ? $relationText[$guest->getPatientRelationshipCode()][1] : '');
                $mkup .='</div>';//end left guest col
                $mkup .='<div class="col">'; //start right guest col
                $mkup .= '<div class="row">';
                $mkup .= '<div class="col-12 ui-widget-content ui-corner-all py-2 mr-2 mb-2">';
                $mkup .= '<strong>Emergency Contact: </strong>' . (!empty($emrg->getEcNameFirst()) ? $emrg->getEcNameFirst() . ' ':'') . (!empty($emrg->getEcNameLast()) ? $emrg->getEcNameLast():'') . '<br>';
                $mkup .= (!empty($emrg->getEcPhone()) ? '<strong>Phone: </strong>' . $emrg->getEcPhone() . '<br>':'');
                $mkup .= (!empty($emrg->getEcRelationship()) ? '<strong>Relationship to ' . Labels::getString('memberType', 'visitor', "Guest") . ': </strong>' . (isset($ecRels[$emrg->getEcRelationship()]) ? $ecRels[$emrg->getEcRelationship()][1] : ''):'');
                $mkup .= '</div>';//end .col emerg contact
                $mkup .= '<div class="col-12">';
                $mkup .= '<strong>Check In Date: </strong>' . $guest->getCheckinDT()->format('M j, Y') . "<br>";
                $mkup .= '<strong>Expected Check Out: </strong>' . $guest->getExpectedCheckOutDT()->format('M j, Y');
                $mkup .= '</div>';//end col-12
                $mkup .= '</div>';//end .row
            }else{
                $mkup .= '</div>'; //end left guest col
                $mkup .= '<div class="col">'; //start right guest col
                $mkup .= (!empty($email["Email"]) ? '<strong>E-Mail: </strong>' . $email["Email"] . '<br>':'');
                $mkup .= '<strong>Relationship to ' . $this->labels->getString('MemberType', 'patient', 'Patient') . ': </strong>' . (isset($relationText[$guest->getPatientRelationshipCode()]) ? $relationText[$guest->getPatientRelationshipCode()][1] : ''). "<br>";
                $mkup .= '<strong>Check In Date: </strong>' . $guest->getCheckinDT()->format('M j, Y') . "<br>";
                $mkup .= '<strong>Expected Check Out: </strong>' . $guest->getExpectedCheckOutDT()->format('M j, Y');
            }
            $mkup .= '</div>';//end right guest col

            $mkup .= '</div>';//end row

            $mkup .= '</div>';//end col-12

            $mkup .= '</div>';//end row

        }

        return $mkup;

    }

    protected function printFooterBlock($primaryGuestName = "", $room = ""){
        $mkup = '<footer class="row pt-3" style="width: 100%;"><div class="col">Registration Form' . ($primaryGuestName != "" ? "  &bull;  " . $primaryGuestName : "") . ($room != '' ? '  &bull;  Room ' . $room : "") . '</div><div class="col" style="text-align:right; max-width: fit-content;">Printed at ' . date("m/d/Y g:i A") . '</div></footer>';

        return $mkup;
    }

    protected function setPageTitle($primaryGuestName = "", $room = ""){
        $this->pageTitle = 'Registration Form' . ($primaryGuestName != "" ? " - " . $primaryGuestName : "") . ($room != '' ? ' - Room ' . $room : "");
    }

    public function getPageTitle(){
        return $this->pageTitle;
    }

    protected function generateDocument(\PDO $dbh, $title, AbstractRole $patient, $referralAgent, array $guests,  $houseAddr, $hospital, $mrn, $hospRoom, $diagnosis, $location, $doctor, $patientRelCodes,
            $vehicles, $agent, $rate, $roomTitle, $expectedDeparture, $expDepartPrompt, $agreement, $cardTokens, $notes, $primaryGuestId = 0) {

        $uS = Session::getInstance();

        $mkup = "<div class='container'>";
        $mkup .= self::titleBlock($roomTitle, $expectedDeparture, $expDepartPrompt, $rate, $title, $agent, $uS->RoomPriceModel, $notes, $houseAddr);

        //guests
        if(!empty($this->settings['Guests']['show'])){
            $mkup .= $this->guestBlock($dbh, $guests, $patientRelCodes, $primaryGuestId);
        }

        // Patient
        if(!empty($this->settings['Patient']['show'])){
            $mkup .= $this->patientBlock($patient, $hospital, $mrn, $hospRoom, $diagnosis, $location, $doctor);
        }

        // Referral Agent
        if(!empty($this->settings['Referral Agent']['show'])){
            $mkup .= $this->referralAgentBlock($referralAgent);
        }

        // Vehicles
        if (is_null($vehicles) === FALSE) {
            $mkup .= $this->vehicleBlock($vehicles);
        }

        // Credit card
        if (count($cardTokens) > 0) {
            $mkup .= $this->creditBlock($cardTokens);
        }

        // Agreement

        if(!empty($this->settings["Agreement"]['title'])){
            $agreementTitle = $this->settings['Agreement']['title'];
        }else{
            $agreementTitle = $this->labels->getString('referral', 'agreementTitle','Agreement');
        }

        $mkup .= $this->AgreementBlock($guests, $primaryGuestId, $agreementTitle, $agreement);

        $mkup .= "</div> <!-- end .container -->";

        $primaryGuestName = '';
        foreach($guests as $guest){
            if($guest->getIdName() == $primaryGuestId){
                $primaryGuestName = $guest->getRoleMember()->get_fullName();
                break;
            }
        }

        //$mkup .= $this->printFooterBlock($primaryGuestName, $roomTitle);

        $this->setPageTitle($primaryGuestName, $roomTitle);

        return $mkup;
    }

    public static function getStyling() {
        return '<style id="regFormStyle">
     /* Style Definitions */

    * {
        line-height:1.5em;
    }

    .header * {
        line-height: 1.1em;
    }

    .agreement * {
        line-height: 1em;
    }

    h2 {
        line-height: 1.1em;
    }

    h2:not(.title):not(.agreement h2) {
        border-bottom:1.5pt solid #98C723;
    }

    .title {
        padding-left: 0;
    }

    .row {
        page-break-inside: avoid;
    }

    @media screen {
        footer {
            display: none !important;
        }
    }

    @media print {
        footer {
            position: fixed;
            bottom: 0;
            //height: .25in;
            //display: table-footer-group;
        }

        button.btnSign{
            display:none;
        }

        @page {
            size: letter;
            margin: .5in .5in;
            //margin-bottom: 0.5in;
        }

        .agreementContainer p {
            page-break-inside: always;
        }

        .agreementContainer li {
            page-break-inside: always;
        }

    }

</style>';
    }

    public function prepareRegForm(\PDO $dbh, $idVisit, $span, $idReservation, $doc = []) {

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
        $expectedDeparturePrompt = 'Expected Departure';
        $hospital = '';

        $agreement = "";
        if(isset($doc['Doc'])){
            $agreement = $doc['Doc'];
        }

        if ($idVisit > 0) {

            // visit
            $visit = new Visit($dbh, 0, $idVisit, NULL, NULL, NULL, '', $span);
            $reg = new Registration($dbh, 0, $visit->getIdRegistration());
            $visit->getResource($dbh);

            $stayingSql = ($visit->getVisitStatus() == VisitStatus::CheckedIn ? " and s.Status = 'a' " : ""); //only show current stays if visit is checked in
            $query = "select s.idName, s.Span_Start_Date, s.Expected_Co_Date, s.Span_End_Date, s.`Status`, if(s.idName = v.idPrimaryGuest, 1, 0) as `primaryGuest`
					from stays s "
                    . " join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span "
                    . " where s.idVisit = :reg and s.Visit_Span = :spn "
                    . $stayingSql
                    . " and DATEDIFF(ifnull(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date)), s.Span_Start_Date) > 0 "
                    . " order by `primaryGuest` desc, `Status` desc";
            $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            $stmt->bindValue(':reg', $idVisit, \PDO::PARAM_INT);
            $stmt->bindValue(':spn', $span, \PDO::PARAM_INT);
            $stmt->execute();
            $stays = $stmt->fetchAll(\PDO::FETCH_ASSOC);



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

            $query = "select hs.idPatient, hs.Room, IFNULL(h.Title, ''), IFNULL(d.Description, '') as 'Diagnosis', IFNULL(l.Description, '') as 'Location', hs.MRN, hs.idReferralAgent, hs.idDoctor from hospital_stay hs join visit v on hs.idHospital_stay = v.idHospital_Stay
				left join hospital h on hs.idHospital = h.idHospital left join gen_lookups d on hs.diagnosis = d.Code and d.Table_Name = 'diagnosis' left join gen_lookups l on hs.Location = l.Code and l.Table_Name = 'Location' where v.idVisit = " . intval($idVisit) . " group by v.idVisit limit 1";

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

            $query = "select hs.idPatient, hs.Room, IFNULL(h.Title, ''), IFNULL(d.Description, '') as 'Diagnosis', IFNULL(l.Description, '') as 'Location', hs.MRN, hs.idReferralAgent, hs.idDoctor from hospital_stay hs join reservation r on hs.idHospital_stay = r.idHospital_Stay
				left join hospital h on hs.idHospital = h.idHospital left join gen_lookups d on hs.diagnosis = d.Code and d.Table_Name = 'diagnosis' left join gen_lookups l on hs.Location = l.Code and l.Table_Name = 'Location' where r.idReservation = " . intval($idReservation) . " limit 1";

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
            $diagnosis = $hospitalStay[0][3];
            $location = $hospitalStay[0][4];
            $mrn = $hospitalStay[0][5];
            if($hospitalStay[0][6] > 0){
                $referralAgent = new Agent($dbh, '', $hospitalStay[0][6]);
            }else{
                $referralAgent = null;
            }
            if($hospitalStay[0][7] > 0){
                $doctor = new Doctor($dbh, '', $hospitalStay[0][7]);
            }else{
                $doctor = null;
            }

        }

        // Title
        if(!empty($this->settings["Header"]["title"])){
            $title = $this->settings["Header"]["title"];
        }else{
            $title = $uS->siteName . " Registration Form";
        }

        // Vehicles
        if ($uS->TrackAuto && !empty($this->settings['Vehicle']['show'])) {

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
        $cardTokens = [];
        if (($primaryGuestId > 0 || $reg->getIdRegistration() > 0) && !empty($this->settings['Credit Cards']['show'])) {

            $cardTokens = CreditToken::getRegTokenRSs($dbh, $reg->getIdRegistration(), '', $primaryGuestId);

        }


        $roomTitle = '';

        if (!empty($this->settings['Top']['room'])) {

            $stmt2 = $dbh->query("select Title from resource where idResource = '" . $idResc . "';");
            $rows2 = $stmt2->fetchAll();

            if(count($rows2) == 1 && !empty($rows2[0]['Title'])) {
                $roomTitle = $rows2[0]['Title'];
            }
        }


        // get user name phrase
        $agent = '';
        $userRS = new NameRS();
        $userRS->idName->setStoredVal($uS->uid);
        $users = EditRS::select($dbh, $userRS, array($userRS->idName));

        if (count($users) > 0) {
            EditRS::loadRow($users[0], $userRS);

            switch($this->settings["Top"]["staff"]){
                case 'shortname':
                    $agent = $userRS->Name_First->getStoredVal() . " " . substr($userRS->Name_Last->getStoredVal(), 0, 2);
                    break;
                case 'username':
                    $agent = $uS->username;
                    break;
                case 'nickname':
                    $agent = $userRS->Name_Nickname->getStoredVal();
                    break;
                default:
                    $agent = '';
            }

            //$agent = $userRS->Name_First->getStoredVal() . ' ' . substr($userRS->Name_Last->getStoredVal(), 0, 2);
        }

        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        $rate = (1 + $rateAdj/100) * $priceModel->amountCalculator(1, $idRate, $rateCat, $pledgedRate);

        $houseAddr = '';

        $stmth = $dbh->query("select a.Address_1, a.Address_2, a.City, a.State_Province, a.Postal_Code, p.Phone_Num
    from name n left join name_phone p on n.idName = p.idName and n.Preferred_Phone = p.Phone_Code left join name_address a on n.idName = a.idName and n.Preferred_Mail_Address = a.Purpose where n.idName = " . $uS->sId);

        $rows = $stmth->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) == 1) {

            $street = $rows[0]['Address_1'];

            if ($rows[0]['Address_2'] != '') {
                $street .= ', ' . $rows[0]['Address_2'];
            }

            $houseAddr = $street . ' ' . $rows[0]['City'] . ', ' . $rows[0]['State_Province'] . ' ' . $rows[0]['Postal_Code'] . "<br>" . $rows[0]['Phone_Num'];

        }



        return $this->generateDocument(
                $dbh,
                $title,
                $patient,
                $referralAgent,
                $guests,
                $houseAddr,
                $hospital,
                $mrn,
                $hospRoom,
                $diagnosis,
                $location,
                $doctor,
                $uS->guestLookups[GLTableNames::PatientRel],
                $vehs,
                $agent,
                $rate,
                $roomTitle,
                $depDate,
                $expectedDeparturePrompt,
                $agreement,
                $cardTokens,
                $notes,
                $primaryGuestId
            );

    }

    public function getEditMkup(){
        $mkup = HTMLContainer::generateMarkup("h2", "Registration Form Layout Options");

        foreach($this->settingTemplate as $group=>$inputs){
            $mkup .= '<div class="ui-widget mb-3">';
            $mkup .= HTMLContainer::generateMarkup("h3", $group, ["class"=>"ui-widget-header ui-corner-top pl-2"]);
            $mkup .= '<div class="ui-widget-content ui-corner-bottom p-2">';

            foreach($inputs as $key=>$input){
                switch($input["type"]){
                    case "string":
                        $inputMkup = HTMLContainer::generateMarkup("label", $input['label'], ["for"=>"regForm[" . $this->docCode . "][" . $group . "][" . $key . "]", "class"=>"mr-2"]) . HTMLInput::generateMarkup((!empty($this->settings[$group][$key]) ? $this->settings[$group][$key] : ''), ["name"=>"regForm[" . $this->docCode . "][" . $group . "][" . $key . "]"]);
                        break;
                    case "bool":
                        $cbAttr = ["type"=>"checkbox", "name"=>"regForm[" . $this->docCode . "][" . $group . "][" . $key . "]", "class"=>"mr-2"];
                        if(!empty($this->settings[$group][$key])){
                            $cbAttr['checked'] = 'checked';
                        };
                        $inputMkup = HTMLInput::generateMarkup("", $cbAttr) . HTMLContainer::generateMarkup("label", $input['label'], ["for"=>"regForm[" . $this->docCode . "][" . $group . "][" . $key . "]"]);
                        break;
                    case "select":
                        $inputMkup = HTMLContainer::generateMarkup("label", $input['label'], ["for"=>"regForm[" . $this->docCode . "][" . $group . "][" . $key . "]", "class"=>"mr-2"]) . HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($input['values'], (!empty($this->settings[$group][$key]) ? $this->settings[$group][$key] : ''), false), ["name"=>"regForm[" . $this->docCode . "][" . $group . "][" . $key . "]"]);
                        break;
                    default:
                        $inputMkup = '';
                }

                $mkup .= HTMLContainer::generateMarkup("div",$inputMkup, ["class"=>"mb-2"]);
            }
            $mkup .= "</div></div>";
        }

        //misc settings
        $mkup .= '<div class="ui-widget mb-3">';
        $mkup .= HTMLContainer::generateMarkup("h3", "Miscellaneous", ["class"=>"ui-widget-header ui-corner-top pl-2"]);
        $mkup .= '<div class="ui-widget-content ui-corner-bottom p-2">';

        $cbAttr = ["type"=>"checkbox", "name"=>"regForm[misc][applyAll]", "class"=>"mr-2"];
        $inputMkup = HTMLInput::generateMarkup("", $cbAttr) . HTMLContainer::generateMarkup("label", "Apply layout options to all forms", ["for"=>"regForm[misc][applyAll]"]);

        $mkup .= HTMLContainer::generateMarkup("div",$inputMkup, ["class"=>"mb-2"]);
        $mkup .= "</div></div>";

        return HTMLContainer::generateMarkup("div", $mkup);
    }

    public function validateSettings($post = array()){

        $settings = [];

        foreach($this->settingTemplate as $group=>$inputs){

            foreach($inputs as $key=>$input){
                switch($input["type"]){
                    case "string":
                        $settings[$group][$key] = (!empty($post[$group][$key]) ? $post[$group][$key]: "");
                        break;
                    case "bool":
                        $settings[$group][$key] = (!empty($post[$group][$key]) ? boolval($post[$group][$key]): false);
                        break;
                    case "select":
                        $settings[$group][$key] = (!empty($post[$group][$key]) ? $post[$group][$key]: "");
                        break;
                    default:
                }

            }
        }

        return $settings;

    }

    public function getDefaultSettings(){
        $settings = [];
        foreach($this->settingTemplate as $group=>$inputs){

            foreach($inputs as $key=>$input){
                switch($input["type"]){
                    case "bool":
                        $settings[$group][$key] = (!empty($input["default"]) ? $input["default"]: false);;
                        break;
                    default:
                        $settings[$group][$key] = (!empty($input["default"]) ? $input["default"]: "");
                }

            }
        }

        return $settings;
    }
}

?>