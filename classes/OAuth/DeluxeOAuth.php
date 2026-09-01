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
class DeluxeOAuth extends AbstractOAuth{

    public function __construct(\PDO $dbh, Credentials $credentials){
        parent::__construct($dbh, $credentials);
    }

    public function requestToken(){

        //build the request specific to Deluxe
        $requestOptions = [
            RequestOptions::AUTH => [$this->credentials->getClientId(), $this->credentials->getClientSecret()],
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'client_credentials'
            ]
        ];

        return $this->sendTokenRequest($requestOptions);
    }

    protected function getExpiresAt(object $tokenResponse): int {
        //tokenExpiry_time is a UTC timestamp, e.g. "2026-09-01T18:12:37Z"
        $expiresAt = new \DateTimeImmutable($tokenResponse->tokenExpiry_time, new \DateTimeZone('UTC'));
        return max(time() + 60, $expiresAt->getTimestamp() - 60);
    }

    public function validateTokenResponse($data): bool{
        if(isset($data->access_token) && isset($data->tokenExpiry_time)){
            $this->accessToken = $data->access_token; // Valid access token
            return true;
        }else{
            throw new RuntimeException('OAuth access token is invalid');
        }
    }

    public function getLogServiceName(): string{
        return "Deluxe";
    }
}