<?php
/**
 * VolCats.php
 *
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class VolCats {
    //
    private $dormantSelections;
    private $roleSelection;
    private $ordering;
    private $andOr;
    private $Vol_Codes = array();
    private $reportMarkup;
    private $startDate;
    private $endDate;
    private $curRetire;
    public $reportHdrMarkup = "";

    public function __construct() {}


    function set_roleSelection($v) {
        $this->roleSelection = $v;
    }
    function get_roleSelection() {
        return $this->roleSelection;
    }

    function set_dormantSelections($v) {
        $this->dormantSelections = $v;
    }
    function get_dormantSelections() {
        return $this->dormantSelections;
    }

    function set_curRetire($v) {
        $this->curRetire = $v;
    }
    function get_curRetire() {
        return $this->curRetire;
    }

    function set_startDate($v) {
        $this->startDate = $v;
    }
    function get_startDate() {
        return $this->startDate;
    }

    function set_endDate($v) {
        $this->endDate = $v;
    }
    function get_endDate() {
        return $this->endDate;
    }

    function set_ordering($v) {
        $this->ordering = $v;
    }
    function get_ordering() {
        return $this->ordering;
    }

    function set_andOr($v) {
        $this->andOr = $v;
    }
    function get_andOr() {
        return $this->andOr;
    }

    function set_Vol_Codes($v) {
        $this->Vol_Codes = $v;
    }
    function get_Vol_Codes() {
        return $this->Vol_Codes;
    }

//    function set_Vol_Skills($v) {
//        $this->Vol_Skills = $v;
//    }
//    function get_Vol_Skills() {
//        return $this->Vol_Skills;
//    }
//
//    function set_Vol_Types($v) {
//        $this->Vol_Types = $v;
//    }
//    function get_Vol_Types() {
//        return $this->Vol_Types;
//    }

    function set_reportMarkup($v) {
        $this->reportMarkup = $v;
    }
    function get_reportMarkup() {
        return $this->reportMarkup;
    }



}
?>
