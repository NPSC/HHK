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
                'amount'=>$invoice->getAmountToPay(),
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

            $status = $resp->getStatusCode();
            $this->responseCode = $status;

            $this->responseBody = json_decode($resp->getBody()->getContents(), true);
            return new PaymentGatewayResponse($invoice->getAmountToPay(), $invoice->getInvoiceNumber(), $tokenRS->CardType->getStoredVal(), $tokenRS->MaskedAccount->getStoredVal(), $tokenRS->CardHolderName->getStoredVal(), "sale", $uS->username, $this->responseBody["responseCode"]);
            
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