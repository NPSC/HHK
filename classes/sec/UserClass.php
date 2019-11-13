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
    protected $defaultPage = '';

    const PW_Changed = 'PC';
    const PW_New = 'PS';
    const Login = 'L';

    public function _checkLogin(\PDO $dbh, $username, $password, $remember = FALSE) {
         // instantiate a ChallengeGenerator object
        $chlgen = new ChallengeGenerator(FALSE);

        // get challenge variable
        $challenge = $chlgen->getChallengeVar();

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

        } else {
            $this->logMessage = "Bad username or password.  ";
        }

        return FALSE;
    }

    public function getDefaultPage($site = 'h') {
        if ($site == 'h') {
            return $this->defaultPage;
        }

        return '';
    }

    public static function setPCAccess($dbh, $pcName = null) {
	    if(!self::checkPCAccess($dbh)){
		    
			if($pcName){
				$remoteIp = self::getRemoteIp();
				$ipRS = new W_auth_ipRS();
			    $ipRS->IP_addr->setNewVal($remoteIp);
			    $ipRS->Title->setNewVal($pcName);
			    
				$id = EditRS::insert($dbh, $ipRS);
			
			    if (count(EditRS::select($dbh, $ipRS, array($remoteIp))) > 0) {
			        return "IP-Restricted Access is set for this device.";
			    } else {
			        return "Failed to set IP address!";
			    }
			}else{
				return "PC Name is required";
			}
		}else{
			return "PC already Authorized";
		}
    }
    
    public static function revokePCAccess($dbh, $ipAddr) {
	    if($ipAddr){ //if $ipAddr exists
		    $ipRS = new W_auth_ipRS();
		    $ipRS->IP_addr->setStoredVal($ipAddr);
		    
		    //check if IP is assigned to a group
		    $query = "select * from w_group_ip where IP_addr = '$ipAddr'";
		    $stmt = $dbh->prepare($query);
		    $stmt->execute();
			if($stmt->rowCount() == 0){ //only revoke if no groups are assigned
				if(count(EditRS::select($dbh, $ipRS, array($ipAddr))) > 0){ //If IP is found
					if(EditRS::delete($dbh, $ipRS, array($ipRS->IP_addr))){
				        return "IP address revoked.";
				    } else {
				        return "Failed to revoke IP address";
				    }
				}else{
					return "Cannot revoke access, PC is not Authorized";
				}
			}else{
				return "Cannot revoke access. Remove IP address from all groups before revoking access.";
			}
		}else{
			return "IP address is required";
		}
    }
    
    //return true if current IP is in IP list
    //if group code is present, check if current IP is authorized for that group
    public static function checkPCAccess($dbh, $gc = false){
	    $remoteIp = self::getRemoteIp();
		$query = "SELECT * from w_auth_ip waip";
		if($gc){
			$query .= " join w_group_ip wgip on waip.IP_addr = wgip.IP_addr where wgip.Group_Code = $gc";
		}
		$stmt = $dbh->prepare($query);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		
		foreach($rows as $row){
			$isMatch = self::ip_in_range( $remoteIp, $row['IP_addr'] . "/" . $row['cidr']);
			if($isMatch){
				return true;
			}
		}
		return false;
    }
    
    /**
	 * Original code from https://gist.github.com/tott/7684443
	 * Check if a given ip is in a network
	 * @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
	 * @param  string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
	 * @return boolean true if the ip is in this range / false if not.
	 */
	protected function ip_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) == false ) {
			$range .= '/32';
		}
		// $range is in IP/CIDR format eg 127.0.0.1/24
		list( $range, $netmask ) = explode( '/', $range, 2 );
		$range_decimal = ip2long( $range );
		$ip_decimal = ip2long( $ip );
		$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal = ~ $wildcard_decimal;
		return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
	}

    public function updateDbPassword(\PDO $dbh, $id, $oldPw, $newPw) {

        $ssn = Session::getInstance();

        if ($oldPw == $newPw) {
            $this->logMessage = "The new password must be different from the old one.  ";
            return FALSE;
        }

        // Are we legit?
        $success = $this->_checkLogin($dbh, $ssn->username, $oldPw);

        if ($success) {
            $query = "update w_users set PW_Change_Date = now(), PW_Updated_By = :uname, Enc_PW = :newPw where idName = :id and Status='a';";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':uname'=>$ssn->username, ':newPw'=>$newPw, ':id'=>$id));

            if ($stmt->rowCount() == 1) {
                $this->insertUserLog($dbh, UserClass::PW_Changed);

                return TRUE;
            }
        }
        return FALSE;
    }

    public function setPassword(\PDO $dbh, $id, $newPw) {

        if ($newPw != '' && $id != 0) {

            $query = "update w_users set PW_Change_Date = now(), PW_Updated_By = 'install', Enc_PW = :newPw where idName = :id;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':newPw'=>$newPw, ':id'=>$id));

            if ($stmt->rowCount() == 1) {

                $this->insertUserLog($dbh, UserClass::PW_New);
                return TRUE;
            }
        }
        return FALSE;
    }

    public static function getRemoteIp() {

        if (filter_has_var(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR')) {
            $remoteIp = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP);
        } else {
            $remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        }

        return $remoteIp;
    }

    protected function insertUserLog(\PDO $dbh, $action) {

        $ssn = Session::getInstance();
        $remoteIp = self::getRemoteIp();
        $dbh->exec("insert into w_user_log (Username, Access_Date, IP, `Action`) values ('" . $ssn->username . "', now(), '$remoteIp', '$action')");
    }

    public static function getUserCredentials(\PDO $dbh, $username) {

        if (!is_string($username) || $username == '') {
            return NULL;
        }

        $uname = str_ireplace("'", "", $username);

        $stmt = $dbh->query("SELECT u.*, a.Role_Id as Role_Id
FROM w_users u join w_auth a on u.idName = a.idName
join `name` n on n.idName = u.idName
WHERE n.idName is not null and u.Status='a' and u.User_Name = '$uname'");

        if ($stmt->rowCount() === 1) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows[0];
        }

        return NULL;
    }

    protected static function setSecurityGroups(\PDO $dbh, $idName, $housePc = FALSE) {

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

    public function setSession(\PDO $dbh, Session $ssn, $r, $init = true) {

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

    public static function _logout() {
        $uS = Session::getInstance();
        $uS->destroy();
    }

}
