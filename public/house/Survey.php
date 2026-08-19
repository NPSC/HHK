<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\HTMLTable;
use HHK\ExcelHelper;
use HHK\Vite\Vite;


/**
 * Survey.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new WebInit();

$dbh = $wInit->dbh;

$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();


$refreshDate = NULL;
$dataTable = '';
$showTable = 'display:none;';

function outputIt(array $gName, bool $excel, int $reportRows, ExcelHelper $writer, ?array $hdr, HTMLTable $tbl) {
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

    return $reportRows++;

}

// date of last survey
$stmt = $dbh->prepare("SELECT `Description` FROM `gen_lookups` WHERE `Table_Name` = 'Guest_Survey' AND `Code` = 'Survey_Date'");
$stmt->execute();
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

    $params = [':endDate' => $endDate];

    if ($refreshDate != NULL) {
        $startDateClause = " AND `v`.`Actual_Departure` >= :refreshDate";
        $params[':refreshDate'] = $refreshDate->format('Y-m-d');
    } else {
        $startDateClause = '';
    }

    $query = "SELECT MAX(`v`.`Actual_Departure`) AS `Actual_Departure`, `n2`.`Name_Last` AS `pLast`, `n2`.`Name_First` AS `pFirst`, `v`.`idPrimaryGuest`, `n`.`idName`, `n`.`Name_Last`, `n`.`Name_First`, `n`.`Name_Prefix`, `n`.`Name_Suffix`, `g`.`Description` AS `Relationship`,
IFNULL(`na`.`Address_1`, '') AS `Address_1`, IFNULL(`na`.`Address_2`, '') AS `Address_2`, IFNULL(`na`.`City`, '') AS `City`, IFNULL(`na`.`State_Province`, '') AS `State_Province`, IFNULL(`na`.`Postal_Code`, '') AS `Postal_Code`
FROM `visit` `v` LEFT JOIN `stays` `s` ON `v`.`idVisit` = `s`.`idVisit`
	LEFT JOIN `name_guest` `ng` ON `s`.`idName` = `ng`.`idName`
	LEFT JOIN `name` `n` ON `ng`.`idName` = `n`.`idName`
        LEFT JOIN `hospital_stay` `h` ON `v`.`idHospital_stay` = `h`.`idHospital_stay`
        LEFT JOIN `name` `n2` ON `h`.`idPatient` = `n2`.`idName`
	LEFT JOIN `name_address` `na` ON `n`.`idName` = `na`.`idName` AND `n`.`Preferred_Mail_Address` = `na`.`Purpose`
	LEFT JOIN `name_demog` `nd` ON `n`.`idName` = `nd`.`idName` AND IFNULL(`nd`.`Age_Bracket`, '6') IN ('6', '8')
        LEFT JOIN `gen_lookups` `g` ON `g`.`Table_Name` = 'Patient_Rel_Type' AND `g`.`Code` = `ng`.`Relationship_Code`
WHERE `v`.`Status` = 'co' AND `v`.`Actual_Departure` < :endDate $startDateClause
    GROUP BY `n`.`idName`
ORDER BY `h`.`idPsg`, `na`.`Address_1`, `na`.`Address_2`";

    $stmt = $dbh->prepare($query);
    $stmt->execute($params);
    $tbl = new HTMLTable();
    $pName = '';
    $address = '';
    $gName = array();
    $sml = NULL;
    $reportRows = 1;
    $file = 'GuestSurvey';
    $writer = new ExcelHelper($file);
    $hdr = array();


    if ($excel) {
        
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
        $updSurveyStmt = $dbh->prepare("UPDATE `gen_lookups` SET `Description` = :endDate WHERE `Table_Name` = 'Guest_Survey' AND `Code` = 'Survey_Date'");
        $updSurveyStmt->execute([':endDate' => $endDate]);

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
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>

        <?php echo Vite::asset('resources/js/house.js'); ?>
        
        <?php echo FAVICON; ?>

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
