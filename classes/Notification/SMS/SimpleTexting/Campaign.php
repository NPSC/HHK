<?php

namespace HHK\Notification\SMS\SimpleTexting;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use HHK\Exception\SmsException;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\TableLog\NotificationLog;

class Campaign {

    protected \PDO $dbh;
    protected Settings $settings;
    protected Message $message;

    public function __construct(\PDO $dbh, string $text, string $fallbackText = ""){
        $this->dbh = $dbh;
        $this->settings = new Settings($dbh, true);
        $this->message = new Message($dbh, "", $text, '', $fallbackText);
    }

    protected function sendCampaign(string $listId, string $listName){
        $client = $this->settings->getClient();
        $uS = Session::getInstance();

        //send campaign
        $requestArray = [
            "title"=>$listName,
            "accountPhone"=>$this->settings->getAccountPhone(),
            "listIds"=>[$listId],
            "messageTemplate"=>$this->message->getMessageTemplate()
        ];

        try {
            $response = $client->post('campaigns', [
                'json' => $requestArray
            ]);
        }catch(BadResponseException $e){
            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $listName, $uS->smsFrom, "Error sending campaign", ["msgText" => $this->message->getMessageTemplate()["text"], "listId"=>$listId, "listName"=>$listName, "request"=>$requestArray, "response"=>$e->getResponse()->getBody()->getContents()]);
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error sending campaign: " . $respArr["status"] . ": " . $respArr["message"]);
            } else if(is_array($respArr) && isset($respArr["message"])){
                throw new SmsException("Error sending campaign: " . $respArr["message"]);
            }else{
                throw new SmsException("Error sending campaign: Error " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
            }
        }


        if($response->getStatusCode() == 201){
            $body = $response->getBody();

            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $listName, $uS->smsFrom, "Campaign sent Successfully", ["msgText" => $this->message->getMessageTemplate()["text"], "listId"=>$listId, "listName"=>$listName, "request"=>$requestArray, "response"=>json_decode($response->getBody()->getContents(), true)]);

            try{
                return json_decode($body, true);
            }catch(\Exception $e){
                return ["error"=>"Unable to parse response: " . $e->getMessage()];
            }
        }else{
            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $listName, $uS->smsFrom, "Error sending campaign", ["msgText" => $this->message->getMessageTemplate()["text"], "listId"=>$listId, "listName"=>$listName, "request"=>$requestArray, "response"=>json_decode($response->getBody()->getContents(), true)]);
            throw new SmsException("Invalid response received while trying to send campaign. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
        }

    }

    /**
     * Summary of prepareAndSendCampaign
     * @param string|null $status
     * @throws \HHK\Exception\SmsException
     * @return array
     */
    public function prepareAndSendCampaign(string|null $status, string $filterVal = ""){
        $client = $this->settings->getClient();
        $uS = Session::getInstance();

        $messages = new Messages($this->dbh);
        $guestData = $messages->getCampaignGuestsData($status, $filterVal);
        $campaignListName = $this->makeContactListName($guestData, $filterVal);

        if(isset($guestData["contacts"]) && count($guestData["contacts"]) > 0){
            //make contact list
            try {
                $response = $client->post('contact-lists', [
                    'json' => ["name"=>$campaignListName]
                ]);
                NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $campaignListName, $uS->smsFrom, "Creating campaign list", ["msgText" => $this->message->getMessageTemplate()["text"], "listName"=>$campaignListName, "response"=>json_decode($response->getBody()->getContents(), true)]);
            }catch(ClientException $e){
                NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $campaignListName, $uS->smsFrom, "Error creating campaign list", ["msgText" => $this->message->getMessageTemplate()["text"], "listName"=>$campaignListName, "request"=>json_decode($e->getRequest()->getBody()->getContents(), true), "response"=>json_decode($e->getResponse()->getBody()->getContents(), true)]);
                $respArr = json_decode($e->getResponse()->getBody(), true);

                if (is_array($respArr) && isset($respArr["message"])) {
                    throw new SmsException("Error creating contact list: " . $respArr["message"] . (isset($respArr["details"]) ? $respArr["details"]:""));
                } else {
                    throw new SmsException("Error creating contact list: Error " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
                }
            }

            if($response->getStatusCode() == 201){
                $respArr = json_decode($response->getBody(), true);
                
                if(isset($respArr["id"])){
                    $campaignListId = $respArr["id"];
                    
                    //sync contacts to new list
                    $contacts = new Contacts($this->dbh);
                    $syncStatus = $contacts->syncContacts($status, [$this->settings->getSmsListName(), $campaignListId]);

                    if(is_array($syncStatus) && strtolower($syncStatus["status"]) == "done"){
                        $this->sendCampaign($campaignListId, $campaignListName);
                        $msg = "Message sent successfully";
                        if(count($syncStatus["warnings"]) > 0){
                            $msg .= ", however, the following contacts may not have been included: <br>" . implode("<br>", $syncStatus["warnings"]);
                        }
                        return ["success" => $msg];
                    }else{
                        return ["info" => "It's taking longer than expected to sync contacts, would you like to continue to wait?", "batchId"=>$contacts->getBatchId(), "campaignListId"=>$campaignListId, "campaignListName"=>$campaignListName];
                    }
                }else{
                    throw new SmsException("Error sending campaign message: Could not create contact list");
                }
            }else{
                throw new SmsException("Invalid response received while trying to send message: Status code: " . $response->getStatusCode());
            }
        }else{
            throw new SmsException("No " . Labels::getString("MemberType", "visitor", "Guest") . "s have opted in to receive text messages");
        }
    }

    public function checkBatchAndSendCampaign(string|null $batchId, string $campaignListId, string $campaignListName){
        $contacts = new Contacts($this->dbh, false, $batchId);
        
        $syncStatus = $contacts->syncContacts(null, [$this->settings->getSmsListName(), $campaignListId]);

        if(is_array($syncStatus) && strtolower($syncStatus["status"]) == "done"){
            $this->sendCampaign($campaignListId, $campaignListName);

            $msg = "Message sent successfully";
            if(count($syncStatus["warnings"]) > 0){
                $msg .= ", however, the following contacts may not have been included: <br>" . implode("<br>", $syncStatus["warnings"]);
            }
            return ["success" => $msg];
        }else{
            return ["info" => "It's taking longer than expected to sync contacts, would you like to continue to wait?", "batchId"=>$batchId, "campaignListId"=>$campaignListId, "campaignListName"=>$campaignListName, "lastBatchStatus"=>$syncStatus];
        }
    }

    protected function makeContactListName(array $guestData, string $filterVal = ""){
        $now = new \DateTime();

        if(isset($guestData["filterOptions"][$filterVal])){
            $listTitle = $guestData["title"] . " - " . $guestData["filterOptions"][$filterVal]["Description"];
        }else{
            $listTitle = $guestData["title"];
        }

        if(strlen($listTitle . " - " . $now->format("M j, Y h:i:s a")) > 50){

            $maxTitleLength = 47 - strlen(" - " . $now->format("M j, Y h:i:s a")); //50 max length - 3 characters for "..." - date

            return substr($listTitle, 0, $maxTitleLength) . "... - " . $now->format("M j, Y h:i:s a");

        }else{
            return $listTitle . " - " . $now->format("M j, Y h:i:s a");
        }
    }

}