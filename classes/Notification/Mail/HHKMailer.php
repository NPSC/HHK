<?php

namespace HHK\Notification\Mail;

use HHK\sec\Session;
use HHK\SysConst\NotificationStatus;
use HHK\TableLog\NotificationLog;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Set up PHPMailer with basic settings
 */
class HHKMailer extends PHPMailer {

    protected \PDO $dbh;
    /**
     * Construct PHPMailer
     */
    public function __construct(\PDO $dbh){
        parent::__construct(true);

        $uS = Session::getInstance();

        $this->dbh = $dbh;

        $this->CharSet = "utf-8";
        $this->Encoding = "base64";
        $this->addCustomHeader('X-HHK-Source', $uS->databaseName);

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
                    $this->Password = decryptMessage($uS->SMTP_Password);
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

    public function getToString(){
        $to = [];
        foreach($this->to as $toArr){
            $to[] = $toArr[0];
        }
        return $to;
    }

    public function getCCString(){
        $to = [];
        foreach($this->cc as $toArr){
            $to[] = $toArr[0];
        }
        return $to;
    }

    public function getBCCString(){
        $to = [];
        foreach($this->bcc as $toArr){
            $to[] = $toArr[0];
        }
        return $to;
    }

    /**
     * Create a message and send it.
     * Uses the sending method specified by $Mailer.
     *
     * @throws Exception
     *
     * @return bool false on error - See the ErrorInfo property for details of the error
     */
    public function send(){

        $uS = Session::getInstance();

        $stmt = $this->dbh->prepare("INSERT INTO `Notifications_Log` (`To`,`From`, `Type`,`Sub_Type`, `Subject`, `Content`, `Status`, `Updated_By`) VALUES (:to, :from, :type, :subType, :subject, :content, :status, :updatedBy)");
        $stmt->execute([
            ":to"=> implode(',', array_keys($this->all_recipients)),
            ':from'=> $this->From,
            ':type'=>"Email",
            ':subType'=>"",
            ':subject'=>$this->Subject,
            ':content'=>$this->Body,
            ':status'=>NotificationStatus::Queued,
            ':updatedBy'=>$uS->username
        ]);

        $messageId = $this->dbh->lastInsertId();

        $this->addCustomHeader("X-HHK-Mail-Id", $messageId);

        $logDetails = [
            "Subject" => $this->Subject,
            "CC" => implode(', ', $this->getCCString()),
            "BCC" => implode(', ', $this->getBCCString())
        ];

        try{
            if(parent::send()){
                $logDetails["messageId"] = $this->getLastMessageID();
                NotificationLog::logEmail($this->dbh, $uS->username, implode(', ', $this->getToString()), $this->From, "Email submitted for delivery", $logDetails);
                return true;
            }else{
                $logDetails["error"] = $this->ErrorInfo;
                NotificationLog::logEmail($this->dbh, $uS->username, implode(', ',$this->getToString()), $this->From, "Email failed to send", $logDetails);
                return false;
            }
            
        }catch(Exception $e){

            $logDetails["error"] = $this->ErrorInfo;
            NotificationLog::logEmail($this->dbh, $uS->username, implode(', ', $this->getToString()), $this->From, "Email failed to send", $logDetails);
            throw $e;
        }
    }

}