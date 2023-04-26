<?php
namespace HHK\CrmExport\OAuth;

use GuzzleHttp\{Client, RequestOptions};
use HHK\Exception\RuntimeException;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Handles the OAuth login and token request process
 *
 * @author wireland
 *
 */
class OAuth{

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
        return $this->validateTokenRepsonse($tokenResponse);
    }

    private function requestToken(){

        $client = new Client(['base_uri' => $this->credentials->getBaseURI()]);
        try {
            $response = $client->post($this->credentials->getTokenURI(), [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'password',
                    'client_id' => $this->credentials->getClientId(),
                    'client_secret' => $this->credentials->getClientSecret(),
                    'username' => $this->credentials->getUsername(),
                    'password' => $this->credentials->getPassword() . $this->credentials->getSecurityToken(),
                ]
            ]);

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

    private function validateTokenRepsonse($data){
        $hash = hash_hmac(
            'sha256',
            $data->id . $data->issued_at,
            $this->credentials->getClientSecret(),
            true
            );
        if (base64_encode($hash) !== $data->signature) {
            throw new RuntimeException('OAuth access token is invalid');
        }
        $this->accessToken = $data->access_token; // Valid access token
        $this->instanceURL = $data->instance_url;  //
        return true;
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
?>