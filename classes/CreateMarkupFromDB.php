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


    /**
     * Build an HTML table from rows; the first row's keys become the column headers.
     * Keys and cell values are output as-is (callers pass markup), so they are not escaped.
     * @param array<array<scalar|null>> $rows
     * @param string $tableId
     * @param string $tdClassIndicator row key whose value is used as the td class, not shown as a column
     * @return string
     */
    public static function generateHTML_Table(array $rows, string $tableId, string $tdClassIndicator = ''): string {

        $thead = "";
        $tbody = "";

        if (count($rows) < 1) {
            return "<table id='$tableId'  class='display'><thead><td>No Data</td></thead><tbody><td></td></tbody></table>";
        }

        $firstKey = array_key_first($rows);

        $keys = array_keys($rows[$firstKey]);

        if (count($keys) == 0) {
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

    /**
     * @param array<string, string|int> $sumaryRows label => value; rows with an empty label or value are skipped
     * @param string $reportTitle
     * @return string
     */
    public static function generateHTMLSummary(array $sumaryRows, string $reportTitle): string {
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