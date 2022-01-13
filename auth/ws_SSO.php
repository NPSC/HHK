<?php

use HHK\sec\Session;
use HHK\sec\Login;
use HHK\sec\SAML;
use HHK\sec\SysConfig;

/**
 * ws_SSO.php
 *
 * Web Service for SAML SSO
 *
 * @author    Will Ireland <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("../house/homeIncludes.php");

try {

    $login = new Login();
    $login->initHhkSession(ciCFG_FILE);
    $uS = Session::getInstance();
    $dbh = initPDO(TRUE);
    SysConfig::getCategory($dbh, $uS, '"sso"', 'sys_config');

} catch (InvalidArgumentException $pex) {
    exit ("Database Access Error.");

} catch (Exception $ex) {
    exit ($ex->getMessage());
}

$c = "";
// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$idpId = "";
if (isset($_REQUEST["idpId"])) {
    $idpId = intval(filter_var($_REQUEST["idpId"], FILTER_SANITIZE_NUMBER_INT), 10);
}

$uS = Session::getInstance();

$events = array();

try {

    switch ($c) {

        case 'login':
            $saml = new SAML($dbh, $idpId);
            $saml->login();
            break;
        case 'acs':
            $saml = new SAML($dbh, $idpId);
            $events = $saml->acs();
            break;
        case 'metadata':
            $saml = new SAML($dbh, $idpId);
            $events = $saml->getMetadata();
            break;
        default:
            $events = array("error" => "Bad Command: \"" . $c . "\"");
    }

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage());

} catch (Exception $ex) {
    $events = array("error" => "Programming Error: " . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();
?>