<?php
/**
 * NameCutover.php
 *
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@hospitalityhousekeeper.com>
 * @copyright 2010-2013 <ecrane@hospitalityhousekeeper.com>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require ("homeIncludes.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'ReservationRS.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . "psg.php");
require (HOUSE . 'Patient.php');
require (HOUSE . 'Hospital.php');
require (HOUSE . 'ActivityReport.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Doctor.php');
require (HOUSE . 'HouseServices.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'RoomChooser.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'VisitCharges.php');
require (CLASSES . 'FinAssistance.php');

require (HOUSE . 'Vehicle.php');
require (HOUSE . 'Visit.php');
require (HOUSE . "visitViewer.php");

require (CLASSES . 'TableLog.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');


require (CLASSES . 'volunteer.php');
require (CLASSES . 'History.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'CleanAddress.php');

require (CLASSES . 'Notes.php');


$dbh = initPDO();

set_time_limit(280);

// get session instance
$uS = Session::getInstance();

if ($uS->username == '') {
    exit('Please log in');
}

function loadAddress(\PDO $dbh, $r, $countries, &$zipLookups) {

    $state = ucfirst(trim($r['State']));
    $city = ucwords(trim($r['City']));
    $county = '';  //ucfirst($r['County']);
    $country = '';
    $zip = $r['Zip'];

    if (trim($r['Country']) == 'US' || $r['Country'] == '') {

        $searchZip = substr($zip, 0, 5);

        if (isset($zipLookups[$searchZip]) === FALSE) {

            $stmtz = $dbh->query("Select City, State, County from postal_codes where Zip_Code = '$searchZip'");
            $rows = $stmtz->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) == 1) {
                $zipLookups[$searchZip] = $rows[0];
            }
        }

        if (isset($zipLookups[$searchZip])) {

            $state = $zipLookups[$searchZip]['State'];
            $city = $zipLookups[$searchZip]['City'];
            $county = $zipLookups[$searchZip]['County'];

        }

        $country = 'US';

    } else {

//        if (isset($countries[$r['Country']])) {
//            $country = $countries[$r['Country']];
//        }

        $country = $r['County'];

    }

    $adr1 = array('1' => array(
        'address1' => ucwords(strtolower(trim($r['Address']))),
        'address2' => trim($r['Address_2']),
        'city' => $city,
        'county'=>  $county,
        'state' => $state,
        'country' => $country,
        'zip' => $zip));

    return $adr1;
}

function addGuest(\PDO $dbh, $r, $countries, $zipLookups, Psg $psg) {

    $relations = array(
        'Friend'=>'frd',
        'Parent'=>'par',
        'Child'=>'chd',
        'Partner'=>'sp',
        'Sibling'=>'sib',
        'Relative'=>'rltv',
        'Patient'=>'slf',
//        'significant other'=>'rltv',
//        'nephew of patient'=>'rltv',
//        'aunt of patient'=>'rltv',
//        'uncle of patient'=>'rltv',
//        'sister-in-law'=>'rltv',
//        'cousin of patient'=>'rltv',
//        'brother-in-law'=>'rltv',
        );


    // get session instance
    $uS = Session::getInstance();

    $newFirst = ucwords(strtolower(trim(addslashes($r['First']))));
    $newLast = ucwords(strtolower(trim(addslashes($r['Last']))));
    $newMiddle = trim(addslashes($r['Middle']));

    if ($newLast == '') {
        return;
    }

    $query = "Select n.idName from name n join name_guest ng on n.idName = ng.idName"
        //. " where n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "' and n.Name_Middle = '" . $newMiddle ."'"
        . " where ng.Relationship_Code != 'slf' and n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "'"
        . " Limit 1";

    $stmtg = $dbh->query($query);
    $rowgs = $stmtg->fetchAll(PDO::FETCH_NUM);

    if (count($rowgs) == 0) {
        $id = 0;
    } else {
        $id = $rowgs[0][0];
    }

    $guest = new Guest($dbh, '', $id);

    $gender = '';
//    if ($r['Gender'] != '') {
//        $gender = $r['Gender'];
//    }

//    if ($r['Gender'] == 'Male') {
//        $gender = 'm';
//    } else if ($r['Gender'] == 'Female') {
//        $gender = 'f';
//
//    } else if ($r['Gender'] == 'N/A') {
//        $gender = 'z';
//    }

    // phone
    $phone = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $r['Phone']);


    $post = array(
        'txtFirstName' => $newFirst,
        'txtLastName'=>  $newLast,
        'rbPrefMail'=>'1',
        'rbEmPref'=>"1",
        'txtEmail'=>array('1'=>$r['Email']),
        'rbPhPref'=>"dh",
        'txtPhone'=>array('dh'=>$phone),
        'selStatus'=>'a',
        'sel_Gender'=>$gender,
        'selMbrType'=>'ai'
        );

    $adr1 = loadAddress($dbh, $r, $countries, $zipLookups);
    $post['adr'] = $adr1;

    $guest->save($dbh, $post, $uS->username);

    $relship = RelLinkType::Friend;
    if (isset($relations[$r['Relationship_to_Patient']])) {
        $relship = $relations[$r['Relationship_to_Patient']];
    }

    $psg->setNewMember($guest->getIdName(), $relship);
    $psg->savePSG($dbh, $psg->getIdPatient(), $uS->username);

    // external id
    $dbh->exec("update `name` set `External_Id` = " . $r['Guest_ID'] . " where `idName` = " . $guest->getIdName());

    return $guest;
}

function newVisit(\PDO $dbh, $guest, $reg, $hstay, $arrDT, $depDT, $newRoom, $rescs) {

    $rate = 'f';
    $idRoomRate = 6;
    $checkedOut = TRUE;

    if ($depDT == NULL) {
        $depDT = new DateTime();
        $depDT->add(new DateInterval('P1D'));
        $checkedOut = FALSE;
    }

//    if ($depDT->diff($arrDT, TRUE)->days < 1) {
//        return;
//    }
//
//
//    $stmt3 = $dbh->query("select * from reservation where idHospital_Stay= " . $hstay->getIdHospital_Stay() . " and  "
//        . "Date(Actual_Arrival) < DATE('".$depDT->format('Y-m-d') . "') and Date(Expected_Departure) >= DATE('".$arrDT->format('Y-m-d') . "') ");
//
//    $resvs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
//
//    if (count($resvs) > 0) {
//
//        echo($resvs[0]['idReservation'] . '<br/>');
//        return;
//    }

    // Reservation
    $reserv = Reservation_1::instantiateFromIdReserv($dbh, 0);

    $reserv->setExpectedArrival($arrDT->format('Y-m-d H:i:s'));
    $reserv->setActualArrival($arrDT->format('Y-m-d H:i:s'));
    $reserv->setExpectedDeparture($depDT->format('Y-m-d H:i:s'));
    $reserv->setIdGuest($guest->getIdName());
    $reserv->setHospitalStay($hstay);
    $reserv->setRoomRateCategory($rate);
    $reserv->setIdRoomRate($idRoomRate);

    $reserv->setNumberGuests(1);
    $reserv->setStatus(ReservationStatus::Committed);



    if (isset($rescs[$newRoom])) {
        $reserv->setIdResource($rescs[$newRoom]);
    } else {
        echo('bad room ' . $newRoom . '<br/>');
        return;
    }

    $reserv->saveReservation($dbh, $reg->getIdRegistration(), 'admin');
    ReservationSvcs::saveReservationGuest($dbh, $reserv->getIdReservation(), $guest->getIdName(), TRUE);


    // Visit
//    if (isset($rescs[$newRoom])) {

    $resc = Resource::getResourceObj($dbh, $rescs[$newRoom]);

    $visit = new Visit($dbh, $reg->getIdRegistration(), 0, $arrDT, $depDT, $resc, '', -1, TRUE);

    // Room Rate category
    $visit->setRateCategory($reserv->getRoomRateCategory());
    $visit->setIdRoomRate($reserv->getIdRoomRate());
    $visit->setPledgedRate($reserv->getFixedRoomRate());
    $visit->setPrimaryGuestId($guest->getIdName());

    // Reservation Id
    $visit->setReservationId($reserv->getIdReservation());

    // hospital stay id
    $visit->setIdHospital_stay($hstay->getIdHospital_Stay());

    return $visit;
}

function addStay($visit, $guest,  $arrDT, $depDT) {
    try {
        // add guests
        if ($visit->addGuestStay($guest->getIdName(), $arrDT->format('Y-m-d H:i:s'), $arrDT->format('Y-m-d H:i:s'), $depDT->format('Y-m-d H:i:s')) === FALSE) {
            echo'Add Stay Failed.';
        }

    } catch (Hk_Exception_Runtime $ex) {
        echo($ex->getMessage() . '<br/>');

    }
}

function finishVisit(\PDO $dbh, Visit $visit, $arrivalDT, $depDT) {


        // Checkin
        $visit->visitRS->Arrival_Date->setNewVal($arrivalDT->format('Y-m-d H:i:s'));
        $visit->visitRS->Span_Start->setNewVal($arrivalDT->format('Y-m-d H:i:s'));
        $visit->checkin($dbh, 'admin');


        //if ($checkedOut) {
        $visit->checkOutVisit($dbh, $depDT->format('Y-m-d H:i:s'), '', FALSE);
        //}
//    }


}

function loadPatients(\PDO $dbh, $start, $quant) {

    $zipLookups = array();

    $countries = array(
        'United States'=>'US',
        'Canada'=>'CA',
        'Uruguay'=>'UY',
        'Honduras'=>'HN',
        'Australia'=>'AU',
        'Ireland'=>'IE',
        'Egypt'=>'EG',
        'India'=>'IN',
        'Mexico'=>'MX',
        );
    // 25000  39732, 43280


//    Temecula Valley 1
//Rancho Springs 3
//Other 9
//Inland Valley 2
//Rady's 4
//Corona Regional 7
//Loma Linda 5
//Kaiser 8
    $hosps = array(
        "Temecula Valley"=>1,
        "Inland Valley"=>2,
        "Rancho Springs"=>3,
        "Rady's"=>4,
        "Loma Linda"=>5,
        "Corona Regional"=>7,
        "Kaiser"=>8,
        "Other"=>9,

    );

    // diagnosis
    $diags = array();

//    foreach (readGenLookupsPDO($dbh, 'Location') as $r) {
//        $diags[$r[1]] = $r[0];
//    }

    //Ethnicity
//    $ethnicity = array(
//        "White - Non-Hispanic/Caucasian" => 'c',
//        "Hispanic" => 'h',
//        "Black - Non-Hispanic" => 'f',
//        "Other" => 'x',
//        );

    // rooms
    $resRS = $dbh->query('select idResource, Title from resource');
    $rows = $resRS->fetchAll(PDO::FETCH_NUM);
    $rescs = array();
    foreach ($rows as $r) {
        $rescs[html_entity_decode($r[1], ENT_QUOTES)] = $r[0];
    }


    $query = "Select * from hhk_import order by `Patient_Last_Name`, `Patient_First_Name` LIMIT $start, $quant;";
    $stmt = $dbh->query($query);

    $numRead = $stmt->rowCount();
    $psg = null;
    $patient = null;
    $visit = null;
    $firstArrival = null;
    $lastDeparture = null;

    $patId = '';

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        if ($patId != $r['Patient_Last_Name'] . $r['Patient_First_Name']) {

            if ($visit !== null) {

                finishVisit($dbh, $visit, $firstArrival, $lastDeparture);
                $visit = null;

            }

            $patId = $r['Patient_Last_Name'] . $r['Patient_First_Name'];

            // New Patient
            $newPatFirst = ucwords(strtolower(trim(addslashes($r['Patient_First_Name']))));
            $newPatLast = ucwords(strtolower(trim(addslashes($r['Patient_Last_Name']))));

            $memStatus = 'a';

            $query = "Select n.idName from name n join name_guest ng on n.idName = ng.idName "
                //. " where n.Name_Last = '" . $newPatLast . "' and n.Name_First = '" . $newPatFirst . "' and n.Name_Middle = '" . $newPatMiddle ."'"
                . " where ng.Relationship_Code = 'slf' and n.Name_Last = '" . $newPatLast . "' and n.Name_First = '" . $newPatFirst . "'"
                . " Limit 1";

            $stmtp = $dbh->query($query);
            $rowgs = $stmtp->fetchAll(PDO::FETCH_NUM);

            if (count($rowgs) == 0) {
                $id = 0;
            } else {
                $id = $rowgs[0][0];
            }

            $patient = new Patient($dbh, '', $id);

            $gender = '';


            $post = array(
                'txtFirstName' => $newPatFirst,
                'txtLastName'=>  $newPatLast,
                'rbPrefMail'=>'1',
                'selStatus'=>$memStatus,
                'sel_Gender'=>$gender,
                'selMbrType'=>'ai',
                );


            $patient->save($dbh, $post, 'admin');

            //$unitText = '';  //isset($diags[trim($r['UNIT'])]) ? $diags[trim($r['UNIT'])] : '';

            $hospitalId = (isset($hosps[trim($r['Hospital'])]) ? $hosps[trim($r['Hospital'])] : 0);

            // PSG
            $psg = new Psg($dbh, 0, $patient->getIdName());
            $psg->setNewMember($patient->getIdName(), RelLinkType::Self);
            $psg->savePSG($dbh, $patient->getIdName(), 'admin');

            // Registration
            $reg = new Registration($dbh, $psg->getIdPsg());
            $reg->saveRegistrationRs($dbh, $psg->getIdPsg(), 'admin');

            // Hospital
            if ($hospitalId > 0) {

                $hospitalStay = new HospitalStay($dbh, $patient->getIdName());
                $hospitalStay->setHospitalId($hospitalId);
                //$hospitalStay->setLocationCode($unitText);
                $hospitalStay->setIdPsg($psg->getIdPsg());

                $hospitalStay->save($dbh, $psg, 0, 'admin');
            }

            // visits
            if ($r['Room'] != '' && $r['Arrival_Date'] != '') {

                $firstArrival = new DateTime($r['Arrival_Date']);
                $firstArrival->setTime(16, 0, 0);

                if ($r['Departure_Date'] != '') {
                    $lastDeparture = new DateTime($r['Departure_Date']);
                    $lastDeparture->setTime(10, 0, 0);
                } else {
                    $lastDeparture = NULL;
                }


                $visit = newVisit($dbh, $patient, $reg, $hospitalStay, $firstArrival, $lastDeparture, $r['Room'], $rescs);

                if (trim($r['Relationship_to_Patient']) == 'Patient') {
                    addStay($visit, $patient,  $firstArrival, $lastDeparture);
                }

            }

        }  // end of new patient


        if (trim($r['Relationship_to_Patient']) != 'Patient') {

            $guest = addGuest($dbh, $r, $countries, $zipLookups, $psg);

            $arrival = new DateTime($r['Arrival_Date']);
            $arrival->setTime(16, 0, 0);

            if ($r['Departure_Date'] != '') {
                $departure = new DateTime($r['Departure_Date']);
                $departure->setTime(10, 0, 0);
            } else {
                $departure = NULL;
            }

            if ($arrival < $firstArrival) {
                $firstArrival = new DateTime($arrival->format('y-m-d H:i:s'));
            }

            if ($departure > $lastDeparture) {
                $lastDeparture = new DateTime($departure->format('y-m-d H:i:s'));
            }

            addStay($visit, $guest,  $arrival, $departure);
        }

    }  // While records last


    // Finish last visit
    if ($visit !== null) {

        finishVisit($dbh, $visit, $firstArrival, $lastDeparture);

    }


    return $numRead;
}

$countPatients = 0;
$st = 0;
$en = 0;

if (isset($_POST['btnGo'])) {

    $st = intval($_POST['st'], 10);
    $en = intval($_POST['en'], 10);

    $countPatients = loadPatients($dbh, $st, $en);
}



?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title></title>

        <link href="css/house.css" rel="stylesheet" type="text/css" />

    </head>
    <body >
        <form action="#" method="post" style="margin:20px;">
        <p>Enter Patients</p>
        <p>Records last read: <?php echo $countPatients; ?></p>
        <p>Start at record: <input name="st" value="<?php echo $st; ?>" /></p>
        <p>Number of records: <input name="en" value="<?php echo $en; ?>" /></p>
        <input type="submit" name="btnGo" value="GO" />
        </form>
    </body>
</html>
