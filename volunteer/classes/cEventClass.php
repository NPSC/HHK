<?php
/**
 * cEventClass.php
 *
 * @category
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class cEventClass {

    private $id;
    private $title = "";
    private $allDay = 0;
    private $start = "";
    private $end = "";
    private $url = "";
    private $className = "";
    private $editable = 0;
    private $source = "";
    private $idName = 0;
    private $idName2 = 0;
    private $shellId = 0;
    private $volCat = "";
    private $volCode = "";
    //private $volCatCode = "";
    private $description = "";
    private $memberName = "";
    private $memberName2 = "";
    private $relieve = 0;
    private $shell = 0;
    private $fixed = 0;
    private $locked = 0;
    private $backgroundColor = "";
    private $borderColor = "";
    private $textColor = "";
    private $eventColor = "";
    private $volDescription = "";
    private $repeater = 0;
    private $reptdUnits = "";
    private $reptdQty = "";
    private $reptId = 0;
    private $reptDay = 0;
    private $reptWeek = 1;
    private $logTime = 0;
    private $timeLogged = 0;
    //private $emailDelete = 0;
    private $showEmailDelete = 0;
    private $hideAddMember = 0;
    private $ShowAddAll = 0;
    private $selectAddAll = 0;


    // ------------------------
    function set_ShowAddAll($v) {
        if ($v == 'y') {
            $this->ShowAddAll = 1;
        } else {
            $this->ShowAddAll = 0;
        }
    }
    function get_ShowAddAll() {
        return $this->ShowAddAll;
    }
    // ------------------------
    function set_hideAddMember($v) {
        if ($v == 'y') {
            $this->hideAddMember = 1;
        } else {
            $this->hideAddMember = 0;
        }
    }
    function get_hideAddMember() {
        return $this->hideAddMember;
    }
// ------------------------
    function set_selectAddAll($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->selectAddAll = 1;
        } else {
            $this->selectAddAll = 0;
        }
    }
    function get_selectAddAll() {
        return $this->selectAddAll;
    }

    //----------------------------------------
    function set_logTime($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->logTime = 1;
        } else {
            $this->logTime = 0;
        }
    }
    function get_logTime() {
        return $this->logTime;
    }
    //----------------------------------------
    function set_timeLogged($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->timeLogged = 1;
        } else {
            $this->timeLogged = 0;
        }
    }
    function get_timeLogged() {
        return $this->timeLogged;
    }
    //----------------------------------------
    function set_reptDay($v) {
        $this->reptDay = $v;
    }
    function get_reptDay() {
        return $this->reptDay;
    }
    //----------------------------------------
    function set_reptWeek($v) {
        $this->reptWeek = $v;
    }
    function get_reptWeek() {
        return $this->reptWeek;
    }
    //----------------------------------------
    function set_repeater($v) {
        $this->repeater = $v;
    }
    function get_repeater() {
        return $this->repeater;
    }
    //----------------------------------------
    function set_reptdUnits($v) {
        $this->reptdUnits = $v;
    }
    function get_reptdUnits() {
        return $this->reptdUnits;
    }
    //----------------------------------------
    function set_reptdQty($v) {
        $this->reptdQty = $v;
    }
    function get_reptdQty() {
        return $this->reptdQty;
    }
    //----------------------------------------
    function set_reptId($v) {
        $this->reptId = $v;
    }
    function get_reptId() {
        return $this->reptId;
    }
    //----------------------------------------
    function set_shellId($v) {
        $this->shellId = $v;
    }
    function get_shellId() {
        return $this->shellId;
    }
    //----------------------------------------
    function set_volDescription($v) {
        $this->volDescription = $v;
    }
    function get_volDescription() {
        return $this->volDescription;
    }
    //----------------------------------------
    function set_eventColor($v) {
        $this->eventColor = $v;
    }
    function get_eventColor() {
        return $this->eventColor;
    }
    //----------------------------------------
    function set_backgroundColor($v) {
        $this->backgroundColor = $v;
    }
    function get_backgroundColor() {
        return $this->backgroundColor;
    }
    //----------------------------------------
    function set_borderColor($v) {
        $this->borderColor = $v;
    }
    function get_borderColor() {
        return $this->borderColor;
    }
    //----------------------------------------
    function set_textColor($v) {
        $this->textColor = $v;
    }
    function get_textColor() {
        return $this->textColor;
    }

// ------------------------
    function set_shell($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->shell = 1;
        } else {
            $this->shell = 0;
        }
    }
    function get_shell() {
        return $this->shell;
    }
// ------------------------
    function set_fixed($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->fixed = 1;
        } else {
            $this->fixed = 0;
        }
    }
    function get_fixed() {
        return $this->fixed;
    }
// ------------------------
    function set_locked($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->locked = 1;
        } else {
            $this->locked = 0;
        }
    }
    function get_locked() {
        return $this->locked;
    }
// ------------------------
    function set_memberName($v) {
        $this->memberName = $v;
    }
    function get_memberName() {
        return $this->memberName;
    }
// ------------------------
    function set_memberName2($v) {
        $this->memberName2 = $v;
    }
    function get_memberName2() {
        return $this->memberName2;
    }
// ------------------------
    function set_description($v) {
        $this->description = $v;
    }
    function get_description() {
        return $this->description;
    }
// ------------------------
    function set_idName($v) {
        if (is_null($v) || $v == "") {
            $v = 0;
        }

        $this->idName = $v;
    }
    function get_idName() {
        return $this->idName;
    }
// ------------------------
    function set_idName2($v) {
        if (is_null($v) || $v == "") {
            $v = 0;
        }
        $this->idName2 = $v;
    }
    function get_idName2() {
        return $this->idName2;
    }
// ------------------------
    function set_volCat($v) {
        $this->volCat = $v;
    }
    function get_volCat() {
        return $this->volCat;
    }
// ------------------------
    function set_volCode($v) {
        $this->volCode = $v;
    }
    function get_volCode() {
        return $this->volCode;
    }
// -----------------------
    function set_id($v) {
        if (is_null($v)) {
            $v = "";
        }
        $this->id = $v;
    }
    function get_id() {
        return $this->id;
    }
// ------------------------
    function set_title($v) {
        $this->title = $v;
    }
    function get_title() {
        return $this->title;
    }
// ------------------------
//    function set_emailDelete($v) {
//        if ($v == '1' || $v === true) {
//            $this->emailDelete = 1;
//        } else {
//            $this->emailDelete = 0;
//        }
//    }
//    function get_emailDelete() {
//        return $this->emailDelete;
//    }
    // ------------------------
    function set_showEmailDelete($v) {
        if (strtolower($v) == 'y') {
            $this->showEmailDelete = 1;
        } else {
            $this->showEmailDelete = 0;
        }
    }
    function get_showEmailDelete() {
        return $this->showEmailDelete;
    }
// ------------------------
    function set_allDay($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->allDay = 1;
        } else {
            $this->allDay = 0;
        }
    }
    function get_allDay() {
        return $this->allDay;
    }
// ------------------------
    function set_relieve($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->relieve = 1;
        } else {
            $this->relieve = 0;
        }
    }
    function get_relieve() {
        return $this->relieve;
    }
// ------------------------
    function set_start($str) {
        $dt = new DateTime($str);

        //$s = date('c', strtotime($str));
        $this->start = $dt->format('c');
    }
    function get_start() {
        return $this->start;
    }
// ------------------------
    function set_end($str) {
        $dt = new DateTime($str);

//        $s = date('c', strtotime($str));
        $this->end = $dt->format('c');
    }
    function get_end() {
        return $this->end;
    }
// ------------------------
    function set_url($v) {
        $this->url = $v;
    }
    function get_url() {
        return $this->url;
    }
// ------------------------
    function set_className($v) {
        $this->className = $v;
    }
    function get_className() {
        return $this->className;
    }
// ------------------------
    function set_editable($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->editable = 1;
        } else {
            $this->editable = 0;
        }
    }
    function get_editable() {
        return $this->editable;
    }
// ------------------------
    function set_source($v) {
        $this->source = $v;
    }
    function get_source() {
        return $this->source;
    }


    public function LoadFromGetString($getArray) {

        if (isset($getArray["title"])) {
            $this->set_title(filter_var($getArray["title"], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
        }
        if (isset($getArray["desc"])) {
            $this->set_description(filter_var($getArray["desc"], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
        }
        if (isset($getArray["start"])) {
            $this->set_start( filter_var($getArray["start"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["end"])) {
            $this->set_end(filter_var($getArray["end"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["allDay"])) {
            $this->set_allDay( filter_var($getArray["allDay"], FILTER_VALIDATE_BOOLEAN));
        }
//        if (isset($getArray["emailDel"])) {
//            $this->set_emailDelete( filter_var($getArray["emailDel"], FILTER_VALIDATE_BOOLEAN));
//        }
        if (isset($getArray["addAll"])) {
            $this->set_selectAddAll( filter_var($getArray["addAll"], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($getArray["vcc"])) {
            $v = $getArray["vcc"];
            $prms = UserCategories::splitVcc($v);
            if (count($prms) >= 2) {
                $this->set_volCat($prms[0]);
                $this->set_volCode($prms[1]);
            }
        }
        if (isset($getArray["rlf"])) {
            $this->set_relieve(filter_var($getArray["rlf"], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($getArray["id"])) {
            $this->set_id( filter_var($getArray["id"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["nid"])) {
            $this->set_idname( filter_var($getArray["nid"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["nid2"])) {
            $this->set_idname2( filter_var($getArray["nid2"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["className"])) {
            $this->set_className( filter_var($getArray["className"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["URL"])) {
            $this->set_url( filter_var($getArray["URL"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["source"])) {
            $this->set_source( filter_var($getArray["source"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["logtime"])) {
            $this->set_logTime(filter_var($getArray["logtime"], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($getArray["shl"])) {
            $this->set_shell(filter_var($getArray["shl"], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($getArray["shlid"])) {
            $this->set_shellId(filter_var($getArray["shlid"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["rptr"])) {
            $this->set_repeater(filter_var($getArray["rptr"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["rptrQty"])) {
            $this->set_reptdQty(filter_var($getArray["rptrQty"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["rptid"])) {
            $this->set_reptId(filter_var($getArray["rptid"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["rptrday"])) {
            $this->set_reptDay(filter_var($getArray["rptrday"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["rptrweek"])) {
            $this->set_reptWeek(filter_var($getArray["rptrweek"], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($getArray["rptrUnit"])) {
            $this->set_reptdUnits( filter_var($getArray["rptrUnit"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["fix"])) {
            $this->set_fixed(filter_var($getArray["fix"], FILTER_VALIDATE_BOOLEAN));
        }
//        if (isset($getArray["lkd"])) {
//            $this->set_locked(filter_var($getArray["lkd"], FILTER_VALIDATE_BOOLEAN));
//        }

    }

    public function fillEventsArray($ar = 0) {
        $eventsArray = array(
            "id" =>  $this->get_id(),

            'start'=> $this->get_start()
         );

        if ($this->get_timeLogged() == 1) {
            $tabIcon = "(L) ";
            $eventsArray['backgroundColor'] = "#4F7F4F";
        } else {
            $tabIcon = "";
            if ($this->get_backgroundColor() != "") {
                $eventsArray['backgroundColor'] = $this->get_backgroundColor ();
            }
        }
        $eventsArray['title'] = $tabIcon . $this->get_title();

        $eventsArray['allDay'] = $this->get_allDay();
        $eventsArray['showEmailDel'] = $this->get_showEmailDelete();
        $eventsArray['hideAddMem'] = $this->get_hideAddMember();
        $eventsArray['addAll'] = $this->get_selectAddAll();
        $eventsArray['showAddAll'] = $this->get_ShowAddAll();
        $eventsArray['fix'] = $this->get_fixed();
        $eventsArray['lkd'] = $this->get_locked();
        $eventsArray['rlf'] = $this->get_relieve();
        $eventsArray['shlid'] = $this->get_shellId();
        $eventsArray['rptid'] = $this->get_reptId();
        $eventsArray["timelogged"] = $this->get_timeLogged();

        if ($this->get_end() != "") {
            $eventsArray['end'] = $this->get_end();
        }

        if ($this->get_description() != "") {
            $eventsArray['desc'] = $this->get_description();
        }

        if ($this->get_idName() > 0) {
            $eventsArray['nid'] = $this->get_idName();
        }

        if ($this->get_idName2() > 0) {
            $eventsArray['nid2'] = $this->get_idName2();
        }

        if ($this->get_volCode() != "" || $this->get_volCat() != "") {
            $eventsArray['vcc'] = UserCategories::combineVcc($this->get_volCat(), $this->get_volCode());
        }

        if ($this->get_memberName() != "") {
            $eventsArray['mName'] = $this->get_memberName();
        }

        if ($this->get_memberName2() != "") {
            $eventsArray['mName2'] = $this->get_memberName2();
        }

//        if ($this->get_className() != "")
//            $eventsArray['className'] = $this->get_className ();

        if ($this->get_url() != "") {
            $eventsArray['URL'] = $this->get_url();
        }

        if ($this->get_eventColor() != "") {
            $eventsArray['color'] = $this->get_eventColor();
        }

        if ($this->get_borderColor() != "") {
            $eventsArray['borderColor'] = $this->get_borderColor();
        }

        if ($this->get_textColor() != "") {
            $eventsArray['textColor'] = $this->get_textColor();
        }

        if ($this->get_volDescription() != "") {
            $eventsArray["vdesc"] = $this->get_volDescription();
        }

        return $eventsArray;
    }

    public function LoadFromDB($r) {

        if (!is_null($r)) {
            $this->set_start($r["E_Start"]);
            $this->set_end($r["E_End"]);
            $this->set_title(stripslashes($r["E_Title"]));
            $this->set_id( $r["idmcalendar"] );
            $this->set_idName($r["idName"]);
            $this->set_idName2($r["idName2"]);
            $this->set_reptId($r["E_Rpt_Id"]);
            $this->set_description(stripslashes($r["E_Description"]));

            $this->set_allDay($r["E_AllDay"]);
            $this->set_editable($r["E_Editable"]);
            $this->set_locked($r["E_Locked"]);
            $this->set_relieve($r["E_Take_Overable"]);
            $this->set_shell($r["E_Shell"]);
            $this->set_shellId($r["E_Shell_Id"]);
            $this->set_fixed($r["E_Fixed_In_Time"]);
            if ($r["E_Status"] == Vol_Calendar_Status::Logged) {
                $this->set_timeLogged(1);
            } else {
                 $this->set_timeLogged(0);
            }

            if ($r["E_ClassName"] != "") {
                $this->set_className($r["E_ClassName"]);
            }

            if ($r["E_Vol_Code"] != "") {
                $this->set_volCode($r["E_Vol_Code"]);
            }

            if ($r["E_Vol_Category"] != "") {
                $this->set_volCat($r["E_Vol_Category"]);
            }

            if ($r["E_URL"] != "") {
                $this->set_url($r["E_URL"]);
            }


            if ($this->get_idName() != 0) {
                if (isset($r["First"]) && isset($r["Last"])) {
                    $this->set_memberName($r["First"] . " " . stripslashes($r["Last"]));
                }
            }

            if ($this->get_idName2() != 0) {
                if (isset($r["First2"]) && isset($r["Last2"])) {
                    $this->set_memberName2($r["First2"] . " " . stripslashes($r["Last2"]));
                }
            }

            if (isset($r["Vol_Description"]) && $r["Vol_Description"] != "") {
                $this->set_volDescription(stripslashes($r["Vol_Description"]));
            }

            if (isset($r["Show_Email_Delete"])) {
                $this->set_showEmailDelete($r["Show_Email_Delete"]);
            }
            if (isset($r["Hide_Add_Members"])) {
                $this->set_hideAddMember($r["Hide_Add_Members"]);
            }
            if (isset($r["Show_AllCategory"])) {
                $this->set_ShowAddAll($r["Show_AllCategory"]);
            }
            if (isset($r["E_Show_All"])) {
                $this->set_selectAddAll($r["E_Show_All"]);
            }
        }
    }

    function set_volCatCode($v) {
        if (!is_null($v)) {
            $prms = UserCategory::splitVcc($v);
            if (count($prms) >= 2) {
                $this->set_volCat($prms[0]);
                $this->set_volCode($prms[1]);
            }
        }
    }

    function get_volCatCode() {
        if ($this->get_volCat() != "" && $this->get_volCode() != "") {
            return UserCategory::combineVcc($this->get_volCat(), $this->get_volCode());
        } else {
            return "";
        }
    }


    public function getEventColorsPDO(PDO $dbh) {
        // Substitute = backgroundColor,TextColor

        if ($this->get_volCat() != "" && $this->get_volCode() != "") {
            $query = "select Substitute from gen_lookups where Table_Name = :tname and Code = :cde;";

            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(":tname" => $this->get_volCat(), ":cde" => $this->get_volCode()));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) == 1) {

                $parts = explode(",", $rows[0]["Substitute"]);
                if (count($parts) == 2) {
                    $this->set_eventColor(trim($parts[0]));
                    $this->set_textColor(trim($parts[1]));
                    $this->set_backgroundColor(trim($parts[0]));
                    $this->set_borderColor(trim($parts[0]));
                } else {
                    $this->set_eventColor("blue");
                    $this->set_textColor("white");
                    $this->set_backgroundColor("blue");
                    $this->set_borderColor("blue");

                }
            }
        }
    }


}

