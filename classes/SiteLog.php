<?php

namespace HHK;

use HHK\sec\Session;

/**
 * SiteLog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of SiteLog
 *
 * @author Eric
 */

class SiteLog {

    public static function logPatch(\PDO $dbh, $logText, $GIT_Id = '') {


        self::writeLog($dbh, "Patch", $logText, $GIT_Id);

    }

    public static function logDbDownload(\PDO $dbh, $logText, $GIT_Id = '') {


        self::writeLog($dbh, "DB", $logText, $GIT_Id);

    }

    public static function writeLog(\PDO $dbh, $logType, $logText, $gitId) {

        if ($logText == '' || $logType == '') {
            return;
        }

        $encodedText = addslashes($logText);

        // get session instance
        $uS = Session::getInstance();

        $remoteIp = '';

        if (filter_has_var(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR')) {
            $remoteIp = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP);
        } else {
            $remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        }

        $dbh->exec("insert into syslog values ('$logType', '" . $uS->username . "', '$remoteIp', '$encodedText', '" . $uS->ver . "', '" . $gitId  . "', now());");
    }

}
?>