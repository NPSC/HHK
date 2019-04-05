<?php
/**
 * GiestDemog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("homeIncludes.php");
require(CLASSES . 'AuditLog.php');
require(DB_TABLES . "nameRS.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();



$demos = array();

$whDemos = '';
$fields = '';

$numRecords = 25;
$startAt = 0;

foreach (readGenLookupsPDO($dbh, 'Demographics') as $d) {

    if (strtolower($d[2]) == 'y') {

        if ($whDemos == '') {
            $whDemos = ' (';
        } else {
            $whDemos .= ' or ';
        }

        if ($d[0] == 'Gender') {
            $whDemos .= " n.`Gender` = '' ";
            $fields .= "ifnull(n.`Gender`,'') as `Gender`,";
        } else {
            $whDemos .= " nd.`" . $d[0] . "` = '' ";
            $fields .= "ifnull(nd.`" . $d[0] . "`, '') as `" . $d[0] . "`,";
        }

        $demos[$d[0]] = array(
            'title' => $d[1],
            'list' => removeOptionGroups(readGenLookupsPDO($dbh, $d[0], 'Order')),
        );
    }
}

$whDemos .= ") ";

function getDemographicField($tableName, $recordSet) {

    if ($tableName == 'Gender') {
        return $recordSet->Gender;
    } else {

        foreach ($recordSet as $k => $v) {

            if ($k == $tableName) {
                return $v;
            }
        }
    }

    return NULL;
}

if (isset($_POST['btnnotind'])) {

    foreach ($demos as $j => $d) {

        if (isset($_POST['sel' . $j])) {

            foreach ($_POST['sel' . $j] as $k => $v) {

                $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

                if ($j == 'Gender') {
                    $nameRS = new NameRS();
                } else {
                    $nameRS = new NameDemogRS();
                }

                $nameRS->idName->setStoredVal($id);
                $rows = EditRS::select($dbh, $nameRS, array($nameRS->idName));

                if (count($rows) === 1) {

                    EditRS::loadRow($rows[0], $nameRS);

                    $dbField = getDemographicField($j, $nameRS);

                    if (isset($_POST['cbUnkn'][$k])) {
                        $dbField->setNewVal('z');
                    } else {
                        $dbField->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
                    }

                    $nameRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                    $nameRS->Updated_By->setNewVal($uS->username);

                    $numRows = EditRS::update($dbh, $nameRS, array($nameRS->idName));

                    if ($numRows > 0) {
                        NameLog::writeUpdate($dbh, $nameRS, $nameRS->idName->getStoredVal(), $uS->username);
                        //$missing .= $nameRS->Name_Full->getStoredVal() . ",  ";
                    }
                }
            }
        }
    }

    $startAt = intval(filter_input(INPUT_POST, 'btnNext'), 10) + 50;

}






$query = "select distinct $fields
    n.idName,
    n.Name_Full,
    np.Name_Full as `Patient_Name`
from
    name_guest ng
        join
    name_demog nd ON ng.idName = nd.idName
        left join
    name n on n.idName = ng.idName
        left join
    psg p on ng.idPsg = p.idPsg
        left join
    name np on p.idPatient = np.idName
where  n.Member_Status in ('a' , 'in', 'd')
        and $whDemos order by n.idName desc Limit $startAt, 50";

$stmt = $dbh->query($query);

$tbl = new HTMLTable();

// Rows
while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

    $tr = HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $r['idName'], array('href'=>'GuestEdit.php?id='.$r['idName']))) . HTMLTable::makeTd($r['Name_Full']) . HTMLTable::makeTd($r['Patient_Name']);

    foreach ($demos as $k => $d) {
        $tr.= HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($d['list'], $r[$k]), array('name' => 'sel' . $k . '[' . $r['idName'] . ']')));
    }

    $tr .=  HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbUnkn[' . $r['idName'] . ']')), array('style'=>'text-align:center;'));
    $tbl->addBodyTr($tr);
}

$th = HTMLTable::makeTh("Id") . HTMLTable::makeTh("Name") . HTMLTable::makeTh("Patient");

// Header
foreach ($demos as $d) {
    $th .= HTMLTable::makeTh($d['title']);
}

$nextBtn = HTMLInput::generateMarkup("$startAt", array('name'=>'btnNext', 'type'=>'hidden'));

$tbl->addHeaderTr($th . HTMLTable::makeTh('Unknown'));

$saveBtn = HTMLInput::generateMarkup('Save/Next', array('name'=>'btnnotind', 'type'=>'submit', 'style'=>'margin:15px;float:right;'));

$form = HTMLContainer::generateMarkup('form', $tbl->generateMarkup(array(), "Shows 50 names at a time") . $saveBtn . $nextBtn, array('action'=>'GuestDemog.php', 'method'=>'post', 'name'=>'frmmissing'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function() {
        "use strict";
        $('#btnnotind').button();

    });
        </script>
    </head>
    <body <?php if ($testVersion) {echo "class='testbody'";} ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-member-detail hhk-visitdialog" style="font-size:.8em;padding:15px;margin-top:15px;">
                <?php echo $form; ?>
            </div>
    </body>
</html>
