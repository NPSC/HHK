<?php
namespace HHK\API\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;

/**
 * Set up CORS Policies
 */
class CorsMiddleware
{
    
    public function __construct(private App $app)
    {
    }

    public function __invoke(Request $request, RequestHandler $handler):ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->app->getResponseFactory()->createResponse();
        } else {
            $response = $handler->handle($request);
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');

        if (ob_get_contents()) {
            ob_clean();
        }

        return $response;
    }
}