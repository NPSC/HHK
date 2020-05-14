<?php
/**
 * anomalies.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

require(CLASSES . "chkBoxCtrlClass.php");
require(CLASSES . "selCtrl.php");
require(CLASSES . "OpenXML.php");

$wInit = new webInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();


// Member Status Selection control
$cbMemStatus = new chkBoxCtrlClass($dbh, "validMemStatus", "Include", "cbMemStatus", false);
$cbMemStatus->set_cbValueArray(true, "a");

// Report type selection control - options defined by Table_Name = anomalyTypes in table gen_loookups
$cbRptType = new chkBoxCtrlClass($dbh, "anomalyTypes", "Include", "cbRptType", true);

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
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
if (isset($_POST["btnRunHere"]) || isset($_POST["btnDlExcel"])) {
     addslashesextended($_POST);
    // set the return values into the controls
    $cbMemStatus->setReturnValues($_POST[$cbMemStatus->get_htmlNameBase()]);
    $cbRptType->setReturnValues($_POST[$cbRptType->get_htmlNameBase()]);

    $isExcel = false;
    if (isset($_POST["btnDlExcel"])) {
        $isExcel = true;
    }

    $prefOnly = false;
    if (isset($_POST["cbPrefOnly"])) {
        $prefOnly = true;
        $checked = "checked='checked'";
    } else {
        $checked = "";
    }

    $includeBad = false;
    if (isset($_POST["cbBad"])) {
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


function doReports(PDO $dbh, chkBoxCtrlClass $cbMemStatus, chkBoxCtrlClass $cbRptType, $isExcel, $prefOnly, $includeBad) {

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

    if ($isExcel) {
        $file = "AddrExceptions.xls";
        $sml = OpenXML::createExcel($uname, 'Address Exception Report');

        // create summary table
        $myWorkSheet = new PHPExcel_Worksheet($sml, 'Constraints');
        // Attach the â€œMy Dataâ€� worksheet as the first worksheet in the PHPExcel object
        $sml->addSheet($myWorkSheet, 1);
        $sml->setActiveSheetIndex(1);
        $sRows = OpenXML::writeHeaderRow($sml, array(0=>'Filter', 1=>'Parameters'));
        // create summary table
        foreach ($sumaryRows as $key => $val) {
            if ($key != "" && $val != "") {
                $flds = array(0 => array('type' => "s",
                        'value' => $key,
                        'style' => "sRight"
                    ),
                    1 => array('type' => "s",
                        'value' => $val
                    )
                );
                $sRows = OpenXML::writeNextRow($sml, $flds, $sRows);
            }
        }
        $sml->setActiveSheetIndex(0);

    } else {
        $txtIntro = "<table style='margin-bottom:5px;'><tr><th colspan='2'>" . $reportTitle . "</th></tr>";
        foreach ($sumaryRows as $key => $val) {
            if ($key != "" && $val != "") {

                $txtIntro .= "<tr><td class='tdlabel'>$key: </td><td>" . $val . "</td></tr>";
            }
        }
        $txtIntro .= "<tr><td class='tdlabel'>Records Fetched: </td><td>" . count($rows) . "</td></tr></table>";
        $markup = "<thead><tr>";
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
            OpenXML::writeHeaderRow($sml, $hdr);
            $reportRows++;

        } else {
            $markup .= "</tr></thead><tbody>";
        }
    } else {

        $markup = "<th>No Data</th></thead><tbody>";
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
                    $flds[$n++] = array('type' => "s", 'value' => $v);
                } else {
                    $markup .= "<td>$v</td>";
                }
            }
        }

        // Process completed row
        if ($isExcel) {
            $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
        } else {
            $markup .= "</tr>";
        }
    }


    if ($isExcel) {
        // Redirect output to a client's web browser (Excel2007)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();

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
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo GRID_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        
        <script type="text/javascript">
            // Init j-query
            $(document).ready(function() {
                var useTable = '<?php echo $divDisp; ?>';
                if (useTable === 'block') {
                    try {
                        listTable = $('#tblCategory').dataTable({
                            "displayLength": 50,
                            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                            "Dom": '<"top"ilfp>rt<"bottom"p>'
                        });
                    }
                    catch (err) {}
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
                <table id="tblCategory" cellpadding="0" cellspacing="0" border="0" class="display">
                    <?php echo $markup; ?>
                </table>
            </div>
        </div>
    </body>
</html>
