<?php

namespace HHK\Payment\PaymentGateway\Instamed;

use HHK\Crypto;
use HHK\Tables\PaymentGW\InstamedGatewayRS;

/**
 * InstamedCredentials.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class InstamedCredentials {
    
    // NVP names
    const SEC_KEY = 'securityKey';
    const ACCT_ID = 'accountID';
    const ID = 'id';
    const SSO_ALIAS = 'ssoAlias';
    const MERCHANT_ID = 'merchantId';
    const STORE_ID = 'storeId';
    const TERMINAL_ID = 'terminalID';
    const WORKSTATION_ID = 'additionalInfo6';
    const U_NAME = 'userName';
    const U_ID = 'userID';
    
    public $merchantId;
    public $storeId;
    public $password;
    public $id;
    
    protected $securityKey;
    protected $accountID;
    protected $terminalId;
    protected $workstationId;
    protected $ssoAlias;
    
    
    public function __construct(InstamedGatewayRS $gwRs) {
        
        $this->accountID = $gwRs->account_Id->getStoredVal();
        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->ssoAlias = $gwRs->sso_Alias->getStoredVal();
        $this->merchantId = $gwRs->merchant_Id->getStoredVal();
        $this->storeId = $gwRs->store_Id->getStoredVal();
        $this->terminalId = $gwRs->terminal_Id->getStoredVal();
        $this->workstationId = $gwRs->WorkStation_Id->getStoredVal();
        $this->password = Crypto::decryptMessage($gwRs->password->getStoredVal());
        
        $parts = explode('@', $this->accountID);
        $this->id = $parts[0];
    }
    
    public function toSSO() {
        
        return array(
            InstamedCredentials::ACCT_ID => $this->accountID,
            InstamedCredentials::SEC_KEY => Crypto::decryptMessage($this->securityKey),
            InstamedCredentials::SSO_ALIAS => $this->ssoAlias,
            InstamedCredentials::ID => $this->id,
            InstamedCredentials::WORKSTATION_ID => $this->workstationId,
        );
    }
    
    public function toCurl($useWorkstationId = TRUE) {
        
        return
        InstamedCredentials::MERCHANT_ID . '=' . $this->merchantId
        . '&' . InstamedCredentials::STORE_ID . '=' . $this->storeId
        . '&' . InstamedCredentials::TERMINAL_ID . '=' . $this->terminalId
        . ($useWorkstationId ? '&' . InstamedCredentials::WORKSTATION_ID . '=' . $this->workstationId : '');
    }
    
    public function toSOAP() {
        
        return array(
            InstamedCredentials::ACCT_ID => $this->accountID,
            'password' => Crypto::decryptMessage($this->securityKey),
            'alias' => $this->ssoAlias,
        );
    }
    
}
?>