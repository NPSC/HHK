<?php
namespace HHK\sec\OAuth;

use HHK\sec\SysConfig;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HHK\sec\OAuth\Repository\ClientRepository;
use HHK\sec\OAuth\Repository\ScopeRepository;
use HHK\sec\OAuth\Repository\AccessTokenRepository;


class OAuthServer
{
    private ClientRepository $clientRepository;
    private ScopeRepository $scopeRepository;
    private AccessTokenRepository $accessTokenRepository;

    /**
     * @var AuthorizationServer
     */
    private AuthorizationServer $server;

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

        $this->encryptionKey = 'L7/AxZDnKHKX5yWWJBAEs0ZE5TVydMxbbt6gFxMeIDk='; // generate using base64_encode(random_bytes(32))

        // Setup the authorization server
        $this->server = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            $this->privateKeyPath,
            $this->encryptionKey
        );

        // Enable the client credentials grant on the server
        $this->server->enableGrantType(
            new ClientCredentialsGrant(),
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );

    }


    /**
     * Summary of requestAccessToken
     * @param \PDO $dbh
     * @param Request $request
     * @param Response $response
     * @return Response $response
     */
    public function requestAccessToken(\PDO $dbh, $request, $response){
        // Handle the request and generate a response
        $response = $this->server->respondToAccessTokenRequest($request, $response);
        return $response;
    }

}