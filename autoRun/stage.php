<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require 'AutoIncludes.php';
require 'EmailRegister.php';
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

try {

    $login = new Login();
    $config = $login->initializeSession(ciCFG_FILE);

} catch (PDOException $pex) {
    exit ("<h3>Database Error.  </h3>");

} catch (Exception $ex) {
    exit ("<h3>" . $ex->getMessage());
}

// define db connection obj
$dbh = initPDO(TRUE);


$emailRegister = new EmailRegister();
$emailRegister->runReport($dbh, $config);

exit();
