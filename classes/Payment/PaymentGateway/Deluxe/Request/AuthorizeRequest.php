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
    public function submit(float $amount, string $token, string $expDate, $cardType, $maskedAcct, $cardHolderName, string $currrency = "USD"){

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

        //send request
        try{
            $resp = $this->GuzzleClient->post(self::ENDPOINT, [
                \GuzzleHttp\RequestOptions::JSON => $requestData
            ]);

            $status = $resp->getStatusCode();
            $this->responseCode = $status;

            $this->responseBody = json_decode($resp->getBody()->getContents(), true);
            return new AuthorizeGatewayResponse($this->responseBody['token'], $this->responseBody["amountApproved"], 0, $cardType, $maskedAcct, $cardHolderName, "COF", $uS->username);

        }catch(BadResponseException $e){//error
            $this->responseCode = $e->getResponse()->getStatusCode();
            $this->responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            
            if(isset($this->responseBody["error"]["message"])){
                throw new PaymentException("Error making payment with Payment Gateway: Error: " . $this->responseBody["error"]["message"]);
            }else{
                throw new PaymentException("Error making payment with Payment Gateway: Unknown Error: " . $e->getMessage());
            }
            
        }
    }
}