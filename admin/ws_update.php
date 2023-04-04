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
 * POST params:
 *  cd = string, site identifier
 *  un = string, user name
 *  so = string, password
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
if (isset($_POST['cd'])) {
    $cd = filter_input(INPUT_POST, 'cd');
}
if (isset($_POST['so'])) {
    $so = filter_input(INPUT_POST, 'so');
}
if (isset($_POST['un'])) {
    $un = filter_input(INPUT_POST, 'un');
}
if (isset($_POST['ck'])) {
    $ck = filter_input(INPUT_POST, 'ck');
}

if ($cd == '' || $so == '' || $un == '') {
    $uS->destroy(true);
    exit();
}

// Initialize
try {

    $login = new Login();
    $dbh = $login->initHhkSession(CONF_PATH, ciCFG_FILE);

    // Load the page information
    $page = new ScriptAuthClass($dbh);

} catch (Exception $ex) {

    $uS->destroy(true);
    echo (json_encode(array('error'=>"Server Error: " . $ex->getMessage())));
    exit();
}

// Check site identifier
if ($cd !== $uS->databaseName) {

    $uS->destroy(true);
    echo(json_encode(array('error'=>"Bad Site Identifier: " . $cd)));
    exit();
}


// Log in
$user = new UserClass();

if($user->_checkLogin($dbh, $un, $so, false, false)){

	// Must be THE ADMIN
	if ($page->is_TheAdmin()) {

	    if (strtolower($ck) == 'y') {
	        // password check
	        $events['resultMsg'] = 'bubbly';

	    } else {

	        //perform update
	        $update = new UpdateSite();
	        $update->doUpdate($dbh);

	        $events['errorMsg'] = $update->getErrorMsg();
	        $events['resultMsg'] = $update->getResultAccumulator();

	    }

	} else {
		$events['error'] = 'This user does not enjoy site update priviledges.  username = ' . $un;
	}

} else {
    $events['error'] = $user->logMessage;
}



// Leave
echo( json_encode($events) );
$uS->destroy(true);
exit();