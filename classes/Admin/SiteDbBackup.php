<?php

namespace HHK\Admin;

use HHK\sec\Session;
use Ifsnop\Mysqldump\Mysqldump;

/**
 * SiteDbBackup.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * SiteDbBackup
 *
 * @author Eric Crane <ecrane at nonprofitsoftwarecorp.org>
 */

class SiteDbBackup {


    public int $return_var;

    protected string $bkupMessage;

    protected string $fileName;

    protected int $clrFileSize = 0;

    protected bool $dbBkUpFlag = false;
    
    /**
     * @param string $filePath Directory for backupSchema() files; not used when streaming.
     */
    public function __construct(protected string $filePath = '') {
        $uS = Session::getInstance();
        date_default_timezone_set($uS->tz);
    }

    /**
     * Generate a database schema backup file.
     *
     * @param array $ignoreTables
     * @param bool $zipIt
     * @return bool
     */
    public function backupSchema(array $ignoreTables = array(), bool $zipIt = TRUE): bool {

        $this->dbBkUpFlag = FALSE;
        $this->bkupMessage = '';

        $dumper = $this->createDumper($ignoreTables, ($zipIt ? Mysqldump::GZIP : Mysqldump::NONE));

        if ($dumper === NULL) {
            return FALSE;
        }

        $this->fileName = $this->filePath . $this->fileName . ($zipIt ? '.gz' : '');

        try {
            $dumper->start($this->fileName);
        } catch (\Exception $e) {
            $this->bkupMessage = 'mysqldump error: ' . $e->getMessage();
            $this->return_var = 1;
            return FALSE;
        }

        // Analyze result
        if (file_exists($this->fileName)) {

            $this->clrFileSize = filesize($this->fileName);

            if ($this->clrFileSize > 1000) {

                $this->bkupMessage .= 'Database Dump successful.  File size = ' . $this->clrFileSize . ' bytes.  ';
                $this->dbBkUpFlag = TRUE;

            } else {
                $this->bkupMessage .= 'Database Dump file too small: ' . $this->clrFileSize . ' bytes.  ';
                unlink($this->fileName);
            }

        } else {
            $this->bkupMessage .= 'Database Dump file not found.  ';
        }

        return $this->dbBkUpFlag;
    }

    /**
     * Dump the database as gzip directly to the browser, without a temp file.
     *
     * Returns FALSE before sending anything if the database parameters are missing.  Once headers
     * are sent, a failure mid-dump leaves the browser with a truncated file; check headers_sent()
     * and exit rather than rendering the page.
     *
     * @param array $ignoreTables
     * @return bool
     */
    public function streamSchema(array $ignoreTables = array()): bool {

        $this->dbBkUpFlag = FALSE;
        $this->bkupMessage = '';

        $dumper = $this->createDumper($ignoreTables, Mysqldump::GZIPSTREAM);

        if ($dumper === NULL) {
            return FALSE;
        }

        // Release the session lock so other requests from this user aren't blocked during a long dump.
        session_write_close();

        set_time_limit(0);
        ini_set('zlib.output_compression', '0');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $this->fileName . '.gz"');
        header('Expires: 0');
        header('Cache-Control: no-store, must-revalidate');
        header('Pragma: public');
        header('X-Accel-Buffering: no');

        try {
            $dumper->start('php://output');
        } catch (\Exception $e) {
            $this->bkupMessage = 'mysqldump error: ' . $e->getMessage();
            $this->return_var = 1;
            return FALSE;
        }

        $this->bkupMessage = 'Database Dump streamed.  ';
        $this->dbBkUpFlag = TRUE;

        return TRUE;
    }

    /**
     * Build a Mysqldump from the session database parameters and set $this->fileName to the bare dump name (no directory or .gz).
     *
     * @param array $ignoreTables
     * @param string $compress Mysqldump compression constant
     * @return Mysqldump|null NULL if the parameters are missing or the connection fails
     */
    protected function createDumper(array $ignoreTables, string $compress): ?Mysqldump {

        $uS = Session::getInstance();

        $dbUrl = $uS->databaseURL;
        $dbname = $uS->databaseName;
        $dbuser = $uS->databaseUName;
        $dbpwd = $uS->databasePWord;

        if ($dbuser == '' || $dbpwd == '' || $dbname == '' || $dbUrl == '') {
            $this->bkupMessage = 'Database parameters are not set.  ';
            return NULL;
        }

        if (strtolower($dbUrl) == 'localhost') {
            $dbUrl = '127.0.0.1';
        }

        $this->fileName = $dbname . '_' . date('Y-m-d_His') . '.sql';

        $dumpSettings = [
            'compress' => $compress,
            'exclude-tables' => $ignoreTables,
            'single-transaction' => true,
            'lock-tables' => false,
            'skip-definer' => true,
            'skip-comments' => true,
        ];

        $this->return_var = 0;

        try {
            return new Mysqldump("mysql:host=$dbUrl;dbname=$dbname", $dbuser, $dbpwd, $dumpSettings);
        } catch (\Exception $e) {
            $this->bkupMessage = 'mysqldump error: ' . $e->getMessage();
            $this->return_var = 1;
            return NULL;
        }
    }

    /**
     * Stream the backup file to the browser for download and delete it from the server.
     * 
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

        unlink($this->fileName);

        exit();
    }

    /**
     * Get the error message from the backup process.
     * 
     * @return string
     */
    public function getErrors(): string {

        return 'Schema Backup (' . $this->return_var . ').  ' . $this->bkupMessage;

    }
}