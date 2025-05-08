<?php
namespace HHK\sec\OAuth\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AllowedOriginMiddleware
{
    public function __construct(private \PDO $dbh, private array $allowedOrigins = [])
    {
    }
    
    public function __invoke(Request $request, RequestHandler $handler):ResponseInterface
    {
        $endpoint = $request->getUri()->getPath();

        $origin = $request->getHeaderLine('origin');

        if(!in_array($origin, $this->allowedOrigins) && !in_array($origin, $this->getEndpointAllowedOrigins($endpoint))) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error'=>'Unauthorized']));
            return $response->withStatus(403);
        }

        $response = $handler->handle($request);
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        return $response;
    }
}