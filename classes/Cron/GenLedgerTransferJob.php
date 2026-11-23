<?php

namespace HHK\Cron;

use HHK\Exception\RuntimeException;
use HHK\House\GLCodes\GLCodes;
use HHK\House\GLCodes\GLParameters;
use HHK\House\GLCodes\GLTemplateRecord;
use HHK\HTMLControls\HTMLTable;
use HHK\Notification\Mail\HHKMailer;
use HHK\sec\SysConfig;
use PDO;

/**
 * GenLedgerTransferJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Builds the General Ledger journal file for the most recently completed fiscal month
 * (sys_config fm_Start_Day) and uploads it to the GL SFTP server, then emails the
 * results to the notification address(es).
 *
 * Replaces running house/GenLedger.php from the server's crontab.  Schedule it monthly
 * on or after the fiscal month start day.  A dry run maps the records and reports what
 * would be sent, but does not upload or email.
 */
class GenLedgerTransferJob extends AbstractJob implements JobInterface {

    public array $paramTemplate = [
        "NotificationEmail"=>[
            "label"=>"Notification Email (comma separated, blank for none)",
            "type"=>"string",
            "defaultVal"=>"",
            "required"=>false
        ]
    ];

    public function __construct(PDO $dbh, int $idJob, array $params=[], bool $dryRun=false){

        $this->paramTemplate["NotificationEmail"]["defaultVal"] = SysConfig::getKeyValue($dbh, 'sys_config', 'NotificationAddress', '');

        parent::__construct($dbh, $idJob, $params, $dryRun);
    }

    public function tasks():void {

        if (!SysConfig::getKeyValue($this->dbh, 'sys_config', 'useGLCodes', false)) {
            throw new RuntimeException("GL Codes are turned off.  Go to Site Configuration, useGLCodes.");
        }

        $glParm = new GLParameters($this->dbh, 'Gl_Code');

        if ($glParm->isSftpConfigured() === FALSE) {
            throw new RuntimeException("The GL SFTP connection is not set up.  Go to Site Configuration, General Ledger, SFTP settings.");
        }

        // The fiscal month ends the day before the start day, so the most recently
        // completed one ends on this month's start day if we're past it, else last month's.
        $endMonth = new \DateTimeImmutable('first day of this month');
        if (intval((new \DateTime())->format('j')) < intval($glParm->getStartDay())) {
            $endMonth = $endMonth->modify('-1 month');
        }

        $glCodes = new GLCodes($this->dbh, $endMonth->format('m'), $endMonth->format('Y'), $glParm, new GLTemplateRecord());
        $glCodes->mapRecords();

        $lineCount = count($glCodes->getLines());

        if ($this->dryRun) {
            $this->logMsg = $lineCount . " journal lines would be sent to " . $glParm->getHost()
                . ".  Debits " . number_format($glCodes->getTotalDebit(), 2)
                . ", Credits " . number_format($glCodes->getTotalCredit(), 2)
                . ".  " . implode('; ', $glCodes->getErrors());
            return;
        }

        // A month with no payments isn't a failure, just nothing to send.
        if ($lineCount == 0) {
            $this->sendNotification($glParm, $glCodes, 0);
            $this->logMsg = "No journal lines for the period; nothing sent.  " . implode('; ', $glCodes->getErrors());
            return;
        }

        $bytesWritten = $glCodes->transferRecords();

        // Email the results whether or not the transfer worked; the errors list says why.
        $this->sendNotification($glParm, $glCodes, $bytesWritten);

        if ($bytesWritten === FALSE) {
            throw new RuntimeException("GL transfer failed: " . implode('; ', $glCodes->getErrors()));
        }

        $this->logMsg = "Sent " . $glCodes->getFileId() . ".csv (" . $lineCount . " lines, " . number_format($bytesWritten) . " bytes) to " . $glParm->getHost()
            . ".  " . implode('; ', $glCodes->getErrors());
    }

    /**
     * Mail the transfer report, the same report house/GenLedger.php sent.
     */
    protected function sendNotification(GLParameters $glParm, GLCodes $glCodes, int|bool $bytesWritten): void {

        $notificationAddress = trim($this->params["NotificationEmail"] ?? '');

        if ($notificationAddress == '') {
            return;
        }

        $siteName = htmlspecialchars_decode(SysConfig::getKeyValue($this->dbh, 'sys_config', 'siteName'), ENT_QUOTES);
        $from = "no_reply@nonprofitsoftwarecorp.org";

        $mail = new HHKMailer($this->dbh);

        $mail->From = $from;
        $mail->FromName = $siteName;
        $mail->isHTML(true);
        $mail->Subject = $siteName . ' GL Transfer Report'
            . ($bytesWritten === FALSE ? ' FAILED' : '')
            . (stripos($glParm->getRemoteFilePath(), 'test') !== FALSE ? ' THIS IS A TEST' : '');

        foreach ($mail->parseAddresses($notificationAddress) as $a) {
            $mail->addAddress($a['address']);
        }

        $mail->msgHTML($this->notificationBody($glParm, $glCodes, $bytesWritten));

        if ($mail->send() === FALSE) {
            // Don't fail the job over the email; the transfer already happened.
            $this->logMsg .= " Notification email failed: " . $mail->ErrorInfo . ".";
        }
    }

    /**
     * HTML body of the transfer report: a summary table, then any GLCodes messages.
     */
    protected function notificationBody(GLParameters $glParm, GLCodes $glCodes, int|bool $bytesWritten): string {

        $lineCount = count($glCodes->getLines());
        $debit = round($glCodes->getTotalDebit(), 2);
        $credit = round($glCodes->getTotalCredit(), 2);

        if ($bytesWritten === FALSE) {
            $status = 'Failed - see messages below';
        } else if ($lineCount == 0) {
            $status = 'Nothing to send';
        } else {
            $status = 'Sent';
        }

        $summary = [
            'Status' => $status,
            'Period' => $glCodes->getStartDate()->format('M j, Y') . ' to ' . $glCodes->getPeriodEndDate()->format('M j, Y'),
            'File' => ($lineCount > 0 ? $glCodes->getFileId() . '.csv' : '(none)'),
            'Lines Transferred' => number_format(($bytesWritten === FALSE ? 0 : $lineCount)),
            'Invoices' => number_format(count($glCodes->getInvoices())),
            'Total Debits' => number_format($debit, 2),
            'Total Credits' => number_format($credit, 2),
            'Balanced' => ($debit == $credit ? 'Yes' : 'No - off by ' . number_format($credit - $debit, 2)),
            'Bytes Written' => number_format(intval($bytesWritten)),
            'SFTP Host' => $glParm->getHost(),
            'Remote Path' => $glParm->getRemoteFilePath(),
            'Processed at' => (new \DateTime())->format('M j, Y H:i'),
        ];

        $stbl = new HTMLTable();
        foreach ($summary as $label => $value) {
            $stbl->addBodyTr(
                HTMLTable::makeTd($label . ':', ['style' => 'font-weight:bold; text-align:right; padding-right:10px;'])
                . HTMLTable::makeTd(htmlspecialchars($value))
            );
        }

        // GLCodes logs the period and file name as messages too; they're in the summary already.
        $skip = ['Report Dates: ' . $summary['Period'], $glCodes->getFileId() . '.csv'];

        $messages = array_diff($glCodes->getErrors(), $skip);

        $etbl = new HTMLTable();
        foreach ($messages as $e) {
            $etbl->addBodyTr(HTMLTable::makeTd(htmlspecialchars($e)));
        }

        $body = $stbl->generateMarkup();

        if (count($messages) > 0) {
            $body .= '<h4>Messages</h4>' . $etbl->generateMarkup();
        }

        return $body;
    }
}
