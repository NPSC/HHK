<?php

use HHK\House\Distance\DistanceFactory;
use HHK\House\Distance\GoogleDistance;
use HHK\sec\{
    SecurityComponent,
    Session,
    WebInit,
    Labels,
    SAML
};
use HHK\SysConst\{
    WebRole,
    CodeVersion
};
use HHK\Update\{
    SiteConfig,
    UpdateSite,
    SiteLog,
    Patch
};
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\{
    HTMLContainer,
    HTMLSelector,
    HTMLTable
};
use HHK\Exception\UploadException;
use HHK\CrmExport\AbstractExportManager;

/**
 * Configure.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require "AdminIncludes.php";
require FUNCTIONS . 'mySqlFunc.php';

try {
    $wInit = new webInit();
} catch (\Exception $exw) {
    die($exw->getMessage());
}

// get session instance
$uS = Session::getInstance();

// Kick out 'Guest' Users
if ($uS->rolecode > WebRole::WebUser) {
    exit("Not Authorized");
}


$dbh = $wInit->dbh;

$resultMsg = '';
$errorMsg = '';
$tabIndex = 0;
$resultAccumulator = '';
$ccResultMessage = '';
$holResultMessage = '';
$externalErrMsg = '';

$CmsManager = NULL;
$rteFileSelection = '';
$rteMsg = '';
$confError = '';

$labl = Labels::getLabels();

if ($uS->ContactManager !== '') {
    $CmsManager = AbstractExportManager::factory($dbh, $uS->ContactManager);
}

if (filter_has_var(INPUT_POST, "btnSiteCnf") || filter_has_var(INPUT_POST, "btnLocalAuth")) {

    $notymsg = SiteConfig::saveSysConfig($dbh, $_POST);

    if (filter_has_var(INPUT_POST, "btnLocalAuth")) {
        $tabIndex = 3;
    }
}

if (filter_has_var(INPUT_POST, "btnLabelCnf")) {

    $tabIndex = 7;

    $notymsg = SiteConfig::saveLabels($dbh, $_POST);
}

// Save web service configurtion
if (filter_has_var(INPUT_POST, "btnExtCnf") && $CmsManager !== NULL) {

    $tabIndex = 9;

    try {
        $externalErrMsg = $CmsManager->saveConfig($dbh);
    } catch (UploadException $ex) {
        $externalErrMsg = "Save Configuration Error: " . $ex->getMessage();
    }

    if ($externalErrMsg != '') {
        $externalErrMsg = HTMLContainer::generateMarkup('p', $externalErrMsg, array('class'=>'ui-state-error'));
    }
}

// Patch tab, button: Update COnfig
if (filter_has_var(INPUT_POST, 'btnUpdate')) {

    $tabIndex = 1;

    if (SecurityComponent::is_TheAdmin()) {

        $update = new UpdateSite();

        $update->doUpdate($dbh);
        $errorMsg .= $update->getErrorMsg();
        $resultAccumulator .= $update->getResultAccumulator();
    } else {
        $errorMsg .= 'This user does not enjoy site update priviledges.';
    }
}

// Zip code file
if (isset($_FILES['zipfile'])) {
    $tabIndex = 6;

    try {

        SiteConfig::checkUploadFile('zipfile');

        $resultMsg .= SiteConfig::loadZipCodeFile($dbh, $_FILES['zipfile']['tmp_name']);

        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Loaded. ' . $resultMsg, CodeVersion::VERSION . '.' . CodeVersion::BUILD);
    } catch (\Exception $hex) {
        $resultMsg .= $hex->getMessage();
        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Failed. ' . $resultMsg, CodeVersion::VERSION . '.' . CodeVersion::BUILD);
    }
}

// Patch Tab, button: Re-Create Tables (if not exists), Views and SP's
if (filter_has_var(INPUT_POST, 'btnSaveSQL')) {

    $tabIndex = 1;

    $patch = new Patch();

    // Update Tables
    $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllTables.sql', "Tables");
    foreach ($patch->results as $err) {

        if ($err['errno'] == 1091 || $err['errno'] == 1061) {  // key not exist, Duplicate Key name
            continue;
        }

        $errorMsg .= 'Create Table Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
    }


    $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllViews.sql', 'Views');
    foreach ($patch->results as $err) {
        $errorMsg .= 'Create View Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
    }

    $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllRoutines.sql', 'Stored Procedures', '$$', '-- ;');
    foreach ($patch->results as $err) {
        $errorMsg .= 'Create Stored Procedures Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
    }


    // Log update.
    $logText = "Save SQL.  " . $resultAccumulator;
    SiteLog::writeLog($dbh, 'DB', $logText, CodeVersion::VERSION . '.' . CodeVersion::BUILD);
}

// Payment credentials
if (filter_has_var(INPUT_POST, 'btnPay')) {
    $tabIndex = 2;
    $ccResultMessage = SiteConfig::savePaymentCredentials($dbh, $_POST);

    unset($uS->nameLookups);
    $wInit->reloadGenLkUps($uS);
}

// Patch Log
$logs = '';
if (filter_has_var(INPUT_POST, 'btnLogs')) {
    $tabIndex = 1;

    $stmt = $dbh->query("Select * from syslog order by Timestamp DESC Limit 100;");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $edRows = array();

    foreach ($rows as $r) {

        $r['Date'] = date('M j, Y H:i:s', strtotime($r['Timestamp']));
        unset($r['Timestamp']);

        $edRows[] = $r;
    }

    $logs = CreateMarkupFromDB::generateHTML_Table($edRows, 'syslog');
}

$logSelRows = [
    ['sl','Combined Log'],
    ['ss','Sys Config Log'],
    ['rr','Rooms Log'],
    ['ll','Lookups Log'],
    ['nl', 'Notification Log'],
    ['api', 'HHK API Access Log']
];

try {
    $payments = SiteConfig::createPaymentCredentialsMarkup($dbh, $ccResultMessage);
} catch (\Exception $pex) {
    $payments = 'Error: ' . $pex->getMessage();
}

if (filter_has_var(INPUT_POST, 'saveHolidays')) {
    $tabIndex = 5;
    try{
        echo json_encode(["success"=>SiteConfig::saveHolidays($dbh, $_POST, $uS->username)]);
    }catch(\Exception $e){
        echo json_encode(["error"=>$e->getMessage()]);
    }
    exit;
}

try {
    $holidays = SiteConfig::createHolidaysMarkup($dbh, $holResultMessage);
} catch (Exception $pex) {
    $holidays = "";
}

$googleDistanceMkup = "";
$cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if(SecurityComponent::is_TheAdmin()){
    if($cmd == 'calcDistArray'){
        try{
            $distanceCalculator = DistanceFactory::make();
            if($distanceCalculator instanceof GoogleDistance){
                $numCalls = filter_input(INPUT_POST, 'numCalls', FILTER_SANITIZE_NUMBER_INT);
                $events = $distanceCalculator->calcDistanceArray($dbh, $distanceCalculator->getUncalculatedAddresses($dbh), $numCalls);
            }else{
                $events = ["error"=>"Google Distance Calculator is not enabled"];
            }
        }catch(\Exception $e){
            $events = ["error"=>$e->getMessage()];
        }
    }

    if($cmd == 'calcDistAPICost'){
        try{
            $distanceCalculator = DistanceFactory::make();
            if($distanceCalculator instanceof GoogleDistance){
                $numCalls = filter_input(INPUT_POST, 'numCalls', FILTER_SANITIZE_NUMBER_INT);
                $events = ["cost"=>$distanceCalculator->calculateApiCost($numCalls)];
            }else{
                $events = ["error"=>"Google Distance Calculator is not enabled"];
            }
        }catch(\Exception $e){
            $events = ["error"=>$e->getMessage()];
        }
    }

    if($cmd == 'getUncalculatedAddressTbl'){
        try{
            $distanceCalculator = DistanceFactory::make();
            if($distanceCalculator instanceof GoogleDistance){
                $events = CreateMarkupFromDB::generateHTML_Table($distanceCalculator->getUncalculatedAddresses($dbh),'uncalcAddr');
            }else{
                $events = "Google Distance Calculator is not enabled";
            }
        }catch(\Exception $e){
            $events = $e->getMessage();
        }
    }

    if(isset($events)){
        if(is_array($events)){
            echo json_encode($events);
        }else{
            echo $events;
        }
        exit;
    }

    if($uS->distCalculator == "google"){
        $distanceCalculator = new GoogleDistance();
        $googleDistanceMkup = HTMLContainer::generateMarkup("h3", "Google Distance Calculator") . $distanceCalculator->getEditMarkup($dbh);
    }
}
try {
    $stmt = $dbh->query("Select MAX(TimeStamp) from syslog where Log_Type = 'Zip';");
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
} catch (PDOException $pe) {
    $rows = array();
}

if (count($rows) > 0 && $rows[0][0] != '') {
    $zipLoadDate = 'Zip Code File Loaded on ' . date('M j, Y', strtotime($rows[0][0]));
} else {
    $zipLoadDate = '';
}

// save SSO
if(filter_has_var(INPUT_POST, 'saveIdP') && filter_has_var(INPUT_POST, 'idpConfig')){
    try{
        $idpId = array_key_first($_POST['idpConfig']);
        $saml = new SAML($dbh, $idpId);
        $saml = $saml->save($_POST, $_FILES);
        $events = ["success" => 'Auth provider saved successfully', 'idpMkup' => $saml->getEditMarkup(true), "idpName" => $saml->getIdpName()];
    } catch (\Exception $e) {
        $events = ["error" => "<strong>Error saving Identity Provider:</strong>" . $e->getMessage()];
    }

    echo json_encode($events);
    exit();
}

// Patch tab markup
$patchMarkup = Patch::patchTabMu();

$li = '';
$tabContent = '';

foreach ($logSelRows as $r) {

    $li .= HTMLContainer::generateMarkup('li',
                    HTMLContainer::generateMarkup('a', $r[1], array('href' => '#tc' . $r[0])), array('id' => 'li' . $r[0]));

    $content = HTMLContainer::generateMarkup('h3', $r[1], ['style' => 'background-color:#D3D3D3; padding:10px;'])
            . HTMLContainer::generateMarkup('div', "<table id='tableli$r[0]' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>", []);

    $tabContent .= HTMLContainer::generateMarkup('div',
                    $content
                    ,
        ['id' => 'tc' . $r[0]]);
}

$ul = HTMLContainer::generateMarkup('ul', $li, array());
$tabControl = HTMLContainer::generateMarkup('div', $ul . $tabContent, array('id' => 'logsTabDiv'));

$conf = SiteConfig::createMarkup($dbh, NULL, array('pr'));

$localAuthMkup = SiteConfig::createMarkup($dbh, 'pr');

$labels = SiteConfig::createLabelsMarkup($dbh)->generateMarkup();

$authIdpList = SAML::getIdpList($dbh, false);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
<?php echo JQ_UI_CSS; ?>
<?php echo FAVICON; ?>
<?php echo DEFAULT_CSS; ?>
<?php echo JQ_DT_CSS; ?>
<?php echo NOTY_CSS; ?>
<?php echo GRID_CSS; ?>
<?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CONFIGURE_JS; ?>"></script>
    </head>
    <body <?php if ($wInit->testVersion) {
                echo "class='testbody'";
            } ?>>
<?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="tabs" style="display:none;" class="hhk-widget-content">
                <ul>
                    <li><a href="#config">Site Configuration</a></li>
                    <li><a href="#patch">Patch</a></li>
                    <li><a href="#pay">Credit Card Processor</a></li>
                    <li><a href="#auth">Authentication</a></li>
                    <li id="liCron"><a href="#cron">Job Scheduler</a></li>
                    <li><a href="#holidays">Set Holidays</a></li>
                    <li><a href="#loadZip">Load Zip Codes</a></li>
                    <li><a href="#labels">Labels &#38; Prompts</a></li>
                    <li id="liLogs"><a href="#logs">Site Logs</a></li>
<?php if ($uS->ContactManager != '') {
    echo '<li id="liService"><a href="#external">' . $CmsManager->getServiceTitle() . '</a></li>';
} ?>
                </ul>
                <div id="config" class="ui-tabs-hide" >
                    <div style="color:#347201;font-size:1.3em;"><?php echo $confError; ?></div>
                    <form method="post" id="siteConfigForm" name="form4" action="">
                            <?php echo $conf; ?>
                        <br>
                        <div class="divSubmitButtons ui-corner-all">
                            <input type="reset" name="btnreset" id="btnreset" value="Reset" style="margin-right:5px;"/>
                            <input type="submit" name="btnSiteCnf" id="btnSiteCnf" value="Save Site Configuration"/>
                        </div>
                    </form>
                </div>
                <div id="auth" class="ui-tabs-hide">
                    <div id="authTabs" class="hhk-member-detail" style="display:none; width: 100%;">
                        <ul>
                            <li><a href="#localAuth">Local</a></li>
<?php foreach ($authIdpList as $idp) { ?>
                                <li><a href="#<?php echo $idp['idIdp']; ?>Auth"><?php echo $idp["Name"]; ?></a></li>
                        <?php } ?>
                            <li><a href="#newAuth"><span class="ui-icon ui-icon-plusthick mr-2"></span>New Identity Provider</a></li>
                        </ul>

                        <div id="localAuth" class="ui-tabs-hide">
                            <form method="post" action="Configure.php">
                        <?php echo $localAuthMkup; ?>
                                <div style="text-align: right">
                                    <input type="submit" name="btnLocalAuth" id="btnLocalAuth" value="Save">
                                </div>
                            </form>
                        </div>
<?php
foreach ($authIdpList as $idp) {
    $saml = new SAML($dbh, $idp['idIdp']);
    echo $saml->getEditMarkup();
}
$newsaml = new SAML($dbh);
echo $newsaml->getEditMarkup();
?>

                    </div>
                </div>
                <div id="cron" class="ui-tabs-hide hhk-tdbox">
                    <h2>Job Scheduler</h2>
                    <div id="cronTabs">
                        <ul>
                            <li id="liJobs"><a href="#jobs">Jobs</a></li>
                            <li id="liCronLog"><a href="#cronLog">Log</a></li>
                        </ul>
                        <div id="jobs" class="hhk-overflow-x">
<?php if (SecurityComponent::is_TheAdmin()) { ?>
                                <div id="newJob" class="ui-widget ui-widget-content ui-corner-all p-2 d-inline-block">
                                    <label for="newJobType"><strong>Add New Job:</strong></label>
                                    <select id="newJobType" class="mr-2">
                                        <option value="" selected disabled>Select Job Type</option>
    <?php echo HTMLSelector::doOptionsMkup(readGenLookupsPDO($dbh, "cronJobTypes", "Description"), '', false); ?>
                                    </select>
                                    <button type="button" id="addJob">Add Job</button>
                                </div>
<?php } ?>
                            <table id="cronJobs" style="width: 100%"></table>
                        </div>
                        <div id="cronLog" class="ui-tabs-hide">
                            <table id="cronLog" style="width: 100%"></table>
                        </div>
                    </div>
                </div>
                <div id="labels" class="ui-tabs-hide" >
                    <form method="post" name="form5" action="">
<?php echo $labels; ?>
                        <div class="divSubmitButtons ui-corner-all">
                            <input type="reset" name="btnlblreset" id="btnlblreset" value="Reset" style="margin-right:5px;"/>
                            <input type="submit" name="btnLabelCnf" id="btnLabelCnf" value="Save Labels"/>
                        </div>
                    </form>
                </div>
<?php if ($uS->ContactManager != '') { ?>
                    <div id="external" class="ui-tabs-hide" >
                        <?php echo $externalErrMsg; ?>
                        <form method="post" id="formext" name="formext" action="">
                            <div id="serviceContent" class="hhk-tdbox"><span style="margin-left:300px;">Loading...</span></div>
                            <div class="divSubmitButtons ui-corner-all">
                                <input type="submit" id="btnExtCnf" name="btnExtCnf" value="Save"/>
                            </div>
                        </form>
                    </div>
<?php } ?>
                <div id="pay" class="ui-tabs-hide" >
                    <form method="post" name="form2" action="">
<?php echo $payments; ?>
                        <div style="float:right;margin-right:40px;"><input type="submit" name="btnPay" id="btnPay" value="Save"/></div>
                    </form>
                </div>
                <div id="holidays" class="ui-tabs-hide hhk-tdbox" >
                    <form method="post" name="form3" action="" id="formHolidays">
<?php echo $holidays; ?>
                        <div style="float:right;margin-right:40px;"><input type="submit" name="btnHoliday" value="Save"/></div>
                    </form>
                </div>
                <div id="logs" class="ui-tabs-hide hhk-tdbox" >
                    <form autocomplete="off">
                        <?php echo $tabControl; ?>
                    </form>
                </div>
                <div id="patch" class="ui-tabs-hide">
                    <div class="hhk-member-detail">
                        <p style="color:red;"><?php echo $errorMsg; ?></p>
<?php echo $patchMarkup; ?>
                        <div style="clear:both"></div>
                        <form method="post" action="" name="form1">
                            <input type="submit" name="btnLogs" id="btnLogs" value="View Patch Log" style="margin-left:100px;margin-top:20px;"/>
                            <input type="submit" name="btnSaveSQL" id="btnSaveSQL" value="Re-Create Tables, Views and SP's" style="margin-left:20px;margin-top:20px;"/>
                            <input type="submit" name="btnUpdate" id="btnUpdate" value="Update Config" style="margin-left:20px;margin-top:20px;"/>
                        </form>
<?php echo $resultAccumulator; ?>
                        <div style="margin-top:20px;">
<?php echo $logs; ?>
                        </div>
                    </div>
                </div>
                <div id="loadZip" class="ui-tabs-hide">
                    <h3><?php echo $zipLoadDate; ?></h3>
                    <form enctype="multipart/form-data" action="" method="POST" name="formz">
                        <!-- MAX_FILE_SIZE must precede the file input field -->
                        <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
                        <!-- Name of input element determines name in $_FILES array -->
                        <p style="color:red;"><?php echo $resultMsg; ?></p>
                        <p><input name="zipfile" id="zipfile" type="file" /></p><br/>

                        <div style="float:right;margin-right:40px;"><input type="submit" name="btnZipGo" id="btnZipGo" value="Go" /></div>
                    </form>
                    <?php echo $googleDistanceMkup; ?>
                </div>
            </div>
            <input type="hidden" id="notyMsg" value='<?php echo json_encode((isset($notymsg) ? $notymsg : [])); ?>'>
            <input type="hidden" id="tabIndex" value="<?php echo $tabIndex; ?>"/>
            <input type="hidden" id="notymsg" value='<?php echo (isset($notymsg) ? json_encode($notymsg) : '[]'); ?>' />
            <input type="hidden" id="dateFormat" value='<?php echo $labl->getString("momentFormats", "dateTime", "MMM D, YYYY"); ?>' />
            <input type="hidden" id="canEditCron" value='<?php echo SecurityComponent::is_TheAdmin(); ?>' />
            <input type="hidden" id="canForceRunCron" value='<?php echo ($uS->mode != "live" ? true : false); ?>' />
        </div>
    </body>
</html>
