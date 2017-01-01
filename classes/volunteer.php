<?php
/**
 * volunteer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of volunteer
 *
 * @author Eric
 */
class VolunteerCategory {

    protected $Category_Code = "";
    protected $title = "";
    protected $isTabChecked = false;
    protected $vNotesMarkup = array();    // Copies of the notes in suitable markup
    protected $rankOptions = array();
    protected $alsoCheck;   // do we need to join an associated cat/code?  (like volunteer code)

    function __construct($code, $title, $alsoCheck = '') {
        $this->Category_Code = $code;
        $this->title = $title;
        $this->alsoCheck = $alsoCheck;
    }

    public function getCategoryCode() {
        return $this->Category_Code;
    }

    public function getCategoryTitle() {
        return $this->title;
    }

    function getTabMarkup() {
        $tabIcon = "";

        if ($this->isTabChecked) {
            $tabIcon = "<span class='ui-icon ui-icon-check' style='float: left; margin-right: .3em;'></span>";
        } else {
            $tabIcon = "";
        }

        $volTab = "<li><a href='#".$this->Category_Code."'>$tabIcon".$this->title."</a></li>";
        return $volTab;
    }

    public function getNotesMarkup() {
        return $this->vNotesMarkup;
    }

    protected function getRankOptions($selectedRank = "") {

        $selectedRank = trim($selectedRank);

        if (is_null($selectedRank) || $selectedRank == "") {
            $data = "<option value='' selected='selected'></option>";
        } else {
            $data = "<option value=''></option>";
        }

        foreach ($this->rankOptions as $row) {

            if ($selectedRank == $row[0])
                $data = $data . "<option value='" . $row[0] . "' selected='selected'>" . $row[1] . "</option>";
            else
                $data = $data . "<option value='" . $row[0] . "'>" . $row[1] . "</option>";
        }

        return $data;
    }

    public function set_rankOptions(array $rankOptions) {
        $this->rankOptions = $rankOptions;
    }

    public function volCategoryDivMarkup($dbh, $id) {

        $data = "";

        $volData = $this->getVolCategoryData($dbh, $id, $this->Category_Code);

        $height = (140 * count($volData)) + 45;
        $hdr = "<div id='".$this->Category_Code."' style='height:".$height."px'>
        <h2 class='top-header'>Options</h2>
        <div class='toggle-docs-links'><a class='toggle-docs-detail' href='#'>Hide details</a></div>
        <ul class='options-list'>";

        $tralr = "</ul></div>";

        foreach ($volData as $row2) {
            $data .= $this->fullGroupMarkup($row2);

            // save the notes for the notes tab.
            if ($row2["Vol_Notes"] != "") {
                $this->vNotesMarkup[$row2["Vol_Code"]] = "<tr><td style='border-style:solid;'>" . $row2["Description"] . "</td><td colspan='3' style='border-style:solid;'><span class='noteTA' name='" . $this->Category_Code . "[" . $row2["Vol_Code"] . "]'>" . $row2["Vol_Notes"] . "</span></td></tr>";
            }
        }

        $data = $hdr . $data . $tralr;

        return $data;
    }

    public function fullGroupMarkup($rowArray, $deleteRecordCB = true) {

        // The <li> id must match the <h3><a> href.
        $liId = $this->Category_Code . "-" . $rowArray["Vol_Code"];
        $cbName = $this->Category_Code . "[cb][" . $rowArray["Vol_Code"] . "]";
        $cbId = $this->Category_Code . "_cb_" . $rowArray["Vol_Code"] . "";
        $ckd = "";
        $vStat = '';

        if ($rowArray["Vol_Status"] == 'a') {
            $ckd = "checked='checked'";
            $sty = " ui-state-active";
            $this->isTabChecked = true;
        } else if ($rowArray["Vol_Status"] == 'i') {
            $sty = " ui-state-active";
            $vStat = ' (Retired)';
        } else {
            $sty = " ui-state-default";
        }


        $data = "<li id='$liId' class='$this->Category_Code'>
    <div class='option-header$sty'>
        <span style='float:left; margin-top:5px;'><input type='checkbox' class='chkVolunteer' name='" . $cbName . "' id='" . $cbId . "' " . $ckd . " title='Check to join/retire this member to ".$rowArray["Description"]."' /></span>
        <h3 class='option-name' title='Click to show/hide details'><a href='#$liId'>" . $rowArray["Description"] . $vStat . "</a></h3>
    </div>";

        $data .= $this->groupDetailMarkup($rowArray, $deleteRecordCB) . "</li>";
        return $data;
    }

    public function skinnyGroupMarkup($rowArray) {

        $cbName = $this->Category_Code . "[cb][" . $rowArray["Vol_Code"] . "]";
        $cbId = $this->Category_Code . "_cb_" . $rowArray["Vol_Code"] . "";

        if ($rowArray["Vol_Status"] == 'a') {
            $ckd = "checked='checked'";
        } else {
            $ckd = "";
        }

        $data = "<input type='checkbox' class='chkVolunteer' style='display:none' name='" . $cbName . "' id='" . $cbId . "' " . $ckd . " />";

        $data .= $this->groupDetailMarkup($rowArray, FALSE);
        return $data;
    }

    protected function groupDetailMarkup(array $rowArray, $deleteRecordCB = TRUE) {

        $ipId = $this->Category_Code . "[ckdate][" . $rowArray["Vol_Code"] . "]";
        //$cbId = $this->Category_Code . "_cb_" . $rowArray["Vol_Code"] . "";
        $taId = $this->Category_Code . "[notes][" . $rowArray["Vol_Code"] . "]";
        //$dorId = $this->Category_Code . "[dcode][" . $rowArray["Vol_Code"] . "]";
        //$sdId = $this->Category_Code . "sdid" . $rowArray["Vol_Code"];
        //$edId = $this->Category_Code . "edid" . $rowArray["Vol_Code"];
        $tdId = $this->Category_Code . "[tdid][" . $rowArray["Vol_Code"] . "]";
        $tnId = $this->Category_Code . "[tnid][" . $rowArray["Vol_Code"] . "]";
        $rank = $this->Category_Code . "[rank][" . $rowArray["Vol_Code"] . "]";

        $ckDate = $rowArray["Vol_Check_Date"] == '' ? "" : date("m/d/Y", strtotime($rowArray["Vol_Check_Date"]));
        $stDate = $rowArray["Vol_Begin"] == '' ? "" : date("m/d/Y", strtotime($rowArray["Vol_Begin"]));
        $enDate = $rowArray["Vol_End"] == '' ? "" : date("m/d/Y", strtotime($rowArray["Vol_End"]));
        $trDate = $rowArray["Vol_Training_Date"] == '' ? "" : date("m/d/Y", strtotime($rowArray["Vol_Training_Date"]));
        $lastUpdated = $rowArray["Last_Updated"] == '' ? "" : date("m-d-Y", strtotime($rowArray["Last_Updated"]));

        $rankDisabled = "disabled='disabled'";
        $resetControlMarkup = "";
        $resetCbDisabled = "";

        // Do the delete record markup if desired.
        if ($deleteRecordCB === true) {
            $rankDisabled = "";

            // disable reset checkbox if no data for this volunteer type
            if ($stDate == "") {
                $resetCbDisabled = "disabled='disabled'";
            }
            $reset = $this->Category_Code . "[rs][" . $rowArray["Vol_Code"] . "]";
            $resetDialogStr = "Delete Type `" . $rowArray["Description"] . "` from this member  (Start & retirement dates are lost).";
            $resetControlMarkup = "<label for='$reset'>Reset to blank </label><input type='checkbox' name='$reset' $resetCbDisabled title='$resetDialogStr' class='hhk-delete-vol' />";
        }

        $data = "<div class='option-description' style='display: block;'>
    <table style='clear:left;'><tr>
    <th>Role</th>
    <th>Upd. By</th>
    <th>Upd. On</th>
    <th>Start</th>
    <th>End</th>
    <th>Train Date</th>
    <th>Trainer</th>
    <th>Contact Date</th>
    </tr><tr>
    <td><select name='" . $rank . "' $rankDisabled>" . $this->getRankOptions($rowArray["Vol_Rank"]) . "</select></td>
    <td style='vertical-align: middle;'>" . $rowArray["Updated_By"] . "</td>
    <td style='vertical-align: middle;'>" . $lastUpdated . "</td>
    <td style='vertical-align: middle;'>" . $stDate . "</td>
    <td style='vertical-align: middle;'>" . $enDate . "</td>
    <td><input type='text' class='ckdate' name='" . $tdId . "'  value='" . $trDate . "' size='10' /></td>
    <td><input type='text' name='" . $tnId . "'  value='" . $rowArray["Vol_Trainer"] . "' size='12' /></td>
    <td><input type='text' class='ckdate' name='" . $ipId . "'  value='" . $ckDate . "' size='10' /></td>
    </tr><tr><td style='text-align:left;'>" . $resetControlMarkup .
    "</td><td colspan='7'><textarea rows='1' cols='84' name='" . $taId . "'>" . $rowArray["Vol_Notes"] . "</textarea></td></tr></table></div>";

        return $data;
    }

    public static function getVolCategoryData(PDO $dbh, $id, $category_Code) {
        $paramList = array();

        $paramList[":idName"] = $id;
        $paramList[":catcode"] = $category_Code;

        $query = "call getVolCategoryCodes(:idName, :catcode);";

        $volStmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $volStmt->execute($paramList);
        $rows = $volStmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }


    public function saveVolCategory(PDO $dbh, $id, $volArray, $user) {

        $resultMessage = "";
        $volData = $this->getVolCategoryData($dbh, $id, $this->Category_Code);

        foreach ($volData as $row2) {

            // The client always sets the CheckDate control, so we use it to determine if this vol group is even on hte form.
            if (isset($volArray['ckdate'][$row2["Vol_Code"]]) === FALSE) {
                continue;
            }

            $nvRS = new NameVolunteerRS();
            $nvRS->idName->setStoredVal($id);
            $nvRS->Vol_Category->setStoredVal($this->Category_Code);
            $nvRS->Vol_Code->setStoredVal($row2["Vol_Code"]);

            $rows = EditRS::select($dbh, $nvRS, array($nvRS->idName, $nvRS->Vol_Category, $nvRS->Vol_Code));

            $isNewRS = TRUE;
            if (count($rows) > 0) {
                // existing record
                EditRS::loadRow($rows[0], $nvRS);
                $isNewRS = FALSE;
            } else {
                // New record.
                $nvRS = new NameVolunteerRS();
                $nvRS->idName->setNewVal($id);
                $nvRS->Vol_Category->setNewVal($this->Category_Code);
                $nvRS->Vol_Code->setNewVal($row2["Vol_Code"]);
            }

            // Set Values from page

            // Check Date
            if (isset($volArray["ckdate"][$row2["Vol_Code"]])) {
                $nvRS->Vol_Check_Date->setNewVal(trim(filter_var($volArray["ckdate"][$row2["Vol_Code"]], FILTER_SANITIZE_STRING)));
            }

            // Training Date
            if (isset($volArray["tdid"][$row2["Vol_Code"]])) {
                $nvRS->Vol_Training_Date->setNewVal(trim(filter_var($volArray["tdid"][$row2["Vol_Code"]], FILTER_SANITIZE_STRING)));
            }

            // Rank/Role
            if (isset($volArray['rank'][$row2["Vol_Code"]])) {
                if (isset($this->rankOptions[$volArray['rank'][$row2["Vol_Code"]]])) {
                    $nvRS->Vol_Rank->setNewVal($volArray['rank'][$row2["Vol_Code"]]);
                } else {
                    $nvRS->Vol_Rank->setNewVal(VolRank::Member);
                }
            }

            // Vol Notes
            if (isset($volArray['notes'][$row2["Vol_Code"]])) {
                $nvRS->Vol_Notes->setNewVal($volArray['notes'][$row2["Vol_Code"]]);
            }

            // Trainer
            if (isset($volArray["tnid"][$row2["Vol_Code"]])) {
                $nvRS->Vol_Trainer->setNewVal($volArray["tnid"][$row2["Vol_Code"]]);
            }

            // Updated by and last update
            $nvRS->Updated_By->setNewVal($user);
            $nvRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $reset = false;
            if (isset($volArray["rs"][$row2["Vol_Code"]])) {
                $reset = filter_var($volArray["rs"][$row2["Vol_Code"]], FILTER_VALIDATE_BOOLEAN);
            }

            // Insert??
            if ($row2["Vol_Status"] == "z" && isset($volArray["cb"][$row2["Vol_Code"]])) {
                if (isset($volArray["cb"][$row2["Vol_Code"]]) && $volArray["cb"][$row2["Vol_Code"]] == "on") {
                    // Just add a vol type to this member.
                    $nvRS->Vol_Begin->setNewVal(date('Y/m/d'));
                    $nvRS->Vol_Status->setNewVal(VolStatus::Active);

                    if ($isNewRS) {
                        EditRS::insert($dbh, $nvRS);
                        VolunteerLog::writeInsert($dbh, $nvRS, $id, $user);
                    }
//
                    // Check if we also must join another category/code
                    if ($this->alsoCheck != "") {
                        $parts = explode(".", $this->alsoCheck);
                        if (count($parts) == 2) {
                            $acNvRS = new NameVolunteerRS();
                            $acNvRS->idName->setStoredVal($id);
                            $acNvRS->Vol_Category->setStoredVal($parts[0]);
                            $acNvRS->Vol_Code->setStoredVal($parts[1]);

                            $rs = EditRS::select($dbh, $acNvRS, array($acNvRS->idName, $acNvRS->Vol_Category, $acNvRS->Vol_Code));

                            if (count($rs) > 0) {
                                // Update
                                EditRS::loadRow($rs[0], $acNvRS);
                                if ($acNvRS->Vol_Status->getStoredVal() != VolStatus::Active) {
                                    $acNvRS->Vol_Begin->setNewVal(date('Y/m/d'));
                                    $acNvRS->Vol_End->setNewVal('');
                                    $acNvRS->Vol_Status->setNewVal(VolStatus::Active);
                                    $acNvRS->Updated_By->setNewVal($user);
                                    $acNvRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

                                    $rc = EditRS::update($dbh, $acNvRS, array($acNvRS->idName, $acNvRS->Vol_Category, $acNvRS->Vol_Code));
                                    if ($rc > 0) {
                                        VolunteerLog::writeUpdate($dbh, $acNvRS, $id, $user);
                                    }
                                }
                            } else {
                                // Insert
                                $acNvRS = new NameVolunteerRS();
                                $acNvRS->idName->setNewVal($id);
                                $acNvRS->Vol_Category->setNewVal($parts[0]);
                                $acNvRS->Vol_Code->setNewVal($parts[1]);
                                $acNvRS->Vol_Begin->setNewVal(date('Y/m/d'));
                                $acNvRS->Vol_Rank->setNewVal(VolRank::Member);
                                $acNvRS->Vol_Status->setNewVal(VolStatus::Active);
                                $acNvRS->Updated_By->setNewVal($user);
                                $acNvRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                                EditRS::insert($dbh, $acNvRS);
                                VolunteerLog::writeInsert($dbh, $acNvRS, $id, $user);

                            }
                        }
                    }

                    // And done.
                    $resultMessage .= $this->title . "-". $row2["Description"] . " Added.  ";
                }
            } else if ($reset) {
            // delete
                EditRS::delete($dbh, $nvRS, array($nvRS->idName, $nvRS->Vol_Category, $nvRS->Vol_Code));
                VolunteerLog::writeDelete($dbh, $nvRS, $id, $user);
                $resultMessage .= $this->title . "-". $row2["Description"] . " Deleted.  ";

            } else if ($row2["Vol_Status"] != "z" && $isNewRS === FALSE) {
            // Update?

                if (isset($volArray["cb"][$row2["Vol_Code"]])) {

                    if ($row2["Vol_Status"] == VolStatus::Retired && $volArray["cb"][$row2["Vol_Code"]] == "on") {
                        // Status back to ON
                        $nvRS->Vol_Begin->setNewVal(date('Y/m/d'));
                        $nvRS->Vol_Status->setNewVal(VolStatus::Active);
                        $nvRS->Vol_End->setNewVal('');
                    }

                } else if ($row2["Vol_Status"] == VolStatus::Active && isset($volArray["cb"][$row2["Vol_Code"]]) === FALSE) {
                    // status - disabled.  update existing vol record to "inactive"
                    $nvRS->Vol_End->setNewVal(date('Y/m/d'));    // end date - default today if not filled in.
                    $nvRS->Vol_Status->setNewVal(VolStatus::Retired);

                }

                $rc = EditRS::update($dbh, $nvRS, array($nvRS->idName, $nvRS->Vol_Category, $nvRS->Vol_Code));
                if ($rc > 0) {
                    VolunteerLog::writeUpdate($dbh, $nvRS, $id, $user);
                    $resultMessage .= $this->title . "-". $row2["Description"] . " Updated.  ";
                }

            }
        }
        return $resultMessage;
    }

}

