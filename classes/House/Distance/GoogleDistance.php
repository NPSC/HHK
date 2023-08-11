<?php
namespace HHK\House\Distance;

use GuzzleHttp\Client;
use HHK\Exception\RuntimeException;
use HHK\sec\Session;
use HHK\TableLog\HouseLog;

class GoogleDistance extends AbstractDistance {

    /**
     * @param \PDO $dbh
     * @param array $originAddr
     * @param array $destinationAddr
     * @throws RuntimeException
     * @return array [units=>meters|miles, value=>int]
     */
    protected function calcDistance(\PDO $dbh, array $originAddr, array $destinationAddr) {

        $responseJson = $this->sendRequest($dbh, $originAddr, $destinationAddr);

        if($responseJson->status == "OK" && isset($responseJson->routes[0]->legs[0]->distance)){
            return array("type"=>"google", "units"=>"meters", "value"=>$responseJson->routes[0]->legs[0]->distance->value);
        }else{
            throw new RuntimeException("Failed to get driving distance: cannot find distance in API response");
        }

    }

    /**
     * @param array $originAddr
     * @param array $destinationAddr
     * @throws RuntimeException
     * @return object JSON response
     */
    private function sendRequest(\PDO $dbh, array $originAddr, array $destinationAddr){

        $uS = Session::getInstance();

        $endpoint = "https://maps.googleapis.com/maps/api/directions/json";
        $apiKey = decryptMessage($uS->recaptchaApiKey);

        try{

            $params = [
                "origin"=>$this->stringifyAddr($originAddr),
                "destination"=>$this->stringifyAddr($destinationAddr),
                "key"=>$apiKey
            ];

            $client = new Client();
            $response = $client->get($endpoint, ['query'=>$params, 'headers'=>['Accept'=>'applicatioin/json']]);

            HouseLog::logApi($dbh, "GoogleDirections", true, "Called " . $endpoint . " successfully", $uS->username);

            return json_decode($response->getBody());

        }catch(\Exception $e){
            HouseLog::logApi($dbh, "GoogleDirections", false, "Error calling " . $endpoint . ": " . $e->getMessage(), $uS->username);
            throw new RuntimeException("Failed to get driving distance: " . $e->getMessage());
        }
    }
}

?>