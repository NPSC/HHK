<?php
namespace HHK\Admin\Import;


use HHK\Member\AbstractMember;
use HHK\Member\Role\Doctor;
use HHK\Member\Role\Patient;
use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\sec\WebInit;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\MemBasis;
use HHK\SysConst\MemDesignation;
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
use HHK\Member\Address\Address;
use HHK\Member\Address\Phones;
use HHK\Member\Address\Emails;
use HHK\Volunteer\VolunteerCategory;


class Import {

    protected \PDO $dbh;
    protected array $volLkups;
    protected array $zipLookups;
    protected array $hospitals;
    protected array $rooms;
    protected int $importedPatients;
    protected int $importedGuests;

    /**
     * Mapping of import field to gen lookup table name
     * 
     * @var array //[<import field> => <genLookupTableName>]
     */
    public array $genLookupMapping; //array[<import field>] => <genLookupTableName>
    
    /**
     * Mapping of import field to a single specific HHK field
     * 
     * @var array //[<hhkField> => <import field>]
     */
    public array $fieldMapping;
    
    protected array $genLookups;

    public function __construct(\PDO $dbh){
        $this->dbh = $dbh;
        $wInit = new WebInit();
        $this->volLkups = $wInit->sessionLoadVolLkUps();
        
        //TODO: Customize mappings for each import to facilitate making missing lookups
        $this->genLookupMapping = [
            //"<importfieldname>"=>"genLookupTableName"
            "relationship"=>"Patient_Rel_Type",
            "patientTitle"=>"Name_Prefix"
        ];

        $this->fieldMapping = [
            "hospital"=>"",
            "room"=>"",
            "doctor"=>[
                "first"=>"",
                "last"=>"",
                "full"=>""
            ]
        ];

        $this->loadGenLookups();
        //$this->getHospitals();
        //$this->getRooms();
    }

    /**
     * Main import method, customize for each import
     * @param int $limit
     * @param bool $people
     * @param bool $visits
     * @return array{batch: int, guests: int, patients: int, progress: array{imported: float|int, progress: float, remaining: mixed, success: bool, workerId: string}|array{ImportId: mixed, error: string, trace: string}}
     */
    public function startImport(int $limit = 100, bool $people = true, bool $visits = false){
        $uS = Session::getInstance();
        $workerId = bin2hex(random_bytes(16)); //generate random 32 char string for workerId
        ini_set('max_execution_time', '300');

        


        $batchquery = "UPDATE `". Upload::TBL_NAME."` set status = 'processing', workerId = '$workerId' where status = 'pending' order by `importId` LIMIT $limit;";
        $this->dbh->exec($batchquery);
        
        //$query = "Select * from `" . Upload::TBL_NAME . "` i where i.imported is null and status = 'pending' group by i.importId order by i.`patientID` LIMIT $limit;";
        $query = "Select * from `" . Upload::TBL_NAME . "` i where i.imported is null and i.status = 'processing' and i.workerId = '$workerId' group by i.importId order by i.`importId`";
        $stmt = $this->dbh->query($query);

        $numRead = $stmt->rowCount();
        $psg = null;
        $patient = null;
        $reg = null;
        $hospStay = null;
        $this->importedPatients = 0;
        $this->importedGuests = 0;
        $idGuest = 0;

        $memId = '';

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            //TODO: Customize for each import

            /*
            if($memId == $r["MembershipId"]){ //skip duplicates
                $this->dbh->exec("update `" . Upload::TBL_NAME . "` set `imported` = '1', status = 'done' where `importId` = " . $r['importId']);
                continue;
            }
            */

            //$guests = [];
            try{
                $this->dbh->beginTransaction();

                //donors
                if(trim($r["Company"]) != ""){
                    $donor = $this->addDonor($r, MemBasis::Company);
                }else{
                    $donor = $this->addDonor($r, MemBasis::Indivual);
                }

               //map gender
               /*$gender = "";
               switch(strtolower($r["Gender"])){
                    case "m":
                        $gender = "male";
                        break;
                    case "f":
                        $gender = "female";
                        break;
                    default:
                        $gender = "";
               }

               //set up guest
                $guest = [
                    "FirstName" => $r['FirstName'],
                    "Middle" => "",
                    "LastName" => $r['LastName'],
                    "Email" =>$r["EmailAddress"],
                    "Phone" =>$r["Phone"],
                    "Address" => $r["Address"],
                    "Address2" => "",
                    "City" => $r["City"],
                    "County" => "",
                    "State" => $r["State"],
                    "ZipCode"=>$r["ZipCode"],
                    "Hospital"=>"",
                    "Gender"=>$r["Gender"],
                    "importId" => $r["importId"],
                ];

                $this->addGuest($guest);
*/
                //if($r["patientID"] == $r["guestID"] || strtolower($r["relationship"]) == "patient"){
                    //include extra info
                
                    //"BirthDate" => "",
                    //"Gender" => $r["PatientGender"],
                    //"mediaSource" => $r["PatientMarketingOptIn"],
                    //$patient["Address"] = $r["address1"];
                    //$patient["Address2"] = $r["address2"];
                    //$patient["City"] = $r["city"];
                    //$patient["County"] = "";
                    //$patient["State"] = $r["state"];
                    //$patient["ZipCode"] = $r["zipCode"];
                    //$patient["Phone"] = $r["homeNumber"];
                    //$patient["Mobile"] = $r["cellNumber"];
                    //$patient["Email"] = $r["eMail"];
                    //"Diagnosis"=>"",
                    //"PrimaryGuest" => $r["PatientPrimaryGuest"],
                    //"Banned" => $r["PatientBanned"],
                    //"Hospital" =>$r["Hospital"],
                    
                //}
/*
                $patArray = $this->addPatient($patient, false);

                if(!($r["patientID"] == $r["guestID"] || strtolower($r["relationship"]) == "patient")){
                    $guest = [
                            "FirstName" => $r['guestFirstName'],
                            "Middle" => $r['guestMiddleName'],
                            "LastName" => $r['guestLastName'],
                            "Relationship_to_Patient" => strtolower($r["relationship"]),
                            //"BirthDate" => "",
                            //"Gender" => $r["Guest_".$i."_Gender"],
                            //"mediaSource" => $r["Guest_".$i."_Marketing_opt_in"],
                            "Address" => $r["address1"],
                            "Address2" => $r["address2"],
                            "City" => $r["city"],
                            "County" => "",
                            "State" => $r["state"],
                            "ZipCode" => $r["zipCode"],
                            "Phone" => $r["homeNumber"],
                            "Mobile" => $r["cellNumber"],
                            "Email" => $r["eMail"],
                            //"Diagnosis"=>"",
                            //"PrimaryGuest"=>$r["Guest_".$i."_Primary_Guest"],
                            //"Banned"=>$r["Guest_".$i."_Banned"],
                            //"Hospital" =>$r["Hospital"],
                            "importId"=>$r["importId"]
                    ];
                    $this->addGuest($guest, $patArray['psg']);
                }

                //add notes
                $noteText = trim(filter_var($r["Notes"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                if($noteText !== ""){
                    LinkNote::save($this->dbh, $noteText, $patArray['psg']->getIdPsg(), Note::PsgLink, "", $uS->username, $uS->ConcatVisitNotes);
                }
*/
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
                /*foreach($guests as $k=>$guest){
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
                    
                }*/

                // mark imported
                $this->dbh->exec("update `" . Upload::TBL_NAME . "` set `imported` = '1', status = 'done' where `importId` = " . $r['importId']);

                $this->dbh->commit();

                

            }catch(\Exception $e){
                if($this->dbh->inTransaction()){
                    $this->dbh->rollBack();
                }

                $this->dbh->exec("update `" . Upload::TBL_NAME . "` set status = 'pending', workerId = null where `importId` = " . $r['importId']);

                return array("error"=>$e->getMessage(), "ImportId"=>$r['importId'], "trace"=>$e->getTraceAsString());
            }
        }

        return array('success'=>true, 'batch'=>$numRead, 'workerId'=>$workerId, 'patients'=>$this->importedPatients, 'guests'=>$this->importedGuests, "progress"=>$this->getProgress());

    }

    private function addPatient(array $r, bool $update = true){

        // New Patient
        $newPatFirst = trim(addslashes($r['FirstName']));
        $newPatMiddle = trim(addslashes($r['Middle']));
        $newPatLast = trim(addslashes($r['LastName']));
        //$newPatNickname = trim(addslashes($r['PatientNickname']));
        //$gender = $this->findIdGender($r['Gender']);
        //$ethnicity = $this->findIdEthnicity((isset($r['Ethnicity']) ? $r["Ethnicity"] : ""));
        //$noReturn = $this->findIdNoReturn($r["Banned"]);
        //$mediaSource = $this->findIdMediaSource($r["mediaSource"]);

        $birthDate = "";
        //if(trim($r['BirthDate']) != ''){
        //    $birthdateDT = new \DateTime($r['BirthDate']);
        //    $birthDate = $birthdateDT->format("M j, Y");
        //}


        $id = $this->findPerson($newPatFirst, $newPatLast, "patient", true, $r["Phone"]);

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
                    $hospitalStay->setDiagnosis($this->findIdGenLookup("diagnosis", $r["Diagnosis"]));
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
            //'sel_Gender'=>$gender,
            //'sel_Ethnicity'=>$ethnicity,
            //'sel_Media_Source'=>$mediaSource,
            //'selnoReturn'=>$noReturn,
            'selMbrType'=>'ai',
        );

        //if (trim($r['PatientLast'] . $r['PatientFirst']) == trim($r['GuestLast'] . $r['GuestFirst'])) { //assume patient is the guest

            $homePhone = (isset($r['Phone']) ? $this->formatPhone($r['Phone']):'');
            $cellPhone = (isset($r['Mobile']) ? $this->formatPhone($r['Mobile']):'');
            //$workPhone = $this->formatPhone($r['Work']);

            $post['rbPrefMail'] = '1';
            $post['rbEmPref'] = "1";
            $post['txtEmail'] = array('1'=>$r['Email']);
            $post['rbPhPref'] = ($homePhone != '' ? "dh": ($cellPhone != "" ? "mc" : ""));
            $post['txtPhone'] = array('dh'=>$homePhone, 'mc'=>$cellPhone, 'gw'=>'');

            $adr1 = $this->loadAddress($this->dbh, $r);
            $post['adr'] = $adr1;

            if(trim($r['Address']) == ""){
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
                $hospitalStay->setDiagnosis($this->findIdGenLookup("diagnosis", $r["Diagnosis"]));
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

    /**
     * Search for person and or create them. if PSG is given, add the guest to the PSG.
     * @param array $r ["firstName", "LastName", "Middle", "Gender", "Ethnicity", "BirthDate", "Banned", "mediaSource", "Relationship_to_Patient", "Address", "Address2, "City", "County", "State", "ZipCode", "Phone", "Mobile", "Email"]
     * @param mixed $psg
     * @return Guest|bool
     */
    private function addGuest(array $r, PSG|bool $psg = false){

        // get session instance
        $uS = Session::getInstance();

        $newFirst = isset($r["FirstName"]) ? trim(addslashes($r['FirstName'])) : "";
        $newLast = isset($r["LastName"]) ? trim(addslashes($r['LastName'])) : "";
        $newMiddle = isset($r["Middle"]) ? trim(addslashes(string: $r['Middle'])) : "";
        //$newNickname = trim(addslashes($r['GuestNickname']));

        if ($newLast == '') {
            return false;
        }

        $id = $this->findPerson($newFirst, $newLast, "guest", true, $r["Phone"]);

        $guest = new Guest($this->dbh, '', $id);

        if($id == 0){
            $gender = $this->findIdGenLookup("Gender", (isset($r['Gender']) ? $r["Gender"] : ""));
            $ethnicity = $this->findIdGenLookup("Ethnicity", (isset($r['Ethnicity']) ? $r["Ethnicity"] : ""));
            $noReturn =  $this->findIdGenLookup("No_Return", (isset($r["Banned"]) ? $r["Banned"] : ""));
            $mediaSource = $this->findIdGenLookup("Media_Source", (isset($r["mediaSource"]) ? $r["mediaSource"] : ""));

            $birthDate = "";
            if(isset($r["BirthDate"]) && trim($r['BirthDate']) != ''){
                $birthdateDT = new \DateTime($r['BirthDate']);
                $birthDate = $birthdateDT->format("M j, Y");
            }

            // phone
            $homePhone = isset($r['Phone']) ? $this->formatPhone($r['Phone']) : "";
            $cellPhone = isset($r['Mobile']) ? $this->formatPhone($r['Mobile']) : "";
            //$workPhone = $this->formatPhone($r['Work']);

            $post = array(
                'txtFirstName' => $newFirst,
                'txtLastName'=>  $newLast,
                'txtNickname'=> "",//$newNickname,
                'txtMiddleName'=> $newMiddle,
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

            if(trim($r['Address']) == ""){
                //$post['incomplete'] = true;
            }

            $guest->save($this->dbh, $post, $uS->username);
        }
        $relship = RelLinkType::Relative;
        if (isset($r['Relationship_to_Patient'])) {
            $relship = $this->findIdGenLookup("Patient_Rel_Type", $r['Relationship_to_Patient']);
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

    /**
     * Summary of addDonor
     * @param array $r
     * @param mixed $memType
     * @return bool|\HHK\Member\IndivMember|\HHK\Member\OrgMember|null
     */
    private function addDonor(array $r, $memType){
        // get session instance
        $uS = Session::getInstance();

        $newFirst = isset($r["FirstName"]) ? trim(addslashes($r['FirstName'])) : "";
        $newLast = isset($r["LastName"]) ? trim(addslashes($r['LastName'])) : "";
        $newMiddle = isset($r["MiddleName"]) ? trim(addslashes(string: $r['MiddleName'])) : "";
        //$newNickname = trim(addslashes($r['GuestNickname']));
        $company = isset($r["Company"]) ? trim(addslashes(string: $r['Company'])) : "";
        $notes = isset($r['NOTES']) ? trim(addslashes($r["NOTES"])) : "";

        if ($newLast == '' && $company == '') {
            return false;
        }

        //$id = $this->findPerson($newFirst, $newLast, VolMemberType::Donor, true, $r["Phone"]);
        $id = 0;

        // Instantiate the member object
        try {
            $name = AbstractMember::GetDesignatedMember($this->dbh, $id, $memType);
        } catch (\Exception $ex) {
            $id = 0;
            $name = AbstractMember::GetDesignatedMember($this->dbh, $id, $memType);
        }


        // the rest
        try {

            $address = new Address($this->dbh, $name, $uS->nameLookups[GLTableNames::AddrPurpose]);
            $phones = new Phones($this->dbh, $name, $uS->nameLookups[GLTableNames::PhonePurpose]);
            $emails = new Emails($this->dbh, $name, $uS->nameLookups[GLTableNames::EmailPurpose]);

            $rel = $name->loadRealtionships($this->dbh);

            // Volunteers
            $vols = array();
            if(isset($this->volLkups["Vol_Category"]["Vol_Type"]) && ($memType != MemBasis::Indivual || $name->getMemberDesignation() != MemDesignation::Individual)){ //orgs only need member type vol_category
                $purpose = $this->volLkups["Vol_Category"]["Vol_Type"];
                $volunteer = new VolunteerCategory($purpose[0], $purpose[1], $purpose[2]);
                $volunteer->set_rankOptions($this->volLkups["Vol_Rank"]);
                $vols[$purpose[0]] = $volunteer;
            }else{
                foreach ($this->volLkups["Vol_Category"] as $purpose) {
                    $volunteer = new VolunteerCategory($purpose[0], $purpose[1], $purpose[2]);
                    $volunteer->set_rankOptions($this->volLkups["Vol_Rank"]);
                    $vols[$purpose[0]] = $volunteer;
                }
            }


        } catch (\Exception $ex) {
            exit("Error opening supporting objects: " . $ex->getMessage());
        }




        try {

            if($id == 0){
                $gender = $this->findIdGenLookup("Gender", (isset($r['Gender']) ? $r["Gender"] : ""));
                $ethnicity = $this->findIdGenLookup("Ethnicity", (isset($r['Ethnicity']) ? $r["Ethnicity"] : ""));
                $noReturn =  $this->findIdGenLookup("No_Return", (isset($r["Banned"]) ? $r["Banned"] : ""));
                $mediaSource = $this->findIdGenLookup("Media_Source", (isset($r["mediaSource"]) ? $r["mediaSource"] : ""));

                $birthDate = "";
                if(isset($r["BirthDate"]) && trim($r['BirthDate']) != ''){
                    $birthdateDT = new \DateTime($r['BirthDate']);
                    $birthDate = $birthdateDT->format("M j, Y");
                }

                if($memType == MemBasis::Company){
                    $adrPurpose = "4";
                }else{
                    $adrPurpose = "1";
                }

                // phone
                $homePhone = isset($r['Phone']) ? $this->formatPhone($r['Phone']) : "";
                $cellPhone = isset($r['Mobile']) ? $this->formatPhone($r['Mobile']) : "";
                //$workPhone = $this->formatPhone($r['Work']);

                $post = array(
                    'txtFirstName' => $newFirst,
                    'txtLastName'=>  $newLast,
                    'txtNickname'=> "",//$newNickname,
                    'txtMiddleName'=> $newMiddle,
                    'txtCoName'=>$company,
                    'rbPrefMail'=>$adrPurpose,
                    'rbEmPref'=>"1",
                    'txtEmail'=>array('1'=>''),
                    'rbPhPref'=>($homePhone != '' ? "dh": ($cellPhone != "" ? "mc" : "")),
                    'txtPhone'=>array('dh'=>$homePhone, 'mc'=>$cellPhone),
                    'txtBirthDate'=> $birthDate,  //$r['Date_of_Birth'],
                    'selStatus'=>'a',
                    'sel_Ethnicity'=>$ethnicity,
                    'sel_Gender'=>$gender,
                    'sel_Media_Source'=>$mediaSource,
                    'selnoReturn'=>$noReturn,
                    'selMbrType'=>$memType,
                    'Vol_Type'=>[
                        'cb'=>['d'=>'on'],
                        'rank'=>['d'=>'m'],
                        'tdid'=>['d'=>''],
                        'tnid'=>['d'=>''],
                        'ckdate'=>['d'=>''],
                        'notes'=>['d'=>'']
                    ]
                );

                $adr1 = $this->loadAddress($this->dbh, $r, $adrPurpose);
                $post['adr'] = $adr1;

                if(trim($r['Address']) == ""){
                    //$post['incomplete'] = true;
                }


                // Name
                $name->saveChanges($this->dbh, $post);
                $id = $name->get_idName();

                // Address
                $address->savePost($this->dbh, $post, $uS->username);

                // Phone number
                $phones->savePost($this->dbh, $post, $uS->username);

                // Email Address
                $emails->savePost($this->dbh, $post, $uS->username);


                // Volunteers
                foreach ($vols as $v) {
                    if(isset($post[$v->getCategoryCode()])){
                        $v->saveVolCategory($this->dbh, $id, $post[$v->getCategoryCode()], $uS->username);
                    }
                }

                if(strlen($notes) > 0){
                    LinkNote::save($this->dbh, $notes, $id, Note::MemberLink, '', $uS->username);
                }

                // external id
                $this->dbh->exec("update `name` set `External_Id` = " . $r['importId'] . " where `idName` = " . $name->get_idName());

                $this->importedGuests++;

                return $name;
            }


        } catch (\Exception $ex) {

        }


        
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
        }else if(in_array($memberType, [VolMemberType::Doctor, VolMemberType::Donor])){
            $query = "SELECT distinct n.idName, n.Name_Last, n.Name_First
FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . $memberType . "'
WHERE n.Name_First = '" . $newFirst . "' AND n.Name_Last = '" . $newLast . "'";
        }else{
            $query = "Select n.idName from name n where n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "'";
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

    

    /**
     * Trim and format phone as (###) ###-####
     * @param string $phone
     * @return array|string|null
     */
    private function formatPhone(string $phone){
        $phone = preg_replace('[^0-9]', '', $phone);//throw out any non numeric characters
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $phone); //format remaining numbers
    }

    private function loadAddress(\PDO $dbh, $r, $purpose = 1) {

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


        $adr1 = array($purpose => array(
            'address1' => isset($r['Address']) ? ucwords(strtolower(trim($r['Address']))) : '',
            'address2' => isset($r['Address2']) ? ucwords(strtolower(trim($r['Address2']))) : '',
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

    private function getRooms(){
        $stmt = $this->dbh->query("Select idResource, Title from resource");
        while ($h = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->rooms[trim(strtolower($h['Title']))] = $h['idResource'];
        }
    }

    private function findIdResource(string $roomTitle){
        return (isset($this->rooms[trim(strtolower($roomTitle))]) ? $this->rooms[trim(strtolower($roomTitle))] : 0);
    }

    /**
     * Find the Gen lookup ID based on given value, return empty string if not found
     * @param string $importFieldName
     * @param string $importFieldValue
     * @return int|string
     */
    private function findIdGenLookup(string $genLookupTableName, string $importFieldValue):int|string
    {
        if(isset($this->genLookups[$genLookupTableName])){
            return (isset($this->genLookups[$genLookupTableName][trim(strtolower($importFieldValue))]) ? $this->genLookups[$genLookupTableName][trim(strtolower($importFieldValue))] : '');
        }
        return '';
    }

    /**
     * Load required gen lookups based on contents of genLookupMapping
     * @return void
     */
    private function loadGenLookups(){
        foreach($this->genLookupMapping as $fieldName=>$genlookupTableName){
            if(!isset($this->genLookups[$genlookupTableName])){
                $this->genLookups[$genlookupTableName] = [];
                foreach(readGenLookupsPDO($this->dbh, $genlookupTableName) as $r) {
                    $this->genLookups[$genlookupTableName][strtolower($r[1])] = $r[0];
                }
            }
        }
    }

    /**
     * Create genLookups in HHK if they don't already exist
     *
     * @return array
     */
    public function makeMissingGenLookups(string $importFieldName){
        if(isset($this->genLookupMapping[$importFieldName])){
            $uploadedGenLookups = (new ImportMarkup($this->dbh))->getGenLookupInfo($this->genLookupMapping[$importFieldName], $importFieldName);
        }
        $insertCount = 0;

        try{

            foreach($uploadedGenLookups as $genLookup){
                if($genLookup["id"] == null && $genLookup["Import Name"] != ''){
                    //insert new ethnicity
                    $newCode = 'g' . incCounter($this->dbh, 'codes');

                    $glRs = new GenLookupsRS();
                    $glRs->Table_Name->setNewVal($this->genLookupMapping[$importFieldName]);
                    $glRs->Code->setNewVal($newCode);
                    $glRs->Description->setNewVal($genLookup["Import Name"]);
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

        return array("success"=>$insertCount . " " .$this->genLookupMapping[$importFieldName] . "s inserted");
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
     * Create Doctors in HHK if they don't already exist
     * 
     * @return array{success: string}
     */
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

    private function getProgress(){
        $query = "Select count(*) as `Remaining`, (select count(*) from `" . Upload::TBL_NAME . "`) as `Total` from `" . Upload::TBL_NAME . "` i where i.imported is null";
        $stmt = $this->dbh->query($query);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $imported = ($row["Total"] - $row["Remaining"]);
        $progress = round($imported/$row["Total"]*100);
        return array("imported"=>$imported, "remaining"=>$row["Remaining"], "progress"=>$progress);
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
}