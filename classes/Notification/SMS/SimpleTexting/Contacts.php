<?php

namespace HHK\Notification\SMS\SimpleTexting;

use GuzzleHttp\Exception\ClientException;
use HHK\Exception\SmsException;
use HHK\Notification\SMS\AbstractContacts;
use HHK\sec\Session;
use HHK\TableLog\NotificationLog;

Class Contacts extends AbstractContacts{
    protected Settings $settings;
    protected string|null $batchId;

    public function __construct(\PDO $dbh, bool $validateSettings = false, string|null $batchId = null){
        parent::__construct($dbh);
        $this->settings = new Settings($dbh, $validateSettings);
        $this->batchId = $batchId;
    }

    public function fetchContacts(string $status = ""){
        $client = $this->settings->getClient();

        try {
            $response = $client->get("contacts");

            $respArr = json_decode($response->getBody(), true);
            if (is_array($respArr["content"])) {
                if ($status == "") {
                    return $respArr["content"];
                } else {
                    return $this->filterContactsByStatus($status, $respArr['content']);
                }
            } else {
                throw new SmsException("Error getting contacts: content not found on remote");
            }
        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error getting contacts: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Error getting contacts: Error " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
            }
        }
    }

    protected function filterContactsByStatus(string $status, array $contacts){
        $filtered = [];

        foreach($contacts as $contact){
            if($contact["customFields"]["hhk_status"] == $status){
                $filtered[] = $contact;
            }
        }

        return $filtered;
    }

    public function syncContacts(string|null $status, array $listIds = []){

        $listIds = (count($listIds) == 0 ? [$this->settings->getSmsListName()] : $listIds);
        $phones = [];

        if($this->batchId == null){

            switch ($status){
                case "checked_in":
                    $phones = $this->getCheckedInGuestPhones();
                    break;
                case "confirmed_reservation":
                    $phones = $this->getConfirmedReservationGuestPhones();
                    break;
                case "unconfirmed_reservation":
                    $phones = $this->getUnConfirmedReservationGuestPhones();
                    break;
                case "waitlist":
                    $phones = $this->getWaitlistReservationGuestPhones();
                    break;
                default:
                    return false;
            }
    
            $contacts = ["listsReplacement"=> false, "updates" => []];
            foreach($phones as $phone){
                $contact = [
                    "contactPhone"=>$phone["Phone_Search"],
                    "firstName"=>$phone["Name_First"],
                    "lastName"=>$phone["Name_Last"]
                ];
    
                if(count($listIds) > 0){
                    $contact["listIds"] = $listIds;
                }
    
                $contacts["updates"][] = $contact;
            }
    
            try {
                $client = $this->settings->getClient();
                $response = $client->post("contacts-batch/batch-update",
                    ["json"=>$contacts]
                );

                $uS = Session::getInstance();
                NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, "", $uS->smsFrom, "Syncing contacts", ["listIds"=>$listIds, "response"=>json_decode($response->getBody()->getContents(), true)]);
    
                $respArr = json_decode($response->getBody(), true);
                if (isset($respArr["id"])) {
                    $this->batchId = $respArr["id"];
                } else {
                    throw new SmsException("Error syncing contacts: batch ID not found");
                }
            }catch(ClientException $e){
                NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, "", $uS->smsFrom, "Syncing contacts", ["listIds"=>$listIds, "response"=>json_decode($e->getResponse()->getBody()->getContents(), true)]);
                $respArr = json_decode($e->getResponse()->getBody(), true);
    
                if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                    throw new SmsException("Error syncing contacts: " . $respArr["status"] . ": " . $respArr["message"]);
                } else {
                    throw new SmsException("Error syncing contacts: Error " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
                }
            }
        }
        


        //check status
        if($this->batchId !== null){
            for ($i = 0; $i < 5; $i++){
                
                $status = $this->getBatchProgress($this->batchId);
                
                if($status["status"] != "IN_PROGRESS"){
                    return $status;
                }
                sleep(5);
            }

            return false;
        }else{
            throw new SmsException("Could not sync contacts: unknown batch ID, please try again");
        }
        
    }

    protected function getBatchProgress(string $batchId){
        try {
            $client = $this->settings->getClient();
            
            $response = $client->get("contacts-batch/batch-update/" . $batchId);

            $uS = Session::getInstance();
            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, "", $uS->smsFrom, "Syncing contacts: checking batch status", ["batchId"=>$batchId, "response"=>json_decode($response->getBody()->getContents(), true)]);

            $respArr = json_decode($response->getBody(), true);
            if (isset($respArr["status"]) && isset($respArr["results"])) {
                $warnings = [];
                foreach($respArr["results"] as $result){
                    if(isset($result["errorCode"])){
                        $warnings[] = $result["contactPhone"] . ": " . $result["errorMessage"];
                    }
                }
                return ["status"=>$respArr["status"], "warnings"=>$warnings];
            } else {
                throw new SmsException("Error syncing contacts: batch status not found");
            }
        }catch(ClientException $e){
            $uS = Session::getInstance();
            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, "", $uS->smsFrom, "Syncing contacts", ["batchId"=>$batchId, "response"=>json_decode($e->getResponse()->getBody()->getContents(), true)]);

            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error syncing contacts: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Error syncing contacts: Error " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
            }
        }
    }

    public function deleteList(string $listId){

        try {
            $client = $this->settings->getClient();
            $response = $client->delete("contact-lists/".$listId);
            return true;
        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error deleting contact list: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Error deleting contact list: Error " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
            }
        }
    }

    public function getBatchId(){
        return $this->batchId;
    }

}