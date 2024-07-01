<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Exception\PaymentException;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\Deluxe\Response\PaymentGatewayResponse;
use HHK\sec\Session;
use HHK\Tables\PaymentGW\Guest_TokenRS;

Class PaymentRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    public function submit(Invoice $invoice, Guest_TokenRS $tokenRS, string $currrency = "USD"){

        $uS = Session::getInstance();
        
        //build request data
        $requestData = [
            'paymentType' => 'Sale',
            'amount'=>[
                'amount'=>(float) round($invoice->getAmountToPay(), 2),
                'currency'=>$currrency
            ],
            'paymentMethod'=>[
                'token'=>[
                    "token"=>$tokenRS->Token->getStoredVal(),
                    "expiry"=>$tokenRS->ExpDate->getStoredVal()
                ]
            ],
            'customData'=>[
                [
                    "name" => 'idPayor',
                    "value" => (string) $invoice->getSoldToId()
                ],
                [
                    "name" => 'Invoice Number',
                    "value" => (string) $invoice->getInvoiceNumber()
                ]
            ]
        ];

        //send request
        try{
            $resp = $this->GuzzleClient->post(self::ENDPOINT, [
                \GuzzleHttp\RequestOptions::JSON => $requestData
            ]);

            $this->responseBody = json_decode($resp->getBody()->getContents(), true);
            $this->responseCode = (isset($this->responseBody["responseCode"]) ? $this->responseBody["responseCode"] : $resp->getStatusCode());
            
            if(is_array($this->responseBody["responseMessage"])){
                $this->responseMsg = implode(", ", $this->responseBody["responseMessage"]);
            }else if(isset($this->responseBody["responseMessage"])){
                $this->responseMsg = $this->responseBody["responseMessage"];
            }

            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'Payment');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            $response = new PaymentGatewayResponse($invoice->getAmountToPay(), $invoice->getInvoiceNumber(), $tokenRS->CardType->getStoredVal(), $tokenRS->MaskedAccount->getStoredVal(), $tokenRS->ExpDate->getStoredVal(), $tokenRS->CardHolderName->getStoredVal(), "sale", $uS->username, $this->responseBody["responseCode"], $tokenRS->Token->getStoredVal(), $this->responseMsg, $this->responseBody["paymentId"]);
            $response->setMerchant($this->merchant);
            return $response;

        }catch(BadResponseException $e){//error
            $this->responseCode = $e->getResponse()->getStatusCode();
            $this->responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            
            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'Payment');
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