<?php
/**
 * Configure.php
 *
  -- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
  -- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
  -- @license   MIT
  -- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

require CLASSES . 'SiteLog.php';
require CLASSES . 'TableLog.php';
require CLASSES . 'HouseLog.php';
require CLASSES . 'CreateMarkupFromDB.php';
require CLASSES . 'SiteConfig.php';
require CLASSES . 'Patch.php';

require SEC . 'Login.php';
require SEC . 'ChallengeGenerator.php';
require CLASSES . 'US_Holidays.php';
require DB_TABLES . 'MercuryRS.php';
require DB_TABLES . 'GenLookupsRS.php';

require (FUNCTIONS . 'mySqlFunc.php');

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

// get session instance
$uS = Session::getInstance();

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

$confError = '';

if (isset($_POST["btnSiteCnf"])) {

    addslashesextended($_POST);

    // Check subsidyId and returnId - cannot be the same
    $subsidyId = intval(filter_var($_POST['financial']['RoomSubsidyId'], FILTER_SANITIZE_NUMBER_INT), 10);

    SiteConfig::saveConfig($dbh, $config, $_POST, $uS->username);
    SiteConfig::saveSysConfig($dbh, $_POST);

    $config = Login::initializeSession(ciCFG_FILE);
}

if (isset($_POST["btnLabelCnf"])) {

    $tabIndex = 5;
    SiteConfig::saveConfig($dbh, $labl, $_POST, $uS->username);
}

if (isset($_POST["btnExtCnf"]) && is_null($wsConfig) === FALSE) {

    $tabIndex = 6;

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

            //$transfer = new TransferMembers($wsConfig->getString('credentials', 'User'), decryptMessage($wsConfig->getString('credentials', 'Password')));
            $neonItems = $transfer->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

            $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, $list['HHK_Lookup']));

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

                    if ($hhkTypeCode == '' || isset($hhkLookup[$hhkTypeCode]) === FALSE) {
                        continue;
                    }

                    if (isset($mappedItems[$hhkTypeCode])) {
                        // Update
                        $coount = $dbh->exec("update neon_type_map set Neon_Type_Code = '$n' where HHK_Type_Code = '$hhkTypeCode'");
                    } else {
                        // Insert
                        $idTypeMap = $dbh->exec("Insert into neon_type_map (List_Name, Neon_Name, Neon_Type_Code, HHK_Type_Code, Updated_By, Last_Updated) "
                                . "values ('" . $list['List_Name'] . "', '" . $list['List_Item'] . "', '" . $n . "', '" . $hhkTypeCode . "', '" . $uS->username . "', now() );");
                    }
                }
            }
        }

    } catch (Hk_Exception_Upload $ex) {
        $externalErrMsg = "Transfer Error: " . $ex->getMessage();
    }
}

if (isset($_POST["btnUlPatch"])) {
    $tabIndex = 1;
}

if (isset($_FILES['patch']) && $_FILES['patch']['name'] != '') {
    $tabIndex = 1;
    $errorCount = 0;
    $uploadFileName = $_FILES['patch']['name'];

    // Log attempt.
    $logText = "Attempt software patch.  File = " . $_FILES['patch']['name'];
    SiteLog::logPatch($dbh, $logText, $config->getString('code', 'GIT_Id', ''));

    try {


        SiteConfig::checkZipFile('patch');

        $uploadfile = '..' . DS . 'patch' . DS . 'upload.zip';

        if (move_uploaded_file($_FILES['patch']['tmp_name'], $uploadfile)) {

            // patch system
            $patch = new Patch();

            // Verify file and build #.  Throws an error on problems.
            $patch->verifyUpLoad($uploadfile, 'hhk/patch/patchSite.cfg', $uS->ver);

            // Replace files
            $resultAccumulator .= $patch->loadFiles('../', $uploadfile);

            // Annotate any missed files.
            foreach ($patch->results as $err) {
                $errorMsg .= 'Patch File Copy Error: ' . $err['error'] . '<br/>';
            }

            // Update config file
            $resultAccumulator .= $patch->loadConfigUpdates('../patch/patchSite.cfg', $config);
            $resultAccumulator .= $patch->deleteConfigItems('../patch/deleteSiteItems.cfg', $config);

            // Update labels file
            $resultAccumulator .= $patch->loadConfigUpdates('../patch/patchLabel.cfg', $labl);
            $resultAccumulator .= $patch->deleteConfigItems('../patch/deleteLabelItems.cfg', $labl);

            // Update Tables
            $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllTables.sql', "Tables");

            foreach ($patch->results as $err) {

                if ($err['errno'] == 1091 || $err['errno'] == 1061) {  // key not exist, Duplicate Key name
                    continue;
                }

                $errorMsg .= 'Create Table Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
            }

            // Run SQL patches
            if (file_exists('../patch/patchSQL.sql')) {

                $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../patch/patchSQL.sql', "Updates");


                foreach ($patch->results as $err) {

                    if ($err['errno'] == 1062 || $err['errno'] == 1060) {
                        continue;
                    }

                    $errorMsg .= 'Patch Update Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                    $errorCount++;
                }
            }

            // Update views
            if ($errorCount < 1) {

                $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllViews.sql', 'Views');

                foreach ($patch->results as $err) {

                    $errorMsg .= 'Create Views Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                }
            } else {

                $errorMsg .= '**Views not updated**  ';
            }

            // Update SPs
            if ($errorCount < 1) {
                $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllRoutines.sql', 'Stored Procedures', '$$', '-- ;');

                foreach ($patch->results as $err) {

                    $errorMsg .= 'Create Stored Procedures Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                }

            } else {
                $errorMsg .= '** Stored Procedures not updated**  ';
            }

            // Update pay types
            $cnt = SiteConfig::updatePayTypes($dbh);
            if ($cnt > 0) {
                $resultAccumulator .= "Pay Types updated.  ";
            }

            // Log update.
            $logText = "Loaded software patch - " . $uploadFileName . "; " . $errorMsg;
            SiteLog::logPatch($dbh, $logText, $config->getString('code', 'GIT_Id', ''));

        } else {
            throw new Hk_Exception_Runtime("Problem moving uploaded patch file.  ");
        }

    } catch (Exception $hex) {
        $errorMsg .= '***' . $hex->getMessage();
        // Log failure.
        $logText = "Fail software patch - " . $uploadFileName . $errorMsg;
        SiteLog::logPatch($dbh, $logText, $config->getString('code', 'GIT_Id', ''));
    }
}

// Zip code file
if (isset($_FILES['zipfile'])) {
    $tabIndex = 4;

    try {

        SiteLog::checkZipFile('zipfile');

        $resultMsg .= SiteConfig::loadZipCodeFile($dbh, $_FILES['zipfile']['tmp_name']);

        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Loaded. ' . $resultMsg, $config->getString('code', 'GIT_Id', ''));

    } catch (Exception $hex) {
        $resultMsg .= $hex->getMessage();
        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Failed. ' . $resultMsg, $config->getString('code', 'GIT_Id', ''));
    }
}

// Delete old files
if (isset($_POST['btnDelBak'])) {
    $tabIndex = 1;
    Patch::deleteBakFiles('../', '.bak');
}

if (isset($_POST['delInstallDir'])) {
    $tabIndex = 1;
    Patch::deleteDirectory('../install');
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



try {
    $payments = SiteConfig::createPaymentCredentialsMarkup($dbh, $ccResultMessage);
} catch (PDOException $pex) {

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

$delInstallDir = '';
if (is_dir('../install')) {
    $delInstallDir = HTMLContainer::generateMarkup('p', HTMLInput::generateMarkup('Delete Install Directory', array('name'=>'delInstallDir', 'type'=>'submit')));
}

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

            $neonItems = $transfer->listNeonType($list['Method'], $list['List_Name'], $list['List_Item']);

            $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, $list['HHK_Lookup']));

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
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo DEFAULT_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
$(document).ready(function () {

    $('#financialRoomSubsidyId, #financialReturnPayorId').change(function () {

        $('#financialRoomSubsidyId, #financialReturnPayorId').removeClass('ui-state-error');

        if ($('#financialRoomSubsidyId').val() != 0 && $('#financialRoomSubsidyId').val() === $('#financialReturnPayorId').val()) {
            $('#financialRoomSubsidyId, #financialReturnPayorId').addClass('ui-state-error');
            alert('Subsidy Id must be different than the Return Payor Id');
        }
    });

    var tabIndex = '<?php echo $tabIndex; ?>';
    var tbs = $('#tabs').tabs();
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
                    <li><a href="#config">View Site Configuration</a></li>
                    <li><a href="#patch">Patch</a></li>
                    <li><a href="#pay">Credit Card Processor</a></li>
                    <li><a href="#holidays">Set Holidays</a></li>
                    <li><a href="#loadZip">Load Zip Code Distance Data</a></li>
                    <li><a href="#labels">View Labels & Prompts</a></li>
                    <?php if ($serviceName != '') {echo '<li><a href="#external">' . $serviceName . '</a></li>';} ?>
                </ul>
                <div id="config" class="ui-tabs-hide" >
                    <div style="color:red;font-size:1.5em;"><?php echo $confError; ?></div>
                    <form method="post" name="form4" action="">
                        <?php echo $conf; ?>
                        <div style="float:right;margin-right:40px;"><input type="reset" name="btnreset" value="Reset" style="margin-right:5px;"/><input type="submit" name="btnSiteCnf" value="Save Site Configuration"/></div>
                    </form>
                </div>
                <div id="labels" class="ui-tabs-hide" >
                    <form method="post" name="form5" action="">
                        <?php echo $labels; ?>
                        <div style="float:right;margin-right:40px;"><input type="reset" name="btnreset" value="Reset" style="margin-right:5px;"/><input type="submit" name="btnLabelCnf" value="Save Labels"/></div>
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
                        <div style="float:right;margin-right:40px;"><input type="submit" name="btnPay" value="Save"/></div>
                    </form>
                </div>
                <div id="holidays" class="ui-tabs-hide hhk-tdbox" >
                    <form method="post" name="form3" action="">
                        <?php echo $holidays; ?>
                        <div style="float:right;margin-right:40px;"><input type="submit" name="btnHoliday" value="Save"/></div>
                    </form>
                </div>
                <div id="patch" class="ui-tabs-hide">
                    <div class="hhk-member-detail">
                        <!-- The data encoding type, enctype, MUST be specified as below -->
                        <form enctype="multipart/form-data" action="" method="POST" name ="formp">
                            <!-- MAX_FILE_SIZE must precede the file input field -->
                            <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
                            <!-- Name of input element determines name in $_FILES array -->
                            <p>Select Patch File: <input name="patch" type="file" /></p><br/>

                            <div style="float:left;margin-left:200px;"><input type="submit" name='btnUlPatch' value="Upload & Execute Patch" /></div>
                        </form>

                    </div>
                    <div style='clear:both;'>
                        <p style="color:red;"><?php echo $errorMsg; ?></p>

                        <form method="post" action="" name="form1">
                            <p>URL: <?php echo $uS->databaseURL; ?></p>
                            <p>Schema: <?php echo $uS->databaseName; ?></p>
                            <p>User: <?php echo $uS->databaseUName; ?></p>
                            <?php echo $delInstallDir; ?>
                            <input type="submit" name="btnLogs" value="View Patch Log" style="margin-left:100px;margin-top:20px;"/>
                            <input type="submit" name="btnSaveSQL" id="btnSave" value="Re-Create Tables, Views and SP's" style="margin-left:20px;margin-top:20px;"/>
                            <input type="submit" name="btnDelBak" id="btnSave" value="Delete .bak Files" style="margin-left:20px;margin-top:20px;"/>
                        </form>
                        <?php echo $resultAccumulator; ?>
                    </div>
                    <div style="margin-top:20px;">
                        <?php echo $logs; ?>
                    </div>
                </div>
                <div id="loadZip" class="ui-tabs-hide">
                    <h3><?php echo $zipLoadDate; ?></h3>
                    <form enctype="multipart/form-data" action="" method="POST" name="formz">
                        <!-- MAX_FILE_SIZE must precede the file input field -->
                        <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
                        <!-- Name of input element determines name in $_FILES array -->
                        <p style="color:red;"><?php echo $resultMsg; ?></p>
                        <p><input name="zipfile" type="file" /></p><br/>

                        <div style="float:right;margin-right:40px;"><input type="submit" value="Go" /></div>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
