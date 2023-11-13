<?php

namespace HHK\sec\MFA;

use HHK\sec\UserClass;
use chillerlan\QRCode\QRCode;
use HHK\HTMLControls\HTMLContainer;

/**
 * PHP Class for handling Google Authenticator 2-factor authentication.
 *
 * Forked and modified for HHK
 *
 * @author Michael Kliewe
 * @author Will Ireland
 * @copyright 2012 Michael Kliewe
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 *
 * @link http://www.phpgangsta.de/
 */
class GoogleAuthenticator extends AbstractMultiFactorAuth
{

    protected $discrepancy = 1; //code validity time in 30sec increments (1 = 30sec, 10 = 5min)

    /**
     * @param array $userAr
     */
    public function __construct(array $userAr){
        $this->secret = $userAr['totpSecret'];
        $this->username = $userAr['User_Name'];
    }

    public function saveSecret(\PDO $dbh): bool {
        if($this->username && $this->secret != ''){
            $query = "update w_users set totpSecret = :secret, Last_Updated = now() where User_Name = :username and Status='a';";
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

    public function disable(\PDO $dbh): bool {
        if($this->username && $this->secret != ''){
            $query = "update w_users set totpSecret = '', Last_Updated = now() where User_Name = :username and Status='a';";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':username' => $this->username
            ));

            if ($stmt->rowCount() == 1) {
                UserClass::insertUserLog($dbh, UserClass::OTPSecChanged, $this->username);
            }
            $this->secret = '';
            return true;
        }else{
            return false;
        }
    }

    /**
     * Get QR-Code URL for image, from google charts.
     *
     * @param string $title
     *
     * @return string
     */
    public function getQRCodeImage($title = null)
    {

        $data = 'otpauth://totp/' . $this->username . '?secret=' . $this->secret . '';
        if (isset($title)) {
            $data .= '&issuer=' . urlencode($title);
        }

        return (new QRCode)->render($data);
    }

    public function getEditMarkup(\PDO $dbh) : string {
        $mkup = '';

        if($this->secret != ''){ //if configured
            $mkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('button', "Disable", array('class'=>'disableMFA', 'data-method'=>'authenticator')) .
                HTMLContainer::generateMarkup('button', "Show QR Code", array("id"=>'getTOTPSecret'))
            , array('class'=>'my-3 hhk-flex', 'style'=>'justify-content: space-around;'));

        }else{

            $mkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup("p", 'Use an Authenticator app (Google Authenticator, <a href="//authenticator.cc" target="_blank">Authenticator browser extension</a>, etc) to generate 2 step verification codes', array('class'=>"mb-3")) .
                HTMLContainer::generateMarkup('button', "Enable Authenticator 2 Factor Verification", array('id'=>'genTOTPSecret'))
                , array('class'=>'my-3', 'style'=>'text-align: center;'));

        }

        $mkup .= '<div class="hhk-flex" style="justify-content: space-between; align-items: center;">
                    <div id="qrcode"></div>
                    <div id="showqrhelp" style="display: none;">Use this QR code to add the existing Authenticator configuration to a new device</div>
                    <form class="otpForm" style="display: none; text-align: center;">
                        <label for="otp" class="d-block mb-3">Enter Verification Code</label>
                        <input type="text" name="otp" size="10" class="mb-3">
                        <input type="hidden" name="secret">
                        <input type="hidden" name="cmd" value="save2fa">
                        <input type="hidden" name="method" value="authenticator">
                        <input type="submit">
                    </form>
                </div>
                    <div id="backupCodeDiv" style="display: none;">
                        <h3 style="text-align: center;">Backup Codes</h3>
                        <p class="mx-3">If you are ever unable to access your Authenticator app, you can use one of the following one time codes to log in. Each code can only be used once.</p>
                        <p class="mx-3" style="text-align: center;" id="backupCodes"></p>
                    </div>';

        return $mkup;
    }

}
