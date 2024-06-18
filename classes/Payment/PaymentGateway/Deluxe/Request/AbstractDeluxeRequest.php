<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;
use GuzzleHttp\Client;
use HHK\OAuth\Credentials;
use HHK\OAuth\OAuth;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

abstract class AbstractDeluxeRequest
{

    protected OAuth $oAuth;

    protected string $baseApiUrl;

    protected Client $GuzzleClient;

    public function __construct(\PDO $dbh, DeluxeGateway $gway)
    {
        $this->oAuthSetup($gway);
        $this->baseApiUrl = (isset($gway->getCredentials()["Checkout_Url"]) ? $gway->getCredentials()["Checkout_Url"] : "");
        $this->GuzzleClient = new Client(['base_uri' => $this->baseApiUrl, 'headers' => ['Authorization' => 'Bearer ' . $this->oAuth->getAccessToken()]]);
    }

    protected function oAuthSetup(DeluxeGateway $gway)
    {
        $creds = new Credentials();

        $gwayCreds = $gway->getCredentials();

        $creds->setBaseURI($gwayCreds["oAuthURL"]);
        $creds->setTokenURI("token");
        $creds->setClientId($gwayCreds["oAuthClientId"]);
        $creds->setClientSecret($gwayCreds["oAuthSecret"]);

        $oAuth = new OAuth($creds);
        $oAuth->login();
        return $oAuth;
    }


}