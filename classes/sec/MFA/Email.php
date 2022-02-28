<?php

namespace HHK\sec\MFA;

use HHK\sec\UserClass;

/**
 * Email.php
 *
 * Faciltates email-based multifactor authentication
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Email extends AbstractMultiFactorAuth
{

    protected $codeCreatedAt;

    /**
     * @param array $userAr
     */
    public function __construct(array $userAr){
        $this->secret = $userAr['emailSecret'];
        $this->codeCreatedAt = $userAr = $userAr['emailCode_created_at'];
        $this->username = $userAr['User_Name'];
    }

    public function verifyCode($secret, $code)
    {}

    public function getCode($secret, $timeSlice)
    {}

    public function saveSecret(\PDO $dbh)
    {
        if($this->username && $this->secret != ''){
            $query = "update w_users set emailSecret = :secret, Last_Updated = now() where User_Name = :username and Status='a';";
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