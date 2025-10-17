<?php

use HHK\sec\{Session, Login, ScriptAuthClass, UserClass};
use HHK\Update\UpdateSite;
use HHK\sec\WebInit;

/**
 * ws_session.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


require ("AdminIncludes.php");

$uS = Session::getInstance();

$cmd = 'get';
if (isset($_GET['cmd'])) {
    $cmd = filter_input(INPUT_GET, 'cmd');
}

// Initialize
try {

    $login = new Login();
    $dbh = $login->initHhkSession(CONF_PATH, ciCFG_FILE);

} catch (Exception $ex) {

    $uS->destroy(true);
    echo (json_encode(array('error'=>"Server Error: " . $ex->getMessage())));
    exit();
}

switch ($cmd){
    case "get":
        $expiresIn = false;
        if(isset($uS->timeout_idle)){
            $expiresIn = $uS->timeout_idle - time();
        }

        $events = array('ExpiresIn'=>$expiresIn);
        break;
    case "extend":
        try{
            new WebInit();

            $expiresIn = false;
            if(isset($uS->timeout_idle)){
                $expiresIn = $uS->timeout_idle - time();
            }

            $events = array('ExpiresIn'=>$expiresIn);
        }catch(\Exception $e){
            $events = array('error'=>$e->getMessage());
        }
        break;
}
echo( json_encode($events) );
exit();