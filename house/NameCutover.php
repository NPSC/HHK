<?php
/**
 * IMDcutover.php
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
require (HOUSE . 'Registration.php');
require (HOUSE . 'VisitLog.php');

require (CLASSES . 'volunteer.php');
require (CLASSES . 'History.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'CleanAddress.php');

require (CLASSES . 'Notes.php');


$dbh = initPDO();

set_time_limit(180);

// get session instance
$uS = Session::getInstance();

if ($uS->username == '') {
    exit('Please log in');
}

function loadAddress(\PDO $dbh, $r, $countries, &$zipLookups) {

    $state = ucfirst(trim($r['State']));
    $city = ucfirst(trim($r['City']));
    $county = ucfirst($r['County']);
    $country = '';
    $zip = trim($r['ZIP']);

    if ($r['Country'] == 'United States') {

        if (isset($zipLookups[$zip]) === FALSE) {

            $stmtz = $dbh->query("Select City, State, County from postal_codes where Zip_Code = '$zip'");
            $rows = $stmtz->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) == 1) {
                $zipLookups[$zip] = $rows[0];
            }
        }

        if (isset($zipLookups[$zip])) {

            $state = $zipLookups[$zip]['State'];
            $city = $zipLookups[$zip]['City'];
            $county = $zipLookups[$zip]['County'];

        }

        $country = 'US';

    } else {

        if (isset($countries[$r['Country']])) {
            $country = $countries[$r['Country']];
        }

        $state = $r['State'];
    }

    $adr1 = array('1' => array(
        'address1' => trim($r['Address1']),
        'address2' => '',
        'city' => $city,
        'county'=>  $county,
        'state' => $state,
        'country' => $country,
        'zip' => $zip));

    return $adr1;
}

function addGuest(\PDO $dbh, $r, $countries, $zipLookups, Psg $psg) {

    // get session instance
    $uS = Session::getInstance();
    $relations = array('friend'=>'frd', 'parent'=>'par', 'grandparent'=>'gp', 'Child'=>'chd', 'spouse/partner'=>'sp', 'Partner'=>'sp', 'other extended family'=>'rltv');

    $newFirst = trim(addslashes($r['FirstName']));
    $newLast = trim(addslashes($r['LastName']));
    $newMiddle = trim(addslashes($r['MiddleName']));

    $query = "Select n.idName from name n "
        . " where n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "' and n.Name_Middle = '" . $newMiddle ."'"
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
    if ($r['Gender'] != '') {
        $gender = $r['Gender'];
    }

    $post = array(
        'txtFirstName' => addslashes($newFirst),
        'txtLastName'=>  addslashes($newLast),
        'rbEmPref'=>"1",
        'txtEmail'=>array('1'=>$r['Email']),
        'rbPhPref'=>"dh",
        'txtPhone'=>array('dh'=>$r['Phone']),
        'selStatus'=>'a',
        'sel_Gender'=>$gender,
        'selMbrType'=>'ai'
        );

    $adr1 = loadAddress($dbh, $r, $countries, $zipLookups);
    $post['adr'] = $adr1;

    $guest->save($dbh, $post, $uS->username);

    $relship = RelLinkType::Friend;
    if (isset($relations[$r['Relationship']])) {
        $relship = $relations[$r['Relationship']];
    }

    $psg->setNewMember($guest->getIdName(), $relship);
    $psg->savePSG($dbh, $psg->getIdPatient(), $uS->username);

}



$states =  array_flip(array("AL"=>"Alabama","AK"=>"Alaska","AS"=>"American Samoa","AZ"=>"Arizona","AR"=>"Arkansas","AF"=>"Armed Forces Africa","AA"=>"Armed Forces Americas","AC"=>"Armed Forces Canada","AE"=>"Armed Forces Europe","CA"=>"California","CO"=>"Colorado","CT"=>"Connecticut","DE"=>"Delaware","DC"=>"District of Columbia","FM"=>"Federated States Of Micronesia","FL"=>"Florida","GA"=>"Georgia","GU"=>"Guam","HI"=>"Hawaii","ID"=>"Idaho","IL"=>"Illinois","IN"=>"Indiana","IA"=>"Iowa","KS"=>"Kansas","KY"=>"Kentucky","LA"=>"Louisiana","ME"=>"Maine","MH"=>"Marshall Islands","MD"=>"Maryland","MA"=>"Massachusetts","MI"=>"Michigan","MN"=>"Minnesota","MS"=>"Mississippi","MO"=>"Missouri","MT"=>"Montana","NE"=>"Nebraska","NV"=>"Nevada","NH"=>"New Hampshire","NJ"=>"New Jersey","NM"=>"New Mexico","NY"=>"New York","NC"=>"North Carolina","ND"=>"North Dakota","MP"=>"Northern Mariana Islands","OH"=>"Ohio","OK"=>"Oklahoma","OR"=>"Oregon","PW"=>"Palau","PA"=>"Pennsylvania","PR"=>"Puerto Rico","RI"=>"Rhode Island","SC"=>"South Carolina","SD"=>"South Dakota","TN"=>"Tennessee","TX"=>"Texas","UT"=>"Utah","VT"=>"Vermont","VI"=>"Virgin Islands","VA"=>"Virginia","WA"=>"Washington","WV"=>"West Virginia","WI"=>"Wisconsin","WY"=>"Wyoming"));

$zipLookups = array();

$countries = array(
    'United States'=>'US',
    'Canada'=>'CA',
    'Uruguay'=>'UY',
    'Honduras'=>'HN',
    'Australia'=>'AU',
    'Ireland'=>'IE',
    );


$query = "Select * from secunh order by PatientID limit 100;";
$stmt = $dbh->query($query);
$psg = null;
$patient = null;

$patId = 0;

/*secunh_id int(11) AI PK
PatientID int(11)
PatientFirstName varchar(45)
PatientMiddleName varchar(45)
PatientLastName varchar(45)
FirstName varchar(45)
MiddleName varchar(45)
LastName varchar(45)
Gender varchar(12)
Relationship varchar(45)
Address1 varchar(255)
Address2 varchar(45)
City varchar(45)
State varchar(25)
Country varchar(25)
ZIP varchar(25)
County varchar(45)
Email varchar(255)
Phone varchar(25)
EmergencyContact
*/
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

    if ($patId != $r['PatientID']) {

        $patId = $r['PatientID'];

        // New Patient
        $newPatFirst = trim(addslashes($r['PatientFirstName']));
        $newPatLast = trim(addslashes($r['PatientLastName']));
        $newPatMiddle = trim(addslashes($r['PatientMiddleName']));

        $memStatus = 'a';
        if ($newPatMiddle == '(deceased)') {
            $newPatMiddle = '';
            $memStatus = 'd';
        }

        $query = "Select n.idName from name n "
            . " where n.Name_Last = '" . $newPatLast . "' and n.Name_First = '" . $newPatFirst . "' and n.Name_Middle = '" . $newPatMiddle ."'"
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
        if ($r['Gender'] != '') {
            $gender = $r['Gender'];
        }

        $post = array(
            'txtFirstName' => addslashes($newPatFirst),
            'txtLastName'=>  addslashes($newPatLast),
            'txtMiddleName'=>  addslashes($newPatMiddle),
            'rbEmPref'=>"1",
            'txtEmail'=>array('1'=>$r['Email']),
            'rbPhPref'=>"dh",
            'txtPhone'=>array('dh'=>$r['Phone']),
            'selStatus'=>$memStatus,
            'sel_Gender'=>$gender,
            'selMbrType'=>'ai'
            );


        if ($r['Relationship'] == 'self') {
            // Have patient address
            $adr1 = loadAddress($dbh, $r, $countries, $zipLookups);
            $post['adr'] = $adr1;
        }


        $patient->save($dbh, $post, $uS->username);

        $psg = new Psg($dbh, 0, $patient->getIdName());
        $psg->setNewMember($patient->getIdName(), RelLinkType::Self);
        $psg->savePSG($dbh, $patient->getIdName(), $uS->username);



        if ($r['Relationship'] != 'self') {
            // add Guest
            addGuest($dbh, $r, $countries, $zipLookups, $psg);
        }


    } else {

        // New Guest for existing patient.
        addGuest($dbh, $r, $countries, $zipLookups, $psg);

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
