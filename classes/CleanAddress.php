<?php
/**
 * CleanAddress.php
 *
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class CleanAddress {

    protected $stSuffixes;
    protected $secAbvrs;

    function __construct(PDO $dbh) {
        $this->stSuffixes = $this->getStreetSuffix($dbh);
        $this->secAbvrs = $this->getSecondary($dbh);

    }

    public function cleanAddr($adr) {
        $secondary = "";
        $priAdr = "";

        // try splitting by comma and look for apt, suite, etc.
        $phrse = explode(",", trim($adr));

        if (count($phrse) == 2) {
            // There was a comma leading to the supposition of an apt number.
            $addrs = $this->convertSuffix(trim($phrse[0]));
            $priAdr = $addrs[0];

            $secondary = $this->convertSecondary(trim($phrse[1]));
        }
        else {
            $addrs = $this->convertSuffix(trim($adr));
            $priAdr = $addrs[0];
            $secondary = $addrs[1];
        }

        $newAdr = array(0=>$priAdr, 1=>$secondary);

        return $newAdr;
    }

    public function checkSecondary($aptAdr) {

        $secAdr = explode(" ", $aptAdr);

        for ($i = 0; $i < count($secAdr); $i++) {

            $secAdr[$i] = str_ireplace("#", "", $secAdr[$i]);

            if (intval($secAdr[$i], 10) == 0) {
                // not a number
                if (strtoupper($secAdr[$i]) != $secAdr[$i]) {
                    // Not all capatalized.
                    $secAdr[$i] = ucfirst(strtolower($secAdr[$i]));
                }
            }
        }

        $newAdr = "";

        if (count($secAdr) > 0) {
            $sec = trim(str_ireplace("#", "", str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($secAdr[0]))))));

            if (isset($this->secAbvrs[$sec])) {

                $newAdr = $this->secAbvrs[$sec];
                // Concatenate any remaining address words.
                for ($i = 1; $i < count($secAdr); $i++) {
                    $newAdr .= " " . str_ireplace("#", "", trim($secAdr[$i]));
                }
            }
        }

        return $newAdr;
    }

    public function convertSecondary($aptAdr) {

        $secAdr = explode(" ", $aptAdr);

        for ($i = 0; $i < count($secAdr); $i++) {

            $secAdr[$i] = str_ireplace("#", "", $secAdr[$i]);

            if (intval($secAdr[$i], 10) == 0) {
                // not a number
                if (strtoupper($secAdr[$i]) != $secAdr[$i]) {
                    // Not all capatalized.
                    $secAdr[$i] = ucfirst(strtolower($secAdr[$i]));
                }
            }
        }

        $newAdr = "";

        if (count($secAdr) > 0) {

            // secondary could contain a street address
            if (intval($secAdr[0], 10) > 0 && count($secAdr) > 2) {

                $adrArray = $this->convertSuffix($aptAdr);
                $newAdr = $adrArray[0];

            } else {

                $sec = trim(str_ireplace("#", "", str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($secAdr[0]))))));

                $newAdr = isset($this->secAbvrs[$sec]) ? $this->secAbvrs[$sec] : trim($secAdr[0]);

                // Concatenate any remaining address words.
                for ($i = 1; $i < count($secAdr); $i++) {
                    $newAdr .= " " . str_ireplace("#", "", trim($secAdr[$i]));
                }
            }
        }

        return $newAdr;
    }

    public function convertSuffix($primary) {

        $wrds = explode(" ", $primary);

        for ($i = 0; $i < count($wrds); $i++) {
            if (intval($wrds[$i], 10) == 0) {
                // not a number
                if (strtoupper($wrds[$i]) != $wrds[$i]) {
                    // Not all capatalized.
                    $wrds[$i] = ucfirst(strtolower($wrds[$i]));
                }
            }
        }

        $newAdr = implode(" ", $wrds);
        $secAdr = "";

        if (count($wrds) == 3) {
            $suf = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[1])))));

            // Check for the second word to be a direction, i.e. 101 N. Bluff
            //   and the last word is not a suffix, but a street name.
            if ($suf == "N" || $suf == "S" || $suf == "E" ||$suf == "W") {
                $newAdr = $wrds[0] . " " . $suf . " " . $wrds[2];
            } else {

                // The last word may be a suffix.
                $suf = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[2])))));
                if (isset($this->stSuffixes[$suf])) {
                    $newAdr = $wrds[0] . " " . $wrds[1] . " " . $this->stSuffixes[$suf];
                }
            }

        } else if (count($wrds) == 4) {
            $suf = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[3])))));

            if (!isset($this->stSuffixes[$suf])) {
                // check for the last word being a direction.  '101 Pine St. N'
                if ($suf == "N" || $suf == "S" || $suf == "E" ||$suf == "W" || $suf == "NORTH" || $suf == "SOUTH" || $suf == "EAST" || $suf == "WEST") {
                    $suf2 = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[2])))));

                    if (isset($this->stSuffixes[$suf2])) {
                        $newAdr = $wrds[0] . " " . $wrds[1] . " " .  $this->stSuffixes[$suf2] . " " . ucfirst(strtolower($suf));
                    }
                } else {
                    // Maybe have apt number?  '101 Pine Apt 3'
                    $secAdr = $this->checkSecondary($wrds[2]." ".$wrds[3]);

                    if ($secAdr != "") {
                        $newAdr = $wrds[0] . " " . $wrds[1];  // . " " . $secAdr;
                    } else {
                        $newAdr = $wrds[0] . " " . $wrds[1] . " " . $wrds[2]." ".$wrds[3];
                    }
                }
            } else {
                // We have an address with a standard suffix.
                // check for the second word being a direction.  '101 N. Pine St'
                $suf2 = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[1])))));
                if ($suf2 == "N" || $suf2 == "S" || $suf2 == "E" ||$suf2 == "W") {
                    $newAdr = $wrds[0] . " " . $suf2 . " " . $wrds[2] . " " . $this->stSuffixes[$suf];
                } else {
                    $newAdr = $wrds[0] . " " . $wrds[1] . " " . $wrds[2] . " " . $this->stSuffixes[$suf];
                }

            }
        } else if (count($wrds) == 5) {
            $suf = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[4])))));

            if (!isset($this->stSuffixes[$suf])) {
                // check for the last word being a direction.
                if ($suf == "N" || $suf == "S" || $suf == "E" ||$suf == "W" || $suf == "NORTH" || $suf == "SOUTH" || $suf == "EAST" || $suf == "WEST") {
                    $suf2 = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[3])))));

                    if (isset($this->stSuffixes[$suf2])) {
                        $newAdr = $wrds[0] . " " . $wrds[1] . " " . $wrds[2] . " " . $this->stSuffixes[$suf2] . " " . ucfirst(strtolower($suf));
                    }
                } else {
                    // Maybe have apt number?  ' 101 N pine apt 3
                    $secAdr = $this->checkSecondary($wrds[3]." ".$wrds[4]);
                    if ($secAdr != "") {
                        // Check the third word for a street suffix
                        $suf3 = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[2])))));

                        if (isset($this->stSuffixes[$suf3])) {
                            $newAdr = $wrds[0] . " " . $wrds[1] . " " . $this->stSuffixes[$suf3]; // . " " . $secAdr;
                        } else {
                            $newAdr = $wrds[0] . " " . $wrds[1] . " " . ucfirst(strtolower($suf3));  // . " " . $secAdr;
                        }
                    } else {
                        $newAdr = $wrds[0] . " " . $wrds[1] . " " . $wrds[2]." ".$wrds[3]." ".$wrds[4];
                    }
                }
            } else {
                // We have an address with a standard suffix.
                // check for the second word being a direction.  '101 N. Pine St'
                $suf2 = trim(str_ireplace(",", "", str_ireplace(".", "", strtoupper(trim($wrds[1])))));
                if ($suf2 == "N" || $suf2 == "S" || $suf2 == "E" ||$suf2 == "W") {
                    $newAdr = $wrds[0] . " " . $suf2 . " " . $wrds[2] . " " . $wrds[3] . " " . $this->stSuffixes[$suf];
                } else {
                    $newAdr = $wrds[0] . " " . $wrds[1] . " " . $wrds[2] . " " . $wrds[3] . " " . $this->stSuffixes[$suf];
                }
            }
        }

        return array(0=>$newAdr, 1=>$secAdr);
    }

    public static function getStreetSuffix(PDO $dbh) {
        $lst = array();
        $stmt = $dbh->query("select Common, TitleCaps from street_suffix order by Common");

        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $rw) {
            $lst[trim($rw[0])] = $rw[1];
        }
        return $lst;
    }

    public static function getSecondary(PDO $dbh) {
        $lst = array();
        $stmt = $dbh->query("select Common, TitleCaps from secondary_unit_desig order by Common");

        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $rw) {
            $lst[trim($rw[0])] = $rw[1];
        }
        return $lst;
    }

}

?>