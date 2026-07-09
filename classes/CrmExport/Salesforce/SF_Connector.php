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
use HHK\Exception\{RuntimeException, UploadException};
use GuzzleHttp\Exception\BadResponseException;
use HHK\sec\Session;
use HHK\TableLog\ExternalAPILog;
use HHK\TableLog\HouseLog;
use Psr\Http\Message\ResponseInterface;


/**
 *
 * @author Eric
 *
 */
class SF_Connector {

    /**
     * Summary of oAuth
     * @var SalesForceOauth|null
     */
    protected SalesForceOAuth $oAuth;

    protected \PDO $dbh;
    /**
     * Summary of credentials
     * @var Credentials
     */
    protected $credentials;

    protected ?Client $client = null;

    /**
     * The number of concurrent async requests to send at one time
     * @const CONCURRENT_REQUESTS
     */
    protected const CONCURRENT_REQUESTS = 5;

    public function __construct(\PDO $dbh, Credentials $credentials) {

        $this->dbh = $dbh;
        $this->credentials = $credentials;
        $this->oAuth = new SalesForceOAuth($this->dbh, $credentials);
    }

    /**
     * Logs in and builds the Guzzle client on first use only, so constructing
     * an SF_Connector does not by itself request an OAuth token.
     *
     * @return Client
     */
    protected function getClient(): Client {

        if ($this->client === null) {

            $this->oAuth->login();

            $this->client = new Client([
                'base_uri' => $this->credentials->getBaseURI(),
                'handler' => GuzzleAPILogger::createStack($this->dbh, SalesforceManager::LOG_SERVICE_NAME),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'Content-Type' => 'application/json',
                ]
            ]);
        }

        return $this->client;
    }

    /**
     * Search the $endpoint using $query.  Uses HTTP::GET
     *
     * @param string $query
     * @param string $endpoint
     * @return mixed
     */
    public function search($query, $endpoint) {

        $result = null;
        try{

            $response = $this->getClient()->request('GET', $endpoint, [
                RequestOptions::QUERY => [
                    'q' => $query
                ]
            ]);

            $result = json_decode($response->getBody(), true);

        } catch (BadResponseException $exception) {
            $this->checkErrors($exception);
        }

        return $result;
    }

    /**
     * Summary of goUrl
     * @param mixed $endpoint
     * @return mixed
     */
    public function goUrl($endpoint) {

        $result = null;
        try{

            $response = $this->getClient()->request('GET', $endpoint);

            $result = json_decode($response->getBody(), true);

        } catch (BadResponseException $exception) {
            $this->checkErrors($exception);

        }

        return $result;
    }

    /**
     * Send a POST to endpoint using formParams as JSON in body
     *
     * @param string $endpoint
     * @param array $params
     * @return mixed
     */
    public function postUrl($endpoint, array $params, $isUpdate = FALSE) {

        $result = null;
       try{

            $request = new Request('POST', $endpoint, [], json_encode($params));

            $response = $this->getClient()->send($request);

            $result = json_decode($response->getBody(), true);

        } catch (BadResponseException $exception) {
            $this->checkErrors($exception);
        }

        return $result;
    }

    /**
     * Send multiple async POST requests to endpoint using formParams as JSON in body
     *
     * @param string $endpoint
     * @param array $jsonBodies An array of request bodies to be sent asyncronously
     * @return array An array of batchRequests and batchResults
     */
    public function postUrlAsync($endpoint, array $jsonBodies, $isUpdate = FALSE) {

        $result = null;
        try{

            $batchRequests = [];
            $batchResults = [];

            foreach($jsonBodies as $batchId=>$params){
                $batchRequests[$batchId] = new Request('POST', $endpoint, [], json_encode($params));
            }

            $pool = new Pool($this->getClient(), $batchRequests, [
                'concurrency' => self::CONCURRENT_REQUESTS,
                'fulfilled' =>function (ResponseInterface $response, $batchId) use (&$batchResults){ //if the response is success
                    $batchResults[$batchId] = ['success'=>json_decode($response->getBody(), true)];
                },
                'rejected' => function (BadResponseException $exception, $batchId) use (&$batchResults) { //if the response is not success
                    try{
                        $this->checkErrors($exception);
                    }catch(Exception $e){
                        $batchResults[$batchId] = ['error'=>$e->getMessage()];
                    }
                }
            ]);

            // Initiate the transfers and create a promise
            $promise = $pool->promise();

            // Wait for the pool of requests to complete.
            $promise->wait();


        } catch (Exception $exception) {
            throw new RuntimeException($exception->getMessage());
        }

        return ['batchRequests'=>$batchRequests, 'batchResults'=>$batchResults];
    }

    /**
     * Send a PATCH to endpoint with attachment
     *
     * @param string $endpoint
     * @param array $params
     * @return mixed
     */
    public function patchUrl($endpoint, array $params)
    {

        $result = null;
        try {

            $request = new Request('PATCH', $endpoint, [], json_encode($params));

            $response = $this->getClient()->send($request);

            $result = json_decode($response->getBody(), true);
        } catch (BadResponseException $exception) {
            $this->checkErrors($exception);
        }

        return $result;
    }


    /**
     * Summary of collectErrors
     * @param mixed $errorJson
     * @return string
     */
    protected function collectErrors($errorJson){
        $errors = '';
        if(is_array($errorJson)){
            foreach($errorJson as $error){
                $errors .= (isset($error->errorCode) ? $error->errorCode . ': ' : '') . (isset($error->message) ? $error->message . "\n" : '');
            }
        }
        return $errors;
    }

    /**
     * Log and handle Salesforce Response errrors
     * @param BadResponseException $exception
     * @throws RuntimeException
     */
    protected function checkErrors(BadResponseException $exception) {

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

