<?php

namespace HHK\Notification\SMS;

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
    public function getCheckedInGuestPhones(){
        $query = "select distinct n.idName, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search from stays s join name n on s.idName = n.idName join name_phone np on n.idName = np.idName and np.Phone_Code = :code where s.Status = :status and Phone_Search != '' and n.Member_Status = :memStatus and np.is_SMS = 1";

        $stmt = $this->dbh->prepare($query);

        $stmt->execute([
            ":code" => PhonePurpose::Cell,
            ":status" => VisitStatus::CheckedIn,
            ":memStatus" => MemStatus::Active
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Summary of getConfirmedReservationGuestPhones
     * @return array
     */
    public function getConfirmedReservationGuestPhones(){
        $query = "select distinct n.idName, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search from reservation_guest rg join reservation r on rg.idReservation = r.idReservation join name n on rg.idGuest = n.idName join name_phone np on n.idName = np.idName and np.Phone_Code = :code where r.Status = :status and Phone_Search != '' and n.Member_Status = :memStatus and np.is_SMS = 1";

        $stmt = $this->dbh->prepare($query);

        $stmt->execute([
            ":code" => PhonePurpose::Cell,
            ":status" => ReservationStatus::Committed,
            ":memStatus" => MemStatus::Active
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);        
    }

    /**
     * Summary of getWaitlistReservationGuestPhones
     * @return array
     */
    public function getWaitlistReservationGuestPhones(){
        $query = "select distinct n.idName, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search from reservation_guest rg join reservation r on rg.idReservation = r.idReservation join name n on rg.idGuest = n.idName join name_phone np on n.idName = np.idName and np.Phone_Code = :code where r.Status = :status and Phone_Search != '' and n.Member_Status = :memStatus and np.is_SMS = 1";

        $stmt = $this->dbh->prepare($query);

        $stmt->execute([
            ":code" => PhonePurpose::Cell,
            ":status" => ReservationStatus::Waitlist,
            ":memStatus" => MemStatus::Active
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
}