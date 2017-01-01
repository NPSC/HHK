<?php
/*
 * HouseLog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of HouseLog
 *
 * @author Eric
 */
class HouseLog extends TableLog {

    public static function logGenLookups(\PDO $dbh, $tableName, $code, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('gen_lookups');
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->Str1->setNewVal($tableName);
        $logRS->Str2->setNewVal($code);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logSysConfig(\PDO $dbh, $key, $value, $logText, $userName) {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('sys_config');
        $logRS->Sub_Type->setNewVal('update');
        $logRS->Str1->setNewVal($key);
        $logRS->Str2->setNewVal($value);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logSiteConfig(\PDO $dbh, $key, $value, $userName, $subType = 'update') {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('Site Config File');
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->Str1->setNewVal($key);
        $logRS->Str2->setNewVal($value);
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

}
