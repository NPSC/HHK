<?php
namespace sec;

/**
 * UserClass.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class UserClass
{

    public $logMessage = '';

    protected $defaultPage = '';

    const PW_Changed = 'PC';

    const PW_New = 'PS';

    const Login = 'L';
    
    const Lockout = 'PL';
    
    const Expired = 'E';

    public function _checkLogin(\PDO $dbh, $username, $password, $remember = FALSE)
    {
        $ssn = Session::getInstance();

        if ($this->testTries() === FALSE) {
            $this->logMessage = "To many log-in attempts.  ";
            return FALSE;
        }

        $this->incrementTries();

        $r = self::getUserCredentials($dbh, $username);

        // disable user if inactive || force password reset
        if ($r != NULL) {
            $r = self::disableInactiveUser($dbh, $r); // returns updated user array
            $r = self::setPassExpired($dbh, $r);
        }

        //check PW
        $match = false;
        //new method
        if($r != NULL && stripos($r['Enc_PW'], '$argon2id') === 0 && isset($ssn->sitePepper) && password_verify($password . $ssn->sitePepper, $r['Enc_PW'])){
            $match = true;
        }else if ($r != NULL && $r['Enc_PW'] == md5($password)) { //old method
            $match = true;
        }
        
        if ($match && $r['Status'] == 'a') {

            // Regenerate session ID to prevent session fixation attacks
            $ssn = Session::getInstance();
            $ssn->regenSessionId();
            
            //reset login tries
            $this->resetTries();

            // Get magic PC cookie
            $housePc = FALSE;
            if (filter_has_var(INPUT_COOKIE, 'housepc')) {

                $remoteIp = self::getRemoteIp();

                if (decryptMessage(filter_var($_COOKIE['housepc'], FILTER_SANITIZE_STRING)) == $remoteIp . 'eric') {
                    $housePc = TRUE;
                }
            }

            $this->setSession($dbh, $ssn, $r);

            $ssn->groupcodes = self::setSecurityGroups($dbh, $r['idName'], $housePc);

            $this->defaultPage = $r['Default_Page'];

            return TRUE;
        } else if ($match && $r['Status'] == 'd') { // is user disabled?
            $this->logMessage = "Account disabled, please contact your administrator. ";
        } else {
            $this->logMessage = "Bad username or password.  ";
        }

        return FALSE;
    }

    public function getDefaultPage($site = 'h')
    {
        if ($site == 'h') {
            return $this->defaultPage;
        }

        return '';
    }

    public static function setPCAccess($dbh, $pcName = null)
    {
        if (! self::checkPCAccess($dbh)) {

            if ($pcName) {
                $remoteIp = self::getRemoteIp();
                $ipRS = new W_auth_ipRS();
                $ipRS->IP_addr->setNewVal($remoteIp);
                $ipRS->Title->setNewVal($pcName);

                EditRS::insert($dbh, $ipRS);

                if (count(EditRS::select($dbh, $ipRS, array(
                    $remoteIp
                ))) > 0) {
                    return "IP-Restricted Access is set for this device.";
                } else {
                    return "Failed to set IP address!";
                }
            } else {
                return "PC Name is required";
            }
        } else {
            return "PC already Authorized";
        }
    }

    public static function revokePCAccess($dbh, $ipAddr)
    {
        if ($ipAddr) { // if $ipAddr exists
            $ipRS = new W_auth_ipRS();
            $ipRS->IP_addr->setStoredVal($ipAddr);

            // check if IP is assigned to a group
            $query = "select * from w_group_ip where IP_addr = '$ipAddr'";
            $stmt = $dbh->prepare($query);
            $stmt->execute();
            if ($stmt->rowCount() == 0) { // only revoke if no groups are assigned
                if (count(EditRS::select($dbh, $ipRS, array(
                    $ipAddr
                ))) > 0) { // If IP is found
                    if (EditRS::delete($dbh, $ipRS, array(
                        $ipRS->IP_addr
                    ))) {
                        return "IP address revoked.";
                    } else {
                        return "Failed to revoke IP address";
                    }
                } else {
                    return "Cannot revoke access, PC is not Authorized";
                }
            } else {
                return "Cannot revoke access. Remove IP address from all groups before revoking access.";
            }
        } else {
            return "IP address is required";
        }
    }

    // return true if current IP is in IP list
    // if group code is present, check if current IP is authorized for that group
    public static function checkPCAccess($dbh, $gc = false)
    {
        $remoteIp = self::getRemoteIp();
        $query = "SELECT * from w_auth_ip waip";
        if ($gc) {
            $query .= " join w_group_ip wgip on waip.IP_addr = wgip.IP_addr where wgip.Group_Code = '$gc'";
        }
        $stmt = $dbh->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $isMatch = self::ip_in_range($remoteIp, $row['IP_addr'] . "/" . $row['cidr']);
            if ($isMatch) {
                return true;
            }
        }
        return false;
    }

    /**
     * Original code from https://gist.github.com/tott/7684443
     * Check if a given ip is in a network
     *
     * @param string $ip
     *            IP to check in IPV4 format eg. 127.0.0.1
     * @param string $range
     *            IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
     * @return boolean true if the ip is in this range / false if not.
     */
    public static function ip_in_range($ip, $range)
    {
        if (strpos($range, '/') == false) {
            $range .= '/32';
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24
        list ($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }

    public function updateSecurityQuestions(\PDO $dbh, array $questions)
    {
        $ssn = Session::getInstance();
        $updateCount = 0;

        foreach ($questions as $question) {
            $answerRS = new W_userAnswersRS();
            // if question already exists, update
            if ($question['idAnswer']) {
                $answerRS->idAnswer->setStoredVal($question['idAnswer']);
                $rows = EditRS::select($dbh, $answerRS, array(
                    $answerRS->idAnswer
                ));

                if (count($rows) == 1) {
                    EditRS::loadRow($rows[0], $answerRS);

                    $answerRS->idQuestion->setNewVal($question['idQuestion']);
                    if ($question['Answer'] != "") {
                        $answerRS->Answer->setNewVal($question['Answer']);
                    }

                    $counter = EditRS::update($dbh, $answerRS, array(
                        $answerRS->idAnswer
                    ));
                    if ($counter > 0) {
                        $updateCount ++;
                    }
                }
            } else {
                $answerRS->idUser->setNewVal($ssn->uid);
                $answerRS->idQuestion->setNewVal($question['idQuestion']);
                $answerRS->Answer->setNewVal($question['Answer']);

                $idAnswer = EditRS::insert($dbh, $answerRS);
                if ($idAnswer > 0) {
                    $updateCount ++;
                }
            }
        }

        if ($updateCount > 0) {
            $this->insertUserLog($dbh, "Security Questions updated");
            return TRUE;
        }
        return FALSE;
    }

    public function updateDbPassword(\PDO $dbh, $id, $oldPw, $newPw, $uname, $resetNextLogin = 0)
    {
        $ssn = Session::getInstance();
        $priorPasswords = SysConfig::getKeyValue($dbh, 'sys_config', 'PriorPasswords');

        if ($oldPw == $newPw) {
            $this->logMessage = "The new password must be different from the old one.  ";
            return FALSE;
        }

        if(isset($ssn->sitePepper) && $ssn->sitePepper != ''){
            $newPwHash = password_hash($newPw . $ssn->sitePepper, PASSWORD_ARGON2ID);
        }else{
            $newPwHash = md5($newPw);
        }
        
        
        // check if password has already been used
        if ($this->isPasswordUsed($dbh, $newPw)) {
            $this->logMessage = "You cannot use any of the prior " . $priorPasswords . " passwords";
            return FALSE;
        }

        // Are we legit?
        $success = $this->_checkLogin($dbh, $ssn->username, $oldPw);

        if ($success) {
            $query = "update w_users set PW_Change_Date = now(), PW_Updated_By = :uname, Enc_PW = :newPw, Chg_PW = :reset where idName = :id and Status='a';";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':uname' => $ssn->username,
                ':newPw' => $newPwHash,
                ':id' => $id,
                ':reset' => $resetNextLogin
            ));

            if ($stmt->rowCount() == 1) {
                $this->insertUserLog($dbh, UserClass::PW_Changed, $uname);

                $query = "insert into w_user_passwords (idUser, Enc_PW) values(:idUser, :newPw);";
                $stmt = $dbh->prepare($query);
                $stmt->execute(array(
                    ':idUser' => $id,
                    ':newPw' => $newPwHash
                ));

                return TRUE;
            }
        }
        return FALSE;
    }

    public function isPasswordUsed(\PDO $dbh, $newPw)
    {
        $uS = Session::getInstance();

        // get prior password hashes
        $query = "select Enc_PW from w_user_passwords where idUser = " . $uS->uid . " order by Timestamp desc limit " . $uS->PriorPasswords . ";";
        $stmt = $dbh->query($query);
        $hashes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach($hashes as $hash){
            if(isset($uS->sitePepper) && $uS->sitePepper && password_verify($newPw . $uS->sitePepper, $hash)){
                return true;
            }
        }
        
        return false;
    }

    public function setPassword(\PDO $dbh, $id, $newPw)
    {
        $uS = Session::getInstance();
        if ($newPw != '' && $id != 0) {

            $newPwHash = password_hash($newPw . $uS->sitePepper, PASSWORD_ARGON2ID);
            
            $query = "update w_users set PW_Change_Date = now(), PW_Updated_By = 'install', Enc_PW = :newPw where idName = :id;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':newPw' => $newPwHash,
                ':id' => $id
            ));

            if ($stmt->rowCount() == 1) {

                $this->insertUserLog($dbh, UserClass::PW_New);
                return TRUE;
            }
        }
        return FALSE;
    }

    public static function isUserNew(\PDO $dbh, $uS)
    {
        $query = "select idAnswer, idQuestion from w_user_answers A join w_users U on A.idUser = U.idName where U.User_Name='" . $uS->username . "' limit 3;";
        $stmt = $dbh->query($query);
        if ($stmt->rowCount() != 3 && $uS->AllowPasswordRecovery) {
            return true;
        }

        return false;
    }
    
    public static function isPassExpired(\PDO $dbh, $uS)
    {
        $u = self::getUserCredentials($dbh, $uS->username);
        if (isset($u['Chg_PW']) && $u['Chg_PW']) {
            return true;
        }
        
        return false;
    }
    
    public static function setPassExpired(\PDO $dbh, array $user){
        if(isset($user['pass_rules']) && $user['pass_rules']){ //if password rules apply
            $date = false;
            //use creation date if never logged in
            if($user['PW_Change_Date'] != ''){
                $date = new DateTimeImmutable($user['PW_Change_Date']);
            }else{
                $date = new DateTimeImmutable($user['Timestamp']);
            }
            
            $passResetDays = SysConfig::getKeyValue($dbh, 'sys_config', 'passResetDays');
            
            if ($date && ($user['idName'] > 0) && $user['Status'] == 'a' && $passResetDays) {
                
                $date = $date->setTime(0, 0);
                $deactivateDate = $date->add(new DateInterval('P' . $passResetDays . 'D')); // add resetdays
                $now = new DateTime();
                $today = $now->setTime(0, 0);
                $lastChangeDays = $date->diff($today)->format('%a');
                if ($lastChangeDays >= $passResetDays) {
                    $stmt = "update w_users set `Chg_PW` = '1', `Last_Updated` = '" . $deactivateDate->format("Y-m-d H:i:s") . "' where idName = $user[idName]";
                    if ($dbh->exec($stmt) > 0) {
                        $user['Chg_PW'] = '1';
                        self::insertUserLog($dbh, UserClass::Expired, $user['User_Name'], $deactivateDate->format("Y-m-d H:i:s"));
                    }
                }
            }
        }
        return $user;
    }
    
    public static function createUserSettingsMarkup(\PDO $dbh)
    {
        $uS = Session::getInstance();

        $mkup = '<div id="dchgPw" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display:none;">';
        $passwordTitle = 'Change your Password';

        if (self::isPassExpired($dbh, $uS)){
            $mkup .= '
            <div class="ui-widget hhk-visitdialog hhk-row PassExpDesc" style="margin-bottom: 1em;">
                <div class="ui-widget-header ui-state-default ui-corner-top" style="padding: 5px;">
        			Password Expired
        		</div>
        		<div class="ui-corner-bottom hhk-tdbox ui-widget-content" style="padding: 5px;">
                    <p style="margin: 0.5em">Your password has expired, please choose a new one below</p>
                </div>
            </div>
            ';
        }

        // password markup
        $mkup .= '
            <div class="ui-widget hhk-visitdialog hhk-row" style="margin-bottom: 1em;">
        		<div class="ui-widget-header ui-state-default ui-corner-top" style="padding: 5px;">' . $passwordTitle . '</div>
        		<div class="ui-corner-bottom hhk-tdbox ui-widget-content" style="padding: 5px;">
        		
                    <table style="width: 100%"><tr>
                            <td class="tdlabel">User Name:</td><td style="background-color: white;"><span id="utxtUserName">' . $uS->username . '</span></td>
                        </tr><tr>
                            <td class="tdlabel">Enter Old Password:</td><td style="display: flex"><input style="width: 100%" id="utxtOldPw" type="password" value=""  /><button class="showPw" style="font-size: .75em; margin-left: 1em;" tabindex="-1">Show</button></td>
                        </tr><tr>
                            <td class="tdlabel">Enter New Password:</td><td style="display: flex"><input style="width: 100%" id="utxtNewPw1" type="password" value=""  /><button class="showPw" style="font-size: .75em; margin-left: 1em;" tabindex="-1">Show</button></td>
                        </tr><tr>
                            <td class="tdlabel">New Password Again:</td><td style="display: flex"><input style="width: 100%" id="utxtNewPw2" type="password" value=""  /><button class="showPw" style="font-size: .75em; margin-left: 1em;" tabindex="-1">Show</button></td>
                        </tr><tr>
                            <td colspan ="2"><span style="font-size: smaller;">Passwords must have at least 8 characters with at least 1 uppercase letter,<br> 1 lowercase letter, a number and a symbol. Do not use names or dictionary words</span></td>
                        </tr><tr>
                            <td colspan ="2" style="text-align: center;padding-top:10px;"><span id="pwChangeErrMsg" style="color:red;"></span></td>
                        </tr>
                    </table>
                </div>
            </div>';
        
        $mkup .= "</div>";
        return $mkup;
    }

    public static function getRemoteIp()
    {
        if (filter_has_var(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR')) {
            $remoteIp = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP);
        } else {
            $remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        }

        return $remoteIp;
    }

    public static function insertUserLog(\PDO $dbh, $action, $username = false, $date = false)
    {
        if (! $username) {
            $ssn = Session::getInstance();
            $username = $ssn->username;
        }

        if ($date) {
            $timestamp = "'" . $date . "'"; // add quotes to date
        } else {
            $timestamp = "now()";
        }

        $ssn = Session::getInstance();
        $remoteIp = self::getRemoteIp();
        $dbh->exec("insert into w_user_log (Username, Access_Date, IP, `Action`) values ('" . $username . "', $timestamp , '$remoteIp', '$action')");
    }

    public static function getUserCredentials(\PDO $dbh, $username)
    {
        if (! is_string($username) || $username == '') {
            return NULL;
        }

        $uname = str_ireplace("'", "", $username);

        $stmt = $dbh->query("SELECT u.*, a.Role_Id as Role_Id
FROM w_users u join w_auth a on u.idName = a.idName
join `name` n on n.idName = u.idName
WHERE n.idName is not null and u.Status IN ('a', 'd') and u.User_Name = '$uname'");

        if ($stmt->rowCount() === 1) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows[0];
        }

        return NULL;
    }

    public static function disableInactiveUser(\PDO $dbh, array $user)
    {
        if(isset($user['pass_rules']) && $user['pass_rules']){ //if password rules apply
            
            $date = false;
            //use creation date if never logged in
            if($user['Last_Login'] != ''){
                $date = new DateTimeImmutable($user['Last_Login']);
            }else{
                $date = new DateTimeImmutable($user['Timestamp']);
            }
            
            $userInactiveDays = SysConfig::getKeyValue($dbh, 'sys_config', 'userInactiveDays');
            
            if ($date && $user['idName'] > 0 && $user['Status'] == 'a' && $userInactiveDays) {
                
                $lastUpdated = new DateTimeImmutable($user['Last_Updated']);
                $lastUpdated = $lastUpdated->setTime(0, 0);
                $date = $date->setTime(0, 0);
                $deactivateDate = $date->add(new DateInterval('P' . $userInactiveDays . 'D')); // add inactivedays
                $now = new DateTime();
                $today = $now->setTime(0, 0);
                $lastLoginDays = $date->diff($today)->format('%a');
                $lastUpdatedDays = $lastUpdated->diff($today)->format('%a');
                if ($lastLoginDays >= $userInactiveDays && $lastUpdatedDays >= $userInactiveDays) {
                    $stmt = "update w_users set `Status` = 'd', `Last_Updated` = '" . $deactivateDate->format("Y-m-d H:i:s") . "' where idName = $user[idName]";
                    if ($dbh->exec($stmt) > 0) {
                        $user['Status'] = 'd';
                        self::insertUserLog($dbh, UserClass::Lockout, $user['User_Name'], $deactivateDate->format("Y-m-d H:i:s"));
                    }
                }
            }
        }
        return $user;
    }

    protected static function setSecurityGroups(\PDO $dbh, $idName, $housePc = FALSE)
    {
        $id = intval($idName, 10);

        $grpArray = array();
        $query = "SELECT s.Group_Code, case when w.IP_Restricted = 1 then '1' else '0' end as `IP_Restricted` FROM id_securitygroup s join w_groups w on s.Group_Code = w.Group_Code WHERE s.idName = $id";
        $stmt = $dbh->query($query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($r["Group_Code"] != "" && ($r['IP_Restricted'] == "0" || self::checkPCAccess($dbh, $r["Group_Code"]))) {
                $grpArray[$r["Group_Code"]] = $r["Group_Code"];
            }
        }

        return $grpArray;
    }

    public function setSession(\PDO $dbh, Session $ssn, $r, $init = true)
    {
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

            $remoteIp = self::getRemoteIp();

            $query = "UPDATE w_users SET Session = '$sessionId', Ip = '$remoteIp', Last_Login=now() WHERE User_Name = '" . $ssn->username . "'";
            $dbh->exec($query);

            // Log access
            $this->insertUserLog($dbh, UserClass::Login);
        }
    }

    public static function _logout()
    {
        $uS = Session::getInstance();
        $uS->destroy();
    }
    
    private function incrementTries() {
        $ssn = Session::getInstance();
        if (isset($ssn->Challtries) === FALSE) {
            $ssn->Challtries = 0;
        }
        $ssn->Challtries++;
        return $ssn->Challtries;
    }
    
    private function testTries($max = 3) {
        $ssn = Session::getInstance();
        if (isset($ssn->Challtries) && $ssn->Challtries > $max) {
            return FALSE;
        }
        return TRUE;
    }
    
    private function resetTries(){
        $ssn = Session::getInstance();
        if (isset($ssn->Challtries)){
            unset($ssn->Challtries);
        }
    }
    
    //Strong Password generator from https://gist.github.com/tylerhall/521810
    // Generates a strong password of N length containing at least one lower case letter,
    // one uppercase letter, one digit, and one special character. The remaining characters
    // in the password are chosen at random from those four sets.
    //
    // The available characters in each set are user friendly - there are no ambiguous
    // characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
    // makes it much easier for users to manually type or speak their passwords.
    //
    // Note: the $add_dashes option will increase the length of the password by
    // floor(sqrt(N)) characters.
    
    public function generateStrongPassword($length = 9, $add_dashes = false, $available_sets = 'luds')
    {
        $sets = array();
        if(strpos($available_sets, 'l') !== false)
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
            if(strpos($available_sets, 'u') !== false)
                $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
                if(strpos($available_sets, 'd') !== false)
                    $sets[] = '23456789';
                    if(strpos($available_sets, 's') !== false)
                        $sets[] = '!@#$%&*?';
                        
                        $all = '';
                        $password = '';
                        foreach($sets as $set)
                        {
                            $password .= $set[array_rand(str_split($set))];
                            $all .= $set;
                        }
                        
                        $all = str_split($all);
                        for($i = 0; $i < $length - count($sets); $i++)
                            $password .= $all[array_rand($all)];
                            
                            $password = str_shuffle($password);
                            
                            if(!$add_dashes)
                                return $password;
                                
                                $dash_len = floor(sqrt($length));
                                $dash_str = '';
                                while(strlen($password) > $dash_len)
                                {
                                    $dash_str .= substr($password, 0, $dash_len) . '-';
                                    $password = substr($password, $dash_len);
                                }
                                $dash_str .= $password;
                                return $dash_str;
    }
}
?>