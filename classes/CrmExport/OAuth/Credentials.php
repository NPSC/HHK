<?php
namespace HHK\CrmExport\OAuth;

/**
 * Fill in OAuth credentials to be passed to OAuth object
 *
 * @author wireland
 *
 */
class Credentials {

    protected $baseURI;
    protected $tokenURI;
    protected $clientId;
    protected $clientSecret;
    protected $securityToken;

    protected $username;
    protected $password;


    public function setBaseURI(string $baseURI){
        $this->baseURI = $baseURI;
    }

    public function getBaseURI(){
        return $this->baseURI;
    }

    public function setTokenURI(string $tokenURI){
        $this->tokenURI = $tokenURI;
    }

    public function getTokenURI(){
        return $this->tokenURI;
    }

    public function setClientId(string $clientId){
        $this->clientId = $clientId;
    }

    public function getClientId(){
        return $this->clientId;
    }

    public function setClientSecret(string $clientSecret){
        $this->clientSecret = $clientSecret;
    }

    public function getClientSecret(){
        return $this->clientSecret;
    }

    public function setSecurityToken(string $securityToken){
        $this->securityToken = $securityToken;
    }

    public function getSecurityToken(){
        return $this->securityToken;
    }

    public function setUsername(string $username){
        $this->username = $username;
    }

    public function getUsername(){
        return $this->username;
    }

    public function setPassword(string $password){
        $this->password = $password;
    }

    public function getPassword(){
        return $this->password;
    }
}
?>