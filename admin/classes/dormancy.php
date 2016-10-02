<?php
/**
 * dormancy.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class dormancyClass {

    private $iddormant = 0;
    private $BeginActive;
    private $EndActive;
    private $BeginDormant;
    private $EndDormant;
    private $Title = "";
    private $Code = "";
    private $Status = "";
    private $Description = "";
    private $Updatedby = "";
    private $LastUpdated;
    private $TimeStamp;

    public function get_iddormant() {
        return $this->iddormant;
    }

    public function set_iddormant($iddormant) {
        $this->iddormant = $iddormant;
    }

    public function get_BeginActive() {
        return $this->BeginActive;
    }

    public function set_BeginActive($BeginActive) {
        $this->BeginActive = $BeginActive;
    }

    public function get_EndActive() {
        return $this->EndActive;
    }

    public function set_EndActive($EndActive) {
        $this->EndActive = $EndActive;
    }

    public function get_BeginDormant() {
        return $this->BeginDormant;
    }

    public function set_BeginDormant($BeginDormant) {
        $this->BeginDormant = $BeginDormant;
    }
    public function get_EndDormant() {
        return $this->EndDormant;
    }

    public function set_EndDormant($EndDormant) {
        $this->EndDormant = $EndDormant;
    }

    public function get_Title() {
        return $this->Title;
    }

    public function set_Title($Title) {
        $this->Title = $Title;
    }
    public function get_Code() {
        return $this->Code;
    }

    public function set_Code($Code) {
        $this->Code = $Code;
    }

    public function get_Description() {
        return $this->Description;
    }

    public function set_Description($Description) {
        $this->Description = $Description;
    }
    public function get_Updatedby() {
        return $this->Updatedby;
    }

    public function set_Updatedby($Updatedby) {
        $this->Updatedby = $Updatedby;
    }

    public function get_LastUpdated() {
        return $this->LastUpdated;
    }

    public function set_LastUpdated($LastUpdated) {
        $this->LastUpdated = $LastUpdated;
    }

    public function get_TimeStamp() {
        return $this->TimeStamp;
    }

    public function set_TimeStamp($TimeStamp) {
        $this->TimeStamp = $TimeStamp;
    }

    public function get_Status() {
        return $this->Status;
    }

    public function set_Status($Status) {
        $this->Status = $Status;
    }


    public function __Construct($con, $dormCode) {

        if ($dormCode == "") {
            return;
        }
        $resultSet = queryDB($con, "Select * from dormant_schedules where Code='" . $dormCode . "'", true);
        $rw = mysqli_fetch_array($resultSet);

        $this->set_iddormant($rw["iddormant_schedules"]);
        $this->set_BeginActive($rw["Begin_Active"]);
        $this->set_EndActive($rw["End_Active"]);
        $this->set_BeginDormant($rw["Begin_Dormant"]);
        $this->set_EndDormant($rw["End_Dormant"]);
        $this->set_Title($rw["Title"]);
        $this->set_Code($rw["Code"]);
        $this->set_Status($rw["Status"]);
        $this->set_Description($rw["Description"]);
        $this->set_Updatedby($rw["Updated_by"]);
        $this->set_LastUpdated($rw["Last_Updated"]);
        $this->set_TimeStamp($rw["Time_Stamp"]);

    }
}
?>
