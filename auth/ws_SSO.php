<?php

use HHK\Common;
use HHK\sec\Session;
use HHK\sec\Login;
use HHK\sec\SAML;
use HHK\sec\SysConfig;
use HHK\sec\SecurityComponent;

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
    $login->initHhkSession(CONF_PATH, ciCFG_FILE);
    $uS = Session::getInstance();
    $dbh = Common::initPDO(TRUE);

} catch (InvalidArgumentException $pex) {
    exit ("Database Access Error.");

} catch (Exception $ex) {
    exit ($ex->getMessage());
}

$c = "";
// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
            if(SecurityComponent::is_Admin()){
                $saml = new SAML($dbh, $idpId);
                $events = $saml->getMetadata();
            }else{
                $events = array("Unauthorized");
            }
            break;
        default:
            $events = array("error" => "Bad Command: \"" . $c . "\"");
    }

} catch (PDOException $ex) {
    $uS->ssoLoginError = "Database Error: " . $ex->getMessage();
    header('location:../' . $uS->webSite['Relative_Address']);

} catch (Exception $ex) {
    $uS->ssoLoginError = $ex->getMessage();
    header('location:../' . $uS->webSite['Relative_Address']);
}




if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();