<?php
namespace HHK\API\Handlers;

use HHK\API\Middleware\LogMiddleware;
use Psr\Container\ContainerInterface;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Interfaces\CallableResolverInterface;

class ErrorHandler extends SlimErrorHandler
{

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container, CallableResolverInterface $callableResolver, ResponseFactoryInterface $responseFactory, ?LoggerInterface $logger = null)
    {
        $this->container = $container;
        parent::__construct($callableResolver, $responseFactory, $logger);
    }

    protected function logError(string $error):void
    {
        try {
            $dbh = $this->container->get("dbh");
            $request = $this->request;
            $requestJson = json_encode(["method"=> $request->getMethod(), "uri"=> $request->getUri()->getPath(), "headers"=>LogMiddleware::formatHeaders($request->getHeaders()), "params"=>$request->getQueryParams()], JSON_PRETTY_PRINT);

            if($this->statusCode == 404){
                $errorJson = json_encode(["error"=>$this->exception->getMessage()], JSON_PRETTY_PRINT);
            }else{
                $errorJson = json_encode(["error"=>$this->exception->getMessage(), "trace"=>$this->exception->getTrace()], JSON_PRETTY_PRINT);
            }
            
            $oauthClientId = $request->getAttribute("oauth_client_id", "");
            $oauthUserId = $request->getAttribute("oauth_user_id", "");
            $oauthAccessTokenId = $request->getAttribute("oauth_access_token_id", "");
            $ipAddress = (isset($request->getServerParams()["REMOTE_ADDR"]) ? $request->getServerParams()["REMOTE_ADDR"] : "");

            $stmt = $dbh->prepare("INSERT INTO api_access_log (requestPath, responseCode, request, response, oauth_client_id, oauth_user_id, oauth_access_token_id, ip_address) VALUES (:requestPath, :responseCode, :request, :response, :oauth_client_id, :oauth_user_id, :oauth_access_token_id, :ip_address)");
            $stmt->execute(["requestPath"=>$request->getUri()->getPath(), "responseCode"=>$this->statusCode, "request"=>$requestJson, "response"=>$errorJson, "oauth_client_id"=>$oauthClientId, "oauth_user_id"=>$oauthUserId, "oauth_access_token_id"=>$oauthAccessTokenId, "ip_address"=>$ipAddress]);
        }catch (\Exception $e) {

        }
    }

}