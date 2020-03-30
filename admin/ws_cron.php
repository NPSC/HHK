<?php

require ("AdminIncludes.php");
require (CLASSES . "cron/cron.php");
require (CLASSES . "cron/disableUserJob.php");
require CLASSES . 'SiteLog.php';

$dbh = initPDO(TRUE);
$uS = Session::getInstance();

$disableUser = new disableUserJob($dbh, $uS);

$disableUser->run();

?>