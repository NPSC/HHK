<?php
namespace HHK\CrmExport\Salesforce;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
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
    protected SalesForceOAuth|null $oAuth;

    protected \PDO $dbh;
    /**
     * Summary of credentials
     * @var Credentials
     */
    protected $credentials;

    public function __construct(\PDO $dbh, Credentials $credentials) {

        $this->dbh = $dbh;
        $this->credentials = $credentials;
        $this->oAuth = NULL;
    }

    /**
     * Instantiate OAuth object and authenticate to endpoint
     *
     * Access Bearer token via $this->oAuth->getAccessToken();
     */
    public function login() {

        $this->oAuth = new SalesForceOAuth($this->credentials);

        $this->oAuth->login();
    }

    public function logout () {
        $this->oAuth = NULL;
    }

    /**
     * Search the $endpoint using $query.  Uses HTTP::GET
     *
     * @param string $query
     * @param string $endpoint
     * @return mixed
     */
    public function search($query, $endpoint) {

        try{
            if(!$this->oAuth instanceof SalesForceOAuth){
                $this->login();
            }

            $client = new Client(['base_uri' => $this->oAuth->getInstanceURL()]);

            $response = $client->request('GET', $endpoint, [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'X-PrettyPrint' => 1,
                ],
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

        try{
            if(!$this->oAuth instanceof SalesForceOauth){
                $this->login();
            }

            $client = new Client(['base_uri' => $this->oAuth->getInstanceURL()]);

            $response = $client->request('GET', $endpoint, [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'X-PrettyPrint' => 1,
                ]
            ]);

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

       try{
            if(!$this->oAuth instanceof SalesForceOAuth){
                $this->login();
            }

            $client = new Client(['base_uri' => $this->oAuth->getInstanceURL()]);

            $headers = [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'Content-Type' => 'application/json',
            ];

            $request = new Request('POST', $endpoint, $headers, json_encode($params));

            $response = $client->send($request);

            $result = json_decode($response->getBody(), true);

        } catch (BadResponseException $exception) {
            $this->checkErrors($exception);
        }

        //log transaction
        try{
            $uS = Session::getInstance();
            ExternalAPILog::log($this->dbh, "SalesForce", "", $request, $response, $uS->username);
        }catch(Exception $e){
            //do nothing
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

       try{
            if(!$this->oAuth instanceof SalesForceOAuth){
                $this->login();
            }

            $client = new Client(['base_uri' => $this->oAuth->getInstanceURL()]);
            
            $headers = [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'Content-Type' => 'application/json',
            ];

            $batchRequests = [];
            $batchResults = [];
            $batchErrors = [];

            foreach($jsonBodies as $batchId=>$params){
                $batchRequests[$batchId] = new Request('POST', $endpoint, $headers, json_encode($params));
            }

            $pool = new Pool($client, $batchRequests, [
                'concurrency' => 5,
                'fulfilled' =>function (ResponseInterface $response, $batchId) use ($batchRequests, &$batchResults){ //if the response is success
                    //log transaction
                    try{
                        $uS = Session::getInstance();
                        ExternalAPILog::log($this->dbh, "SalesForce", "", $batchRequests[$batchId], $response, $uS->username);
                    }catch(Exception $e){
                        //do nothing
                    }

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

        try {
            if (!$this->oAuth instanceof SalesForceOAuth) {
                $this->login();
            }


            $client = new Client(['base_uri' => $this->oAuth->getInstanceURL()]);

            $headers = [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'Content-Type' => 'application/json',
            ];

            $request = new Request('PATCH', $endpoint, $headers, json_encode($params));

            $response = $client->send($request);

            $result = json_decode($response->getBody(), true);
        } catch (BadResponseException $exception) {
            $this->checkErrors($exception);
        }

        //log transaction
        try{
            $uS = Session::getInstance();
            ExternalAPILog::log($this->dbh, "SalesForce", "", $request, $response, $uS->username);
        }catch(Exception $e){
            //do nothing
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

