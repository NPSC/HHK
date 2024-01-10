<?php

namespace HHK\Notification\SMS\SimpleTexting;
use HHK\Exception\RuntimeException;

Class Messages {

    protected \PDO $dbh;
    protected Settings $settings;
    protected string $accountPhone;

    public function __construct(\PDO $dbh, string $accountPhone = ""){

        $this->dbh = $dbh;
        $this->settings = new Settings($dbh);
        $this->accountPhone = $accountPhone;

    }

    public function getMessages(string $contactPhone, int $limit = 20, string $since = ""){
        $client = $this->settings->getClient();

        $queryParams = [
            "size"=>$limit,
            "accountPhone"=>$this->accountPhone,
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

        $response = $client->get("messages", [
            "query"=>$queryParams
        ]);

        if($response->getStatusCode() === 200){
            return json_decode($response->getBody(), true);
        }else{
            throw new RuntimeException("Error getting messages: Error " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
        }
    }

}

?>