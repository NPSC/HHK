<?php

namespace HHK\OAuth;

interface OAuthInterface {

    /**
     * Set up GuzzleHttp request options for requesting an OAuth token
     * Should return the response from $this->sendTokenRequest
     * 
     * @return mixed
     */
    public function requestToken();

    /**
     * Validate the token response and set $this->accessToken and return true
     * 
     * @param object $data object returned from json_decode()
     * @return bool
     * @throws \RuntimeException
     */
    public function validateTokenResponse(object $data):bool;
}