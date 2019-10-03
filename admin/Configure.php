<?php
/**
 * Configure.php
 *
  -- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
  -- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
  -- @license   MIT
  -- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

require DB_TABLES . 'PaymentGwRS.php';
require DB_TABLES . 'GenLookupsRS.php';

require CLASSES . 'SiteLog.php';
require CLASSES . 'TableLog.php';
require CLASSES . 'HouseLog.php';
require CLASSES . 'CreateMarkupFromDB.php';
require CLASSES . 'SiteConfig.php';
require CLASSES . 'UpdateSite.php';
require CLASSES . 'Patch.php';
require CLASSES . 'US_Holidays.php';

require (PMT . 'GatewayConnect.php');
require (PMT . 'PaymentGateway.php');
require (PMT . 'PaymentResponse.php');
require (PMT . 'CreditToken.php');

require SEC . 'Login.php';
require SEC . 'ChallengeGenerator.php';

require (FUNCTIONS . 'mySqlFunc.php');

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

// get session instance
$uS = Session::getInstance();
creditIncludes($uS->PaymentGateway);

// Kick out 'Guest' Users
if ($uS->rolecode > WebRole::WebUser) {
    include("../errorPages/forbid.html");
    exit();
}


$dbh = $wInit->dbh;

$resultMsg = '';
$errorMsg = '';
$tabIndex = 0;
$resultAccumulator = '';
$ccResultMessage = '';
$holResultMessage = '';
$externalErrMsg = '';
$serviceName = '';
$rteFileSelection = '';
$rteMsg = '';
$confError = '';

$config = new Config_Lite(ciCFG_FILE);
$labl = new Config_Lite(LABEL_FILE);
$wsConfig = NULL;


if ($config->has('webServices', 'Service_Name') && $config->getString('webServices', 'Service_Name', '') != '' && $config->getString('webServices', 'ContactManager', '') != '') {

    require (CLASSES . 'neon.php');
    require (CLASSES . "TransferMembers.php");

    if (file_exists(REL_BASE_DIR . 'conf' . DS . $config->getString('webServices', 'ContactManager', ''))) {
        try {
            $wsConfig = new Config_Lite(REL_BASE_DIR . 'conf' . DS . $config->getString('webServices', 'ContactManager', ''));
        } catch (Config_Lite_Exception_Runtime $ex) {
            $wsConfig = NULL;
        }

        $serviceName = $config->getString('webServices', 'Service_Name', '');
    }
}

if (isset($_POST["btnSiteCnf"])) {

    addslashesextended($_POST);

    $confError = SiteConfig::saveSysConfig($dbh, $_POST);
    SiteConfig::savePaymentCredentials($dbh, $post);

}

if (isset($_POST["btnLabelCnf"])) {

    $tabIndex = 5;
    SiteConfig::saveConfig($dbh, $labl, $_POST, $uS->username);
}

if (isset($_POST["btnExtCnf"]) && is_null($wsConfig) === FALSE) {

    $tabIndex = 7;

    SiteConfig::saveConfig($dbh, $wsConfig, $_POST, $uS->username);

    $transfer = new TransferMembers($wsConfig->getString('credentials', 'User'), decryptMessage($wsConfig->getString('credentials', 'Password')));

    try {
        // Custom fields
        $results = $transfer->listCustomFields();
        $custom_fields = array();

        foreach ($results as $v) {
            if ($wsConfig->has('custom_fields', $v['fieldName'])) {
                $custom_fields[$v['fieldName']] = $v['fieldId'];
            }
        }

        // Write Custom Field Ids to the config file.
        $confData = array('custom_fields' => $custom_fields);
        SiteConfig::saveConfig($dbh, $wsConfig, $confData, $uS->username);


        // Properties
        $stmt = $dbh->query("Select * from neon_lists;");

        while ($list = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $neonItems = $transfer->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

            if ($list['HHK_Lookup'] == 'Fund') {

                // Use Items for the Fund
                $stFund = $dbh->query("select `idItem` as `Code`, `Description`, '' as `Substitute` from item where Deleted = 0;");
                $hhkLookup = array();

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row['Code']] = $row;
                }

                $hhkLookup['p'] = array('Code'=>'p', 0=>'p', 'Description' => 'Payment', 1=>'Payment', 'Substitute'=>'', 2=>'');

            } else if ($list['HHK_Lookup'] == 'Pay_Type') {

                // Use Items for the Fund
                $stFund = $dbh->query("select `idPayment_method` as `Code`, `Method_Name` as `Description`, '' as `Substitute` from payment_method;");
                $hhkLookup = array();

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row['Code']] = $row;
                }

            } else {
                $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, $list['HHK_Lookup']));
            }

            $stmtList = $dbh->query("Select * from neon_type_map where List_Name = '" . $list['List_Name'] . "'");
            $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);
            $mappedItems = array();
            foreach ($items as $i) {
                $mappedItems[$i['HHK_Type_Code']] = $i;
            }

            $nTbl = new HTMLTable();
            $nTbl->addHeaderTr(HTMLTable::makeTh('HHK Lookup') . HTMLTable::makeTh('NeonCRM Name') . HTMLTable::makeTh('NeonCRM Id'));

            foreach ($neonItems as $n => $k) {

                if (isset($_POST['sel' . $list['List_Name']][$n])) {

                    $hhkTypeCode = filter_var($_POST['sel' . $list['List_Name']][$n], FILTER_SANITIZE_STRING);

                    if ($hhkTypeCode == '') {
                        // delete if previously set
                        foreach ($mappedItems as $i) {
                            if ($i['Neon_Type_Code'] == $n && $i['HHK_Type_Code'] != '') {
                                $dbh->exec("delete from neon_type_map  where idNeon_type_map = " .$i['idNeon_type_map']);
                                break;
                            }
                        }

                        continue;

                    } else if (isset($hhkLookup[$hhkTypeCode]) === FALSE) {
                        continue;
                    }

                    if (isset($mappedItems[$hhkTypeCode])) {
                        // Update
                        $count = $dbh->exec("update neon_type_map set Neon_Type_Code = '$n', Neon_Type_name = '$k' where HHK_Type_Code = '$hhkTypeCode' and List_Name = '" . $list['List_Name'] . "'");
                    } else {
                        // Insert
                        $idTypeMap = $dbh->exec("Insert into neon_type_map (List_Name, Neon_Name, Neon_Type_Code, Neon_Type_Name, HHK_Type_Code, Updated_By, Last_Updated) "
                                . "values ('" . $list['List_Name'] . "', '" . $list['List_Item'] . "', '" . $n . "', '" . $k . "', '" . $hhkTypeCode . "', '" . $uS->username . "', now() );");
                    }
                }
            }
        }

    } catch (Hk_Exception_Upload $ex) {
        $externalErrMsg = "Transfer Error: " . $ex->getMessage();
    }
}

if (isset($_POST['btnUpdate'])) {

    $tabIndex = 1;

    if (SecurityComponent::is_TheAdmin()) {

        $update = new UpdateSite();

        $update->doUpdate($dbh);
        $errorMsg .= $update->getErrorMsg();
        $resultAccumulator = $update->getResultAccumulator();
    } else {
        $errorMsg .= 'This user does not enjoy site update priviledges.';
    }
}

if (isset($_POST['btncopy'])) {

    if (SecurityComponent::is_TheAdmin()) {
        $patch = new Patch();
        $patch->insertSiteConf($dbh);
    } else {
        $confError .= 'This user does not enjoy copy configuration priviledges.';
    }
}

// Zip code file
if (isset($_FILES['zipfile'])) {
    $tabIndex = 4;

    try {

        SiteConfig::checkUploadFile('zipfile');

        $resultMsg .= SiteConfig::loadZipCodeFile($dbh, $_FILES['zipfile']['tmp_name']);

        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Loaded. ' . $resultMsg, $config->getString('code', 'GIT_Id', ''));

    } catch (Exception $hex) {
        $resultMsg .= $hex->getMessage();
        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Failed. ' . $resultMsg, $config->getString('code', 'GIT_Id', ''));
    }
}

// Save SQL
if (isset($_POST['btnSaveSQL'])) {

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
    SiteLog::writeLog($dbh, 'DB', $logText, $config->getString('code', 'GIT_Id', ''));
}

// Payment credentials
if (isset($_POST['btnPay'])) {
    $tabIndex = 2;
    $ccResultMessage = SiteConfig::savePaymentCredentials($dbh, $_POST);

    unset($uS->nameLookups);
    $wInit->reloadGenLkUps($uS);
}

$logs = '';
if (isset($_POST['btnLogs'])) {
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

$logMarkup = '';
if (isset($_POST['btnLogSel'])) {
    $tabIndex = 6;
    $where = '';

    if (isset($_POST['logSel'])) {
        $logSel = filter_var($_POST['logSel'], FILTER_SANITIZE_STRING);
    }

    if ($logSel == 's') {
        $where = " where `Log_Type` in ('sys_config', 'Site_Config_File') ";
    }
    if ($logSel == 'r') {
        $where = " where `Log_Type` in ('resource', 'room_rate', 'room') ";
    }
    if ($logSel == 'l') {
        $where = " where `Log_Type` ='gen_lookups' ";
    }

    if ($where != '') {

        $stmt = $dbh->query("Select * from house_log $where order by Timestamp DESC Limit 100;");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $edRows = array();

        foreach ($rows as $r) {

            $r['Date'] = date('M j, Y H:i:s', strtotime($r['Timestamp']));
            unset($r['Timestamp']);

            unset($r['Id1']);
            unset($r['Id2']);
            $edRows[] = $r;
        }

        $logMarkup = CreateMarkupFromDB::generateHTML_Table($edRows, 'houselog');
    }
}

$pgw = $uS->PaymentGateway;
try {
    $payments = SiteConfig::createPaymentCredentialsMarkup($dbh, $ccResultMessage);
} catch (Exception $pex) {
    $payments = 'Error: ' . $pex->getMessage();
}

if (isset($_POST['btnHoliday'])) {
    $tabIndex = 3;
    $holResultMessage = SiteConfig::saveHolidays($dbh, $_POST, $uS->username);
}

try {
    $holidays = SiteConfig::createHolidaysMarkup($dbh, $holResultMessage);
} catch (Exception $pex) {

}

try {
    $stmt = $dbh->query("Select MAX(TimeStamp) from syslog where Log_Type = 'Zip';");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
} catch (PDOException $pe) {
    $rows = array();
}

if (count($rows) > 0 && $rows[0][0] != '') {
    $zipLoadDate = 'Zip Code File Loaded on ' . date('M j, Y', strtotime($rows[0][0]));
} else {
    $zipLoadDate = '';
}

// Patch tab markup
$patchMarkup = Patch::patchTabMu();

$logSelRows = array(

    2=>array(0=>'s', 1=>'Sys Config Log'),
    3=>array(0=>'r', 1=>'Rooms Log'),
    4=>array(0=>'l', 1=>'Lookups Log'),
);

$logSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkuP($logSelRows, 'p', FALSE), array('name'=>'logSel'));

$conf = SiteConfig::createMarkup($dbh, $config, new Config_Lite(REL_BASE_DIR . 'conf' . DS . 'siteTitles.cfg'));

$labels = SiteConfig::createCliteMarkup($labl)->generateMarkup();

$externals = '';
if (is_null($wsConfig) === FALSE) {

    $externals = SiteConfig::createCliteMarkup($wsConfig, new Config_Lite(REL_BASE_DIR . 'conf' . DS . 'neonTitles.cfg'))->generateMarkup();

    if ($wsConfig->getString('credentials', 'User') != '' && $wsConfig->getString('credentials', 'Password') != '') {

      try {

        $transfer = new TransferMembers($wsConfig->getString('credentials', 'User'), decryptMessage($wsConfig->getString('credentials', 'Password')));
        $stmt = $dbh->query("Select * from neon_lists;");

        while ($list = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if (isset($list['HHK_Lookup']) === FALSE) {
                continue;
            }

            $neonItems = $transfer->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

            if ($list['HHK_Lookup'] == 'Fund') {

                // Use Items for the Fund
                $stFund = $dbh->query("select idItem as Code, Description, '' as `Substitute` from item where Deleted = 0;");
                $hhkLookup = array();

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row["Code"]] = $row;
                }

                $hhkLookup['p'] = array('Code'=>'p', 0=>'p', 'Description' => 'Payment', 1=>'Payment', 'Substitute'=>'', 2=>'');

            } else if ($list['HHK_Lookup'] == 'Pay_Type') {

                // Use Items for the Fund
                $stFund = $dbh->query("select `idPayment_method` as `Code`, `Method_Name` as `Description`, '' as `Substitute` from payment_method;");
                $hhkLookup = array();

                while ($row = $stFund->fetch(\PDO::FETCH_BOTH)) {
                    $hhkLookup[$row['Code']] = $row;
                }

            } else {
                $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, $list['HHK_Lookup']));
            }

            $stmtList = $dbh->query("Select * from neon_type_map where List_Name = '" . $list['List_Name'] . "'");
            $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

            $mappedItems = array();
            foreach ($items as $i) {
                $mappedItems[$i['Neon_Type_Code']] = $i;
            }

            $nTbl = new HTMLTable();
            $nTbl->addHeaderTr(HTMLTable::makeTh('HHK Lookup') . HTMLTable::makeTh('NeonCRM Name') . HTMLTable::makeTh('NeonCRM Id'));

            foreach ($neonItems as $n => $k) {

                $hhkTypeCode = '';
                if (isset($mappedItems[$n])) {
                    $hhkTypeCode = $mappedItems[$n]['HHK_Type_Code'];
                }

                $nTbl->addBodyTr(
                    HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hhkLookup, $hhkTypeCode), array('name' => 'sel' . $list['List_Name'] . '[' . $n . ']')))
                    . HTMLTable::makeTd($k)
                    . HTMLTable::makeTd($n, array('style'=>'text-align:center;'))
                );
            }

            $externals .= $nTbl->generateMarkup(array('style'=>'margin-top:5px;'), $list['List_Name']);
        }

      } catch (Exception $pe) {
          $externalErrMsg = "Transfer Error: " .$pe->getMessage();
      }

    }
}

// Alert Message
$webAlert = new alertMessage("webContainer");
$webAlert->set_DisplayAttr("none");
$webAlert->set_Context(alertMessage::Success);
$webAlert->set_iconId("webIcon");
$webAlert->set_styleId("webResponse");
$webAlert->set_txtSpanId("webMessage");
$webAlert->set_Text("oh-oh");

$getWebReplyMessage = $webAlert->createMarkup();
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo DEFAULT_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

<script type="text/javascript">
$(document).ready(function () {
    var tabIndex = '<?php echo $tabIndex; ?>';
    var tbs = $('#tabs').tabs();

    $('#btnreset, #btnSiteCnf, #btnLogs, #btnSaveSQL, #btnUpdate, #btnlblreset, #btnLabelCnf, #btnPay, #btnZipGo, #zipfile').button();
    $('#financialRoomSubsidyId, #financialReturnPayorId').change(function () {

        $('#financialRoomSubsidyId, #financialReturnPayorId').removeClass('ui-state-error');

        if ($('#financialRoomSubsidyId').val() != 0 && $('#financialRoomSubsidyId').val() === $('#financialReturnPayorId').val()) {
            $('#financialRoomSubsidyId, #financialReturnPayorId').addClass('ui-state-error');
            alert('Subsidy Id must be different than the Return Payor Id');
        }
    });
    tbs.tabs("option", "active", tabIndex);
    $('#tabs').show();
});
</script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
    <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <?php echo $getWebReplyMessage; ?>
            <div id="tabs" class="hhk-member-detail" style="display:none;">
                <ul>
                    <li><a href="#config">Site Configuration</a></li>
                    <li><a href="#patch">Patch</a></li>
                    <li><a href="#pay">Credit Card Processor</a></li>
                    <li><a href="#holidays">Set Holidays</a></li>
                    <li><a href="#loadZip">Load Zip Codes</a></li>
                    <li><a href="#labels">Labels & Prompts</a></li>
                    <li><a href="#logs">System Logs</a></li>
                    <?php if ($serviceName != '') {echo '<li><a href="#external">' . $serviceName . '</a></li>';} ?>
                </ul>
                <div id="config" class="ui-tabs-hide" >
                    <div style="color:black;font-size:1em;"><?php echo $confError; ?></div>
                    <form method="post" name="form4" action="">
                        <?php echo $conf; ?>
                        <br>
                        <div class="divSubmitButtons ui-corner-all">
                            <input type="submit" name="btncopy" id="btncopy" value="Copy site.cfg to DB" style="margin-right:5px;"/>
                            <input type="reset" name="btnreset" id="btnreset" value="Reset" style="margin-right:5px;"/>
                            <input type="submit" name="btnSiteCnf" id="btnSiteCnf" value="Save Site Configuration"/>
                        </div>
                    </form>
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
                    <?php if ($serviceName != '') { ?>
                        <div id="external" class="ui-tabs-hide" >
                            <div style="color:red;font-size: large;" id="divextnlerror"><?php echo $externalErrMsg; ?></div>
                            <div style='margin: 5px;font-weight: bold;'><span ><a href="../house/SetupNeonCRM.htm" title='click me for instructions!' target="_blank">Instructions</a></span></div>
                            <form method="post" name="formext" action="">
                                <?php echo $externals; ?>
                                <div style="float:right;margin-right:40px;">
                                    <input type="submit" style='margin-right:10px;' name="btnExtIndiv" value="Reload NeonCRM Custom Id's"/>
                                    <input type="submit" name="btnExtCnf" value="Save"/>
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
                    <form method="post" name="form3" action="">
                        <?php echo $holidays; ?>
                        <div style="float:right;margin-right:40px;"><input type="submit" name="btnHoliday" value="Save"/></div>
                    </form>
                </div>
                <div id="logs" class="ui-tabs-hide hhk-tdbox" >
                    <form method="post" name="formlog" action="">
                        <?php echo $logSelector; ?>
                        <input type="submit" name="btnLogSel" id="btnLogSel" value="View Site Log" style="margin-left:100px;"/>
                    </form>
                        <div style="margin-top:20px;">
                            <?php echo $logMarkup; ?>
                        </div>
                </div>
                <div id="patch" class="ui-tabs-hide">
                    <div class="hhk-member-detail">
                        <p style="color:red;"><?php echo $errorMsg; ?></p>
                        <?php echo $patchMarkup; ?>
                        <div style="clear:both"></div>
                        <form method="post" action="" name="form1">
                            <input type="submit" name="btnLogs" id="btnLogs" value="View Site Log" style="margin-left:100px;margin-top:20px;"/>
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
                </div>
            </div>
        </div>
    </body>
</html>
