<?php
namespace HHK\API\OAuth;

use HHK\sec\SysConfig;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use HHK\API\OAuth\Repository\ClientRepository;
use HHK\API\OAuth\Repository\ScopeRepository;
use HHK\API\OAuth\Repository\AccessTokenRepository;
use League\OAuth2\Server\ResourceServer;


class OAuthServer
{
    private ClientRepository $clientRepository;
    private ScopeRepository $scopeRepository;
    private AccessTokenRepository $accessTokenRepository;
    private AuthorizationServer $authServer;
    private ?ResourceServer $resourceServer = null;

    private string $privateKeyPath;
    private string $publicKeyPath;
    private string $encryptionKey;

    public function __construct(\PDO $dbh)
    {
        $this->clientRepository = new ClientRepository(); // instance of ClientRepositoryInterface
        $this->scopeRepository = new ScopeRepository(); // instance of ScopeRepositoryInterface
        $this->accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface

        // Path to public and private keys
        $keyPath = SysConfig::getKeyValue($dbh, "sys_config", "keyPath");
        $this->privateKeyPath = $keyPath . "/oauth/private.key";
        $this->publicKeyPath = $keyPath . "/oauth/public.key";

        // generate using base64_encode(random_bytes(32))
        $encryptionKeyPath = $keyPath . "/oauth/encryptionKey.txt";
        $encryptionKey = is_readable($encryptionKeyPath) ? file_get_contents($encryptionKeyPath) : false;
        if($encryptionKey === false || $encryptionKey === ''){
            throw new \RuntimeException("OAuth encryption key not found or unreadable at " . $encryptionKeyPath);
        }
        $this->encryptionKey = $encryptionKey;
        
        // Setup the authorization server
        $this->authServer = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            $this->privateKeyPath,
            $this->encryptionKey
        );

        // Enable the client credentials grant on the server
        $this->authServer->enableGrantType(
            new ClientCredentialsGrant(),
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );

    }

    public function getResourceServer(){
        if (!$this->resourceServer instanceof ResourceServer) {
            $this->resourceServer = new ResourceServer(
                $this->accessTokenRepository,
                $this->publicKeyPath
            );
        }
        return $this->resourceServer;
    }

    public function getAuthServer(){
        return $this->authServer;
    }

}