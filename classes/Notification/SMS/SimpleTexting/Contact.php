<?php

namespace HHK\Notification\SMS\SimpleTexting;

use GuzzleHttp\Exception\ClientException;
use HHK\Exception\RuntimeException;
use HHK\Exception\SmsException;
use HHK\sec\Session;
use HHK\TableLog\NotificationLog;

Class Contact {

    protected \PDO $dbh;
    protected Settings $settings;

    public function __construct(\PDO $dbh, bool $validateSettings = false){
        $this->dbh = $dbh;
        $this->settings = new Settings($dbh, $validateSettings);
    }

    /**
     * Upsert a contact into SimpleTexting
     * @param string $contactPhone
     * @param string $firstName
     * @param string $lastName
     * @throws \HHK\Exception\RuntimeException|\HHK\Exception\SmsException
     * @return array
     */
    public function upsert(string $contactPhone, string $firstName, string $lastName){
        try{
            $uS = Session::getInstance();
            $client = $this->settings->getClient();

            $requestArray = [
                "contactPhone"=>$contactPhone,
                "firstName"=>$firstName,
                "lastName"=>$lastName,
                "listIds"=>[$this->settings->getSmsListName()],
                "subscriptionStatus"=>"OPT_IN"
            ];

            $response = $client->put('contacts/' . $contactPhone,[
                'query'=>["listsReplacement"=>false],
                'json'=>$requestArray
            ]);
            
        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);
            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, "", $uS->smsFrom, "Updating Contact", ["response"=>$respArr]);
            

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error updating Contact in SimpleTexting: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Error updating Contact in SimpleTexting: Error " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
            }
        }

        if($response->getStatusCode() === 200){
            $body = json_decode($response->getBody(), true);

            if(is_array($body)){
                return $body;
            }else {
                throw new RuntimeException("Unable to parse response");
            }
        }else{

            NotificationLog::logSMS($this->dbh, $uS->smsProvider, $uS->username, "", $uS->smsFrom, "Error updating Contact", ["request"=>$requestArray, "response"=>json_decode($response->getBody()->getContents(), true)]);
            throw new SmsException("Invalid response received while trying to update Contact in SimpleTexting. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());

        }
    }

    public function fetchContact(string $contactPhone){
        $client = $this->settings->getClient();

        try {
            $response = $client->get("contacts/" . $contactPhone);

            $respArr = json_decode($response->getBody(), true);
            if (is_array($respArr) && isset($respArr["contactId"])) {
                return $respArr;
            } else {
                throw new SmsException("Error getting contact: Invalid response received");
            }
            
        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error getting contact: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Error getting contact: Error " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
            }
        }
    }

}