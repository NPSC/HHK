<?php
/**
 * campaign.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class Campaign {

    protected $campRS;


    public static function CampaignSelOptionMarkup(PDO $dbh, $campCode, $newOpt, $shoDisabled = true) {
        // Load the selector control with all the campaigns.
        $query = "";

        if ($shoDisabled) {
            $query = "Select Campaign_Code, Title, Status, Start_Date, End_Date from campaign";  // where Status='a' and (case when End_Date is null then now() else End_Date end) >= now()";
        } else {
            // Onlny show active campaigns
            $query = "Select Campaign_Code, Title, Status, Start_Date, End_Date from campaign where Status = 'a'";
        }
        $stmt = $dbh->query($query);
        $timedOut = "";
        $disOut = "";
        $currentOpt = "";
        $futureOpt = "";
        $CampOpt = "";

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row2) {

            $cCode = $row2["Campaign_Code"];
            $cTitle = $row2["Title"];
            $cStatus = trim($row2["Status"]);
            $cStart = strtotime($row2["Start_Date"]);
            $cEnd = strtotime($row2["End_Date"]);

            if ($campCode == $cCode) {
                $optString = "<option value='" . $cCode . "' selected='selected'>" . $cTitle . "</option>";
            } else {
                $optString = "<option value='" . $cCode . "'>" . $cTitle . "</option>";
            }


            if ($cStatus == 'a') {

                if (time() > $cEnd) {
                    $timedOut .= $optString;
                } else if (time() < $cStart) {
                    $futureOpt .= $optString;
                } else {
                    $currentOpt .= $optString;
                }
            } else {
                $disOut .= $optString;
            }
        }

        if ($timedOut != "") {
            $timedOut = "<optgroup label='Timed Out' class='cogTimedOut' >" . $timedOut . "</optgroup>";
        }

        if ($disOut != "") {
            $disOut = "<optgroup label='Disabled' class='cogDisabled' >" . $disOut . "</optgroup>";
        }

        if ($currentOpt != "") {
            $currentOpt = "<optgroup label='Current' class='cogCurrent' >" . $currentOpt . "</optgroup>";
        }

        if ($futureOpt != "") {
            $futureOpt = "<optgroup label='Future' class='cogFuture' >" . $futureOpt . "</optgroup>";
        }

        if ($newOpt) {
            if ($campCode == "") {
                $CampOpt = "<option value='vNew' selected='selected'>New</option>";
            } else {
                $CampOpt = "<option value='vNew'>New</option>";
            }
        }

        $CampOpt .= $currentOpt . $futureOpt . $timedOut . $disOut;
        // return just the options
        return $CampOpt;
    }

    public function get_percentCut() {
        return $this->campRS->Percent_Cut->getStoredVal();
    }

    public function set_percentCut($v) {
        $this->campRS->Percent_Cut->setNewVal($v);
    }

    public function get_type() {
        return $this->campRS->Campaign_Type->getStoredVal();
    }

    public function set_type($v) {
        $this->campRS->Campaign_Type->setNewVal($v);
    }

    public function get_idcampaign() {
        return $this->campRS->idcampaign->getStoredVal();
    }

    public function get_campaigncode() {
        return $this->campRS->Campaign_Code->getStoredVal();
    }

    public function set_campaigncode($v) {
        $this->campRS->Campaign_Code->setNewVal($v);
    }

    public function set_mergecode($v) {
        $this->campRS->Campaign_Merge_Code->setNewVal($v);
    }

    public function get_mergecode() {
        return $this->campRS->Campaign_Merge_Code->getStoredVal();
    }

    public function get_startdate() {
        return $this->campRS->Start_Date->getStoredVal();
    }

    public function set_startdate($v) {
        $this->campRS->Start_Date->setNewVal($v);
    }

    public function get_enddate() {
        return $this->campRS->End_Date->getStoredVal();
    }

    public function set_enddate($v) {
        $this->campRS->End_Date->setNewVal($v);
    }

    public function get_title() {
        return $this->campRS->Title->getStoredVal();
    }

    public function set_title($v) {
        $this->campRS->Title->setNewVal($v);
    }

    public function get_description() {
        return $this->campRS->Description->getStoredVal();
    }

    public function set_description($v) {
        $this->campRS->Description->setNewVal($v);
    }

    public function get_target() {
        return $this->campRS->Target->getStoredVal();
    }

    public function set_target($v) {
        $this->campRS->Target->setNewVal($v);
    }

    public function get_mindonation() {
        return $this->campRS->Min_Donation->getStoredVal();
    }

    public function get_mindonationString() {
        return number_format($this->get_mindonation(), 2, ".", ",");
    }

    public function set_mindonation($v) {
        $this->campRS->Min_Donation->setNewVal($v);
    }

    public function get_maxdonation() {
        return $this->campRS->Max_Donation->getStoredVal();
    }

    public function get_maxdonationString() {
        return number_format($this->get_maxdonation(), 2, ".", ",");
    }

    public function set_maxdonation($v) {
        $this->campRS->Max_Donation->setNewVal($v);
    }

    public function get_category() {
        return $this->campRS->Category->getStoredVal();
    }

    public function set_category($v) {
        $this->campRS->Category->setNewVal($v);
    }

    public function get_status() {
        return $this->campRS->Status->getStoredVal();
    }

    public function set_status($v) {
        $this->campRS->Status->setNewVal($v);
    }

    public function get_lastupdated() {
        return $this->campRS->Last_Updated->getStoredVal();
    }

    public function set_lastupdated($v) {
        $this->campRS->Last_Updated->setNewVal($v);
    }

    public function get_updatedby() {
        return $this->campRS->Updated_By->getStoredVal();
    }

    public function set_updatedby($v) {
        $this->campRS->Updated_By->setNewVal($v);
    }

    public function isAmountValid($amt) {

        if ($this->get_maxdonation() == 0 && $this->get_mindonation() == 0) {
            return TRUE;

        } else if ($this->get_maxdonation() == 0 && $amt - $this->get_mindonation() >= 0) {
            return TRUE;

        } else if ($amt - $this->get_mindonation() >= 0 && ($this->get_maxdonation() - $amt) >= 0) {
            return TRUE;
        }
        return FALSE;
    }


    public function __construct(PDO $dbh, $campCode = "") {

        $campRS = new CampaignRS();
        $campRS->Campaign_Code->setStoredVal($campCode);
        $rows = EditRS::select($dbh, $campRS, array($campRS->Campaign_Code));

        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $campRS);
        }

        $this->campRS = $campRS;

    }

}

?>
