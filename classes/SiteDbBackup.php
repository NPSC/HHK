<?php
/**
 * SiteDbBackup.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of DbBackup
 *
 * @author Eric Crane <ecrane at nonprofitsoftwarecorp.org>
 */
class SiteDbBackup {

    public $return_var;
    public $encReturnVar;
    public $encryptedFile;
    public $emailError;
    protected $fileName;
    protected $filePath;

    protected $config;


    function __construct($filePath, $configFileName) {

        $this->config = new Config_Lite($configFileName);

        $this->filePath = $filePath;
        $this->emailError = '';

        $timezone = $this->config->getString('calendar', 'TimeZone', 'America/Chicago');
        date_default_timezone_set($timezone);
    }

    public function backupSchema($ignoreTables = array()) {

        $dbuser = $this->config->getString("backup", "BackupUser", "");
        $dbpwd = $this->decrypt($this->config->getString("backup", "BackupPassword", ""));

        $gzipTheFile = '| gzip';

        $dbUrl = $this->config->getString('db', 'URL', '');
        $dbname = $this->config->getString('db', 'Schema', '');

        if ($dbuser == '' || $dbpwd == '' || $dbname == '' || $this->filePath == '') {
            return FALSE;
        }

        $this->fileName = $this->filePath . date("Y_m_d") . "_" . $dbname . ".sql.gz";

        // ignore tables
        $igtables = '';
        foreach ($ignoreTables as $t) {
            $igtables .= " --ignore-table=$dbname.$t";
        }

        $this->return_var = 0;

        // Backup database
        $command = 'mysqldump ';
        $params = " --host=$dbUrl --skip-lock-tables --single-transaction $igtables --user='$dbuser' --password='$dbpwd' $dbname $gzipTheFile > $this->fileName";
        passthru($command . $params, $this->return_var);


        return file_exists($this->fileName);

    }

    public function encryptFile($inFile = '', $cypher = 'aes-256-cbc') {

        $encPass = $this->config->getstring('backup', 'EncryptionPassword', '');

        // Encrypt Database
        if ($encPass != '') {
            $encPass = $this->decrypt($encPass);

            if ($inFile == '') {
                $inFile = $this->fileName;
            }

            if (file_exists($inFile) === FALSE) {
                return FALSE;
            }

            $outfile = $inFile . '.enc';
            $this->encryptedFileName = $outfile;
            $this->encReturnVar = 0;

            passthru("openssl enc -$cypher -e -pass pass:$encPass -in $inFile -out $outfile", $this->encReturnVar);

            // Delete the clear text file if the encryption completed successfully.
            if ($this->encReturnVar == 0 && file_exists($outfile)) {
                unlink($this->fileName);
                return TRUE;
            }
        }

        return FALSE;

    }

    public function emailFile($emFileName = '') {

        if ($emFileName == '') {
            $emFileName = $this->encryptedFileName;
        }

        if ($emFileName == '' || file_exists($emFileName) === FALSE) {
            return FALSE;
        }

        $now = getDate();
        $emailBackupDay = $this->config->getString("backup", "BackupEmailWeekDay", "Saturday");
        $to = $this->config->getString("backup", "BackupEmailAddr", "");      // Email address to send dump file to

        // Is proper day for download?
        if ($to != '' && (strtolower($emailBackupDay) == "all" || strtolower($now["weekday"]) == strtolower($emailBackupDay))) {

            $attachmentname = 'DB_BackupFile';
            $message = "Encrypted compressed database backup file $attachmentname attached.\r\n\r\n";
            $message .= $this->messageFileList;

            $mail = $this->prepareEmail();

            $mail->From = $this->config->getString("backup", "FromEmailAddr", "");
            $mail->FromName = $this->config->getString("site", "Site_Name", "Hospitality HouseKeeper");
            $mail->addAddress($to);
            $mail->Subject = $this->config->getString("site", "Site_Name", "Hospitality HouseKeeper") . " DB backup file - " . date("Y_m_d");
            $mail->msgHTML($message);

            $mail->addAttachment($emFileName, $attachmentname, 'binary', '', 'attachment');
            $mail->send();

            $this->emailError = $mail->ErrorInfo;

        }

        return TRUE;
    }

    public function deleteOldFiles() {

        $ticksToLive = 84600 * $this->config->getString("backup", "BackupDaysToLive", "8");

        $boundryTime = time() - $ticksToLive;

        // directory listing
        $filelist = scandir($this->filePath);

        // Check each file for freshness
        foreach ($filelist as $f) {

            $fullPath = $this->filePath . $f;

            if (is_file($fullPath) && filemtime($fullPath) !== FALSE && filemtime($fullPath) < $boundryTime) {
                unlink($fullPath);
            }
        }
    }

    public function getErrors() {

        $errorMessage = '';

        if ($this->return_var != 0) {
            $errorMessage .= 'Schema Backup Error (' . $this->return_var . ') ';
        }

        if ($this->encReturnVar != 0) {
            $errorMessage .= 'File Encryption Error (' . $this->encReturnVar . ') ';
        }

        if ($this->encReturnVar != 0) {
            $errorMessage .= 'Send Email Error (' . $this->emailError . ') ';
        }

        return $errorMessage;

    }

    protected function createFileList() {

        // directory listing
        $filelist = scandir($this->filePath);
        $fileListMessage = "Files available on the web host server:\r\n";

        // Check each file for freshness
        foreach ($filelist as $f) {

            $fullPath = $this->filePath . $f;

            if (is_file($fullPath)) {

                $fileListMessage .= $f . "\r\n";

            }
        }

        return $fileListMessage;
    }

    protected function prepareEmail() {

        $mail = new PHPMailer;
        $mailService = $this->config->getString('email_server', 'Type', 'mail');

        switch (strtolower($mailService)) {

            case 'smtp':

                $mail->isSMTP();

                $mail->Host = $this->config->getString('email_server', 'Host', '');
                $mail->SMTPAuth = $this->config->getBool('email_server', 'Auth_Required', 'true');
                $mail->Username = $this->config->getString('email_server', 'Username', '');

                if ($this->config->getString('email_server', 'Password', '') != '') {
                    $mail->Password = $this->decrypt($this->config->getString('email_server', 'Password', ''));
                }

                if ($this->config->getString('email_server', 'Port', '') != '') {
                    $mail->Port = $this->config->getString('email_server', 'Port', '');
                }

                if ($this->config->getString('email_server', 'Secure', '') != '') {
                    $mail->SMTPSecure = $this->config->getString('email_server', 'Secure', '');
                }

                $mail->SMTPDebug = $this->config->getString('email_server', 'Debug', '0');

                break;

            case 'mail':
                $mail->isMail();
                break;
        }

        return $mail;
}

    protected function decrypt($string) {

        $encrypt_method = "AES-256-CBC";
        // hash
        $key = hash('sha256', "017d609a4b2d8910685595C8df");

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', "fYfhHeDmf j98UUy4"), 0, 16);

        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);

        return $output;
    }


    public static function collectConfigSection($config, $section) {

        // Collect tables names to ignore.
        foreach ($config as $secName => $secArray) {

            if ($secName === $section) {
                return $secArray;
            }
        }

        return array();
    }

    public function getMessageFileList() {
        return $this->createFileList();
    }

}
