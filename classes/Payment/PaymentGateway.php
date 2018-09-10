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

    protected $ssoUrl;

    public function initHostedPayment(\PDO $dbh, Invoice $invoice, Guest $guest, $addr, $postbackUrl) {

        $uS = Session::getInstance();

        // Do a hosted payment.
        $secure = new SecurityComponent();
        $houseUrl = $secure->getSiteURL();

        if ($houseUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
        }

        $data = array (
            "patientID" => $invoice->getSoldToId(),
            "patientFirstName" => $guest->getRoleMember()->get_firstName(),
            "patientLastName" => $guest->getRoleMember()->get_lastName(),
            "amount" => $invoice->getBalance(),

            InstamedGateway::GROUP_ID => $invoice->getIdGroup(),
            InstamedGateway::INVOICE_NUMBER => $invoice->getInvoiceNumber(),

            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,

            "lightWeight" => 'true',
            'preventCheck' => 'true',
            'preventCash'  => 'true',
            "hideGuarantorID" => 'true',
            "responseActionType" => 'header',
            'returnURL' => $houseUrl . $postbackUrl,
            "requestToken" => 'true',
            "RelayState" => "https://online.instamed.com/providers/Form/PatientPayments/NewPaymentSimpleSSO",
        );

        $headerResponse = $this->doHeaderRequest(http_build_query(array_merge($data, $this->getCredentials()->toNVP())));

        if ($headerResponse->getToken() != '') {


            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                . " values (" . $invoice->getSoldToId() . " , " . $invoice->getIdGroup() . ", 'hco', '" . $invoice->getInvoiceNumber() . "', '" . $headerResponse->getToken() . "', now(), 'OneTime', " . $headerResponse->getResponseCode() . ")";

            $dbh->exec($ciq);


            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'paymentId' => $headerResponse->getToken() );

        } else {

            // The initialization failed.
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $headerResponse->getResponseMessage());

        }

        return $dataArray;

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
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->account_Id->getStoredVal(), array('name'=>$indx . '_txtaid', 'size'=>'50')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Security Key', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->security_Key->getStoredVal(), array('name'=>$indx .'_txtsk', 'size'=>'50')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO Alias', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->sso_Alias->getStoredVal(), array('name'=>$indx .'_txtsalias', 'size'=>'50')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('User Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->user_Id->getStoredVal(), array('name'=>$indx .'_txtuid', 'size'=>'50')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('User Name', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->user_Name->getStoredVal(), array('name'=>$indx .'_txtuname', 'size'=>'50')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->providersSso_Url->getStoredVal(), array('name'=>$indx .'_txtpurl', 'size'=>'70')))
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
                $ccRs->user_Id->setNewVal(filter_var($post[$indx . '_txtuid'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtuname'])) {
                $ccRs->user_Name->setNewVal(filter_var($post[$indx . '_txtuname'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtpurl'])) {
                $ccRs->providersSso_Url->setNewVal(filter_var($post[$indx . '_txtpurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtsk'])) {

                $pw = filter_var($post[$indx . '_txtsk'], FILTER_SANITIZE_STRING);

                if ($pw != '') {
                    $ccRs->security_Key->setNewVal(encryptMessage($pw));
                } else {
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
    const U_NAME = 'userName';
    const U_ID = 'userID';


    protected $securityKey;
    protected $accountID;
    protected $ssoAlias;
//    protected $userName;
//    protected $userID;


    public function __construct(InstamedGatewayRS $gwRs) {

        $this->accountID = $gwRs->account_Id->getStoredVal();
        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->ssoAlias = $gwRs->sso_Alias->getStoredVal();
//        $this->userID = $gwRs->user_Id->getStoredVal();
//        $this->userName = $gwRs->user_Name->getStoredVal();

    }

    public function toNVP() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            InstaMedCredentials::SEC_KEY => decryptMessage($this->securityKey),
            InstaMedCredentials::SSO_ALIAS => $this->ssoAlias,
//            InstaMedCredentials::U_ID => $this->userID,
//            InstaMedCredentials::U_NAME => $this->userName,
        );
    }

}

class HeaderResponse extends GatewayResponse {

    protected function parseResponse($headers) {

        //"https://online.instamed.com/providers/Form/SSO/SSOError?respCode=401&respMessage=Invalid AccountID or Password.&lightWeight=true"

        if (isset($headers[InstamedGateway::RELAY_STATE])) {

            $qs = parse_url($headers[InstamedGateway::RELAY_STATE], PHP_URL_QUERY);
            parse_str($qs, $this->result);

            $this->result[InstamedGateway::RELAY_STATE] = $headers[InstamedGateway::RELAY_STATE];

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