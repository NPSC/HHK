<?php


/**
 * Description of PaymentGateway
 *
 * @author Eric
 */
abstract class PaymentGateway {

    const VANTIV = 'vantiv';
    const INSTAMED = 'instamed';

    protected $gwName;
    protected $credentials;

    /**
     *  Get the gateway information from the database.
     */
    protected abstract function loadGateway(\PDO $dbh);

    /**
     *  Interpret database info into payment gateway credentials object.
     */
    protected abstract function setCredentials($credentials);



    public static function factory(\PDO $dbh, $gwType, $gwName) {


        switch ($gwType) {

            case VANTIV:

                throw new Hk_Exception_Runtime('Vantiv is not yet implemented. ');
                break;

            case INSTAMED:

                return new InstamedGateway($dbh, $gwName);

                break;

            default:
                throw new Hk_Exception_InvalidArguement('Gateway Type "' . $gwType . '" is not implemented. ');
        }
    }

    public function getGwName() {
        return $this->gwName;
    }

    public function getCredentials() {
        return $this->credentials;
    }

    protected function setGwName($gwName) {
        $this->gwName = $gwName;
        return $this;
    }

}


/**
 * Description of InstamedGateway
 *
 * @author Eric
 */
class InstamedGateway extends PaymentGateway {

    protected $ssoUrl;

    public function __construct(\PDO $dbh, $gwName) {

        $this->setGwName($gwName);

        $this->setCredentials(loadGateway($dbh));
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
        $this->ssoUrl = $gwRs->providersSsoUrl->getStoredVal();
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

        return get_headers($this->ssoUrl, 1, $context);
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
    protected $userName;
    protected $userID;


    public function __construct(InstamedGatewayRS $gwRs) {

        $this->accountID = $gwRs->account_Id->getStoredVal();
        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->ssoAlias = $gwRs->sso_Alias->getStoredVal();
        $this->userID = $gwRs->user_Id->getStoredVal();
        $this->userName = $gwRs->user_Name->getStoredVal();

    }

    public function toNVP() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            InstaMedCredentials::SEC_KEY => decryptMessage($this->securityKey),
            InstaMedCredentials::SSO_ALIAS => $this->ssoAlias,
            InstaMedCredentials::U_ID => $this->userID,
            InstaMedCredentials::U_NAME => $this->userName,
        );
    }

}