<?php

namespace HHK\Admin;

use HHK\Config_Lite\Config_Lite;
use HHK\sec\Session;


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
    protected $fileName;
    protected $filePath;
    protected $dumpErrorFile;
    protected $clrFileSize;
    protected $dbBkUpFlag;
    protected $config;


    function __construct($filePath, $configFileName) {

        $this->config = new Config_Lite($configFileName);

        $this->filePath = $filePath;
        $this->clrFileSize = 0;
        $uS = Session::getInstance();

        $timezone = $uS->tz;
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
            $this->bkupMessage = 'Database Backup parameters are not set in site.cfg.  ';
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
//         $igtables = '';
//         foreach ($ignoreTables as $t) {
//             $igtables .= " --ignore-table=$t";
//         }

        $this->return_var = 0;

        // Backup database
        $command = 'mysqldump ';
        $params = " --single-transaction --skip-lock-tables --log-error=" . $this->dumpErrorFile . " --host='$dbUrl' --user=$dbuser --password='$dbpwd' $dbname | grep -v DEFINER $zipPipe > " . $this->fileName;
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

    public function getErrors() {

        $errorMessage = 'Schema Backup (' . $this->return_var . ').  ' . $this->bkupMessage;

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
?>