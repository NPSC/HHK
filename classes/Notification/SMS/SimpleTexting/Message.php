<?php

namespace HHK\Notification\SMS\SimpleTexting;
use GuzzleHttp\Exception\ClientException;
use HHK\Exception\RuntimeException;
use HHK\Exception\SmsException;

/**
 * Summary of Message
 */
Class Message {

    /**
     * Summary of dbh
     * @var \PDO
     */
    protected \PDO $dbh;
    /**
     * Summary of settings
     * @var Settings
     */
    protected Settings $settings;
    /**
     * Summary of contactPhone
     * @var string
     */
    protected string $contactPhone;
    /**
     * Summary of MMSsubject
     * @var string
     */
    protected string $MMSsubject;
    /**
     * Summary of text
     * @var string
     */
    protected string $text;
    /**
     * Summary of fallbackText
     * @var string
     */
    protected string $fallbackText;

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param string $contactPhone
     * @param string $text
     * @param string $subject
     * @param string $fallbackText
     */
    public function __construct(\PDO $dbh, string $contactPhone, string $text, string $subject = "", string $fallbackText = "", bool $validateSettings = false){
        $this->dbh = $dbh;
        $this->settings = new Settings($dbh, $validateSettings);
        $this->contactPhone = $contactPhone;
        $this->MMSsubject = $subject;
        $this->text = $text;
        $this->fallbackText = $fallbackText;
    }

    /**
     * Summary of evaluateMessage
     * @throws \HHK\Exception\SmsException
     * @return array
     */
    public function evaluateMessage():array{
        $client = $this->settings->getClient();
        
        $requestArray = [
            "mode"=>"AUTO",
            "text"=>$this->text
        ];

        if(strlen($this->MMSsubject) > 0){
            $requestArray["subject"] = $this->MMSsubject;
        }

        if(strlen($this->fallbackText) > 0){
            $requestArray["fallbackText"] = $this->fallbackText;
        }

        try {
            $response = $client->post("messages/evaluate", [
                "json" => $requestArray
            ]);
        }catch(ClientException $e){
            $response = $e->getResponse();
            $jsonResp = json_decode($response->getBody(), true);
            throw new SmsException("Unable to evaluate message: " . $jsonResp["details"]);
        }

        if($response->getStatusCode() === 201){
            $body = json_decode($response->getBody(), true);

            if(is_array($body)){
                return $body;
            }else{
                return ["error"=>"Unable to parse response"];
            }
        }else{
            $respArr = json_decode($response->getBody(), true);
            
            if(is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])){
                throw new SmsException("error getting messages: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Invalid response received while trying to evaluate message. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
            }
        }
    }
    /**
     * Summary of sendMessage
     * @throws \HHK\Exception\SmsException
     * @return array
     */
    public function sendMessage(){
        $client = $this->settings->getClient();

        /*
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
            throw new SmsException("Unable to send message:" . ($errors ? " Error: " . $errors . '.': '') . ($warnings ? " Warning: " . $warnings . '.': ''));
        }
*/
        //send message
        $requestArray = [
            "contactPhone"=>$this->contactPhone,
            "accountPhone"=>$this->settings->getAccountPhone(),
            "mode"=>"AUTO",
            "text"=>$this->text,
        ];

        if(strlen($this->MMSsubject) > 0){
            $requestArray["subject"] = $this->MMSsubject;
        }

        if(strlen($this->fallbackText) > 0){
            $requestArray["fallbackText"] = $this->fallbackText;
        }

        try {
            $response = $client->post('messages', [
                'json' => $requestArray
            ]);

            $body = json_decode($response->getBody(), true);

            if (is_array($body)) {
                return $body;
            } else {
                throw new SmsException("Unable to parse response");
            }

        }catch(ClientException $e){
            $respArr = json_decode($e->getResponse()->getBody(), true);

            if (is_array($respArr) && isset($respArr["status"]) && isset($respArr["message"])) {
                throw new SmsException("Error sending message: " . $respArr["status"] . ": " . $respArr["message"]);
            } else {
                throw new SmsException("Invalid response received while trying to send message. Error  " . $response->getStatusCode() . ": " . $response->getReasonPhrase());
            }
        }

    }

    /**
     * Summary of getMessageTemplate
     * @return array
     */
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