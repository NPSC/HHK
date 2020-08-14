<?php

use HHK\sec\{Session, Login, ScriptAuthClass, UserClass};
use HHK\Update\UpdateSite;

/*
 * The MIT License
 *
 * Copyright 2018 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * ws_update.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * GET params:
 *  cd = string, site identifier
 *  un = string, user name
 *  so = string, MD5 encoded password
 *  ck = check password only, returns 'bubbly' if correct
 *
 * Returns:
 *  error = string, access error message.  Update not attempted.
 *
 * or:
 *  resultMsg = HTML string, the results of the update
 *  errorMsg = HTML string, any errors encountered during the update
 *
 */
require ("AdminIncludes.php");

require (FUNCTIONS . 'mySqlFunc.php');


$uS = Session::getInstance();

$ck = '';
$cd = '';
$un = '';
$so = '';
$events = array('init'=>'Im here');


// Check input
if (isset($_GET['cd'])) {
    $cd = filter_input(INPUT_GET, 'cd');
}
if (isset($_GET['so'])) {
    $so = filter_input(INPUT_GET, 'so');
}
if (isset($_GET['un'])) {
    $un = filter_input(INPUT_GET, 'un');
}
if (isset($_GET['ck'])) {
    $ck = filter_input(INPUT_GET, 'ck');
}

if ($cd == '' || $so == '' || $un == '') {
    $uS->destroy(true);
    exit();
}

// Initialize
try {

    $login = new Login();
    $config = $login->initHhkSession(ciCFG_FILE);

    // define db connection obj
    $dbh = initPDO(TRUE);

    // Load the page information
    $page = new ScriptAuthClass($dbh);

} catch (Exception $ex) {

    $uS->destroy(true);
    echo (json_encode(array('error'=>"Server Error: " . $ex->getMessage())));
    exit();
}

// Check site identifier
if ($cd !== $config->getString('db', 'Schema', '')) {

    $uS->destroy(true);
    echo(json_encode(array('error'=>"Bad Site Identifier")));
    exit();
}

$user = new UserClass();

// validate username and password
$record = $user->getUserCredentials($dbh, $un);

if (is_array($record) && isset($record['Enc_PW']) && $record['Enc_PW'] === $so) {

    if (strtolower($ck) == 'y') {
        // password check
        $events['resultMsg'] = 'bubbly';

    } else {

        //perform update
        $uS->regenSessionId();

        // Record the login.
        $user->setSession($dbh, $uS, $record);

        // Must be THE ADMIN
        if ($page->is_TheAdmin()) {

            $update = new UpdateSite();

            $update->doUpdate($dbh);

            $events['errorMsg'] = $update->getErrorMsg();
            $events['resultMsg'] = $update->getResultAccumulator();

        } else {
            $events['error'] = 'This user does not enjoy site update priviledges.';
        }
    }

} else {
    $events['error'] = 'Bad username or password.';
}



// Leave
echo( json_encode($events) );
$uS->destroy(true);
exit();