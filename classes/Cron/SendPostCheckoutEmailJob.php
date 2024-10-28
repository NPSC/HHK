<?php

namespace HHK\Cron;

use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\Notification\Mail\HHKMailer;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use HHK\SysConst\VisitStatus;
use PDO;
use HHK\House\TemplateForm\SurveyForm;
use HHK\Exception\RuntimeException;

/**
 * EmailCheckedoutJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of EmailCheckedoutJob
 *
 * @author Will Ireland
 */

class SendPostCheckoutEmailJob extends AbstractJob implements JobInterface{

    public array $paramTemplate = [
        "after"=>[
            "label"=>"Send",
            "type"=>"select",
            "values"=>[
                "checkin"=>["checkin","After Check In"],
                "checkout"=>["checkout","After Check Out"],
                "beforecheckout"=>["beforecheckout", "Before Check Out"]
            ],
            "defaultVal"=>"checkout",
            "required"=>true
        ],
        "solicitBuffer"=>[
            "label"=>"Days",
            "type"=>"number",
            "defaultVal" =>"",
            "min"=>1,
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
        $this->paramTemplate["EmailTemplate"]["values"] = $this->getSurveyDocList($dbh);

        parent::__construct($dbh, $idJob, $params, $dryRun);
    }

    public function tasks():void {

        $sendEmail = ($this->dryRun ? FALSE : TRUE);

        $labels = Labels::getLabels();

        $uS = Session::getInstance();

        $siteName = SysConfig::getKeyValue($this->dbh, 'sys_config', 'siteName');
        $from = SysConfig::getKeyValue($this->dbh, 'sys_config', 'NoReplyAddr');      // Email address message will show as coming from.
        $maxAutoEmail = SysConfig::getKeyValue($this->dbh, 'sys_config', 'MaxAutoEmail');

        if($this->params["EmailTemplate"] > 0){
            $sForm = new SurveyForm($this->dbh, $this->params['EmailTemplate']);
        }else{
            throw new RuntimeException("Cannot find Survey document");
        }

        if($sForm->getSubjectLine() != ""){
            $subjectLine = $sForm->getSubjectLine();
        }else if($labels->getString('referral', 'Survey_Subject', '') != ""){
            $subjectLine = $labels->getString('referral', 'Survey_Subject', '');
        }else{
            throw new RuntimeException("Subject line is missing.  Go to Resource Builder -> Form Upload -> Survey -> Email Subject.");
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

        // Load guests
        if (isset($this->params["after"])) {
            switch ($this->params["after"]) {

                case "checkin": //post checkin
                    $paramList[":delayDays"] = $delayDays;

                    $stmt = $this->dbh->prepare("SELECT
                        n.Name_First,
                        n.Name_Last,
                        n.Name_Suffix,
                        n.Name_Prefix,
                        ne.Email,
                        v.idVisit,
                        v.idPrimaryGuest
                    FROM
                        stays s
                            JOIN
                        visit v ON v.idVisit = s.idVisit
                            AND v.Span = s.Visit_Span
                        JOIN
                        `name` n ON s.idName = n.idName
                            AND n.Member_Status != 'd'
                            AND n.Exclude_Email = 0
                            JOIN
                        `name_email` ne ON n.idName = ne.idName
                            AND n.Preferred_Email = ne.Purpose
                    WHERE
                        n.Member_Status != 'd'
                            AND v.`Status` = 'a'
                            and DateDiff(DATE(NOW()), DATE(v.Arrival_Date)) = :delayDays
                    GROUP BY s.idName;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                    break;

                case "checkout": //post checkout
                    $paramList[":delayDays"] = $delayDays;
                    $stmt = $this->dbh->prepare("SELECT
                        n.Name_First,
                        n.Name_Last,
                        n.Name_Suffix,
                        n.Name_Prefix,
                        ne.Email,
                        v.idVisit,
                        v.idPrimaryGuest,
                        v.Actual_Departure
                    FROM
                        stays s
                            JOIN
                        visit v ON v.idVisit = s.idVisit
                            AND v.Span = s.Visit_Span
                        JOIN
                        `name` n ON v.idPrimaryGuest = n.idName
                            AND n.Member_Status != 'd'
                            AND n.Exclude_Email = 0
                            JOIN
                        `name_email` ne ON n.idName = ne.idName
                            AND n.Preferred_Email = ne.Purpose
                    WHERE
                        n.Member_Status != 'd'
                            AND v.`Status` = 'co'
                            AND DATE(s.Checkin_Date) < DATE(s.Checkout_Date) and
                            DateDiff(DATE(NOW()), DATE(v.Actual_Departure)) = :delayDays
                    GROUP BY v.idPrimaryGuest;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

                    break;

                case "beforecheckout": //pre checkout
                    $paramList[":delayDays"] = 0 - $delayDays; //set delayDays negative
                    $stmt = $this->dbh->prepare("SELECT
                        n.Name_First,
                        n.Name_Last,
                        n.Name_Suffix,
                        n.Name_Prefix,
                        ne.Email,
                        v.idVisit,
                        v.idPrimaryGuest,
                        v.Actual_Departure
                    FROM
                        stays s
                            JOIN
                        visit v ON v.idVisit = s.idVisit
                            AND v.Span = s.Visit_Span
                        JOIN
                        `name` n ON v.idPrimaryGuest = n.idName
                            AND n.Member_Status != 'd'
                            AND n.Exclude_Email = 0
                            JOIN
                        `name_email` ne ON n.idName = ne.idName
                            AND n.Preferred_Email = ne.Purpose
                    WHERE
                        n.Member_Status != 'd'
                            AND v.`Status` in ('" . VisitStatus::CheckedIn . "', '" . VisitStatus::ChangeRate . "', '" . VisitStatus::NewSpan . "', '" . VisitStatus::OnLeave . "') AND
                            DateDiff(DATE(NOW()), DATE(v.Expected_Departure)) = :delayDays
                    GROUP BY v.idPrimaryGuest;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

                    break;

                default:
                    throw new RuntimeException("the 'Send' parameter is invalid");
            }

            $stmt->execute($paramList);
            $numRecipients = $stmt->rowCount();
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }else{
            throw new RuntimeException("The 'Send' parameter cannot be empty");
        }

        if ($numRecipients > $maxAutoEmail) {
            // to many recipients.
            throw new RuntimeException("The number of email recipients, " . $stmt->rowCount() . " is higher than the maximum number allowed, $maxAutoEmail. See System Configuration, email_server -> MaxAutoEmail");
        }

        $mail = new HHKMailer($this->dbh);

        $mail->From = $from;
        $mail->addReplyTo($from);
        $mail->FromName = htmlspecialchars_decode($siteName, ENT_QUOTES);
        $mail->Subject = htmlspecialchars_decode($subjectLine, ENT_QUOTES);
        
        $mail->isHTML(true);

        $badAddresses = 0;
        $deparatureDT = new \DateTime();
        
        if($this->params["after"] == "beforecheckout"){
            $deparatureDT->add(new \DateInterval('P' . $delayDays . 'D'));
        } else {
            $deparatureDT->sub(new \DateInterval('P' . $delayDays . 'D'));
        }

        foreach ($recipients as $r) {

            //$deparatureDT = new \DateTime($r['Actual_Departure']);

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

            $form = $sForm->createForm($sForm->makeReplacements($r));

            if ($sendEmail) {

                $mail->clearAddresses();
                $mail->addAddress($emailAddr);
                $mail->msgHTML($form);

                if ($mail->send() === FALSE) {
                    echo $mail->ErrorInfo . '<br/>';
                    $this->logMsg .= "Email Address: " . $r['Email'] . " - Email send error: " . $mail->ErrorInfo . '<br>';
                }

                $this->logMsg .= "Email Address: " . $r['Email'] . ',  Visit Id: ' . $r['idVisit'] . ', PrimaryGuest Id: ' . $r['idPrimaryGuest'] . "<br>";

                //Add Visit note
                $noteText = "Email sent to " . $r['Email'] . " with subject: " . $subjectLine;
                LinkNote::save($this->dbh, $noteText, $r['idVisit'], Note::VisitLink, '', $uS->username, $uS->ConcatVisitNotes);

            } else {
                $this->logMsg .= "(Email Address: " . $r['Email'] . ',  Visit Id: ' . $r['idVisit'] . ', PrimaryGuest Id: ' . $r['idPrimaryGuest'] . ")<br/>";
            }

        }

        $copyEmail = filter_var(SysConfig::getKeyValue($this->dbh, 'sys_config', 'Auto_Email_Address'), FILTER_VALIDATE_EMAIL);

        if ($sendEmail) {
            if($copyEmail && $copyEmail != ''){
                $mail->clearAddresses();
                $mail->addAddress($copyEmail);
                $mail->Subject = "Auto Email Results for ".$labels->getString('MemberType', 'visitor', 'Guest') . "s leaving " . $deparatureDT->format('M j, Y');

                $messg = "<p><strong>Today's date:</strong> " . date('M j, Y');
                $messg .= "<p>For ".$labels->getString('MemberType', 'visitor', 'Guest'). "s leaving " . $deparatureDT->format('M j, Y') . ', ' . $numRecipients . " messages were sent. Bad Emails: " . $badAddresses . "</p>";
                $messg .= "<p><strong>Subject Line:</strong> " . $subjectLine . "</p>";
                $messg .= "<p><strong>Template Text:</strong> </p>" . $sForm->template . "<br/>";
                $messg .= "<p><strong>Results:</strong></p>" . $this->logMsg;

                $mail->msgHTML($messg);

                $mail->send();
            }

            $this->logMsg .= "<hr/>Auto Email Results: " . $numRecipients . " messages were sent";
            $this->logMsg .= "<p>For ".$labels->getString('MemberType', 'visitor', 'Guest'). "s leaving " . $deparatureDT->format('M j, Y');
            $this->logMsg .= "<br/> Subject Line: " . $subjectLine;

        } else if (!$sendEmail) {
            $this->logMsg .= "<hr/>Auto Email Results: " . $numRecipients . " messages would be sent. Bad addresses: ".$badAddresses;
            $this->logMsg .= "<p>For ".$labels->getString('MemberType', 'visitor', 'Guest'). "s leaving " . $deparatureDT->format('M j, Y');
            $this->logMsg .= "<br/> Subject Line: " . $subjectLine;
        }


    }

    protected function getSurveyDocList(PDO $dbh){
        $stmt = $dbh->query("Select d.`idDocument`,concat(d.`Title`, ': ', g.`Description`) as `Title` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` join gen_lookups fu on fu.`Substitute` = g.`Table_Name` where fu.`Code` = 's' AND fu.`Table_Name` = 'Form_Upload' order by g.`Order`");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        $result = [];
        foreach($rows as $row){
            $result[$row[0]] = $row;
        }

        return $result;
    }

}