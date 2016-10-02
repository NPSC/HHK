<?php
/**
 * UserClass.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
class UserClass {

    public $logMessage = '';

    public function _checkLogin(PDO $dbh, $username, $password, $remember = FALSE) {
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

    public function updateDbPassword(PDO $dbh, $id, $oldPw, $newPw) {

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

    protected static function getUserCredentials(PDO $dbh, $username) {

        $query = "SELECT u.*, a.Role_Id as Role_Id FROM w_users u join w_auth a on u.idName = a.idName  WHERE u.Status='a' and u.User_Name = :uname";
        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->execute(array(":uname" => $username));

        if ($stmt->rowCount() > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows[0];
        }

        return NULL;
    }

    protected static function setSecurityGroups(PDO $dbh, $id, $housePc = FALSE) {

        $grpArray = array();
        $query = "SELECT s.Group_Code, case when w.Cookie_Restricted = 1 then '1' else '0' end as `Cookie_Restricted` FROM id_securitygroup s join w_groups w on s.Group_Code = w.Group_Code WHERE s.idName = :id";
        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->execute(array(":id" => $id));

        if ($stmt->rowCount() > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {

                if ($r["Group_Code"] != "" && ($r['Cookie_Restricted'] == "0" || $housePc)) {
                    $grpArray[] = $r["Group_Code"];
                }
            }

        }
        return $grpArray;
    }

    protected static function _setSession(PDO $dbh, Session $ssn, &$r, $init = true) {

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

            $query = "UPDATE w_users SET Session = :ssn, Ip = :adr, Last_Login=now() WHERE User_Name = :uname;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(":ssn" => $sessionId, ":adr" => $ip, ":uname" => $r["User_Name"]));

            // Log access
            $dbh->query("insert into w_user_log (Username, Access_Date, IP, Session_Id) values ('" . $r['User_Name'] . "', now(), '$ip', '')");
        }
    }

    protected static function _checkSession(PDO $dbh, Session $ssn) {

        if (isset($ssn->username)) {
            $parms = array(
                ":uname" => $ssn->username,
                ":cook" => $ssn->cookie,
                ":ssn" => session_id(),
                ":adr" => $_SERVER['REMOTE_ADDR']
                );

            $query = "SELECT u.*, a.Role_Id as Role_Id FROM w_users u join w_auth a on u.idName = a.idName WHERE u.Status='a' and " .
            "(u.User_Name = :uname) AND (u.Cookie = :cook) AND " .
            "(u.Session = :ssn) AND (u.Ip = :adr)";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute($parms);


            if ($stmt->rowCount() > 0) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $this->_setSession($dbh, $ssn, $rows[0], false, false);
                return true;

            }
        }
        $this->_logout();
        return false;
    }

    public static function _logout() {
        $uS = Session::getInstance();
        $uS->destroy();
    }

}
