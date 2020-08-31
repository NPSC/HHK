<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\HTMLTable;
use HHK\ExcelHelper;


/**
 * Survey.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();


$refreshDate = NULL;
$dataTable = '';
$showTable = 'display:none;';

function outputIt(array $gName, $excel, ExcelHelper $writer, $hdr, $tbl) {
    // write last patient out
    foreach ($gName as $g) {

        if ($excel) {

            $flds = array(
                $g['depart'],
                $g['last'],
                $g['first'],
                $g['street'],
                $g['city'],
                $g['state'],
                $g['zip']
            );

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Worksheet", $row);


        } else {
            $lineAddr = $g['street'] . ', ' . $g['city'] . ', ' . $g['state'] . ' ' . $g['zip'];

            $tbl->addBodyTr(
                HTMLTable::makeTd('')
                .HTMLTable::makeTd($g['depart'])
                .HTMLTable::makeTd($g['first'] . ' ' . $g['last'])
                .HTMLTable::makeTd($lineAddr)
                );
        }
    }

    return $reportRows;

}

// date of last survey
$stmt = $dbh->query("select Description from gen_lookups where Table_Name='Guest_Survey' and Code = 'Survey_Date'");
$rows = $stmt->fetchAll(PDO::FETCH_NUM);
if (count($rows) > 0) {
    $refreshDate = new DateTime(date('Y-m-d', strtotime($rows[0][0])));
}

// Survey End Date
$endDT = new DateTime();
$endDT->sub(new DateInterval('P' . $uS->SolicitBuffer . 'D'));

//
if (isset($_POST['btnPsg']) || isset($_POST['btnGen'])) {

    $excel = FALSE;
    if (isset($_POST['btnGen'])) {
        $excel = TRUE;
    }

    $endDate = $endDT->format('Y-m-d');

    if ($refreshDate != NULL) {
        $startDateClause = " and v.Actual_Departure >= '" . $refreshDate->format('Y-m-d') . "'";
    } else {
        $startDateClause = '';
    }

    $query = "select max(v.Actual_Departure)as Actual_Departure, n2.Name_Last as pLast, n2.Name_First as pFirst, v.idPrimaryGuest, n.idName, n.Name_Last, n.Name_First, n.Name_Prefix, n.Name_Suffix, g.Description as Relationship,
ifnull(na.Address_1,'') as Address_1, ifnull(na.Address_2,'') as Address_2, ifnull(na.City,'') as City, ifnull(na.State_Province,'') as State_Province, ifnull(na.Postal_Code,'') as Postal_Code
from visit v left join stays s on v.idVisit = s.idVisit
	left join name_guest ng on s.idName = ng.idName
	left join name n on ng.idName = n.idName
        left join hospital_stay h on v.idHospital_stay = h.idHospital_stay
        left join name n2 on h.idPatient = n2.idName
	left join name_address na on n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
	left join name_demog nd on n.idName = nd.idName and ifnull(nd.Age_Bracket, '6') in ('6', '8')
        left join gen_lookups g on g.Table_Name = 'Patient_Rel_Type' and g.Code = ng.Relationship_Code
where v.Status = 'co' and v.Actual_Departure < '$endDate' $startDateClause
    group by n.idName
order by h.idPsg, na.Address_1, na.Address_2";

    $stmt = $dbh->query($query);
    $tbl = new HTMLTable();
    $pName = '';
    $address = '';
    $gName = array();
    $sml = NULL;
    $reportRows = 1;
    $file = 'GuestSurvey';


    if ($excel) {
        $writer = new ExcelHelper("GuestSurvey");
        $writer->setAuthor($uS->username);
        $writer->setTitle("Guest Survey");
        
        // build header
        $hdr = array(
            "Depart"=>"string",
            "Last Name"=>"string",
            "First Name"=>"string",
            "Address"=>"string",
            "City"=>"string",
            "State"=>"string",
            "Zip"=>"string"
        );
        $colWidths = array("15", "20", "20", "20", "15", "10", "10");
        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Worksheet", $hdr, $hdrStyle);
    }

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // New PSG?
        if ($r['pFirst'] . ' ' . $r['pLast'] != $pName) {

            $reportRows = outputIt($gName, $excel, $reportRows, $writer, $hdr, $tbl);

            $gName = array();
            $pName = $r['pFirst'] . ' ' . $r['pLast'];

            if ($excel === FALSE) {
                $tbl->addBodyTr(HTMLTable::makeTd($pName, array('colspan'=>'6')));
            }

        }

        $addr = $r['Address_1'];
        if ($r['Address_2'] != '') {
            $addr .= ' ' . $r['Address_2'];
        }

        $foundIt = FALSE;


        if ($addr == $address) {

            // Look for same last name and address
            for ($i = 0; $i < count($gName); $i++) {
                if ($gName[$i]['last'] == $r['Name_Last']) {
                    $foundIt = TRUE;
                    $gName[$i]['first'] .= ' & ' . $r['Name_First'];
                }
            }
        }

        if ($foundIt === FALSE) {

            $gName[] = array(
                        'depart' => date('M j, Y', strtotime($r['Actual_Departure'])),
                        'first' => $r['Name_First'],
                        'last'=> $r['Name_Last'],
                        'street' => $addr,
                        'city' => $r['City'],
                        'state' => $r['State_Province'],
                        'zip' => $r['Postal_Code']);
        }

        $address = $addr;
    }

    // write last patient out
    outputIt($gName, $excel, $reportRows, $writer, $hdr, $tbl);

    if ($excel) {

        // update the saved survey date.
        $dbh->exec("update gen_lookups set Description = '$endDate' where Table_Name='Guest_Survey' and Code = 'Survey_Date'");

        $writer->download();
    }

    $tbl->addHeaderTr(HTMLTable::makeTh('Patient').HTMLTable::makeTh('Depart').HTMLTable::makeTh('Guest').HTMLTable::makeTh('Address'));

    $dataTable = $tbl->generateMarkup();
    $showTable = 'display:block;';
}



?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo NOTY_CSS; ?>

        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox  hhk-member-detail hhk-visitdialog" style="padding:25px;margin-top:15px;">
                <form name='form1' method="post">
                <table>
                    <tr><td>Survey Blackout Days:</td><td><?php echo $uS->SolicitBuffer; ?></td></tr>
                    <tr><td>Last Survey Date:</td><td><?php echo $refreshDate->format('M j, Y'); ?></td></tr>
                    <tr><td>Guests departing before:</td><td><?php echo $endDT->format('M j, Y'); ?></td></tr>
                </table>
                    <input type="submit" name="btnPsg" value="Run Here" style='margin:5px;'/><input type='submit' name='btnGen' value='Download Excel Spreadsheet' style='margin:5px;<?php echo $showTable; ?>'/>
                </form>
            </div>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox  hhk-member-detail hhk-visitdialog" style="padding:5px;margin-top:15px; <?php echo $showTable; ?>">
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
