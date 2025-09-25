<?php
namespace HHK\API\Middleware;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware for validating access tokens.
 */
class ResourceServerMiddleware
{

    public function __construct(private ResourceServer $server)
    {
    }
    
    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {

        try{
            $request = $this->server->validateAuthenticatedRequest($request);
        }catch(OAuthServerException $e){
            $response = new Response();
            return $e->generateHttpResponse($response);
        }

        // Invoke the next middleware and return response
        return $handler->handle($request);
    }
}