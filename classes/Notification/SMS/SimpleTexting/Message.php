<?php

namespace HHK\Notification\SMS\SimpleTexting;
use HHK\Exception\RuntimeException;

Class Message {

    protected \PDO $dbh;
    protected Settings $settings;
    protected string $contactPhone;
    protected string $accountPhone;
    protected string $MMSsubject;
    protected string $text;
    protected string $fallbackText;

    public function __construct(\PDO $dbh, string $contactPhone, string $text, string $subject = "", string $fallbackText = "", string $accountPhone = ""){
        $this->dbh = $dbh;
        $this->settings = new Settings($dbh);
        $this->contactPhone = $contactPhone;
        $this->accountPhone = $accountPhone;
        $this->MMSsubject = $subject;
        $this->text = $text;
        $this->fallbackText = $fallbackText;
    }

    public function evaluateMessage():array{
        $client = $this->settings->getClient();
        
        $requestArray = [
            "text"=>$this->text,
            "fallbackText"=>$this->fallbackText,
        ];

        if(isset($this->MMSsubject)){
            $requestArray["subject"] = $this->MMSsubject;
        }

        $response = $client->post("messages/evalute", [
            "json" => $requestArray
        ]);

        if($response->getStatusCode() === 201){
            $body = $response->getBody();

            try{
                return json_decode($body, true);
            }catch(\Exception $e){
                return ["error"=>"Unable to parse response: " . $e->getMessage()];
            }
        }else{
            throw new RuntimeException("Invalid response received while trying to evaluate message. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
        }
    }
    public function sendMessage(){
        $client = $this->settings->getClient();

        $evaluated = $this->evaluateMessage();
        $warnings = false;
        $errors = false;

        if(isset($evaluated['warnings']) && count($evaluated['warnings']) > 0){
            $warnings = implode(", ", $evaluated['warnings']);
        }

        if(isset($evaluated['errors']) && count($evaluated['errors']) > 0){
            $errors = implode(", ", $evaluated['errors']);
        }

        if($warnings || $errors){
            throw new RuntimeException("Unable to send message:" . ($errors ? " Error: " . $errors . '.': '') . ($warnings ? " Warning: " . $warnings . '.': ''));
        }

        //send message
        $requestArray = [
            "contactPhone"=>$this->contactPhone,
            "accountPhone"=>$this->accountPhone,
            "mode"=>"AUTO",
            "text"=>$this->text,
            "fallbackText"=>$this->fallbackText,
        ];

        if(isset($this->MMSsubject)){
            $requestArray["subject"] = $this->MMSsubject;
        }

        $response = $client->post('messages',[
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
            throw new RuntimeException("Invalid response received while trying to send message. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
        }

    }

    public function getMessageTemplate():array{
        $template = [
            "mode"=>"AUTO",
            "text"=>$this->text,
            "fallbackText"=>$this->fallbackText
        ];
        
        if($this->MMSsubject){
            $template['subject'] = $this->MMSsubject;
        }

        return $template;
    }

}

?>