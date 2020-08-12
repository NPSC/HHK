<?php
use HHK\Config_Lite\Config_Lite;
use HHK\sec\SecurityComponent;
use HHK\Update\SiteConfig;
use HHK\HTMLControls\HTMLContainer;

/**
 * step1.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("InstallIncludes.php");
//require CLASSES . 'SiteConfig.php';


// Get the site configuration object
$preConfig = new Config_Lite(ciCFG_FILE);
$result = "";
$secureComp = new SecurityComponent(FALSE);

// Set the SSL config parameter
if ($secureComp->isHTTPS()) {
    $preConfig->set('site', 'SSL', 'true');
} else {
    $preConfig->set('site', 'SSL', 'false');
}

// Save SSL state and reopen the config file.
$preConfig->save();
$config = new Config_Lite(ciCFG_FILE);


// Save button
if (isset($_POST['btnSave'])) {
    addslashesextended($_POST);
    try {
        SiteConfig::saveConfig(NULL, $config, $_POST, 'admin');
        $result = "Config file saved.  ";
    } catch (Exception $ex) {
        $result = $ex->getMessage();
    }
}

// Next button
if (isset($_POST['btnNext'])) {
    header('location:step2.php');
}

// Page Markup
$siteURL = HTMLContainer::generateMarkup('p', "Site URL: " . $secureComp->getRootURL());

$tbl = SiteConfig::createCliteMarkup($config, new Config_Lite(REL_BASE_DIR . 'conf' . DS . 'siteTitles.cfg'));

$configuration = $siteURL . $tbl->generateMarkup();
?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Hospitality HouseKeeper Installer</title>
        <script type="text/javascript" src="../<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="../js/install.js"></script>
        <script type="text/javascript">
    $(document).ready(function() {
        "use strict";
        $('#btnTestDb').click(function () {
            var parms = {
                cmd: 'testdb',
                dburl: document.getElementById('dbURL').value,
                dbuser: document.getElementById('dbUser').value,
                dbPW: document.getElementById('dbPassword').value,
                dbSchema: document.getElementById('dbSchema').value,
                dbms: document.getElementById('dbDBMS').value
            };

            testDb(parms);
        });
    });
        </script>
    </head>
    <body>
        <div id="page" style="width:900px;">
            <div class="topNavigation"></div>
            <div>
                <h2 class="logo">Hospitality HouseKeeper Installation Process</h2>
                <a href="showini.php" target="_blank">Show PHP Initialization</a>
                <h3>Step One: Configure Site</h3>
            </div><div class='pageSpacer'></div>
            <div id="content" style="margin:10px; width:100%;">
                <span style="color:red;"><?php echo $result; ?></span>
                <form method="post" action="step1.php" name="form1" id="form1">
<?php echo $configuration ?>
                    <input type="button" id="btnTestDb" value="Test Db Connection" style="margin-left:5px;margin-top:20px;"/>
                    <span id="dbResult" style="color:darkgreen;"></span>
                    <input type="submit" name="btnSave" id="btnSave" value="Save" style="margin-left:700px;margin-top:20px;"/>
                    <input type="submit" name="btnNext" id="btnNext" value="Next" style="margin-left:7px;margin-top:20px;"/>
                </form>
            </div>
        </div>
    </body>
</html>

