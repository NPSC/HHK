<?php
namespace HHK\CrmExport\Salesforce;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use HHK\CrmExport\OAuth\OAuth;
use HHK\CrmExport\OAuth\Credentials;
use HHK\Exception\{RuntimeException, UploadException};
use GuzzleHttp\Exception\BadResponseException;


/**
 *
 * @author Eric
 *
 */
class SF_Connector {

    protected $oAuth;
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

        $this->oAuth = new OAuth($this->credentials);

        $this->oAuth->login();
    }

    public function logout () {
        $this->oAuth = NULL;
    }

    /**
     * Search the $endpoint using $query.  Uses HTTP::GET
     *
     * @param string $query
     * @param str $endpoint
     * @return mixed
     */
    public function search($query, $endpoint) {

        //$client = new Client(['base_uri' => $this->credentials->getBaseURI()]);

        try{
            if(!$this->oAuth instanceof OAuth){
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

    public function goUrl($endpoint) {

        //$client = new Client(['base_uri' => $this->credentials->getBaseURI()]);

        try{
            if(!$this->oAuth instanceof OAuth){
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
            if(!$this->oAuth instanceof OAuth){
                $this->login();
            }
            
            $meth = 'POST';
            
            if ($isUpdate) {
                $meth = 'PATCH';  // Use patch for updates
            }

            $client = new Client(['base_uri' => $this->oAuth->getInstanceURL()]);
            
            $response = $client->request($meth, $endpoint, [
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

    protected function collectErrors($errorJson){
        $errors = '';
        if(is_array($errorJson)){
            foreach($errorJson as $error){
                $errors .= (isset($error->errorCode) ? $error->errorCode . ': ' : '') . (isset($error->message) ? $error->message . "\n" : '');
            }
        }
        return $errors;
    }

    protected function checkErrors(BadResponseException $exception) {

        $errorResponse = $exception->getResponse();
        $errorJson = json_decode($errorResponse->getBody()->getContents());

        if(isset($errorJson->error_description)){
            throw new RuntimeException("Unable to postURL via OAuth: " . $errorJson->error_description);
        }elseif(is_countable($errorJson)){
            throw new RuntimeException($this->collectErrors($errorJson));
        }else{
            throw new RuntimeException('to PostURL via OAuth: ' . $errorResponse->getBody()->getContents());
        }

    }


}

