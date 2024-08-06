<?php
namespace HHK\Payment\PaymentGateway\Deluxe;

use HHK\Exception\PaymentException;
use HHK\Exception\RuntimeException;
use HHK\House\HouseServices;
use HHK\House\Reservation\Reservation;
use HHK\House\Reservation\Reservation_1;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\Payment\CreditToken;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentGateway\CreditPayments\ReturnReply;
use HHK\Payment\PaymentGateway\CreditPayments\SaleReply;
use HHK\Payment\PaymentGateway\CreditPayments\VoidReply;
use HHK\Payment\PaymentGateway\Deluxe\Request\AuthorizeRequest;
use HHK\Payment\PaymentGateway\Deluxe\Request\PaymentRequest;
use HHK\Payment\PaymentGateway\Deluxe\Request\RefundRequest;
use HHK\Payment\PaymentGateway\Deluxe\Request\VoidRequest;
use HHK\Payment\PaymentGateway\Deluxe\Request\Webhooks\SubscribeEventRequest;
use HHK\Payment\PaymentGateway\Deluxe\Response\AuthorizeCreditResponse;
use HHK\Payment\PaymentGateway\Deluxe\Response\PaymentCreditResponse;
use HHK\Payment\PaymentGateway\Deluxe\Response\RefundCreditResponse;
use HHK\Payment\PaymentGateway\Deluxe\Response\RefundGatewayResponse;
use HHK\Payment\PaymentGateway\Deluxe\Response\VoidCreditResponse;
use HHK\Payment\PaymentManager\PaymentManagerPayment;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Payment\PaymentResult\CofResult;
use HHK\Payment\PaymentResult\PaymentResult;
use HHK\Payment\PaymentResult\ReturnResult;
use HHK\Payment\Receipt;
use HHK\Payment\Transaction;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\SysConst\MpTranType;
use HHK\SysConst\PaymentStatusCode;
use HHK\SysConst\PayType;
use HHK\SysConst\TransMethod;
use HHK\SysConst\TransType;
use HHK\SysConst\VisitStatus;
use HHK\TableLog\AbstractTableLog;
use HHK\TableLog\HouseLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\LocationRS;
use HHK\Tables\Payment\Payment_AuthRS;
use HHK\Tables\Payment\PaymentRS;
use HHK\Tables\PaymentGW\CC_Hosted_GatewayRS;
use HHK\Tables\PaymentGW\Guest_TokenRS;
/**
 * DeluxeGateway.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class DeluxeGateway extends AbstractPaymentGateway
{

    protected $manualKey = FALSE;

    public function __construct(\PDO $dbh, $gwType = '', $tokenId = 0) {

        // Glean gwType if not there.
        if ($gwType == '' && $tokenId > 0) {

            // Find merchant (gwType) from the token.
            $tknRs = CreditToken::getTokenRsFromId($dbh, $tokenId);

            if (CreditToken::hasToken($tknRs)) {
                $gwType = $tknRs->Merchant->getStoredVal();
            }
        }

        parent::__construct($dbh, $gwType);
    }

    /**
     *
     * @param \HHK\Payment\GatewayResponse\GatewayResponseInterface $vcr
     * @param mixed $idPayor
     * @param mixed $idGroup
     */
    public function getCofResponseObj(\HHK\Payment\GatewayResponse\GatewayResponseInterface $vcr, $idPayor, $idGroup) {
    }

    /**
     */
    public function getGatewayName() {
        return AbstractPaymentGateway::DELUXE;
    }

    public function hasCofService() {
		return TRUE;
	}
	public function hasUndoReturnPmt() {
		return FALSE;
	}

	public function hasUndoReturnAmt() {
		return FALSE;
	}

    public function hasVoidReturn() {
    	return FALSE;
    }

    /**
     *
     * @param \HHK\Payment\GatewayResponse\GatewayResponseInterface $vcr
     * @param mixed $idPayor
     * @param mixed $idGroup
     * @param mixed $invoiceNumber
     * @param mixed $idToken
     * @param mixed $payNotes
     */
    public function getPaymentResponseObj(\HHK\Payment\GatewayResponse\GatewayResponseInterface $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {
        return new PaymentCreditResponse($vcr, $idPayor, $idGroup);
    }

    protected function loadGateway(\PDO $dbh) {

        $query = "select * from `cc_hosted_gateway` where `cc_name` = '" . $this->getGatewayType() . "' and `Gateway_Name` = '" .$this->getGatewayName()."'";
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) < 1) {
            $rows[0] = [];
            //throw new PaymentException('Payment Gateway Merchant "' . $this->getGatewayType() . '" is not found for '.$this->getGatewayName());
        }

        $gwRs = new CC_Hosted_GatewayRS();
        EditRS::loadRow($rows[0], $gwRs);

        $oAuthClientId = $gwRs->Merchant_Id->getStoredVal();
        unset($rows[0]['Merchant_Id']);
        if ($oAuthClientId != '') {
            $rows[0]['oAuthClientId'] = $oAuthClientId;
        }

        $oAuthURL = $gwRs->CheckoutPOS_Url->getStoredVal();
        unset($rows[0]['CheckoutPOS_Url']);
        if ($oAuthURL != '') {
            $rows[0]['oAuthURL'] = $oAuthURL;
        }

        $oAuthSecret = $gwRs->Password->getStoredVal();
        unset($rows[0]['Password']);
        if ($oAuthSecret != '') {
        	$rows[0]['oAuthSecret'] = decryptMessage($oAuthSecret);
        }

        $hpfAccessToken = $gwRs->Credit_Url->getStoredVal();
        unset($rows[0]['Credit_Url']);
        if ($hpfAccessToken != '') {
        	$rows[0]['hpfAccessToken'] = decryptMessage($hpfAccessToken);
        }

        return $rows[0];
    }

    protected function setCredentials($gwRow) {

        $this->credentials = $gwRow;
    }

    /**
     * Summary of getCredentials
     * @return mixed
     */
    public function getCredentials() {
    	return $this->credentials;
    }

    /**
     *
     * @param \PDO $dbh
     * @param mixed $post
     * @param mixed $ssoToken
     * @param mixed $idInv
     * @param mixed $payNotes
     * @param mixed $payDate
     */
    public function processHostedReply(\PDO $dbh, $post, $ssoToken, $idInv, $payNotes, $payDate) {

    	$uS = Session::getInstance();
    	$payResult = NULL;
        $rtnCode = '';
        $rtnMessage = '';

        // THis eventually selects the merchant id
        if (isset($uS->manualKey)) {
        	$this->manualKey = $uS->manualKey;
        }

        //captured card info
        if (isset($post['token']) && isset($post['expDate']) && isset($post["cmd"]) && $post["cmd"] == "cof") {
            //card on file
            $result = $this->saveCOF($dbh, $post);
            if (is_array($result)){
                echo json_encode($result);
                exit;
            }
        } else if (isset($post['token']) && isset($post['expDate']) && isset($post["cmd"]) && $post["cmd"] == "payment"){
            //payment with new card
            $pmp = new PaymentManagerPayment(PayType::Charge);
            $invoice = new Invoice($dbh, $post["invoiceNum"]);
            $invoice->setAmountToPay($invoice->getBalance());
            return $this->creditSale($dbh, $pmp, $invoice, $post['pbp']);
        }
    }

    protected function initHostedPayment(\PDO $dbh, Invoice $invoice, $postbackUrl) {

        $uS = Session::getInstance();

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new RuntimeException("The Invoice is missing.  ");
        }

        // Set CC Gateway name
        $uS->ccgw = $this->getGatewayType();
        $uS->manualKey = $this->manualKey;

        // Card reader?
        if ($this->usePOS && ! $this->manualKey) {
            //card present
            //TODO Implement card present
            return false;
        } else {
        	//Card not present
            return HostedPaymentForm::sendToPortal($dbh, $this, $invoice->getSoldToId(), $invoice->getIdGroup(), $this->manualKey, $postbackUrl, "payment", $invoice->getInvoiceNumber(), $invoice->getAmountToPay());
        }
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $manualKey, $cardHolderName, $postbackUrl, $selChgType = '', $chgAcct = '', $idx = '') {

        $uS = Session::getInstance();
        $secure = new SecurityComponent();

        $houseUrl = $secure->getSiteURL();
        $siteUrl = $secure->getRootURL();

        if ($houseUrl == '' || $siteUrl == '') {
            throw new RuntimeException("The site/house URL is missing.  ");
        }

        if ($this->getGatewayType() == '') {
            // Undefined Gateway.
            $dataArray['error'] = 'Location not selected. ';
            return $dataArray;
        }

        // This selects the correct merchant from the credentials
        $this->manualKey = $manualKey;

        // Set CC Gateway name
        $uS->ccgw = $this->getGatewayType();
        $uS->manualKey = $this->manualKey;


        // Card reader?
        if ($this->usePOS && ! $this->manualKey) {
            //card present
            //TODO Implement card present
            return false;
        } else {
        	//Card not present
            return ["deluxehpf"=>HostedPaymentForm::sendToPortal($dbh, $this, $idGuest, $idGroup, $manualKey, $postbackUrl, "cof")];
        }

    }

    protected function saveCOF(\PDO $dbh, array $data) {

        $uS = Session::getInstance();

        if(isset($data['rid'])){
            $data['psg'] = Reservation_1::getIdPsgStatic($dbh, $data['rid']);
            $data['id'] = 0;
        }

        //authorize $1 to make sure card is real
        $authRequest = new AuthorizeRequest($dbh, $this);
        $response = $authRequest->submit(1.00, $data["token"], $data["expDate"], $data["cardType"], $data["maskedPan"], $data["nameOnCard"]);

        $respBody = $authRequest->getResponseBody();
        $respBody['InvoiceNumber'] = 0;
        $respBody['cardHolderName'] = $data["nameOnCard"];
        $respBody["expDate"] = $data["expDate"];
        $respBody["cardType"] = $data["cardType"];
        $respBody["maskedAcct"] = substr($data["maskedPan"], -4);

        if($respBody["amountApproved"] == "1" && isset($respBody["paymentId"])){
            $voidRequest = new VoidRequest($dbh, $this);
            $voidResponse = $voidRequest->submit($respBody["paymentId"]);
        }

        $vr = new AuthorizeCreditResponse($response, $data['id'], $data['psg']);
        $responseMessage = "";
        if($vr->getStatus() == AbstractCreditPayments::STATUS_APPROVED){
            // save token
            $idToken = CreditToken::storeToken($dbh, $vr->idRegistration, $vr->idPayor, $response, $data["token"]);

            return ["success" => "New Card saved successfully","COFmkup"=> HouseServices::guestEditCreditTable($dbh, $data['psg'], $data['id'], 'g'), 'idx'=>'g'];
        }else{
            return ["warning" => $vr->response->getResponseMessage()];
        }
    }

    public function creditSale(\PDO $dbh, PaymentManagerPayment $pmp, Invoice $invoice, $postbackUrl) {

        $uS = Session::getInstance();
        $payResult = NULL;

        if ($this->getGatewayType() == '') {
            // Undefined Gateway.
            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->feePaymentError($dbh, $uS);
            $payResult->setDisplayMessage('Location not selected. ');

        } else {
            $tokenRS = CreditToken::getTokenRsFromId($dbh, $pmp->getIdToken());

            // Do we have a token?
            if (CreditToken::hasToken($tokenRS)) {

                $paymentRequest = new PaymentRequest($dbh, $this);

                $gatewayResponse = $paymentRequest->submit($invoice, $tokenRS);

                $paymentResponse = new PaymentCreditResponse($gatewayResponse, $invoice->getSoldToId(), $invoice->getIdGroup());

                // Record transaction
                try {
                    $transRs = Transaction::recordTransaction($dbh, $paymentResponse, $this->getGatewayName(), TransType::Sale, TransMethod::Token);
                    $paymentResponse->setIdTrans($transRs->idTrans->getStoredVal());
                } catch (\Exception $ex) {
                    throw new PaymentException("Error creating transaction: " . $ex->getMessage());
                }

                $paymentResponse = SaleReply::processReply($dbh, $paymentResponse, $uS->username);

                $payResult = $this->analyzeCredSaleResult($dbh, $paymentResponse, $invoice, $pmp->getIdToken());

            } else if (filter_has_var(INPUT_POST, 'token') && filter_has_var(INPUT_POST, 'expDate')){
                $paymentRequest = new PaymentRequest($dbh, $this);

                $token = filter_input(INPUT_POST, "token", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $expDate = filter_input(INPUT_POST, "expDate", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $nameOnCard = filter_input(INPUT_POST, "nameOnCard", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $cardType = filter_input(INPUT_POST, "cardType", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $acct = substr(filter_input(INPUT_POST, "maskedPan", FILTER_SANITIZE_FULL_SPECIAL_CHARS), -4);

                $newTokenRS = new Guest_TokenRS();
                $newTokenRS->Token->setStoredVal($token);
                $newTokenRS->ExpDate->setStoredVal($expDate);
                $newTokenRS->CardHolderName->setStoredVal($nameOnCard);
                $newTokenRS->CardType->setStoredVal($cardType);
                $newTokenRS->MaskedAccount->setStoredVal($acct);

                $gatewayResponse = $paymentRequest->submit($invoice, $newTokenRS);

                $paymentResponse = new PaymentCreditResponse($gatewayResponse, $invoice->getSoldToId(), $invoice->getIdGroup());

                // Record transaction
                try {
                    $transRs = Transaction::recordTransaction($dbh, $paymentResponse, $this->getGatewayName(), TransType::Sale, TransMethod::HostedPayment);
                    $paymentResponse->setIdTrans($transRs->idTrans->getStoredVal());
                } catch (\Exception $ex) {
                    throw new PaymentException("Error creating transaction: " . $ex->getMessage());
                }

                $paymentResponse = SaleReply::processReply($dbh, $paymentResponse, $uS->username);

                $payResult = $this->analyzeCredSaleResult($dbh, $paymentResponse, $invoice, $paymentResponse->getIdToken());


            }else {

            	$this->manualKey = $pmp->getManualKeyEntry();

                // Initialiaze hosted payment
                $fwrder = $this->initHostedPayment($dbh, $invoice, $postbackUrl);

                $uS->paymentNotes = $pmp->getPayNotes();
                $uS->paymentDate = $pmp->getPayDate();

                $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $payResult->setForwardHostedPayment($fwrder);
                //$payResult->setDisplayMessage('Forward to Payment Page. ');
            }
        }

        return $payResult;
    }


    public function analyzeCredSaleResult(\PDO $dbh, AbstractCreditResponse $payResp, Invoice $invoice, $idToken) {

        $uS = Session::getInstance();

        $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId(), $idToken);


        switch ($payResp->getStatus()) {

            case AbstractCreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, $payResp->response->getAuthorizedAmount(), $uS->username);

                $payResult->feePaymentAccepted($dbh, $uS, $payResp, $invoice);
                $payResult->setDisplayMessage('Paid by Credit Card.  ');

                if ($payResp->isPartialPayment()) {
                    $payResult->setDisplayMessage('** Partially Approved Amount: ' . number_format($payResp->response->getAuthorizedAmount(), 2) . ' (Remaining Balance Due: ' . number_format($invoice->getBalance(), 2) . ').  ');
                }
/*
                if ($this->useAVS) {
                    $avsResult = new AVSResult($payResp->response->getAVSResult());

                    if ($avsResult->isZipMatch() === FALSE) {
                        $payResult->setDisplayMessage($avsResult->getResultMessage() . '  ');
                    }
                }

                if ($this->useCVV) {
                    $cvvResult = new CVVResult($payResp->response->getCvvResult());
                    if ($cvvResult->isCvvMatch() === FALSE && $uS->CardSwipe === FALSE) {
                        $payResult->setDisplayMessage($cvvResult->getResultMessage() . '  ');
                    }
                }
*/
                break;

            case AbstractCreditPayments::STATUS_DECLINED:

                $payResult->setStatus(PaymentResult::DENIED);
                $payResult->feePaymentRejected($dbh, $uS, $payResp, $invoice);

                $msg = '** The Payment is Declined. **';
                if ($payResp->response->getResponseMessage() != '') {
                    $msg .= 'Message: ' . $payResp->response->getResponseMessage();
                }
                $payResult->setDisplayMessage($msg);

                break;

            default:

                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->feePaymentError($dbh, $uS);
                $payResult->setDisplayMessage('** Payment Invalid or Error **  Message: ' . $payResp->response->getResponseMessage());
        }

        return $payResult;
    }

    protected function _voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid){

        $uS = Session::getInstance();

        $paymentId = $pAuthRs->AcqRefData->getStoredVal();

        $voidRequest = new VoidRequest($dbh, $this);

        $gatewayResponse = $voidRequest->submit($paymentId, $invoice, $payRs);

        $tkRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());

        $voidResponse = new VoidCreditResponse($gatewayResponse, $invoice->getSoldToId(), $invoice->getIdGroup(), $payRs, $tkRs);

        // Record transaction
        try {
            $transRs = Transaction::recordTransaction($dbh, $voidResponse, $this->getGatewayName(), TransType::Void, TransMethod::Token);
            $voidResponse->setIdTrans($transRs->idTrans->getStoredVal());
        } catch (\Exception $ex) {
            throw new PaymentException("Error creating transaction: " . $ex->getMessage());
        }

        // Record payment
        $csResp = VoidReply::processReply($dbh, $voidResponse, $uS->username, $payRs);

        switch ($csResp->getStatus()) {

            case AbstractCreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                $csResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));
                $dataArray['success'] = 'Payment is void.  ';

                break;

            case AbstractCreditPayments::STATUS_DECLINED:

                $dataArray['warning'] = '** Void Declined. **  Message: ' . $csResp->response->getResponseMessage();

                break;

            default:

                $dataArray['warning'] = '** Void Invalid or Error. **  Message: ' . $csResp->response->getResponseMessage();
        }

        return $dataArray;
    }

    public function voidReturn(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid)
    {
        return $this->_voidSale($dbh, $invoice, $payRs, $pAuthRs, $bid);
    }

    protected function _returnPayment(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $returnAmt, $bid) {

        $uS = Session::getInstance();

        $csResp = $this->processReturnPayment($dbh, $payRs, $pAuthRs->AcqRefData->getStoredVal(), $invoice, $returnAmt, $uS->username, '');

        $dataArray = array('bid' => $bid);

        switch ($csResp->getStatus()) {

            case AbstractCreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                $csResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));

                break;

            case AbstractCreditPayments::STATUS_DECLINED:

                $dataArray['warning'] = $csResp->response->getResponseMessage();

                break;

            default:

                $dataArray['warning'] = $csResp->response->getResponseMessage();
        }

        return $dataArray;
    }

    public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes) {

        $uS = Session::getInstance();

        // Find a credit payment
        $idGroup = intval($invoice->getIdGroup(), 10);
        $amount = abs($invoice->getAmount());
        $idToken = intval($rtnToken, 10);

        //find payment >= amount that hasn't been used for a refund yet. Payments used for return amount already can't be used again.
        $stmt = $dbh->query("select sum(case WHEN pa.Status_Code = 'r' then (0-pa.Approved_Amount) WHEN rp.Is_Refund = 1 THEN 0 ELSE pa.Approved_Amount END) as `Total`, pa.AcqRefData, p.idPayment
from payment p join payment_auth pa on p.idPayment = pa.idPayment left join payment rp on p.idPayment = rp.parent_idPayment
where p.idToken = $idToken group by p.idPayment having `Total` >= $amount order by idPayment desc;");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) == 0) {

            $payResult = new ReturnResult($invoice->getIdInvoice(), 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage('** An appropriate payment was not found for this return amount: ' . $amount . ' **');
            return $payResult;

            /*
            //try returning multiple payments
            $remainingAmount = $amount;

            $stmt = $dbh->query("select pa.Approved_Amount as `Total`, pa.AcqRefData, p.idPayment
from payment p join payment_auth pa on p.idPayment = pa.idPayment
    join payment_invoice pi on p.idPayment = pi.Payment_Id
    join invoice i on pi.Invoice_Id = i.idInvoice
where p.idToken = $idToken and i.idGroup = $idGroup
order by pa.Timestamp desc");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $rowCount = $stmt->rowCount();
            $i = 0;

            $payResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $returnedAmount = 0;
            $csResps = [];
            while($i < $rowCount && $remainingAmount > 0){
                //get paymentRS
                $payRS = new PaymentRS();
                $payRS->idPayment->setStoredVal($rows[0]['idPayment']);
                $paymentRows = EditRS::select($dbh, $payRS, [$payRS->idPayment]);
                if(count($paymentRows) == 1){
                    EditRS::loadRow($paymentRows[0], $payRS);
                }

                if($remainingAmount >= $rows[$i]["Total"]){ //return full payment
                    $csResps[$i] = $this->processReturnPayment($dbh, $payRS, $rows[$i]["AcqRefData"], $invoice, $rows[$i]["Total"], $uS->username, $paymentNotes);
                }else{ //partially return the remaining amount
                    $csResps[$i] = $this->processReturnPayment($dbh, $payRS, $rows[$i]["AcqRefData"], $invoice, $remainingAmount, $uS->username, $paymentNotes);
                }

                if($csResps[$i]->getStatus() == AbstractCreditPayments::STATUS_APPROVED && (float) $csResps[$i]->getAmount() > 0){
                    $returnedAmount += $csResps[$i]->getAmount();
                    $remainingAmount -= (float) $csResps[$i]->getAmount();
                }
                $i++;
            }

            if($remainingAmount == 0){
                $payResult->feePaymentsAccepted($dbh, $uS, $csResps, $invoice);
                $invoice->updateInvoiceBalance($dbh, 0 - $returnedAmount, $uS->username);

                $payResult->setDisplayMessage('Amount Returned by Credit Card.  ');
            } */
        } else { //return the payment found
            //get paymentRS
            $payRS = new PaymentRS();
            $payRS->idPayment->setStoredVal($rows[0]['idPayment']);
            $paymentRows = EditRS::select($dbh, $payRS, [$payRS->idPayment]);
            if(count($paymentRows) == 1){
                EditRS::loadRow($paymentRows[0], $payRS);
            }

            $csResp = $this->processReturnAmount($dbh, $payRS, $rows[0]["AcqRefData"], $invoice, $amount, $uS->username, $paymentNotes);
            //$csResp = $this->processStandaloneReturn($dbh, $tokenRS, $invoice, $amount, $uS->username, $paymentNotes);

            $payResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());

            switch ($csResp->getStatus()) {

                case AbstractCreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);
                    $payResult->setStatus(PaymentResult::ACCEPTED);

                    $payResult->feePaymentAccepted($dbh, $uS, $csResp, $invoice);
                    $payResult->setDisplayMessage('Amount Returned by Credit Card.  ');

                    break;

                case AbstractCreditPayments::STATUS_DECLINED:

                    $payResult->feePaymentRejected($dbh, $uS, $csResp, $invoice);

                    $msg = '** The Return is Declined. **';
                    if ($csResp->response->getResponseMessage() != '') {
                        $msg .= 'Message: ' . $csResp->response->getResponseMessage();
                    }
                    $payResult->setDisplayMessage($msg);

                    break;

                default:

                    $payResult->setStatus(PaymentResult::ERROR);
                    $payResult->setDisplayMessage('**  Error Message: ' . $csResp->response->getResponseMessage());
            }
        }

        return $payResult;
    }

    /**
     *
     * @param \PDO $dbh
     * @param PaymentRS|null $payRs
     * @param string $paymentTransId
     * @param Invoice $invoice
     * @param float $returnAmt
     * @param string $userName
     * @return AbstractCreditResponse
     */
    protected function processReturnPayment(\PDO $dbh, PaymentRS|null $payRs, $paymentTransId, Invoice $invoice, $returnAmt, $userName, $paymentNotes) {

        $returnRequest = new RefundRequest($dbh, $this);

        //find token for building the receipt
        try{
            $tokenRS = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        }catch(\Exception $e){
            throw new PaymentException("Unable to return payment: " . $e->getMessage());
        }

        $returnGatewayResponse = $returnRequest->submit($paymentTransId, $tokenRS, $invoice->getInvoiceNumber(), $returnAmt, MpTranType::ReturnSale);

        // Make a return response...
        $sr = new RefundCreditResponse($returnGatewayResponse, $invoice->getSoldToId(), $invoice->getIdGroup(), $returnAmt);
        $sr->setResult($returnGatewayResponse->getStatus());

        if ($sr->getStatus() == AbstractCreditPayments::STATUS_APPROVED) {
        	$sr->setPaymentStatusCode(PaymentStatusCode::Retrn);
        } else {
        	$sr->setPaymentStatusCode(PaymentStatusCode::Declined);
        }
//         if ($curlResponse->getResponseMessage() != self::RESPONSE_APPROVED) {
//         	$sr->setPaymentStatusCode(PaymentStatusCode::Declined);
//         } else {
//         	$sr->setPaymentStatusCode(PaymentStatusCode::Retrn);
//         }

        // Record transaction
        try {
        	$transRs = Transaction::recordTransaction($dbh, $sr, $this->getGatewayName(), TransType::Retrn, TransMethod::Token);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());
        } catch (\Exception $ex) {
            // do nothing
        }

        // Record return
        return ReturnReply::processReply($dbh, $sr, $userName, $payRs);

    }

    /**
     *
     * @param \PDO $dbh
     * @param PaymentRS|null $payRs
     * @param string $paymentTransId
     * @param Invoice $invoice
     * @param float $returnAmt
     * @param string $userName
     * @return AbstractCreditResponse
     */
    protected function processReturnAmount(\PDO $dbh, PaymentRS|null $payRs, $paymentTransId, Invoice $invoice, $returnAmt, $userName, $paymentNotes) {

        $returnRequest = new RefundRequest($dbh, $this);

        //find token for building the receipt
        try{
            $tokenRS = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        }catch(\Exception $e){
            throw new PaymentException("Unable to return payment: " . $e->getMessage());
        }

        $returnGatewayResponse = $returnRequest->submit($paymentTransId, $tokenRS, $invoice->getInvoiceNumber(), $returnAmt, MpTranType::ReturnAmt);
        
        // Make a return response...
        $sr = new RefundCreditResponse($returnGatewayResponse, $invoice->getSoldToId(), $invoice->getIdGroup(), $returnAmt);
        $sr->setResult($returnGatewayResponse->getStatus());
        $sr->setIdToken($tokenRS->idGuest_token->getStoredVal());

        if($payRs instanceof PaymentRS){
            $sr->setParentIdPayment($payRs->idPayment->getStoredVal());
        }

        if ($sr->getStatus() == AbstractCreditPayments::STATUS_APPROVED) {
        	$sr->setPaymentStatusCode(PaymentStatusCode::Paid);
        } else {
        	$sr->setPaymentStatusCode(PaymentStatusCode::Declined);
        }

        // Record transaction
        try {
        	$transRs = Transaction::recordTransaction($dbh, $sr, $this->getGatewayName(), TransType::Retrn, TransMethod::Token);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());
        } catch (\Exception $ex) {
            // do nothing
        }

        // Record return
        return ReturnReply::processReply($dbh, $sr, $userName);

    }

    /**
     *
     * @param \PDO $dbh
     * @param mixed $payTable
     * @param mixed $index
     */
    public function selectPaymentMarkup(\PDO $dbh, &$payTbl, $index = '') {
        $selArray = ['name'=>'selccgw'.$index, 'class'=>'hhk-feeskeys'.$index, 'style'=>'width:min-content;', 'title'=>'Select the Location'];
    	$manualArray =  ['type'=>'checkbox', 'name'=>'btnvrKeyNumber'.$index, 'class'=>'hhk-feeskeys'.$index, 'title'=>'Check to Key in credit account number'];

        // Precheck the manual account number entry checkbox?
        if ($this->checkManualEntryCheckbox) {
        	$manualArray['checked'] = 'checked';
        }

        $keyCb = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("span", "Swipe") .
            HTMLContainer::generateMarkup(
                'label',
                HTMLInput::generateMarkup('', $manualArray) .
                HTMLContainer::generateMarkup("div", "", ['class' => 'hhk-slider round'])
                ,
                ['for' => 'btnvrKeyNumber' . $index, 'title' => 'Check to Key in credit account number', 'class' => 'hhk-switch mx-2']
            ) .
            HTMLContainer::generateMarkup("span", "Type")
            , ["class"=>"hhk-flex"]);


        if ($this->getGatewayType() != '') {
        	// A location is already selected.

            $sel = HTMLSelector::doOptionsMkup([0=>[0=>$this->getGatewayType(), 1=> ucfirst($this->getGatewayType())]], $this->getGatewayType(), FALSE);

            $payTbl->addBodyTr(
                    HTMLTable::makeTh('Selected Location:', ['style'=>'text-align:right;'])
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup($sel, $selArray), ['colspan'=>'2'])
            		, ['id'=>'trvdCHName'.$index, 'class'=>'d-none tblCreditExpand'.$index.' tblCredit'.$index]
            );



        } else {
			// Show all locations, none is preselected.

            $stmt = $dbh->query("Select DISTINCT l.`Merchant`, l.`Title` from `location` l join `room` r on l.idLocation = r.idLocation where r.idLocation is not null and l.`Status` = 'a'");
            $gwRows = $stmt->fetchAll();

            $selArray['size'] = count($gwRows);

            if (count($gwRows) == 1) {
            	// only one merchant
            	$sel = HTMLSelector::doOptionsMkup($gwRows, $gwRows[0][0], FALSE);
            } else {
            	$sel = HTMLSelector::doOptionsMkup($gwRows, '', FALSE);
            }

            $payTbl->addBodyTr(
            		HTMLTable::makeTh('Select a Location:', ['style'=>'text-align:right; width:130px;'])
                    .HTMLTable::makeTd(HTMLSelector::generateMarkup($sel, $selArray), ['colspan'=>'2'])
                    , ['id'=>'trvdCHName'.$index, 'class'=> (count($gwRows) == 1 ? "d-none " : "") . 'tblCreditExpand'.$index.' tblCredit'.$index, 'style'=>'display: none;']
            );

        }

        $payTbl->addBodyTr(
            HTMLTable::makeTh('Capture Method:', ['style'=>'text-align:right;'])
            .HTMLTable::makeTd($keyCb, ['colspan'=>'2'])
        ,['class'=>'d-none tblCreditExpand'.$index.' tblCredit'.$index, "style"=>'display: none;']);
    }

    protected static function _createEditMarkup(\PDO $dbh, $gatewayName, $resultMessage = '') {

        $gwRs = new CC_Hosted_GatewayRS();
        $gwRs->Gateway_Name->setStoredVal($gatewayName);
        $rows = EditRS::select($dbh, $gwRs, array($gwRs->Gateway_Name));

        $opts = array(
            array(0, 'False'),
            array(1, 'True'),
        );

        $tbl = new HTMLTable();

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan' => '2', 'class' => 'ui-state-highlight')));
        }

        foreach ($rows as $r) {

            $tbl->addBodyTr(HTMLTable::makeTd('&nbsp', array('colspan'=>'2')), array('style'=>'border-top: solid 1px black;'));

            $gwRs = new CC_Hosted_GatewayRS();
            EditRS::loadRow($r, $gwRs);

            $indx = $gwRs->idcc_gateway->getStoredVal();
            $title = ucfirst($gwRs->cc_name->getStoredVal());

            // Merchant name
            $tbl->addBodyTr(
                HTMLTable::makeTh('Merchant Name:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd($title)
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Oauth Client Id:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Merchant_Id->getStoredVal(), array('name' => $indx . '_txtuid', 'size' => '50')))
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Oauth Secret:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup(($gwRs->Password->getStoredVal() == '' ? '' : self::PW_PLACEHOLDER), array('type'=>'password', 'name' => $indx . '_txtsecret', 'size' => '90')) . ' (Obfuscated)')
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Oauth URL:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->CheckoutPOS_Url->getStoredVal(), array('name' => $indx . '_txtoauthurl', 'size' => '90')))
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Payments API URL:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Checkout_Url->getStoredVal(), array('name' => $indx . '_txtapiurl', 'size' => '90')))
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Hosted Payment Access Token:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup(($gwRs->Credit_Url->getStoredVal() == '' ? '': self::PW_PLACEHOLDER), array('type'=>'password', 'name' => $indx . '_txtaccesstoken', 'size' => '90')) . ' (Obfuscated)')
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Use Card Swiper:', array('class' => 'tdlabel'))
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $gwRs->Retry_Count->getStoredVal(), FALSE), array('name' => $indx . '_txtuseSwipe')))
            );

            // Set my rooms
            $tbl->addBodyTr(
                HTMLTable::makeTh('Set '.$title.' Rooms:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => $indx . '_cbSetRooms', 'class'=>'hhk-setMerchantRooms', 'data-merchant'=>$title, 'type'=>'checkbox')))
                );

            // Delete me
            $tbl->addBodyTr(
                HTMLTable::makeTh('Delete ' . $title . ':', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => $indx . '_cbdelMerchant', 'class'=>'hhk-delMerchant', 'data-merchant'=>$title, 'type'=>'checkbox')))
                );

            //webhooks
            $tbl->addBodyTr(
                HTMLTable::makeTh('Transaction Webhook', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => $indx . '_cbTransactionWebhook', 'class'=>'hhk-transactionWebhook', 'data-merchant'=>$title, 'type'=>'checkbox'))),
                ['class'=>'d-none']);
        }


        // New location
        $tbl->addBodyTr(HTMLTable::makeTd('&nbsp', array('colspan'=>'2')), array('style'=>'border-top: solid 1px black;'));
        $tbl->addBodyTr(
            HTMLTable::makeTh('Add a new merchant', array('class' => 'tdlabel'))
            .HTMLTable::makeTd('New Merchant Name: '.HTMLInput::generateMarkup('', array('name' => 'txtnewMerchant', 'size' => '50')))

        );

        return $tbl->generateMarkup();
    }

    protected static function _saveEditMarkup(\PDO $dbh, $gatewayName, $post) {

        $uS = Session::getInstance();

        $msg = '';

        $ccRs = new CC_Hosted_GatewayRS();
        $ccRs->Gateway_Name->setStoredVal($gatewayName);
        $rows = EditRS::select($dbh, $ccRs, array($ccRs->Gateway_Name));

        // Add new merchant
        if (isset($post['txtnewMerchant'])) {
            // Add a new default row

            $merchantName = strtolower(filter_var($post['txtnewMerchant'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));

            if ( ! empty($merchantName)) {

                $isThere = FALSE;

                // Compare with previous merchant names
                foreach ($rows as $r) {

                    $ccRs = new CC_Hosted_GatewayRS();
                    EditRS::loadRow($r, $ccRs);

                    if ($ccRs->cc_name->getStoredVal() == $merchantName) {
                        $isThere = TRUE;
                        break;
                    }
                }

                reset($rows);

                // Don't let them make a new merchant with the same name.
                if ($isThere) {
                    $msg .= HTMLContainer::generateMarkup('p', 'Merchant name ' . $merchantName . ' already exists.');
                } else {
                    //Insert new gateway record
                    $num = self::insertGwRecord($dbh, $gatewayName, $merchantName, new CC_Hosted_GatewayRS());
                    self::checkLocationTable($dbh, $merchantName);

                    // Retrieve the new record.
                    $ccRs = new CC_Hosted_GatewayRS();
                    $ccRs->Gateway_Name->setStoredVal($gatewayName);
                    $ccRs->cc_name->setStoredVal($merchantName);
                    $newrows = EditRS::select($dbh, $ccRs, array($ccRs->Gateway_Name, $ccRs->cc_name));

                    $rows[] = $newrows[0];
                }
            }
        }

        foreach ($rows as $r) {

            $ccRs = new CC_Hosted_GatewayRS();
            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();
            $merchantName = $ccRs->cc_name->getStoredVal();

            // Delete Merchant
            if (isset($post[$indx . '_cbdelMerchant']) && $ccRs->cc_name->getStoredVal() != '') {

                $result = self::deleteMerchant($dbh, $ccRs);
                $msg .= HTMLContainer::generateMarkup('p', $result);

                continue;
            }

            // Oauth Client Id
            if (isset($post[$indx . '_txtuid'])) {
                $ccRs->Merchant_Id->setNewVal(filter_var($post[$indx . '_txtuid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Oauth token URL
            if (isset($post[$indx . '_txtoauthurl'])) {
                $ccRs->CheckoutPOS_Url->setNewVal(filter_var($post[$indx . '_txtoauthurl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Payments API URL
            if (isset($post[$indx . '_txtapiurl'])) {
                $ccRs->Checkout_Url->setNewVal(filter_var($post[$indx . '_txtapiurl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Hosted Payment Access Token
            if (isset($post[$indx . '_txtaccesstoken'])) {

                $accessToken = filter_var($post[$indx . '_txtaccesstoken'], FILTER_UNSAFE_RAW);

                if ($accessToken != '' && $accessToken != self::PW_PLACEHOLDER) {
                    $ccRs->Credit_Url->setNewVal(encryptMessage($accessToken));
                } else if ($accessToken == '') {
                    $ccRs->Credit_Url->setNewVal('');
                }
            }

            // Use Card swipe
            if (isset($post[$indx . '_txtuseSwipe'])) {
            	$ccRs->Retry_Count->setNewVal(filter_var($post[$indx . '_txtuseSwipe'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Oauth Client Secret
            if (isset($post[$indx . '_txtsecret'])) {

                $pw = filter_var($post[$indx . '_txtsecret'], FILTER_UNSAFE_RAW);

                if ($pw != '' && $pw != self::PW_PLACEHOLDER) {
                    $ccRs->Password->setNewVal(encryptMessage($pw));
                } else if ($pw == '') {
                    $ccRs->Password->setNewVal('');
                }
            }

            //transaction webhook
            if (isset($post[$indx . '_cbTransactionWebhook']) && $ccRs->cc_name->getStoredVal() != '' && !$ccRs->Trans_Url->getStoredVal() > 0) {

                try {
                    $gway = new DeluxeGateway($dbh, $ccRs->cc_name->getStoredVal());
                    $webhookRequest = new SubscribeEventRequest($dbh, $gway);
                    $response = $webhookRequest->submit($webhookRequest::EVENT_TRANSACTION);

                    if ($response["success"] == true) {
                        $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " - " . $response['eventType'] . " webhook: " . $response['message']);
                        $ccRs->Trans_Url->setNewVal($response['eventSubscriptionId']);
                    }
                }catch(PaymentException $e){
                    $msg .= $e->getMessage();
                }

            }

            $num = 0;

            // Save record.
            if ($merchantName != '') {
                //Update

                $ccRs->Updated_By->setNewVal($uS->username);
                $ccRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));

                if ($num > 0) {

                    $logText = AbstractTableLog::getUpdateText($ccRs);
                    HouseLog::logGeneral($dbh, 'CC Gateway', $ccRs->idcc_gateway->getStoredVal(), $logText, $uS->username, 'update');

                    self::checkLocationTable($dbh, $merchantName);
                }

            } else if (empty($merchantName)) {
                $num = 'Merchant name is missing.';
            }

            // Set Merchant Rooms
            if (isset($post[$indx . '_cbSetRooms']) && $ccRs->cc_name->getStoredVal() != '') {

                $rooms = self::setMerchantRooms($dbh, $ccRs);

                if ($rooms > 0) {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " - " . $rooms . " rooms set to $merchantName");
                }

            }

            if (intval($num, 10) == 0 && $num !== 0) {
                $msg .= HTMLContainer::generateMarkup('p', $num);
            } else if ($num > 0) {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " " . $merchantName . " - Payment Credentials Updated.  ");
            } else {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " " . $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
            }
        }

        return $msg;
    }

    private static function insertGwRecord(\PDO $dbh, $gatewayName, $merchantName, CC_Hosted_GatewayRS $ccRs) {

        $uS = Session::getInstance();

        // Check for previous entry
        $rs = new CC_Hosted_GatewayRS();
        $rs->Gateway_Name->setStoredVal($gatewayName);
        $rs->cc_name->setStoredVal($merchantName);
        $rows = EditRS::select($dbh, $rs, array($rs->Gateway_Name, $rs->cc_name));

        if (count($rows) > 0) {
            return 'Merchant ' . $merchantName . ' already exists for gateway ' .$gatewayName;
        }

        //Insert gateway record
        $ccRs->Gateway_Name->setNewVal($gatewayName);
        $ccRs->cc_name->setNewVal($merchantName);
        $ccRs->Updated_By->setNewVal($uS->username);
        $ccRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $num = EditRS::insert($dbh, $ccRs);

        if ($num > 0) {
            $logText = AbstractTableLog::getInsertText($ccRs);
            HouseLog::logGeneral($dbh, 'CC Gateway', $num, $logText, $uS->username, 'insert');
        }

        return $num;
    }

    private static function setMerchantRooms(\PDO $dbh, CC_Hosted_GatewayRS $ccRs) {

        $idLocation = 0;
        $num = 0;

        $locRs = new LocationRS();
        $locRs->Merchant->setStoredVal($ccRs->cc_name->getStoredVal());
        $r = EditRS::select($dbh, $locRs, array($locRs->Merchant));

        if (count($r) > 0) {
            EditRS::loadRow($r[0], $locRs);
            $idLocation = $locRs->idLocation->getStoredVal();

            $num = $dbh->exec("update room set idLocation = $idLocation");
        }

        return $num;
    }

    private static function deleteMerchant(\PDO $dbh, CC_Hosted_GatewayRS $ccRs) {

        $uS = Session::getInstance();
        $num = '';
        $merchantName = $ccRs->cc_name->getStoredVal();

        //
        $result = EditRS::delete($dbh, $ccRs, array($ccRs->idcc_gateway));

        if ($result) {

            $logText = AbstractTableLog::getDeleteText($ccRs, $ccRs->idcc_gateway->getStoredVal());
            HouseLog::logGeneral($dbh, 'CC Gateway', $ccRs->idcc_gateway->getStoredVal(), $logText, $uS->username, 'delete');

            $num = 'Merchant ' . $merchantName . ' is deleted. ';

        } else {
            $num = 'Merchant ' . $merchantName . ' was not found! ';
        }

        // Deal with location table
        $locRs = new LocationRS();
        $locRs->Merchant->setStoredVal($merchantName);
        $r = EditRS::select($dbh, $locRs, array($locRs->Merchant));

        if (count($r) > 0) {

            EditRS::loadRow($r[0], $locRs);

            $result = EditRS::delete($dbh, $locRs, array($locRs->idLocation));

            if ($result) {
                $logText = AbstractTableLog::getDeleteText($locRs, $locRs->idLocation->getStoredVal());
                HouseLog::logGeneral($dbh, 'Location', $locRs->idLocation->getStoredVal(), $logText, $uS->username, 'delete');
                $num .= 'Location ' . $merchantName . ' is deleted. ';
            }

        } else {
            $num .= 'Location ' . $merchantName . ' was not found! ';
        }

        return $num;
    }

    private static function checkLocationTable(\PDO $dbh, $merchantName) {

        $uS = Session::getInstance();
        $num = 0;

        $locRs = new LocationRS();
        $locRs->Merchant->setStoredVal($merchantName);
        $locRows = EditRS::select($dbh, $locRs, array($locRs->Merchant));

        if (count($locRows) == 0) {
            // Insert

            $locRs = new LocationRS();
            $locRs->Merchant->setNewVal($merchantName);
            $locRs->Title->setNewVal(ucfirst($merchantName));
            $locRs->Status->setNewVal('a');
            $locRs->Updated_By->setNewVal($uS->username);
            $locRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $num = EditRS::insert($dbh, $locRs);

            if ($num > 0) {
                $logText = AbstractTableLog::getInsertText($locRs);
                HouseLog::logGeneral($dbh, 'Location', $num, $logText, $uS->username, 'insert');
            }

        } else {
            // Update
            EditRS::loadRow($locRows[0], $locRs);

            $locRs->Merchant->setNewVal($merchantName);
            $locRs->Title->setNewVal(ucfirst($merchantName));
            $locRs->Status->setNewVal('a');
            $locRs->Updated_By->setNewVal($uS->username);
            $locRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $num = EditRS::update($dbh, $locRs, array($locRs->idLocation));

            if ($num > 0) {
                $logText = AbstractTableLog::getUpdateText($locRs);
                HouseLog::logGeneral($dbh, 'Location', $locRs->idLocation->getStoredVal(), $logText, $uS->username, 'update');
            }
        }

        return $num;
    }

    public static function getIframeMkup(){
        return HTMLContainer::generateMarkup("div", "", ["id"=>"deluxeDialog", "style"=>"display:none;"]);
    }
}