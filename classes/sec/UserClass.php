<?php
/**
 * UserClass.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class UserClass {

    public $logMessage = '';

    public function _checkLogin(\PDO $dbh, $username, $password, $remember = FALSE) {
         // instantiate a ChallengeGenerator object
        $chlgen = new ChallengeGenerator(FALSE);

        // get challenge variable
        $challenge = $chlgen->getChallengeVar('challenge');

        if ($challenge === FALSE) {
            $this->logMessage = "Challange variable is missing.  ";
            return FALSE;
        }

        if ($chlgen->testTries() === FALSE) {
            $this->logMessage = "To many log-in attempts.  ";
            return FALSE;
        }

        $chlgen->incrementTries();

        $r = self::getUserCredentials($dbh, $username);

        if ($r != NULL && md5($r['Enc_PW'] . $challenge) == $password) {

            //Regenerate session ID to prevent session fixation attacks
            $ssn = Session::getInstance();
            $ssn->regenSessionId();

            // Get magic PC cookie
            $housePc = FALSE;
            if (isset($_COOKIE["housepc"])) {
                if (decryptMessage(filter_var($_COOKIE['housepc'], FILTER_SANITIZE_STRING)) == filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) . 'eric') {
                    $housePc = TRUE;
                }
            }

            self::_setSession($dbh, $ssn, $r);

            $ssn->groupcodes = self::setSecurityGroups($dbh, $r['idName'], $housePc);

            return TRUE;

        } else {
            $this->logMessage = "Bad username or password.  ";
        }

        return FALSE;
    }

    public function updateDbPassword(\PDO $dbh, $id, $oldPw, $newPw) {

        $ssn = Session::getInstance();

        // Are we legit?
        $success = $this->_checkLogin($dbh, $ssn->username, $oldPw);

        if ($success) {
            $query = "update w_users set Last_Updated = now(), Updated_By = :uname, Enc_PW = :newPw where idName = :id and Status='a';";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':uname'=>$ssn->username, ':newPw'=>$newPw, ':id'=>$id));

            if ($stmt->rowCount() == 1) {

                return TRUE;
            }
        }
        return FALSE;
    }

    public function setPassword(\PDO $dbh, $id, $newPw) {

        if ($newPw != '' && $id != 0) {

            $query = "update w_users set Last_Updated = now(), Updated_By = 'install', Enc_PW = :newPw where idName = :id;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':newPw'=>$newPw, ':id'=>$id));

            if ($stmt->rowCount() == 1) {
                return TRUE;
            }
        }
        return FALSE;
    }

    protected static function getUserCredentials(\PDO $dbh, $username) {

        if (!is_string($username)) {
            return NULL;
        }

        $uname = str_ireplace("'", "", $username);

        $stmt = $dbh->query("SELECT u.*, a.Role_Id as Role_Id FROM w_users u join w_auth a on u.idName = a.idName  WHERE u.Status='a' and u.User_Name = '$uname'");

        if ($stmt->rowCount() > 0) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows[0];
        }

        return NULL;
    }

    protected static function setSecurityGroups(\PDO $dbh, $idName, $housePc = FALSE) {

        $id = intval($idName, 10);

        $grpArray = array();
        $query = "SELECT s.Group_Code, case when w.Cookie_Restricted = 1 then '1' else '0' end as `Cookie_Restricted` FROM id_securitygroup s join w_groups w on s.Group_Code = w.Group_Code WHERE s.idName = $id";
        $stmt = $dbh->query($query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($r["Group_Code"] != "" && ($r['Cookie_Restricted'] == "0" || $housePc)) {
                $grpArray[$r["Group_Code"]] = $r["Group_Code"];
            }
        }

        return $grpArray;
    }

    protected static function _setSession(\PDO $dbh, Session $ssn, &$r, $init = true) {

        $ssn->uid = $r["idName"];
        $ssn->username = htmlspecialchars($r["User_Name"]);
        $ssn->cookie = $r["Cookie"];
        $ssn->vaddr = $r["Verify_Address"];

        if ($r["Role_Id"] == "") {
            $ssn->rolecode = WebRole::DefaultRole;
        } else {
            $ssn->rolecode = $r["Role_Id"];
        }

        $ssn->logged = true;
        unset($ssn->Challtries);

        if ($init) {
            $sessionId = session_id();
            $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);

            $query = "UPDATE w_users SET Session = '$sessionId', Ip = '$ip', Last_Login=now() WHERE User_Name = '" . $ssn->username . "'";
            $dbh->exec($query);

            // Log access
            $dbh->exec("insert into w_user_log (Username, Access_Date, IP, Session_Id) values ('" . $ssn->username . "', now(), '$ip', '')");
        }
    }

//    protected static function _checkSession(\PDO $dbh, Session $ssn) {
//
//        if (isset($ssn->username)) {
//            $parms = array(
//                ":uname" => $ssn->username,
//                ":cook" => $ssn->cookie,
//                ":ssn" => session_id(),
//                ":adr" => $_SERVER['REMOTE_ADDR']
//                );
//
//            $query = "SELECT u.*, a.Role_Id as Role_Id FROM w_users u join w_auth a on u.idName = a.idName WHERE u.Status='a' and " .
//            "(u.User_Name = :uname) AND (u.Cookie = :cook) AND " .
//            "(u.Session = :ssn) AND (u.Ip = :adr)";
//            $stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
//            $stmt->execute($parms);
//
//
//            if ($stmt->rowCount() > 0) {
//                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
//
//                $this->_setSession($dbh, $ssn, $rows[0], false, false);
//                return true;
//
//            }
//        }
//        $this->_logout();
//        return false;
//    }

    public static function _logout() {
        $uS = Session::getInstance();
        $uS->destroy();
    }

}
