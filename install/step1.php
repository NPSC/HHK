<?php
/**
 * step1.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("InstallIncludes.php");
require CLASSES . 'SiteConfig.php';

    function createMarkup(Config_Lite $config) {

        $hostName = '';
        $serverName = filter_input(INPUT_SERVER, "SERVER_NAME", FILTER_SANITIZE_URL);;

        if ($serverName === FALSE || is_string($serverName) === FALSE) {
            exit("Server Name is not a string?  " . $serverName);
        }

        $requestURI = filter_input(INPUT_SERVER, "REQUEST_URI", FILTER_SANITIZE_URL);;

        if ($requestURI === FALSE || is_string($requestURI) === FALSE) {
            exit("Reqauest URI is not a string?  " . $requestURI);
        }

        $hostParts = explode(".", $serverName);
        if (strtolower($hostParts[0]) == "www") {
            unset($hostParts[0]);
            $hostName = substr(implode(".", $hostParts), 1);
        } else {
            $hostName = $serverName;
        }

        $serverHTTPS = filter_input(INPUT_SERVER, "HTTPS", FILTER_SANITIZE_STRING);
        $protocol = 'HTTP://';

        if (empty($serverHTTPS) || strtolower($serverHTTPS) == 'off' ) {
            $protocol = 'https://';
        }

        $path = "";
        // find the path
        $parts = explode("/", $requestURI);

        $path = implode("/", array_slice($parts, 0, count($parts) - 2)) . '/';

        $stbl = new HTMLTable();
        $stbl->addBodyTr(HTMLTable::makeTh("Site URL") . HTMLTable::makeTd(
                HTMLContainer::generateMarkup('span', $protocol . $hostName . $path, array('id'=>'spnSiteURL'))));

        $tbl = SiteConfig::createCliteMarkup($config, new Config_Lite(REL_BASE_DIR . 'conf' . DS . 'siteTitles.cfg'));  //new HTMLTable();

        return $stbl->generateMarkup() . $tbl->generateMarkup();
    }


// Get the site configuration object
$config = new Config_Lite(ciCFG_FILE);
$result = "";

if (isset($_POST['btnSave'])) {
    addslashesextended($_POST);
    try {
        SiteConfig::saveConfig(NULL, $config, $_POST, 'admin');
        $result = "Config file saved.  ";
    } catch (Exception $ex) {
        $result = $ex->getMessage();
    }
}


if (isset($_POST['btnNext'])) {
    header('location:step2.php');
}

$configuration = createMarkup($config);

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
                <a href="showini.php">Show PHP Initialization</a>
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

