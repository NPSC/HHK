<?php

namespace HHK\sec\MFA;

use HHK\sec\UserClass;

/**
 * Backup.php
 *
 * Facilitates Multifactor authentication backup codes
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Backup extends AbstractMultiFactorAuth {

    /**
     * @param array $userAr
     */
    public function __construct(array $userAr){
        $this->secret = $userAr['backupSecret'];
        $this->username = $userAr['User_Name'];
    }

    public function verifyCode(string $code): bool
    {
        if(strlen($code) == 6){
            $availableCodes = $this->getCode();
            return in_array($code, $availableCodes);
        }else{
            return false;
        }
    }

    public function getCode($timeSlice = null)
    {
        $codeStr = substr(hash_hmac('sha256', $this->username, $this->secret), 0, 60);
        $codesAr = str_split($codeStr, 6);
        return $codesAr;
    }

    public function saveSecret(\PDO $dbh): bool {
        if($this->username && $this->secret != ''){
            $query = "update w_users set backupSecret = :secret, Last_Updated = now() where User_Name = :username and Status='a';";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':secret' => $this->secret,
                ':username' => $this->username
            ));

            if ($stmt->rowCount() == 1) {
                $this->insertUserLog($dbh, UserClass::OTPSecChanged, $this->username);
            }
            return true;
        }else{
            return false;
        }
    }


}

?>