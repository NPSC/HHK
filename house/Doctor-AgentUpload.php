<?php


use HHK\sec\WebInit;

use HHK\sec\Session;
use HHK\sec\SecurityComponent;

use HHK\Exception\RuntimeException;
use HHK\SysConst\VolMemberType;
use HHK\SysConst\MemBasis;
use HHK\Member\RoleMember\DoctorMember;
use HHK\Member\Role\Doctor;
use HHK\Member\Address\Address;
use HHK\Member\Address\Phones;
use HHK\Member\Address\Emails;
use HHK\HTMLControls\HTMLTable;
use HHK\House\Hospital\HospitalStay;
use HHK\House\Hospital\Hospital;
use HHK\Member\Address\Addresses;
use HHK\SysConst\GLTableNames;
use HHK\HTMLControls\HTMLInput;
use HHK\Tables\EditRS;
use HHK\SysConst\MemStatus;
use HHK\sec\Labels;

/**
 * Doctor-AgentUpload.php
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


$query = "Select * from goldoctors;";
$stmt = $dbh->query($query);

$agent = null;


while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

	$newFirst = '';
	$newLast = '';
	$newMiddle = '';
	
	$names = explode(' ', $r['Full_Name']);
	
	if (count($names) > 2) {
		// three names
		$newFirst = trim(addslashes($names[0]));
		$newMiddle = trim(addslashes($names[1]));
		$newLast = trim(addslashes($names[2]));
		
	}else if (count($names) > 1) {
		// Two names
		$newFirst = trim(addslashes($names[0]));
		$newLast = trim(addslashes($names[1]));
		
	} else {
		continue;
	}

        // name already exists?

        $query = "Select n.idName from name n join name_volunteer2 nv on n.idName = nv.idName"
            . " where n.Name_Last = '" . $newLast . "' and n.Name_First = '" . $newFirst . "' and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = '" . VolMemberType::Doctor . "'"
            . " Limit 1";

        $stmtp = $dbh->query($query);
        $rowgs = $stmtp->fetchAll(PDO::FETCH_NUM);

        if (count($rowgs) == 0) {
            $id = 0;
        } else {
            $id = $rowgs[0][0];
        }

        $doctor = new Doctor($dbh, 'd_', $id);


        $post = array(
            'd_txtFirstName' => addslashes($newFirst),
            'd_txtLastName'=>  addslashes($newLast),
			'd_txtMiddleName' => addslashes($newMiddle),
            );

        $doctor->save($dbh, $post, $uS->username);


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
