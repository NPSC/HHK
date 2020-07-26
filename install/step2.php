<?php
use HHK\sec\Login;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\Patch;
use HHK\SysConst\WebSiteCode;
use HHK\SiteConfig;
use HHK\SiteLog;
use HHK\SysConst\CodeVersion;

/**
 * step2.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require_once ("InstallIncludes.php");

/* require_once (SEC . 'UserClass.php');
require_once(SEC . 'Login.php');
require CLASSES . 'SiteLog.php';
require CLASSES . 'TableLog.php';
require CLASSES . 'SiteConfig.php';

require_once(CLASSES . 'Patch.php'); */
require_once(FUNCTIONS . 'mySqlFunc.php');

try {
    $login = new Login();
    $config = $login->initHhkSession(ciCFG_FILE);
} catch (PDOException $pex) {
    echo ("Database Error.  " . $pex->getMessage());
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}

// get session instance
$ssn = Session::getInstance();

$pageTitle = $ssn->siteName;

// define db connection obj
try {
    $dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
    exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
}

$errorMsg = '';
$resultAccumulator = "";

$resultMsg = '';

// Zip code file
if (isset($_FILES['zipfile'])) {

    $clr = 'color:green;';

    try {

        SiteConfig::checkUploadFile('zipfile');
        $resultMsg .= SiteConfig::loadZipCodeFile($dbh, $_FILES['zipfile']['tmp_name']);

        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Loaded. ' . $resultMsg, CodeVersion::GIT_Id);

    } catch (Exception $hex) {
        $resultMsg .= $hex->getMessage();
        $clr = 'color:red;';
        SiteLog::writeLog($dbh, 'Zip', 'Zip Code File Failed. ' . $resultMsg, CodeVersion::GIT_Id);
    }

    $resultMsg = HTMLContainer::generateMarkup('p', $resultMsg, array('style' => $clr));
}


// Check for returns
if (isset($_POST['btnSave'])) {

    try {

        $patch = new Patch();

        // Update Tables
        $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllTables.sql', "Tables");
        foreach ($patch->results as $err) {
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

        // Set web_sites table
        $adminDir = str_ireplace('/', '', 'admin');
        $houseDir = str_ireplace('/', '', 'house');
        $volDir = str_ireplace('/', '', 'volunteer');


        // Admin
        $dbh->exec("update web_sites set Relative_Address = '$adminDir/' where Site_Code = '" . WebSiteCode::Admin . "'");

        // House
        if ($houseDir != '') {
            $dbh->exec("update web_sites set Relative_Address = '$houseDir/' where Site_Code = '" . WebSiteCode::House . "'");
        } else {
            $dbh->exec("update web_sites set Relative_Address = '' where Site_Code = '" . WebSiteCode::House . "'");
        }

        // Volunteer
        if ($volDir != '') {
            $dbh->exec("update web_sites set Relative_Address = '$volDir/' where Site_Code = '" . WebSiteCode::Volunteer . "'");
        } else {
            $dbh->exec("update web_sites set Relative_Address = '' where Site_Code = '" . WebSiteCode::Volunteer . "'");
        }


    } catch (Exception $hex) {
        $errorMsg .= '*** ' . $hex->getMessage();
    }
}

if (isset($_POST['btnNext'])) {
    header('location:step3.php');
}
?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <script type="text/javascript" src="../<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="../<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="../js/install.js"></script>
        <script type="text/javascript">
            $(document).ready(function () {
                "use strict";

                $('#btnMeta').click(function () {

                    var pw1 = $('#txtpw1'),
                        pw2 = $('#txtpw2'),
                        pword;

                    $('#spanpwerror').text('');
                    pword = pw1.val();

                    if (checkStrength(pword)) {

                        // Strength ok, check second copy
                        if (pword !== pw2.val()) {
                            $('#spanpwerror').text('Passwords are not the same.');
                            return;
                        }

                    } else {
                        $('#spanpwerror').text("Password must have 8 or more characters including at least one uppercase and one lower case alphabetical character and one number and one of ! @ # $ % ^ & * ( ) - = _ + ~ . , \" < > / ? ; : ' | [ ] { }");
                        return;
                    }

                    $.post('ws_install.php', {cmd: 'loadmd', 'new': pword}, function (data) {
                        if (data) {
                            try {
                                data = $.parseJSON(data);
                            } catch (err) {
                                alert("Parser error - " + err.message);
                                return;
                            }

                            if (data.result) {
                                $('#spanDone').text(data.result);
                                $(this).prop('disabled', true);
                            }

                            if (data.error) {
                                $('#spanpwerror').text(data.error);
                            }
                        }
                    });

                    pw1.val('');
                    pw2.val('');

                });
            });
        </script>
    </head>
    <body>
        <div id="page" style="width:800px;">
            <div>
                <h2 class="logo">Hospitality HouseKeeper Installation Process</h2>
                <h3>Step Two: Install Database</h3>
            </div><div class='pageSpacer'></div>
            <div id="content" style="margin:10px; width:100%;">
                <div><span style="color:red;"><?php echo $errorMsg; ?></span></div>

                    <table>
                        <tr>
                            <th style='text-align: right;'>URL:</th><td><?php echo $ssn->databaseURL; ?></td>
                        </tr><tr>
                            <th style='text-align: right;'>Schema:</th><td><?php echo $ssn->databaseName; ?></td>
                        </tr><tr>
                            <th style='text-align: right;'>User:</th><td><?php echo $ssn->databaseUName; ?></td>
                        </tr>
                    </table><br/>

                    <p><?php echo $resultAccumulator; ?></p>
                    <form method="post" action="step2.php" name="form1" id="form1">
                    <fieldset>
                        <legend>1.  Install Database</legend>
                        <input type="submit" name="btnSave" id="btnSave" value="Install DB"/>
                    </fieldset>
                    <fieldset>
                        <legend>2.  Load Metadata</legend>
                        <p>Admin account password: <input type='password' id='txtpw1'/><span id='spanpwerror' style='color:red; margin-left: .5em;'></span></p>
                        <p>Admin account password again: <input type='password' id='txtpw2'/></p>

                        <input type="button" id="btnMeta" value="Load Metadata" style="margin:20px;"/><span id='spanDone' style='font-weight: bold;'></span>
                    </fieldset>
                </form>
                    <fieldset>
                        <legend>3.  Load Zip Codes</legend>
                        <form enctype="multipart/form-data" action="" method="POST" name="formz">
                            <!-- MAX_FILE_SIZE must precede the file input field -->
                            <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
                            <!-- Name of input element determines name in $_FILES array -->
                            <input name="zipfile" type="file" />
                            <input type="submit" value="Load Zip Code File" style="margin-left:20px;"/>
                            <?php echo $resultMsg; ?>
                        </form>
                    </fieldset>
                <form method="post" action="step2.php" name="form2" id="form2">
                    <input type="submit" name="btnNext" id="btnNext" value="Next" style="margin-left:7px;margin-top:20px;"/>
                </form>
            </div>
        </div>
    </body>
</html>

