<?php
/**
 * UserCategories.php
 *
 * @category  Member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

class UserCategories {

    private $myCats = array();
    private $isLoaded;
    private $myRole = 100;
    private $myUsername = "";

    const ActiveAll = 1;
    const ActiveNonDormant = 2;
    const RetiredAll = 3;
    const Guest = 4;

    private $id = 0;

    function __construct($nameId, $roleId, $username = "") {
        $this->id = $nameId;
        $this->myRole = $roleId;
        $this->myUsername = $username;
        $this->isLoaded = false;
    }

    public function loadFromDb(PDO $dbh) {
        $query = "select Vol_Category, Vol_Code, ifnull(Dormant_Code,'') as Dormant_Code, ifnull(Vol_Rank,'') as Vol_Rank, Vol_Status, Vol_Begin
            from name_volunteer2 where idName =:id;";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':id'=>$this->id));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $temp = array();
        foreach ($rows as $row) {
            $temp["cat"] = $row["Vol_Category"];
            $temp["type"] = $row["Vol_Code"];
            $temp["rank"] = $row["Vol_Rank"];
            $temp["status"] = $row["Vol_Status"];
            $temp["dormant"] = $row["Dormant_Code"];
            $this->myCats[] = $temp;
        }

        $this->isLoaded = true;
    }

    public function allowBypass($combinedVCC) {
        $parts = $this->splitVcc($combinedVCC);

//        // maybe there already is an entry
        $cts = $this->getCats(UserCategories::Guest);
        if ($cts == null) {
            $temp["cat"] = $parts[0];
            $temp["type"] = $parts[1];
            $temp["rank"] = "c";
            $temp["status"] = "a";
            $this->myCats[] = $temp;
        } else {
            $this->myCats[$cts]["rank"] = "c";
        }
        return;
    }

    public function getCats($filter) {
        if ($this->isLoaded) {
            $data = array();
            switch ($filter) {
                case UserCategories::ActiveAll:
                    foreach ($this->myCats as $row) {
                        if ($row["status"] == "a") {
                            $rtn = array();
                            $rtn["cat"] = $row["cat"];
                            $rtn["type"] = $row["type"];
                            $rtn["rank"] = $row["rank"];
                            $data[] = $rtn;
                        }
                    }
                    break;

                case UserCategories::ActiveNonDormant:

                    break;
                case UserCategories::RetiredAll:

                    break;

                case UserCategories::Guest:
                    $data = null;
//                    foreach ($this->myCats as $key => $row) {
//                        if ($row["status"] == "a" && $row["cat"] == "Vol_Type" && $row["type"] == "g" ) {
//                            $data = $key;
//                        }
//                    }

                    break;

                default:
                    $data = array("error" => "Bad filter Index.");
            }

        } else {
            $data = array("error" => "Data Not Loaded.");
        }
        return $data;
    }

    private function getUserRank($cat, $tpe) {
        if (!$this->isLoaded) {
            return VolRank::Guest;
        }

        $rol = VolRank::Guest;
        foreach ($this->myCats as $row) {
            if (strtolower($row["cat"]) == strtolower($cat) && strtolower($row["type"]) == strtolower($tpe)) {
                $rol = $row["rank"];
            }
        }

        return $rol;
    }

    public function runAuthorization($cat, $tpe, $eventNameId) {
        if ($this->isLoaded()) {
            if (SecurityComponent::is_Admin($this->myRole, $this->myUsername)) {
                $answer = true;
            }
            else {
                $rank = $this->getUserRank($cat, $tpe);
                switch ($rank) {
                    case VolRank::Chair:
                        $answer = true;
                        break;

                    case VolRank::CoChair:
                        $answer = true;
                        break;

                    case VolRank::Member:
                        if ($this->id == $eventNameId)
                            $answer = true;
                        else
                            $answer = false;
                        break;

                    case VolRank::Guest:
                        $answer = false;
                        break;

                    default:
                        $answer = false;
                }
            }
         }
        else
            $answer = false;    // Data not loaded

        return $answer;
    }

    public function isLoaded() {
        return $this->isLoaded;
    }

    public function get_Username() {
        return $this->myUsername;
    }
    public function get_IdName() {
        return $this->id;
    }
    public function get_role() {
        return $this->myRole;
    }
    public static function splitVcc($pipedString) {
        $prts = explode("|", $pipedString);
        if (count($prts) >= 2) {
            return $prts;
        }
        else {
            return array();
        }
    }
    public static function combineVcc($category, $type, $pipeSymbol = "|") {
        return $category.$pipeSymbol.$type;
    }
}

?>
