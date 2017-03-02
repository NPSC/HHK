<?php
/**
 * step2.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require_once ("InstallIncludes.php");

require_once (SEC . 'UserClass.php');
require_once(SEC . 'ChallengeGenerator.php');
require_once(SEC . 'Login.php');

require_once(CLASSES . 'Patch.php');
require_once(FUNCTIONS . 'mySqlFunc.php');

try {
    $login = new Login();
    $config = $login->initializeSession(ciCFG_FILE);
} catch (PDOException $pex) {
    echo ("Database Error.  " . $pex->getMessage());
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}

// get session instance
$ssn = Session::getInstance();

$pageTitle = $ssn->siteName;

// define db connection obj
$dbh = initPDO();

$errorMsg = '';
$resultAccumulator = "";

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

    } catch (Exception $hex) {
        $errorMsg .= '***' . $hex->getMessage();

    }

} else if (isset($_POST['btnNext'])) {
    header('location:step3.php');
}
?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
    </head>
    <body>
        <div id="page" style="width:900px;">
            <div>
                <h2 class="logo">Hospitality HouseKeeper Installation Process</h2>
                <h3>Step Two: Install Database</h3>
            </div><div class='pageSpacer'></div>
            <div id="content" style="margin:10px; width:100%;">
                <div><span style="color:red;"><?php echo $errorMsg; ?></span></div>
                <form method="post" action="step2.php" name="form1" id="form1">
                    <p>URL: <?php echo $ssn->databaseURL; ?></p>
                    <p>Schema: <?php echo $ssn->databaseName; ?></p>
                    <p>User: <?php echo $ssn->databaseUName; ?></p>
                    <p><?php echo $resultAccumulator; ?></p>
                    
                    <input type="submit" name="btnSave" id="btnSave" value="Install DB" style="margin-left:700px;margin-top:20px;"/>
                    <input type="submit" name="btnNext" id="btnNext" value="Next" style="margin-left:7px;margin-top:20px;"/>
                </form>
            </div>
        </div>
    </body>
</html>

