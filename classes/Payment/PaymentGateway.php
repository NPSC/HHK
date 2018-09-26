<?php


/**
 * Description of PaymentGateway
 *
 * @author Eric
 */
abstract class PaymentGateway {

    const VANTIV = 'vantiv';
    const INSTAMED = 'instamed';
    const LOCAL = '';

    protected $gwName;
    protected $credentials;
    protected $responseErrors;

    public function __construct(\PDO $dbh, $gwName) {

        $this->setGwName($gwName);
        $this->setCredentials($this->loadGateway($dbh));
    }

    /**
     *  Get the gateway information from the database.
     */
    protected abstract function loadGateway(\PDO $dbh);

    /**
     *  Interpret database info into payment gateway credentials object.
     */
    protected abstract function setCredentials($credentials);

    public abstract function createEditMarkup(\PDO $dbh);
    public abstract function SaveEditMarkup(\PDO $dbh, $post);

    public static function logGwTx(PDO $dbh, $status, $request, $response, $transType) {

        $gwRs = new Gateway_TransactionRS();

        $gwRs->Vendor_Response->setNewVal($response);
        $gwRs->Vendor_Request->setNewVal($request);
        $gwRs->GwResultCode->setNewVal($status);
        $gwRs->GwTransCode->setNewVal($transType);

        return EditRS::insert($dbh, $gwRs);

    }

    public function updatePayTypes(\PDO $dbh, $username) {

        $uS = Session::getInstance();
        $msg = '';

        $glRs = new GenLookupsRS();
        $glRs->Table_Name->setStoredVal('Pay_Type');
        $glRs->Code->setStoredVal(PayType::Charge);
        $rows = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

        if (count($rows) > 0) {
            $glRs = new GenLookupsRS();
            EditRS::loadRow($rows[0], $glRs);


            if ($this->getGwName() != PaymentGateway::LOCAL) {
                $glRs->Substitute->setNewVal(PaymentMethod::Charge);
            } else {
                $glRs->Substitute->setNewVal(PaymentMethod::ChgAsCash);
            }


            $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if ($ctr > 0) {
                $logText = HouseLog::getUpdateText($glRs);
                HouseLog::logGenLookups($dbh, 'Pay_Type', PayType::Charge, $logText, "update", $username);
                $msg = "Pay_Type Charge is updated.  ";
            }
        }

        return $msg;
    }


    public static function factory(\PDO $dbh, $gwType, $gwName) {


        switch ($gwType) {

            case PaymentGateway::VANTIV:

                return new VantivGateway($dbh, $gwName);
                break;

            case PaymentGateway::INSTAMED:

                return new InstamedGateway($dbh, $gwName);
                break;

            default:

                return new LocalGateway($dbh, $gwName);
        }
    }

    public function getGwName() {
        return $this->gwName;
    }
    public function getResponseErrors() {
        return $this->responseErrors;
    }

    public function getCredentials() {
        return $this->credentials;
    }

    protected function setGwName($gwName) {
        $this->gwName = $gwName;
        return $this;
    }

}


class LocalGateway extends PaymentGateway {

    public function initHostedPayment(\PDO $dbh, Invoice $invoice, Guest $guest, $addr, $postbackUrl) {

    }
    protected function loadGateway(\PDO $dbh) {
        return '';
    }

    protected function setCredentials($gwRs) {
        return '';
    }

    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {
        return '';
    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

    }

}


class VantivGateway extends PaymentGateway {

    public function initHostedPayment(\PDO $dbh, Invoice $invoice, Guest $guest, $addr, $postbackUrl) {

        $uS = Session::getInstance();

        // Do a hosted payment.
        $config = new Config_Lite(ciCFG_FILE);
        $secure = new SecurityComponent();

        $houseUrl = $secure->getSiteURL();
        $siteUrl = $secure->getRootURL();
        $logo = $config->getString('financial', 'PmtPageLogoUrl', '');

        if ($houseUrl == '' || $siteUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
        }

        $pay = new InitCkOutRequest($uS->siteName, 'Custom');


        // Card reader?
        if ($uS->CardSwipe) {
            $pay->setDefaultSwipe('Swipe')
                ->setCardEntryMethod('Both')
                ->setPaymentPageCode('Checkout_Url');
        } else {
            $pay->setPaymentPageCode('Checkout_Url');
        }

        $pay->setPartialAuth(TRUE);

        $pay    ->setAVSZip($addr["Postal_Code"])
                ->setAVSAddress($addr['Address_1'])
                ->setCardHolderName($guest->getRoleMember()->get_fullName())
                ->setFrequency(MpFrequencyValues::OneTime)
                ->setInvoice($invoice->getInvoiceNumber())
                ->setMemo(MpVersion::PosVersion)
                ->setTaxAmount(0)
                ->setTotalAmount($invoice->getBalance())
                ->setCompleteURL($houseUrl . $postbackUrl)
                ->setReturnURL($houseUrl . $postbackUrl)
                ->setTranType(MpTranType::Sale)
                ->setLogoUrl($siteUrl . $logo)
                ->setCVV('on')
                ->setAVSFields('both');

        $CreditCheckOut = HostedCheckout::sendToPortal($dbh, $this->gwName, $invoice->getSoldToId(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $pay);

        return $CreditCheckOut;
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $cardHolderName, $postBackPage) {

        $uS = Session::getInstance();

        $secure = new SecurityComponent();
        $config = new Config_Lite(ciCFG_FILE);

        $houseUrl = $secure->getSiteURL();
        $siteUrl = $secure->getRootURL();
        $logo = $config->getString('financial', 'PmtPageLogoUrl', '');

        if ($houseUrl == '' || $siteUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        if ($idGuest < 1 || $idGroup < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
        }


        $initCi = new InitCiRequest($pageTitle, 'Custom');

        // Card reader?
        if ($uS->CardSwipe) {
            $initCi->setDefaultSwipe('Swipe')
                ->setCardEntryMethod('Both')
                ->setPaymentPageCode('CardInfo_Url');
        } else {
            $initCi->setPaymentPageCode('CardInfo_Url');
        }

        $initCi->setCardHolderName($cardHolderName)
                ->setFrequency(MpFrequencyValues::OneTime)
                ->setCompleteURL($houseUrl . $postBackPage)
                ->setReturnURL($houseUrl . $postBackPage)
                ->setLogoUrl($siteUrl . $logo);


        return CardInfo::sendToPortal($dbh, $this->gwName, $idGuest, $idGroup, $initCi);
    }


    protected function loadGateway(\PDO $dbh) {

        $query = "select * from `cc_hosted_gateway` where cc_name = :ccn";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':ccn'=>$this->gwName));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) != 1) {
            throw new Hk_Exception_Runtime('The credit card payment gateway is not defined.');
        }

        if (isset($rows[0]['Password']) && $rows[0]['Password'] != '') {
            $rows[0]['Password'] = decryptMessage($rows[0]['Password']);
        }

        return $rows[0];

    }

    protected function setCredentials($gwRs) {

        $this->credentials = $gwRs;

    }


    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {

        $stmt = $dbh->query("Select * from cc_hosted_gateway");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $indx = $r['idcc_gateway'];
            $tbl->addBodyTr(HTMLTable::makeTd($r['cc_name'], array('class'=>'tdlabel'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup($r['Merchant_Id'], array('name'=>$indx . '_txtMid')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup($r['Password'], array('name'=>$indx .'_txtpw')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>$indx .'_txtpw2')))
             .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>$indx .'cbDel'))));
        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan'=>'4', 'style'=>'font-weight:bold;')));
        }

        $tbl->addHeader(HTMLTable::makeTh('Name') . HTMLTable::makeTh('Merchant Id')
                . HTMLTable::makeTh('Password') . HTMLTable::makeTh('Password Again') . HTMLTable::makeTh('Delete'));

        return $tbl->generateMarkup();
    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

        $msg = '';
        $ccRs = new Cc_Hosted_GatewayRS();
        $rows = EditRS::select($dbh, $ccRs, array());

        foreach ($rows as $r) {

            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();

            // Clear the entries??
            if (isset($post[$indx . 'cbDel'])) {

                $ccRs->Merchant_Id->setNewVal('');
                $ccRs->Password->setNewVal('');
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Deleted.  ");

            } else {

                if (isset($post[$indx . '_txtMid'])) {
                    $mid = filter_var($post[$indx . '_txtMid'], FILTER_SANITIZE_STRING);
                        $ccRs->Merchant_Id->setNewVal($mid);
                }

                if (isset($post[$indx . '_txtpw']) && isset($post[$indx . '_txtpw2']) && $post[$indx . '_txtpw2'] != '') {

                    $pw = filter_var($post[$indx . '_txtpw'], FILTER_SANITIZE_STRING);
                    $pw2 = filter_var($post[$indx . '_txtpw2'], FILTER_SANITIZE_STRING);

                    if ($pw != '' && $pw != $ccRs->Password->getStoredVal()) {

                        // Don't save the pw blank characters
                        if ($pw == $pw2) {

                            $ccRs->Password->setNewVal(encryptMessage($pw));

                        } else {
                            // passwords don't match
                            $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Passwords do not match.  ");
                        }
                    }
                }

                // Save record.
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));

                if ($num > 0) {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Updated.  ");
                } else {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
                }

            }
        }

        return $msg;
    }
}


/**
 * Description of InstamedGateway
 *
 * @author Eric
 */
class InstamedGateway extends PaymentGateway {

    const RELAY_STATE = 'relayState';
    const INVOICE_NUMBER = 'additionalInfo1';
    const GROUP_ID = 'additionalInfo2';

    const HCO_POSTBACK_VAR = 'imco';
    const POSTBACK_CANCEL = 'x';
    const POSTBACK_COMPLETE = 'c';

    protected $ssoUrl;
    protected $soapUrl;
    protected $saleUrl;
    protected $cofUrl;

    public function initHostedPayment(\PDO $dbh, Invoice $invoice, Guest $guest, $addr, $postbackUrl) {

        $uS = Session::getInstance();

        // Do a hosted payment.
        $secure = new SecurityComponent();
        $houseUrl = $secure->getSiteURL();

        if ($houseUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        $houseUrl .= $postbackUrl . '?' . InstamedGateway::HCO_POSTBACK_VAR . '=';

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
        }

        $patInfo = $this->getPatientInfo($dbh, $idGroup);

        $data = array (
            'patientID' => $patInfo['idName'],
            'patientFirstName' => $patInfo['Name_First'],
            'patientLastName' => $patInfo['Name_Last'],
            'amount' => $invoice->getBalance(),

            InstamedGateway::GROUP_ID => $invoice->getIdGroup(),
            InstamedGateway::INVOICE_NUMBER => $invoice->getInvoiceNumber(),

            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,

            'creditCardKeyed ' => 'true',
            'incontext' => 'true',
            'lightWeight' => 'true',
            'preventCheck' => 'true',
            'preventCash'  => 'true',
            'suppressReceipt' => 'true',
            'hideGuarantorID' => 'true',
            'responseActionType' => 'header',

            'cancelURL' => $houseUrl . InstamedGateway::POSTBACK_CANCEL,
            'confirmURL' => $houseUrl . InstamedGateway::POSTBACK_COMPLETE,
            'requestToken' => 'true',
            'RelayState' => $this->saleUrl,
        );

        $headerResponse = $this->doHeaderRequest(http_build_query(array_merge($data, $this->getCredentials()->toNVP())));

        if ($headerResponse->getToken() != '') {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                . " values (" . $invoice->getSoldToId() . " , " . $invoice->getIdGroup() . ", 'hco', '" . $invoice->getInvoiceNumber() . "', '" . $headerResponse->getToken() . "', now(), 'OneTime', " . $headerResponse->getResponseCode() . ")";

            $dbh->exec($ciq);

            $uS->imtoken = $headerResponse->getToken();

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'paymentId' => $headerResponse->getToken() );

        } else {

            // The initialization failed.
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $headerResponse->getResponseMessage());

        }

        return $dataArray;

    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $cardHolderName, $postbackUrl) {

        $uS = Session::getInstance();

        // Do a hosted payment.
        $secure = new SecurityComponent();
        $houseUrl = $secure->getSiteURL();

        if ($houseUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        $houseUrl .= $postbackUrl . '?' . InstamedGateway::HCO_POSTBACK_VAR . '=';

        $patInfo = $this->getPatientInfo($dbh, $idGroup);

        $data = array (
            'patientID' => $patInfo['idName'],
            'patientFirstName' => $patInfo['Name_First'],
            'patientLastName' => $patInfo['Name_Last'],

            InstamedGateway::GROUP_ID => $idGroup,

            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,

            //'creditCardKeyed ' => 'true',
            'incontext' => 'true',
            'lightWeight' => 'true',
            'preventCheck' => 'true',
            'preventCash'  => 'true',
            'suppressReceipt' => 'true',
            'hideGuarantorID' => 'true',
            'responseActionType' => 'header',

            'cancelURL' => $houseUrl . InstamedGateway::POSTBACK_CANCEL,
            'confirmURL' => $houseUrl . InstamedGateway::POSTBACK_COMPLETE,
            'requestToken' => 'true',
            'RelayState' => $this->saleUrl,
        );

        $headerResponse = $this->doHeaderRequest(http_build_query(array_merge($data, $this->getCredentials()->toNVP())));

        if ($headerResponse->getToken() != '') {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                . " values (" . $invoice->getSoldToId() . " , " . $invoice->getIdGroup() . ", 'hco', '" . $invoice->getInvoiceNumber() . "', '" . $headerResponse->getToken() . "', now(), 'OneTime', " . $headerResponse->getResponseCode() . ")";

            $dbh->exec($ciq);

            $uS->imtoken = $headerResponse->getToken();

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'paymentId' => $headerResponse->getToken() );

        } else {

            // The initialization failed.
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $headerResponse->getResponseMessage());

        }

        return $dataArray;
    }

    public function HostedPaymentComplete(\PDO $dbh, $idToken, $paymentNotes) {


        // Poll for results.
/*
        do  {

            $result = $this->pollPaymentStatus($idToken, TRUE);

            if ($result->isExpired()) {

            }

            if ($result->isComplete()) {

            }

            sleep(10);

        } while ($result->isWaiting());



        if ($result->isExpired()) {

        }

        if ($result->isComplete()) {
*/
	        //get transaction details
	        $url = "https://online.instamed.com/payment/NVP.aspx?";
	        $params = "merchantID=" . $this->getCredentials()->merchantId
	        		. "&storeID=" . $this->getCredentials()->storeId
	        		. "&terminalID=001"
	        		. "&transactionAction=ViewReceipt"
	        		. "&requestToken=false"
	        		. "&allowPartialPayment=false"
	        		. "&singleSignOnToken=" . $idToken;
	        
	        //var_dump($url . $params);
	        		
			$ch = curl_init();
	
	        curl_setopt($ch, CURLOPT_URL, $url . $params);
	        curl_setopt($ch, CURLOPT_USERPWD, "NP.SOFTWARE.TEST:vno9cFqM");
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			
	        $responseString = curl_exec($ch);
	        parse_str($responseString, $transaction);
	        //var_dump($transaction);
	        //IsEMVVerifiedByPIN=false&isEMVTransaction=false&EMVCardEntryMode=Keyed&isSignatureRequired=false&cardBrand=VISA&cardExpirationMonth=12&cardExpirationYear=2021&cardBINNumber=411111&cardHolderName= &paymentCardType=Credit&lastFourDigits=1111&authorizationNumber=A2CDB9&responseCode=000&responseMessage=APPROVAL&transactionStatus=C&primaryTransactionID=c5a1a5a099f748c8bf16b890c8b371ec&authorizationText=I AGREE TO PAY THE ABOVE AMOUNT ACCORDING TO MY CARD HOLDER AGREEMENT.&transactionID=c5a1a5a099f748c8bf16b890c8b371ec&paymentPlanID=ccc366b1641444fe9e59620340d5e06c&transactionDate=2018-09-26T19:05:40.1666074Z
	        
	        curl_close($ch);
	        
	        $response = new VerifyCurlResponse($transaction);
	        // Check paymentId
			$cidInfo = PaymentSvcs::getInfoFromCardId($dbh, $idToken);
			
	        $vr = new CheckOutResponse($response, $cidInfo['idName'], $cidInfo['idGroup'], $cidInfo['InvoiceNumber'], $payNotes);


	        // Save raw transaction in the db.
	        try {
	            Gateway::saveGwTx($dbh, $vr->response->getStatus(), json_encode($verify->getFieldsArray()), json_encode($vr->response->getResultArray()), 'HostedCoVerify');
	        } catch(Exception $ex) {
	            // Do Nothing
	        }
	
	        // Record transaction
	        try {
	
	            if ($verifyResponse->getTranType() == MpTranType::ReturnAmt) {
	                $trType = TransType::Retrn;
	            } else if ($verifyResponse->getTranType() == MpTranType::Sale) {
	                $trType = TransType::Sale;
	            }
	
	            $transRs = Transaction::recordTransaction($dbh, $vr, $gw, $trType, TransMethod::HostedPayment);
	            $vr->setIdTrans($transRs->idTrans->getStoredVal());
	
	        } catch(Exception $ex) {
	
	        }
	
	        // record payment
	        return SaleReply::processReply($dbh, $vr, $uS->username);

//        }

    }

    protected function pollPaymentStatus($token, $trace = FALSE) {

        $data = $this->getCredentials()->toSOAP();

        $data['tokenID'] = $token;

        $soapReq = new PollingRequest();

        return new PollingResponse($soapReq->submit($data, $this->soapUrl, $trace));

    }

    protected function loadGateway(\PDO $dbh) {

        $gwRs = new InstamedGatewayRS();
        $gwRs->cc_name->setStoredVal($this->getGwName());


        $rows = EditRS::select($dbh, $gwRs, array($gwRs->cc_name));

        if (count($rows) == 1) {

            $gwRs = new InstamedGatewayRS();
            EditRS::loadRow($rows[0], $gwRs);

        } else {
            throw new Hk_Exception_Runtime('The credit card payment gateway is not defined.');
        }

        return $gwRs;
    }

    protected function setCredentials($gwRs) {

        $this->credentials = new InstaMedCredentials($gwRs);
        $this->ssoUrl = $gwRs->providersSso_Url->getStoredVal();
        $this->soapUrl = $gwRs->soap_Url->getStoredVal();
        $this->saleUrl = $gwRs->sale_Url->getStoredVal();
        $this->cofUrl = $gwRs->COF_Url->getStoredVal();
    }

    public function doHeaderRequest($data) {

        //Create HTTP stream context
        $context = stream_context_create(array(
            'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded,'.'\r\n'.
            'Content-Length:'.strlen($data).'\r\n'.
            'Expect: 100-continue,'.'\r\n'.
            'Connection: Keep-Alive,'.'\r\n',
            'content' => $data
            )
        ));

        $headers = get_headers($this->ssoUrl, 1, $context);

        return new HeaderResponse($headers);

    }

    protected function getPatientInfo(\PDO $dbh, $idRegistration) {

        $idReg = intval($idRegistration);

        $stmt = $dbh->query("Select n.idName, n.Name_First, n.Name_Last
from registration r join psg p on r.idPsg = p.idPsg
	join name n on p.idPatient = n.idName
where r.idRegistration =" . $idReg);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            return $rows[0];
        }

        return array();
    }

    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {

        $gwRs = new InstamedGatewayRS();
        $rows = EditRS::select($dbh, $gwRs, array());

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $gwRs = new InstamedGatewayRS();
            EditRS::loadRow($r, $gwRs);

            $indx = $gwRs->idcc_gateway->getStoredVal();

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Name', array('style'=>'border-top:2px solid black;', 'class'=>'tdlabel'))
                    .HTMLTable::makeTd($gwRs->cc_name->getStoredVal(), array('style'=>'border-top:2px solid black;'))
            );

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Account Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->account_Id->getStoredVal(), array('name'=>$indx . '_txtaid', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Security Key', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->security_Key->getStoredVal(), array('name'=>$indx .'_txtsk', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO Alias', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->sso_Alias->getStoredVal(), array('name'=>$indx .'_txtsalias', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Merchant Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->merchant_Id->getStoredVal(), array('name'=>$indx .'_txtuid', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Store Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->store_Id->getStoredVal(), array('name'=>$indx .'_txtuname', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->providersSso_Url->getStoredVal(), array('name'=>$indx .'_txtpurl', 'size'=>'90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SOAP URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->soap_Url->getStoredVal(), array('name'=>$indx .'_txtsurl', 'size'=>'90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Sale URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->sale_Url->getStoredVal(), array('name'=>$indx .'_txtsaurl', 'size'=>'90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Card on File URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->COF_Url->getStoredVal(), array('name'=>$indx .'_txtcofurl', 'size'=>'90')))
            );

        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan'=>'2', 'style'=>'font-weight:bold;')));
        }

        return $tbl->generateMarkup();
    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

        $msg = '';
        $ccRs = new InstamedGatewayRS();

        $rows = EditRS::select($dbh, $ccRs, array());

        foreach ($rows as $r) {

            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();

            if (isset($post[$indx . '_txtaid'])) {
                $ccRs->account_Id->setNewVal(filter_var($post[$indx . '_txtaid'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtsalias'])) {
                $ccRs->sso_Alias->setNewVal(filter_var($post[$indx . '_txtsalias'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtuid'])) {
                $ccRs->merchant_Id->setNewVal(filter_var($post[$indx . '_txtuid'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtuname'])) {
                $ccRs->store_Id->setNewVal(filter_var($post[$indx . '_txtuname'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtpurl'])) {
                $ccRs->providersSso_Url->setNewVal(filter_var($post[$indx . '_txtpurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtsurl'])) {
                $ccRs->soap_Url->setNewVal(filter_var($post[$indx . '_txtsurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtsaurl'])) {
                $ccRs->sale_Url->setNewVal(filter_var($post[$indx . '_txtsaurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtcofurl'])) {
                $ccRs->COF_Url->setNewVal(filter_var($post[$indx . '_txtcofurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtsk'])) {

                $pw = filter_var($post[$indx . '_txtsk'], FILTER_SANITIZE_STRING);

                if ($pw != '' && $ccRs->security_Key->getStoredVal() != $pw) {
                    $ccRs->security_Key->setNewVal(encryptMessage($pw));
                } else if ($pw == '') {
                    $ccRs->security_Key->setNewVal('');
                }
            }

            // Save record.
            $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));

            if ($num > 0) {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Updated.  ");
            } else {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
            }
        }

        return $msg;

    }
}


class InstaMedCredentials {

    // NVP names
    const SEC_KEY = 'securityKey';
    const ACCT_ID = 'accountID';
    const SSO_ALIAS = 'ssoAlias';
    const MERCHANT_ID = 'merchantId';
    const STORE_ID = 'storeId';
    const U_NAME = 'userName';
    const U_ID = 'userID';

    protected $securityKey;
    protected $accountID;
    protected $ssoAlias;
    public $merchantId;
    public $storeId;


    public function __construct(InstamedGatewayRS $gwRs) {

        $this->accountID = $gwRs->account_Id->getStoredVal();
        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->ssoAlias = $gwRs->sso_Alias->getStoredVal();
        $this->merchantId = $gwRs->merchant_Id->getStoredVal();
        $this->storeId = $gwRs->store_Id->getStoredVal();

    }

    public function toNVP() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            InstaMedCredentials::SEC_KEY => decryptMessage($this->securityKey),
            InstaMedCredentials::SSO_ALIAS => $this->ssoAlias,
        );
    }

    public function toSOAP() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            'password' => decryptMessage($this->securityKey),
            'alias' => $this->ssoAlias,
        );
    }

}

class HeaderResponse extends GatewayResponse {

    protected function parseResponse() {

        //"https://online.instamed.com/providers/Form/SSO/SSOError?respCode=401&respMessage=Invalid AccountID or Password.&lightWeight=true"

        if (isset($this->response[InstamedGateway::RELAY_STATE])) {

            $qs = parse_url($this->response[InstamedGateway::RELAY_STATE], PHP_URL_QUERY);
            parse_str($qs, $this->result);

            $this->result[InstamedGateway::RELAY_STATE] = $this->response[InstamedGateway::RELAY_STATE];

        } else {
            $this->errors = 'response is missing. ';
        }

    }

    public function getRelayState() {
        return $this->result[InstamedGateway::RELAY_STATE];
    }

    public function getToken() {

        if (isset($this->result['token'])) {
            return $this->result['token'];
        }

        return '';
    }

    public function getResponseCode() {

        if (isset($this->result['respCode'])) {

            return intval($this->result['respCode'], 10);
        }

        return 0;
    }

    public function getResponseMessage() {

        if (isset($this->result['respMessage'])) {
            return $this->result['respMessage'];
        }

        return '';
    }
}


class PollingRequest extends SoapRequest {

    protected function execute(\SoapClient $soapClient, $data) {
        return new PollingResponse($soapClient->GetSSOTokenStatus($data));
    }
}


class PollingResponse extends GatewayResponse {

    const WAIT = 'NEW';
    const EXPIRED = 'EXPIRED';
    const COMPLETE = 'complete';


    protected function parseResponse() {

        if (isset($this->response->GetSSOTokenStatusResponse)) {
            $this->result = $this->response->GetSSOTokenStatusResponse;
        } else {
            throw new Hk_Exception_Payment("GetSSOTokenStatusResponse is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['GetSSOTokenStatusResult'])) {
            return $this->result['GetSSOTokenStatusResult'];
        } else {
            throw new Hk_Exception_Payment("GetSSOTokenStatusResult is missing from the payment gateway response.  ");
        }
    }

    public function isWaiting() {
        if ($this->getResponseCode() == PollingResponse::WAIT) {
            return TRUE;
        }
        return FALSE;
    }

    public function isExpired() {
        if ($this->getResponseCode() == PollingResponse::EXPIRED) {
            return TRUE;
        }
        return FALSE;
    }

    public function isComplete() {
        if ($this->getResponseCode() == PollingResponse::COMPLETE) {
            return TRUE;
        }
        return FALSE;
    }

}

class VerifyCurlResponse extends GatewayResponse {

    function __construct($response) {
        parent::__construct($response);
		
		if(is_array($response)){
			$this->result = $response;
		}else{
			throw new Hk_Exception_Payment("Curl transaction response is invalid.  ");
		}
        

    }
	
	public function parseResponse(){
		return '';
	}
	
	public function getResponseCode() {
        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }
        return '';
    }
	
    public function getStatus() {
        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }
        return '';
    }

    public function getStatusMessage() {
        if (isset($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }
        return '';
    }

    public function getMessage() {
        if (isset($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }
        return '';
    }

    public function getDisplayMessage() {
        if (isset($this->result->DisplayMessage)) {
            return $this->result->DisplayMessage;
        }
        return '';
    }

    public function getToken() {
        /*
if (isset($this->result->Token)) {
            return $this->result->Token;
        }
*/
        return '';
    }

    public function getCardType() {
        if (isset($this->result['cardBrand'])) {
            return $this->result['cardBrand'];
        }
        return '';
    }

    public function getCardUsage() {
        /*
if (isset($this->result->CardUsage)) {
            return $this->result->CardUsage;
        }
*/
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result['lastFourDigits'])) {
            return $this->result['lastFourDigits'];
        }
        return '';
    }

    public function getTranType() {
        /*
if (isset($this->result->TranType)) {
            return $this->result->TranType;
        }
*/
        return '';
    }

    public function getPaymentIDExpired() {
        /*
if (isset($this->result->PaymentIDExpired)) {
            return $this->result->PaymentIDExpired;
        }
*/
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result['cardHolderName'])) {
            return $this->result['cardHolderName'];
        }
        return '';
    }

    public function getExpDate() {	 
	       
        if (isset($this->result['cardExpirationMonth']) && isset($this->result['cardExpirationYear'])) {
	        if($this->result['cardExpirationMonth'] < 10){
            	$month = '0' . $this->result['cardExpirationMonth'];
            }else{
	            $month = $this->result['cardExpirationMonth'];
            }
            
            $year = $this->result['cardExpirationYear'];
            
            return $month . '/' . $year;
        }
        
        return '';
    }

    public function getAcqRefData() {
        /*
if (isset($this->result->AcqRefData)) {
            return $this->result->AcqRefData;
        }
*/
        return '';
    }

    public function getAuthorizeAmount() {
        /*
if (isset($this->result->AuthAmount)) {
            return $this->result->AuthAmount;
        }
*/
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result['authorizationNumber'])) {
            return $this->result['authorizationNumber'];
        }
        return '';
    }

    public function getAVSAddress() {
        // Address used for AVS verification. Note it is truncated to 8 characters.
/*
        if (isset($this->result->AVSAddress)) {
            return $this->result->AVSAddress;
        }
*/
        return '';
    }

    public function getAVSResult() {
/*
        if (isset($this->result->AvsResult)) {
            return $this->result->AvsResult;
        }
*/
        return '';
    }

    public function getAVSZip() {
        // Postal code used for AVS verification
/*
        if (isset($this->result->AVSZip)) {
            return $this->result->AVSZip;
        }
*/
        return '';
    }

    public function getCvvResult() {
/*
        if (isset($this->result->CvvResult)) {
            return $this->result->CvvResult;
        }
*/
        return '';
    }

    public function getInvoice() {
/*
        if (isset($this->result->Invoice)) {
            return $this->result->Invoice;
        }
*/
        return '';
    }

    public function getMemo() {
/*
        if (isset($this->result->Memo)) {
            return $this->result->Memo;
        }
*/
        return '';
    }

    public function getProcessData() {
/*
        if (isset($this->result->ProcessData)) {
            return $this->result->ProcessData;
        }
*/
        return '';
    }

    public function getRefNo() {
/*
        if (isset($this->result->RefNo)) {
            return $this->result->RefNo;
        }
*/
        return '';
    }

    public function getTaxAmount() {
/*
        if (isset($this->result->TaxAmount)) {
            return $this->result->TaxAmount;
        }
*/
        return '';
    }

    public function getAmount() {
/*
        if (isset($this->result->Amount)) {
            return $this->result->Amount;
        }
*/
        return '';
    }

    public function getTransPostTime() {
        if (isset($this->result['transactionDate'])) {
            return $this->result['transactionDate'];
        }
        return '';
    }

    public function getCustomerCode() {
/*
        if (isset($this->result->CustomerCode)) {
            return $this->result->CustomerCode;
        }
*/
        return '';
    }

    public function getOperatorID() {
/*
        if (isset($this->result->OperatorID)) {
            return $this->result->OperatorID;
        }
*/
        return '';
    }


}