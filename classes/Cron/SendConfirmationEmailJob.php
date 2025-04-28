<?php

namespace HHK\Cron;

use HHK\House\Reservation\Reservation_1;
use HHK\House\TemplateForm\ConfirmationForm;
use HHK\Member\Role\Guest;
use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\Notification\Mail\HHKMailer;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use HHK\sec\WebInit;
use PDO;
use HHK\House\TemplateForm\SurveyForm;
use HHK\Exception\RuntimeException;

/**
 * SendConfirmationEmailJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Send email confirmation for reservations
 *
 * @author Will Ireland
 */

class SendConfirmationEmailJob extends AbstractJob implements JobInterface{

    public array $paramTemplate = [
        "before"=>[
            "label"=>"Send Before",
            "type"=>"select",
            "values"=>[
                "checkin"=>["checkin","Expected Check in"],
            ],
            "defaultVal"=>"checkin",
            "required"=>true
        ],
        "solicitBuffer"=>[
            "label"=>"SolicitBuffer (days)",
            "type"=>"number",
            "defaultVal" =>"",
            "min"=>1,
            "required"=>true
        ],
        "ResvStatus"=>[
            "label"=>"Reservation Status",
            "type"=>"select",
            "values"=>[],
            "required"=>true
        ],
        "EmailTemplate"=>[
            "label"=>"EmailTemplate",
            "type"=>"select",
            "values"=>[],
            "required"=>true
        ],
    ];

    public function __construct(PDO $dbh, int $idJob, array $params=[], bool $dryRun=false){
        $uS = Session::getInstance();
        $this->paramTemplate["solicitBuffer"]["defaultVal"] = $uS->SolicitBuffer;
        $this->paramTemplate["EmailTemplate"]["values"] = $this->getConfirmationDocList($dbh);
        $this->paramTemplate["ResvStatus"]["values"] = $this->getResvStatusList($dbh);

        parent::__construct($dbh, $idJob, $params, $dryRun);
    }

    public function tasks():void {

        $sendEmail = ($this->dryRun ? FALSE : TRUE);

        $labels = Labels::getLabels();

        $uS = Session::getInstance();
        
        SysConfig::getCategory($this->dbh, $uS, ["h", "a", "d", "es", "f", "fg", "pr", "v", "ga"], webInit::SYS_CONFIG);
        
        WebInit::loadNameLookups($this->dbh, $uS);

        $siteName = SysConfig::getKeyValue($this->dbh, 'sys_config', 'siteName');
        $from = SysConfig::getKeyValue($this->dbh, 'sys_config', 'NoReplyAddr');      // Email address message will show as coming from.
        $maxAutoEmail = SysConfig::getKeyValue($this->dbh, 'sys_config', 'MaxAutoEmail');

        if($this->params["EmailTemplate"] > 0){
            $sForm = new ConfirmationForm($this->dbh, $this->params['EmailTemplate']);
        }else{
            throw new RuntimeException("Cannot find Confirmation document");
        }

        if($sForm->getSubjectLine() != ""){
            $subjectLine = $sForm->getSubjectLine();
        }else if($labels->getString('referral', 'Res_Confirmation_Subject', '') != ""){
            $subjectLine = $labels->getString('referral', 'Res_Confirmation_Subject', '');
        }else{
            throw new RuntimeException("Subject line is missing.  Go to Resource Builder -> Form Upload -> Confirmation -> Email Subject.");
        }

        if ($from == '') {
            throw new RuntimeException("From/Reply To address is missing.  Go to System Configuration, House, NoReply.");
        }

        $buffer = (isset($this->params["solicitBuffer"]) && $this->params["solicitBuffer"] > 0 ? $this->params["solicitBuffer"] : SysConfig::getKeyValue($this->dbh, 'sys_config', 'SolicitBuffer'));


        if (strtolower($buffer) === 'off') {
            throw new RuntimeException('Auto Email is off.  Go to System Configuration, Solicit Buffer.');
        }

        $delayDays = intval($buffer, 10);

        if ($delayDays <1) {
            throw new RuntimeException("Delay days not set properly.  Go to System Configuration, SolicitBuffer.");
        }

        $resvStatus = (isset($this->params["ResvStatus"]) && $this->params["ResvStatus"] != "" ? $this->params["ResvStatus"] : SysConfig::getKeyValue($this->dbh, 'sys_config', 'SolicitBuffer'));

        // Load guests

        $paramList[":delayDays"] = $delayDays;
        $paramList[":resvStatus"] = $resvStatus;
        //post checkout
            $stmt = $this->dbh->prepare("SELECT
                n.Name_First,
                n.Name_Last,
                n.Name_Suffix,
                n.Name_Prefix,
                ne.Email,
                r.idReservation,
                r.idGuest,
            	r.Expected_Arrival,
                r.Expected_Departure
            FROM
                reservation r
                JOIN
                `name` n ON r.idGuest = n.idName
            		AND n.Member_Status != 'd'
                    AND n.Exclude_Email = 0
                    JOIN
                `name_email` ne ON n.idName = ne.idName
                    AND n.Preferred_Email = ne.Purpose
            WHERE
                n.Member_Status != 'd'
                    AND r.`Status` = :resvStatus
                    AND DATE(r.Expected_Arrival) < DATE(r.Expected_Departure) and
                    DateDiff(DATE(r.Expected_Arrival), DATE(NOW())) = :delayDays
            GROUP BY r.idReservation;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        $stmt->execute($paramList);
        $numRecipients = $stmt->rowCount();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($numRecipients > $maxAutoEmail) {
            // to many recipients.
            throw new RuntimeException("The number of email recipients, " . $stmt->rowCount() . " is higher than the maximum number allowed, $maxAutoEmail. See System Configuration, email_server -> MaxAutoEmail");
        }

        $mail = new HHKMailer($this->dbh);

        $mail->From = $from;
        $mail->addReplyTo($from);
        $mail->FromName = htmlspecialchars_decode($siteName, ENT_QUOTES);

        $mail->isHTML(true);
        $mail->Subject = htmlspecialchars_decode($subjectLine, ENT_QUOTES);

        $badAddresses = 0;
        $ArrivalDT = new \DateTime();
        $ArrivalDT->add(new \DateInterval('P' . $delayDays . 'D'));

        foreach ($recipients as $r) {

            if (isset($r['Email']) && $r['Email'] != '') {
                // Verify Email Address
                $emailAddr = filter_var($r['Email'], FILTER_VALIDATE_EMAIL);

                if ($emailAddr === FALSE || $emailAddr == '') {
                    $badAddresses++;
                    continue;
                }
            } else {
                $badAddresses++;
                continue;
            }

            $resv = Reservation_1::instantiateFromIdReserv($this->dbh, $r["idReservation"]);

            $idGuest = $resv->getIdGuest();
        
            $guest = new Guest($this->dbh, '', $idGuest);

            $form = $sForm->createForm($sForm->makeReplacements($this->dbh, $resv, $guest, 0, ""));

            if ($sendEmail) {

                $mail->clearAddresses();
                $mail->addAddress($emailAddr);
                $mail->msgHTML($form);

                if ($mail->send() === FALSE) {
                    echo $mail->ErrorInfo . '<br/>';
                    $this->logMsg .= "Email Address: " . $r['Email'] . " - Email send error: " . $mail->ErrorInfo . '<br>';
                }

                $this->logMsg .= "Email Address: " . $r['Email'] . ',  Reservation Id: ' . $r['idReservation'] . ', PrimaryGuest Id: ' . $r['idGuest'] . "<br>";

                //build note text
                $noteText = ($sForm->getDocTitle() != '' ? $sForm->getDocTitle() . ' ' : '') . 'Confirmation Email';

                try {
                    $arrive = (new \DateTime($resv->getArrival()))->format("M d, Y");
                    $depart = (new \DateTime($resv->getDeparture()))->format("M d, Y");

                    $noteText .= " for " . $arrive . " to " . $depart;
                }catch(\Exception $e){

                }
                
                $noteText .= ' sent to ' . $r['Email'] .  " with subject: " . htmlspecialchars_decode($subjectLine, ENT_QUOTES);

                //Save note
                LinkNote::save($this->dbh, $noteText, $r['idReservation'], Note::ResvLink, '', $uS->username, $uS->ConcatVisitNotes);

            } else {
                $this->logMsg .= "(Email Address: " . $r['Email'] . ',  Reservation Id: ' . $r['idReservation'] . ', PrimaryGuest Id: ' . $r['idGuest'] . ")<br/>";
            }

        }

        $copyEmail = filter_var(SysConfig::getKeyValue($this->dbh, 'sys_config', 'Auto_Email_Address'), FILTER_VALIDATE_EMAIL);

        if ($sendEmail) {
            if($copyEmail && $copyEmail != ''){
                $mail->clearAddresses();
                $mail->addAddress($copyEmail);
                $mail->Subject = "Auto Confirmation Email Results for ".$labels->getString('MemberType', 'visitor', 'Guest') . "s arriving " . $ArrivalDT->format('M j, Y');

                $messg = "<p><strong>Today's date:</strong> " . date('M j, Y');
                $messg .= "<p>For ".$labels->getString('MemberType', 'visitor', 'Guest'). "s arriving " . $ArrivalDT->format('M j, Y') . ', ' . $numRecipients . " messages were sent. Bad Emails: " . $badAddresses . "</p>";
                $messg .= "<p><strong>Subject Line:</strong> " . $subjectLine . "</p>";
                $messg .= "<p><strong>Template Text:</strong> </p>" . $sForm->template . "<br/>";
                $messg .= "<p><strong>Results:</strong></p>" . $this->logMsg;

                $mail->msgHTML($messg);

                $mail->send();
            }

            $this->logMsg .= "<hr/>Auto Email Results: " . $numRecipients . " messages were sent";
            $this->logMsg .= "<p>For ".$labels->getString('MemberType', 'visitor', 'Guest'). "s arriving " . $ArrivalDT->format('M j, Y');
            $this->logMsg .= "<br/> Subject Line: " . $subjectLine;

        } else if (!$sendEmail) {
            $this->logMsg .= "<hr/>Auto Email Results: " . $numRecipients . " messages would be sent. Bad addresses: ".$badAddresses;
            $this->logMsg .= "<p>For ".$labels->getString('MemberType', 'visitor', 'Guest'). "s arriving " . $ArrivalDT->format('M j, Y');
            $this->logMsg .= "<br/> Subject Line: " . $subjectLine;
        }


    }

    protected function getConfirmationDocList(PDO $dbh){
        $stmt = $dbh->query("Select d.`idDocument`,concat(d.`Title`, ': ', g.`Description`) as `Title` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` join gen_lookups fu on fu.`Substitute` = g.`Table_Name` where fu.`Code` = 'c' AND fu.`Table_Name` = 'Form_Upload' order by g.`Order`");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        $result = [];
        foreach($rows as $row){
            $result[$row[0]] = $row;
        }

        return $result;
    }

    public static function getResvStatusList(PDO $dbh){
        $stmt = $dbh->query("select `Code`, `Title` from `lookups` where `Category` = 'ReservStatus' and `Show` = 'y' order by `Type` asc;");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        $result = [];
        foreach($rows as $row){
            $result[$row[0]] = $row;
        }

        return $result;
    }

}