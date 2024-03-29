<?php

namespace HHK;



/**
 * CreateMarkupFromDB.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */




class CreateMarkupFromDB {


    public static function generateHTML_Table(array $rows, $tableId, $tdClassIndicator = '') {

        $thead = "";
        $tbody = "";

        if (count($rows) < 1) {
            return "<table id='$tableId'  class='display'><thead><td>No Data</td></thead><tbody><td></td></tbody></table>";
        }

        $firstKey = array_key_first($rows);

        $keys = array_keys($rows[$firstKey]);

        if (is_array($keys) === FALSE || count($keys) == 0) {
            return '';
        }

        // Header row
        foreach ($keys as $k) {

            if ($tdClassIndicator == '' || $k != $tdClassIndicator) {
                $thead .= "<th>$k</th>";
            }
        }

        if ($thead != "") {
            $thead = "<thead><tr>" . $thead . "</tr></thead>";
        }


        // Body Rows
        foreach ($rows as $r) {

            $mkupRow = "";
            $tdClass = '';

            if ($tdClassIndicator != '' && isset($r[$tdClassIndicator])) {
                $tdClass = ' class="' .$r[$tdClassIndicator] . '"';
                unset($r[$tdClassIndicator]);
            }

            foreach ($r as $col) {

                $mkupRow .= "<td$tdClass>" . ($col == '' ? ' ' : $col) . "</td>";
            }

            $tbody .= "<tr>" . $mkupRow . "</tr>";

        }

        $tbody = "<tbody>" . $tbody . "</tbody>";
        if ($tableId != "") {
            $tableId = " id='$tableId' ";
        }

        return "<table $tableId class='display'>" . $thead . $tbody . "</table>";

    }

    public static function generateTRs(array $rows, $tdClassIndicator = '') {

        $tbodys = array();

        foreach ($rows as $r) {

            $mkupRow = "";
            $tdClass = '';

            if ($tdClassIndicator != '' && isset($r[$tdClassIndicator])) {
                $tdClass = ' class="' .$r[$tdClassIndicator] . '"';
                unset($r[$tdClassIndicator]);
            }

            foreach ($r as $col) {

                $mkupRow .= "<td$tdClass>" . ($col == '' ? '&nbsp;' : $col) . "</td>";
            }

            $tbodys[] = "<tr>" . $mkupRow . "</tr>";

        }

        return $tbodys;

    }

    public static function generateHTMLSummary($sumaryRows, $reportTitle) {
        $summaryRowsTxt = "";
        $txtHeader = "<tr><th colspan='2'>" . $reportTitle . " <input id='Print_Button' type='button' value='Print'/></th></tr>";

        // create summary table
        foreach ($sumaryRows as $key => $val) {

            if ($key != "" && $val != "") {
                $summaryRowsTxt .= "<tr><td class='tdlabel'>" . $key . "</td><td>" . $val . "</td></tr>";

            }
        }
        return "<table style='margin-top:40px; margin-bottom:10px; min-width: 350px;'>" . $txtHeader . $summaryRowsTxt . "</table>";
    }


}

?>
