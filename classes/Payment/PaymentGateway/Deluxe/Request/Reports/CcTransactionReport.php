<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request\Reports;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Exception\PaymentException;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\Deluxe\Request\AbstractDeluxeRequest;
use HHK\sec\Session;

Class CcTransactionReport extends AbstractDeluxeRequest {
    const ENDPOINT = "reports";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    /**
     * Retreive Reconciliation report
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return array
     * @throws PaymentException
     */
    public function submit(\DateTimeInterface $startDate, \DateTimeInterface $endDate){

        $uS = Session::getInstance();

        //build request data
        $requestData = [
            'reportTitle'=>"ccTransaction",
            'reportStartDate'=>$startDate->format("n/j/Y"),
            'reportEndDate'=>$endDate->format("n/j/Y")
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
            
            if(isset($this->responseBody["responseMessage"]) && is_array($this->responseBody["responseMessage"])){
                $this->responseMsg = implode(", ", $this->responseBody["responseMessage"]);
            }else if(isset($this->responseBody["responseMessage"])){
                $this->responseMsg = $this->responseBody["responseMessage"];
            }

            return $this->responseBody;
            
        }catch(BadResponseException $e){//error
            $this->responseCode = $e->getResponse()->getStatusCode();
            $this->responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);

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