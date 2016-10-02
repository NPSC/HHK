<?php
/**
 * ws_install.php
 *
 * @category  Install
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once ("InstallIncludes.php");


addslashesextended($_POST);

//Check request
if (isset($_POST['cmd'])) {
    $c = filter_var($_POST['cmd'], FILTER_SANITIZE_STRING);
} else {
    exit("Missing Request");
}

$events = array();

// switch on command...
if ($c == "testdb") {

    $events = testdb($_POST);


}

// return results.
echo( json_encode($events));
exit();

function testdb($post) {

    $dbURL = '';
    $dbUser = '';
    $pw = '';
    $dbName = '';

    if (isset($post['dburl'])) {
        $dbURL = filter_var($post['dburl'], FILTER_SANITIZE_STRING);
    }
    if (isset($post['dbuser'])) {
        $dbUser = filter_var($post['dbuser'], FILTER_SANITIZE_STRING);
    }
    if (isset($post['dbPW'])) {
        $pw = decryptMessage(filter_var($post['dbPW'], FILTER_SANITIZE_STRING));
    }
    if (isset($post['dbSchema'])) {
        $dbName = filter_var($post['dbSchema'], FILTER_SANITIZE_STRING);
    }


    try {
        $dbh = new PDO(
                'mysql:host=' . $dbURL . ';dbname=' . $dbName . '',
                $dbUser,
                $pw,
                array(PDO::ATTR_PERSISTENT => true)
                );

        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $serverInfo = $dbh->getAttribute(PDO::ATTR_SERVER_VERSION);
        $driver = $dbh->getAttribute(PDO::ATTR_DRIVER_NAME);

    } catch (PDOException $e) {
        return array("error" => $e->getMessage() . "<br/>");
    }

    return array('success'=>'Good! Server version ' . $serverInfo . '; ' . $driver);
}
?>
