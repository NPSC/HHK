<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Exception\PaymentException;
use HHK\Payment\CreditToken;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\Deluxe\Response\VoidGatewayResponse;
use HHK\sec\Session;
use HHK\SysConst\MpTranType;
use HHK\Tables\Payment\PaymentRS;
use HHK\Tables\PaymentGW\Guest_TokenRS;

Class VoidRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "payments/cancel";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    public function submit(string $paymentId, Invoice|null $invoice = null, PaymentRS|null $payRs = null){

        $uS = Session::getInstance();

        try{
            if ($payRs instanceof PaymentRS) {
                $tokenRS = CreditToken::getTokenRsFromId($this->dbh, $payRs->idToken->getStoredVal());
            }else{
                $tokenRS = new Guest_TokenRS();
            }
        }catch(\Exception $e){
            throw new PaymentException("Unable to perform void request: " . $e->getMessage());
        }
        

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

            if(is_array($this->responseBody["responseMessage"])){
                $this->responseMsg = implode(", ", $this->responseBody["responseMessage"]);
            }else if(isset($this->responseBody["responseMessage"])){
                $this->responseMsg = $this->responseBody["responseMessage"];
            }
            
            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'Void');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            if ($payRs instanceof PaymentRS && $invoice instanceof Invoice) {
                $this->responseBody["amount"] = $payRs->Amount->getStoredVal();
                $this->responseBody["invoiceNumber"] = $invoice->getInvoiceNumber();

                $tkRs = CreditToken::getTokenRsFromId($this->dbh, $payRs->idToken->getStoredVal());
                $this->responseBody["cardholderName"] = $tkRs->CardHolderName->getStoredVal();
                $this->responseBody["cardType"] = $tkRs->CardType->getStoredVal();
                $this->responseBody["maskedAccount"] = $tkRs->MaskedAccount->getStoredVal();
            }

            $response = new VoidGatewayResponse($this->responseBody, $tokenRS, MpTranType::Void);
            $response->setMerchant($this->merchant);
            return $response;

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
            }else if(isset($this->responseBody["errors"]) && is_array($this->responseBody["errors"])){
                $msg = $this->responseBody["errors"]["message"] . ": " . $this->responseBody["errors"]["details"];
                throw new PaymentException("Error making payment with Payment Gateway: " . $msg);
            } else{
                throw new PaymentException("Error making payment with Payment Gateway: Unknown Error: " . $e->getMessage());
            }
            
        }
    }
}