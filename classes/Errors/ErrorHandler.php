<?php
namespace HHK\Errors;

use HHK\Notification\Mail\HHKMailer;

/**
 * Manually send bug report when catching an exception
 */
class ErrorHandler {

    public static function reportException(\PDO $dbh, \Throwable $e, string $context = ''): void {

        try{
            $siteName = self::getErrorSiteName();
            $requestType = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ? 'AJAX' : 'Page';

            $message = "New bug report received from {$siteName}\r\n\r\n";
            $message .= "Request Type: $requestType\r\n\r\n";
            $message .= ($context !== '' ? "Context: $context\r\n\r\n":"");
            $message .= "Exception: " . \get_class($e) . "\r\n\r\n";
            $message .= "Message: " . $e->getMessage() . "\r\n\r\n";
            $message .= "File: " . $e->getFile() . " line " . $e->getLine() . "\r\n\r\n";
            $message .= "Trace:\r\n" . $e->getTraceAsString();

            $subject = "New bug report received from {$siteName}";

            $mail = new HHKMailer($dbh);
            $mail->setFrom("noreply@nonprofitsoftwarecorp.org", "BugReporter");
            $mail->addAddress(errorReportEmail);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->send();
        }catch(\Exception $e){
            
        }
    }

    private static function getErrorSiteName(): string {

        $host = explode('.', $_SERVER['HTTP_HOST'] ?? '');
        $requestURI = explode('/', $_SERVER['REQUEST_URI'] ?? '/');

        if (\count($host) === 3) {
            return $host[0];
        } elseif (($requestURI[1] ?? '') === 'demo') {
            return $requestURI[2] ?? 'unknown';
        } else {
            return $requestURI[1] ?? 'unknown';
        }
    }
}
