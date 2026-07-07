<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

Class SearchPaymentRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments/search";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    public function submit(string $paymentId): array{        

        //build request data
        $requestData = [
            "paymentId"=>$paymentId
        ];

        //send request
        try{
            $resp = $this->GuzzleClient->post(self::ENDPOINT, [
                \GuzzleHttp\RequestOptions::JSON => $requestData
            ]);
            
            $this->responseCode = $resp->getStatusCode();
            $this->responseBody = json_decode($resp->getBody()->getContents(), true);

        }catch(BadResponseException $e){//error
            $this->responseCode = $e->getResponse()->getStatusCode();
            $this->responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
        }
        
        return $this->responseBody;
    }
}