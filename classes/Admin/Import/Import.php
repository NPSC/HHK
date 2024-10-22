<?php
namespace HHK\Admin\Import;


use HHK\Member\Role\Doctor;
use HHK\Member\Role\Patient;
use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\Notes;
use HHK\SysConst\RelLinkType;
use HHK\sec\Session;
use HHK\House\PSG;
use HHK\House\Registration;
use HHK\House\Hospital\HospitalStay;
use HHK\Member\Role\Guest;
use HHK\SysConst\VolMemberType;
use HHK\Tables\Visit\VisitRS;
use HHK\SysConst\VisitStatus;
use HHK\Tables\EditRS;
use HHK\SysConst\ReservationStatus;
use HHK\Tables\Visit\StaysRS;
use HHK\Tables\GenLookupsRS;
use RuntimeException;


class Import {

    protected \PDO $dbh;
    protected array $zipLookups;
    protected array $hospitals;
    protected array $relations;
    protected array $diags;
    protected array $rooms;
    protected array $genders;
    protected array $ethnicities;
    protected array $noReturn;
    protected array $mediaSources;
    protected int $importedPatients;
    protected int $importedGuests;

    public function __construct(\PDO $dbh){
        $this->dbh = $dbh;
        $this->getHospitals();
        $this->getRelations();
        $this->getDiags();
        $this->getRooms();
        $this->getGenders();
        $this->getEthnicities();
        $this->getNoReturn();
        $this->getMediaSources();
    }

    public function startImport(int $limit = 100, bool $people = true, bool $visits = false){

        ini_set('max_execution_time', '300');

        $query = "Select * from `" . Upload::TBL_NAME . "` i where i.imported is null group by i.importId order by i.`importId` LIMIT $limit;";
        $stmt = $this->dbh->query($query);

        $numRead = $stmt->rowCount();
        $psg = null;
        $patient = null;
        $reg = null;
        $hospStay = null;
        $this->importedPatients = 0;
        $this->importedGuests = 0;
        $idGuest = 0;

        $patId = '';

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $guests = [];
            try{
                $this->dbh->beginTransaction();
               //loop through guests
                $patient = [
                    "GuestFirst" => $r['PatientFirst'],
                    "GuestLast" => $r['PatientLast'],
                    "Relationship_to_Patient" => "Self",
                    "BirthDate" => "",
                    "Gender" => $r["PatientGender"],
                    "mediaSource" => $r["PatientMarketingOptIn"],
                    "Street" => "",
                    "City" => $r["PatientCity"],
                    "County" => "",
                    "State" => "",
                    "ZipCode" => "",
                    "Phone" => str_replace("-", "", filter_var($r["PatientPhoneNumber"], FILTER_SANITIZE_NUMBER_INT)),
                    "Mobile" => "",
                    "Email" => $r["PatientEmail"],
                    "Diagnosis"=>"",
                    "PrimaryGuest" => $r["PatientPrimaryGuest"],
                    "Banned" => $r["PatientBanned"],
                    "Hospital" =>$r["Hospital"],
                    "importId"=>$r["importId"]
                ];

                $guests[] = $patient;

                for ($i = 1; $i < 3; $i++){
                    if(trim($r['Guest_'.$i.'_First']) !== "" && trim($r['Guest_'.$i.'_Last']) !== ""){
                        $guest = [
                            "GuestFirst" => $r['Guest_'.$i.'_First'],
                            "GuestLast" => $r['Guest_'.$i.'_Last'],
                            "Relationship_to_Patient" => $r["Guest_".$i."_Relationship"],
                            "BirthDate" => "",
                            "Gender" => $r["Guest_".$i."_Gender"],
                            "mediaSource" => $r["Guest_".$i."_Marketing_opt_in"],
                            "Street" => "",
                            "City" => $r["Guest_".$i."_City"],
                            "County" => "",
                            "State" => "",
                            "ZipCode" => "",
                            "Phone" => str_replace("-", "", filter_var($r["Guest_".$i."_Phone_Number"], FILTER_SANITIZE_NUMBER_INT)),
                            "Mobile" => "",
                            "Email" => $r["Guest_".$i."_Email"],
                            "Diagnosis"=>"",
                            "PrimaryGuest"=>$r["Guest_".$i."_Primary_Guest"],
                            "Banned"=>$r["Guest_".$i."_Banned"],
                            "Hospital" =>$r["Hospital"],
                            "importId"=>$r["importId"]
                        ];
                        $guests[] = $guest;
                    }
                }

                $patient = false;

                //find patient
                foreach($guests as $k=>$guest){
                    if($guest["Relationship_to_Patient"] == "Self"){
                        $patArray = $this->addPatient($guest);
                        $patient = $patArray["patient"];
                        $psg = $patArray["psg"];
                        $reg = $patArray["reg"];
                        $hospStay = $patArray["hospStay"];
                        $guests[$k]["idName"] = $patient->getIdName();
                    }
                }

                /*
                if($patient == false){
                    $guest = [
                        "GuestFirst" => $r['FirstName'],
                        "GuestLast" => $r["LastName"],
                        "Relationship_to_Patient" => "Self",
                        "BirthDate" => "",
                        "Gender" => "",
                        "Ethnicity" => "",
                        "Street" => "",
                        "City" => "",
                        "County" => "",
                        "State" => "",
                        "ZipCode" => "",
                        "Phone" => "",
                        "Mobile" => "",
                        "Email" => "",
                        "Diagnosis"=>$r["Diagnosis"],
                        "Hospital" =>$r["Hospital"],
                        "importId" =>$r["importId"]
                    ];

                    $patArray = $this->addPatient($guest, false);
                    $patient = $patArray["patient"];
                    $psg = $patArray["psg"];
                    $reg = $patArray["reg"];
                    $hospStay = $patArray["hospStay"];
                }
*/
                //add guests
                foreach($guests as $k=>$guest){
                    if($guest["Relationship_to_Patient"] !== "Self"){
                        $guests[$k]["idName"] = $this->addGuest($guest, $psg)->getIdName();
                    }
                }

                if(isset($r["prop_Vehicle_1___Make___Model"]) && isset($r["prop_Vehicle_1___Color"]) && trim($r["prop_Vehicle_1___Make___Model"]) != "" && trim($r["prop_Vehicle_1___Color"]) != "" && trim($r["prop_Vehicle_1___License_No_"]) != ""){
                    $this->addVehicle($r, $reg);
                }
                
                //resv
                if($r['ArrivalDate'] != '' && $r['DepartureDate'] != '' && count($guests) > 0 && $hospStay instanceof HospitalStay){
                    $idResv = false;
                    
                    $idResv = $this->addReservation($guests, $reg, $hospStay, $r);
                    $this->addVisit($r, $guests, $reg, $hospStay, $idResv);
                    
                }

                $this->dbh->commit();

                // mark imported
                $this->dbh->exec("update `" . Upload::TBL_NAME . "` set `imported` = '1' where `importId` = " . $r['importId']);

            }catch(\Exception $e){
                if($this->dbh->inTransaction()){
                    $this->dbh->rollBack();
                }

                return array("error"=>$e->getMessage(), "ImportId"=>$r['importId'], "trace"=>$e->getTraceAsString());
            }
        }

        return array('success'=>true, 'batch'=>$numRead, 'patients'=>$this->importedPatients, 'guests'=>$this->importedGuests, "progress"=>$this->getProgress());

    }

    private function addPatient(array $r, bool $update = true){

        // New Patient
        $newPatFirst = trim(addslashes($r['GuestFirst']));
        $newPatLast = trim(addslashes($r['GuestLast']));
        //$newPatNickname = trim(addslashes($r['PatientNickname']));
        $gender = $this->findIdGender($r['Gender']);
        $ethnicity = $this->findIdEthnicity((isset($r['Ethnicity']) ? $r["Ethnicity"] : ""));
        $noReturn = $this->findIdNoReturn($r["Banned"]);
        $mediaSource = $this->findIdMediaSource($r["mediaSource"]);

        $birthDate = "";
        if(trim($r['BirthDate']) != ''){
            $birthdateDT = new \DateTime($r['BirthDate']);
            $birthDate = $birthdateDT->format("M j, Y");
        }


        $id = $this->findPerson($newPatFirst, $newPatLast, "patient");

        if($id > 0){
            $patient = new Patient($this->dbh, '', $id);
            $psg = new Psg($this->dbh, 0, $patient->getIdName());
            $reg = new Registration($this->dbh, $psg->getIdPsg());

            $hospitalId = (isset($this->hospitals[trim(strtolower($r['Hospital']))]) ? $this->hospitals[trim(strtolower($r['Hospital']))] : 0);

            $hospitalStay = null;
            if ($hospitalId > 0) {

                $hospitalStay = new HospitalStay($this->dbh, $patient->getIdName());
                $hospitalStay->setHospitalId($hospitalId);
                $hospitalStay->setIdPsg($psg->getIdPsg());
                if(isset($r["Diagnosis"])){
                    $hospitalStay->setDiagnosis($this->findIdDiag($r["Diagnosis"]));
                }

                $hospitalStay->save($this->dbh, $psg, 0, 'admin');
            }
            return array("patient"=>$patient, "psg"=>$psg, "reg"=> $reg, "hospStay"=>$hospitalStay);
        }
        

        $post = array(
            'txtFirstName' => $newPatFirst,
            'txtLastName'=>  $newPatLast,
            'txtNickname' => '',

            'txtBirthDate'=>$birthDate,
            'selStatus'=>'a',
            'sel_Gender'=>$gender,
            'sel_Ethnicity'=>$ethnicity,
            'sel_Media_Source'=>$mediaSource,
            'selnoReturn'=>$noReturn,
            'selMbrType'=>'ai',
        );

        //if (trim($r['PatientLast'] . $r['PatientFirst']) == trim($r['GuestLast'] . $r['GuestFirst'])) { //assume patient is the guest

            $homePhone = $this->formatPhone($r['Phone']);
            $cellPhone = $this->formatPhone($r['Mobile']);
            //$workPhone = $this->formatPhone($r['Work']);

            $post['rbPrefMail'] = '1';
            $post['rbEmPref'] = "1";
            $post['txtEmail'] = array('1'=>$r['Email']);
            $post['rbPhPref'] = ($homePhone != '' ? "dh": ($cellPhone != "" ? "mc" : ""));
            $post['txtPhone'] = array('dh'=>$homePhone, 'mc'=>$cellPhone, 'gw'=>'');

            $adr1 = $this->loadAddress($this->dbh, $r);
            $post['adr'] = $adr1;

            if(trim($r['Street']) == ""){
            $post['incomplete'] = true;
            }

        //}

        $patient = new Patient($this->dbh, '', 0);
        $patient->save($this->dbh, $post, 'admin');


        $hospitalId = (isset($this->hospitals[trim(strtolower($r['Hospital']))]) ? $this->hospitals[trim(strtolower($r['Hospital']))] : 0);
        //$hospitalId = 21;

        // PSG
        $psg = new Psg($this->dbh, 0, $patient->getIdName());
        $psg->setNewMember($patient->getIdName(), RelLinkType::Self);
        $psg->savePSG($this->dbh, $patient->getIdName(), 'admin');

        // Registration
        $reg = new Registration($this->dbh, $psg->getIdPsg());
        $reg->saveRegistrationRs($this->dbh, $psg->getIdPsg(), 'admin');

        // Hospital
        $hospitalStay = null;
        if ($hospitalId > 0) {

            $hospitalStay = new HospitalStay($this->dbh, $patient->getIdName());
            $hospitalStay->setHospitalId($hospitalId);
            $hospitalStay->setIdPsg($psg->getIdPsg());
            if(isset($r["Diagnosis"])){
                $hospitalStay->setDiagnosis($this->findIdDiag($r["Diagnosis"]));
            }
            if(isset($r["MRN"])){
                $hospitalStay->setMrn($r["MRN"]);
            }

            $hospitalStay->save($this->dbh, $psg, 0, 'admin');
        }

        // external id
        $this->dbh->exec("update `name` set `External_Id` = " . $r['importId'] . " where `idName` = " . $patient->getIdName());

        $this->importedPatients++;

        return array("patient"=>$patient, "psg"=>$psg, "reg"=> $reg, "hospStay"=>$hospitalStay);
    }

    private function addGuest(array $r, $psg){

        // get session instance
        $uS = Session::getInstance();

        $newFirst = trim(addslashes($r['GuestFirst']));
        $newLast = trim(addslashes($r['GuestLast']));
        //$newNickname = trim(addslashes($r['GuestNickname']));

        if ($newLast == '') {
            return;
        }

        $id = $this->findPerson($newFirst, $newLast, false);

        $guest = new Guest($this->dbh, '', $id);

        if($id == 0){
            $gender = $this->findIdGender($r['Gender']);
            $ethnicity = $this->findIdEthnicity((isset($r['Ethnicity']) ? $r["Ethnicity"] : ""));
            $noReturn = $this->findIdNoReturn($r["Banned"]);
            $mediaSource = $this->findIdMediaSource($r["mediaSource"]);

            $birthDate = "";
            if(trim($r['BirthDate']) != ''){
                $birthdateDT = new \DateTime($r['BirthDate']);
                $birthDate = $birthdateDT->format("M j, Y");
            }

            // phone
            $homePhone = $this->formatPhone($r['Phone']);
            $cellPhone = $this->formatPhone($r['Mobile']);
            //$workPhone = $this->formatPhone($r['Work']);

            $post = array(
                'txtFirstName' => $newFirst,
                'txtLastName'=>  $newLast,
                'txtNickname'=> "",//$newNickname,
                'rbPrefMail'=>'1',
                'rbEmPref'=>"1",
                'txtEmail'=>array('1'=>$r['Email']),
                'rbPhPref'=>($homePhone != '' ? "dh": ($cellPhone != "" ? "mc" : "")),
                'txtPhone'=>array('dh'=>$homePhone, 'mc'=>$cellPhone),
                'txtBirthDate'=> $birthDate,  //$r['Date_of_Birth'],
                'selStatus'=>'a',
                'sel_Ethnicity'=>$ethnicity,
                'sel_Gender'=>$gender,
                'sel_Media_Source'=>$mediaSource,
                'selnoReturn'=>$noReturn,
                'selMbrType'=>'ai'
            );

            $adr1 = $this->loadAddress($this->dbh, $r);
            $post['adr'] = $adr1;

            if(trim($r['Street']) == ""){
                $post['incomplete'] = true;
            }

            $guest->save($this->dbh, $post, $uS->username);
        }
        $relship = RelLinkType::Relative;
        if (isset($r['Relationship_to_Patient']) && isset($this->relations[$r['Relationship_to_Patient']])) {
            $relship = $this->relations[$r['Relation_to_Patient']];
        }

        if($psg instanceof PSG){
            $psg->setNewMember($guest->getIdName(), $relship);
            $psg->savePSG($this->dbh, $psg->getIdPatient(), $uS->username);
        }
        // external id
        $this->dbh->exec("update `name` set `External_Id` = " . $r['importId'] . " where `idName` = " . $guest->getIdName());

        $this->importedGuests++;

        return $guest;

    }

    private function addVisit(array $r, array $guests, $reg, $hospStay, $resvId, $visitStatus = VisitStatus::CheckedOut){
/*
        //make hospital stay
        if($hospStay == null){
            $idPatient = $this->findPerson($r["PatientFirst"], $r["PatientLast"], "patient");
            $psg = new PSG($this->dbh, $reg->getIdPsg());
            $idHospital = (isset($this->hospitals[trim(strtolower($r['Hospital']))]) ? $this->hospitals[trim(strtolower($r['Hospital']))] : 0);
            $hospStay = new HospitalStay($this->dbh, $idPatient);
            $hospStay->setHospitalId($idHospital);
            $hospStay->save($this->dbh, $psg, 0, 'admin');

        }

        //make reservation
        
        $arrival = (new \DateTime($r['ArrivalDate']))->format("Y-m-d 16:00:00");
        $departure = (new \DateTime($r['DepartureDate']))->format('Y-m-d 10:00:00');
        $stmt = $this->dbh->prepare("insert into reservation (`idRegistration`, `idGuest`,`idHospital_Stay`, `idResource`, `Expected_Arrival`, `Expected_Departure`, `Number_Guests`, `Status`) VALUES(:idRegistration, :idGuest, :idHospitalStay, :idResource, :Arrive, :Departure, :numGuests, :status)");
        $stmt->execute(array(
            ":idRegistration"=>$reg->getIdRegistration(),
            ":idGuest"=>$guests[0]["idName"],
            ":idHospitalStay"=>$hospStay->getIdHospital_Stay(),
            ":idResource"=>$idResource,
            ":Arrive"=>$arrival,
            ":Departure"=>$departure,
            ":numGuests"=>count($guests),
            ":status"=>ReservationStatus::Checkedout
        ));
        $resvId = $this->dbh->lastInsertId();
*/
        if($resvId){
            $idResource = $this->findIdResource($r['RoomNum']);
            $arrival = (new \DateTime($r['ArrivalDate']))->format("Y-m-d 16:00:00");
            $departure = (new \DateTime($r['DepartureDate']))->format('Y-m-d 10:00:00');
/*
            $stmt = $this->dbh->prepare("insert into reservation_guest (`idReservation`, `idGuest`,`Primary_Guest`) VALUES(:idReservation, :idGuest, :primaryGuest)");
            $stmt->execute(array(
                ":idReservation"=>$resvId,
                ":idGuest"=>$guests[0]["idName"],
                ":primaryGuest"=>1
            ));
*/
            //make visit
            $visitRS = new VisitRS();
            $visitRS->idReservation->setNewVal($resvId);
            $visitRS->idRegistration->setNewVal($reg->getIdRegistration());
            $visitRS->idHospital_stay->setNewVal($hospStay->getIdHospital_Stay());
            $visitRS->idPrimaryGuest->setNewVal($guests[0]["idName"]);
            $visitRS->idResource->setNewVal($idResource);
            $visitRS->Arrival_Date->setNewVal($arrival);
            $visitRS->Expected_Departure->setNewVal($departure);
            $visitRS->Actual_Departure->setNewVal($departure);
            $visitRS->Span->setNewVal(0);
            $visitRS->Span_Start->setNewVal($arrival);
            $visitRS->Span_End->setNewVal($departure);
            $visitRS->Status->setNewVal($visitStatus);
            $idVisit = EditRS::insert($this->dbh, $visitRS);

            foreach($guests as $guest){
                //make stay
                $stayRS = new StaysRS();
                $stayRS->idName->setNewVal($guest["idName"]);
                $stayRS->idVisit->setNewVal($idVisit);
                $stayRS->Visit_Span->setNewVal(0);
                $stayRS->idRoom->setNewVal($idResource);
                $stayRS->Checkin_Date->setNewVal($arrival);
                $stayRS->Checkout_Date->setNewVal($departure);
                $stayRS->Span_Start_Date->setNewVal($arrival);
                $stayRS->Span_End_Date->setNewVal($departure);
                $stayRS->Status->setNewVal($visitStatus);
                EditRS::insert($this->dbh, $stayRS);
            }
        }
    }

    private function addVehicle(array $vehicle, Registration $reg){
        $stmt = $this->dbh->prepare("insert into vehicle (`idRegistration`, `Make`,`Model`, `Color`, `State_Reg`, `License_Number`) VALUES(:idReg, :make, :model, :color, :state, :license)");
                $stmt->execute(array(
                    ":idReg" => $reg->getIdRegistration(),
                    ":make" => "",
                    ":model" => substr(trim($vehicle["prop_Vehicle_1___Make___Model"]), 0,45),
                    ":color" => substr(trim($vehicle["prop_Vehicle_1___Color"]), 0, 45),
                    ":state" => "",
                    ":license" => substr(trim($vehicle["prop_Vehicle_1___License_No_"]), 0, 15)
                ));
    }

    private function addReservation(array $guests, Registration $reg, HospitalStay $hospStay, array $r, $resvStatus = ReservationStatus::Checkedout){
        
        $idResource = $this->findIdResource($r['RoomNum']);

        $primaryGuestId = 0;
        foreach($guests as $guest){
            if($guest["PrimaryGuest"] == "Yes"){
                $primaryGuestId = $guest["idName"];
            }
        }

        //make reservation
        $arrival = (new \DateTime($r['ArrivalDate']))->format("Y-m-d 16:00:00");
        $departure = (new \DateTime($r['DepartureDate']))->format('Y-m-d 10:00:00');
        $stmt = $this->dbh->prepare("insert into reservation (`idRegistration`, `idGuest`,`idHospital_Stay`, `idResource`, `Expected_Arrival`, `Expected_Departure`, `Actual_Arrival`, `Actual_Departure`, `Number_Guests`, `Status`) VALUES(:idRegistration, :idGuest, :idHospitalStay, :idResource, :expectedArrival, :expectedDeparture, :actualArrival, :actualDeparture, :numGuests, :status)");
        $stmt->execute(array(
            ":idRegistration"=>$reg->getIdRegistration(),
            ":idGuest"=>($primaryGuestId > 0 ? $primaryGuestId : $guests[0]["idName"]),
            ":idHospitalStay"=>$hospStay->getIdHospital_Stay(),
            ":idResource"=>$idResource,
            ":expectedArrival"=>$arrival,
            ":expectedDeparture"=>$departure,
            ":actualArrival"=>$arrival,
            ":actualDeparture"=>$departure,
            ":numGuests"=>count($guests),
            ":status"=>$resvStatus
        ));
        $resvId = $this->dbh->lastInsertId();

        if ($resvId) {
            foreach($guests as $k=>$guest){
                $stmt = $this->dbh->prepare("insert ignore into reservation_guest (`idReservation`, `idGuest`,`Primary_Guest`) VALUES(:idReservation, :idGuest, :primaryGuest)");
                $stmt->execute(array(
                    ":idReservation" => $resvId,
                    ":idGuest" => $guest["idName"],
                    ":primaryGuest" => ($guest['PrimaryGuest'] == "Yes" ? 1:0)
                ));
            }

            //add Notes
            if($r["Notes"] != ""){
                LinkNote::save($this->dbh, $r["Notes"], $resvId, Note::ResvLink, "", 'admin', true);
            }
        }
        return $resvId;
    }

    /**
     * Create rooms in HHK if they don't already exist
     *
     * @return array
     */
    public function makeMissingRooms(){

        $uploadedRooms = (new ImportMarkup($this->dbh))->getRoomInfo();
        $insertCount = 0;

        try{
            $this->dbh->beginTransaction();
            // Install missing rooms
            foreach($uploadedRooms as $room) {
                if($room["idResource"] == null && $room['RoomNum'] != ''){
                    $title = $room['RoomNum'];

                    // create room record
                    $stmt = $this->dbh->prepare("insert into room (`idHouse`,`Item_Id`,`Title`,`Type`,`Category`,`Status`,`State`,`Availability`, `Max_Occupants`,`Min_Occupants`,`Rate_Code`,`Key_Deposit_Code`,`Cleaning_Cycle_Code`, `idLocation`) VALUES"
                            . " (0, 1, :roomTitle, 'r', 'dh', 'a', 'a', 'a', 4, 0,'rb', 'k0', 'a', 1);");
                    $stmt->execute(array(":roomTitle"=>$title));
                    $idRoom = $this->dbh->lastInsertId();

                    // create resource record
                    $stmt = $this->dbh->prepare("insert into resource (`idResource`,`idSponsor`,`Title`,`Utilization_Category`,`Type`,`Status`) values "
                            . "(:idRoom, 0, :roomTitle, 'uc1', 'room', 'a')");
                    $stmt->execute(array(":idRoom"=>$idRoom, ":roomTitle"=>$title));

                    // Resource-Room
                    $stmt = $this->dbh->prepare("insert into resource_room (`idResource_room`,`idResource`,`idRoom`) values "
                            . "(:idRoom, :idRoom2, :idRoom3)");
                    $stmt->execute(array(":idRoom" => $idRoom,":idRoom2" => $idRoom,":idRoom3" => $idRoom));
                    $insertCount++;
                }
            }
        }catch(\Exception $e){
            if($this->dbh->inTransaction()){
                $this->dbh->rollBack();
            }
            return array("error"=>$e->getMessage());
        }
        $this->dbh->commit();
        return array("success"=>$insertCount . " rooms inserted");
    }

    /**
     * Create Hospitals in HHK if they don't already exist
     *
     * @return array
     */
    public function makeMissingHospitals(){
        $uploadedHospitals = (new ImportMarkup($this->dbh))->getHospitalInfo();
        $insertCount = 0;

        try{
            $this->dbh->beginTransaction();

            foreach($uploadedHospitals as $hospital){
                if($hospital["idHospital"] == null && $hospital["Hospital"] != ''){
                    $stmt = $this->dbh->prepare("insert into `hospital` (`Title`, `Type`, `Status`) values (:title, 'h','a');");
                    $stmt->execute(array(":title"=>$hospital["Hospital"]));
                    $insertCount++;
                }
            }
            $this->dbh->commit();
        }catch (\Exception $e){
            if($this->dbh->inTransaction()){
                $this->dbh->rollBack();
            }
            return array("error"=>$e->getMessage());
        }

        return array("success"=>$insertCount . " hospitals inserted");
    }

    /**
     * Create Diagnoses in HHK if they don't already exist
     *
     * @return array
     */
    public function makeMissingDiags(){
        $uploadedDiags = (new ImportMarkup($this->dbh))->getDiagInfo();
        $insertCount = 0;

        try{

            foreach($uploadedDiags as $diag){
                if($diag["idDiagnosis"] == null && $diag["Diagnosis"] != ''){
                    //insert new diag
                    $newCode = 'g' . incCounter($this->dbh, 'codes');

                    $glRs = new GenLookupsRS();
                    $glRs->Table_Name->setNewVal("Diagnosis");
                    $glRs->Code->setNewVal($newCode);
                    $glRs->Description->setNewVal($diag["Diagnosis"]);
                    $glRs->Type->setNewVal('h');
                    $glRs->Substitute->setNewVal('');
                    $glRs->Order->setNewVal(0);

                    EditRS::insert($this->dbh, $glRs);
                    $insertCount++;
                }
            }
        }catch (\Exception $e){
            return array("error"=>$e->getMessage());
        }

        return array("success"=>$insertCount . " diagnoses inserted");
    }

    /**
     * Create Genders in HHK if they don't already exist
     *
     * @return array
     */
    public function makeMissingGenders(){
        $uploadedDiags = (new ImportMarkup($this->dbh))->getGenderInfo();
        $insertCount = 0;

        try{

            foreach($uploadedDiags as $diag){
                if($diag["idGender"] == null && $diag["Gender"] != ''){
                    //insert new gender
                    $newCode = 'g' . incCounter($this->dbh, 'codes');

                    $glRs = new GenLookupsRS();
                    $glRs->Table_Name->setNewVal("Gender");
                    $glRs->Code->setNewVal($newCode);
                    $glRs->Description->setNewVal($diag["Gender"]);
                    $glRs->Type->setNewVal('h');
                    $glRs->Substitute->setNewVal('');
                    $glRs->Order->setNewVal(0);

                    EditRS::insert($this->dbh, $glRs);
                    $insertCount++;
                }
            }
        }catch (\Exception $e){
            return array("error"=>$e->getMessage());
        }

        return array("success"=>$insertCount . " genders inserted");
    }

    /**
     * Create Ethnicities in HHK if they don't already exist
     *
     * @return array
     */
    public function makeMissingEthnicities(){
        $uploadedEthnicities = (new ImportMarkup($this->dbh))->getEthnicityInfo();
        $insertCount = 0;

        try{

            foreach($uploadedEthnicities as $eth){
                if($eth["idEthnicity"] == null && $eth["Ethnicity"] != ''){
                    //insert new ethnicity
                    $newCode = 'g' . incCounter($this->dbh, 'codes');

                    $glRs = new GenLookupsRS();
                    $glRs->Table_Name->setNewVal("Ethnicity");
                    $glRs->Code->setNewVal($newCode);
                    $glRs->Description->setNewVal($eth["Ethnicity"]);
                    $glRs->Type->setNewVal('h');
                    $glRs->Substitute->setNewVal('');
                    $glRs->Order->setNewVal(0);

                    EditRS::insert($this->dbh, $glRs);
                    $insertCount++;
                }
            }
        }catch (\Exception $e){
            return array("error"=>$e->getMessage());
        }

        return array("success"=>$insertCount . " ethnicities inserted");
    }


    /**
     * Undo an import - Sets member status to 'tbd' and importTbl.imported = false
     * You must go into Misc->Delete Member Records to finish the undo process.
     *
     * @return array
     */
    public function undoImport(){
        try{
            $this->dbh->beginTransaction();
            $this->dbh->exec("update `name` set `Member_Status` = 'tbd' where `External_Id` != ''");
            $this->dbh->exec("update `" . Upload::TBL_NAME . "` set `imported` = null");
        }catch(\Exception $e){
            if($this->dbh->inTransaction()){
                $this->dbh->rollBack();
            }
            return array("error"=>$e->getMessage());
        }
        $this->dbh->commit();
        return array("success"=>"Imported people have been set for 'To Be Deleted', use the 'Delete Member Records' function to delete them.");
    }

    /**
     * Search for a member record depending on name/phone/email/member type (guest/patient)
     *
     * @param string $first
     * @param string $last
     * @param string $memberType - "guest", "patient" "doctor" or ""
     * @param bool $limit - limit to 1 record or multiple
     * @param string $phone
     * @param string $email
     * @return number|array
     */
    private function findPerson(string $first, string $last, string $memberType, bool $limit = true, string $phone = '', string $email = ''){

        $newFirst = trim(htmlentities($first, ));
        $newLast = trim(htmlentities($last));
        $phone = ($phone !='' ? $this->formatPhone($phone) : null);
        $email = ($email !='' ? trim($email) : null);


        if(in_array($memberType, ["guest", "patient"])){
            $query = "Select n.idName, ng.idPsg, ng.Relationship_Code from name n join name_guest ng on n.idName = ng.idName where n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "'";
            if($memberType == "guest"){
                $query .= " and ng.Relationship_Code != 'slf'";
            }else if($memberType == "patient"){
                $query .= " and ng.Relationship_Code = 'slf'";
            }
        }else if($memberType == VolMemberType::Doctor){
            $query = "SELECT distinct n.idName, n.Name_Last, n.Name_First
FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::Doctor . "'
WHERE n.Name_First = '" . $newFirst . "' AND n.Name_Last = '" . $newLast . "'";
        }else{
            return 0;
        }
        

        if($limit){
            $query .= " limit 1";
        }

        $stmtg = $this->dbh->query($query);
        $rowCount = $stmtg->rowCount();
        $rowgs = $stmtg->fetchAll(\PDO::FETCH_NUM);

        if ($rowCount == 0) {
            $id = 0;
        } else if($rowCount == 1) {
            $id = $rowgs[0][0];
        } else {
            $id = $rowgs;
        }
        return $id;
    }

    public function makeMissingDoctors(){
        $uS = Session::getInstance();
        $insertCount = 0;

        $uploadedDocs = (new ImportMarkup($this->dbh))->getDoctorInfo();

        foreach ($uploadedDocs as $doc) {
            $docId = $this->findPerson($doc["docFirst"], $doc["docLast"], VolMemberType::Doctor);

            if (!$docId > 0) {
                $hhkDoc = new Doctor($this->dbh, 'd_', 0);
                $docFields = [
                    'd_txtFirstName' => $doc["docFirst"],
                    'd_txtLastName' => $doc["docLast"]
                ];


                $hhkDoc->save($this->dbh, $docFields, $uS->username);
                $insertCount++;
            }
        }
        return array("success"=>$insertCount . " doctors inserted");
    }

    private function formatPhone($phone){
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $phone);
    }

    private function loadAddress(\PDO $dbh, $r) {

        $state = ucfirst(trim($r['State']));
        $city = ucwords(trim($r['City']));
        $county = (isset($r['County']) ? ucfirst($r['County']) : '');
        $country = 'US';
        $zip = $r['ZipCode'];

        if (strlen($zip) > 4) {

            $searchZip = substr($zip, 0, 5);

            if (isset($this->zipLookups[$searchZip]) === FALSE) {

                $stmtz = $dbh->query("Select City, State, County from postal_codes where Zip_Code = '$searchZip'");
                $rows = $stmtz->fetchAll(\PDO::FETCH_ASSOC);

                if (count($rows) == 1) {
                    $this->zipLookups[$searchZip] = $rows[0];
                }
            }

            if (isset($this->zipLookups[$searchZip])) {

                $state = $this->zipLookups[$searchZip]['State'];
                $city = $this->zipLookups[$searchZip]['City'];
                $county = $this->zipLookups[$searchZip]['County'];

            }
        }


        $adr1 = array('1' => array(
            'address1' => ucwords(strtolower(trim($r['Street']))),
            'address2' => '',
            'city' => $city,
            'county'=>  $county,
            'state' => $state,
            'country' => $country,
            'zip' => $zip));

        return $adr1;
    }

    private function getHospitals(){
        $stmt = $this->dbh->query("Select idHospital, Title from hospital");
        while ($h = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->hospitals[strtolower($h['Title'])] = $h['idHospital'];
        }
    }

    private function getRelations(){
        foreach(readGenLookupsPDO($this->dbh, 'Patient_Rel_Type') as $r) {
            $this->relations[strtolower($r[1])] = $r[0];
        }
    }

    private function getDiags(){
        foreach(readGenLookupsPDO($this->dbh, 'Diagnosis') as $r) {
            $this->diags[strtolower($r[1])] = $r[0];
        }
    }

    private function getGenders(){
        foreach(readGenLookupsPDO($this->dbh, 'Gender') as $r) {
            $this->genders[strtolower($r[1])] = $r[0];
        }
    }

    private function getEthnicities(){
        foreach(readGenLookupsPDO($this->dbh, 'Ethnicity') as $r) {
            $this->ethnicities[strtolower($r[1])] = $r[0];
        }
    }

    private function getNoReturn(){
        foreach(readGenLookupsPDO($this->dbh, 'NoReturnReason') as $r) {
            $this->noReturn[strtolower($r[1])] = $r[0];
        }
    }

    private function getMediaSources(){
        foreach(readGenLookupsPDO($this->dbh, 'Media_Source') as $r) {
            $this->mediaSources[strtolower($r[1])] = $r[0];
        }
    }

    private function getProgress(){
        $query = "Select count(*) as `Remaining`, (select count(*) from `" . Upload::TBL_NAME . "`) as `Total` from `" . Upload::TBL_NAME . "` i where i.imported is null";
        $stmt = $this->dbh->query($query);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $imported = ($row["Total"] - $row["Remaining"]);
        $progress = round($imported/$row["Total"]*100);
        return array("imported"=>$imported, "remaining"=>$row["Remaining"], "progress"=>$progress);
    }

    private function getRooms(){
        $stmt = $this->dbh->query("Select idResource, Title from resource");
        while ($h = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->rooms[trim(strtolower($h['Title']))] = $h['idResource'];
        }
    }

    private function findIdDiag(string $diag){
        return (isset($this->diags[trim(strtolower($diag))]) ? $this->diags[trim(strtolower($diag))] : '');
    }

    private function findIdResource(string $roomTitle){
        return (isset($this->rooms[trim(strtolower($roomTitle))]) ? $this->rooms[trim(strtolower($roomTitle))] : 0);
    }

    private function findIdGender(string $gender){
        return (isset($this->genders[trim(strtolower($gender))]) ? $this->genders[trim(strtolower($gender))] : '');
    }

    private function findIdEthnicity(string $ethnicity){
        return (isset($this->ethnicities[trim(strtolower($ethnicity))]) ? $this->ethnicities[trim(strtolower($ethnicity))] : '');
    }

    private function findIdNoReturn(string $noReturn){
        return (isset($this->noReturn[trim(strtolower($noReturn))]) ? $this->noReturn[trim(strtolower($noReturn))] : '');
    }

    private function findIdMediaSource(string $mediaSource){
        return (isset($this->mediaSources[trim(strtolower($mediaSource))]) ? $this->mediaSources[trim(strtolower($mediaSource))] : '');
    }
}