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
    protected $bkupMessage;
    public $encReturnVar;
    protected $encMessage;
    public $encryptedFile;
    public $emailError;
    protected $fileName;
    protected $filePath;
    protected $dumpErrorFile;
    protected $clrFileSize;
    protected $dbBkUpFlag;
    protected $config;


    function __construct($filePath, $configFileName) {

        $this->config = new Config_Lite($configFileName);

        $this->filePath = $filePath;
        $this->emailError = '';
        $this->clrFileSize = 0;

        $timezone = $this->config->getString('calendar', 'TimeZone', 'America/Chicago');
        date_default_timezone_set($timezone);
    }

    public function backupSchema($ignoreTables = array(), $zipIt = TRUE) {

        $this->dbBkUpFlag = FALSE;
        $this->bkupMessage = '';
        $zipPipe = '';

        if (strtoupper($this->config->getString('db', 'DBMS', '')) != 'MYSQL') {
            $this->bkupMessage = 'This backup only works for MySQL Databases.  ';
            return FALSE;
        }

        $dbuser = $this->config->getString("backup", "BackupUser", "");
        $dbpwd = $this->decrypt($this->config->getString("backup", "BackupPassword", ""));

        $dbUrl = $this->config->getString('db', 'URL', '');
        $dbname = $this->config->getString('db', 'Schema', '');

        if ($dbuser == '' || $dbpwd == '' || $dbname == '' || $dbUrl == '' || $this->filePath == '') {
            $this->bkupMessage = 'Database Backup parameters are not set.  ';
            return FALSE;
        }

        if (strtolower($dbUrl) == 'localhost') {
            $dbUrl = '127.0.0.1';
        }

        if ($zipIt) {
            $this->fileName = $this->filePath . $dbname . ".sql.zip";
            $zipPipe = '| gzip';
        } else {
            $this->fileName = $this->filePath . $dbname . ".sql";
            $zipPipe = '';
        }

        $this->dumpErrorFile = $this->filePath . $dbname . "_errors.txt";

        if (file_exists($this->fileName)) {
            unlink($this->fileName);
        }

        if (file_exists($this->dumpErrorFile)) {
            unlink($this->dumpErrorFile);
        }

        // ignore tables
        $igtables = '';
        foreach ($ignoreTables as $t) {
            $igtables .= " --ignore-table=$t";
        }

        $this->return_var = 0;

// mysqldump.exe --defaults-file="c:\users\eric\appdata\local\temp\tmpnh5gsa.cnf"  --set-gtid-purged=OFF --user=ab17426_eric --host=hospitalityhousekeeper.online --protocol=tcp --port=3306 --default-character-set=utf8 --single-transaction=TRUE --skip-triggers "ab17426_noras"

        // Backup database
        $command = 'mysqldump ';
        $params = " --single-transaction --skip-lock-tables --log-error=" . $this->dumpErrorFile . " --host='$dbUrl' --user=$dbuser --password='$dbpwd' $dbname $zipPipe > " . $this->fileName;
        passthru($command . $params, $this->return_var);

        // Analyze result
        if (file_exists($this->fileName)) {

            $this->clrFileSize = filesize($this->fileName);

            if ($this->clrFileSize > 1000) {

                $this->bkupMessage .= 'Database Backup successful.  File size = ' . $this->clrFileSize . ' bytes.  ';
                $this->dbBkUpFlag = TRUE;

            } else {
                $this->bkupMessage .= 'Database Backup file too small: ' . $this->clrFileSize . ' bytes.  ';
            }

        } else {
            $this->bkupMessage .= 'Database Backup file not found.  ';
        }

        return $this->dbBkUpFlag;
    }

    public function encryptFile($inFile = '', $cypher = 'aes-256-gcm') {

        $this->encMessage = '';
        $encPass = $this->config->getstring('backup', 'EncryptionPassword', '');

        // Encrypt Database
        if ($encPass != '') {

            $encPass = $this->decrypt($encPass);

            if ($inFile == '') {
                $inFile = $this->fileName;
            }

            if (file_exists($inFile) === FALSE) {
                $this->encMessage = 'The Clear text file is Missing: ' . $inFile;
                return FALSE;
            }

            $outfile = $inFile . '.enc';

            if (file_exists($outfile)) {
                unlink($outfile);
            }

            $this->encryptedFileName = $outfile;
            $this->encReturnVar = 0;

            passthru("openssl enc -$cypher -e -base64 -z -pass pass:$encPass -in $inFile -out $outfile", $this->encReturnVar);

            // Delete the clear text file if the encryption completed successfully.
            if ($this->encReturnVar == 0 && file_exists($outfile)) {
                unlink($this->fileName);
                $this->encMessage = 'Encryption Successful. ';
                return TRUE;
            } else {
                $this->encMessage .= 'Encryption Failed, return code = ' . $this->encReturnVar;
            }
        } else {
            $this->encMessage = 'Encryption PW is not set. ';
        }

        return FALSE;

    }

    public function downloadFile() {

        if ($this->fileName == '' || file_exists($this->fileName) === FALSE) {
            $this->emailError = 'File name is not set or doesnt exist:  ' . $this->fileName;
            return FALSE;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($this->fileName).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($this->fileName));

        ob_flush();

        readfile($this->fileName);

        exit();
    }

    public function emailFile($emFileName = '', $forceMail = FALSE) {

        if ($emFileName == '') {
            $emFileName = $this->encryptedFileName;
        }

        if ($emFileName == '' || file_exists($emFileName) === FALSE) {
            $this->emailError = 'File name is not set or doesnt exist:  ' . $emFileName;
            return FALSE;
        }

        $now = getDate();
        $emailBackupDay = $this->config->getString("backup", "BackupEmailWeekDay", "Saturday");
        $to = $this->config->getString("backup", "BackupEmailAddr", "");      // Email address to send dump file to

        // Is proper day for download?
        if ($to != '' && ($forceMail || strtolower($now["weekday"]) == strtolower($emailBackupDay))) {

            $attachmentname = $emFileName;
            $message = "Encrypted compressed database backup file $attachmentname attached.\r\n\r\n";
            $message .= $this->messageFileList;

            $mail = $this->prepareEmail();

            $mail->From = $this->config->getString("backup", "FromEmailAddr", "");
            $mail->FromName = $this->config->getString("site", "Site_Name", "Hospitality HouseKeeper");
            $mail->addAddress($to);
            $mail->Subject = $this->config->getString("site", "Site_Name", "Hospitality HouseKeeper") . " DB backup file - " . date("Y_m_d");
            $mail->msgHTML($message);

            $mail->addAttachment($emFileName, $attachmentname, 'binary', '', 'attachment');

            if ($mail->send()) {
                $this->emailError = 'Email successfull.';
            }

            $this->emailError = $mail->ErrorInfo;

        } else {
            $this->emailError = 'Wrong Day or To is missing.  To = ' . $to;
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

        $errorMessage = 'Schema Backup (' . $this->return_var . ').  ' . $this->bkupMessage;

        if ($this->encMessage != '') {
            $errorMessage .= '  File Encryption (' . $this->encReturnVar . '). ' . $this->encMessage;
        }

        if ($this->emailError != '') {
            $errorMessage .= '  Send Email: ' . $this->emailError;
        }

        if (file_exists($this->dumpErrorFile)) {
            $errorMessage .= '  mysqldump errors: ' . file_get_contents($this->dumpErrorFile);
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
