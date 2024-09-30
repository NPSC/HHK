<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request\Webhooks;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Exception\PaymentException;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\Deluxe\Request\AbstractDeluxeRequest;
use HHK\sec\Session;

Class UnsubscribeEventRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "events/unsubscribe";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    /**
     * Submit a webhook subscribe request
     * @throws PaymentException
     */
    public function submit(string $eventSubscriptionId){

        $uS = Session::getInstance();

        //build request data
        $requestData = [
            'events'=>[
                'email'=>"",
                'eventSubscriptionId'=>$eventSubscriptionId
            ]
        ];

        //send request
        try{
            $resp = $this->GuzzleClient->post(self::ENDPOINT, [
                \GuzzleHttp\RequestOptions::JSON => $requestData
            ]);

            $status = $resp->getStatusCode();
            $this->responseCode = $status;

            $this->responseBody = json_decode($resp->getBody()->getContents(), true);
            $this->responseCode = (isset($this->responseBody["responseCode"]) ? $this->responseBody["responseCode"] : $resp->getStatusCode());
            
            if(is_array($this->responseBody["responseMessage"])){
                $this->responseMsg = implode(", ", $this->responseBody["responseMessage"]);
            }else if(isset($this->responseBody["responseMessage"])){
                $this->responseMsg = $this->responseBody["responseMessage"];
            }
            
            try {
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'WebhookUnsubscribe');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            return true;
            
        }catch(BadResponseException $e){//error
            $this->responseCode = $e->getResponse()->getStatusCode();
            $this->responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            
            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'Authorize');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            if(isset($this->responseBody["error"]["message"])){
                throw new PaymentException("Error subscribing to webhook: Error: " . $this->responseBody["error"]["message"]);
            }else if(isset($this->responseBody["errors"]) && is_array($this->responseBody["errors"])){
                $msg = $this->responseBody["errors"]["message"] . ": " . $this->responseBody["errors"]["details"];
                throw new PaymentException("Error subscribing to webhook: " . $msg);
            } else{
                throw new PaymentException("Error subscribing to webhook: Unknown Error: " . $e->getMessage());
            }
            
        }
    }
}