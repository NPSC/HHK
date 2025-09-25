<?php

namespace HHK\Notification\SMS;

use HHK\Member\Address\Phones;
use HHK\SysConst\MemStatus;
use HHK\SysConst\PhonePurpose;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;

abstract class AbstractContacts
{

    protected \PDO $dbh;

    /**
     * Summary of __construct
     * @param \PDO $dbh
     */
    public function __construct(\PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * Summary of getCheckedInGuestPhones
     * @return array
     */
    public function getCheckedInGuestPhones(string $filterField, string $filterVal = ""){
        $query = "select distinct n.idName, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search, r.Type, r.Category, r.Report_Category from stays s JOIN room r ON s.idRoom = r.idRoom join name n on s.idName = n.idName join name_phone np on n.idName = np.idName and np.Phone_Code = :code where s.Status = :status and Phone_Search != '' and n.Member_Status = :memStatus and np.SMS_status = 'opt_in'";
        $params = [
            ":code" => PhonePurpose::Cell,
            ":status" => VisitStatus::CheckedIn,
            ":memStatus" => MemStatus::Active
        ];

        if($filterVal != "") {
            $query .= " and r." . $filterField . " = :filterVal";
            $params[":filterVal"] = $filterVal;
        }

        $stmt = $this->dbh->prepare($query);

        $stmt->execute($params);

        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->stripUnsupportedNumbers($contacts);

        return $contacts;
    }

    /**
     * Summary of getConfirmedReservationGuestPhones
     * @return array
     */
    public function getConfirmedReservationGuestPhones(string $filterField, string $filterVal = ""){
        $query = "select distinct n.idName, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search, rm.Type, rm.Category, rm.Report_Category from reservation_guest rg join reservation r on rg.idReservation = r.idReservation JOIN resource_room rr on r.idResource = rr.idResource JOIN room rm ON rr.idRoom = rm.idRoom join name n on rg.idGuest = n.idName join name_phone np on n.idName = np.idName and np.Phone_Code = :code where r.Status = :status and Phone_Search != '' and n.Member_Status = :memStatus and np.SMS_status = 'opt_in'";
        $params = [
            ":code" => PhonePurpose::Cell,
            ":status" => ReservationStatus::Committed,
            ":memStatus" => MemStatus::Active
        ];

        if($filterVal != "") {
            $query .= " and rm." . $filterField . " = :filterVal";
            $params[":filterVal"] = $filterVal;
        }

        $stmt = $this->dbh->prepare($query);

        $stmt->execute($params);

        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->stripUnsupportedNumbers($contacts);

        return $contacts;
    }

    /**
     * Summary of getConfirmedReservationGuestPhones
     * @return array
     */
    public function getUnConfirmedReservationGuestPhones(string $filterField, string $filterVal = ""){
        $query = "select distinct n.idName, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search, rm.Type, rm.Category, rm.Report_Category from reservation_guest rg join reservation r on rg.idReservation = r.idReservation JOIN resource_room rr on r.idResource = rr.idResource JOIN room rm ON rr.idRoom = rm.idRoom join name n on rg.idGuest = n.idName join name_phone np on n.idName = np.idName and np.Phone_Code = :code where r.Status = :status and Phone_Search != '' and n.Member_Status = :memStatus and np.SMS_status = 'opt_in'";
        $params = [
            ":code" => PhonePurpose::Cell,
            ":status" => ReservationStatus::UnCommitted,
            ":memStatus" => MemStatus::Active
        ];

        if($filterVal != "") {
            $query .= " and rm." . $filterField . " = :filterVal";
            $params[":filterVal"] = $filterVal;
        }

        $stmt = $this->dbh->prepare($query);

        $stmt->execute($params);

        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->stripUnsupportedNumbers($contacts);

        return $contacts;
    }

    /**
     * Summary of getWaitlistReservationGuestPhones
     * @return array
     */
    public function getWaitlistReservationGuestPhones(){
        $query = "select distinct n.idName, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search from reservation_guest rg join reservation r on rg.idReservation = r.idReservation join name n on rg.idGuest = n.idName join name_phone np on n.idName = np.idName and np.Phone_Code = :code where r.Status = :status and Phone_Search != '' and n.Member_Status = :memStatus and np.SMS_status = 'opt_in'";

        $stmt = $this->dbh->prepare($query);

        $stmt->execute([
            ":code" => PhonePurpose::Cell,
            ":status" => ReservationStatus::Waitlist,
            ":memStatus" => MemStatus::Active
        ]);

        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->stripUnsupportedNumbers($contacts);

        return $contacts;
    }

    private function stripUnsupportedNumbers(array &$contacts){
        foreach($contacts as $k=>$contact){
            $phoneAr = Phones::validateAndFormatPhoneNumber($contact["Phone_Num"]);
            if($phoneAr['smsSupported'] == false){
                unset($contacts[$k]);
            }
        }
    }
    
}