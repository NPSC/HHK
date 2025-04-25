<?php
namespace HHK\sec\OAuth\Middleware;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

class ResourceServerMiddleware
{

    public function __construct(private ResourceServer $server, private Response $response)
    {
    }
    
    public function __invoke(Request $request, RequestHandler $handler): Response
    {

        try{
            $request = $this->server->validateAuthenticatedRequest($request);
        }catch(OAuthServerException $e){
            return $e->generateHttpResponse($this->response);
        }

        // Invoke the next middleware and return response
        return $handler->handle($request);
    }
}