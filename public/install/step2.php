<?php
use HHK\Common;
use HHK\sec\Login;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\Update\Patch;
use HHK\SysConst\WebSiteCode;
use HHK\Update\SiteConfig;
use HHK\Update\SiteLog;
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

try {
    $login = new Login();
    $config = $login->initHhkSession();
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
    $dbh = Common::initPDO(TRUE);
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
        $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../../sql/CreateAllTables.sql', "Tables");
        foreach ($patch->results as $err) {
            $errorMsg .= 'Create Table Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
        }


        $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../../sql/CreateAllViews.sql', 'Views');
        foreach ($patch->results as $err) {
            $errorMsg .= 'Create View Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
        }

        $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../../sql/CreateAllRoutines.sql', 'Stored Procedures', '$$', '-- ;');
        foreach ($patch->results as $err) {
            $errorMsg .= 'Create Stored Procedures Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
        }

        // Set web_sites table
        $adminDir = str_ireplace('/', '', 'admin');
        $houseDir = str_ireplace('/', '', 'house');
        $volDir = str_ireplace('/', '', 'volunteer');


        $updWebSiteStmt = $dbh->prepare("UPDATE `web_sites` SET `Relative_Address` = :relAddr WHERE `Site_Code` = :siteCode");

        // Admin
        $updWebSiteStmt->execute([':relAddr' => $adminDir . '/', ':siteCode' => WebSiteCode::Admin]);

        // House
        if ($houseDir != '') {
            $updWebSiteStmt->execute([':relAddr' => $houseDir . '/', ':siteCode' => WebSiteCode::House]);
        } else {
            $updWebSiteStmt->execute([':relAddr' => '', ':siteCode' => WebSiteCode::House]);
        }

        // Volunteer
        if ($volDir != '') {
            $updWebSiteStmt->execute([':relAddr' => $volDir . '/', ':siteCode' => WebSiteCode::Volunteer]);
        } else {
            $updWebSiteStmt->execute([':relAddr' => '', ':siteCode' => WebSiteCode::Volunteer]);
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
        
        <script type="text/javascript">
            function checkStrength(pwStr) {
                const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*\W).{8,}$/;
                return strongRegex.test(pwStr);
            }

            document.addEventListener("DOMContentLoaded", () => {
                "use strict";

                const btnMeta = document.getElementById('btnMeta');

                btnMeta.addEventListener('click', function () {

                    const pw1 = document.getElementById('txtpw1'),
                        pw2 = document.getElementById('txtpw2'),
                        pw3 = document.getElementById('txtpw3'),
                        pw4 = document.getElementById('txtpw4'),
                        spanPwError = document.getElementById('spanpwerror'),
                        spanPw3Error = document.getElementById('spanpw3error'),
                        spanDone = document.getElementById('spanDone');

                    spanPwError.textContent = '';
                    spanPw3Error.textContent = '';

                    if (checkStrength(pw1.value)) {

                        // Strength ok, check second copy
                        if (pw1.value !== pw2.value) {
                            spanPwError.textContent = 'Passwords are not the same.';
                            return;
                        }

                    } else {
                        spanPwError.textContent = "Password must have 8 or more characters including at least one uppercase and one lower case alphabetical character and one number and one of ! @ # $ % ^ & * ( ) - = _ + ~ . , \" < > / ? ; : ' | [ ] { }";
                        return;
                    }

                    if (checkStrength(pw3.value)) {

                        // Strength ok, check second copy

                        if (pw3.value !== pw4.value) {
                            spanPw3Error.textContent = 'Passwords are not the same.';
                            return;
                        }

                    } else {
                        spanPw3Error.textContent = "Password must have 8 or more characters including at least one uppercase and one lower case alphabetical character and one number and one of ! @ # $ % ^ & * ( ) - = _ + ~ . , \" < > / ? ; : ' | [ ] { }";
                        return;
                    }

                    const params = new URLSearchParams({cmd: 'loadmd', adminpw: pw1.value, npscuserpw: pw3.value});

                    fetch('ws_install.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: params.toString()
                    })
                        .then(response => response.text())
                        .then(data => {
                            if (data) {
                                try {
                                    data = JSON.parse(data);
                                } catch (err) {
                                    alert("Parser error - " + err.message);
                                    return;
                                }

                                if (data.result) {
                                    spanDone.textContent = data.result;
                                    btnMeta.disabled = true;
                                }

                                if (data.error) {
                                    spanPwError.textContent = data.error;
                                }
                            }
                        });

                    pw1.value = '';
                    pw2.value = '';
                    pw3.value = '';
                    pw4.value = '';

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
                        <table>
                            <tr>
                                <td>Admin account password: <input type='password' id='txtpw1'/></td>
                                <td style="padding-left: 0.5em;">Confirm: <input type='password' id='txtpw2'/></td>
                                <td><span id='spanpwerror' style='color:red; margin-left: .5em;'></span></td>
                            </tr>
                            <tr>
                                <td>npscuser account password: <input type='password' id='txtpw3'/></td>
                                <td style="padding-left: 0.5em;">Confirm: <input type='password' id='txtpw4'/></td>
                                <td><span id='spanpw3error' style='color:red; margin-left: .5em;'></span></td>
                            </tr>
                        </table>
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

