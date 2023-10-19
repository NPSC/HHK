<?php
namespace HHK\House\Distance;

use HHK\Exception\RuntimeException;

class ZipDistance extends AbstractDistance{

    protected const TYPE = "nautical";

    /**
     * @param \PDO $dbh
     * @param array $originAddr
     * @param array $destinationAddr
     * @throws RuntimeException
     * @return array [units=>meters|miles, value=>int]
     */
    protected function calcDistance(\PDO $dbh, array $originAddr, array $destinationAddr) {

        $sourceZip = $originAddr['zip'] ?? '';
        $destZip = $destinationAddr['zip'] ?? '';

        if (strlen($destZip) > 5) {
            $destZip = substr($destZip, 0, 5);
        }

        if ($destZip == $sourceZip) {
            return array("type"=>"zip", "units"=>"miles", "value"=>0);
        }

        $miles = 0;
        $stmt = $dbh->prepare("select Zip_Code, Lat, Lng from postal_codes where Zip_Code in (:src, :dest)");
        $stmt->execute(array(':src' => $sourceZip, ':dest' => $destZip));

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        if (count($rows) == 2) {
            $miles = self::GPS2Miles($rows[0][1], $rows[0][2], $rows[1][1], $rows[1][2]);
        } else {
            return array("type"=>"zip", "units"=>"miles", "value"=>"-1");
        }
        return array("type"=>"zip", "units"=>"miles", "value"=>$miles);
    }

    protected static function GPS2Miles($lat_A, $long_A, $lat_B, $long_B) {

        $distance = sin(deg2rad((double)$lat_A)) * sin(deg2rad((double)$lat_B)) + cos(deg2rad((double)$lat_A)) * cos(deg2rad((double)$lat_B)) * cos(deg2rad((double)$long_A - (double)$long_B));
        $distance2 = (rad2deg(acos($distance))) * 69.09;

        return $distance2;
    }

}

?>