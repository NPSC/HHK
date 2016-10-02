<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require 'AutoIncludes.php';

function runJob($url) {

    return file_get_contents($url, null, stream_context_create(
        array(
        'http' => array(
            'method' => 'GET',
            'protocol_version' => 1.1,
            'header' => array('Connection: close')
        )
    )));

}


$config = new Config_Lite('auto.cfg');

// Run once for each entry
foreach ($config as $secName => $secArray) {

    if ($secName === 'jobs') {

        foreach ($secArray as $val) {

            if ($val == '') {
                continue;
            }

            $success = runJob($val);
            if ($success != '') {
                echo("Error on job $val: " . $success);
            }
        }
    }
}

exit();
