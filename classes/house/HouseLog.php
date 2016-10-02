<?php
/**
 * HouseLog.php
 *
 *
 * @category  House
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of HouseLog
 * @package name
 * @author Eric
 */
class HouseLog {

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

    public static function getInsertText(iTableRS $rs) {

        $logText = array();

        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if ($dbF->logMe() && !is_null($dbF->getNewVal()) && $dbF->getNewVal() != "") {
                    $logText[$dbF->getCol()] =  $dbF->getNewVal();
                }
            }
        }

        return $logText;
    }

    public static function getUpdateText(iTableRS $rs) {

        $logText = array();

        foreach ($rs as $dbF) {
            if (is_a($dbF, "DB_Field")) {

                if ($dbF->logMe() && !is_null($dbF->getNewVal()) && $dbF->getNewVal() != $dbF->getStoredVal()) {

                    $stored = '';
                    if (!is_null($dbF->getStoredVal())) {
                        $stored = $dbF->getStoredVal();
                    }

                    $logText[$dbF->getCol()] = $stored . '|_|' . $dbF->getNewVal();
                }
            }
        }

        return $logText;
    }

    public static function getDeleteText(iTableRS $rs, $idPrimaryKey) {

        $logText = array();

        $logText[$rs->getTableName()] = $idPrimaryKey;

        return $logText;
    }

    protected static function insertLog(PDO $dbh, TableRS $logRS) {

        $rt = EditRS::insert($dbh, $logRS);
        return $rt;
    }

    protected static function encodeLogText($logText) {
        return json_encode($logText);
    }

    protected static function checkLogText($logText) {

        $rtn = FALSE;

        if (is_array($logText) && count($logText) > 0) {
            $rtn = TRUE;
        } else if (is_array($logText) === FALSE && strlen($logText) > 0) {
            $rtn = TRUE;
        }

        return $rtn;
    }

}


?>
