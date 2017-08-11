<?php
/**
 * ws_install.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK

 */

require_once ("InstallIncludes.php");
require (CLASSES . 'PDOdata.php');
require (DB_TABLES . 'WebSecRS.php');
require (DB_TABLES . 'HouseRS.php');
require (SEC . 'UserClass.php');


addslashesextended($_POST);

//Check request
if (isset($_POST['cmd'])) {
    $c = filter_var($_POST['cmd'], FILTER_SANITIZE_STRING);
}

$events = array();


// switch on command...
if ($c == "testdb") {

    $events = testdb($_POST);


} else if ($c == 'loadmd') {

    $dbh = initPDO();
    $errorMsg = '';

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

    // Update websites table
    try {
        $config = new Config_Lite(ciCFG_FILE);
        updateWebSites($dbh, $config);

    } catch (Exception $ex) {
        $ssn->destroy();
        $errorMsg .= $ex . " Configurtion file path = " . ciCFG_FILE . '.  ';
    }


    // Update admin password
    if (isset($_POST['new'])) {

        $newPw = filter_var($_POST['new'], FILTER_SANITIZE_STRING);

        $uclass = new UserClass();
        if ($uclass->setPassword($dbh, -1, $newPw)) {
            $events['result'] = "Admin Password set.  ";
        } else {
            $errorMsg .= "Admin Password set.  ";
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
        $dbms = filter_var($post['dbms'], FILTER_SANITIZE_STRING);
    }
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

        switch ($dbms) {

            case 'MS_SQL':
                $dbh = initMS_SQL($dbURL, $dbName, $dbUser, $pw);
                break;

            case 'MYSQL':
                $dbh = initMY_SQL($dbURL, $dbName, $dbUser, $pw);
                break;

            case 'ODBC':
                $dbh = initODBC($dbURL, $dbName, $dbUser, $pw);
                return array('success'=>'Good!');
                break;

            default:
                return array("error" => "Bad DBMS: " . $dbms . "<br/>");

        }

        $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $serverInfo = $dbh->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $driver = $dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);

    } catch (PDOException $e) {
        return array("error" => $e->getMessage() . "<br/>");
    }

    return array('success'=>'Good! Server version ' . $serverInfo . '; ' . $driver);
}

function updateWebSites(\PDO $dbh, Config_Lite $config) {

    // Update website table
    $webRS = new Web_SitesRS();
    $rows = EditRS::select($dbh, $webRS, array());

    foreach ($rows as $w) {

        $webRS = new Web_SitesRS();
        EditRS::loadRow($w, $webRS);

        $host = '';

        switch ($webRS->Site_Code->getStoredVal()) {

            case 'a':
                $host = $config->getString('site', 'Admin_URL', '');
                break;


            case 'h':
                $host = $config->getString('site', 'House_URL', '');
                break;

            case 'v':
                $host = $config->getString('site', 'Volunteer_URL', '');

                if ($host == '') {
                    // delete the volunteer row.
                    EditRS::delete($dbh, $webRS, array($webRS->Site_Code));
                }

                break;

            case 'r':
                $host = $config->getString('site', 'Site_URL', '');
                break;
        }

        if ($host == '') {
            continue;
        }

        $url = parse_url($host);

        $webRS->HTTP_Host->setNewVal($url['host']);

        if (isset($url['path'])) {
            $webRS->Relative_Address->setNewVal($url['path']);
        }

        EditRS::update($dbh, $webRS, array($webRS->idweb_sites));
    }
}

