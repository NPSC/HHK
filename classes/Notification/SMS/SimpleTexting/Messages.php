<?php

namespace HHK\Notification\SMS\SimpleTexting;

use GuzzleHttp\Exception\ClientException;
use HHK\Exception\SmsException;
use HHK\Notification\SMS\AbstractMessages;

Class Messages extends AbstractMessages {

    protected \PDO $dbh;
    protected Settings $settings;
    protected string $accountPhone;

    /**
     * Set up Messages object
     * @param \PDO $dbh
     * @param string $accountPhone
     */
    public function __construct(\PDO $dbh, string $accountPhone = ""){

        parent::__construct($dbh, $accountPhone);
        $this->settings = new Settings($dbh);
    }

    /**
     * Fetch messages from SimpleTexting
     * @param string $contactPhone
     * @param int $limit
     * @param string $since
     * @throws \HHK\Exception\SmsException
     * @return array
     */
    public function fetchMessages(string $contactPhone, int $limit = 20, string $since = ""){
        $client = $this->settings->getClient();

        $queryParams = [
            "size"=>$limit,
            "accountPhone"=>$this->settings->getAccountPhone(),
            "contactPhone"=>$contactPhone
        ];
        
        if(strlen($since) > 0){
            try{
                $since = new \DateTime($since);
                $sinceTimestamp = $since->format(\DateTime::ATOM);
                $queryParams["since"] = $sinceTimestamp;
            }catch(\Exception $e){

            }
        }

        try {
            $response = $client->get("messages", [
                "query" => $queryParams
            ]);

            $respArr = json_decode($response->getBody(), true);
            if (is_array($respArr["content"])) {
                return $respArr;
            } else {
                throw new SmsException("Error getting messages: content not found on remote");
            }
            
        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error getting messages: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Error getting messages: Error " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
            }
        }
    }

}