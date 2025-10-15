<?php
namespace HHK\OAuth;

use GuzzleHttp\{Client, RequestOptions};
use HHK\Exception\RuntimeException;


/**
 * Handles the OAuth login and token request process
 *
 * @author wireland
 *
 */
class SalesForceOAuth extends AbstractOAuth{

    public function __construct(Credentials $credentials){
        parent::__construct($credentials);
    }

    public function requestToken(){

        $requestOptions = [
            RequestOptions::AUTH => [$this->credentials->getClientId(), $this->credentials->getClientSecret()],
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'password',
                'client_id'=> $this->credentials->getClientId(),
                'client_secret' => $this->credentials->getClientSecret(),
                'username' => $this->credentials->getUsername(),
                'password' => $this->credentials->getPassword() . $this->credentials->getSecurityToken(),
            ]
        ];

        return $this->sendTokenRequest($requestOptions);
    }

    public function validateTokenResponse($data): bool{
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
}