<?php
/**
 * EventShells.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */

require("AdminIncludes.php");
require(DB_TABLES . 'volCalendarRS.php');

$wInit = new webInit();
$dbh = $wInit->dbh;
$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

function getDayControl($name, $data) {

    $attrs = array('name'=>$name, 'type'=>'checkbox', 'data-ckd' => $data);

    if ($data != 0) {
        $attrs['checked'] = 'checked';
    }

    return HTMLInput::generateMarkup('', $attrs);
}

// Catch callback
if (isset($_POST['txtTitle'])) {

    $idShells = filter_var_array($_POST['txtTitle'], FILTER_SANITIZE_STRING);

    foreach ($idShells as $idS => $s) {

        $shellRs = new ShellEventsRS();

        if ($idS > 0) {

            $shellRs->idShell->setStoredVal($idS);
            $rows = EditRS::select($dbh, $shellRs, array($shellRs->idShell));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $shellRs);
            } else {
                continue;
            }

            // Delete entry?
            if ($_POST['selCat'][$idS] == '' || $_POST['txtCode'][$idS] == '') {
                EditRS::delete($dbh, $shellRs, array($shellRs->idShell));
                continue;
            }

        } else {
            // Is hter a new item?
            if ($_POST['selCat'][0] == '' || $_POST['txtCode'][0] == '') {
                continue;
            }
        }

        $shellRs->Title->setNewVal($s);
        $shellRs->Description->setNewVal(filter_var($_POST['txtDesc'][$idS], FILTER_SANITIZE_STRING));
        $shellRs->Status->setNewVal(filter_var($_POST['selStatus'][$idS], FILTER_SANITIZE_STRING));
        $shellRs->Vol_Cat->setNewVal(filter_var($_POST['selCat'][$idS], FILTER_SANITIZE_STRING));
        $shellRs->Vol_Code->setNewVal(filter_var($_POST['txtCode'][$idS], FILTER_SANITIZE_STRING));
        $shellRs->Shell_Color->setNewVal(filter_var($_POST['txtColor'][$idS], FILTER_SANITIZE_STRING));
        $shellRs->Date_Start->setNewVal(filter_var($_POST['txtStartDate'][$idS], FILTER_SANITIZE_STRING));

        $stTime = filter_var($_POST['selStartHour'][$idS], FILTER_SANITIZE_STRING) . ':' . filter_var($_POST['selStartMinutes'][$idS], FILTER_SANITIZE_STRING);
        $shellRs->Time_Start->setNewVal($stTime);
        $enTime = filter_var($_POST['selEndHour'][$idS], FILTER_SANITIZE_STRING) . ':' . filter_var($_POST['selEndMinutes'][$idS], FILTER_SANITIZE_STRING);
        $shellRs->Time_End->setNewVal($enTime);

        $shellRs->Sun->setNewVal(isset($_POST['rbSun'][$idS]) ? 1 : 0);
        $shellRs->Mon->setNewVal(isset($_POST['rbMon'][$idS]) ? 1 : 0);
        $shellRs->Tue->setNewVal(isset($_POST['rbTue'][$idS]) ? 1 : 0);
        $shellRs->Wed->setNewVal(isset($_POST['rbWed'][$idS]) ? 1 : 0);
        $shellRs->Thu->setNewVal(isset($_POST['rbThu'][$idS]) ? 1 : 0);
        $shellRs->Fri->setNewVal(isset($_POST['rbFri'][$idS]) ? 1 : 0);
        $shellRs->Sat->setNewVal(isset($_POST['rbSat'][$idS]) ? 1 : 0);

        // Save
        if ($idS > 0) {
            EditRS::update($dbh, $shellRs, array($shellRs->idShell));
        } else {
            EditRS::insert($dbh, $shellRs);
        }
    }
}


// Create markup
$tbl = new HTMLTable();
$dayAttrs = array('style'=>'text-align:center');
$gattrs = readGenLookupsPDO($dbh, 'E_Shell_Status');

// Get Vol Categories
$stmtCat = $dbh->query("SELECT `Code`, `Description`, '' FROM `gen_lookups` WHERE `Table_Name` = 'Vol_Category';");

$vattrs = array();
$typeDump = '';

while ($row = $stmtCat->fetch(\PDO::FETCH_NUM)) {
    // Dont get Vol_Type category.
    if ($row[0] != 'Vol_Type') {
        $vattrs[$row[0]] = $row;

        // Make its' type options
        $stmtType = $dbh->query("SELECT `Code`, `Description`, '' FROM `gen_lookups` WHERE `Table_Name` = '$row[0]';");
        $typeRows = '';

        while ($r = $stmtType->fetch(\PDO::FETCH_NUM)) {
            $typeRows .= HTMLContainer::generateMarkup('li', $r[0] . ': ' . $r[1]);
        }

        $typeDump .= HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('p', $row[1]). HTMLContainer::generateMarkup('ul', $typeRows, array('style'=>'margin:5px')), array('style'=>'float:left;margin-right:5px;', 'class'=>'ui-widget ui-widget-content ui-corner-all hhk-member-detail'));

    }
}

// Time of day options
$hourOptions = array(
    0 => array(0=>'0', 1=>'00'),
    1 => array(0=>1, 1=>'01'),
    2 => array(0=>2, 1=>'02'),
    3 => array(0=>3, 1=>'03'),
    4 => array(0=>4, 1=>'04'),
    5 => array(0=>5, 1=>'05'),
    6 => array(0=>6, 1=>'06'),
    7 => array(0=>7, 1=>'07'),
    8 => array(0=>8, 1=>'08'),
    9 => array(0=>9, 1=>'09'),
    10 => array(0=>10, 1=>'10'),
    11 => array(0=>11, 1=>'11'),
    12 => array(0=>12, 1=>'12'),
    13 => array(0=>13, 1=>'13'),
    14 => array(0=>14, 1=>'14'),
    15 => array(0=>15, 1=>'15'),
    16 => array(0=>16, 1=>'16'),
    17 => array(0=>17, 1=>'17'),
    18 => array(0=>18, 1=>'18'),
    19 => array(0=>19, 1=>'19'),
    20 => array(0=>20, 1=>'20'),
    21 => array(0=>21, 1=>'21'),
    22 => array(0=>22, 1=>'22'),
    23 => array(0=>23, 1=>'23'),

);

    // Time of day options
$minuteOptions = array(
    0 => array(0=>'00', 1=>'00'),
    15 => array(0=>15, 1=>'15'),
    30 => array(0=>30, 1=>'30'),
    45 => array(0=>45, 1=>'45'),
);

$query = "Select * from shell_events order by Vol_Cat";
$stmt = $dbh->query($query);

// peruse the rows
while ($rw = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $tr = HTMLTable::makeTd(HTMLInput::generateMarkup($rw['Title'], array('name'=>'txtTitle['. $rw['idShell'] . ']', 'size'=>'10')));
    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($rw['Description'], array('name'=>'txtDesc['. $rw['idShell'] . ']', 'size'=>'10')));
    $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($gattrs, $rw['Status'], FALSE), array('name'=>'selStatus['. $rw['idShell'] . ']')));

    $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($vattrs, $rw['Vol_Cat'], TRUE), array('name'=>'selCat['. $rw['idShell'] . ']')));
    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($rw['Vol_Code'], array('name'=>'txtCode['. $rw['idShell'] . ']', 'size'=>'5')));

    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($rw['Shell_Color'], array('name'=>'txtColor['. $rw['idShell'] . ']', 'size'=>'10')));
    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup(date('M j, Y', strtotime($rw['Date_Start'])), array('name'=>'txtStartDate['. $rw['idShell'] . ']', 'class'=>'ckdate')));

    // Times
    if (($startTimeDT = new DateTime($rw['Time_Start'])) === FALSE) {
        $startTimeDT = new DateTime();
        $startTimeDT->setTime(0, 0, 0);
    }

    $tr .= HTMLTable::makeTd(
            HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hourOptions, $startTimeDT->format('H'), FALSE), array('name'=>'selStartHour['. $rw['idShell'] . ']'))
            .':'.HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($minuteOptions, $startTimeDT->format('i'), FALSE), array('name'=>'selStartMinutes['. $rw['idShell'] . ']'))
    );

    if (($endTimeDT = new DateTime($rw['Time_End'])) === FALSE) {
        $endTimeDT = new DateTime();
        $endTimeDT->setTime(0, 0, 0);
    }

    $tr .= HTMLTable::makeTd(
            HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hourOptions, $endTimeDT->format('H'), FALSE), array('name'=>'selEndHour['. $rw['idShell'] . ']'))
            .':'.HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($minuteOptions, $endTimeDT->format('i'), FALSE), array('name'=>'selEndMinutes['. $rw['idShell'] . ']'))
    );

    // Days
    $tr .= HTMLTable::makeTd(getDayControl('rbSun['. $rw['idShell'] . ']', (ord( $rw['Sun'] ) === 1 ?: (ord( $rw['Sun'] ) === 0 ? false : (bool) $rw['Sun']))), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbMon['. $rw['idShell'] . ']', (ord( $rw['Mon'] ) === 1 ?: (ord( $rw['Mon'] ) === 0 ? false : (bool) $rw['Mon']))), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbTue['. $rw['idShell'] . ']', (ord( $rw['Tue'] ) === 1 ?: (ord( $rw['Tue'] ) === 0 ? false : (bool) $rw['Tue']))), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbWed['. $rw['idShell'] . ']', (ord( $rw['Wed'] ) === 1 ?: (ord( $rw['Wed'] ) === 0 ? false : (bool) $rw['Wed']))), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbThu['. $rw['idShell'] . ']', (ord( $rw['Thu'] ) === 1 ?: (ord( $rw['Thu'] ) === 0 ? false : (bool) $rw['Thu']))), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbFri['. $rw['idShell'] . ']', (ord( $rw['Fri'] ) === 1 ?: (ord( $rw['Fri'] ) === 0 ? false : (bool) $rw['Fri']))), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbSat['. $rw['idShell'] . ']', (ord( $rw['Sat'] ) === 1 ?: (ord( $rw['Sat'] ) === 0 ? false : (bool) $rw['Sat']))), $dayAttrs);

    $tbl->addBodyTr($tr);

}

// New
    $tr = HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtTitle[0]', 'size'=>'10')));
    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtDesc[0]', 'size'=>'10')));
    $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($gattrs, 'a', FALSE), array('name'=>'selStatus[0]')));

    $tr .= HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($vattrs, ''), array('name'=>'selCat[0]')));
    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtCode[0]', 'size'=>'5')));

    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtColor[0]', 'size'=>'10')));
    $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtStartDate[0]', 'class'=>'ckdate')));

    // Times
    $tr .= HTMLTable::makeTd(
            HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hourOptions, ''), array('name'=>'selStartHour[0]'))
            .':'.HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($minuteOptions, '', FALSE), array('name'=>'selStartMinutes[0]'))
    );

    $tr .= HTMLTable::makeTd(
            HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hourOptions, ''), array('name'=>'selEndHour[0]'))
            .':'.HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($minuteOptions, '', FALSE), array('name'=>'selEndMinutes[0]'))
    );

    // Days
    $tr .= HTMLTable::makeTd(getDayControl('rbSun[0]', '0'), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbMon[0]', '0'), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbTue[0]', '0'), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbWed[0]', '0'), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbThu[0]', '0'), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbFri[0]', '0'), $dayAttrs);
    $tr .= HTMLTable::makeTd(getDayControl('rbSat[0]', '0'), $dayAttrs);

    $tbl->addBodyTr(HTMLTable::makeTd('New Entry', array('colspan'=>'16')));
    $tbl->addBodyTr($tr);


$tbl->addHeaderTr(
        HTMLTable::makeTh('Title')
        .HTMLTable::makeTh('Description')
        .HTMLTable::makeTh('Active')
        .HTMLTable::makeTh('Category')
        .HTMLTable::makeTh('Type')
        .HTMLTable::makeTh('Shell Color')
        .HTMLTable::makeTh('Starting Date')
        .HTMLTable::makeTh('Start Time')
        .HTMLTable::makeTh('End Time')
        .HTMLTable::makeTh('Sun')
        .HTMLTable::makeTh('Mon')
        .HTMLTable::makeTh('Tue')
        .HTMLTable::makeTh('Wed')
        .HTMLTable::makeTh('Thu')
        .HTMLTable::makeTh('Fri')
        .HTMLTable::makeTh('Sat')
);

$evtShellMarkup = $tbl->generateMarkup(array('id'=>'dataTbl'));


?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        
        <script type="text/javascript">
    $(document).ready(function() {
        try {
            if ($('#dataTbl').length > 0) {
                $('#dataTbl').dataTable({"iDisplayLength": 10,
                    "aLengthMenu": [[10, 20, -1], [10, 20, "All"]]
                    , "Dom": '<"top"ilf>rt<"bottom"ip>'
                });
            }
        } catch (err) { }

        $( "input.ckdate" ).datepicker({
            changeMonth: true,
            changeYear: true,
            autoSize: true,
            dateFormat: 'M d, yy'
        });
        $('#btnSubmit').button();
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">

            <h1><?php echo $wInit->pageHeading; ?></h1>
            <form method="post">
            <div id="tabs-3" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <?php echo $typeDump ?>
                <?php echo $evtShellMarkup ?>
                <input type="submit" id="btnSubmit" name="btnSubmit" value="Save" style="float:right; margin:5px;" />
            </div>
            </form>
        </div>
    </body>
</html>
