<?php
namespace HHK\House\Distance;

use HHK\sec\Session;
use HHK\Exception\RuntimeException;

/**
 * Creates correct Distance object depending on distCalculator site config param
 *
 */
class DistanceFactory{

    /**
     * @throws RuntimeException
     * @return AbstractDistance
     */
    public static function make() {
        $uS = Session::getInstance();

        switch ($uS->distCalculator){
            case "zip":
                return new ZipDistance();
                break;
            case "google":
                return new GoogleDistance();
                break;
            default:
                return new ZipDistance();
        }
    }

}

?>