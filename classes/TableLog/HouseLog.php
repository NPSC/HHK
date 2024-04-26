<?php
namespace HHK\TableLog;
use HHK\Tables\House\House_LogRS;
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
class HouseLog extends AbstractTableLog {

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
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    public static function logSysConfig(\PDO $dbh, $key, $value, $logText, $userName, $type = 'sys_config') {

    	$logRS = new House_LogRS();
    	$logRS->Log_Type->setNewVal($type);
    	$logRS->Sub_Type->setNewVal('update');
    	$logRS->Str1->setNewVal($key);
    	$logRS->Str2->setNewVal($value);
    	$logRS->Log_Text->setNewVal(self::encodeLogText($logText));
    	$logRS->User_Name->setNewVal($userName);
    	$logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

    	return self::insertLog($dbh, $logRS);

    }

    public static function logSiteConfig(\PDO $dbh, $key, $value, $userName, $subType = 'update') {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('Site Config File');
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->Str1->setNewVal($key);
        $logRS->Str2->setNewVal($value);
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    public static function logGeneral(\PDO $dbh, $table, $id, $logText, $userName, $subType) {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal($table);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->Id1->setNewVal($id);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);
    }


    public static function logRoomRate(\PDO $dbh, $action, $idRoomRate,  $logText, $userName) {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('Room Rate');
        $logRS->Sub_Type->setNewVal($action);
        $logRS->Id1->setNewVal($idRoomRate);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    public static function logFinAssist(\PDO $dbh, $action, $idRateBreakpoint,  $logText, $userName) {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('Financial Assistance');
        $logRS->Sub_Type->setNewVal($action);
        $logRS->Id1->setNewVal($idRateBreakpoint);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    /**
     * Log external API calls
     *
     * @param \PDO $dbh
     * @param string $apiService (google, salesforce, etc)
     * @param bool $isSuccess
     * @param string $logText
     * @param string $userName
     * @return number
     */
    public static function logApi(\PDO $dbh, string $apiService, bool $isSuccess, string $logText, string $userName) {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('API');
        $logRS->Sub_Type->setNewVal($apiService);
        $logRS->Str1->setNewVal(($isSuccess ? "success":"error"));
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    /**
     * Log Downloads
     *
     * @param \PDO $dbh
     * @param string $type report, invoice, etc
     * @param string $fileType Word, Excel, etc
     * @param string $logText
     * @param string $userName
     * @return number
     */
    public static function logDownload(\PDO $dbh, string $type, string $fileType, string $logText, string $userName) {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('Download');
        $logRS->Sub_Type->setNewVal($type);
        $logRS->Log_Text->setNewVal($logText);
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    /**
     * Log errors/exceptions
     *
     * @param \PDO $dbh
     * @param string $apiService (google, salesforce, etc)
     * @param bool $isSuccess
     * @param string $logText
     * @param string $userName
     * @return number
     */
    public static function logError(\PDO $dbh, string $exceptionType, string $logText, string $userName) {

        $logRS = new House_LogRS();
        $logRS->Log_Type->setNewVal('Error');
        $logRS->Sub_Type->setNewVal($exceptionType);
        $logRS->Str1->setNewVal("error");
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

}
