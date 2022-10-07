<?php
/**
 * DRaHospReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
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
use HHK\sec\Labels;
use HHK\House\Report\ReportFilter;

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

$isGuestAdmin = SecurityComponent::is_Authorized('guestadmin');

$labels = Labels::getLabels();


$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();

function getRecords(\PDO $dbh, $local, $type, $colNameTitle, $whClause, ReportFilter $filter, $labels) {

    if ($type == VolMemberType::Doctor) {
        $Id = 'idDoctor';
    } else if ($type == VolMemberType::ReferralAgent) {
        $Id = 'idReferralAgent';
    }

    $query = "select hs.$Id as `Id`, concat(n.Name_Last, ', ', n.Name_First) as `FirstLast`, ifnull(hs.idHospital, 'Sub Total') as `Hospital`, count(hs.idHospital_stay) as `Patients`
from hospital_stay hs left join `name` n  ON hs.$Id = n.idName
left join reservation rv on hs.idHospital_stay = rv.idHospital_Stay
where rv.`Status` in ('" . ReservationStatus::Checkedout . "', '" . ReservationStatus::Staying . "') "
 . " and DATE(ifnull(rv.Actual_Departure, rv.Expected_Departure)) >= DATE('".$filter->getReportStart()."') and DATE(ifnull(rv.Actual_Arrival, rv.Expected_Arrival)) < DATE('".$filter->getQueryEnd()."')  $whClause
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
//    $hospitals = $filter->getHospitals();

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
            if (isset($filter->getHospitals()[$r['Hospital']])) {
                $hosp = $filter->getHospitals()[$r['Hospital']][1];
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

            if (isset($filter->getHospitals()[$r['Hospital']])) {

                $hosp = $filter->getHospitals()[$r['Hospital']][1];

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


function blanksOnly(\PDO $dbh, $type, $whClause, ReportFilter $filter, $labels) {

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

    $query = "select hs.idPatient as `Id`, n.Name_Full as `$nameCol Name`, h.Title as `$hospitalCol`, hs.idPsg
from hospital_stay hs left join `name` n on hs.idPatient = n.idName
left join reservation rv on hs.idHospital_stay = rv.idHospital_Stay
left join hospital h on h.idHospital = hs.idHospital
where hs.$Id = 0 and rv.`Status` in ('" . ReservationStatus::Checkedout . "', '" . ReservationStatus::Staying . "') "
 . " and DATE(ifnull(rv.Actual_Departure, rv.Expected_Departure)) >= DATE('".$filter->getReportStart()."') and DATE(ifnull(rv.Actual_Arrival, rv.Expected_Arrival)) < DATE('".$filter->getQueryEnd()."') $whClause";

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

$mkTable = '';

$type = '';
$cbBlank = '';
$dataTable = '';
$settingstable = '';

$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$errorMessage = '';

// doctors
if ($uS->Doctor) {
    $rptSetting = 'd';
} else {
    $rptSetting = 'r';
}

if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // gather input

    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();

    // Hospitals
    $whHosp = '';
    foreach ($filter->getSelectedHosptials() as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
            } else {
                $whHosp .= ",". $a;
            }
        }
    }

    $whAssoc = '';
    foreach ($filter->getSelectedAssocs() as $a) {
        if ($a != '') {
            if ($whAssoc == '') {
                $whAssoc .= $a;
            } else {
                $whAssoc .= ",". $a;
            }
        }
    }
    if ($whHosp != '') {
        $whHosp = " and hs.idHospital in (".$whHosp.") ";
    }

    if ($whAssoc != '') {
        $whAssoc = " and hs.idAssociation in (".$whAssoc.") ";
    }

    $whHosp .= $whAssoc;

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

            $dataTable = blanksOnly($dbh, $type, $whHosp, $filter, $labels);
            $sTbl->addBodyTr(HTMLTable::makeTh('Missing ' . $colTitle . ' Assignments', array('colspan'=>'4')));

        } else {

            $dataTable = getRecords($dbh, $local, $type, $colTitle, $whHosp, $filter, $labels);
        }

        $hospitalTitles = $filter->getSelectedHospitalsString();
        $assocTitles = $filter->getSelectedAssocString();

        $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($filter->getReportStart()))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($filter->getReportEnd()))));
        $sTbl->addBodyTr(HTMLTable::makeTd($labels->getString('hospital', 'hospital', 'Hospital') . 's', array('class'=>'tdlabel')) . HTMLTable::makeTd($hospitalTitles) . HTMLTable::makeTd('Associations', array('class'=>'tdlabel')) . HTMLTable::makeTd($assocTitles));
        $settingstable = $sTbl->generateMarkup();

        $mkTable = 1;
    }
}

// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup(array('style'=>'display: inline-block; vertical-align: top; margin-right:5px;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'display: inline-block; vertical-align: top;'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

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
            yearRange: '<?php echo $uS->StartYear; ?>:+01',
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
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px; margin: 10px 0;">
                <form id="fcat" action="DRaHospReport.php" method="post">
                    <fieldset class="hhk-panel" style="margin-bottom: 15px;"><legend style='font-weight:bold;'>Report Type</legend>
                     <table style="width:100%">
                        <tr>
                        <?php if ($uS->Doctor) { ?>
                            <th><label for='rbd'>Doctors</label><input type="radio" id='rbd' name="rbReport" value="d" style='margin-left:.5em;' <?php if ($rptSetting == 'd') {echo 'checked="checked"';} ?>/></th>
                        <?php } if ($uS->ReferralAgent) { ?>
                            <th><label for='rbr'><?php echo $labels->getString('hospital', 'referralAgent', 'Referral Agent'); ?></label><input type="radio" id='rbr' name="rbReport" value="r" style='margin-left:.5em;' <?php if ($rptSetting == 'r') {echo 'checked="checked"';} ?>/></th>
                        <?php } ?>
                        </tr>
                    </table>
                    </fieldset>
                    <div class="filters">
						<?php
						  echo $timePeriodMarkup;
						  if (count($filter->getHospitals()) > 1) {
                            echo $hospitalMarkup;
                          }
                        ?>
                    </div>
                    <table style="margin-top: 15px;">
                        <tr>
                            <td><input type="checkbox" name="cbBlanksOnly" id="cbBlanksOnly" <?php echo $cbBlank; ?>/><label for="cbBlanksOnly"> Only Show <?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>s without an assignment </label></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="divPrintButton" style="display:none; margin-bottom: 10px;"><input id="printButton" value="Print" type="button" /></div>
            <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog" style="float:left;display:none; font-size: .9em; padding: 5px; padding-bottom:25px;">
                <div style="margin-bottom:.5em;"><?php echo $settingstable; ?></div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
