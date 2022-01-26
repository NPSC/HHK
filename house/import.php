<?php
use HHK\sec\WebInit;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\Session;
use HHK\sec\SecurityComponent;
use HHK\Payment\PaymentSvcs;
use HHK\HTMLControls\HTMLContainer;
use HHK\Exception\RuntimeException;
use HHK\House\HouseServices;
use HHK\House\PSG;
use HHK\SysConst\VolMemberType;
use HHK\SysConst\MemBasis;
use HHK\Member\Role\{Patient, Guest};
use HHK\Member\RoleMember\AbstractRoleMember;

use HHK\Member\Address\Address;
use HHK\Member\Address\Phones;
use HHK\Member\Address\Emails;
use HHK\History;
use HHK\HTMLControls\HTMLTable;
use HHK\House\Registration;
use HHK\Member\EmergencyContact\EmergencyContact;
use HHK\House\Hospital\HospitalStay;
use HHK\House\Vehicle;
use HHK\House\Hospital\Hospital;
use HHK\Purchase\FinAssistance;
use HHK\Member\Address\Addresses;
use HHK\SysConst\GLTableNames;
use HHK\HTMLControls\HTMLInput;
use HHK\SysConst\VisitStatus;
use HHK\Tables\Reservation\ReservationRS;
use HHK\Tables\EditRS;
use HHK\House\Reservation\Reservation_1;
use HHK\Purchase\RoomRate;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\MemStatus;
use HHK\CreateMarkupFromDB;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\SysConst\RoomRateCategories;
use HHK\House\Room\RoomChooser;
use HHK\sec\Labels;

/**
 * LoadGuests.php
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
    $country = 'US';
    $zip = trim($r['Zip']);

    if (strlen($zip) > 4) {

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
    }


    $adr1 = array('1' => array(
        'address1' => ucwords(strtolower(trim($r['Address']))),
    	'address2' => ucwords(strtolower(trim($r['Address_2']))),

        'city' => $city,
        'county'=>  $county,
        'state' => $state,
        'country' => $country,
        'zip' => $zip));

    return $adr1;
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

    $query = "Select * from importdata  LIMIT $start, $quant;";
    $stmt = $dbh->query($query);

    $numRead = $stmt->rowCount();
    $psg = null;
    $patient = null;



    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        	// New Patient
            $newPatFirst = trim(addslashes($r['First_Name']));
            $newPatLast = trim(addslashes($r['Last_Name']));


             $query = "Select n.idName from name n  "

                 . " where n.Name_Last = '" . $newPatLast . "' and n.Name_First = '" . $newPatFirst . "'"
                 . " Limit 1";

             $stmtp = $dbh->query($query);
             $rowgs = $stmtp->fetchAll(PDO::FETCH_NUM);

             if (count($rowgs) == 0) {
                 $id = 0;
             } else {
                 $id = $rowgs[0][0];
             }

            $guest = new Guest($dbh, '', 0);

            // veteran status = g108; g109 = no.
            $vstat = '';
            if (strtolower($r['Veteran_Status']) == 'y') {
            	$vstat = 'g108';
            }else if (strtolower($r['Veteran_Status']) == 'n') {
            	$vstat = 'g109';
            }

            $post = array(
                'txtFirstName' => $newPatFirst,
                'txtLastName'=>  $newPatLast,

                'selStatus'=>'a',
                'sel_Gender'=>'',
                'selMbrType'=>'ai',
            	'sel_Special_Needs' => $vStat,
                );


            	$homePhone = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $r['Phone']);
            	$cellPhone = '';

            	$post['rbPrefMail'] = '1';
            	$post['rbEmPref'] = "1";
            	$post['txtEmail'] = array('1'=>$r['Email_Address']);
            	$post['rbPhPref'] = "dh";
            	$post['txtPhone'] = array('dh'=>$homePhone);

            	$adr1 = loadAddress($dbh, $r, $countries, $zipLookups);
            	$post['adr'] = $adr1;



            $guest->save($dbh, $post, 'admin');




    }  // While records last


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
