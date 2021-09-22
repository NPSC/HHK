<?php

namespace HHK\TableLog;

use HHK\Tables\Reservation\Reservation_LogRS;

/**
 * VisitLog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReservationLog extends AbstractTableLog {

    const HospitalStay = 'hospitalStay';
    const Reservation = 'reservation';
    const FinApplication = 'fin_application';


    public static function logReservation(\PDO $dbh, $idResv, $idReg, $idhstay, $idResc, $idRoomRate, $idGuest, $logText, $subType, $userName, $idPsg = 0) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $encodedText = self::encodeLogText($logText);

        $logRS = new Reservation_LogRS();
        $logRS->Log_Type->setNewVal(self::Reservation);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idReservation->setNewVal($idResv);
        $logRS->idRegistration->setNewVal($idReg);
        $logRS->idHospital_stay->setNewVal($idhstay);
        $logRS->idResource->setNewVal($idResc);
        $logRS->idRoom_rate->setNewVal($idRoomRate);
        $logRS->idName->setNewVal($idGuest);
        $logRS->idPsg->setNewVal($idPsg);

        $logRS->Log_Text->setNewVal($encodedText);
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }

    public static function logFinAppl(\PDO $dbh, $idResv, $idGuest, $idRoomRate, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $encodedText = self::encodeLogText($logText);

        $logRS = new Reservation_LogRS();
        $logRS->Log_Type->setNewVal(self::FinApplication);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idReservation->setNewVal($idResv);
        $logRS->idName->setNewVal($idGuest);
        $logRS->idRoom_rate->setNewVal($idRoomRate);
        $logRS->Log_Text->setNewVal($encodedText);
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }


    public static function logHospStay(\PDO $dbh, $idhstay, $idPatient, $idAgent, $idPsg,  $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $encodedText = self::encodeLogText($logText);

        $logRS = new Reservation_LogRS();
        $logRS->Log_Type->setNewVal(self::HospitalStay);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idName->setNewVal($idPatient);
        $logRS->idAgent->setNewVal($idAgent);
        $logRS->idHospital_stay->setNewVal($idhstay);
        $logRS->idPsg->setNewVal($idPsg);
        $logRS->Log_Text->setNewVal($encodedText);
        $logRS->User_Name->setNewVal($userName);
        $logRS->Timestamp->setNewVal(date("Y-m-d H:i:s"));

        return self::insertLog($dbh, $logRS);

    }


}
?>