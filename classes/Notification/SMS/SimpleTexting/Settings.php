<?php

namespace HHK\Notification\SMS\SimpleTexting;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use HHK\Exception\SmsException;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;

Class Settings {

    protected \PDO $dbh;
    protected Client $client;
    protected array $settings;

    const StatusLookups = [
        VisitStatus::CheckedIn=>"checked_in",
        VisitStatus::CheckedOut=>"checked_out",
        VisitStatus::ChangeRate=>"checked_in",
        VisitStatus::OnLeave=>"on_leave",
        ReservationStatus::Waitlist=>"waitlist",
        ReservationStatus::Committed=>"confirmed_reservation"
    ];

    /**
     * Init SimpleTexting Settings, API Client and validate settings
     * @param \PDO $dbh
     */
    public function __construct(\PDO $dbh, bool $validateSettings = false){
        $this->dbh = $dbh;
        $this->loadSettings();
        $this->client = $this->buildClient();
        if($validateSettings){
            $this->validateSettings();
        }
    }

    /**
     * Set up Guzzle Client for SimpleTexting API
     * @return \GuzzleHttp\Client
     */
    protected function buildClient():Client{
        return new Client([
            "base_uri"=>"https://api-app2.simpletexting.com/v2/api/",
            "headers"=> [
                "Accept" => "application/json",
                "Authorization" => "Bearer " . $this->settings["authToken"]
            ]
        ]);
    }

    /**
     * Get settings from session and store in $this->settings
     * @return void
     */
    protected function loadSettings():void{
        $uS = Session::getInstance();

        $this->settings = [
            "authToken"=>$uS->smsToken,
            "accountPhone"=>$uS->smsFrom,
            "hhkListId"=>$uS->smsListId
        ];

    }

    public function getClient(){
        return $this->client;
    }

    public function getAccountPhone(){
        return $this->settings["accountPhone"];
    }

    public function getHhkListId(){
        return $this->settings['hhkListId'];
    }

    /**
     * Validate and set up SimpleTexting settings
     * @throws \HHK\Exception\SmsException
     * @return bool
     */
    public function validateSettings(){
        if(isset($this->settings["authToken"]) && strlen($this->settings["authToken"]) > 0){
            try {
                if (isset($this->settings["hhkListId"]) && strlen($this->settings["hhkListId"]) > 0) {
                    $resp = $this->client->get("contact-lists/" . $this->settings["hhkListId"]);
                } else {
                    $resp = $this->client->post("contact-lists",['json'=>['name'=>"HHK Contacts"]]);
                }

                $body = json_decode($resp->getBody(), true);

                if(isset($body['listId'])){
                    //if response contains listId - list already exists, all good
                    return true;
                }else if(isset($body['id'])){
                    //if response contains id - list was created successfully, now save new list id in db
                    SysConfig::saveKeyValue($this->dbh, "sys_config", "hhkListId", $body['id']);
                    $this->settings['hhkListId'] = $body['id'];
                    return true;
                }else{
                    throw new SmsException("Unable to validate SMS settings: An unknown error occurred.");
                }
                
            }catch(ClientException $e){
                if($e->getResponse()->getStatusCode() == 404){
                    try {
                        //list does not exist on remote, create it
                        $resp = $this->client->post("contact-lists", ['json' => ['name' => "HHK Contacts"]]);
                        if (isset($body['id'])) {
                            //if response contains id - list was created successfully, now save new list id in db
                            SysConfig::saveKeyValue($this->dbh, "sys_config", "hhkListId", $body['id']);
                            $this->settings['hhkListId'] = $body['id'];
                            return true;
                        } else {
                            throw new SmsException("Unable to validate SMS settings: Error creating Contact List");
                        }
                    }catch(ClientException $e){
                        if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                            throw new SmsException("Unable to validate SMS settings: " . $respArr["status"] . ": " . $respArr["message"]);
                        } else {
                            throw new SmsException("Invalid response received while trying to validate SMS settings. Error  " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
                        }
                    }
                }

                $respArr = json_decode($e->getResponse()->getBody(), true);
                if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                    throw new SmsException("Error validating SMS settings: " . $respArr["status"] . ": " . $respArr["message"]);
                } else {
                    throw new SmsException("Invalid response received while trying to validate SMS settings. Error  " . $e->getResponse()->getStatusCode() . ": " . $e->getResponse()->getReasonPhrase());
                }
            }
        }else{
            throw new SmsException("smsToken field is required to send text messages with SimpleTexting.");
        }
    }

}
