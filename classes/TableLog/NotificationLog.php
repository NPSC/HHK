<?php
namespace HHK\TableLog;
use HHK\Tables\Notification_LogRS;

class NotificationLog extends AbstractTableLog {

    public static function logSMS(\PDO $dbh, string $service, string $username, string $to, string $from, string $logText, array $details = []){

        $logRS = new Notification_LogRS();
        $logRS->Log_Type->setNewVal("SMS");
        $logRS->Sub_Type->setNewVal($service);
        $logRS->username->setNewVal($username);
        $logRS->To->setNewVal($to);
        $logRS->From->setNewVal($from);
        $logRS->Log_Text->setNewVal($logText);
        $logRS->Log_Details->setNewVal(json_encode($details));
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    public static function logEmail(\PDO $dbh, string $username, string $to, string $from, string $logText, array $details = []){

        $logRS = new Notification_LogRS();
        $logRS->Log_Type->setNewVal("Email");
        $logRS->Sub_Type->setNewVal("");
        $logRS->username->setNewVal($username);
        $logRS->To->setNewVal($to);
        $logRS->From->setNewVal($from);
        $logRS->Log_Text->setNewVal($logText);
        $logRS->Log_Details->setNewVal(json_encode($details));
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

}