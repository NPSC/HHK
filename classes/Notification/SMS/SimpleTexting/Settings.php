<?php

namespace HHK\Notification\SMS\SimpleTexting;

use GuzzleHttp\Client;
use HHK\sec\Session;

Class Settings {

    protected \PDO $dbh;
    protected Client $client;
    protected array $settings;

    public function __construct(\PDO $dbh){
        $this->dbh = $dbh;
        $this->loadSettings();
        $this->client = $this->buildClient();
    }

    protected function buildClient():Client{
        return new Client([
            "base_uri"=>"https://api-app2.simpletexting.com/v2/api/",
            "headers"=> [
                "Accept" => "application/json",
                "Authorization" => "Bearer " . $this->settings["authToken"]
            ]
        ]);
    }

    protected function loadSettings():void{
        $uS = Session::getInstance();
        $this->settings = $uS->smsSettings;

        //TODO: set up sms settings in DB
    }

    public function getClient(){
        return $this->client;
    }

    public function validateSettings(){
        //TODO: check that Custom Fields exist on remote

        //TODO: Check that proper Contact Segments exist on remote
    }

}
