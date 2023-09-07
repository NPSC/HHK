<?php
namespace HHK\House\Distance;

abstract class AbstractDistance{

    protected const TYPE = "";

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
    public function getDistance(\PDO $dbh, array|null $originAddr, array|null $destinationAddr, string $returnType){
        $distance = 0;

        if(is_array($originAddr) && is_array($destinationAddr) && count(array_diff_assoc($originAddr, $destinationAddr)) > 0){
            $distanceArray = $this->calcDistance($dbh, $originAddr, $destinationAddr);
            
            if($returnType == "miles" && isset($distanceArray['units']) && $distanceArray['units'] == 'meters'){
                $distance = self::meters2miles($distanceArray['value']);
            }elseif ($returnType == "meters" && isset($distanceArray['units']) && $distanceArray['units'] == 'miles'){
                $distance = self::miles2meters($distanceArray['value']);
            }elseif(isset($distanceArray['units']) && $distanceArray['units'] == $returnType){
                $distance = $distanceArray['value'];
            }
        }

        return $distance;
    }

    protected function stringifyAddr(array $addr){
        return ($addr['address1'] ?? "") . ' ' . ' ' . ($addr['city'] ?? "") . ' ' . ($addr['state'] ?? "") . ' ' . ($addr['zip'] ?? "");
    }

    public function meters2miles(float $meters){
        return round($meters/1609.344, 2);
    }

    public function miles2meters(float $miles){
        return $miles*1609.344;
    }

    /**
     * Summary of calcDistance
     * @param \PDO $dbh
     * @param array $originAddr
     * @param array $destinationAddr
     * @return array
     */
    protected function calcDistance(\PDO $dbh, array $originAddr, array $destinationAddr){
        return array();
    }

    public function getType(){
        return static::TYPE;
    }

}

?>