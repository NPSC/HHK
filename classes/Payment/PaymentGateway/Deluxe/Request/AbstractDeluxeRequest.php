<?php
namespace HHK\Payment\PaymentGateway\Deluxe\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use HHK\Integrations\GuzzleAPILogger;
use HHK\OAuth\Credentials;
use HHK\OAuth\DeluxeOAuth;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractDeluxeRequest
{

    protected DeluxeOAuth $oAuth;

    protected string $merchant;

    protected string $baseApiUrl;

    protected Client $GuzzleClient;

    protected string $hpfAccessToken;

    protected string $responseCode;

    protected string $responseMsg;

    protected array $responseBody;

    protected \PDO $dbh;

    public function __construct(\PDO $dbh, DeluxeGateway $gway)
    {
        $this->dbh = $dbh;
        $this->oAuth = $this->oAuthSetup($gway);
        $this->merchant = $gway->getMerchant();
        $this->hpfAccessToken = (isset($gway->getCredentials()["hpfAccessToken"]) ? $gway->getCredentials()["hpfAccessToken"] : "");
        $this->baseApiUrl = (isset($gway->getCredentials()["Checkout_Url"]) ? $gway->getCredentials()["Checkout_Url"] : "");
        $this->buildClient();
        $this->responseMsg = "";
    }

    /**
     * (Re)build the Guzzle client using the OAuth object's current access token.
     */
    protected function buildClient(): void
    {
        $this->GuzzleClient = new Client([
            'base_uri' => $this->baseApiUrl,
            'handler' => GuzzleAPILogger::createStack($this->dbh, "Deluxe"),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                'PartnerToken' => $this->hpfAccessToken,
                'content-type' =>'application/json'
            ]
        ]);
    }

    /**
     * Drop the cached OAuth token, re-login, and rebuild the client with the new token.
     */
    protected function reauthorize(): void
    {
        $this->oAuth->clearCachedToken();
        $this->oAuth->login();
        $this->buildClient();
    }

    /**
     * POST to $endpoint via $this->GuzzleClient. On a 401 (e.g. the cached token was
     * revoked out-of-band), reauthorizes and retries once; any other error, or a repeat
     * failure after reauthorizing, is rethrown for the caller's existing error handling.
     *
     * @throws BadResponseException
     */
    protected function post(string $endpoint, array $options): ResponseInterface
    {
        try {
            return $this->GuzzleClient->post($endpoint, $options);
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() !== 401) {
                throw $e;
            }
        }

        $this->reauthorize();
        return $this->GuzzleClient->post($endpoint, $options);
    }

    /**
     * Set up oAuth object, and authenticate
     * @param DeluxeGateway $gway
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

        $oAuth = new DeluxeOAuth($this->dbh, $creds);
        $oAuth->login();
        return $oAuth;
    }

    public function getResponseCode(){
        return $this->responseCode;
    }

    public function getResponseBody(){
        return $this->responseBody;
    }

}