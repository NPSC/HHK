<?php
namespace HHK\OAuth;

use GuzzleHttp\{Client, Psr7\Request, RequestOptions};
use HHK\Exception\RuntimeException;
use GuzzleHttp\Exception\BadResponseException;
use HHK\Integrations\GuzzleAPILogger;

/**
 * Handles the OAuth login and token request process
 *
 * @author wireland
 *
 */
abstract class AbstractOAuth implements OAuthInterface {

    protected \PDO $dbh;
    protected Credentials $credentials;
    protected $accessToken;
    protected $instanceURL;

    public function __construct(\PDO $dbh, Credentials $credentials){
        $this->dbh = $dbh;
        $this->credentials = $credentials;
    }

    /**
     * Get a Bearer token from the OAuth server and save in $this->accessToken
     *
     * @return bool
     */
    public function login():bool{
        $tokenResponse = $this->requestToken();
        return $this->validateTokenResponse($tokenResponse);
    }

    /**
     * Send a GuzzleHttp request to the request an OAuth access token
     * 
     * @param array $requestOptions
     * @throws RuntimeException
     */
    protected function sendTokenRequest(array $requestOptions){
        $client = new Client(['base_uri' => $this->credentials->getBaseURI(), 'handler' => GuzzleAPILogger::createStack($this->dbh, $this->getLogServiceName())]);
        try {
            $response = $client->post($this->credentials->getTokenURI(), $requestOptions);

            return json_decode($response->getBody());

        } catch (BadResponseException $exception) {
            $errorResponse = $exception->getResponse();
            $errorJson = json_decode($errorResponse->getBody());
            if(isset($errorJson->error_description)){
                throw new RuntimeException("Request Token Error: " . $errorJson->error_description);
            }else{
                throw new RuntimeException('Request Token Error: ' . $errorResponse->getBody());
            }
        }

    }

    /**
     * Get current OAuth Bearer token requested via $this->login()
     *
     * @return string Access Token
     */
    public function getAccessToken(){
        return $this->accessToken;
    }
    public function getInstanceURL(){
        return $this->instanceURL;
    }

    public function getLogServiceName(): string{
        return "";
    }
}