<?php

namespace HHK\sec\MFA;

use HHK\HTMLControls\HTMLContainer;
use HHK\sec\UserClass;
use HHK\sec\Session;
use HHK\Member\IndivMember;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\MemBasis;
use HHK\Member\Address\Emails;

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

    protected $discrepancy = 10; //code validity time in 30sec increments (1 = 30sec, 10 = 5min)
    protected $emailAddr;
    protected $idName;

    /**
     * @param array $userAr
     */
    public function __construct(array $userAr){
        $this->secret = $userAr['emailSecret'];
        $this->username = $userAr['User_Name'];
        $this->idName = $userAr['idName'];
    }

    public function sendCode(\PDO $dbh){
        $uS = Session::getInstance();
        $this->setEmailAddress($dbh);

        $return = array();

        if($this->emailAddr){
            $mail = prepareEmail();
            $mail->From = $uS->FromAddress;
            $mail->FromName = $uS->siteName;
            $mail->addAddress(filter_var($this->emailAddr, FILTER_SANITIZE_EMAIL));

            $mail->isHTML(true);

            $mail->Subject = "Two Factor Verification Code";
            $mail->msgHTML('
Hello,<br>
Your one time 2 factor verification code for ' . $uS->siteName . ' is <strong>' . $this->getCode() . '</strong><br><br>
This code is good for 5 minutes. Don\'t share this code with anyone.<br><br>
Thank You,<br>
Hospitality Housekeeper
            ');

            $mail->send();

        }else{
            throw new \ErrorException("User's preferred email address cannot be blank.");
        }

        return $return;
    }

    public function setEmailAddress(\PDO $dbh){
        $query = "select n.`idName`, e.`Email` from `name` n
join `name_email` e on n.idName = e.idName and n.Preferred_Email = e.Purpose
join `w_users` u on n.idName = u.idName
where u.User_Name = :uname";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':uname'=>$this->username));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if(count($rows) === 1){
            $this->emailAddr = $rows[0]['Email'];
            return true;
        }else{
            return false;
        }


    }

    public function saveSecret(\PDO $dbh) : bool
    {
        if($this->username && $this->secret != ''){
            $query = "update w_users set emailSecret = :secret, Last_Updated = now() where User_Name = :username and Status='a';";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':secret' => $this->secret,
                ':username' => $this->username
            ));

            if ($stmt->rowCount() == 1) {
                UserClass::insertUserLog($dbh, UserClass::OTPSecChanged, $this->username);
            }
            return true;
        }else{
            return false;
        }
    }

    public function getEditMarkup(\PDO $dbh){
        $uS = Session::getInstance();
        $this->setEmailAddress($dbh);
        $mkup = '';

        if($this->secret !== ''){ //if configured
            $mkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('p', "Two factor authentication codes will be sent to " . $this->emailAddr)
            , array('class'=>'my-3 center'));
        }else{
            $userMem = new IndivMember($dbh, MemBasis::Indivual, $this->idName);
            $emails = new Emails($dbh, $userMem, $uS->nameLookups[GLTableNames::EmailPurpose]);

            $mkup = HTMLContainer::generateMarkup('div',
                    $emails->createMarkup() .

                HTMLContainer::generateMarkup('button', "Enable Email 2 Factor Verification", array('id'=>'genEmailSecret'))
                , array('class'=>'my-3', 'style'=>'text-align:center;'));
        }

        $mkup .= '
                    <form class="otpForm" style="display: none; text-align: center;">
                        <label for"otp" style="display: block; margin-bottom: 1em">Enter Verification Code</label>
                        <input type="text" name="otp" size="10">
                        <input type="hidden" name="secret">
                        <input type="hidden" name="cmd" value="save2fa">
                        <input type="hidden" name="method" value="email">
                        <input type="submit" style="margin-left: 1em;">
                    </form>';

        return $mkup;
    }

}

?>