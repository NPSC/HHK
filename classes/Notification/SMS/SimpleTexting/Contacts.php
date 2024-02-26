<?php

namespace HHK\Notification\SMS\SimpleTexting;

use GuzzleHttp\Exception\ClientException;
use HHK\Exception\SmsException;
use HHK\Notification\SMS\AbstractContacts;

Class Contacts extends AbstractContacts{
    protected Settings $settings;

    public function __construct(\PDO $dbh, bool $validateSettings = false){
        parent::__construct($dbh);
        $this->settings = new Settings($dbh, $validateSettings);
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

    public function syncContacts(string $status, array $listIds = []){

        $listIds = (count($listIds) == 0 ? [$this->settings->getSmsListId()] : $listIds);
        $phones = [];
        
        switch ($status){
            case "checked_in":
                $phones = $this->getCheckedInGuestPhones();
                break;
            case "confirmed_reservations":
                $phones = $this->getConfirmedReservationGuestPhones();
                break;
            case "unconfirmed_reservations":
                $phones = $this->getUnConfirmedReservationGuestPhones();
                break;
            case "waitlist":
                $phones = $this->getWaitlistReservationGuestPhones();
                break;
            default:
                return false;
        }

        $contacts = ["listReplacement"=> false, "updates" => []];
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

            $respArr = json_decode($response->getBody(), true);
            if (isset($respArr["id"])) {
                $batchId = $respArr["id"];
            } else {
                throw new SmsException("Error syncing contacts: batch ID not found");
            }
        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error syncing contacts: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Error syncing contacts: Error " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
            }
        }


        //check status
        for ($i = 0; $i < 5; $i++){
            sleep(5);
            $status = $this->getBatchProgress($batchId);
            
            if($status != "IN_PROGRESS"){
                return $status;
            }
        }

        throw new SmsException("Syncing is taking longer than expected, check contacts on SimpleTexting dashboard. Last detected status: " . $status);
    }

    protected function getBatchProgress(string $batchId){
        try {
            $client = $this->settings->getClient();
            $response = $client->get("contacts-batch/batch-update/" . $batchId);

            $respArr = json_decode($response->getBody(), true);
            if (isset($respArr["status"])) {
                return $respArr["status"];
            } else {
                throw new SmsException("Error syncing contacts: batch status not found");
            }
        }catch(ClientException $e){
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

}