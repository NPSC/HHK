<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;
use GuzzleHttp\Client;
use HHK\OAuth\Credentials;
use HHK\OAuth\DeluxeOAuth;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

abstract class AbstractDeluxeRequest
{

    protected DeluxeOAuth $oAuth;

    protected string $baseApiUrl;

    protected Client $GuzzleClient;

    protected string $hpfAccessToken;

    public function __construct(\PDO $dbh, DeluxeGateway $gway)
    {
        $this->oAuth = $this->oAuthSetup($gway);
        $this->hpfAccessToken = (isset($gway->getCredentials()["hpfAccessToken"]) ? $gway->getCredentials()["hpfAccessToken"] : "");
        $this->baseApiUrl = (isset($gway->getCredentials()["Checkout_Url"]) ? $gway->getCredentials()["Checkout_Url"] : "");
        $this->GuzzleClient = new Client([
            'base_uri' => $this->baseApiUrl, 
            'headers' => [
                'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                'PartnerToken' => $this->hpfAccessToken,
                'content-type' =>'application/json'
            ]
        ]);
    }

    /**
     * Set up oAuth object, and authenticate
     * @param \HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway $gway
     * @return DeluxeOAuth
     */
    protected function oAuthSetup(DeluxeGateway $gway)
    {
        $creds = new Credentials();

        $gwayCreds = $gway->getCredentials();

        $creds->setBaseURI($gwayCreds["oAuthURL"]);
        $creds->setTokenURI("token");
        $creds->setClientId($gwayCreds["oAuthClientId"]);
        $creds->setClientSecret($gwayCreds["oAuthSecret"]);

        $oAuth = new DeluxeOAuth($creds);
        $oAuth->login();
        return $oAuth;
    }


}