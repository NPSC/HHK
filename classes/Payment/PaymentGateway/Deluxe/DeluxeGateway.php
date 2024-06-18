<?php
namespace HHK\Payment\PaymentGateway\Deluxe;

use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\Payment\CreditToken;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\TableLog\AbstractTableLog;
use HHK\TableLog\HouseLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\LocationRS;
use HHK\Tables\PaymentGW\CC_Hosted_GatewayRS;
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

        if (isset($post['ReturnCode'])) {
            $rtnCode = intval(filter_var($post['ReturnCode'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['ReturnMessage'])) {
            $rtnMessage = filter_var($post['ReturnMessage'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // THis eventually selects the merchant id
        if (isset($uS->manualKey)) {
        	$this->manualKey = $uS->manualKey;
        }

        //captured card info
        if(isset($post['token'])){
            $data = $post;
            $mkup = "";
            foreach($post as $k=>$v){
                $mkup .= HTMLContainer::generateMarkup("p", $k . ": " . $v);
            }
            $mkup = HTMLContainer::generateMarkup("div", $mkup);
            $data["cardInfoMkup"] = $mkup;
            echo json_encode($data);
            exit;
        }
/*
        if (isset($post[VantivGateway::PAYMENT_ID])) {

            $paymentId = filter_var($post[VantivGateway::PAYMENT_ID], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $cidInfo = $this->getInfoFromCardId($dbh, $paymentId);

            try {
                self::logGwTx($dbh, $rtnCode, '', json_encode($post), 'HostedCoPostBack');
            } catch (\Exception $ex) {
                // Do nothing
            }

            if ($rtnCode > 0) {

                $payResult = new PaymentResult($idInv, $cidInfo['idGroup'], $cidInfo['idName']);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($rtnMessage);
                return $payResult;
            }

            try {

                $csResp = HostedCheckout::portalReply($dbh, $this, $cidInfo, $payNotes, $payDate);

                if ($csResp->response->getTranType() == MpTranType::ZeroAuth) {

                    // Zero auth card info
                    $payResult = new CofResult($csResp->response->getDisplayMessage(), $csResp->response->getStatus(), $csResp->idPayor, $csResp->idRegistration);

                    if ($this->useAVS) {
                        $avsResult = new AVSResult($csResp->response->getAVSResult());

                        if ($avsResult->isZipMatch() === FALSE) {
                            $payResult->setDisplayMessage($avsResult->getResultMessage() . '  ');
                        }
                    }

                    if ($this->useCVV) {
                        $cvvResult = new CVVResult($csResp->response->getCvvResult());
                        if ($cvvResult->isCvvMatch() === FALSE) {
                            $payResult->setDisplayMessage($cvvResult->getResultMessage() . '  ');
                        }
                    }

                } else {

                    // Hosted payment response.
                    if ($csResp->getInvoiceNumber() != '') {

                        $invoice = new Invoice($dbh, $csResp->getInvoiceNumber());

                        // Analyze the result
                        $payResult = $this->analyzeCredSaleResult($dbh, $csResp, $invoice, 0);

                    } else {

                        $payResult = new PaymentResult($idInv, $cidInfo['idGroup'], $cidInfo['idName']);
                        $payResult->setStatus(PaymentResult::ERROR);
                        $payResult->setDisplayMessage('Invoice Not Found!  ');
                    }
                }
            } catch (PaymentException $hex) {

                $payResult = new PaymentResult($idInv, $cidInfo['idGroup'], $cidInfo['idName']);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($hex->getMessage());
            }
        }

        return $payResult;
        */
    }
    
    Protected function initHostedPayment(\PDO $dbh, Invoice $invoice) {

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
            return HostedPaymentForm::sendToPortal($dbh, $this, $invoice->getSoldToId(), $invoice->getIdGroup(), $this->manualKey, "");
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
            return HostedPaymentForm::sendToPortal($dbh, $this, $idGuest, $idGroup, $manualKey, $postbackUrl);
        }

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
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup($sel, $selArray)
            				, ['colspan'=>'2'])
            		, ['id'=>'trvdCHName'.$index, 'class'=>'tblCredit'.$index]
            );

            $payTbl->addBodyTr(
                HTMLTable::makeTh('Capture Method:', ['style'=>'text-align:right;'])
                .HTMLTable::makeTd($keyCb, ['colspan'=>'2'])
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
                    .HTMLTable::makeTd(
                    		HTMLSelector::generateMarkup($sel, $selArray)
                    		. $keyCb
                    		, ['colspan'=>'2'])
                    , ['id'=>'trvdCHName'.$index, 'class'=>'tblCredit'.$index]
            );

        }
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
}