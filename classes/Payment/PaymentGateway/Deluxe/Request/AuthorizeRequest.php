<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use HHK\Payment\CreditToken;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

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
     * @param string $currrency
     */
    public function submit(float $amount, string $token, string $expDate, string $currrency = "USD"){
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
        $resp = $this->GuzzleClient->post(self::ENDPOINT, [
            \GuzzleHttp\RequestOptions::JSON => $requestData
        ]);

        $status = $resp->getStatusCode();

        if($status == 200){ //success
            $body = $resp->getBody()->getContents();
            echo $body;
            exit;
        }else{//error
            $body = $resp->getBody()->getContents();
            echo $body;
            exit;
        }
    }
}