<?php
namespace HHK\Integrations;

use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use HHK\sec\Session;
use HHK\TableLog\ExternalAPILog;
use PDO;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Reusable Guzzle logging middleware that records every request/response pair
 * via ExternalAPILog. Use createStack() when building a GuzzleHttp\Client or
 * middleware() to push onto an existing HandlerStack.
 *
 * @author Will Ireland <wireland@nonprofitsoftwarecorp.org>
 */
class GuzzleAPILogger {

    /**
     * Create a HandlerStack with the logging middleware pre-applied.
     *
     * @param PDO    $dbh         Database connection
     * @param string $serviceName Value stored in Log_Type (e.g. "NeonCRM")
     */
    public static function createStack(PDO $dbh, string $serviceName): HandlerStack {
        $stack = HandlerStack::create();
        $stack->push(self::middleware($dbh, $serviceName));
        return $stack;
    }

    /**
     * Return a Guzzle middleware callable that logs every request/response pair.
     * Both success responses and BadResponseException error responses are logged.
     * Stream bodies are rewound before and after reading so the caller is unaffected.
     *
     * @param PDO    $dbh         Database connection
     * @param string $serviceName Value stored in Log_Type
     */
    public static function middleware(PDO $dbh, string $serviceName): callable {
        return function (callable $handler) use ($dbh, $serviceName): callable {
            return function (RequestInterface $request, array $options) use ($handler, $dbh, $serviceName) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $dbh, $serviceName): ResponseInterface {
                        self::writeLog($dbh, $serviceName, $request, $response);
                        return $response;
                    },
                    function ($reason) use ($request, $dbh, $serviceName) {
                        if ($reason instanceof BadResponseException) {
                            self::writeLog($dbh, $serviceName, $request, $reason->getResponse());
                        }
                        return \GuzzleHttp\Promise\Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    /**
     * Write a single log entry, rewinding stream bodies before and after so
     * the caller can still read the response after logging.
     */
    private static function writeLog(PDO $dbh, string $serviceName, RequestInterface $request, ResponseInterface $response): void {
        try {
            $requestBody  = $request->getBody();
            $responseBody = $response->getBody();

            if ($requestBody->isSeekable())  { $requestBody->rewind(); }
            if ($responseBody->isSeekable()) { $responseBody->rewind(); }

            $uS   = Session::getInstance();
            $type = ltrim($request->getUri()->getPath(), '/');
            ExternalAPILog::log($dbh, $serviceName, $type, $request, $response, $uS->username ?? '');

            // Rewind again so the caller's body reads are unaffected
            if ($requestBody->isSeekable())  { $requestBody->rewind(); }
            if ($responseBody->isSeekable()) { $responseBody->rewind(); }
        } catch (Exception $e) {
            // never let logging break the caller
        }
    }

}
