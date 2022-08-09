<?php

namespace HHK\House\RegistrationForm;

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
use HHK\House\Registration;
use HHK\House\PSG;
use HHK\House\Vehicle;

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
 */
class CustomRegisterForm {

    public $settingTemplate = [
        "Header"=>[
            "Title"=>[
                "type"=>"string"
            ],
            "Logo"=>[
                "type"=>"bool"
            ],
            "Address"=>[
                "type"=>"bool"
            ],
            "Layout"=>[
                "type"=>"dropdown",
                "values"=>[
                    "logoRight"=>"Logo Right",
                    "logoLeft"=>"Logo Left",
                    "center"=>"Center Stacked"
                ]
            ]
        ],
        "Top"=>[
            "Room"=>[
                "type"=>"bool"
            ],
            "Rate"=>[
                "type"=>"bool"
            ],
            "Departure"=>[
                "type"=>"bool"
            ],
            "Staff"=>[
                "type"=>"bool"
            ]
        ],
        "Guests"=>[
            "Show"=>[
                "type"=>"bool"
            ],
            "Type"=>[
                "type"=>"dropdown",
                "values"=>[
                    "checkedin"=>"Only Checked in guests",
                    "allstays"=>"All Guests staying"
                ]
            ],
        ],
        "Patient"=>[
            "Show"=>[
                "type"=>"bool"
            ],
            "Hospital"=>[
                "type"=>"bool"
            ],
            "HospRoom"=>[
                "type"=>"bool"
            ],
            "Diagnosis"=>[
                "type"=>"bool"
            ],
        ],
        "Vehicle"=>[
            "Show"=>[
                "type"=>"bool"
            ],
        ],
        "Agreement"=>[
            "Show"=>[
                "type"=>"bool"
            ],
            "Title"=>[
                "type"=>"string"
            ]
        ],
        "Signatures"=>[
            "Show"=>[
                "type"=>"bool"
            ],
            "Type"=>[
                "type"=>"dropdown",
                "values"=>[
                    "all"=>"All Guests",
                    "primary"=>"Primary Guest Only",
                    "adults"=>"Adults (18+) Only"
                ]
            ]
        ]
    ];

    public $labels;

    protected function titleBlock($roomTitle, $expectedDeparture, $expDepartPrompt, $rate, $title, $agent, $priceModelCode, $houseAddr = '', $roomFeeTitle = 'Pledged Fee') {

        $uS = Session::getInstance();

        $staff = 'Staff';
        $mkup = '<div class="header row mb-3"><div class="col-8"><h2 class="title">' . $title . '</h2>';

        if ($houseAddr != '') {
            $mkup .= '<p>' . $houseAddr . '</p>'; //'<br>' . date("M j, Y") .
        }

        $mkup .= '</div><div class="col-4"><img src="' . $uS->statementLogoFile . '" style="max-width:100%"></div></div>';

        if (stristr($title, 'Patient Family Housing') !== false) {
            $agent = '';
            $staff = '';
        }

        $mkup .= '<div class="row mb-3 ui-widget-content ui-corner-all py-2">';

        $mkup .= '<div class="col" style="min-width: fit-content"><strong>Room:</strong> <span class="room">' . $roomTitle . '</span></div>';
        $mkup .= ($priceModelCode == ItemPriceCode::None ? '' : "<div class='col' style='min-width: fit-content'><strong>" . $this->labels->getString('register', 'rateTitle','Pledged Fee') . ":</strong> $"  . number_format($rate, 2) . "</div>");
        $mkup .= '<div class="col" style="min-width: fit-content"><strong>' . $expDepartPrompt . ': </strong><span>' . ($expectedDeparture == '' ? '' : date("M j, Y", strtotime($expectedDeparture))) . '</span></div>';
        $mkup .= '<div class="col" style="min-width: fit-content"><strong>' . $staff . ": </strong>" . $agent . '</div>';

        $mkup .= '</div>';

        return $mkup;
    }

    protected function patientBlock(AbstractRole $patient, $hospital, $hospRoom) {

        $bd = '';
        if ($patient->getRoleMember()->get_birthDate() != '') {
            $bd = ' (' . date('M j, Y', strtotime($patient->getRoleMember()->get_birthDate())) . ')';
        }

        $mkup = "<h2 class='mb-2'>" .$this->labels->getString('MemberType', 'patient', 'Patient'). "</h2>";

        $mkup .= '<div class="row mb-3 ui-widget-content ui-corner-all py-2">';

        $mkup .= '<div class="col"><strong>Name: </strong>' . $patient->getRoleMember()->get_fullName() . $bd . '</div>';
        $mkup .= '<div class="col"><strong>' . $this->labels->getString('hospital', 'hospital', 'Hospital') . ': </strong>' . $hospital . '</div>';
        if($hospRoom != ''){
            $mkup .= '<div class="col"><strong>' . $this->labels->getString('hospital', 'hospital', 'Hospital') . " Room: </strong>" . $hospRoom . '</div>';
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

                $mkup .= '<div class="col"><strong>Make: </strong>' . $veh->Make->getStoredVal() . '</div>';
                $mkup .= '<div class="col"><strong>Model: </strong>' . $veh->Model->getStoredVal() . '</div>';
                $mkup .= '<div class="col" style="max-width:fit-content"><strong>Color: </strong>' . $veh->Color->getStoredVal() . '</div>';
                $mkup .= '<div class="col" style="max-width:fit-content"><strong>State: </strong>' . $veh->State_Reg->getStoredVal() . '</div>';
                $mkup .= '<div class="col" style="min-width:fit-content"><strong>License: </strong>' . $veh->License_Number->getStoredVal() . '</div>';
                if($veh->Note->getStoredVal() != ''){
                    $mkup .= '<div class="col-12"><strong>Note: </strong>' . $veh->Note->getStoredVal() . '</div>';

                }

                $mkup .= '</div>';

            }

        }

        return $mkup;

    }

    protected function AgreementBlock(array $guests, $agreementLabel, $agreement) {

        $uS = Session::getInstance();
        $mkup = '<div class="agreementContainer">' . HTMLContainer::generateMarkup('h2', $agreementLabel, array('class'=>'mb-2'));

        if ($agreement != '') {
            $mkup .= '<div class="agreement">' . $agreement . '</div></div>';
        } else {
            $mkup .= HTMLContainer::generateMarkup('div', "Your Registration Agreement is missing.  ", array('class'=>'ui-state-error'));
        }


        if (stristr($uS->siteName, 'Patient Family Housing') === false) {

            $usedNames = array();

            $usedNames = array();

            foreach ($guests as $g) {

                if (!isset($usedNames[$g->getIdName()])) {

                    $mkup .= '<div class="row mt-4"><div class="col-8 row"><div class="col pr-0" style="max-width: fit-content;">' . $g->getRoleMember()->get_fullName() . '</div><div class="col" style="border-bottom: 1px solid black;"></div></div><div class="col-4 row"><div class="col pr-0" style="max-width: fit-content">Date</div><div class="col" style="border-bottom: 1px solid black;"></div></div></div>';
                    $usedNames[$g->getIdName()] = 'y';
                }
            }

        }

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

        $uS = Session::getInstance();
        $ecRels = $uS->guestLookups[GLTableNames::PatientRel];

        $mkup = '<h2 class="mb-2">' . Labels::getString('memberType', 'visitor', 'Guest') . "s</h2>";

        foreach ($guests as $guest){

            $name = $guest->getRoleMember();

            $addr = $guest->getAddrObj()->get_data($guest->getAddrObj()->get_preferredCode());
            //$phoneHome = $guest->phones->get_data(Phone_Purpose::Home);
            $phoneCell = $guest->getPhonesObj()->get_data(PhonePurpose::Cell);
            if ($phoneCell["Phone_Num"] == '' || $guest->getHousePhone() == 1) {
                $phoneCell["Phone_Num"] = 'House Phone';
            }
            $email = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
            $emrg = $guest->getEmergContactObj($dbh);


            $mkup .= '<div class="row mb-2 ui-widget-content ui-corner-all py-2">';

            $mkup .= '<div class="col-12">';
            $mkup .= '<div class="row">';
            $mkup .= '<div class="col">';
            $mkup .= '<strong>' . ($guest->getIdName() == $primaryGuestId ? Labels::getString('MemberType', 'primaryGuest', 'Primary Guest'): "Name") . ': </strong>' . $name->get_fullName() . '<br>';
            $mkup .= '<div class="hhk-flex"><div class="mr-2"><strong>Address: </strong></div><div>' . $addr["Address_1"] . " " .  $addr["Address_2"] . '<br>' . $addr["City"] . ($addr["City"] == "" ? "" : ", ") . $addr["State_Province"] . "  ". $addr["Postal_Code"] . '</div></div>';
            $mkup .= '<strong>Cell Phone: </strong>' . $phoneCell["Phone_Num"] . '<br>';
            $mkup .='</div>';
            $mkup .= '<div class="col ui-widget-content ui-corner-all py-2 mr-2 mb-2">';
            $mkup .= '<strong>Emergency Contact:</strong>' . $emrg->getEcNameFirst() . ' ' . $emrg->getEcNameLast() . '<br>';
            $mkup .= '<strong>Phone: </strong>' . $emrg->getEcPhone() . '<br>';
            $mkup .= '<strong>Relationship to ' . Labels::getString('memberType', 'visitor', "Guest") . ': </strong>' . (isset($ecRels[$emrg->getEcRelationship()]) ? $ecRels[$emrg->getEcRelationship()][1] : '');
            $mkup .= '</div>';
            $mkup .= '</div>';
            $mkup .= '<div class="row">';
            $mkup .= '<div class="col-6">';
            $mkup .= '<strong>E-Mail: </strong>' . $email["Email"] . '<br>';
            $mkup .= '</div>';
            $mkup .= '<div class="col-6">';
            $mkup .= '<strong>Check In Date: </strong>' . $guest->getCheckinDT()->format('M j, Y');
            $mkup .= '</div>';
            $mkup .= '<div class="col-6">';
            $mkup .= '<strong>Relationship to ' . $this->labels->getString('MemberType', 'patient', 'Patient') . ': </strong>' . (isset($relationText[$guest->getPatientRelationshipCode()]) ? $relationText[$guest->getPatientRelationshipCode()][1] : '');
            $mkup .= '</div>';
            $mkup .= '<div class="col-6">';
            $mkup .= '<strong>Expected Check Out: </strong>' . $guest->getExpectedCheckOutDT()->format('M j, Y');
            $mkup .= '</div>';
            $mkup .= '</div>';
            $mkup .= '</div>';

            $mkup .= '</div>';

        }

        return $mkup;

    }

    protected function printFooterBlock($primaryGuestName = "", $room = ""){
        $mkup = '<footer class="row pt-3" style="width: 100%;"><div class="col-6">Registration Form' . ($primaryGuestName != "" ? "  &bull;  " . $primaryGuestName : "") . ($room != '' ? '  &bull;  Room ' . $room : "") . '</div><div class="col-6" style="text-align:right;">Printed at ' . date("m/d/Y g:i A") . '</div></footer>';

        return $mkup;
    }

    protected function generateDocument(\PDO $dbh, $title, AbstractRole $patient, array $guests,  $houseAddr, $hospital, $hospRoom, $patientRelCodes,
            $vehicles, $agent, $rate, $roomTitle, $expectedDeparture, $expDepartPrompt, $agreement, $creditRecord, $notes, $primaryGuestId = 0) {

        $uS = Session::getInstance();

        $mkup = "<div class='container'>";
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

        $primaryGuestName = '';
        foreach($guests as $guest){
            if($guest->getIdName() == $primaryGuestId){
                $primaryGuestName = $guest->getRoleMember()->get_fullName();
                break;
            }
        }

        $mkup .= $this->printFooterBlock($primaryGuestName, $roomTitle);

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

    h2:not(.title) {
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
        }

        @page {
            size: letter;
            margin: .25in .5in;
        }

        .agreementContainer {
            page-break-inside: avoid;
        }


    }

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
        $title = $uS->siteName . " Registration Form";

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



        return CustomRegisterForm::generateDocument(
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



?>