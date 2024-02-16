<?php

namespace HHK\Notification\SMS;

use GuzzleHttp\Exception\ClientException;
use HHK\Exception\RuntimeException;
use HHK\Exception\SmsException;
use HHK\Member\Address\Phones;
use HHK\Member\IndivMember;
use HHK\Notification\SMS\SimpleTexting\Message;
use HHK\Notification\SMS\SimpleTexting\Contact;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\SysConst\MemBasis;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\PhonePurpose;
use HHK\SysConst\VisitStatus;
use HHK\Notification\SMS\SimpleTexting\Contacts;
use HHK\TableLog\NotificationLog;

abstract class AbstractMessages
{

    protected \PDO $dbh;
    protected string $accountPhone;

    public function __construct(\PDO $dbh, string $accountPhone = "")
    {
        $this->dbh = $dbh;
        $this->accountPhone = $accountPhone;
    }

    public function getVisitGuestsData(int $idVisit, int $idSpan){
        $query = 'SELECT DISTINCT
    n.idName, n.Name_Full, np.Phone_Num, np.Phone_Search, r.Title as "Room", v.Status, if(s.idName = v.idPrimaryGuest,1,0) as "isPrimaryGuest", if(np.Phone_Code is null or np.SMS_status = "" or np.SMS_status = "opt_out",0,1) as "isMobile"
FROM
    stays s
		join 
	visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
        join
    resource r on v.idResource = r.idResource
        LEFT JOIN
    name n ON s.idName = n.idName
        left JOIN
    name_phone np ON s.idName = np.idName and np.Phone_Code = "mc"
WHERE
    s.idVisit = :idVisit AND s.Visit_Span = :idSpan
        AND s.Status = :status
        order by isMobile desc, isPrimaryGuest desc';

        $stmt = $this->dbh->prepare($query);

        $stmt->execute([
            ':idVisit' => $idVisit,
            ':idSpan' => $idSpan,
            ':status' => VisitStatus::Active
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getResvGuestsData(int $idResv){
        $query = 'SELECT DISTINCT
        n.idName, n.Name_Full, np.Phone_Num, np.Phone_Search, rg.Primary_Guest as "isPrimaryGuest", if(np.Phone_Code is null or np.SMS_status = "" or np.SMS_status = "opt_out",0,1) as "isMobile"
    FROM
        reservation_guest rg
            LEFT JOIN
        name n ON rg.idGuest = n.idName
            left JOIN
        name_phone np ON rg.idGuest = np.idName and np.Phone_Code = "mc"
    WHERE
        rg.idReservation = :idResv
            order by isMobile desc, isPrimaryGuest desc';

        $stmt = $this->dbh->prepare($query);

        $stmt->execute([
            ':idResv' => $idResv
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getGuestData(int $idName){
        $query = 'SELECT DISTINCT
        n.idName, n.Name_Full, np.Phone_Num, np.Phone_Search, if(np.Phone_Code is null or np.SMS_status = "" or np.SMS_status = "opt_out",0,1) as "isMobile", concat("Text ", n.Name_Full) as "dialogTitle"
    FROM
        name n
            left JOIN
        name_phone np ON n.idName = np.idName and np.Phone_Code = "mc"
    WHERE
        n.idName = :idName
            order by isMobile desc';

        $stmt = $this->dbh->prepare($query);

        $stmt->execute([
            ':idName' => $idName
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCampaignGuestsData(string $status){
        $contacts = new Contacts($this->dbh);

        switch ($status){
            case "checked_in":
                return ["status"=>$status, "title"=>"Current " . Labels::getString('MemberType', 'visitor', 'Guest') . "s", "contacts"=>$contacts->getCheckedInGuestPhones()];
            case "confirmed_reservation":
                return ["status" => $status, "title" => Labels::getString('register', 'reservationTab', 'Confirmed Reservations'), "contacts" => $contacts->getConfirmedReservationGuestPhones()];
            case "waitlist":
                return ["status" => $status, "title" => Labels::getString('register', 'waitlistTab', 'Wait List'), "contacts" => $contacts->getWaitlistReservationGuestPhones()];
            default:
                return false;
        }
    }

    /**
     * Summary of sendVisitMessage
     * @param int $idVisit
     * @param int $idSpan
     * @param string $msgTxt
     * @param string $subject
     * @return array
     */
    public function sendVisitMessage(int $idVisit, int $idSpan, string $msgTxt, string $subject = ""){
        $guests = $this->getVisitGuestsData($idVisit, $idSpan);

        return $this->sendBulkMessage($guests, $msgTxt, $subject);
    }

    /**
     * Summary of sendResvMessage
     * @param int $idResv
     * @param string $msgTxt
     * @param string $subject
     * @return array
     */
    public function sendResvMessage(int $idResv, string $msgTxt, string $subject = ""){
        $guests = $this->getResvGuestsData($idResv);

        return $this->sendBulkMessage($guests, $msgTxt, $subject);
    }

    /**
     * Send message to array of guests
     * @param array $guests
     * @param string $msgTxt
     * @param string $subject
     * @return array
     */
    protected function sendBulkMessage(array $guests, string $msgTxt, string $subject = ""){
        $results = array();
        $uS = Session::getInstance();
        if (count($guests) > 0){
            foreach($guests as $guest){
                try {
                    $message = new Message($this->dbh, $guest["Phone_Search"], $msgTxt, $subject);
                    $results["success"][$guest["idName"]] = $message->sendMessage();
                    NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $guest["Phone_Search"], $uS->smsFrom, "Message sent Successfully", ["msgText"=>$msgTxt]);
                }catch(SmsException $e){
                    $results["errors"][] = "Failed to send to " . $guest["Name_Full"] . ": " . $e->getMessage();
                    NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $guest["Phone_Search"], $uS->smsFrom, "Failed to send to " . $guest["Name_Full"] . ": " . $e->getMessage(), ["msgText"=>$msgTxt]);
                }
            }

            if(count($results["success"]) > 0){
                $guestLabel = Labels::getString('MemberType', 'visitor', 'Guest') . (count($results["success"]) > 1 ? "s": "");
                $results["success"] = "Message sent to " . count($results["success"]) . " " . $guestLabel . " in room " . $guests[0]["Room"] . " successfully";
            }

            if(count($results["errors"]) > 0){
                $results["error"] = implode("<br>", $results["errors"]);
            }

        } else {
            return ['error' => "No guests found"];
        }
        return $results;
    }

    public function getMessages(int $idName){
        $uS = Session::getInstance();
        $name = new IndivMember($this->dbh, MemBasis::Indivual, $idName);
        $phones = new Phones($this->dbh, $name, $uS->nameLookups[GLTableNames::PhonePurpose]);
        $cell = $phones->get_Data(PhonePurpose::Cell);

        if(strlen($cell["Unformatted_Phone"]) >= 10){
            $msgs = $this->fetchMessages($cell["Unformatted_Phone"]);

            try {
                $contact = new Contact($this->dbh);
                $contact = $contact->fetchContact($cell["Unformatted_Phone"]);
            }catch(\Exception $e){
                $contact = [];
            }

            $subscriptionStatus = true;
            if (isset($contact["subscriptionStatus"]) && $contact["subscriptionStatus"] === "OPT_OUT") {
                $subscriptionStatus = false;
            }

            return [
                "idName" => $name->get_idName(),
                "Name_Full" => $name->get_fullName(),
                "Name_First" => $name->get_firstName(),
                "siteName"=>html_entity_decode($uS->siteName),
                "phone" => $cell["Phone_Num"],
                "totalPages" => $msgs['totalPages'],
                "totalMsgs" => $msgs['totalElements'],
                "msgs" => array_reverse($msgs['content']),
                "subscriptionStatus" => $subscriptionStatus,
            ];
        }else{
            throw new RuntimeException("Mobile number not found for idName " . $idName);
        }
    }

}