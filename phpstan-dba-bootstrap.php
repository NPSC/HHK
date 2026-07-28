<?php // phpstan-dba-bootstrap.php

use HHK\Common;
use HHK\sec\Login;
use staabm\PHPStanDba\DbSchema\SchemaHasherMysql;
use staabm\PHPStanDba\QueryReflection\PdoMysqlQueryReflector;
use staabm\PHPStanDba\QueryReflection\RuntimeConfiguration;
use staabm\PHPStanDba\QueryReflection\MysqliQueryReflector;
use staabm\PHPStanDba\QueryReflection\QueryReflection;
use staabm\PHPStanDba\QueryReflection\ReplayAndRecordingQueryReflector;
use staabm\PHPStanDba\QueryReflection\ReplayQueryReflector;
use staabm\PHPStanDba\QueryReflection\ReflectionCache;

require_once __DIR__ . '/vendor/autoload.php';


define('ciCFG_FILE', 'site.cfg' );
define('CONF_PATH', 'conf/');

if (file_exists('vendor/autoload.php')) {
    require('vendor/autoload.php');
} else {
    exit("Unable to laod dependancies, be sure to run 'composer install'");
}

try {
    $login = new Login();
    $login->initHhkSession('conf/', 'site.cfg');

} catch (\Exception $ex) {
    session_unset();
    http_response_code(500);
    exit ();
}

try {
    $dbh = Common::initPDO(TRUE);
} catch (RuntimeException $hex) {
    // Databasae not set up.  Nothing we can do.
    http_response_code(200);
    exit();
}

$config = new RuntimeConfiguration();
 $config->debugMode(true);
// $config->stringifyTypes(true);
// $config->analyzeQueryPlans(true);
// $config->utilizeSqlAst(true);

// Alternatively you can use PdoMysqlQueryReflector, PdoPgSqlQueryReflector instead
$reflector = new PdoMysqlQueryReflector($dbh);

/*
$cacheFile = __DIR__.'/.phpstan-dba.cache';
$reflector = new ReplayAndRecordingQueryReflector(
    ReflectionCache::create(
        $cacheFile
    ),
    new SchemaHasherMysql($mysqli)
    $reflector
);
*/

QueryReflection::setupReflector(
    $reflector,
    $config
);