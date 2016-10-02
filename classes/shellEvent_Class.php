<?php
/**
 * shellEvent_Class.php
 *
 * @category  Member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of shellEvent_Class
 *
 * @author Eric Crane
 */
class shellEvent_Class {

    private $idshell_events;
    private $Title = "";
    private $Description = "";
    private $Vol_Description = "";
    private $Vol_Cat = "";
    private $Vol_Code = "";
    private $Time_Start;
    private $Time_End;
    private $Date_Start;
    private $Duration_Code = "";
    private $Class_Name = "";
    private $URL = "";
    private $days = array("Sun"=>0, "Mon"=>0, "Tue"=>0, "Wed"=>0, "Thu"=>0, "Fri"=>0, "Sat"=>0);
    private $allDay = 0;
    private $Skip_Holidays = 0;
    //private $ticks_start = 0;
    //private $ticks_end = 0;
    private $backgroundColor = "transparent";
    private $borderColor = "";
    private $textColor = "black";
    private $eventColor = "";
    private $idRoom = "";

//----------------------------------------
    function set_idRoom($v) {
        $this->idRoom = $v;
    }
    function get_idRoom() {
        return $this->idRoom;
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
//----------------------------------------
    function set_idshell_events($v) {
        $this->idshell_events = $v;
    }
    function get_idshell_events() {
        return $this->idshell_events;
    }
//----------------------------------------
    function set_Title($v) {
        $this->Title = $v;
    }
    function get_Title() {
        return $this->Title;
    }
//----------------------------------------
    function set_Description($v) {
        $this->Description = $v;
    }
    function get_Description() {
        return $this->Description;
    }
//----------------------------------------
    function set_Vol_Description($v) {
        $this->Vol_Description = $v;
    }
    function get_Vol_Description() {
        return $this->Vol_Description;
    }
//----------------------------------------
    function set_Vol_Cat($v) {
        $this->Vol_Cat = $v;
    }
    function get_Vol_Cat() {
        return $this->Vol_Cat;
    }
//----------------------------------------
    function set_Vol_Code($v) {
        $this->Vol_Code = $v;
    }
    function get_Vol_Code() {
        return $this->Vol_Code;
    }
//----------------------------------------
    function set_Time_Start($v) {
        $this->Time_Start = date("H:i:s", strtotime($v));
    }
    function get_Time_Start() {
        return $this->Time_Start;
    }
//----------------------------------------
    function set_Time_End($v) {
        $this->Time_End = date("H:i:s", strtotime($v));
    }
    function get_Time_End() {
        return $this->Time_End;
    }
//----------------------------------------
    function set_Date_Start($v) {
        $this->Date_Start = date("c", strtotime($v));
    }
    function get_Date_Start() {
        return $this->Date_Start;
    }
//----------------------------------------
    function set_Duration_Code($v) {
        $this->Duration_Code = $v;
    }
    function get_Duration_Codes() {
        return $this->Duration_Code;
    }
//----------------------------------------
    function set_Class_Name($v) {
        $this->Class_Name = $v;
    }
    function get_Class_Name() {
        return $this->Class_Name;
    }
//----------------------------------------
    function set_URL($v) {
        $this->URL = $v;
    }
    function get_URL() {
        return $this->URL;
    }
//----------------------------------------
    function set_days($v) {
        $this->days = $v;
    }
    function get_days() {
        return $this->days;
    }
//----------------------------------------
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
//----------------------------------------
    function set_Skip_Holidays($v) {
        $w = ord($v);
        if ($v == '1' || $v === true || $w == 1) {
            $this->Skip_Holidays = 1;
        } else {
            $this->Skip_Holidays = 0;
        }
    }
    function get_Skip_Holidays() {
        return $this->Skip_Holidays;
    }

   function readDbBit($dbBit) {
        $w = ord($dbBit);
        if ($dbBit == '1' || $w == 1) {
            $v = 1;
        } else {
            $v = 0;
        }
        return $v;

    }

    public function loadFromDbRow($v) {

        $this->set_idshell_events($v["idShell"]);
        $this->set_Title($v["Title"]);
        $this->set_Description($v["Description"]);
        if (isset($v["Vol_Description"])) {
            $this->set_Vol_Description($v["Vol_Description"]);
        }
        $this->set_Vol_Cat($v["Vol_Cat"]);
        $this->set_Vol_Code($v["Vol_Code"]);
        $this->set_Time_Start($v["Time_Start"]);
        $this->set_Time_End($v["Time_End"]);
        $this->set_Date_Start($v["Date_Start"]);
        $this->set_Duration_Code($v["Duration_Code"]);
        $this->set_Skip_Holidays($v["Skip_Holidays"]);
        $this->set_allDay($v["AllDay"]);
        $this->set_Class_Name($v["Class_Name"]);
        $this->set_URL($v["URL"]);
        $this->set_borderColor($v["Shell_Color"]);

        if (isset($v["idRoom"])) {
            $this->set_idRoom($v["idRoom"]);
        }

        $wd = array();
        $wd["Sun"] = $this->readDbBit($v["Sun"]);
        $wd["Mon"] = $this->readDbBit($v["Mon"]);
        $wd["Tue"] = $this->readDbBit($v["Tue"]);
        $wd["Wed"] = $this->readDbBit($v["Wed"]);
        $wd["Thu"] = $this->readDbBit($v["Thu"]);
        $wd["Fri"] = $this->readDbBit($v["Fri"]);
        $wd["Sat"] = $this->readDbBit($v["Sat"]);

        $this->set_days($wd);


    }

    public static function fillBlankEventArray($st, $ed, $eid) {
        $eventsArray = array(
            "id" =>  "b" . $eid,
            "title"=> ""
         );


        $eventsArray['start'] = date_format(date_create($st), "c");

        $eventsArray['end'] = date_format(date_create($ed), "c");

        $eventsArray["shlid"] = 0;
        $eventsArray['allDay'] = 1;
        $eventsArray['shl'] = 1;

        $eventsArray['backgroundColor'] = "transparent";

        $eventsArray['borderColor'] = "grey";

        $eventsArray['textColor'] = "black";

        $eventsArray['desc'] = "";

        $eventsArray['className'] = "hhk-spacer";

        return $eventsArray;

    }

    public function fillEventArray($st, $ed, $eid) {
        $eventsArray = array(
            "id" =>  "s" . $eid,
            "title"=> $this->get_Title(),
         );


        $eventsArray['start'] = date_format(date_create($st), "c");

        $eventsArray['end'] = date_format(date_create($ed), "c");

        $eventsArray["shlid"] = $this->get_idshell_Events();
        $eventsArray['allDay'] = $this->get_allDay();
        $eventsArray['shl'] = 1;
        $eventsArray['fix'] = 0;
        $eventsArray['hideAddMem'] = 0;

        $eventsArray['showAddAll'] = 0;

        if ($this->get_backgroundColor() != "")
            $eventsArray['backgroundColor'] = $this->get_backgroundColor ();

        if ($this->get_borderColor() != "")
            $eventsArray['borderColor'] = $this->get_borderColor ();

         if ($this->get_textColor() != "")
            $eventsArray['textColor'] = $this->get_textColor ();


        if ($this->get_Description() != "")
            $eventsArray['desc'] = $this->get_Description();

        if ($this->get_Vol_Description() != "")
            $eventsArray['vdesc'] = $this->get_Vol_Description();

        if ($this->get_Vol_Code() != "" || $this->get_Vol_Cat() != "")
            $eventsArray['vcc'] = $this->get_Vol_Cat() . "|" .$this->get_Vol_Code();

        if ($this->get_Class_Name() != "")
            $eventsArray['className'] = $this->get_Class_Name();

//        if ($this->get_URL() != "")
//            $eventsArray['URL'] = $this->get_URL ();

        return $eventsArray;

    }


}
?>
