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

    public function __construct(Credentials $credentials){
        parent::__construct($credentials);
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

    public function validateTokenResponse($data): bool{
        if(isset($data->access_token) && $data->expires_in > 0){
            $this->accessToken = $data->access_token; // Valid access token
            return true;
        }else{
            throw new RuntimeException('OAuth access token is invalid');
        }
    }
}