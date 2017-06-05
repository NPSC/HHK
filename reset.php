<?php

//The MIT License
//
//Copyright 2017 Eric Crane <ecrane at nonprofitsoftwarecorp.org>.
//
//Permission is hereby granted, free of charge, to any person obtaining a copy
//of this software and associated documentation files (the "Software"), to deal
//in the Software without restriction, including without limitation the rights
//to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
//copies of the Software, and to permit persons to whom the Software is
//furnished to do so, subject to the following conditions:
//
//The above copyright notice and this permission notice shall be included in
//all copies or substantial portions of the Software.
//
//THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
//IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
//FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
//AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
//LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
//OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
//THE SOFTWARE.



define('DS', DIRECTORY_SEPARATOR);
define('P_ROOT', dirname(__FILE__) . DS);
define('ciCFG_FILE', P_ROOT . 'conf' . DS . 'site.cfg');

require 'classes' . DS . 'SiteConfig.php';
require ('classes' . DS .'PDOdata.php');
require ('functions' . DS . 'commonFunc.php');
require ('classes' . DS. 'config'. DS . 'Lite.php');
require ('classes' . DS. 'sec' .DS . 'sessionClass.php');
require ('classes' . DS . 'SysConst.php');
require ('classes' . DS . 'HTML_Controls.php');


// Get the site configuration object
$config = new Config_Lite(ciCFG_FILE);

// get session instance
$uS = Session::getInstance();
$result = '';
$pageTitle = $config->getString("site", "Site_Name", "Hospitality House");
$build = 'Build:' . $config->getString('code', 'Version', '*') . '.' . $config->getString('code', 'Build', '*');
$copyYear = date('Y');

if (isset($_POST['btnSave'])) {

    addslashesextended($_POST);

    try {
        SiteConfig::saveConfig(NULL, $config, $_POST, 'admin');
        $result = "Config file saved.  ";

        $dbConfig = $config->getSection('db');

        if (is_array($dbConfig)) {
            $ssn->databaseURL = $dbConfig['URL'];
            $ssn->databaseUName = $dbConfig['User'];
            $ssn->databasePWord = decryptMessage($dbConfig['Password']);
            $ssn->databaseName = $dbConfig['Schema'];
            $ssn->dbms = $dbConfig['DBMS'];
        } else {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Bad Database Configurtion");
        }

        initPDO();

        header('location: ' . $config->getString('site','Site_URL', ''));

    } catch (Exception $ex) {
        $result = $ex->getMessage();
    }
}

if (isset($_GET['r'])) {
    $result = filter_var($_GET['r'], FILTER_SANITIZE_STRING);
}

$tbl = SiteConfig::createCliteMarkup($config, new Config_Lite('conf' . DS . 'siteTitles.cfg'), 'db');

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link rel="icon" type="image/png" href="images/hhkIcon.png" />
        <link href='root.css' rel='stylesheet' type='text/css' />
    </head>
    <body>
        <div id="page">
            <div class="topNavigation"></div>
            <div>
                <h2 class="hhk-title">
                    <?php echo $pageTitle; ?>
                </h2>
            </div><div class='pageSpacer'></div>
            <div style="float:right;font-size: .6em;margin-right:2px;"><?php echo $build; ?></div>
            <div id="content" style="clear:both; margin-left: 100px;margin-top:10px;">

                <div style="margin: auto; float:left;">

                <form method="post" action="reset.php" name="form1" id="form1">
                    <h2>Set up database connection credentials</h2>
                    <?php echo $tbl->generateMarkup(array('class'=>'hhk-tdbox')); ?>

                    <input type="submit" name="btnSave" id="btnSave" value="Save" style="margin-left:700px;margin-top:20px;"/>
                </form>
                <p style="color:red;margin:10px;"><?php echo $result; ?></p>
                <div style="margin-top:20px;"><a href ="http://nonprofitsoftwarecorp.org" ><div class="nplogo"></div></a></div>
                <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software</div>

                </div>
            </div>
        </div>
    </body>
</html>
