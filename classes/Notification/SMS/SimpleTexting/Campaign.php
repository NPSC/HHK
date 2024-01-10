<?php

namespace HHK\Notification\SMS\SimpleTexting;
use HHK\Exception\RuntimeException;

Class campaign {

    protected \PDO $dbh;
    protected Settings $settings;
    protected string $accountPhone;
    protected string $title;
    protected array $contactSegments;
    protected Message $message;

    public function __construct(\PDO $dbh, string $title, array $contactSegments, string $text, string $subject = "", string $fallbackText = "", string $accountPhone = ""){
        $this->dbh = $dbh;
        $this->settings = new Settings($dbh);
        $this->accountPhone = $accountPhone;
        $this->title = $title;
        $this->contactSegments = $contactSegments;
        $this->message = new Message($dbh, "", $text, $subject, $fallbackText, $accountPhone);
    }

    public function sendCampaign(){
        $client = $this->settings->getClient();

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
            throw new RuntimeException("Unable to send campaign:" . ($errors ? " Error: " . $errors . '.': '') . ($warnings ? " Warning: " . $warnings . '.': ''));
        }

        //send campaign
        $requestArray = [
            "title"=>$this->title,
            "accountPhone"=>$this->accountPhone,
            "segmentIds" => $this->contactSegments,
            "messageTemplate"=>$this->message->getMessageTemplate()
        ];

        $response = $client->post('campaigns',[
            'json'=>$requestArray
        ]);


        if($response->getStatusCode() === 201){
            $body = $response->getBody();

            try{
                return json_decode($body, true);
            }catch(\Exception $e){
                return ["error"=>"Unable to parse response: " . $e->getMessage()];
            }
        }else{
            throw new RuntimeException("Invalid response received while trying to send campaign. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
        }

    }

}

?>