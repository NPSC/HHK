<?php

namespace HHK\Payment\PaymentGateway\Vantiv;

use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\SysConst\MpStatusValues;
use HHK\SysConst\PaymentMethod;
use HHK\HTMLControls\HTMLTable;

/**
 * TokenTX.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 * Description of TokenTX
 *
 * @author Eric
 */
 
class TokenResponse extends AbstractCreditResponse {
    
    
    function __construct($creditTokenResponse, $idPayor, $idRegistration, $idToken, $paymentStatusCode = '') {
        
        $this->response = $creditTokenResponse;
        $this->idPayor = $idPayor;
        $this->setIdToken($idToken);
        $this->idRegistration = $idRegistration;
        $this->invoiceNumber = $creditTokenResponse->getInvoiceNumber();
        $this->amount = $creditTokenResponse->getAuthorizedAmount();
        $this->setPaymentStatusCode($paymentStatusCode);
        
    }
    
    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }
    
    public function getStatus() {
        
        switch ($this->response->getStatus()) {
            
            case MpStatusValues::Approved:
                $pr = AbstractCreditPayments::STATUS_APPROVED;
                break;
                
            case MpStatusValues::Declined:
                $pr = AbstractCreditPayments::STATUS_DECLINED;
                break;
                
            default:
                $pr = AbstractCreditPayments::STATUS_DECLINED;
        }
        
        return $pr;
    }
    
    public function receiptMarkup(\PDO $dbh, &$tbl) {
        
        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->response->getCardType() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxx...". $this->response->getMaskedAccount()));
        
        if ($this->response->getCardHolderName() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->response->getCardHolderName()));
        }
        
        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode() . ' ('.ucfirst($this->response->getMerchant()). ')', array('style'=>'font-size:.8em;')));
        }
        
        if ($this->response->getResponseMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getResponseMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }
        
        
        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));
        
    }
    
}
?>