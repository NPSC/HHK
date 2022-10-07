<?php

namespace HHK\Cron;

use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\sec\SysConfig;
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
        "solicitBuffer"=>[
            "label"=>"SolicitBuffer (days)",
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

    public function __construct(\PDO $dbh, int $idJob, array $params=[], bool $dryRun=false){
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

        $subjectLine = $labels->getString('referral', 'Survey_Subject', '');

        if ($subjectLine == '') {
            throw new RuntimeException("Subject line is missing.  Go to Labels & Prompts, referral -> Survey_Subject.");
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

        $paramList[":delayDays"] = $delayDays;

        $stmt = $this->dbh->prepare("SELECT
    n.Name_First,
    n.Name_Last,
    n.Name_Suffix,
    n.Name_Prefix,
    ne.Email,
    v.idVisit,
    np.idName,
    MAX(v.Actual_Departure) AS `Last_Departure`
FROM
    stays s
        JOIN
    visit v ON v.idVisit = s.idVisit
        AND v.Span = s.Visit_Span
        JOIN
    hospital_stay hp ON v.idHospital_stay = hp.idHospital_stay
        JOIN
    `name` n ON s.idName = n.idName
        JOIN
    `name` np ON hp.idPatient = np.idName
        AND np.Member_Status != 'd'
        JOIN
    name_email ne ON n.idName = ne.idName
        AND n.Preferred_Email = ne.Purpose
WHERE
    n.Member_Status != 'd'
        AND v.`Status` = 'co'
        AND DATE(s.Checkin_Date) < DATE(s.Checkout_Date)
GROUP BY s.idName HAVING DateDiff(NOW(), MAX(v.Actual_Departure)) = :delayDays;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        $stmt->execute($paramList);
        $numRecipients = $stmt->rowCount();
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($numRecipients > $maxAutoEmail) {
            // to many recipients.
            $stmt = NULL;
            throw new RuntimeException("The number of email recipients, " . $stmt->rowCount() . " is higher than the maximum number allowed, $maxAutoEmail. See System Configuration, email_server -> MaxAutoEmail");
        }

        $mail = prepareEmail();

        $mail->From = $from;
        $mail->addReplyTo($from);
        $mail->FromName = $siteName;

        $mail->isHTML(true);
        $mail->Subject = $subjectLine;

        if($this->params["EmailTemplate"] > 0){
            $sForm = new SurveyForm($this->dbh, $this->params['EmailTemplate']);
        }else{
            throw new RuntimeException("Cannot find Survey document");
        }

        if($sForm->getSubjectLine() != ""){
            $subjectLine = $sForm->getSubjectLine();
        }

        $badAddresses = 0;
        $deparatureDT = new \DateTime();
        $deparatureDT->sub(new \DateInterval('P' . $delayDays . 'D'));

        foreach ($recipients as $r) {

            $deparatureDT = new \DateTime($r['Last_Departure']);

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

                $mail->Subject = $subjectLine;
                $mail->msgHTML($form);

                if ($mail->send() === FALSE) {
                    echo $mail->ErrorInfo . '<br/>';
                    $this->logMsg .= "Email Address: " . $r['Email'] . " - Email send error: " . $mail->ErrorInfo . '<br>';
                }

                $this->logMsg .= "Email Address: " . $r['Email'] . ',  Visit Id: ' . $r['idVisit'] . ', Patient Id: ' . $r['idName'] . "<br>";

                //Add Visit note
                $noteText = "Survey Email sent to " . $r['Email'] . " with subject: " . $subjectLine;
                LinkNote::save($this->dbh, $noteText, $r['idVisit'], Note::VisitLink, $uS->username, $uS->ConcatVisitNotes);

            } else {
                $this->logMsg .= "(Email Address: " . $r['Email'] . ',  Visit Id: ' . $r['idVisit'] . ', Patient Id: ' . $r['idName'] . ")<br/>";
            }

        }

        $copyEmail = filter_var(SysConfig::getKeyValue($this->dbh, 'sys_config', 'Auto_Email_Address'), FILTER_VALIDATE_EMAIL);

        if ($sendEmail) {
            if($copyEmail && $copyEmail != ''){
                $mail->clearAddresses();
                $mail->addAddress($copyEmail);
                $mail->Subject = "Auto Email Results for ".$labels->getString('MemberType', 'visitor', 'MemberType', 'Guest') . "s leaving " . $deparatureDT->format('M j, Y');

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
            //echo "<br/>Body Template:<br/>" . $sForm->template;
        }


    }

    protected function getSurveyDocList(\PDO $dbh){
        $stmt = $dbh->query("Select d.`idDocument`,concat(d.`Title`, ': ', g.`Description`) as `Title` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` join gen_lookups fu on fu.`Substitute` = g.`Table_Name` where fu.`Code` = 's' AND fu.`Table_Name` = 'Form_Upload' order by g.`Order`");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        $result = [];
        foreach($rows as $row){
            $result[$row[0]] = $row;
        }

        return $result;
    }

}
?>