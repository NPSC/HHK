<?php
/**
 * occDemo.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
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



$demos = array();

$whDemos = '';
$fields = '';

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

        $demos[$d[0]]['lookups'] = readGenLookupsPDO($dbh, $d[0], 'Order');
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

    foreach ($demos as $d) {

        if (isset($_POST['sel' . $d[0]])) {

            foreach ($_POST['sel' . $d[0]] as $k => $v) {

                $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

                if ($d[0] == 'Gender') {
                    $nameRS = new NameRS();
                } else {
                    $nameRS = new NameDemogRS();
                }

                $nameRS->idName->setStoredVal($id);
                $rows = EditRS::select($dbh, $nameRS, array($nameRS->idName));

                if (count($rows) === 1) {

                    EditRS::loadRow($rows[0], $nameRS);

                    $dbField = getDemographicField($d[0], $nameRS);

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
}


$query = "select distinct $fields
    n.idName,
    n.Name_Full
from
    name n
        join
    stays s ON s.idName = n.idName
        left join
    name_demog nd ON n.idName = nd.idName
where  n.Member_Status in ('a' , 'in', 'd')
        and $whDemos order by n.idName desc Limit 0, 25";

$stmt = $dbh->query($query);

$tbl = new HTMLTable();

// Rows
while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

    $tr = HTMLTable::makeTd($r['idName']) . HTMLTable::makeTd($r['Name_Full']);

    foreach ($demos as $d) {
        $tr.= HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($d['lookups'], ($r[$d[0]] == '' ? '' : $d['lookups'][$r[$d[0]]][0])), array('name' => 'sel' . $d[0] . '[' . $r['idName'] . ']')));
    }

    $tr .=  HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbUnkn[' . $r['idName'] . ']')), array('style'=>'text-align:center;'));
    $tbl->addBodyTr($tr);
}

$th = HTMLTable::makeTh("Id") . HTMLTable::makeTh("Name");

// Header
foreach ($demos as $d) {
    $th .= HTMLTable::makeTh($d[1]);
}

$tbl->addHeaderTr($th . HTMLTable::makeTh('Unknown'));

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
    <body <?php if ($testVersion) {echo "class='testbody'";} ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox  hhk-member-detail hhk-visitdialog" style="padding:25px;margin-top:15px;">
                <?php echo $form; ?>
            </div>
    </body>
</html>
