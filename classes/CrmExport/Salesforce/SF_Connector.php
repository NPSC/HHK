<?php
namespace HHK\CrmExport\Salesforce;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use HHK\OAuth\SalesForceOAuth;
use HHK\OAuth\Credentials;
use HHK\Exception\{RuntimeException, UploadException};
use GuzzleHttp\Exception\BadResponseException;


/**
 *
 * @author Eric
 *
 */
class SF_Connector {

    /**
     * Summary of oAuth
     * @var SalesForceOauth
     */
    protected $oAuth;

    /**
     * Summary of credentials
     * @var Credentials
     */
    protected $credentials;

    public function __construct(Credentials $credentials) {

        $this->credentials = $credentials;
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

            $response = $client->request('POST', $endpoint, [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::JSON => $params
            ]);

            $result = json_decode($response->getBody(), true);

        } catch (BadResponseException $exception) {
            $this->checkErrors($exception);
        }

        return $result;
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

            $response = $client->request('PATCH', $endpoint, [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $this->oAuth->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::JSON => $params
            ]);

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
     * Summary of checkErrors
     * @param BadResponseException $exception
     * @throws RuntimeException
     * @return never
     */
    protected function checkErrors(BadResponseException $exception) {

        $errorResponse = $exception->getResponse();
        $errorJson = json_decode($errorResponse->getBody());

        if(isset($errorJson->error_description)){
            throw new RuntimeException("Unable to postURL via OAuth: " . $errorJson->error_description);
        }elseif(is_countable($errorJson)){
            throw new RuntimeException($this->collectErrors($errorJson) . 'Requested URL: ' . $exception->getRequest()->getUri());
        }else{
            throw new RuntimeException('to PostURL via OAuth: ' . $errorResponse->getBody());
        }

    }


}

