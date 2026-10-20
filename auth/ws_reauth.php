<?php

use HHK\Common;
use HHK\sec\Login;
use HHK\sec\ReauthGuard;

/**
 * ws_reauth.php
 *
 * Web Service for step-up re-authentication (see ReauthGuard).
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("../house/homeIncludes.php");

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $login = new Login();
    $login->initHhkSession(CONF_PATH, ciCFG_FILE);
    $dbh = Common::initPDO(TRUE);
} catch (Exception $ex) {
    exit(json_encode(["error" => "Database Access Error."]));
}

$c = filter_var($_REQUEST["cmd"] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$purpose = filter_var($_REQUEST["purpose"] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$events = [];

try {

    switch ($c) {

        case 'status':
            $events = ReauthGuard::status($dbh, $purpose);
            break;

        case 'verify':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $events = ["error" => "Bad request"];
                break;
            }
            ReauthGuard::verify($dbh, $purpose,
                filter_input(INPUT_POST, "secret", FILTER_UNSAFE_RAW) ?? '',
                filter_input(INPUT_POST, "otpMethod", FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
            $events = ["success" => true, "expiresIn" => ReauthGuard::expiresIn($purpose)];
            break;

        case 'sendcode':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $events = ["error" => "Bad request"];
                break;
            }
            ReauthGuard::sendEmailCode($dbh, $purpose);
            $events = ["success" => true];
            break;

        default:
            $events = ["error" => "Bad Command: \"" . $c . "\""];
    }

} catch (Exception $ex) {
    $events = ["error" => $ex->getMessage()];
}

echo json_encode($events);
exit();
