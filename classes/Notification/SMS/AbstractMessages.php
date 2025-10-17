<?php

namespace HHK\Notification\SMS;

use HHK\Common;
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

abstract class AbstractMessages implements MessagesInterface
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
    n.idName, n.Name_Full, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search, r.Title as "Room", v.Status, if(s.idName = v.idPrimaryGuest,1,0) as "isPrimaryGuest", if(np.Phone_Code is not null and np.SMS_status = "opt_in",1,0) as "isMobile"
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

        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->disableUnsupportedNumbers($contacts);
        return $contacts;
    }

    public function getResvGuestsData(int $idResv){
        $query = 'SELECT DISTINCT
        n.idName, n.Name_Full, n.Name_First, n.Name_Last, np.Phone_Num, np.Phone_Search, rg.Primary_Guest as "isPrimaryGuest", if(np.Phone_Code is not null and np.SMS_status = "opt_in",1,0) as "isMobile"
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

        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->disableUnsupportedNumbers($contacts);
        return $contacts;
    }

    public function getGuestData(int $idName){
        $query = 'SELECT DISTINCT
        n.idName, n.Name_Full, np.Phone_Num, np.Phone_Search, if(np.Phone_Code is not null and np.SMS_status = "opt_in",1,0) as "isMobile", concat("Text ", n.Name_Full) as "dialogTitle"
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

        $contacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->disableUnsupportedNumbers($contacts);
        return $contacts;
    }

    /**
     * Get contacts and metadata for sending a campaign
     * @param string $status
     * @return array
     */
    public function getCampaignGuestsData(string|null $status, string $filterVal = ""){
        $contacts = new Contacts($this->dbh);
        $uS = Session::getInstance();
        $filterField = "";
        $filterOptions = [];

        //Resource grouping
        $rescGroups = Common::readGenLookupsPDO($this->dbh, 'Room_Group');
        if (isset($rescGroups[$uS->CalResourceGroupBy])) {
            $filterField = $uS->CalResourceGroupBy;
            $filterOptions = Common::readGenLookupsPDO($this->dbh, $rescGroups[$uS->CalResourceGroupBy]["Substitute"]);
        }

        switch ($status){
            case "checked_in":
                return ["status"=>$status, "title"=>"Current " . Labels::getString('MemberType', 'visitor', 'Guest') . "s", "filterBy"=> $rescGroups[$uS->CalResourceGroupBy], "filterOptions"=>$filterOptions, "contacts"=>$contacts->getCheckedInGuestPhones($filterField, $filterVal)];
            case "confirmed_reservation":
                return ["status" => $status, "title" => Labels::getString('register', 'reservationTab', 'Confirmed Reservations'), "filterBy"=> $rescGroups[$uS->CalResourceGroupBy], "filterOptions"=>$filterOptions, "contacts" => $contacts->getConfirmedReservationGuestPhones($filterField, $filterVal)];
            case "unconfirmed_reservation":
                return ["status" => $status, "title" => Labels::getString('register', 'unconfirmedTab', 'UnConfirmed Reservations'), "filterBy"=> $rescGroups[$uS->CalResourceGroupBy], "filterOptions"=>$filterOptions, "contacts" => $contacts->getUnConfirmedReservationGuestPhones($filterField, $filterVal)];
            case "waitlist":
                return ["status" => $status, "title" => Labels::getString('register', 'waitlistTab', 'Wait List'), "contacts" => $contacts->getWaitlistReservationGuestPhones()];
            default:
                throw new SmsException("Error sending Campaign: Invalid list type of " . $status);
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
                if ($guest["isMobile"]) {
                    try {

                        $phoneAr = Phones::validateAndFormatPhoneNumber($guest["Phone_Search"]);
                        if($phoneAr['smsSupported']){

                            //upsert contact before send
                            $contact = new Contact($this->dbh, false);
                            $contact->upsert($phoneAr['smsFormat'], $guest["Name_First"], $guest["Name_Last"]);

                            //send message
                            $message = new Message($this->dbh, $phoneAr['smsFormat'], $msgTxt, $subject);
                            $results["success"][$guest["idName"]] = $message->sendMessage();
                            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $guest["Phone_Search"], $uS->smsFrom, "Message sent Successfully", ["msgText" => $msgTxt]);
                        }else{
                            throw new SmsException("SMS is not supported for this phone number: " . $phoneAr['formatted']);
                        }
                    } catch (SmsException $e) {
                        $results["errors"][] = "Failed to send to " . $guest["Name_Full"] . ": " . $e->getMessage();
                        NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $guest["Phone_Search"], $uS->smsFrom, "Failed to send to " . $guest["Name_Full"] . ": " . $e->getMessage(), ["msgText" => $msgTxt]);
                    }
                }
            }

            if(isset($results["success"]) && count($results["success"]) > 0){
                $guestLabel = Labels::getString('MemberType', 'visitor', 'Guest') . (count($results["success"]) > 1 ? "s": "");
                $results["success"] = "Message sent to " . count($results["success"]) . " " . $guestLabel . (isset($guests[0]["Room"]) && strlen($guests[0]["Room"]) > 0 ? " in room " . $guests[0]["Room"] : "") . " successfully";
            }

            if(isset($results["errors"]) && count($results["errors"]) > 0){
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

        $phoneAr = Phones::validateAndFormatPhoneNumber($cell["Unformatted_Phone"]);

        if($phoneAr['smsSupported'] == false){
            throw new SmsException("SMS is not supported for this phone number: " . $phoneAr['formatted']);
        }else if(strlen($phoneAr["smsFormat"]) >= 10){
            $msgs = $this->fetchMessages($phoneAr["smsFormat"]);

            try {
                $contact = new Contact($this->dbh);
                $contact = $contact->fetchContact($phoneAr["smsFormat"]);
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
            throw new SmsException("Mobile number not found for idName " . $idName);
        }
    }

    private function disableUnsupportedNumbers(array &$contacts){
        foreach($contacts as $k=>$contact){
            $phoneAr = Phones::validateAndFormatPhoneNumber($contact["Phone_Num"]);
            if($phoneAr['smsSupported'] == false){
                $contacts[$k]["isMobile"] = 0;
            }
        }
    }

}