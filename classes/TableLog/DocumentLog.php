<?php

namespace HHK\TableLog;

use HHK\Tables\DocumentLogRS;

/**
 * DocumentLog.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DocumentLog extends AbstractTableLog {
    const Document = 'document';



    public static function logDocument(\PDO $dbh, $idDocument, $idPsg, $idName, $idReservation, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $encodedText = self::encodeLogText($logText);

        $logRS = new DocumentLogRS();
        $logRS->Log_Type->setNewVal(self::Document);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idDocument->setNewVal($idDocument);
        $logRS->idPsg->setNewVal($idPsg);
        $logRS->idName->setNewVal($idName);
        $logRS->idReservation->setNewVal($idReservation);
        $logRS->Log_Text->setNewVal($encodedText);
        $logRS->User_Name->setNewVal(($userName != "" ? $userName : "Web"));
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }


}
?>