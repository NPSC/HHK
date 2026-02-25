<?php

use HHK\Debug\DebugBarSupport;
use HHK\sec\{Session, Login};
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

switch ($cmd){
    case "debugbarOpen":
        if($uS->logged && $uS->username){
            DebugBarSupport::handleOpenRequest();
            exit();
        }
        $events = array('error'=>"unauthorized");
        break;
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
