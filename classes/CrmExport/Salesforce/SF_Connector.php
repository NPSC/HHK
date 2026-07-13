<?php
namespace HHK\CrmExport\Salesforce;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use HHK\Integrations\GuzzleAPILogger;
use HHK\OAuth\SalesForceOAuth;
use HHK\OAuth\Credentials;
use HHK\Exception\RuntimeException;
use GuzzleHttp\Exception\BadResponseException;
use HHK\sec\Session;
use HHK\TableLog\ExternalAPILog;
use Psr\Http\Message\ResponseInterface;


/**
 *
 * @author Eric
 *
 */
class SF_Connector {

    protected SalesForceOAuth $oAuth;
    protected \PDO $dbh;
    protected Credentials $credentials;
    protected ?Client $client = null;

    /**
     * The number of concurrent async requests to send at one time
     * @const CONCURRENT_REQUESTS
     */
    protected const int CONCURRENT_REQUESTS = 5;

    public function __construct(\PDO $dbh, Credentials $credentials) {

        $this->dbh = $dbh;
        $this->credentials = $credentials;
        $this->oAuth = new SalesForceOAuth($this->dbh, $credentials);
        $this->oAuth->login();
        $this->buildClient();
    }

    protected function buildClient(): void {
        $this->client = new Client([
            'base_uri' => $this->credentials->getBaseURI(),
            'handler' => GuzzleAPILogger::createStack($this->dbh, SalesforceManager::LOG_SERVICE_NAME),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    protected function reauthorize(): void {
        $this->oAuth->clearCachedToken();
        $this->oAuth->login();
        $this->buildClient();
    }

    /**
     * Runs $fn(). On a 401 reauthorizes and retries once; all other errors go through checkErrors().
     */
    protected function withReauth(callable $fn): mixed {
        try {
            return $fn();
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() !== 401) {
                $this->checkErrors($e);
                return null;
            }
        }

        $this->reauthorize();

        try {
            return $fn();
        } catch (BadResponseException $e) {
            $this->checkErrors($e);
            return null;
        }
    }

    /**
     * Search the $endpoint using $query.  Uses HTTP::GET
     *
     * @param string $query
     * @param string $endpoint
     * @return mixed
     */
    public function search(string $query, string $endpoint) {
        return $this->withReauth(function () use ($query, $endpoint) {
            $response = $this->client->request('GET', $endpoint, [
                RequestOptions::QUERY => ['q' => $query]
            ]);
            return json_decode($response->getBody(), true);
        });
    }

    /**
     * Summary of goUrl
     * @param string $endpoint
     * @return mixed
     */
    public function goUrl(string $endpoint) {
        return $this->withReauth(function () use ($endpoint) {
            $response = $this->client->request('GET', $endpoint);
            return json_decode($response->getBody(), true);
        });
    }

    /**
     * Send a POST to endpoint using formParams as JSON in body
     *
     * @param string $endpoint
     * @param array $params
     * @return mixed
     */
    public function postUrl(string $endpoint, array $params, bool $isUpdate = FALSE) {
        return $this->withReauth(function () use ($endpoint, $params) {
            $request = new Request('POST', $endpoint, [], json_encode($params));
            $response = $this->client->send($request);
            return json_decode($response->getBody(), true);
        });
    }

    /**
     * Send multiple async POST requests to endpoint using formParams as JSON in body
     *
     * @param string $endpoint
     * @param array $jsonBodies An array of request bodies to be sent asyncronously
     * @return array An array of batchRequests and batchResults
     */
    public function postUrlAsync(string $endpoint, array $jsonBodies, bool $isUpdate = FALSE) {

        try {
            $batchRequests = [];
            foreach ($jsonBodies as $batchId => $params) {
                $batchRequests[$batchId] = new Request('POST', $endpoint, [], json_encode($params));
            }
            return $this->runPool($batchRequests);
        } catch (Exception $exception) {
            throw new RuntimeException($exception->getMessage());
        }
    }

    /**
     * Executes a Guzzle Pool against $this->client.
     * On a 401, reauthorizes and retries the full pool once ($allowReauth prevents loops).
     */
    private function runPool(array $batchRequests, bool $allowReauth = true): array {
        $batchResults = [];
        $needsReauth = false;

        $pool = new Pool($this->client, $batchRequests, [
            'concurrency' => self::CONCURRENT_REQUESTS,
            'fulfilled' => function (ResponseInterface $response, $batchId) use (&$batchResults) {
                $batchResults[$batchId] = ['success' => json_decode($response->getBody(), true)];
            },
            'rejected' => function (BadResponseException $exception, $batchId) use (&$batchResults, &$needsReauth, $allowReauth) {
                if ($allowReauth && $exception->getResponse()->getStatusCode() === 401) {
                    $needsReauth = true;
                    return;
                }
                try {
                    $this->checkErrors($exception);
                } catch (Exception $e) {
                    $batchResults[$batchId] = ['error' => $e->getMessage()];
                }
            }
        ]);

        $pool->promise()->wait();

        if ($needsReauth) {
            $this->reauthorize();
            return $this->runPool($batchRequests, false);
        }

        return ['batchRequests' => $batchRequests, 'batchResults' => $batchResults];
    }

    /**
     * Send a PATCH to endpoint with attachment
     *
     * @param string $endpoint
     * @param array $params
     * @return mixed
     */
    public function patchUrl(string $endpoint, array $params) {
        return $this->withReauth(function () use ($endpoint, $params) {
            $request = new Request('PATCH', $endpoint, [], json_encode($params));
            $response = $this->client->send($request);
            return json_decode($response->getBody(), true);
        });
    }


    /**
     * Summary of collectErrors
     * @param mixed $errorJson
     * @return string
     */
    protected function collectErrors($errorJson): string {
        $errors = '';
        if(is_array($errorJson)){
            foreach($errorJson as $error){
                $errors .= (isset($error->errorCode) ? $error->errorCode . ': ' : '') . (isset($error->message) ? $error->message . "\n" : '');
            }
        }
        return $errors;
    }

    /**
     * Log and handle Salesforce Response errors
     * @param BadResponseException $exception
     * @throws RuntimeException
     */
    protected function checkErrors(BadResponseException $exception): void {

        $uS = Session::getInstance();
        $errorResponse = $exception->getResponse();
        $request = $exception->getRequest();
        $errorJson = json_decode($errorResponse->getBody());

        try{
            ExternalAPILog::log($this->dbh, "SalesForce", "error", $request, $errorResponse, $uS->username);
        }catch(Exception $e){
            //do nothing
        }

        if(isset($errorJson->error_description)){
            throw new RuntimeException("Unable to postURL via OAuth: " . $errorJson->error_description);
        }elseif(is_countable($errorJson)){
            throw new RuntimeException($this->collectErrors($errorJson));
        }else{
            throw new RuntimeException('Unable to postURL to ' . $request->getUri() . ': Error ' . $errorResponse->getStatusCode() . ": " . $errorResponse->getReasonPhrase());
        }

    }

}
