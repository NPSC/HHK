<?php
namespace HHK\OAuth;

use GuzzleHttp\{Client, Psr7\Request, RequestOptions};
use HHK\Exception\RuntimeException;
use GuzzleHttp\Exception\BadResponseException;
use HHK\Integrations\GuzzleAPILogger;
use HHK\sec\Session;

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
     * Return the TTL (in seconds) for a token response from this provider.
     * Subclasses may use $tokenResponse->expires_in or return a constant.
     */
    abstract protected function getTokenTtl(object $tokenResponse): int;

    /**
     * Session key unique to this provider + credential set.
     */
    protected function getSessionKey(): string {
        return 'oauth_' . md5($this->credentials->getClientId() . $this->getLogServiceName());
    }

    /**
     * Remove the cached token from the session (e.g. after a 401).
     */
    public function clearCachedToken(): void {
        $uS = Session::getInstance();
        $key = $this->getSessionKey();
        unset($uS->{$key});
    }

    /**
     * Get a Bearer token from the OAuth server and save in $this->accessToken.
     * Returns immediately if a valid cached token exists in the session.
     *
     * @return bool
     */
    public function login(): bool {
        $uS = Session::getInstance();
        $key = $this->getSessionKey();

        if (isset($uS->{$key}) && $uS->{$key}['expires_at'] > time()) {
            $this->accessToken = $uS->{$key}['access_token'];
            $this->instanceURL = $uS->{$key}['instance_url'] ?? '';
            return true;
        }

        $tokenResponse = $this->requestToken();
        $valid = $this->validateTokenResponse($tokenResponse);

        if ($valid) {
            $uS->{$key} = [
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceURL ?? '',
                'expires_at'   => time() + $this->getTokenTtl($tokenResponse),
            ];
        }

        return $valid;
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