<?php
namespace HHK\API\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Save request and response objects to api_access_log table.
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
            
            if($request->getMethod() !== 'OPTIONS') {
                $requestJson = json_encode(["method"=> $request->getMethod(), "uri"=> $request->getUri()->getPath(), "headers"=>$this->formatHeaders($request->getHeaders()), "params"=>$request->getQueryParams()], JSON_PRETTY_PRINT);
                
                if($responseBody = json_decode($response->getBody(), true)){
                    $responseBody = $this->formatBody($responseBody);
                }else{
                    $responseBody = $response->getBody();
                }

                $responseJson = json_encode(["headers"=>$this->formatHeaders($response->getHeaders()), "body"=>$responseBody], JSON_PRETTY_PRINT);
                $oauthClientId = $request->getAttribute("oauth_client_id", "");
                $oauthUserId = $request->getAttribute("oauth_user_id", "");
                $oauthAccessTokenId = $request->getAttribute("oauth_access_token_id", "");
                $ipAddress = (isset($request->getServerParams()["REMOTE_ADDR"]) ? $request->getServerParams()["REMOTE_ADDR"] : "");

                $stmt = $this->dbh->prepare("INSERT INTO api_access_log (requestPath, responseCode, request, response, oauth_client_id, oauth_user_id, oauth_access_token_id, ip_address) VALUES (:requestPath, :responseCode, :request, :response, :oauth_client_id, :oauth_user_id, :oauth_access_token_id, :ip_address)");
                $stmt->execute(["requestPath"=>$request->getUri()->getPath(), "responseCode"=>$response->getStatusCode(), "request"=>$requestJson, "response"=>$responseJson, "oauth_client_id"=>$oauthClientId, "oauth_user_id"=>$oauthUserId, "oauth_access_token_id"=>$oauthAccessTokenId, "ip_address"=>$ipAddress]);
            }
        }catch (\Exception $e) {

            if(!$response instanceof Response) {
                $response = $handler->handle($request);
            }
        }

        return $response;
    }

    public static function formatHeaders(array $headers): array
    {
        $formattedHeaders = [];
        foreach ($headers as $name => $values) {
            if($name == "Authorization"){
                $formattedHeaders[$name] = "************";
            }else{
                $formattedHeaders[$name] = implode(', ', $values);
            }
        }
        return $formattedHeaders;
    }

    private function formatBody(array $body): array
    {
        $formattedBody = [];
        foreach ($body as $key => $value) {
            if($key == "access_token"){
                $formattedBody[$key] = "************";
            }else{
                $formattedBody[$key] = $value;
            }
        }
        return $formattedBody;
    }
}