<?php
use HHK\House\Distance\ZipDistance;
use HHK\Member\Address\Address;
use HHK\sec\Login;
use HHK\sec\Session;
use HHK\sec\WebInit;
use PHPUnit\Framework\TestCase;

class DistanceTest extends TestCase {

    public function testGetHouseAddress(){
        $dbh = Login::initHhkSession(CONF_PATH, ciCFG_FILE);
        $uS = Session::getInstance();

        WebInit::loadNameLookups($dbh, $uS);

        $houseAdr = Address::getHouseAddress($dbh);

        $this->assertIsArray($houseAdr);
    }

    /**
     * Test the Zip Code distance calculator
     * @return void
     */
    public function testZipDistanceEvartToDekalb() {
        $dbh = Login::initHhkSession(CONF_PATH, ciCFG_FILE);
        $distance = new ZipDistance();

        $distance = number_format($distance->getDistance($dbh, ['zip'=>"49631"], Address::getHouseAddress($dbh), "miles"), 0);

        $this->assertEquals("225", $distance);
    }

    public function testZipDistanceWaikikiToDekalb() {
        $dbh = Login::initHhkSession(CONF_PATH, ciCFG_FILE);
        $distance = new ZipDistance();

        $distance = number_format($distance->getDistance($dbh, ['zip'=>"96830"], Address::getHouseAddress($dbh), "miles"), 0);

        $this->assertEquals("4,191", $distance);
    }

}

?>