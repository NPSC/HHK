<?php

/**
 * fbUserClass.php
 *
 * @category  Member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of fbUserClass
 *
 * @author Eric Crane
 */
class fbUserClass {

    private $fbid = "";
    private $fn;
    private $ln;
    private $a1;
    private $a2;
    private $cy;
    private $st;
    private $zp;
    private $ph;
    private $em;
    private $fun;
    private $nid = 0;
    private $status;
    private $timestamp;
    private $apprBy;
    private $apprDate;
    private $pifhUsername;
    private $password;
    private $accessCode;
    private $inst;  // secondary instruction from browser.

    function set_inst($v) {
        $this->inst = $v;
    }

    function get_inst() {
        return $this->inst;
    }

    //--------------------------
    function set_accessCode($v) {
        $this->accessCode = $v;
    }

    function get_accessCode() {
        return $this->accessCode;
    }

    //--------------------------
    function set_password($v) {
        $this->password = $v;
    }

    function get_password() {
        return $this->password;
    }

    //--------------------------
    function set_pifhUsername($v) {
        $this->pifhUsername = strtolower($v);
    }

    function get_pifhUsername() {
        return $this->pifhUsername;
    }

    //--------------------------
    function set_status($v) {
        $this->status = $v;
    }

    function get_status() {
        return $this->status;
    }

    //--------------------------
    function set_timestamp($v) {
        $this->timestamp = $v;
    }

    function get_timestamp() {
        return $this->timestamp;
    }

    //--------------------------
    function set_apprBy($v) {
        $this->apprBy = $v;
    }

    function get_apprBy() {
        return $this->apprBy;
    }

    //--------------------------
    function set_apprDate($v) {
        $this->apprDate = $v;
    }

    function get_apprDate() {
        return $this->apprDate;
    }

    //--------------------------
    function set_nid($v) {
        if (is_null($v) || $v == "" || $v < 0)
            $this->nid = 0;
        else
            $this->nid = $v;
    }

    function get_nid() {
        return $this->nid;
    }

    //--------------------------
    function set_fbid($v) {
        $this->fbid = $v;
    }

    function get_fbid() {
        return $this->fbid;
    }

    //--------------------------
    function set_fn($v) {
        $this->fn = $v;
    }

    function get_fn() {
        return $this->fn;
    }

    //--------------------------
    function set_ln($v) {
        $this->ln = $v;
    }

    function get_ln() {
        return $this->ln;
    }

    //--------------------------
    function set_a1($v) {
        $this->a1 = $v;
    }

    function get_a1() {
        return $this->a1;
    }

    //--------------------------
    function set_a2($v) {
        $this->a2 = $v;
    }

    function get_a2() {
        return $this->a2;
    }

    //--------------------------
    function set_cy($v) {
        $this->cy = $v;
    }

    function get_cy() {
        return $this->cy;
    }

    //--------------------------
    function set_st($v) {
        $this->st = $v;
    }

    function get_st() {
        return $this->st;
    }

    //--------------------------
    function set_zp($v) {
        $this->zp = $v;
    }

    function get_zp() {
        return $this->zp;
    }

    //--------------------------
    function set_ph($v) {
        $this->ph = $v;
    }

    function get_ph() {
        return $this->ph;
    }

    //--------------------------
    //--------------------------
    function set_em($v) {
        $this->em = $v;
    }

    function get_em() {
        return $this->em;
    }

    //--------------------------
    //--------------------------
    function set_fun($v) {
        $this->fun = $v;
    }

    function get_fun() {
        return $this->fun;
    }

    //--------------------------

    public function loadFromArray($getArray) {

        if (isset($getArray["fid"])) {
            $this->set_fbid(filter_var($getArray["fid"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["fn"])) {
            $this->set_fn(filter_var($getArray["fn"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["a1"])) {
            $this->set_a1(filter_var($getArray["a1"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["a2"])) {
            $this->set_a2(filter_var($getArray["a2"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["ln"])) {
            $this->set_ln(filter_var($getArray["ln"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["cy"])) {
            $this->set_cy(filter_var($getArray["cy"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["st"])) {
            $this->set_st(filter_var($getArray["st"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["zp"])) {
            $this->set_zp(filter_var($getArray["zp"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["ph"])) {
            $this->set_ph(filter_var($getArray["ph"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["em"])) {
            $this->set_em(filter_var($getArray["em"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["fun"])) {
            $this->set_fun(filter_var($getArray["fun"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["pun"])) {
            $this->set_pifhUsername(filter_var($getArray["pun"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["pw"])) {
            $this->set_password(filter_var($getArray["pw"], FILTER_SANITIZE_STRING));
        }
        if (isset($getArray["nid"])) {
            $this->set_nid(filter_var($getArray["nid"], FILTER_SANITIZE_INT));
        }
        if (isset($getArray["inst"])) {
            $this->set_inst(filter_var($getArray["inst"], FILTER_SANITIZE_INT));
        }
    }

    public function __construct($FB_Id) {
        $this->set_fbid($FB_Id);
    }

    /*
     *  readFromDB
     *  read previously set fbid from the database.
     *
     */

    public function readFromDB(PDO $dbh, $whereStr) {
        $evt = array();

        $r = $this->selectRow($dbh, $whereStr);

        if (!is_null($r)) {
            $this->set_a1($r["fb_Address_1"]);
            $this->set_a2($r["fb_Address_2"]);
            $this->set_cy($r["fb_City"]);
            $this->set_st($r["fb_State"]);
            $this->set_zp($r["fb_Zip"]);
            $this->set_fn($r["fb_First_Name"]);
            $this->set_ln($r["fb_Last_Name"]);
            $this->set_ph($r["fb_Phone"]);
            $this->set_em($r["fb_Email"]);
            $this->set_nid($r["idName"]);
            $this->set_status($r["Status"]);
            $this->set_fun($r["fb_username"]);
            $this->set_timestamp($r["Timestamp"]);
            $this->set_apprBy($r["Approved_By"]);
            $this->set_apprDate($r["Approved_Date"]);
            $this->set_apprDate($r["Dropped_Date"]);
            $this->set_pifhUsername($r["PIFH_Username"]);
            $this->set_fbid($r["fb_id"]);
            $this->set_password($r["Enc_Password"]);
            $this->set_accessCode($r["Access_Code"]);

            $evt = array("success" => "ok");
        } else {
            // error
            $evt = array("error" => "Cannot Find ID = " . $this->get_fbid());
        }
        return $evt;
    }

    /*
     *  SaveToDB
     *  Update or insert - it checks for an existing record then updates or inserts accordingly
     *  This expects the values in this class to be already defined.
     *  Param: mysqli object
     */

    public function saveToDB(PDO $dbh, $whereStr) {
        $evt = array();

        // Insert or update?
        $r = $this->selectRow($dbh, $whereStr);
        if (!is_null($r)) {
            // updadate only
            $evt = $this->updateRow($dbh, $whereStr);
        } else {
            // insert , then update
            $query = "insert into fbx (fb_id, idName, Status) values ('" . $this->get_fbid() . "', " . $this->get_nid() . ", 'p');";
            $n = $dbh->exec($query);
            if ($n == 1) {
                $evt = $this->updateRow($dbh, $whereStr);
            } else {
                $evt = array("error" => "Insert failed");
            }
        }
        return $evt;
    }

    /*
     *  Internal function: update the row.
     */

    function updateRow(PDO $dbh, $whereStr) {
        $evt = array();

        // don't let the PIFH username be empty.
        if ($this->get_pifhUsername() == "")
            $this->set_pifhUsername($this->get_fun());

        $query = " update fbx set
            idName=" . $this->get_nid() .
                ",Status='w',
            fb_username = '" . $this->get_fun() .
                "',fb_Address_1='" . $this->get_a1() .
                "', fb_Address_2='" . $this->get_a2() .
                "', fb_City='" . $this->get_cy() .
                "', fb_State='" . $this->get_st() .
                "', fb_Zip='" . $this->get_zp() .
                "', fb_First_Name='" . $this->get_fn() .
                "', fb_Last_Name='" . $this->get_ln() .
                "', fb_Phone='" . $this->get_ph() .
                "', fb_Email='" . $this->get_em() .
                "', PIFH_Username='" . $this->get_pifhUsername() .
                "', Enc_Password='" . $this->get_password() .
                "', Access_Code='" . $this->get_accessCode() .
                "' where $whereStr; ";

        $n = $dbh->exec($query);

        if ($n == 1) {
            $evt = array("success" => "update ok");
        } else {
            $evt = array("error" => "update failed");
        }
        return $evt;
    }

    public function selectRow(PDO $dbh, $whereStr) {

        $rows = $this->selectRows($dbh, $whereStr);

        if (count($rows) == 1) {
            return $rows[0];
        } else {
            return null;
        }

        return null;
    }

    public function selectRows(PDO $dbh, $whereStr) {
        $query = "select * from fbx where $whereStr;";
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

}

?>
