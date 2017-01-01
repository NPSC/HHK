<?php
/**
 * mySqlFunc.php
 *
* @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


function openMysqli(\PDO $dbh, Session $uS) {

    $driver = $dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);

    if ($driver != 'mysql') {
        return 'Driver not mysql. ';
    } else {

        $mysqli = new mysqli($uS->databaseURL, $uS->databaseUName, $uS->databasePWord, $uS->databaseName);

        /* check connection */
        if ($mysqli->connect_errno) {
            return "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
        }

    }
    return $mysqli;
}

function multiQuery(mysqli $mysqli, $query, $delimiter = ";", $splitAt = ';') {
    $msg = array();

    if ($query === FALSE || trim($query) == '') {
        return $msg;
    }

    $qParts = explode($splitAt, $query);

    foreach ($qParts as $q) {

        $q = trim($q);
        if ($q == '' || $q == $delimiter || $q == 'DELIMITER') {
            continue;
        }

        if ($mysqli->query($q) === FALSE) {
            $msg[] = array('error'=>$mysqli->error, 'errno'=> $mysqli->errno, 'query'=> $q );
        }
    }

    return $msg;
}

