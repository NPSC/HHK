<?php
namespace HHK\Integrations;

use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
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

    private const REDACTED = '**********';

    private const SENSITIVE_HEADERS = [
        'authorization',
        'x-api-key',
        'x-auth-token',
        'x-access-token',
        'x-secret-key',
        'x-api-secret',
        'cookie',
        'set-cookie',
        'proxy-authorization',
    ];

    private const SENSITIVE_BODY_KEYS = [
        'password',
        'passwd',
        'secret',
        'client_secret',
        'api_secret',
        'token',
        'access_token',
        'refresh_token',
        'auth_token',
        'id_token',
        'api_key',
        'apikey',
        'private_key',
    ];

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
            ExternalAPILog::log($dbh, $serviceName, $type, self::redactRequest($request), self::redactResponse($response), $uS->username ?? '');

            // Rewind again so the caller's body reads are unaffected
            if ($requestBody->isSeekable())  { $requestBody->rewind(); }
            if ($responseBody->isSeekable()) { $responseBody->rewind(); }
        } catch (Exception $e) {
            // never let logging break the caller
        }
    }

    private static function redactRequest(RequestInterface $request): RequestInterface {
        foreach (self::SENSITIVE_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                $request = $request->withHeader($header, self::REDACTED);
            }
        }

        $uri = $request->getUri();
        if ($uri->getQuery() !== '') {
            parse_str($uri->getQuery(), $params);
            $request = $request->withUri($uri->withQuery(http_build_query(self::redactArray($params))));
        }

        return self::redactMessageBody($request);
    }

    private static function redactResponse(ResponseInterface $response): ResponseInterface {
        foreach (self::SENSITIVE_HEADERS as $header) {
            if ($response->hasHeader($header)) {
                $response = $response->withHeader($header, self::REDACTED);
            }
        }

        return self::redactMessageBody($response);
    }

    /**
     * @template T of RequestInterface|ResponseInterface
     * @param T $message
     * @return T
     */
    private static function redactMessageBody(RequestInterface|ResponseInterface $message): RequestInterface|ResponseInterface {
        $body = $message->getBody();
        if ($body->isSeekable()) { $body->rewind(); }
        $content = $body->getContents();
        if ($body->isSeekable()) { $body->rewind(); }

        $redacted = self::redactBodyContent($content, $message->getHeaderLine('Content-Type'));

        if ($redacted !== $content) {
            $message = $message->withBody(Utils::streamFor($redacted));
        }

        return $message;
    }

    private static function redactBodyContent(string $content, string $contentType): string {
        if ($content === '') {
            return $content;
        }

        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($content, true);
            if (\is_array($data)) {
                $encoded = json_encode(self::redactArray($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return $encoded !== false ? $encoded : $content;
            }
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($content, $params);
            return http_build_query(self::redactArray($params));
        }

        return $content;
    }

    private static function redactArray(array $data): array {
        foreach ($data as $key => $value) {
            if (\in_array(strtolower((string) $key), self::SENSITIVE_BODY_KEYS, true)) {
                $data[$key] = self::REDACTED;
            } elseif (\is_array($value)) {
                $data[$key] = self::redactArray($value);
            }
        }
        return $data;
    }

}
