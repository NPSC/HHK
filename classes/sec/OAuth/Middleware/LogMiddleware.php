<?php
namespace HHK\sec\OAuth\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Save request and response objects to api_log table.
 */
class LogMiddleware
{

    public function __construct(private \PDO $dbh)
    {

    }
    
    public function __invoke(Request $request, RequestHandler $handler):ResponseInterface
    {

        try {
            $response = $handler->handle($request);
            
            $requestJson = json_encode(["method"=> $request->getMethod(), "uri"=> $request->getUri()->getPath(), "headers"=>$this->formatHeaders($request->getHeaders()), "params"=>$request->getQueryParams()], JSON_PRETTY_PRINT);
            $responseJson = json_encode(["headers"=>$this->formatHeaders($response->getHeaders()), "body"=>json_decode($response->getBody())], JSON_PRETTY_PRINT);

            $stmt = $this->dbh->prepare("INSERT INTO api_log (requestPath, responseCode, request, response) VALUES (:requestPath, :responseCode, :request, :response)");
            $stmt->execute(["requestPath"=>$request->getUri()->getPath(), "responseCode"=>$response->getStatusCode(), "request"=>$requestJson, "response"=>$responseJson]);

        }catch (\Exception $e) {

            if(!$response instanceof Response) {
                $response = $handler->handle($request);
            }
        }

        return $response;
    }

    private function formatHeaders(array $headers): array
    {
        $formattedHeaders = [];
        foreach ($headers as $name => $values) {
            $formattedHeaders[$name] = implode(', ', $values);
        }
        return $formattedHeaders;
    }
}