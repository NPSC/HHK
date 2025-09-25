<?php
namespace HHK\API\Controllers\Oauth;

use DI\Container;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for accessing calendar events
 */
class RequestTokenController
{

    private Container $container;

   public function __construct(Container $container)
   {
       $this->container = $container;
   }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $oAuthServer = $this->container->get("oAuthServer");

        try{
            return $oAuthServer->getAuthServer()->respondToAccessTokenRequest($request, $response);
        }catch (OAuthServerException $e) {
            return $e->generateHttpResponse($response);
        }
    }
}