<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Exception\PaymentException;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\Deluxe\Response\VoidGatewayResponse;
use HHK\sec\Session;
use HHK\SysConst\MpTranType;

Class VoidRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments/cancel";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    public function submit(string $paymentId){

        $uS = Session::getInstance();

        //build request data
        $requestData = [
            "paymentId"=>$paymentId
        ];

        //send request
        try{
            $resp = $this->GuzzleClient->post(self::ENDPOINT, [
                \GuzzleHttp\RequestOptions::JSON => $requestData
            ]);

            $status = $resp->getStatusCode();
            $this->responseCode = $status;

            $this->responseBody = json_decode($resp->getBody()->getContents(), true);

            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'Void');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            return new VoidGatewayResponse($this->responseBody, MpTranType::Void);

        }catch(BadResponseException $e){//error
            $this->responseCode = $e->getResponse()->getStatusCode();
            $this->responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            
            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'Void');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            if(isset($this->responseBody["error"]["message"])){
                throw new PaymentException("Error making payment with Payment Gateway: Error: " . $this->responseBody["error"]["message"]);
            }else{
                throw new PaymentException("Error making payment with Payment Gateway: Unknown Error: " . $e->getMessage());
            }
            
        }
    }
}