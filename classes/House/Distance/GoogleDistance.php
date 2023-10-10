<?php
namespace HHK\House\Distance;

use GuzzleHttp\Client;
use HHK\AuditLog\NameLog;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Session;
use HHK\TableLog\HouseLog;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameAddressRS;

class GoogleDistance extends AbstractDistance {

    private CONST APICOST = 0.005;
    private array $UncalculatedAddresses;

    protected const TYPE = "driving";

    /**
     * @param \PDO $dbh
     * @param array $originAddr
     * @param array $destinationAddr
     * @throws RuntimeException
     * @return array [units=>meters|miles, value=>int]
     */
    protected function calcDistance(\PDO $dbh, array $originAddr, array $destinationAddr) {

        $uS = Session::getInstance();
        
        $responseJson = $this->sendRequest($dbh, $originAddr, $destinationAddr);

        if($responseJson->status == "OK" && isset($responseJson->routes[0]->legs[0]->distance)){
            return array("type"=>"google", "units"=>"meters", "value"=>$responseJson->routes[0]->legs[0]->distance->value);
        }else{
            HouseLog::logApi($dbh, "GoogleDirections", false, "Error parsing response: " . json_encode($responseJson), $uS->username);
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
            $response = $client->get($endpoint, ['query'=>$params, 'headers'=>['Accept'=>'application/json']]);

            HouseLog::logApi($dbh, "GoogleDirections", true, "Called " . $endpoint . " successfully" . (isset($originAddr['idName_Address']) ? " - idName_Address: " . $originAddr['idName_Address'] : ""), $uS->username);

            return json_decode($response->getBody());

        }catch(\Exception $e){
            HouseLog::logApi($dbh, "GoogleDirections", false, "Error calling " . $endpoint . ": " . $e->getMessage(), $uS->username);
            throw new RuntimeException("Failed to get driving distance: " . $e->getMessage());
        }
    }

    public function getEditMarkup(\PDO $dbh){
        $this->UncalculatedAddresses = $this->getUncalculatedAddresses($dbh);

        $tbl = new HTMLTable();
        $tbl->addBodyTr(
            HTMLTable::makeTd("Uncalculated Addresses") . 
            HTMLTable::makeTd(count($this->UncalculatedAddresses))
        );
        $tbl->addBodyTr(
            HTMLTable::makeTd("Estimated cost to calculate all uncalculated addresses") . 
            HTMLTable::makeTd("$" . $this->calculateApiCost())
        );

        $tbl->addBodyTr( 
            HTMLTable::makeTd("Calculate " . HTMLInput::generateMarkup("", array("type"=>"number", "name"=>"numAddrCalc", "style"=>"width: 5em;")) . " addresses")
        , array("colspan"=>"2"));

        $tbl->addBodyTr( 
            HTMLTable::makeTd("Estimated cost to calculate: " . HTMLInput::generateMarkup(GoogleDistance::APICOST, array("type"=>"hidden", "id"=>"googleApiCostPerCall"))) . 
            HTMLTable::makeTd("", array("id"=>"googleApiCost"))
        );

        $tbl->addBodyTr( 
            HTMLTable::makeTd(
                HTMLContainer::generateMarkup("button", "Calculate Addresses", array("role"=>"button", "class"=>"ui-button mr-3", "id"=>"btnCalcAddresses")) . 
                HTMLContainer::generateMarkup("button", "List Uncalculated Addresses", array("role"=>"button", "class"=>"ui-button", "id"=>"btnLoadUncalcAddrTbl"))
                )
        , array("colspan"=>"2"));

        return $tbl->generateMarkup(array("class"=>"mb-3")) . HTMLContainer::generateMarkup("div", "", array("id"=>"uncalcAddrTbl"));
    }

    public function getUncalculatedAddresses(\PDO $dbh){
        $stmt = $dbh->query("select na.idName_Address, na.idName, n.Name_Full, na.Address_1, na.Address_2, na.City, na.State_Province, na.Postal_Code from name_address na join name n on na.idName = n.idName where Meters_From_House is null and Address_1 != '' and City != '' and State_province != '' and Postal_Code != '' order by na.Timestamp desc;");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function calculateApiCost(int $numCalls = -1){
        if($numCalls >= 0){
            return $numCalls*GoogleDistance::APICOST;
        }else{
            return count($this->UncalculatedAddresses)*GoogleDistance::APICOST;
        }
    }

    /**
     * Calculate distance and update name_address table given an array of addresses, optionally specify maximum number of API calls
     * @param \PDO $dbh
     * @param array $UncalculatedAddresses
     * @param int $numCalls
     * @return array
     */
    public function calcDistanceArray(\PDO $dbh, array $UncalculatedAddresses, int $numCalls = -1){
        $destAddress = array();
        $callCounter = 0;
        $uS = Session::getInstance();

        //if numCalls isn't specified, calculate entire array
        if($numCalls == -1){
            $numCalls = count($UncalculatedAddresses);
        }

        foreach($UncalculatedAddresses as $originaddress){
            $distanceArr = $this->calcDistance($dbh, $originaddress, $destAddress);

            if($distanceArr["units"] = "meters" && $distanceArr["value"] > 0 && isset($originaddress['idName_Address'])){
                $nameAddressRS = new NameAddressRS();
                $nameAddressRS->idName_Address->setStoredVal($originaddress['idName_Address']);
                $rows = EditRS::select($dbh, $nameAddressRS, array($nameAddressRS->idName_Address));
                if(count($rows) == 1){
                    EditRS::loadRow($rows[0], $nameAddressRS);
                    $nameAddressRS->Meters_From_House = $distanceArr["value"];
                    EditRS::update($dbh,$nameAddressRS, array($nameAddressRS->idName_Address));
                    NameLog::writeUpdate($dbh, $nameAddressRS, $nameAddressRS->idName->getStoredVal(), $uS->username);
                    $callCounter++;
                }
            }

            if($callCounter == $numCalls){
                break;
            }
        }
        if($callCounter > 0){
            return ["success"=> $callCounter . " addresses updated."];
        }else{
            return ["error"=>"An error occurred, no addresses were updated."];
        }
    }

}

?>