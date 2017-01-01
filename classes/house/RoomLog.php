<?php
/**
 * RoomLog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoomLog
 * @package name
 * @author Eric
 */
class RoomLog extends TableLog {

    const Resource = "resource";
    const Room = "room";


    public static function logResource(PDO $dbh, $idResource, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal(self::Resource);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->Id1->setNewVal($idResource);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logRoom(PDO $dbh, $idRoom, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal(self::Room);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->Id1->setNewVal($idRoom);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logCleaning(PDO $dbh, $idResource, $idRoom, $type, $status, $notes, $lastCleaned, $username) {

        if ($idResource > 0 || $idRoom > 0) {

            $logRs = new CleaningLogRS();
            $logRs->idResource->setNewVal($idResource);
            $logRs->idRoom->setNewVal($idRoom);
            $logRs->Type->setNewVal($type);
            $logRs->Status->setNewVal($status);
            $logRs->Notes->setNewVal($notes);
            $logRs->Last_Cleaned->setNewVal($lastCleaned);
            $logRs->Username->setNewVal($username);

            return self::insertLog($dbh, $logRs);
        }
    }
}

