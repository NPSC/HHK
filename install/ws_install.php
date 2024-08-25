<?php
use HHK\Exception\RuntimeException;
use HHK\sec\UserClass;

/**
 * ws_install.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK

 */

require_once ("InstallIncludes.php");

//Check request
if (filter_has_var(INPUT_POST, 'cmd')) {
    $c = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$events = array();


// switch on command...
if ($c == "testdb") {

    $events = testdb($_POST);


} else if ($c == 'loadmd') {

    $errorMsg = '';

// define db connection obj
    try {
        $dbh = initPDO(TRUE);
    } catch (RuntimeException $hex) {
        echo( json_encode(array('error'=>$hex->getMessage())));
        exit();
    }



    try {
        // Load initialization data
        $filedata = file_get_contents('initialdata.sql');
        $parts = explode('-- ;', $filedata);

        foreach ($parts as $q) {

            $q = trim($q);

            if ($q != '') {
                try {
                    $dbh->exec($q);
                } catch (PDOException $pex) {
                    $errorMsg .= $pex->getMessage() . '.  ';
                }
            }
        }

        // Update admin password
        if (filter_has_var(INPUT_POST, 'adminpw')) {

            $adminPw = filter_input(INPUT_POST, 'adminpw', FILTER_UNSAFE_RAW);

            $uclass = new UserClass();
            if ($uclass->setPassword($dbh, -1, $adminPw)) {
                $events['result'] = "Admin Password set.  ";
            } else {
                $errorMsg .= "Admin Password set.  ";
            }
        }

        // Update npscuser password
        if (filter_has_var(INPUT_POST, 'npscuserpw')) {

            $npscuserPw = filter_input(INPUT_POST, 'npscuserpw', FILTER_UNSAFE_RAW);

            $uclass = new UserClass();
            if ($uclass->setPassword($dbh, 10, $npscuserPw)) {
                $events['result'] .= "npscuser Password set.  ";
            } else {
                $errorMsg .= "npscuser Password set.  ";
            }
        }

    } catch (Exception $ex) {
        $errorMsg .= "Installer Error: " . $ex->getMessage();
    }

    if ($errorMsg != '') {
        $events['error'] = $errorMsg;
    }

}

// return results.
echo( json_encode($events));
exit();

function testdb($post) {

    $dbms = '';
    $dbURL = '';
    $dbUser = '';
    $pw = '';
    $dbName = '';

    if (isset($post['dbms'])) {
        $dbms = filter_var($post['dbms'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
    if (isset($post['dburl'])) {
        $dbURL = filter_var($post['dburl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
    if (isset($post['dbuser'])) {
        $dbUser = filter_var($post['dbuser'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
    if (isset($post['dbPW'])) {
        $pw = decryptMessage(filter_var($post['dbPW'], FILTER_UNSAFE_RAW));
    }
    if (isset($post['dbSchema'])) {
        $dbName = filter_var($post['dbSchema'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }


    try {

        $dbh = initPDO();

        $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $serverInfo = $dbh->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $driver = $dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);

    } catch (PDOException $e) {
        return array("error" => $e->getMessage() . "<br/>");
    }

    return array('success'=>'Good! Server version ' . $serverInfo . '; ' . $driver);
}

