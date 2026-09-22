<?php
namespace HHK\Admin\Import;

use HHK\Member\Role\Patient;
use HHK\sec\WebInit;
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
use HHK\Tables\Visit\StaysRS;
use HHK\Tables\GenLookupsRS;
use HHK\Common;

/**
 * Functionality shared by all importers: person/patient/guest creation, PSG and registration set up,
 * reservation and visit inserts, address/phone formatting and lookups against HHK gen lookups, rooms and hospitals.
 *
 * Source specific importers (CSV, Cloudbeds, ...) extend this class and implement ImportInterface.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
abstract class AbstractImport {

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
    }

    /**
     * The external id to store on the imported person. Defaults to the CSV import row id.
     *
     * @param array $r
     * @return string
     */
    protected function getExternalId(array $r): string {
        return (string) ($r['externalId'] ?? $r['importId'] ?? '');
    }

    protected function setExternalId(int|string $idName, string $externalId): void {
        $stmt = $this->dbh->prepare("update `name` set `External_Id` = :externalId where `idName` = :idName");
        $stmt->execute([":externalId"=>$externalId, ":idName"=>$idName]);
    }

    /**
     * Prepare a name from the import row for saving. Importers whose source values are clean (not CSV text)
     * override this to skip the slashes.
     *
     * @param string $name
     * @return string
     */
    protected function cleanName(string $name): string {
        return trim(addslashes($name));
    }

    /**
     * Find a person previously imported using the given external id
     *
     * @param string $externalId
     * @return int idName or 0 if not found
     */
    protected function findPersonByExternalId(string $externalId): int {
        if ($externalId === '') {
            return 0;
        }

        $stmt = $this->dbh->prepare("select `idName` from `name` where `External_Id` = :externalId and `Member_Status` != 'tbd' order by `idName` limit 1");
        $stmt->execute([":externalId"=>$externalId]);
        $idName = $stmt->fetchColumn();

        return $idName === false ? 0 : (int) $idName;
    }

    /**
     * Decide whether the person described by the import row already exists in HHK.
     * The default matches on name (and member type); importers with a stable source id override this.
     *
     * @param array $r import row
     * @param string $memberType "guest" or "patient"
     * @param string $first sanitized first name
     * @param string $last sanitized last name
     * @return int|string|array
     */
    protected function findExistingPersonId(array $r, string $memberType, string $first, string $last){
        return $this->findPerson($first, $last, $memberType, true, $r["Phone"]);
    }

    protected function addPatient(array $r, bool $update = true){

        // New Patient
        $newPatFirst = $this->cleanName($r['FirstName']);
        $newPatMiddle = $this->cleanName($r['Middle']);
        $newPatLast = $this->cleanName($r['LastName']);
        //$newPatNickname = trim(addslashes($r['PatientNickname']));
        //$gender = $this->findIdGender($r['Gender']);
        //$ethnicity = $this->findIdEthnicity((isset($r['Ethnicity']) ? $r["Ethnicity"] : ""));
        //$noReturn = $this->findIdNoReturn($r["Banned"]);
        //$mediaSource = $this->findIdMediaSource($r["mediaSource"]);

        $birthDate = "";
        if(isset($r['BirthDate']) && trim($r['BirthDate']) != ''){
            try{
                $birthDate = (new \DateTime($r['BirthDate']))->format("M j, Y");
            }catch(\Exception $e){
                $birthDate = "";
            }
        }


        $id = $this->findExistingPersonId($r, "patient", $newPatFirst, $newPatLast);

        if($id > 0){
            $patient = new Patient($this->dbh, '', $id);
            $psg = new Psg($this->dbh, 0, $patient->getIdName());

            if ($psg->getIdPsg() == 0) {
                // person exists (e.g. imported earlier as a guest) but is not the patient of any PSG yet
                $psg->setNewMember($patient->getIdName(), RelLinkType::Self);
                $psg->savePSG($this->dbh, $patient->getIdName(), 'admin');

                $reg = new Registration($this->dbh, $psg->getIdPsg());
                $reg->saveRegistrationRs($this->dbh, $psg->getIdPsg(), 'admin');
            } else {
                $reg = new Registration($this->dbh, $psg->getIdPsg());
            }

            $hospitalId = (isset($this->hospitals[trim(strtolower($r['Hospital']))]) ? $this->hospitals[trim(strtolower($r['Hospital']))] : 0);

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

        if(isset($r['Gender'])){
            $post['sel_Gender'] = $this->findIdGenLookup("Gender", $r['Gender']);
        }

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
        $this->setExternalId($patient->getIdName(), $this->getExternalId($r));

        $this->importedPatients++;

        return array("patient"=>$patient, "psg"=>$psg, "reg"=> $reg, "hospStay"=>$hospitalStay);
    }

    /**
     * Search for person and or create them. if PSG is given, add the guest to the PSG.
     * @param array $r ["firstName", "LastName", "Middle", "Gender", "Ethnicity", "BirthDate", "Banned", "mediaSource", "Relationship_to_Patient", "Address", "Address2, "City", "County", "State", "ZipCode", "Country", "Phone", "Mobile", "Email"]
     * @param mixed $psg
     * @return Guest|bool
     */
    protected function addGuest(array $r, PSG|bool $psg = false){

        // get session instance
        $uS = Session::getInstance();

        $newFirst = isset($r["FirstName"]) ? $this->cleanName($r['FirstName']) : "";
        $newLast = isset($r["LastName"]) ? $this->cleanName($r['LastName']) : "";
        $newMiddle = isset($r["Middle"]) ? $this->cleanName($r['Middle']) : "";
        //$newNickname = trim(addslashes($r['GuestNickname']));

        if ($newLast == '') {
            return false;
        }

        $id = $this->findExistingPersonId($r, "guest", $newFirst, $newLast);

        $guest = new Guest($this->dbh, '', $id);

        if($id == 0){
            $gender = $this->findIdGenLookup("Gender", (isset($r['Gender']) ? $r["Gender"] : ""));
            $ethnicity = $this->findIdGenLookup("Ethnicity", (isset($r['Ethnicity']) ? $r["Ethnicity"] : ""));
            $noReturn =  $this->findIdGenLookup("No_Return", (isset($r["Banned"]) ? $r["Banned"] : ""));
            $mediaSource = $this->findIdGenLookup("Media_Source", (isset($r["mediaSource"]) ? $r["mediaSource"] : ""));

            $birthDate = "";
            if(isset($r["BirthDate"]) && trim($r['BirthDate']) != ''){
                try{
                    $birthdateDT = new \DateTime($r['BirthDate']);
                    $birthDate = $birthdateDT->format("M j, Y");
                }catch(\Exception $e){
                    $birthDate = ""; // unparseable birth dates (e.g. 0000-00-00) are left blank
                }
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
        $this->setExternalId($guest->getIdName(), $this->getExternalId($r));

        $this->importedGuests++;

        return $guest;

    }

    /**
     * Insert a reservation and its reservation_guest rows
     *
     * @param array $d ["idRegistration", "idGuest" (primary guest), "idHospitalStay", "idResource", "expectedArrival", "expectedDeparture", "actualArrival", "actualDeparture", "status", "notes", "title", "guests" => [idName => isPrimary]]
     * @return int idReservation
     */
    protected function insertReservation(array $d): int {
        $uS = Session::getInstance();

        $stmt = $this->dbh->prepare("insert into reservation (`idRegistration`, `idGuest`,`idHospital_Stay`, `idResource`, `Expected_Arrival`, `Expected_Departure`, `Actual_Arrival`, `Actual_Departure`, `Number_Guests`, `Status`, `Notes`, `Title`, `Updated_By`, `Last_Updated`) VALUES(:idRegistration, :idGuest, :idHospitalStay, :idResource, :expectedArrival, :expectedDeparture, :actualArrival, :actualDeparture, :numGuests, :status, :notes, :title, :updatedBy, now())");
        $stmt->execute(array(
            ":idRegistration"=>$d["idRegistration"],
            ":idGuest"=>$d["idGuest"],
            ":idHospitalStay"=>$d["idHospitalStay"] ?? 0,
            ":idResource"=>$d["idResource"] ?? 0,
            ":expectedArrival"=>$d["expectedArrival"],
            ":expectedDeparture"=>$d["expectedDeparture"],
            ":actualArrival"=>$d["actualArrival"] ?? null,
            ":actualDeparture"=>$d["actualDeparture"] ?? null,
            ":numGuests"=>max(1, count($d["guests"] ?? [])),
            ":status"=>$d["status"],
            ":notes"=>$d["notes"] ?? '',
            ":title"=>$d["title"] ?? '',
            ":updatedBy"=>$uS->username ?? 'admin',
        ));
        $idResv = (int) $this->dbh->lastInsertId();

        $rgStmt = $this->dbh->prepare("insert ignore into reservation_guest (`idReservation`, `idGuest`,`Primary_Guest`) VALUES(:idReservation, :idGuest, :primaryGuest)");
        foreach (($d["guests"] ?? []) as $idGuest => $isPrimary) {
            $rgStmt->execute(array(
                ":idReservation" => $idResv,
                ":idGuest" => $idGuest,
                ":primaryGuest" => ($isPrimary ? 1 : 0)
            ));
        }

        return $idResv;
    }

    /**
     * Insert a visit (span 0) and a stay for each guest
     *
     * @param array $d ["idReservation", "idRegistration", "idHospitalStay", "idResource", "idPrimaryGuest", "arrival", "expectedDeparture", "departure" (null if still checked in), "status", "notes", "stays" => [[idName, idRoom, checkin, checkout|null], ...]]
     * @return int idVisit
     */
    protected function insertVisit(array $d): int {
        $uS = Session::getInstance();
        $status = $d["status"] ?? VisitStatus::CheckedOut;
        $departure = $d["departure"] ?? null;

        $visitRS = new VisitRS();
        $visitRS->idReservation->setNewVal($d["idReservation"]);
        $visitRS->idRegistration->setNewVal($d["idRegistration"]);
        $visitRS->idHospital_stay->setNewVal($d["idHospitalStay"] ?? 0);
        $visitRS->idPrimaryGuest->setNewVal($d["idPrimaryGuest"]);
        $visitRS->idResource->setNewVal($d["idResource"] ?? 0);
        $visitRS->Arrival_Date->setNewVal($d["arrival"]);
        $visitRS->Expected_Departure->setNewVal($d["expectedDeparture"] ?? $departure);
        $visitRS->Span->setNewVal(0);
        $visitRS->Span_Start->setNewVal($d["arrival"]);
        $visitRS->Status->setNewVal($status);
        $visitRS->Notes->setNewVal($d["notes"] ?? '');
        $visitRS->Checked_In_By->setNewVal($uS->username ?? 'admin');
        $visitRS->Updated_By->setNewVal($uS->username ?? 'admin');
        $visitRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        if ($departure !== null) {
            $visitRS->Actual_Departure->setNewVal($departure);
            $visitRS->Span_End->setNewVal($departure);
        }
        $idVisit = EditRS::insert($this->dbh, $visitRS);

        foreach (($d["stays"] ?? []) as $stay) {
            $stayRS = new StaysRS();
            $stayRS->idName->setNewVal($stay["idName"]);
            $stayRS->idVisit->setNewVal($idVisit);
            $stayRS->Visit_Span->setNewVal(0);
            $stayRS->idRoom->setNewVal($stay["idRoom"] ?? 0);
            $stayRS->Checkin_Date->setNewVal($stay["checkin"]);
            $stayRS->Span_Start_Date->setNewVal($stay["checkin"]);
            $stayRS->Expected_Co_Date->setNewVal($d["expectedDeparture"] ?? $departure);
            $stayRS->Status->setNewVal($status);
            $stayRS->Updated_By->setNewVal($uS->username ?? 'admin');
            $stayRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            if (($stay["checkout"] ?? null) !== null) {
                $stayRS->Checkout_Date->setNewVal($stay["checkout"]);
                $stayRS->Span_End_Date->setNewVal($stay["checkout"]);
            }
            EditRS::insert($this->dbh, $stayRS);
        }

        return (int) $idVisit;
    }

    /**
     * Add a vehicle to a registration unless the registration already has one with the same license number
     *
     * @param Registration $reg
     * @param array $v ["make", "model", "color", "license", "state"]
     */
    protected function insertVehicle(Registration $reg, array $v): void {
        $license = substr(trim($v["license"] ?? ''), 0, 15);

        if ($license !== '') {
            $stmt = $this->dbh->prepare("select count(*) from `vehicle` where `idRegistration` = :idReg and `License_Number` = :license");
            $stmt->execute([":idReg" => $reg->getIdRegistration(), ":license" => $license]);
            if ($stmt->fetchColumn() > 0) {
                return;
            }
        }

        $stmt = $this->dbh->prepare("insert into vehicle (`idRegistration`, `Make`,`Model`, `Color`, `State_Reg`, `License_Number`) VALUES(:idReg, :make, :model, :color, :state, :license)");
        $stmt->execute(array(
            ":idReg" => $reg->getIdRegistration(),
            ":make" => substr(trim($v["make"] ?? ''), 0, 45),
            ":model" => substr(trim($v["model"] ?? ''), 0, 45),
            ":color" => substr(trim($v["color"] ?? ''), 0, 45),
            ":state" => substr(trim($v["state"] ?? ''), 0, 2),
            ":license" => $license
        ));
    }

    protected function addVehicle(array $vehicle, Registration $reg){
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
    protected function findPerson(string $first, string $last, string $memberType, bool $limit = true, string $phone = '', string $email = ''){

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
    protected function formatPhone(string $phone){
        $phone = preg_replace('[^0-9]', '', $phone);//throw out any non numeric characters
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $phone); //format remaining numbers
    }

    protected function loadAddress(\PDO $dbh, $r, $purpose = 1) {

        $state = ucfirst(trim($r['State']));
        $city = ucwords(trim($r['City']));
        $county = (isset($r['County']) ? ucfirst($r['County']) : '');
        $country = (isset($r['Country']) && trim($r['Country']) != '') ? strtoupper(trim($r['Country'])) : 'US';
        $zip = $r['ZipCode'];

        if ($country == 'US' && strlen($zip) > 4) {

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

    protected function getHospitals(){
        $stmt = $this->dbh->query("Select idHospital, Title from hospital");
        while ($h = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->hospitals[strtolower($h['Title'])] = $h['idHospital'];
        }
    }

    protected function getRooms(){
        $stmt = $this->dbh->query("Select idResource, Title from resource");
        while ($h = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->rooms[trim(strtolower($h['Title']))] = $h['idResource'];
        }
    }

    protected function findIdResource(string $roomTitle){
        return (isset($this->rooms[trim(strtolower($roomTitle))]) ? $this->rooms[trim(strtolower($roomTitle))] : 0);
    }

    /**
     * Find the Gen lookup ID based on given value, return empty string if not found
     * @param string $genLookupTableName
     * @param string $importFieldValue
     * @return int|string
     */
    protected function findIdGenLookup(string $genLookupTableName, string $importFieldValue):int|string
    {
        $tableKey = strtolower($genLookupTableName);
        if(isset($this->genLookups[$tableKey])){
            return (isset($this->genLookups[$tableKey][trim(strtolower($importFieldValue))]) ? $this->genLookups[$tableKey][trim(strtolower($importFieldValue))] : '');
        }
        return '';
    }

    /**
     * Load required gen lookups based on contents of genLookupMapping
     * @return void
     */
    protected function loadGenLookups(){
        foreach($this->genLookupMapping as $fieldName=>$genlookupTableName){
            $tableKey = strtolower($genlookupTableName);
            if(!isset($this->genLookups[$tableKey])){
                $this->genLookups[$tableKey] = [];
                foreach(Common::readGenLookupsPDO($this->dbh, $genlookupTableName) as $r) {
                    $this->genLookups[$tableKey][strtolower($r[1])] = $r[0];
                }
            }
        }
    }

    /**
     * Create a gen lookup value in HHK
     *
     * @param string $genLookupTableName
     * @param string $description
     * @return string the new code
     */
    protected function createGenLookup(string $genLookupTableName, string $description): string {
        $newCode = 'g' . Common::incCounter($this->dbh, 'codes');

        $glRs = new GenLookupsRS();
        $glRs->Table_Name->setNewVal($genLookupTableName);
        $glRs->Code->setNewVal($newCode);
        $glRs->Description->setNewVal($description);
        $glRs->Type->setNewVal('h');
        $glRs->Substitute->setNewVal('');
        $glRs->Order->setNewVal(0);

        EditRS::insert($this->dbh, $glRs);

        $this->genLookups[strtolower($genLookupTableName)][strtolower(trim($description))] = $newCode;

        return $newCode;
    }

    /**
     * Create a room, its resource and resource_room records
     *
     * @param string $title
     * @return int idRoom (equal to idResource)
     */
    protected function createRoom(string $title): int {
        // create room record
        $stmt = $this->dbh->prepare("insert into room (`idHouse`,`Item_Id`,`Title`,`Type`,`Category`,`Status`,`State`,`Availability`, `Max_Occupants`,`Min_Occupants`,`Rate_Code`,`Key_Deposit_Code`,`Cleaning_Cycle_Code`, `idLocation`) VALUES"
                . " (0, 1, :roomTitle, 'r', 'dh', 'a', 'a', 'a', 4, 0,'rb', 'k0', 'a', 1);");
        $stmt->execute(array(":roomTitle"=>$title));
        $idRoom = (int) $this->dbh->lastInsertId();

        // create resource record
        $stmt = $this->dbh->prepare("insert into resource (`idResource`,`idSponsor`,`Title`,`Utilization_Category`,`Type`,`Status`) values "
                . "(:idRoom, 0, :roomTitle, 'uc1', 'room', 'a')");
        $stmt->execute(array(":idRoom"=>$idRoom, ":roomTitle"=>$title));

        // Resource-Room
        $stmt = $this->dbh->prepare("insert into resource_room (`idResource_room`,`idResource`,`idRoom`) values "
                . "(:idRoom, :idRoom2, :idRoom3)");
        $stmt->execute(array(":idRoom" => $idRoom,":idRoom2" => $idRoom,":idRoom3" => $idRoom));

        $this->rooms[trim(strtolower($title))] = $idRoom;

        return $idRoom;
    }

    /**
     * Create a hospital
     *
     * @param string $title
     * @return int idHospital
     */
    protected function createHospital(string $title): int {
        $stmt = $this->dbh->prepare("insert into `hospital` (`Title`, `Type`, `Status`) values (:title, 'h','a');");
        $stmt->execute(array(":title"=>$title));
        $idHospital = (int) $this->dbh->lastInsertId();

        $this->hospitals[strtolower($title)] = $idHospital;

        return $idHospital;
    }
}
