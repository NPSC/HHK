<?php
/**
 * DRaHospReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

use HHK\sec\Session;
use HHK\sec\WebInit;
use HHK\AlertControl\AlertMessage;
use HHK\sec\SecurityComponent;
use HHK\Config_Lite\Config_Lite;
use HHK\SysConst\VolMemberType;
use HHK\SysConst\ReservationStatus;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLContainer;
use HHK\CreateMarkupFromDB;
use HHK\SysConst\GLTableNames;
use HHK\HTMLControls\HTMLSelector;
use HHK\ExcelHelper;

require ("homeIncludes.php");

try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$pageHeader = $wInit->pageHeading;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();


// Instantiate the alert message control
$alertMsg = new AlertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(AlertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();

$isGuestAdmin = SecurityComponent::is_Authorized('guestadmin');

$labels = new Config_Lite(LABEL_FILE);


function getRecords(\PDO $dbh, $local, $type, $colNameTitle, $whClause, $hospitals, $start, $end, $labels) {

    if ($type == VolMemberType::Doctor) {
        $Id = 'idDoctor';
    } else if ($type == VolMemberType::ReferralAgent) {
        $Id = 'idReferralAgent';
    }

    $query = "select hs.$Id as `Id`, concat(n.Name_Last, ', ', n.Name_First) as `FirstLast`, ifnull(hs.idHospital, 'Sub Total') as `Hospital`, count(hs.idHospital_stay) as `Patients`
from hospital_stay hs left join `name` n  ON hs.$Id = n.idName
left join reservation rv on hs.idHospital_stay = rv.idHospital_Stay
where rv.`Status` in ('" . ReservationStatus::Checkedout . "', '" . ReservationStatus::Staying . "') "
 . " and DATE(ifnull(rv.Actual_Departure, rv.Expected_Departure)) > DATE('$start') and DATE(ifnull(rv.Actual_Arrival, rv.Expected_Arrival)) < DATE('$end')  $whClause
group by concat(n.Name_Last, ', ', n.Name_First), hs.idHospital with rollup";

        $stmt = $dbh->query($query);

    if ($local) {

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh($colNameTitle) . HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital')) . HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient').'s'));

    } else {

        $fileName = 'DoctorReport';
        $sheetName = 'Sheet1';
        
        // build header
        $hdr = array();
        $colWidths = array();
        
        // Header row
        $colWidths = array(10, 20, 20, 15);
        $hdr['Id'] = "string";
        $hdr[$colNameTitle] = "string";
        $hdr[$labels->getString('hospital', 'hospital', 'Hospital')] = "string";
        $hdr[$labels->getString('MemberType', 'patient', 'Patient')] = "integer";
        
        $writer = new ExcelHelper($fileName);
        
        $hdrStyle = $writer->getHdrStyle($colWidths);
        
        $writer->writeSheetHeader($sheetName, $hdr, $hdrStyle);

    }

    $numRows = $stmt->rowCount();
    $rowCounter = 1;
    $lastId = '';

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {


        if ($local) {

            $id = '';
            $doc = '';

            if ($rowCounter < $numRows) {

                if ($r['Id'] > 0 && $lastId != $r['Id']) {
                    $id = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'../admin/NameEdit.php?id=' . $r['Id']));
                    $doc = $r['FirstLast'];
                } else if ($rowCounter == 1) {
                    $doc = 'unassigned';
                }

                $lastId = $r['Id'];
            }

            $hosp = '';
            $harray = array();
            if (isset($hospitals[$r['Hospital']])) {
                $hosp = $hospitals[$r['Hospital']][1];
            } else if ($r['Hospital'] == 'Sub Total') {
                $harray['style'] = 'text-align:right;';
                if ($rowCounter == $numRows) {
                    $hosp = 'Total';
                } else {
                    $hosp = $r['Hospital'];
                }
            }

            $tbl->addBodyTr(
                    HTMLTable::makeTd($id)
                    .HTMLTable::makeTd($doc)
                    .HTMLTable::makeTd($hosp, $harray)
                    .HTMLTable::makeTd($r['Patients'], array('style'=>'text-align:center;')));

        } else {

            $id = '';
            $doc = '';

            if ($rowCounter < $numRows) {

                if ($r['Id'] > 0 && $lastId != $r['Id']) {
                    $id = $r['Id'];
                    $doc = $r['FirstLast'];
                } else if ($rowCounter == 1) {
                    $doc = 'unassigned';
                }

                $lastId = $r['Id'];
            }

            $hosp = '';

            if (isset($hospitals[$r['Hospital']])) {

                $hosp = $hospitals[$r['Hospital']][1];

            } else if ($r['Hospital'] == 'Sub Total') {

                if ($rowCounter == $numRows) {
                    $hosp = 'Total';
                } else {
                    $hosp = $r['Hospital'];
                }
            }

            $row = array($id, $doc, $hosp, $r['Patients']);
            $row = $writer->convertStrings($hdr, $row);
            $writer->writeSheetRow($sheetName, $row);
        }

        $rowCounter++;
    }

    if ($local) {

        $dataTable = $tbl->generateMarkup(array('id'=>'docs'));
                //CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
        return $dataTable;


    } else {
        $writer->download();
    }

}


function blanksOnly(\PDO $dbh, $type, $whClause, $hospitals, $start, $end, $labels) {

    $class = '';
    $htmlId = '';
    $nameCol = $labels->getString('MemberType', 'patient', 'Patient');
    $hospitalCol = $labels->getString('hospital', 'hospital', 'Hospital');

    if ($type == VolMemberType::Doctor) {
        $Id = 'idDoctor';
        $prefix = 'd';
        $class = 'hhk-docInfo';
        $htmlId = 'txtDocSch';
    } else if ($type == VolMemberType::ReferralAgent) {
        $Id = 'idReferralAgent';
        $prefix = 'a';
        $class = 'hhk-agentInfo';
        $htmlId = 'txtAgentSch';
    }

    $query = "select hs.idPatient as `Id`, n.Name_Full as `$nameCol Name`, hs.idHospital as `$hospitalCol`, hs.idPsg
from hospital_stay hs left join `name` n on hs.idPatient = n.idName
left join reservation rv on hs.idHospital_stay = rv.idHospital_Stay
where hs.$Id = 0 and rv.`Status` in ('" . ReservationStatus::Checkedout . "', '" . ReservationStatus::Staying . "') "
 . " and DATE(ifnull(rv.Actual_Departure, rv.Expected_Departure)) > DATE('$start') and DATE(ifnull(rv.Actual_Arrival, rv.Expected_Arrival)) < DATE('$end') $whClause";

    $stmt = $dbh->query($query);

    $rows = array();

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        $r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'GuestEdit.php?id=' . $r['Id'] . '&psg=' . $r['idPsg']));

        if (isset($hospitals[$r[$hospitalCol]])) {
            $r[$hospitalCol] = $hospitals[$r[$hospitalCol]][1];
        }

        unset($r['idPsg']);

        $rows[] = $r;
    }

    if (count($rows) > 0) {
        return CreateMarkupFromDB::generateHTML_Table($rows, '');
    }

}

$assocSelections = array();
$hospitalSelections = '';
$mkTable = '';

$type = '';
$cbBlank = '';
$dataTable = '';
$settingstable = '';

$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$errorMessage = '';
$calSelection = '19';


$monthArray = array(
    1 => array(1, 'January'), 2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}

// doctors
if ($uS->Doctor) {
    $rptSetting = 'd';
} else {
    $rptSetting = 'r';
}

// Hospital and association lists
$hospList = array();
if (isset($uS->guestLookups[GLTableNames::Hospital])) {
    $hospList = $uS->guestLookups[GLTableNames::Hospital];
}

$hList = array();
$aList = array();
foreach ($hospList as $h) {
    if ($h[2] == 'h') {
        $hList[] = array(0=>$h[0], 1=>$h[1]);
    } else if ($h[2] == 'a' && $h[1] != '(None)') {
        $aList[] = array(0=>$h[0], 1=>$h[1]);
    }
}


if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // gather input

    if (isset($_POST['selCalendar'])) {
        $calSelection = intval(filter_var($_POST['selCalendar'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['selIntMonth'])) {
        $months = filter_var_array($_POST['selIntMonth'], FILTER_SANITIZE_NUMBER_INT);
    }

    if (isset($_POST['selIntYear'])) {
        $year = intval(filter_var($_POST['selIntYear'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['stDate'])) {
        $txtStart = filter_var($_POST['stDate'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['enDate'])) {
        $txtEnd = filter_var($_POST['enDate'], FILTER_SANITIZE_STRING);
    }
    if (isset($_POST['selAssoc'])) {
        $reqs = $_POST['selAssoc'];
        if (is_array($reqs)) {
            $assocSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if (isset($_POST['selHospital'])) {
        $reqs = $_POST['selHospital'];
        if (is_array($reqs)) {
            $hospitalSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }


   if ($calSelection == 20) {
        // fiscal year
        $adjustPeriod = new DateInterval('P' . $uS->fy_diff_Months . 'M');
        $startDT = new DateTime($year . '-01-01');
        $startDT->sub($adjustPeriod);
        $start = $startDT->format('Y-m-d');

        $endDT = new DateTime(($year + 1) . '-01-01');
        $end = $endDT->sub($adjustPeriod)->format('Y-m-d');

    } else if ($calSelection == 21) {
        // Calendar year
        $startDT = new DateTime($year . '-01-01');
        $start = $startDT->format('Y-m-d');

        $end = ($year + 1) . '-01-01';

    } else if ($calSelection == 18) {
        // Dates
        if ($txtStart != '') {
            $startDT = new DateTime($txtStart);
        } else {
            $startDT = new DateTime();
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
        } else {
            $endDT = new DateTime();
        }

        $start = $startDT->format('Y-m-d');
        $end = $endDT->format('Y-m-d');

    } else if ($calSelection == 22) {
        // Year to date
        $start = date('Y') . '-01-01';

        $endDT = new DateTime();
        $endDT->add(new DateInterval('P1D'));
        $end = $endDT->format('Y-m-d');

    } else {
        // Months
        $interval = 'P' . count($months) . 'M';
        $month = $months[0];
        $start = $year . '-' . $month . '-01';

        $endDate = new DateTime($start);
        $endDate->add(new DateInterval($interval));

        $end = $endDate->format('Y-m-d');
    }



    // Hospitals
    $whHosp = '';
    $tdHosp = '';

    foreach ($hospitalSelections as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
                $tdHosp .= $hospList[$a][1];
            } else {
                $whHosp .= ",". $a;
                $tdHosp .= ', '. $hospList[$a][1];
            }
        }
    }

    if ($tdHosp == '') {
        $tdHosp = 'All';
    }


    // Associations.
    $whAssoc = '';
    $tdAssoc = '';

    // Only if there are any.
    if (count($aList) > 0) {

        foreach ($assocSelections as $a) {
            if ($a != '') {
                if ($whAssoc == '') {
                    $whAssoc .= $a;
                    $tdAssoc .= $hospList[$a][1];
                } else {
                    $whAssoc .= ",". $a;
                    $tdAssoc .= ', '. $hospList[$a][1];
                }
            }
        }

        if ($tdAssoc == '') {
            $tdAssoc = 'All';
        }

        $tdAssoc = HTMLTable::makeTd($tdAssoc);
    }


    if ($whHosp != '') {
        $whHosp = " and hs.idHospital in (".$whHosp.") ";
    }

    if ($whAssoc != '') {
        $whHosp .= " and hs.idAssociation in (".$whAssoc.") ";
    }




    if (isset($_POST['rbReport'])) {


        // Create settings markup
        $sTbl = new HTMLTable();

        $colTitle = '';
        $blanksOnly = FALSE;

        $rptSetting = filter_var($_POST['rbReport'], FILTER_SANITIZE_STRING);

        if (isset($_POST['cbBlanksOnly'])) {
            $blanksOnly = TRUE;
            $cbBlank = "checked";
        }


        switch ($rptSetting) {

            case 'd':
                $type = VolMemberType::Doctor;
                $colTitle = 'Doctor';
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' Doctor Report', array('colspan'=>'4')));

                break;

            case 'r':
                $type = VolMemberType::ReferralAgent;
                $colTitle = $labels->getString('hospital', 'referralAgent', 'Referral Agent');
                $sTbl->addBodyTr(HTMLTable::makeTh($uS->siteName . ' ' . $colTitle . ' Report', array('colspan'=>'4')));

                break;
        }

        if ($blanksOnly) {

            $dataTable = blanksOnly($dbh, $type, $whHosp, $hospList, $start, $end, $labels);
            $sTbl->addBodyTr(HTMLTable::makeTh('Missing ' . $colTitle . ' Assignments', array('colspan'=>'4')));

        } else {

            $dataTable = getRecords($dbh, $local, $type, $colTitle, $whHosp, $hospList, $start, $end, $labels);
        }

        $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
        $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital') . 's', array('class'=>'tdlabel')) . HTMLTable::makeTd($tdHosp) . HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . $tdAssoc);
        $settingstable = $sTbl->generateMarkup();

        $mkTable = 1;
    }
}

// Setups for the page.
$assocs = '';
if (count($aList) > 0) {
    $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($aList, $assocSelections),
            array('name'=>'selAssoc[]', 'size'=>'3', 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
}

$hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($hList, $hospitalSelections),
        array('name'=>'selHospital[]', 'size'=>'5', 'multiple'=>'multiple', 'style'=>'min-width:60px;'));



$monSize = 5;
if (count($hList) > 5) {

    $monSize = count($hList);

    if ($monSize > 12) {
        $monSize = 12;
    }
}

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>$monSize, 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, '2010', $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'5'));


?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

<script type="text/javascript">
    $(document).ready(function() {
        var makeTable = '<?php echo $mkTable; ?>';

        $('#btnHere, #btnExcel').button();

        if (makeTable === '1') {
            $('div#printArea').show();
            $('#divPrintButton').show();

            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });

        }

        $('#cbBlanksOnly').click(function () {
            if ($(this).prop('checked') === true) {
                $('#btnExcel').hide();

            } else {
                $('#btnExcel').show();

            }
        });

        if ($('#cbBlanksOnly').prop('checked') === true) {
            $('#btnExcel').hide();
        } else {
            $('#btnExcel').show();
        }

        $('.ckdate').datepicker({
            yearRange: '-05:+01',
            changeMonth: true,
            changeYear: true,
            autoSize: true,
            numberOfMonths: 1,
            dateFormat: 'M d, yy'
        });

        $('#selCalendar').change(function () {

            if ($(this).val() && $(this).val() != '19') {
                $('#selIntMonth').hide();
            } else {
                $('#selIntMonth').show();
            }

            if ($(this).val() && $(this).val() == '22') {
                $('#selIntYear').hide();
            } else {
                $('#selIntYear').show();
            }

            if ($(this).val() && $(this).val() != '18') {
                $('.dates').hide();
            } else {
                $('.dates').show();
                $('#selIntYear').hide();
            }
        });
        $('#selCalendar').change();
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) { echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="DRaHospReport.php" method="post">
                    <fieldset class="hhk-panel" style="margin-bottom: 15px;"><legend style='font-weight:bold;'>Report Type</legend>
                     <table style="width:100%">
                        <tr>
                        <?php if ($uS->Doctor) { ?>
                            <th><label for='rbd'>Doctors</label><input type="radio" id='rbd' name="rbReport" value="d" style='margin-left:.5em;' <?php if ($rptSetting == 'd') {echo 'checked="checked"';} ?>/></td>
                        <?php } if ($uS->ReferralAgent) { ?>
                            <th><label for='rbr'><?php echo $labels->getString('hospital', 'referralAgent', 'Referral Agent'); ?></label><input type="radio" id='rbr' name="rbReport" value="r" style='margin-left:.5em;' <?php if ($rptSetting == 'r') {echo 'checked="checked"';} ?>/></td>
                        <?php } ?>
                        </tr>
                    </table>
                    </fieldset>
                   <table id="tblTimePeriod" style="clear:left;float: left;">
                        <tr>
                            <th colspan="3">Time Period</th>
                        </tr>
                        <tr>
                            <th>Interval</th>
                            <th style="min-width:100px; ">Month</th>
                            <th>Year</th>
                        </tr>
                        <tr>
                            <td><?php echo $calSelector; ?></td>
                            <td><?php echo $monthSelector; ?></td>
                            <td><?php echo $yearSelector; ?></td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span class="dates" style="margin-right:.3em;">Start:</span>
                                <input type="text" value="<?php echo $txtStart; ?>" name="stDate" id="stDate" class="ckdate dates" style="margin-right:.3em;"/>
                                <span class="dates" style="margin-right:.3em;">End:</span>
                                <input type="text" value="<?php echo $txtEnd; ?>" name="enDate" id="enDate" class="ckdate dates"/></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2"><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>s</th>
                        </tr>
                        <?php if (count($aList) > 0) { ?><tr>
                            <th>Associations</th>
                            <th><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>s</th>
                        </tr><?php } ?>
                        <tr>
                            <?php if (count($aList) > 0) { ?><td><?php echo $assocs; ?></td><?php } ?>
                            <td><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <table style="clear:left; margin-top: 15px;">
                        <tr>
                            <td><input type="checkbox" name="cbBlanksOnly" id="cbBlanksOnly" <?php echo $cbBlank; ?>/><label for="cbBlanksOnly"> Only Show <?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>s without an assignment </label></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="divPrintButton" style="display:none;"><input id="printButton" value="Print" type="button" /></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox hhk-visitdialog" style="float:left;display:none; font-size: .9em; padding: 5px; padding-bottom:25px;">
                <div style="margin-bottom:.5em;"><?php echo $settingstable; ?></div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
