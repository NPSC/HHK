<?php
namespace HHK\OAuth;

use GuzzleHttp\{Client, RequestOptions};
use HHK\Exception\RuntimeException;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Handles the OAuth login and token request process
 *
 * @author wireland
 *
 */
abstract class AbstractOAuth{

    protected Credentials $credentials;
    protected $accessToken;
    protected $instanceURL;

    public function __construct(Credentials $credentials){
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

    protected function sendTokenRequest(array $requestOptions){
        $client = new Client(['base_uri' => $this->credentials->getBaseURI()]);
        try {
            $response = $client->post($this->credentials->getTokenURI(), $requestOptions);

            return json_decode($response->getBody());

        } catch (BadResponseException $exception) {
            $errorResponse = $exception->getResponse();
            $errorJson = json_decode($errorResponse->getBody()->getContents());
            if(isset($errorJson->error_description)){
                throw new RuntimeException("Request Token Error: " . $errorJson->error_description);
            }else{
                throw new RuntimeException('Request Token Error: ' . $errorResponse->getBody()->getContents());
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
}