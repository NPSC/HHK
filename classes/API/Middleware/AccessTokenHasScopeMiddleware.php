<?php
namespace HHK\API\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Restrict API endpoint to a specific scope
 */
class AccessTokenHasScopeMiddleware
{
    public function __construct(private string $scope)
    {
    }
    
    public function __invoke(Request $request, RequestHandler $handler):ResponseInterface
    {

        $requestScopes = $request->getAttribute('oauth_scopes');

        if(is_array($requestScopes) && in_array($this->scope, $requestScopes)){
            // has scope
            return $handler->handle($request);
        }else{
            $response = new Response();
            $response->getBody()->write(json_encode(['error'=>'Unauthorized', 'description'=>"Access token does not contain the required scope"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }
}