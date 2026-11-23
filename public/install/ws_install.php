<?php
use HHK\Common;
use HHK\Crypto;
use HHK\Exception\RuntimeException;
use HHK\sec\UserClass;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameRS;
use HHK\Tables\WebSec\W_authRS;
use HHK\Tables\WebSec\W_usersRS;

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
$c = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$events = array();


// switch on command...
if ($c == "testdb") {

    $events = testdb($_POST);


} else if ($c == 'loadmd') {

    $errorMsg = '';

// define db connection obj
    try {
        $dbh = Common::initPDO(TRUE);
    } catch (RuntimeException $hex) {
        echo( json_encode(array('error'=>$hex->getMessage())));
        exit();
    }



    try {
        // Load initialization data
        $filedata = file_get_contents('initialdata.sql');
        $parts = explode('-- ;', $filedata);

        $dbh->beginTransaction();

        foreach ($parts as $q) {

            $q = trim($q);

            if ($q != '') {
                try {
                    $dbh->exec($q);
                } catch (PDOException $pex) {
                    $errorMsg .= 'Rolling back database: ' . $pex->getMessage() . '.  <br> QUERY: ' . $q;
                    if ($dbh->inTransaction()) {
                        $dbh->rollBack();
                    }
                }
            }
        }

        // Create the built-in users
        if ($dbh->inTransaction()) {
            try {
                createInitialUsers($dbh);
            } catch (PDOException $pex) {
                $errorMsg .= 'Rolling back database: ' . $pex->getMessage() . '.  <br> Creating users';
                $dbh->rollBack();
            }
        }

        if ($dbh->inTransaction()) {
            $dbh->commit();
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

/**
 * Create the built-in admin and npscuser accounts and the house organization through EditRS
 * so they are in the audit log.  Passwords are set afterwards with UserClass::setPassword().
 *
 * @param \PDO $dbh
 * @return void
 */
function createInitialUsers(\PDO $dbh) {

    $names = [
        -1 => ['Name_Last' => 'admin', 'Name_First' => 'the', 'Member_Type' => 'ai', 'Member_Status' => 'a', 'Record_Member' => 1, 'Record_Company' => 0, 'Company' => ''],
        10 => ['Name_Last' => 'User', 'Name_First' => 'NPSC', 'Member_Type' => 'ai', 'Member_Status' => 'a', 'Record_Member' => 1, 'Record_Company' => 0, 'Company' => ''],
        11 => ['Name_Last' => '', 'Name_First' => '', 'Member_Type' => 'np', 'Member_Status' => 'a', 'Record_Member' => 0, 'Record_Company' => 1, 'Company' => 'Hospitality House'],
    ];

    foreach ($names as $id => $vals) {
        EditRS::upsert($dbh, new NameRS(), ['idName' => $id], $vals);
    }

    $users = [
        -1 => ['User_Name' => 'admin', 'Enc_PW' => '539e17171312c324d3c23908f85f3149'],
        10 => ['User_Name' => 'npscuser', 'Enc_PW' => 'VEFhRldOWVFqVmZ5bjhENVZvd29ldz09'],
    ];

    foreach ($users as $id => $user) {

        EditRS::upsert($dbh, new W_authRS(), ['idName' => $id], [
            'Role_Id' => '10',
            'Organization_Id' => 'p',
            'Policy_id' => 0,
            'User_Name' => $user['User_Name'],
            'Status' => 'a',
            'Updated_By' => 'admin',
            'Last_Updated' => date('Y-m-d H:i:s'),
        ]);

        EditRS::upsert($dbh, new W_usersRS(), ['idName' => $id], [
            'User_Name' => $user['User_Name'],
            'Enc_PW' => $user['Enc_PW'],
            'Chg_PW' => 0,
            'pass_rules' => 0,
            'Status' => 'a',
            'Verify_Address' => 'done',
        ]);
    }
}

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
        $pw = Crypto::decryptMessage(filter_var($post['dbPW'], FILTER_UNSAFE_RAW));
    }
    if (isset($post['dbSchema'])) {
        $dbName = filter_var($post['dbSchema'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }


    try {

        $dbh = Common::initPDO();

        $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $serverInfo = $dbh->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $driver = $dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);

    } catch (PDOException $e) {
        return array("error" => $e->getMessage() . "<br/>");
    }

    return array('success'=>'Good! Server version ' . $serverInfo . '; ' . $driver);
}

