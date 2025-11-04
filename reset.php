<?php

use HHK\Crypto;
use HHK\sec\Login;
use HHK\Update\SiteConfig;
use HHK\sec\Session;
use HHK\sec\SecurityComponent;
use HHK\SysConst\CodeVersion;

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
define('CONF_PATH',  P_ROOT . 'conf' . DS);
define('ciCFG_FILE', 'site.cfg');

if (file_exists('vendor/autoload.php')) {
    require('vendor/autoload.php');
} else {
    exit("Unable to laod dependancies, be sure to run 'composer install'");
}


function testdb($ssn) {

    try {

    	$dbuName = $ssn->databaseUName;
    	$dbPw = $ssn->databasePWord;
    	$dbHost = $ssn->databaseURL;
    	$dbName = $ssn->databaseName;

    	$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

    	$options = [
    			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    			PDO::ATTR_EMULATE_PREPARES   => false,
    	];

    	$dbh = new PDO($dsn, $dbuName, $dbPw, $options);

    } catch (PDOException $e) {
        return 'Database Error:  ' . $e;
    }

    return '';
}

// Get the site configuration object
$config = parse_ini_file(CONF_PATH.ciCFG_FILE, true);

// get session instance
$ssn = Session::getInstance();
$secureComp = new SecurityComponent();

if(isset($config['db'])){
    $dbConfig = $config['db'];
}else{
    $ssn->destroy();
    exit("Database configuration section (db) is missing");
}


if (is_array($dbConfig)) {
    $ssn->databaseURL = $dbConfig['URL'];
    $ssn->databaseUName = $dbConfig['User'];
    $ssn->databasePWord = Crypto::decryptMessage($dbConfig['Password']);
    $ssn->databaseName = $dbConfig['Schema'];
    $ssn->dbms = $dbConfig['DBMS'];
} else {
    $ssn->destroy(TRUE);
    exit("Bad Database Configuration Section (db)");
}


// Check database
$result = testdb($ssn);

if ($result == '') {
    // database ok.
    header('location: ' . $secureComp->getRootURL());
    exit();
}

//
// Database credentials are wrong past here.
//


if (isset($_POST['btnSave'])) {

    try {
        SiteConfig::saveConfig(NULL, $config, $_POST, 'admin');

        $ssn->destroy(TRUE);
        header('location: ' . $secureComp->getRootURL());
        exit();

    } catch (Exception $ex) {
        $result = $ex->getMessage();
    }
}

if (isset($_GET['r'])) {
    $result = filter_var($_GET['r'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$pageTitle = ($ssn->siteName ? $ssn->siteName : "Hospitality Housekeeper");
$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;
$copyYear = date('Y');

$tbl = SiteConfig::createCliteMarkup($dbConfig);
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link rel="icon" type="image/png" href="images/hhkIcon.png" />
        <link href='css/bootstrap-grid.min.css' rel='stylesheet' type='text/css' />
        <link href='css/root.css' rel='stylesheet' type='text/css' />
    </head>
    <body>
        <div id="page">
            <div class='pageHeader'>
                <h2 class="px-3 py-2">
                    <?php echo $pageTitle; ?>
                </h2>
            </div>
            <div class="build"><?php echo $build; ?></div>
            <div id="content" class="container mt-5">

                <div class="row justify-content-md-center mb-3">

                    <form method="post" action="reset.php" name="form1" id="form1">
                        <h2>Set up database connection credentials</h2>
                        <?php echo $tbl->generateMarkup(array('class'=>'hhk-tdbox')); ?>

                        <input type="submit" name="btnSave" id="btnSave" value="Save" style="margin-left:700px;margin-top:20px;"/>
                    </form>
                    <p style="color:red;margin:10px;"><?php echo $result; ?></p>
                </div>
                <?php echo Login::getFooterMarkup(); ?>
            </div>
        </div>
    </body>
</html>
