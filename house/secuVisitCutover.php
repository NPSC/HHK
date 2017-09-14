<?php
/**
 * GHOCcutover.php
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

require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'AttributeRS.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (CLASSES . 'volunteer.php');
//require (CLASSES . 'selCtrl.php');
//require (CLASSES . 'Relation.php');
require (CLASSES . 'History.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'CleanAddress.php');

require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'ActivityReport.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Doctor.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Hospital.php');

require (CLASSES . 'HouseLog.php');
require (HOUSE . 'HouseServices.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'PaymentManager.php');
require (HOUSE . 'PaymentChooser.php');
require (HOUSE . "psg.php");
require (HOUSE . 'RateChooser.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'RoomChooser.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'RegistrationForm.php');

require (HOUSE . 'Vehicle.php');
require (HOUSE . 'Visit.php');
require (HOUSE . "visitViewer.php");
require (HOUSE . 'Waitlist.php');
require (HOUSE . 'WaitlistSvcs.php');
require (HOUSE . 'Register.php');
require (HOUSE . 'VisitCharges.php');
require (CLASSES . 'FinAssistance.php');
require (CLASSES . 'Notes.php');


$dbh = initPDO();

set_time_limit(180);

// get session instance
$uS = Session::getInstance();

if ($uS->username == '') {
    exit('Please log in');
}


// rooms
$resRS = $dbh->query('select idResource, Title from resource');
$rows = $resRS->fetchAll(PDO::FETCH_NUM);
$rescs = array();
foreach ($rows as $r) {
    $sp = explode(' ', $r[1]);

    $rescs[$sp[0]] = $r[0];
}

$now = new DateTime();
$now->setTime(11, 0, 0);

$ethnicities = array('HISP'=>'h','NA'=>'g100','ANGLO'=>'c','AA'=>'f','OTHER'=>'x',''=>'x',);

$states =  array_flip(array("AL"=>"Alabama","AK"=>"Alaska","AS"=>"American Samoa","AZ"=>"Arizona","AR"=>"Arkansas","AF"=>"Armed Forces Africa","AA"=>"Armed Forces Americas","AC"=>"Armed Forces Canada","AE"=>"Armed Forces Europe","CA"=>"California","CO"=>"Colorado","CT"=>"Connecticut","DE"=>"Delaware","DC"=>"District of Columbia","FM"=>"Federated States Of Micronesia","FL"=>"Florida","GA"=>"Georgia","GU"=>"Guam","HI"=>"Hawaii","ID"=>"Idaho","IL"=>"Illinois","IN"=>"Indiana","IA"=>"Iowa","KS"=>"Kansas","KY"=>"Kentucky","LA"=>"Louisiana","ME"=>"Maine","MH"=>"Marshall Islands","MD"=>"Maryland","MA"=>"Massachusetts","MI"=>"Michigan","MN"=>"Minnesota","MS"=>"Mississippi","MO"=>"Missouri","MT"=>"Montana","NE"=>"Nebraska","NV"=>"Nevada","NH"=>"New Hampshire","NJ"=>"New Jersey","NM"=>"New Mexico","NY"=>"New York","NC"=>"North Carolina","ND"=>"North Dakota","MP"=>"Northern Mariana Islands","OH"=>"Ohio","OK"=>"Oklahoma","OR"=>"Oregon","PW"=>"Palau","PA"=>"Pennsylvania","PR"=>"Puerto Rico","RI"=>"Rhode Island","SC"=>"South Carolina","SD"=>"South Dakota","TN"=>"Tennessee","TX"=>"Texas","UT"=>"Utah","VT"=>"Vermont","VI"=>"Virgin Islands","VA"=>"Virginia","WA"=>"Washington","WV"=>"West Virginia","WI"=>"Wisconsin","WY"=>"Wyoming"));

$query = "Select * from secuch1;";
$stmt = $dbh->query($query);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {


    $newFirst = trim(addslashes($r['FirstName']));
    $newLast = trim(addslashes($r['LastName']));


    // Check for previous guest
    $id = 0;

    $query = "Select n.idName from name n "
            . " where n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "'"
            . " Limit 1";

    $stmtn = $dbh->query($query);
    $rows = $stmtn->fetchAll(PDO::FETCH_NUM);

    if (count($rows) > 0) {
        $id = $rows[0][0];
    }

    $guest = new Guest($dbh, '', $id);

    $country = 'US';


    $state = $states[trim($r['State'])];
    $city = trim($r['City']);

    $adr1 = array('1' => array(
        'address1' => trim($r['Address']),
        'address2' => '',
        'city' => $city,
        'state' => $state,
        'country' => $country,
        'zip' => trim($r['ZIP'])));


    // Make a member
    $post = array(
        'txtFirstName' => addslashes($newFirst),
        'txtLastName'=>  addslashes($newLast),
        'adr' => $adr1,
//        'rbEmPref'=>"1",
//        'txtEmail'=>array(1=>$r['Email']),
        'rbPhPref'=>"dh",
        'txtPhone'=>array('dh'=>$r['PhoneHome'], 'gw'=>$r['PhoneWork'], 'mc'=>$r['PhoneMobile']),
        'selStatus'=>'a',
        'selMbrType'=>'ai'
        );


    $guest->save($dbh, $post, $uS->username);


    // PSG
//    $ngRss = Psg::getNameGuests($dbh, $guest->getIdName());
//    $idPsg = 0;
//
//    foreach ($ngRss as $ngRs) {
//        if ($ngRs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
//            // Use it
//            $idPsg = $ngRs->idPsg->getStoredVal();
//            break;
//        }
//
//    }

    $psg = new Psg($dbh, $idPsg);
    $psg->setNewMember($guest->getIdName(), RelLinkType::Self);
    $psg->savePSG($dbh, $guest->getIdName(), $uS->username);

    // Hospital stay
    $hstay = new HospitalStay($dbh, $psg->getIdPatient());

    $hstay->setHospitalId(1);
    $hstay->setDiagnosis($r['Reason']);
    $hstay->setIdPsg($psg->getIdPsg());
    $hstay->save($dbh, $psg, 0, $uS->username);


    // Registration
    $reg = new Registration($dbh, $psg->getIdPsg());
    $reg->saveRegistrationRs($dbh, $psg->getIdPsg(), $uS->username);



    $rate = 'e';
    $idRoomRate = 17;


    $depDT = setTimeZone($uS, $r['DepartureDate']);
    $arrDT = setTimeZone($uS, $r['ArrivalDate']);


    if ($depDT->diff($arrDT, TRUE)->days < 1) {
        continue;
    }

    $stmt3 = $dbh->query("select * from reservation where idHospital_Stay= " . $hstay->getIdHospital_Stay() . " and  "
        . "Date(Actual_Arrival) < DATE('".$depDT->format('Y-m-d') . "') and Date(Expected_Departure) >= DATE('".$arrDT->format('Y-m-d') . "') ");

    $resvs = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    if (count($resvs) > 0) {

        echo($resvs[0]['idReservation'] . '<br/>');
        continue;
    }

    $arrDT->setTime(17, 10, 00);
    $depDT->setTime(10, 10, 0);

    if (strtolower($r['Status']) == 'waitlist') {
        $stat = ReservationStatus::Waitlist;
    } else if (strtolower($r['Status']) == 'checkedin') {
        $stat = ReservationStatus::Committed;
    } else {
        echo('bad status: ' . $r['Status'] . '<br/>');
        continue;
    }


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
    $reserv->setStatus($stat);


    $rawRooms = explode(' ', $r['Rooms']);
    $newRoom = $rawRooms[0];

    //$newRoom = trim($r['Rooms']);

    if (isset($rescs[$newRoom])) {
        $reserv->setIdResource($rescs[$newRoom]);
    } else {
        echo('bad room ' . $newRoom . '<br/>');
        $reserv->setIdResource(0);
    }


    $reserv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);
    ReservationSvcs::saveReservationGuest($dbh, $reserv->getIdReservation(), $guest->getIdName(), TRUE);


    // Visit
    if ($stat == ReservationStatus::Committed && isset($rescs[$newRoom])) {
        $resc = Resource::getResourceObj($dbh, $rescs[$newRoom]);

        $visit = new Visit($dbh, $reg->getIdRegistration(), 0, $arrDT, $depDT, $resc, '', -1, TRUE);

        try {
            // add guests
            if ($visit->addGuestStay($guest->getIdName(), $arrDT->format('Y-m-d H:i:s'), $arrDT->format('Y-m-d H:i:s'), $depDT->format('Y-m-d H:i:s')) === FALSE) {
                echo'Add Stay Failed.';
            }


        } catch (Hk_Exception_Runtime $ex) {
            echo($ex->getMessage() . '<br/>');

        }

        // Room Rate category
        $visit->setRateCategory($reserv->getRoomRateCategory());
        $visit->setIdRoomRate($reserv->getIdRoomRate());
        $visit->setPledgedRate($reserv->getFixedRoomRate());
        $visit->setPrimaryGuestId($guest->getIdName());

        // Reservation Id
        $visit->setReservationId($reserv->getIdReservation());

        // hospital stay id
        $visit->setIdHospital_stay($hstay->getIdHospital_Stay());


        // Checkin
        $visit->checkin($dbh, $uS->username);

                // Save new reservation status
        $reserv->setStatus(ReservationStatus::Staying);
        $reserv->setActualArrival($visit->getArrivalDate());
        $reserv->setExpectedDeparture($visit->getExpectedDeparture());
        $reserv->setNumberGuests($numOccupants);
        $reserv->setIdResource($resc->getIdResource());
        $reserv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);


        $notes = $r['NotesPublic'];
        if ($notes != '' && $visit->getIdRegistration() > 0) {

            $roomTitle = $r['Rooms'];
            $visit->setNotes($notes, $uS->username, $roomTitle);
            $visit->updateVisitRecord($dbh, $uS->username);

//            $oldNotes = is_null($psg->psgRS->Notes->getStoredVal()) ? '' : $psg->psgRS->Notes->getStoredVal();
//            $psg->psgRS->Notes->setNewVal($oldNotes . "\r\n" . date('m-d-Y') . ', visit ' . $visit->getIdVisit() . '-' . $visit->getSpan() . ', room ' . $roomTitle . ', ' . $uS->username . ' - ' . $notes);
//            $psg->savePSG($dbh, $guest->getIdName(), $uS->username);

        }

        //$visit->checkOutVisit($dbh, $depDT->format('Y-m-d H:i:s'), '', FALSE);
    }

}  // While records last







?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title></title>


        <link href="css/house.css" rel="stylesheet" type="text/css" />

    </head>
    <body >


    </body>
</html>
