<?php
use HHK\SysConst\WebPageCode;
use HHK\SysConst\MemStatus;
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\AlertControl\AlertMessage;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\SecurityComponent;
use HHK\HTMLControls\HTMLContainer;
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLTable;
use HHK\Neon\TransferMembers;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLInput;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\Neon\RelationshipMapper;
use HHK\Member\BackgroundChecker\Subject;
use HHK\Member\BackgroundChecker\BkCkGateway;

/**
 * BackgroundCheck.php
 * Background checks to Alliance/Accio
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


try {
    // Do not add CSP.
    $wInit = new WebInit(WebPageCode::Page, FALSE);
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$pageHeader = $wInit->pageHeading;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();
$bgVendor = '';

$config = new Config_Lite(ciCFG_FILE);
$wsConfig = null;

if ($uS->UseBackgroundChecks != '') {
    $bgVendor = $uS->UseBackgroundChecks;


} else {
    exit('<h4>The Background-check feature is not configured. </h4>');
}

if (function_exists('curl_version') === FALSE) {
    exit('<h4>PHP configuration error: cURL functions are missing. </h4>');
}

$isGuestAdmin = SecurityComponent::is_Authorized('guestadmin');

$xmlfile = '';
$dataTable = '';
$errorMessage = '';
$noRecordsMsg = '';
$url = 'https://alliance.bgsecured.com/c/p/researcherxml';

if (isset($_POST['btnNewOrder'])) {

    $account = filter_input(INPUT_POST, 'account', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    $first = filter_input(INPUT_POST, 'subjFirst', FILTER_SANITIZE_STRING);
    $last = filter_input(INPUT_POST, 'subjLast', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'subjEmail', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'subjPhone', FILTER_SANITIZE_STRING);
    $id = filter_input(INPUT_POST, 'subjId', FILTER_SANITIZE_NUMBER_INT);

    $subject = new Subject($id, $first, $last, $phone, $email);

    $gw = new BkCkGateway($account, $username, $password);


    $xmlfile = $gw->newOrder($subject, $subject->getId());

    $dataTable = $gw->curlGateway($url, $xmlfile, $username, $password);

}


$inputTable = new HTMLTable();

$inputTable->addBodyTr(HTMLTable::makeTd('Account') . HTMLTable::makeTd(HTMLInput::generateMarkup('npsctest00', array('name'=>'account', 'type'=>'text'))));
$inputTable->addBodyTr(HTMLTable::makeTd('Username') . HTMLTable::makeTd(HTMLInput::generateMarkup('admin', array('name'=>'username', 'type'=>'text'))));
$inputTable->addBodyTr(HTMLTable::makeTd('Password') . HTMLTable::makeTd(HTMLInput::generateMarkup('kMp,f?Mf2@(i7HG5.yRX', array('name'=>'password', 'type'=>'password'))));
$inputTable->addBodyTr(HTMLTable::makeTd('URL') . HTMLTable::makeTd($url));

$inputTable->addBodyTr(HTMLTable::makeTd('id') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'subjId', 'type'=>'text'))));
$inputTable->addBodyTr(HTMLTable::makeTd('First') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'subjFirst', 'type'=>'text'))));
$inputTable->addBodyTr(HTMLTable::makeTd('Last') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'subjLast', 'type'=>'text'))));
$inputTable->addBodyTr(HTMLTable::makeTd('Email') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'subjEmail', 'type'=>'text'))));
$inputTable->addBodyTr(HTMLTable::makeTd('Phone') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'subjPhone', 'type'=>'text'))));



?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo FAVICON; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <style>
            .hhk-rowseparater { border-top: 2px #0074c7 solid !important; }
            #aLoginLink:hover {background-color: #337a8e; }
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

<script type="text/javascript">
function getSubject(item) {

	$('#subjFirst').val(item.first);
	$('#subjLast').val(item.last);
	$('#subjEmail').val(item.email);
	$('#subjPhone').val(item.phone);
	$('#subjId').val(item.id);

}
$(document).ready(function() {
	$('#btnNewOrder').button();
    createAutoComplete($('#txtSearch'), 3, {cmd: 'role', mode: 'mo'}, function (item) {getSubject(item);}, false);

});
</script>
    </head>
    <body <?php if ($wInit->testVersion) { echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>

            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="f1" action="BackgroundCheck.php" method="post">

				<table>
					<tr>
                       <th>Local (HHK) Name Search</th>
                        <td><input id="txtSearch" type="text" /></td>
                    </tr>
				</table>
		<?php echo $inputTable->generateMarkup(); ?>
		<input type='submit' value="New Order" name="btnNewOrder" id="btnNewOrder" style="margin:10px;" />
                </form>
                <div style="margin-top: 15px; margin-left:50px;" id="retrieve"><?php echo $noRecordsMsg; ?></div>
            </div>
            <div style="clear:both;"><pre><?php echo str_replace('<', '&lt', str_replace('>', '&gt', $xmlfile)); ?></pre></div>

            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox hhk-visitdialog" style="float:left; font-size: .8em; padding: 5px; padding-bottom:25px;">

                <div id="divTable">
                    <?php echo $dataTable; ?>
                </div>
            </div>
        </div>

    </body>
</html>
