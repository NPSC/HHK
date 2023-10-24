<?php

namespace HHK\Admin;

use HHK\sec\Session;


/**
 * SiteDbBackup.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of DbBackup
 *
 * @author Eric Crane <ecrane at nonprofitsoftwarecorp.org>
 */

class SiteDbBackup {

    /**
     * Summary of return_var
     * @var
     */
    public $return_var;
    /**
     * Summary of bkupMessage
     * @var
     */
    protected $bkupMessage;
    /**
     * Summary of fileName
     * @var
     */
    protected $fileName;
    /**
     * Summary of filePath
     * @var
     */
    protected $filePath;
    /**
     * Summary of dumpErrorFile
     * @var
     */
    protected $dumpErrorFile;
    /**
     * Summary of clrFileSize
     * @var
     */
    protected $clrFileSize;
    /**
     * Summary of dbBkUpFlag
     * @var
     */
    protected $dbBkUpFlag;
    /**
     * Summary of config
     * @var
     */
    protected $config;


    /**
     * Summary of __construct
     * @param mixed $filePath
     * @param mixed $configFileName
     */
    function __construct($filePath, $configFileName) {

        $this->config = parse_ini_file($configFileName, true);

        $this->filePath = $filePath;
        $this->clrFileSize = 0;
        $uS = Session::getInstance();

        $timezone = $uS->tz;
        date_default_timezone_set($timezone);
    }

    /**
     * Summary of backupSchema
     * @param array|null $ignoreTables
     * @param bool|null $zipIt
     * @return bool
     */
    public function backupSchema($ignoreTables = array(), $zipIt = TRUE) {

        $this->dbBkUpFlag = FALSE;
        $this->bkupMessage = '';
        $zipPipe = '';

        if (strtoupper($this->config['db']['DBMS']) != 'MYSQL') {
            $this->bkupMessage = 'This backup only works for MySQL/Maria Databases.  ';
            return FALSE;
        }

        $dbuser = $this->config['backup']["BackupUser"];
        $dbpwd = $this->decrypt($this->config['backup']["BackupPassword"]);

        $dbUrl = $this->config['db']['URL'];
        $dbname = $this->config['db']['Schema'];

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
        $igtables = '';
        foreach ($ignoreTables as $t) {
            $igtables .= " --ignore-table=$dbname.$t";
        }

        $this->return_var = 0;

        // Backup database
        $command = 'mysqldump ';
        $params = " --single-transaction --skip-lock-tables $igtables --log-error=" . $this->dumpErrorFile . " --host='$dbUrl' --user=$dbuser --password='$dbpwd' $dbname | grep -v DEFINER $zipPipe > " . $this->fileName;
        passthru($command . $params, $this->return_var);

        // Analyze result
        if (file_exists($this->fileName)) {

            $this->clrFileSize = filesize($this->fileName);

            if ($this->clrFileSize > 1000) {

                $this->bkupMessage .= 'Database Dump successful.  File size = ' . $this->clrFileSize . ' bytes.  ';
                $this->dbBkUpFlag = TRUE;

            } else {
                $this->bkupMessage .= 'Database Dump file too small: ' . $this->clrFileSize . ' bytes.  ';
            }

        } else {
            $this->bkupMessage .= 'Database Dump file not found.  ';
        }

        return $this->dbBkUpFlag;
    }

    /**
     * Summary of downloadFile
     * @return bool
     */
    public function downloadFile() {

        if ($this->fileName == '' || file_exists($this->fileName) === FALSE) {
            // $this->emailError = 'File name is not set or doesnt exist:  ' . $this->fileName;
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

    /**
     * Summary of getErrors
     * @return string
     */
    public function getErrors() {

        $errorMessage = 'Schema Backup (' . $this->return_var . ').  ' . $this->bkupMessage;

        if (file_exists($this->dumpErrorFile)) {
            $errorMessage .= '  mysqldump errors: ' . file_get_contents($this->dumpErrorFile);
        }

        return $errorMessage;

    }

    /**
     * Summary of createFileList
     * @return string
     */
    // protected function createFileList() {

    //     // directory listing
    //     $filelist = scandir($this->filePath);
    //     $fileListMessage = "Files available on the web host server:\r\n";

    //     // Check each file for freshness
    //     foreach ($filelist as $f) {

    //         $fullPath = $this->filePath . $f;

    //         if (is_file($fullPath)) {

    //             $fileListMessage .= $f . "\r\n";

    //         }
    //     }

    //     return $fileListMessage;
    // }

    /**
     * Summary of decrypt
     * @param mixed $string
     * @return bool|string
     */
    protected function decrypt($string) {

        $encrypt_method = "AES-256-CBC";
        // hash
        $key = hash('sha256', "017d609a4b2d8910685595C8df");

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', "fYfhHeDmf j98UUy4"), 0, 16);

        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);

        return $output;
    }

    /**
     * Summary of collectConfigSection
     * @param mixed $config
     * @param mixed $section
     * @return mixed
     */
    // public static function collectConfigSection($config, $section) {

    //     // Collect tables names to ignore.
    //     foreach ($config as $secName => $secArray) {

    //         if ($secName === $section) {
    //             return $secArray;
    //         }
    //     }

    //     return array();
    // }

    // /**
    //  * Summary of getMessageFileList
    //  * @return string
    //  */
    // public function getMessageFileList() {
    //     return $this->createFileList();
    // }

}
?>