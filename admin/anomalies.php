<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\chkBoxCtrl;
use HHK\ExcelHelper;
use HHK\AlertControl\AlertMessage;

/**
 * anomalies.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

$wInit = new WebInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();


// Member Status Selection control
$cbMemStatus = new chkBoxCtrl($dbh, "validMemStatus", "Include", "cbMemStatus", false);
$cbMemStatus->set_cbValueArray(true, "a");

// Report type selection control - options defined by Table_Name = anomalyTypes in table gen_loookups
$cbRptType = new chkBoxCtrl($dbh, "anomalyTypes", "Include", "cbRptType", true);

// Instantiate the alert message control
$alertMsg = new AlertMessage("divAlert1");
$resultMessage = "";
$divDisp = "none";
$markup = "";
$intro = "";
$checked = "checked='checked'";
$bchecked = "";

$Zipauery = "Select     n.idName AS Id,
    (case
        when (n.Record_Company = 0) then concat(n.Name_Last, ', ', n.Name_First)
        else n.Company
    end) AS `Name`,

    (case
        when (na.Bad_Address <> '') then 'T'
        else ''
    end) AS `Bad Addr`,
    (case
        when
            (ifnull(na.Address_2, '') <> '')
        then
            concat(ifnull(na.Address_1, ''),
                    ', ',
                    ifnull(na.Address_2, ''))
        else ifnull(na.Address_1, '')
    end) AS `Street Address`,
    ifnull(na.City, '') AS City,
    ifnull(na.State_Province, '') AS State,
    ifnull(na.Postal_Code, '') AS Zip,
	p.Zip_Code, p.City, p.State, p.Acceptable_Cities

from
    name_address na
    left join name n ON n.idName = na.idName
	left join postal_codes p on left(ifnull(na.Postal_Code, ''), 5) = p.Zip_Code
where
	(n.idName > 0) and (n.Member_Status in ('a','d', 'in')) and na.Country_Code = 'US' and na.Postal_Code <> ''
	and (p.Zip_Code is null
			or p.State != ifnull(na.State_Province, '')
			or (replace(na.City, 'St.', 'Saint') != p.City and locate(replace(na.City, '.', ''), p.Acceptable_Cities) = 0)
		)";
/*
 *  Post-back
 */
if (filter_has_var(INPUT_POST, "btnRunHere") || filter_has_var(INPUT_POST, "btnDlExcel")) {
    
    // set the return values into the controls
    $cbMemStatus->setReturnValues($_POST[$cbMemStatus->get_htmlNameBase()]);
    $cbRptType->setReturnValues($_POST[$cbRptType->get_htmlNameBase()]);

    $isExcel = false;
    if (filter_has_var(INPUT_POST, "btnDlExcel")) {
        $isExcel = true;
    }

    $prefOnly = false;
    if (filter_has_var(INPUT_POST, "cbPrefOnly")) {
        $prefOnly = true;
        $checked = "checked='checked'";
    } else {
        $checked = "";
    }

    $includeBad = false;
    if (filter_has_var(INPUT_POST, "cbBad")) {
        $includeBad = true;
        $bchecked = "checked='checked'";
    } else {
        $bchecked = "";
    }

    // Execute report
    $results = doReports($dbh, $cbMemStatus, $cbRptType, $isExcel, $prefOnly, $includeBad);

    // Check report results
    if ($results[0] === false) {
        // errors
        $alertMsg->set_Context(alertMessage::Alert);
        $alertMsg->set_Text($results[1]);
        $resultMessage = $alertMsg->createMarkup();
    } else if ($results[1] != "") {
        // success
        $markup = $results[1];
        $intro = $results[2];
        $divDisp = "block";
    }
}


function doReports(PDO $dbh, chkBoxCtrl $cbMemStatus, chkBoxCtrl $cbRptType, $isExcel, $prefOnly, $includeBad) {

    $cbMemStatus->setReturnValues($_POST[$cbMemStatus->get_htmlNameBase()]);
    $cbRptType->setReturnValues($_POST[$cbRptType->get_htmlNameBase()]);

    $uS = Session::getInstance();
    $uname = $uS->username;


    $rptClause = "";
    $statClause = "";
    $sumaryRows = array();
    $markup = "";

    $mStatusList = $cbMemStatus->setSqlString();
    if ($mStatusList != "") {
        $statClause = " `Member|Status` in (" . $mStatusList . ") ";
    } else if ($cbMemStatus->setCsvLabel() == "") {
        return array(0 => false, 1 => "No Report - Select a Member Status.");
    }

    $sumaryRows["Member Status"] = $cbMemStatus->setCsvLabel();

    $codes = $cbRptType->get_cbCodeArray();

    if (!($cbRptType->isAllSameState() && $cbRptType->get_cbValueArray($codes[0]) === true)) {

        $rpts = array();
        $gen = $cbRptType->get_genRcrds();

        foreach ($codes as $v) {

            if ($cbRptType->get_cbValueArray($v) === true) {

                $rpts[] = $gen[$v]["Substitute"];
            }
        }

        // Did we catch any reports?
        if (count($rpts) > 0) {
            // Shave the last comma off the label list
            $sumaryRows["Reports"] = substr($sumaryRows["Reports"], 0, -2);

            $rptClause = "";
            // Put the where clause together
            foreach ($rpts as $v) {
                if ($rptClause == "") {
                    $rptClause = " (" . $v;
                } else {
                    $rptClause .= " or " . $v;
                }
            }

            $rptClause .= ") ";
        } else {

            return array(0 => false, 1 => "No Report - Select a Report Type.");
        }
    }

    $sumaryRows["Anomalies"] = $cbRptType->setCsvLabel();

    if ($prefOnly) {
        $sumaryRows["Preferred Address Only"] = "Yes";
    } else {
        $sumaryRows["Preferred Address Only"] = "No";

    }

    if ($includeBad) {
        $sumaryRows["Include Addresses Marked as Bad"] = "Yes";
    } else {
        $sumaryRows["Include Addresses Marked as Bad"] = "No";

    }

    $wClause = "";

    if ($statClause != "" && $rptClause != "") {
        $wClause = " where " . $statClause . " and " . $rptClause;
    } else if ($statClause != "" && $rptClause == "") {
        $wClause = " where " . $statClause;
    } else if ($statClause == "" && $rptClause != "") {
        $wClause = " where " . $rptClause;
    }

    if ($prefOnly && $wClause == "") {
        $wClause = " where Pref<>'' ";
    } else if ($prefOnly && $wClause != "") {
        $wClause .= " and Pref<>'' ";
    }

    if (!$includeBad && $wClause == "") {
        $wClause = " where `Bad Addr` = '' ";
    } else if (!$includeBad && $wClause != "") {
        $wClause .= " and `Bad Addr` = '' ";
    }

    $query = "select * from vdump_badaddress " . $wClause;


    $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hdr = array();
    $cols = 0;
    $file = "";
    $reportRows = 1;
    $reportTitle = "Address Exception Report";

    $txtIntro = '';

    if ($isExcel) {
        $file = "AddrExceptions";
        $writer = new ExcelHelper($file);
        $writer->setAuthor($uname);
        $writer->setTitle("Address Exception Report");

    } else {
        $txtIntro .= "<table style='margin-bottom:5px;'><tr><th colspan='2'>" . $reportTitle . "</th></tr>";
        foreach ($sumaryRows as $key => $val) {
            if ($key != "" && $val != "") {

                $txtIntro .= "<tr><td class='tdlabel'>$key: </td><td>" . $val . "</td></tr>";
            }
        }
        $txtIntro .= "<tr><td class='tdlabel'>Records Fetched: </td><td>" . count($rows) . "</td></tr></table>";
        $markup .= "<thead><tr>";
    }

    // header row
    if (count($rows) > 0) {

        foreach ($rows[0] as $k => $v) {

            $pos = strpos($k, '|');
            // Dont display colunms with a | in their name
            if ($pos === false) {
                $hdr[$cols++] = $k;
                $markup .= "<th>$k</th>";
            }
        }

        if ($isExcel) {
            $dHdr = array();
            foreach($hdr as $col){
                $dHdr[$col] = "string";
            }
            $writer->writeSheetHeader("Worksheet", $dHdr, $writer->getHdrStyle());
        } else {
            $markup .= "</tr></thead><tbody>";
        }
    } else {

        $markup = "<thead><th></th></thead><tbody></tbody>";
        
        return array(0 => true, 1 => $markup, 2 => $txtIntro);
    }

    // Data rows
    for ($i = 0, $j = count($rows); $i < $j; $i++) {

        if ($isExcel) {

        } else {
            $markup .= "<tr>";
        }

        $n = 0;


        // Fields
        foreach ($rows[$i] as $k => $v) {

            if ($k == "Id" && !$isExcel) {
                $v = "<a href='NameEdit.php?id=$v'>" . $v . "</a>";
            }

            $pos = strpos($k, '|');

            if ($pos === false) {
                if ($isExcel) {
                    $flds[$n++] = $v;
                } else {
                    $markup .= "<td>$v</td>";
                }
            }
        }

        // Process completed row
        if ($isExcel) {
            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Worksheet", $row);
        } else {
            $markup .= "</tr>";
        }
    }


    if ($isExcel) {

        //Summary table
        /* $sHdr = array(
            "Filter"=>"string",
            "Parameters"=>"string"
        );
        $sColWidths = array(
            '50',
            '50'
        );

        $sHdrStyle = $writer->getHdrStyle($sColWidths);

        $writer->writeSheetHeader("Constraints", $sHdr, $sHdrStyle);

        $flds = array();
        foreach ($sumaryRows as $key=>$val){
            $flds[] = array($key, $val);
        }
        $writer->writeSheet($flds, "Constraints"); */

        $writer->download();

    } else {
        $markup .= "</tbody>";
    }


    return array(0 => true, 1 => $markup, 2 => $txtIntro);
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">
            // Init j-query
            $(document).ready(function() {

            	$("input[type=submit], input[type=button]").button();

                var useTable = '<?php echo $divDisp; ?>';
                if (useTable === 'block') {
                    try {
                        listTable = $('#tblCategory').dataTable({
                            "displayLength": 50,
                            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                            "Dom": '<"top"ilfp>rt<"bottom"p>'
                        });
                    }
                    catch (err) { console.log(err)}
                }
            });
        </script>

    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <?php echo $resultMessage ?>
                <form name="form1"  action="anomalies.php" method="post">
                    <table>
                        <tr>
                            <th>Member Status</th>
                            <th>Reports</th>
                        </tr><tr>
                            <td><?php echo $cbMemStatus->createMarkup(); ?></td>
                            <td><?php echo $cbRptType->createMarkup(); ?></td>
                        </tr><tr>
                            <td colspan="2"><input type="checkbox" name="cbPrefOnly" id="cbPrefOnly" <?php echo $checked ?> style="margin-right:5px;"/><label for="cbPrefOnly">Search 'Preferred Addresses' Only</label></td>
                        </tr><tr>
                            <td colspan="2"><input type="checkbox" name="cbBad" id="cbBad" <?php echo $bchecked ?> style="margin-right:5px;"/><label for="cbBad">Include addresses marked as 'Bad'</label></td>
                        </tr>
                    </table>
                    <div style="margin-top:15px;">
                        <input type="submit" name="btnRunHere" value="Run Here" style="margin-left:20px; margin-right: 15px;"/>
                        <input type="submit" name="btnDlExcel" value="Download to Excel"/>
                    </div>
                </form>
            </div>
            <div style="clear: both;"></div>
            <div id="divTable" style="margin-top: 15px; display:<?php echo $divDisp; ?>;" class="ui-widget ui-widget-content ui-corner-all">
                <?php echo $intro; ?>
                <table id="tblCategory" class="display">
                    <?php echo $markup; ?>
                </table>
            </div>
        </div>
    </body>
</html>
