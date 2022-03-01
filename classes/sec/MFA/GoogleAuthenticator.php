<?php

namespace HHK\sec\MFA;

use HHK\sec\Session;
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

    public function getEditMarkup(){
        $uS = Session::getInstance();

        $mkup = '';

        if($this->secret != ''){ //if configured
            $mkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('button', "Show QR Code", array("id"=>'getTOTPSecret')) .
                HTMLContainer::generateMarkup('button', "Generate new QR Code", array('id'=>'genTOTPSecret'))
            , array('class'=>'my-3', 'style'=>'text-align:center'));

        }else{

            $mkup = '<div id="TwoFactorHelp" style="margin: 0.5em;">
            <h3>How it works</h3>
            <div>
            <p>Once set up, you will be asked for a temporary code after entering your password when logging in. This temporary code can be found in the Authenticator browser extension configured during set up. These codes change every 30 seconds, so you\'ll need a new one each time you login.</p>
                            <p><strong>Follow these steps to configure Two Step Verification</strong></p>
                            <ol>
                                <li>Install the Authenticator browser extension<br><a href="https://authenticator.cc/" target="_blank" class="button">Download here</a></li>
                                <li>Click "Enable Two Step Verification" below</li>
                                <li>Click the Authenticator icon <img src="' . $uS->resourceURL . '/images/authenticator.png"> at the top right corner of your browser</li>
                                <li>Click the Scan QR Code icon <img src="' . $uS->resourceURL . '/images/authenticator-scan-qr.png"></li>
                                <li>Click and drag from the upper left to the lower right of the QR code generated in Step 2 to select it</li>
                                <li>If you see a message that says "(user) has been added.", then you have successfully configured the Authenticator extension</li>
                                <li>Click the code shown in the Authenticator extension to copy it</li>
                                <li>Click inside the text box below the QR code and press Ctrl-V to paste the code.</li>
                                <li>Click "Submit Code"</li>
                                <li>Two Step Verification is now enabled</li>
                            </ol>
                        </div>
                    </div>

            ';

        }

        $mkup .= HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('button', "Enable Authenticator 2 Factor Verification", array('id'=>'genTOTPSecret'))
            , array('class'=>'my-3', 'style'=>'text-align: center;'));

        $mkup .= '
                    <div id="qrcode" style="margin: 1em 0; text-align:center;"></div>
                    <form class="otpForm" style="display: none;">
                        <label for"otp" style="display: block; margin-bottom: 1em">Enter Verification Code</label>
                        <input type="text" name="otp" size="10">
                        <input type="hidden" name="secret">
                        <input type="hidden" name="cmd" value="save2fa">
                        <input type="submit" style="margin-left: 1em;">
                    </form>
                    <div id="backupCodeDiv" style="display: none;">
                        <h3>Backup Codes</h3>
                        <p class="mx-3">If you are ever unable to access your Authenticator app, you can use one of the following one time codes to log in. Each code can only be used once.</p>
                        <p class="mx-3" id="backupCodes"></p>
                    </div>';

        return $mkup;
    }

}
