<?php

namespace HHK\Notification\SMS\SimpleTexting;
use GuzzleHttp\Exception\ClientException;
use HHK\Exception\SmsException;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\TableLog\NotificationLog;

Class Campaign {

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
        
/*
        $evaluated = $this->message->evaluateMessage();
        $warnings = false;
        $errors = false;

        if(isset($evaluated['warnings']) && count($evaluated['warnings']) > 0){
            $warnings = implode(", ", $evaluated['warnings']);
        }

        if(isset($evaluated['errors']) && count($evaluated['errors']) > 0){
            $errors = implode(", ", $evaluated['errors']);
        }

        if($warnings || $errors){
            throw new SmsException("Unable to send campaign:" . ($errors ? " Error: " . $errors . '.': '') . ($warnings ? " Warning: " . $warnings . '.': ''));
        }
*/
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
        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $listName, $uS->smsFrom, "Error sending campaign: " . $respArr["status"] . ": " . $respArr["message"], ["msgText" => $this->message->getMessageTemplate()["text"], "listId"=>$listId, "listName"=>$listName]);
                throw new SmsException("Error sending campaign: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $listName, $uS->smsFrom, "Error sending campaign: Error " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase(), ["msgText" => $this->message->getMessageTemplate()["text"], "listId"=>$listId, "listName"=>$listName]);
                throw new SmsException("Error sending campaign: Error " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
            }
        }


        if($response->getStatusCode() == 201){
            $body = $response->getBody();

            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, $listName, $uS->smsFrom, "Campaign sent Successfully", ["msgText" => $this->message->getMessageTemplate()["text"], "listId"=>$listId, "listName"=>$listName]);

            try{
                return json_decode($body, true);
            }catch(\Exception $e){
                return ["error"=>"Unable to parse response: " . $e->getMessage()];
            }
        }else{
            throw new SmsException("Invalid response received while trying to send campaign. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
        }

    }

    /**
     * Summary of prepareAndSendCampaign
     * @param string $status
     * @throws \HHK\Exception\SmsException
     * @return array
     */
    public function prepareAndSendCampaign(string $status){
        $client = $this->settings->getClient();

        $messages = new Messages($this->dbh);
        $guestData = $messages->getCampaignGuestsData($status);
        $campaignListName = $this->makeContactListName($guestData);

        if(isset($guestData["contacts"]) && count($guestData["contacts"]) > 0){
            //make contact list
            try {
                $response = $client->post('contact-lists', [
                    'json' => ["name"=>$campaignListName]
                ]);
            }catch(ClientException $e){
                $respArr = json_decode($e->getResponse()->getBody(), true);

                if (is_array($respArr) && isset($respArr["message"])) {
                    throw new SmsException("Error sending campaign: " . $respArr["message"] . (isset($respArr["details"]) ? $respArr["details"]:""));
                } else {
                    throw new SmsException("Error sending campaign: Error " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
                }
            }

            if($response->getStatusCode() == 201){
                $respArr = json_decode($response->getBody(), true);
                
                if(isset($respArr["id"])){
                    $campaignListId = $respArr["id"];
                    
                    //sync contacts to new list
                    $contacts = new Contacts($this->dbh);
                    $syncStatus = $contacts->syncContacts($status, [$this->settings->getSmsListName(), $campaignListId]);

                    if(strtolower($syncStatus) == "done"){
                        $this->sendCampaign($campaignListId, $campaignListName);

                        return ["success" => "Message sent successfully"];
                    }else{
                        throw new SmsException("Error sending campaign: Could not set up campaign list");
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

    protected function makeContactListName(array $guestData){
        $now = new \DateTime();

        if(strlen($guestData['title'] . " - " . $now->format("M j, Y h:i:s a")) > 50){

            $maxTitleLength = 47 - strlen(" - " . $now->format("M j, Y h:i:s a")); //50 max length - 3 characters for "..." - date

            return substr($guestData["title"], 0, $maxTitleLength) . "... - " . $now->format("M j, Y h:i:s a");

        }else{
            return $guestData['title'] . " - " . $now->format("M j, Y h:i:s a");
        }
    }

}