<?php
namespace HHK\House\Distance;

abstract class AbstractDistance{

    /**
     *
     * Gets distance between two address arrays (full or partial, zip required)
     *
     * Address arrays should be formatted as: array('address1'=>'', 'address2'=>'', 'city'=>'', 'state'=>'', 'zip'=>'')
     *
     * @param \PDO $dbh
     * @param array $originAddr
     * @param array $destinationAddr
     * @param string $returnType - "miles"|"meters"
     * @return number distance
     */
    public function getDistance(\PDO $dbh, array $originAddr, array $destinationAddr, string $returnType){

        $distanceArray = $this->calcDistance($dbh, $originAddr, $destinationAddr);
        $distance = 0;

        if($returnType == "miles" && isset($distanceArray['units']) && $distanceArray['units'] == 'meters'){
            $distance = self::meters2miles($distanceArray['value']);
        }elseif ($returnType == "meters" && isset($distanceArray['units']) && $distanceArray['units'] == 'miles'){
            $distance = self::miles2meters($distanceArray['value']);
        }elseif(isset($distanceArray['units']) && $distanceArray['units'] == $returnType){
            $distance = $distanceArray['value'];
        }

        return $distance;
    }

    protected function stringifyAddr(array $addr){
        return ($addr['address1'] ?? "") . ' ' . ' ' . ($addr['city'] ?? "") . ' ' . ($addr['state'] ?? "") . ' ' . ($addr['zip'] ?? "");
    }

    public function meters2miles(int $meters){
        return round($meters/1609.344, 2);
    }

    public function miles2meters(int $miles){
        return $miles*1609.344;
    }

    protected function calcDistance(\PDO $dbh, array $originAddr, array $destinationAddr){

    }

}

?>