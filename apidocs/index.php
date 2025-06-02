<?php

use HHK\sec\Login;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use HHK\SysConst\CodeVersion;

define('DS', DIRECTORY_SEPARATOR);
define('P_ROOT',  dirname(__FILE__) . DS . '..' . DS);
define('CONF_PATH', P_ROOT . 'conf' . DS);
define('ciCFG_FILE', 'site.cfg');
date_default_timezone_set('America/Chicago');

if (file_exists('../vendor/autoload.php')) {
    require('../vendor/autoload.php');
} else {
    exit("Unable to laod dependancies, be sure to run 'composer install'");
}

require ('../functions' . DS . 'commonFunc.php');

try {
    $dbh = Login::initHhkSession(CONF_PATH, ciCFG_FILE);
    $uS = Session::getInstance();
}catch (\Exception $e){
    exit($e->getMessage());
}

$pageTitle = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');
$siteName = '<h2 class="center">Hospitality Housekeeper</h2>';
$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>HHK API Documentation</title>
    
    <link href="../house/css/jqui/jquery-ui.min.css" rel="stylesheet" type="text/css" />
    <link href='../css/bootstrap-grid.min.css' rel='stylesheet' type='text/css' />
    <link rel="stylesheet" type="text/css" href="./swagger-ui.css" />
    <link rel="stylesheet" type="text/css" href="index.css" />
    <link rel="icon" type="image/svg+xml" href="../favicon.svg" />
    <link href='../css/root.css' rel='stylesheet' type='text/css' />
  </head>

  <body>
    <div id="page">
            <div class='pageHeader'>
                <h2 class="px-3 py-2">
                    <?php echo $pageTitle; ?>
                </h2>
            </div>
            <div class="build"><?php echo $build; ?></div>
            <div id="contentDiv" class="container mx-auto">
              <div class="row justify-content-center">
                <div class="col">
                  <div id="swagger-ui"></div>
                </div>
              </div>
            </div>
      </div>
    <script type="text/javascript" src="../js/jquery-min.js"></script>
    <script type="text/javascript" src="../js/jquery-ui.min.js"></script>
    <script src="./swagger-ui-bundle.js" charset="UTF-8"> </script>
    <script src="./swagger-ui-standalone-preset.js" charset="UTF-8"> </script>
    <script src="./swagger-initializer.js" charset="UTF-8"> </script>
  </body>
</html>