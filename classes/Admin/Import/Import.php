<?php
namespace HHK\Admin\Import;


use HHK\Member\Role\Patient;
use HHK\SysConst\RelLinkType;
use HHK\sec\Session;
use HHK\House\PSG;
use HHK\House\Registration;
use HHK\House\Hospital\HospitalStay;
use HHK\Member\Role\Guest;
use HHK\Tables\Visit\VisitRS;
use HHK\SysConst\VisitStatus;
use HHK\Tables\EditRS;
use HHK\SysConst\ReservationStatus;
use HHK\Tables\Visit\StaysRS;


class Import {

    protected \PDO $dbh;
    protected array $zipLookups;
    protected array $hospitals;
    protected array $relations;
    protected array $diags;
    protected array $rooms;

    protected int $importedPatients;
    protected int $importedGuests;

    public function __construct(\PDO $dbh){
        $this->dbh = $dbh;
        $this->getHospitals();
        $this->getRelations();
        $this->getDiags();
        $this->getRooms();
    }

    public function startImport(int $limit = 100, bool $people = true, bool $visits = false){

        ini_set('max_execution_time', '300');

        $query = "Select * from `" . Upload::TBL_NAME . "` i where i.imported is null group by i.importId order by `PatientId`, `PatientLast`, `PatientFirst`, `isPatient` LIMIT $limit;";
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

            try{
                $this->dbh->beginTransaction();
                if ($patId != $r['PatientLast'] . $r['PatientFirst']) {

                    $patId = $r['PatientLast'] . $r['PatientFirst'];

                    $patArray = $this->addPatient($r);
                    $patient = $patArray["patient"];
                    $psg = $patArray["psg"];
                    $reg = $patArray["reg"];
                    $hospStay = $patArray["hospStay"];

                    $idGuest = $patient->getIdName();
                }  // end of new patient


                if (trim($r['PatientLast'] . $r['PatientFirst']) != trim($r['GuestLast'] . $r['GuestFirst'])) {

                    $idGuest = $this->addGuest($r, $psg)->getIdName();
                }

                //stays
                if($r['Arrive'] != '' && $r['Departure'] != ''){
                    $this->addVisit($r, $idGuest, $reg, $hospStay);
                }

                $this->dbh->commit();

                // mark imported
                $this->dbh->exec("update `" . Upload::TBL_NAME . "` set `imported` = 'yes' where `importId` = " . $r['importId']);

            }catch(\Exception $e){
                $this->dbh->rollBack();

                return array("error"=>$e->getMessage(), "ImportId"=>$r['importId'], "trace"=>$e->getTraceAsString());
            }
        }

        return array('success'=>true, 'batch'=>$numRead, 'patients'=>$this->importedPatients, 'guests'=>$this->importedGuests, "progress"=>$this->getProgress());

    }

    private function addPatient(array $r){

        // New Patient
        $newPatFirst = trim(addslashes($r['PatientFirst']));
        $newPatLast = trim(addslashes($r['PatientLast']));
        $newPatNickname = trim(addslashes($r['PatientNickname']));


        $id = $this->findPerson($newPatFirst, $newPatLast, false);

        $patient = new Patient($this->dbh, '', $id);

        $post = array(
            'txtFirstName' => $newPatFirst,
            'txtLastName'=>  $newPatLast,
            'txtNickname' => $newPatNickname,

            'selStatus'=>'a',
            'sel_Gender'=>'',
            'selMbrType'=>'ai',
        );

        if (trim($r['PatientLast'] . $r['PatientFirst']) == trim($r['GuestLast'] . $r['GuestFirst'])) { //assume patient is the guest

            $homePhone = $this->formatPhone($r['Home']);
            $cellPhone = $this->formatPhone($r['Cell']);
            $workPhone = $this->formatPhone($r['Work']);

            $post['rbPrefMail'] = '1';
            $post['rbEmPref'] = "1";
            $post['txtEmail'] = array('1'=>$r['Email']);
            $post['rbPhPref'] = "dh";
            $post['txtPhone'] = array('dh'=>$homePhone, 'mc'=>$cellPhone, 'gw'=>$workPhone);

            $adr1 = $this->loadAddress($this->dbh, $r);
            $post['adr'] = $adr1;

        }


        $patient->save($this->dbh, $post, 'admin');


        $hospitalId = (isset($this->hospitals[trim(strtolower($r['Hospital']))]) ? $this->hospitals[trim(strtolower($r['Hospital']))] : 0);

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
        $newNickname = trim(addslashes($r['GuestNickname']));

        if ($newLast == '') {
            return;
        }

        $id = $this->findPerson($newFirst, $newLast, false);

        $guest = new Guest($this->dbh, '', $id);

        if($id == 0){
            $gender = '';

            // phone
            $homePhone = $this->formatPhone($r['Home']);
            $cellPhone = $this->formatPhone($r['Cell']);
            $workPhone = $this->formatPhone($r['Work']);

            $post = array(
                'txtFirstName' => $newFirst,
                'txtLastName'=>  $newLast,
                'txtNickname'=> $newNickname,
                'rbPrefMail'=>'1',
                'rbEmPref'=>"1",
                'txtEmail'=>array('1'=>$r['Email']),
                'rbPhPref'=>"dh",
                'txtPhone'=>array('dh'=>$homePhone, 'mc'=>$cellPhone, 'gw'=>$workPhone),
                'txtBirthDate'=> '',  //$r['Date_of_Birth'],
                'selStatus'=>'a',
                'sel_Ethnicity'=>'',
                'sel_Gender'=>$gender,
                'selMbrType'=>'ai'
            );

            $adr1 = $this->loadAddress($this->dbh, $r);
            $post['adr'] = $adr1;

            $guest->save($this->dbh, $post, $uS->username);
        }
        $relship = RelLinkType::Relative;
        if (isset($r['Relation_to_Patient']) && isset($this->relations[$r['Relation_to_Patient']])) {
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

    private function addVisit(array $r, $idGuest, $reg, $hospStay){

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
        $idResource = $this->findIdResource($r['RoomNum']);
        $arrival = (new \DateTime($r['Arrive']))->format("Y-m-d 16:00:00");
        $departure = (new \DateTime($r['Departure']))->format('Y-m-d 10:00:00');
        $stmt = $this->dbh->prepare("insert into reservation (`idRegistration`, `idGuest`,`idHospital_Stay`, `idResource`, `Expected_Arrival`, `Expected_Departure`, `Number_Guests`, `Status`) VALUES(:idRegistration, :idGuest, :idHospitalStay, :idResource, :Arrive, :Departure, :numGuests, :status)");
        $stmt->execute(array(
            ":idRegistration"=>$reg->getIdRegistration(),
            ":idGuest"=>$idGuest,
            ":idHospitalStay"=>$hospStay->getIdHospital_Stay(),
            ":idResource"=>$idResource,
            ":Arrive"=>$arrival,
            ":Departure"=>$departure,
            ":numGuests"=>'1',
            ":status"=>ReservationStatus::Checkedout
        ));
        $resvId = $this->dbh->lastInsertId();

        if($resvId){
            $stmt = $this->dbh->prepare("insert into reservation_guest (`idReservation`, `idGuest`,`Primary_Guest`) VALUES(:idReservation, :idGuest, :primaryGuest)");
            $stmt->execute(array(
                ":idReservation"=>$resvId,
                ":idGuest"=>$idGuest,
                ":primaryGuest"=>1
            ));

            //make visit
            $visitRS = new VisitRS();
            $visitRS->idReservation->setNewVal($resvId);
            $visitRS->idRegistration->setNewVal($reg->getIdRegistration());
            $visitRS->idHospital_stay->setNewVal($hospStay->getIdHospital_Stay());
            $visitRS->idPrimaryGuest->setNewVal($idGuest);
            $visitRS->idResource->setNewVal($idResource);
            $visitRS->Arrival_Date->setNewVal($arrival);
            $visitRS->Expected_Departure->setNewVal($departure);
            $visitRS->Actual_Departure->setNewVal($departure);
            $visitRS->Span->setNewVal(0);
            $visitRS->Span_Start->setNewVal($arrival);
            $visitRS->Span_End->setNewVal($departure);
            $visitRS->Status->setNewVal(VisitStatus::CheckedOut);
            $idVisit = EditRS::insert($this->dbh, $visitRS);

            //make stay
            $stayRS = new StaysRS();
            $stayRS->idName->setNewVal($idGuest);
            $stayRS->idVisit->setNewVal($idVisit);
            $stayRS->Visit_Span->setNewVal(0);
            $stayRS->Checkin_Date->setNewVal($arrival);
            $stayRS->Checkout_Date->setNewVal($departure);
            $stayRS->Span_Start_Date->setNewVal($arrival);
            $stayRS->Span_End_Date->setNewVal($departure);
            $stayRS->Status->setNewVal(VisitStatus::CheckedOut);
            EditRS::insert($this->dbh, $stayRS);

        }
    }

    public function makeMissingRooms(){

        $uploadedRooms = (new ImportMarkup($this->dbh))->getRoomInfo();


        try{
        // Install missing rooms
        foreach($uploadedRooms as $room) {
            if($room["idResource"] == null && $room['RoomNum'] != ''){
                $title = $room['RoomNum'];

                // create room record
                $this->dbh->exec("insert into room "
                    . "(`idHouse`,`Item_Id`,`Title`,`Type`,`Category`,`Status`,`State`,`Availability`,
    `Max_Occupants`,`Min_Occupants`,`Rate_Code`,`Key_Deposit_Code`,`Cleaning_Cycle_Code`, `idLocation`) VALUES
    (0, 1, '$title', 'r', 'dh', 'a', 'a', 'a', 4, 0,'rb', 'k0', 'a', 1);");

                    $idRoom = $this->dbh->lastInsertId();

                    // create resource record
                    $this->dbh->exec("insert into resource "
                        . "(`idResource`,`idSponsor`,`Title`,`Utilization_Category`,`Type`,`Util_Priority`,`Status`)"
                        . " Values "
                        . "($idRoom, 0, '$title', 'uc1', 'room', '$title', 'a')");

                    // Resource-Room
                    $this->dbh->exec("insert into resource_room "
                        . "(`idResource_room`,`idResource`,`idRoom`) values "
                        . "($idRoom, $idRoom, $idRoom)");
            }
        }
        }catch(\Exception $e){
            return array("error"=>$e->getMessage());
        }
        return array("success"=>"Rooms inserted");
    }

    public function undoImport(){
        try{
            $this->dbh->exec("update `name` set `Member_Status` = 'tbd' where `External_Id` != ''");
            $this->dbh->exec("update `" . Upload::TBL_NAME . "` set `imported` = null");
        }catch(\Exception $e){
            return array("error"=>$e->getMessage());
        }
        return array("success"=>"Imported people have been set for 'To Be Deleted', use the 'Delete Member Records' function to delete them.");
    }

    private function findPerson(string $first, string $last, string $memberType, bool $limit = true, string $phone = '', string $email = ''){

        $newFirst = trim(addslashes($first));
        $newLast = trim(addslashes($last));
        $phone = ($phone !='' ? $this->formatPhone($phone) : null);
        $email = ($email !='' ? trim($email) : null);


        $query = "Select n.idName, ng.idPsg, ng.Relationship_Code from name n join name_guest ng on n.idName = ng.idName where n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "'";
        if($memberType == "guest"){
            $query .= " and ng.Relationship_Code != 'slf'";
        }else if($memberType == "patient"){
            $query .= " and ng.Relationship_Code = 'slf'";
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

    private function formatPhone($phone){
        return preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $phone);
    }

    private function loadAddress(\PDO $dbh, $r) {

        $state = ucfirst(trim($r['State']));
        $city = ucwords(trim($r['City']));
        $county = (isset($r['County']) ? ucfirst($r['County']) : '');
        $country = 'US';
        $zip = $r['Zip'];

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
            'address1' => ucwords(strtolower(trim($r['Street1']))),
            'address2' => trim($r['Street2']),
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
            $this->rooms[strtolower($h['Title'])] = $h['idResource'];
        }
    }

    private function findIdResource(string $roomTitle){
        return (isset($this->rooms[trim(strtolower($roomTitle))]) ? $this->rooms[trim(strtolower($roomTitle))] : 0);
    }
}
?>