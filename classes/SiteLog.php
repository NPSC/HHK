<?php

/**
 * SiteLog.php
 *
 *
 *
 * @category  Configuration
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of SiteLog
 *
 * @author Eric
 */
class SiteLog {

    public static function logPatch(PDO $dbh, $logText, $GIT_Id = '') {


        self::writeLog($dbh, "Patch", $logText, $GIT_Id);

    }

    public static function writeLog(\PDO $dbh, $logType, $logText, $gitId) {

        if ($logText == '' || $logType == '') {
            return;
        }

        $encodedText = addslashes($logText);

        // get session instance
        $uS = Session::getInstance();

        $remoteIp = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);

        $dbh->exec("insert into syslog values ('$logType', '" . $uS->username . "', '$remoteIp', '$encodedText', '" . $uS->ver . "', '" . $gitId  . "', now());");
    }

}
