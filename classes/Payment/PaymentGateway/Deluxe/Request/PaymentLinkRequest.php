<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;

use GuzzleHttp\Exception\BadResponseException;
use HHK\Exception\PaymentException;
use HHK\Payment\CreditToken;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\sec\Session;

Class PaymentLinkRequest extends AbstractDeluxeRequest {
    const ENDPOINT = "paymentlinks";

    public function __construct(\PDO $dbh, DeluxeGateway $gway){
        parent::__construct($dbh, $gway);
    }

    /**
     * Submit a payment link creation request
     * @param Invoice $invoice
     * @return array $responseBody
     * @throws PaymentException
     */
    public function submit(Invoice $invoice, string $emailAddr, string $currrency = "USD"){

        $uS = Session::getInstance();

        $billTo = $invoice->getBillToName($this->dbh, $invoice->getSoldToId());

        if($emailAddr == ""){
            $emailAddr = $invoice->getBillToEmail($this->dbh);
        }

        //build request data
        $requestData = [
            'amount'=>[
                'amount'=>(float) $invoice->getBalance(),
                'currency'=>$currrency
            ],
            'firstName'=>$billTo["Name_First"],
            'lastName'=>$billTo["Name_Last"],
            'orderData'=>[
                'orderId'=>(string) $invoice->getInvoiceNumber()
            ],
            'customData'=>[
                [
                    'name'=>"Invoice Notes",
                    'value'=>$invoice->getInvoiceNotes()
                ]
            ],
            'paymentLinkExpiry'=>"1 week",
            'acceptPaymentMethod'=>[
                "Card",
                "ACH"
            ],
            'deliveryMethod'=>[
                "email"=>$emailAddr
            ],
            'confirmationMessage'=>"Thank you for your payment"
        ];
        
        $invoiceLines = $invoice->getLines($this->dbh);

        foreach($invoiceLines as $invoiceLine){
            $requestData['level3'][] = [
                'quantity' => (int) $invoiceLine->getQuantity(),
                'price' => (float) $invoiceLine->getPrice(),
                'description' => $invoiceLine->getDescription()
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
            
            /*
            if(is_array($this->responseBody["responseMessage"])){
                $this->responseMsg = implode(", ", $this->responseBody["responseMessage"]);
            }else if(isset($this->responseBody["responseMessage"])){
                $this->responseMsg = $this->responseBody["responseMessage"];
            }
            */

            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'CreatePaymentLink');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            return $this->responseBody;
            
        }catch(BadResponseException $e){//error
            $this->responseCode = $e->getResponse()->getStatusCode();
            $this->responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            
            try {
                //self::logGwTx($dbh, $authRequest->getResponseCode(), json_encode($data), json_encode($resp), 'CardInfoVerify');
                DeluxeGateway::logGwTx($this->dbh, $this->responseCode, json_encode($requestData), json_encode($this->responseBody), 'CreatePaymentLink');
            } catch (\Exception $ex) {
                // Do Nothing
            }

            if(isset($this->responseBody["error"]["message"])){
                throw new PaymentException("Error creating payment link: Error: " . $this->responseBody["error"]["message"]);
            }else if(isset($this->responseBody["errors"]) && is_array($this->responseBody["errors"])){
                $msg = $this->responseBody["errors"]["message"] . ": " . $this->responseBody["errors"]["details"];
                throw new PaymentException("Error creating payment link: " . $msg);
            } else{
                throw new PaymentException("Error creating payment link: Unknown Error: " . $e->getMessage());
            }
            
        }
    }
}