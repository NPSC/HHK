<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Exception\PaymentException;
use HHK\Payment\CreditToken;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\Deluxe\Response\AuthorizeGatewayResponse;
use HHK\sec\Session;

Class AuthorizeRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments/authorize";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    /**
     * Submit a payment authorization request
     * @param float $amount
     * @param string $token
     * @param string $expDate
     * @param string $cardType
     * @param string $maskedAcct
     * @param string $cardHolderName
     * @param string $currrency
     * @return AuthorizeGatewayResponse
     * @throws PaymentException
     */
    public function submit(float $amount, string $token, string $expDate, $cardType, $maskedAcct, $cardHolderName, $billingFirstName = "", $billingLastName = "", string $currrency = "USD"){

        $uS = Session::getInstance();

        //build request data
        $requestData = [
            'amount'=>[
                'amount'=>$amount,
                'currency'=>$currrency
            ],
            'paymentMethod'=>[
                'token'=>[
                    "token"=>$token,
                    "expiry"=>$expDate
                ]
            ]
        ];

        //add customer info
        if($billingFirstName != "" && $billingLastName != ""){
            $requestData['paymentMethod']["billingAddress"] = [
                "firstName"=>$billingFirstName,
                "lastName"=>$billingLastName
            ];
        }

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

            $response = new AuthorizeGatewayResponse($this->responseBody, 0, $cardType, $maskedAcct, $expDate, $cardHolderName, "COF");
            $response->setMerchant($this->merchant);
            
            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'Authorize');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            return $response;
            
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
                throw new PaymentException("Error making payment with Payment Gateway: Error: " . $this->responseBody["error"]["message"]);
            }else if(isset($this->responseBody["errors"]) && is_array($this->responseBody["errors"])){
                $msg = $this->responseBody["errors"]["message"] . ": " . $this->responseBody["errors"]["details"];
                throw new PaymentException("Error making payment with Payment Gateway: " . $msg);
            } else{
                throw new PaymentException("Error making payment with Payment Gateway: Unknown Error: " . $e->getMessage());
            }
            
        }
    }
}