<?php
/**
 * CheckDateReport.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
function chkDate(PDO $dbh) {
    // Fetch Member form, used for contact dates
    $id = 0;
    $sFuture = true;
    $sPast = false;
    $sDays = 5;

    if (isset($_POST["txtId"])) {

        $id = intval(filter_var($_POST["txtId"], FILTER_SANITIZE_NUMBER_INT));

    }

    // Get page input fields
    if (isset($_POST["cbFuture"])) {
        $sFuture = filter_var($_POST["cbFuture"], FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($_POST["cbPast"])) {
        $sPast = filter_var($_POST["cbPast"], FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($_POST["txtNumDays"])) {
        $sDays = filter_var($_POST["txtNumDays"], FILTER_SANITIZE_NUMBER_INT);
    }

    if ($sPast) {
        $sPastTicks = $sDays * 24 * 60 * 60;
        $sPrettyTextPastDate = date("m-d-Y", time() - $sPastTicks);
        $sPastDate = " and `Check Date` >= '" . date("Y/m/d", time() - $sPastTicks) . "'";
        $sPstmk = "checked='checked'";
    }
    else {
        $sPastDate = " and `Check Date` >= '" . date("Y/m/d", time()) . "'";
        $sPrettyTextPastDate = date("m-d-Y", time());
        $sPstmk = "";
    }

    if ($sFuture) {
        $sFutureTicks = $sDays * 24 * 60 * 60;
        $sFutureDate = " and `Check Date` <= '" . date("Y/m/d", time() + $sFutureTicks) . "' ";
        $sPrettyTextFutureDate = date("m-d-Y", time() + $sFutureTicks);
        $sFutmk = "checked='checked'";
    }
    else {
        $sFutureDate = " and `Check Date` <= '" . date("Y/m/d", time()) . "' ";
        $sPrettyTextFutureDate = date("m-d-Y", time());
        $sFutmk = "";
    }


    $markup = "";
    $intro = "";

    if ($id > 0) {
        // individual report.

        // Get any dates
        $query = "select * from vvol_checkdates where Id =" . $id . $sPastDate . $sFutureDate;
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //$res = queryDB($con, "select * from vvol_checkdates where Id =" . $id . $sPastDate . $sFutureDate);

        // table caption
        $intro = "<tr><td>Volunteer Individual Contact-Date Report for Id = $id.  Date Generated: " . date("m-d-Y") . "</td></tr>";
        $intro .= "<tr><td>Search dates between $sPrettyTextPastDate to $sPrettyTextFutureDate</td></tr>";
        $markup .= getCheckDateMarkup($rows);

    } else if (isset($_POST["btnCkDate"])) {
        $tbl = "";
        $cds = "";

        if (isset($_POST["selVol"])) {
            $VolCodes = $_POST["selVol"];

            foreach ($VolCodes as $VolCode) {
                if ($VolCode != "")
                    $tbl .= "'$VolCode', ";
            }
        }

        if ($tbl != "") {
            $tbl = substr($tbl, 0, strlen($tbl) - 2);
            $cds =  "and Category in ($tbl)";
        }

        $query = "select * from vvol_checkdates where 1=1 " . $sPastDate . $sFutureDate . $cds;

        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //$res = queryDB($con, $query);

            // table caption
            $intro = "<tr><td>Contact Date Report.  Date Generated: " . date("m-d-Y") . "</td></tr>";
            $intro .= "<tr><td>Search dates between $sPrettyTextPastDate and $sPrettyTextFutureDate</td></tr>";
            $intro .= "<tr><td>For category(s) $tbl </td></tr>";
            $markup .= getCheckDateMarkup($rows);


    }

    return array($markup, $intro);
}

function getCheckDateMarkup($rows) {

    if (count($rows) > 0) {
        // Table header row
        $markup = "<thead><tr>";

        foreach ($rows[0] as $k => $v) {

            if (substr($k, 0, 4) != "Vol_")
                $markup .= "<th>" . $k . "</th>";
        }

        $markup .= "</tr></thead><tbody>";

        // peruse the rows
        foreach ($rows as $rw) {

            $markup .= "<tr>";
            // peruse the fields in each row
            foreach ($rw as $k => $v) {

                // Dont show the 'Vol_' fields.
                if (substr($k, 0, 4) != "Vol_") {
                    if ($k == 'Id') {
                        $markup .= "<td><a href=\"NameEdit.php?id=" . $v . "\">" . $v . "</a></td>";
                    } else if ($k == "Notes") {
                        if ($v != "")
                            $markup .= "<td><span title=\"" . $v . "\">#</span></td>";
                        else
                            $markup .= "<td>" . $v . "</td>";
                    } else {
                        $markup .= "<td>" . $v . "</td>";
                    }
                }
            }
            $markup .= "</tr>";

        }
    } else {
        $markup = "<tr><td>No Records</td></tr>";
    }

    return $markup . "</tbody>";
}

?>
