<?php
/**
 * VisitLog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require CLASSES . 'TableLog.php';

class VisitLog extends TableLog {

    const PSG = 'psg';
    const NameGuest = "nameguest";
    const Registration = "registration";
    const Visit = 'visit';
    const Stay = 'stay';


    public static function logPsg(\PDO $dbh, $idPsg, $idPatient, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $encodedText = self::encodeLogText($logText);

        $logRS = new Visit_LogRS();
        $logRS->Log_Type->setNewVal(self::PSG);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idPsg->setNewVal($idPsg);
        $logRS->idName->setNewVal($idPatient);
        $logRS->Log_Text->setNewVal($encodedText);
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logNameGuest(\PDO $dbh, $idPsg, $idGuest, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $logRS = new Visit_LogRS();
        $logRS->Log_Type->setNewVal(self::NameGuest);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idPsg->setNewVal($idPsg);
        $logRS->idName->setNewVal($idGuest);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logRegistration(\PDO $dbh, $idPsg, $idRegistration, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $logRS = new Visit_LogRS();
        $logRS->Log_Type->setNewVal(self::Registration);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idPsg->setNewVal($idPsg);
        $logRS->idRegistration->setNewVal($idRegistration);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logVisit(\PDO $dbh, $idVisit, $Span, $idResc, $idRegistration, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $logRS = new Visit_LogRS();
        $logRS->Log_Type->setNewVal(self::Visit);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idVisit->setNewVal($idVisit);
        $logRS->Span->setNewVal($Span);
        $logRS->idRr->setNewVal($idResc);
        $logRS->idRegistration->setNewVal($idRegistration);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }

    public static function logStay(\PDO $dbh, $idVisit, $Span, $idRoom, $idStay, $idGuest, $idRegistration, $logText, $subType, $userName) {

        if (self::checkLogText($logText) === FALSE) {
            return TRUE;
        }

        $logRS = new Visit_LogRS();
        $logRS->Log_Type->setNewVal(self::Stay);
        $logRS->Sub_Type->setNewVal($subType);
        $logRS->idVisit->setNewVal($idVisit);
        $logRS->idStay->setNewVal($idStay);
        $logRS->idRegistration->setNewVal($idRegistration);
        $logRS->Span->setNewVal($Span);
        $logRS->idRr->setNewVal($idRoom);

        $logRS->idName->setNewVal($idGuest);
        $logRS->Log_Text->setNewVal(self::encodeLogText($logText));
        $logRS->User_Name->setNewVal($userName);

        return self::insertLog($dbh, $logRS);

    }


}

class ReservationLog extends TableLog {

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

        return self::insertLog($dbh, $logRS);

    }


}
