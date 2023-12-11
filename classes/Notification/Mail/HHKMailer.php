<?php

namespace HHK\Notification\Mail;

use HHK\sec\Crypto;
use HHK\sec\Session;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Set up PHPMailer with basic settings
 */
class HHKMailer extends PHPMailer {
    /**
     * Construct PHPMailer
     */
    public function __construct(){
        parent::__construct(true);

        $uS = Session::getInstance();

        $this->CharSet = "utf-8";
        $this->Encoding = "base64";

        if($uS->DKIMdomain && @file_get_contents($uS->keyPath . '/dkim/dkimPrivateKey.pem')){
            $this->DKIM_domain = $uS->DKIMdomain;
            $this->DKIM_private = $uS->keyPath . '/dkim/dkimPrivateKey.pem';
            $this->DKIM_selector = "hhk";
            $this->DKIM_identity = $this->From;
        }

        switch (strtolower($uS->EmailType)) {

            case 'smtp':

                $this->isSMTP();

                $this->Host = $uS->SMTP_Host;
                $this->SMTPAuth = $uS->SMTP_Auth_Required;
                $this->Username = $uS->SMTP_Username;

                if ($uS->SMTP_Password != '') {
                    $this->Password = Crypto::decryptMessage($uS->SMTP_Password);
                }

                if ($uS->SMTP_Port != '') {
                    $this->Port = $uS->SMTP_Port;
                }

                if ($uS->SMTP_Secure != '') {
                    $this->SMTPSecure = $uS->SMTP_Secure;
                }

                $this->SMTPDebug = $uS->SMTP_Debug;

                break;

            case 'mail':
                $this->isMail();
                break;
        }

    }
}

?>