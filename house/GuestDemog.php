<?php
/**
 * occDemo.php
 *
 * @category  Report
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require("homeIncludes.php");
require(CLASSES . 'AuditLog.php');

require(DB_TABLES . "nameRS.php");

$wInit = new webInit();
$wInit->sessionLoadGenLkUps();


$dbh = $wInit->dbh;

$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();



$missing = "";

if (isset($_POST['selGender'])) {

    foreach ($_POST['selGender'] as $k => $v) {

        $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

        $nameRS = new NameRS();
        $nameRS->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $nameRS, array($nameRS->idName));

        if (count($rows) === 1) {

            EditRS::loadRow($rows[0], $nameRS);

            if (isset($_POST['cbUnkn'][$k])) {
                $nameRS->Gender->setNewVal('z');
            } else {
                $nameRS->Gender->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
            }

            $nameRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $nameRS->Updated_By->setNewVal($uS->username);

            $numRows = EditRS::update($dbh, $nameRS, array($nameRS->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $nameRS, $nameRS->idName->getStoredVal(), $uS->username);
                $missing .= $nameRS->Name_Full->getStoredVal() . ",  ";
            }
        }
    }
}

if (isset($_POST['selAge'])) {

    foreach ($_POST['selAge'] as $k => $v) {

        $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

        $demoRs = new NameDemogRS();
        $demoRs->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $demoRs, array($demoRs->idName));

        if (count($rows) === 1) {
            EditRS::loadRow($rows[0], $demoRs);

            if (isset($_POST['cbUnkn'][$k])) {
                $demoRs->Age_Bracket->setNewVal('z');
            } else {
                $demoRs->Age_Bracket->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
            }

            $demoRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $demoRs->Updated_By->setNewVal($uS->username);

            $numRows = EditRS::update($dbh, $demoRs, array($demoRs->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $demoRs, $demoRs->idName->getStoredVal(), $uS->username);
            }
        }
    }
}

if (isset($_POST['selEthnicity'])) {

    foreach ($_POST['selEthnicity'] as $k => $v) {

        $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

        $demoRs = new NameDemogRS();
        $demoRs->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $demoRs, array($demoRs->idName));

        if (count($rows) === 1) {
            EditRS::loadRow($rows[0], $demoRs);

            if (isset($_POST['cbUnkn'][$k])) {
                $demoRs->Ethnicity->setNewVal('z');
            } else {
                $demoRs->Ethnicity->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
            }

            $demoRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $demoRs->Updated_By->setNewVal($uS->username);

            $numRows = EditRS::update($dbh, $demoRs, array($demoRs->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $demoRs, $demoRs->idName->getStoredVal(), $uS->username);
            }
        }
    }
}

if (isset($_POST['selIncome'])) {

    foreach ($_POST['selIncome'] as $k => $v) {

        $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

        $demoRs = new NameDemogRS();
        $demoRs->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $demoRs, array($demoRs->idName));

        if (count($rows) === 1) {
            EditRS::loadRow($rows[0], $demoRs);

            if (isset($_POST['cbUnkn'][$k])) {
                $demoRs->Income_Bracket->setNewVal('z');
            } else {
                $demoRs->Income_Bracket->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
            }

            $demoRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $demoRs->Updated_By->setNewVal($uS->username);

            $numRows = EditRS::update($dbh, $demoRs, array($demoRs->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $demoRs, $demoRs->idName->getStoredVal(), $uS->username);
            }
        }
    }
}

if (isset($_POST['selEducation'])) {

    foreach ($_POST['selEducation'] as $k => $v) {

        $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

        $demoRs = new NameDemogRS();
        $demoRs->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $demoRs, array($demoRs->idName));

        if (count($rows) === 1) {
            EditRS::loadRow($rows[0], $demoRs);

            if (isset($_POST['cbUnkn'][$k])) {
                $demoRs->Education_Level->setNewVal('z');
            } else {
                $demoRs->Education_Level->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
            }

            $demoRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $demoRs->Updated_By->setNewVal($uS->username);

            $numRows = EditRS::update($dbh, $demoRs, array($demoRs->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $demoRs, $demoRs->idName->getStoredVal(), $uS->username);
            }
        }
    }
}

if (isset($_POST['selMedia'])) {

    foreach ($_POST['selMedia'] as $k => $v) {

        $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

        $demoRs = new NameDemogRS();
        $demoRs->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $demoRs, array($demoRs->idName));

        if (count($rows) === 1) {
            EditRS::loadRow($rows[0], $demoRs);

            if (isset($_POST['cbUnkn'][$k])) {
                $demoRs->Media_Source->setNewVal('z');
            } else {
                $demoRs->Media_Source->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
            }

            $demoRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $demoRs->Updated_By->setNewVal($uS->username);

            $numRows = EditRS::update($dbh, $demoRs, array($demoRs->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $demoRs, $demoRs->idName->getStoredVal(), $uS->username);
            }
        }
    }
}

if (isset($_POST['selSpecial'])) {

    foreach ($_POST['selSpecial'] as $k => $v) {

        $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

        $demoRs = new NameDemogRS();
        $demoRs->idName->setStoredVal($id);
        $rows = EditRS::select($dbh, $demoRs, array($demoRs->idName));

        if (count($rows) === 1) {
            EditRS::loadRow($rows[0], $demoRs);

            if (isset($_POST['cbUnkn'][$k])) {
                $demoRs->Special_Needs->setNewVal('z');
            } else {
                $demoRs->Special_Needs->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
            }

            $demoRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $demoRs->Updated_By->setNewVal($uS->username);

            $numRows = EditRS::update($dbh, $demoRs, array($demoRs->idName));

            if ($numRows > 0) {
                NameLog::writeUpdate($dbh, $demoRs, $demoRs->idName->getStoredVal(), $uS->username);
            }
        }
    }
}



$demos = readGenLookupsPDO($dbh, 'Demographics');

$whDemos = '';

foreach ($demos as $d) {
    if (strtolower($d[2]) == 'y') {

        if ($whDemos == '') {
            $whDemos = " (";
        } else {
            $whDemos .= " or ";
        }

        if ($d[0] == 'g') {
            $whDemos .= " n." . $d[1] . " = '' ";
        } else {
            $whDemos .= " nd." . $d[1] . " = '' ";
        }
    }
}

$whDemos .= ") ";

$whRel = '';

if ($uS->PatientAsGuest === FALSE) {
    " ng.Relationship_Code != 'slf' and ";
}


$query = "select distinct
    n.idName,
    n.Name_Full,
    n.Gender,
    ifnull(nd.Age_Bracket, '') as Age_Bracket,
    ifnull(nd.Ethnicity, '') as Ethnicity,
    ifnull(nd.Income_Bracket, '') as Income_Bracket,
    ifnull(nd.Media_Source, '') as Media_Source,
    ifnull(nd.Special_Needs, '') as Special_Needs,
    ifnull(nd.Education_Level, '') as Education_Level
from
    name_guest ng
        join
    stays s ON s.idName = ng.idName
        left join
    name_demog nd ON ng.idName = nd.idName
        left join
    name n ON ng.idName = n.idName
where
    $whRel
        n.Member_Status in ('a' , 'in', 'd')
        and $whDemos order by n.idName desc Limit 0, 25";

$stmt = $dbh->query($query);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$tbl = new HTMLTable();

$incomes = readGenLookupsPDO($dbh, 'Income_Bracket');
$educationLevels = readGenLookupsPDO($dbh, 'Education_Level');
$mediaSources = readGenLookupsPDO($dbh, 'Media_Source');
$specials = readGenLookupsPDO($dbh, 'Special_Needs');


foreach ($rows as $r) {

    $tbl->addBodyTr(
            HTMLTable::makeTd($r['idName'])
            . HTMLTable::makeTd($r['Name_Full'])
            . (strtolower($demos['g'][2]) == 'y' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($uS->nameLookups[GL_TableNames::Gender], $r['Gender']), array('name' => 'selGender[' . $r['idName'] . ']'))) : '')
            . (strtolower($demos['a'][2]) == 'y' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($uS->nameLookups[GL_TableNames::AgeBracket], $r['Age_Bracket']), array('name' => 'selAge[' . $r['idName'] . ']'))) : '')
            . (strtolower($demos['e'][2]) == 'y' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($uS->nameLookups[GL_TableNames::Ethnicity], $r['Ethnicity']), array('name' => 'selEthnicity[' . $r['idName'] . ']'))) : '')
            . (strtolower($demos['i'][2]) == 'y' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($incomes, $r['Income_Bracket']), array('name' => 'selIncome[' . $r['idName'] . ']'))) : '')
            . (strtolower($demos['1'][2]) == 'y' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($educationLevels, $r['Education_Level']), array('name' => 'selEducation[' . $r['idName'] . ']'))) : '')
            . (strtolower($demos['ms'][2]) == 'y' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($mediaSources, $r['Media_Source']), array('name' => 'selMedia[' . $r['idName'] . ']'))) : '')
            . (strtolower($demos['sn'][2]) == 'y' ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($specials, $r['Special_Needs']), array('name' => 'selSpecial[' . $r['idName'] . ']'))) : '')
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbUnkn[' . $r['idName'] . ']')), array('style'=>'text-align:center;'))
    );
}

$tbl->addHeaderTr(HTMLTable::makeTh("Id") . HTMLTable::makeTh("Name")
        . (strtolower($demos['g'][2]) == 'y' ? HTMLTable::makeTh("Gender") : '')
        . (strtolower($demos['a'][2]) == 'y' ? HTMLTable::makeTh("Age Range") : '')
        . (strtolower($demos['e'][2]) == 'y' ? HTMLTable::makeTh("Ethnicity") : '')
        . (strtolower($demos['i'][2]) == 'y' ? HTMLTable::makeTh("Income Bracket") : '')
        . (strtolower($demos['1'][2]) == 'y' ? HTMLTable::makeTh("Education Level") : '')
        . (strtolower($demos['ms'][2]) == 'y' ? HTMLTable::makeTh("Media Source") : '')
        . (strtolower($demos['sn'][2]) == 'y' ? HTMLTable::makeTh("Special Needs") : '')
        . HTMLTable::makeTh("Set Unknown")
        );
$saveBtn = HTMLInput::generateMarkup('Save', array('name'=>'btnnotind', 'type'=>'submit', 'style'=>'margin:15px;float:right;'));
$form = HTMLContainer::generateMarkup('form', $tbl->generateMarkup(array(), "- Shows only 25 names at a time -") . $saveBtn, array('action'=>'GuestDemog.php', 'method'=>'post', 'name'=>'frmmissing'));


?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo TOP_NAV_CSS; ?>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
         <script type="text/javascript">
    $(document).ready(function() {
        "use strict";
        $('#btnnotind').button();
    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox  hhk-member-detail hhk-visitdialog" style="padding:25px;margin-top:15px;">
                <?php echo $form; ?>
            </div>
    </body>
</html>
