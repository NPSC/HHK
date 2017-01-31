<?php
/**
 * Patch.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Patch
 *
 * @author Eric
 */
class Patch {

    public $oldVersion = '';
    public $newVersion = '';

    public function verifyUpLoad($zipFile, $versionFileName, $origBuild) {

        $result = '';
        $fname = '..' . DS .'patch' . DS . 'patchVer.cfg';
        $fileSize = 0;
        $this->oldVersion = $origBuild;

        $zip = zip_open($zipFile);

        if (is_resource($zip)) {

            while (($entry = zip_read($zip)) !== false) {

                if (zip_entry_name($entry) == $versionFileName) {

                    // copy the new version in
                    $fileSize = file_put_contents($fname, zip_entry_read($entry, zip_entry_filesize($entry)));

                    zip_entry_close($entry);

                    if ($fileSize === false) {
                        zip_close($zip);
                        throw new Hk_Exception_Runtime("Unable to write patch version file.  ");
                    }

                    break;

                }
            }
        }

        zip_close($zip);


        if ($fileSize <= 0) {
            throw new Hk_Exception_Runtime("Patch version file not found or empty.  ");
        }


        $siteCnf = new Config_Lite($fname);
        $newBuild = intval($siteCnf->getString('code', 'Build'), 10);
        $newVersion = $siteCnf->getString('code', 'Version');

        $this->newVersion = $newVersion . '.' . $newBuild;

        $newVersions = explode('.', $newVersion);

        if (count($newVersions) < 2) {
            throw new Hk_Exception_Runtime("New version not in proper format: " . $newVersion);
        }

        $origBuilds = explode('.', $origBuild);

        if (count($origBuilds) < 3) {
            throw new Hk_Exception_Runtime("Current site version not in proper format: " . $origBuild);
        }

        // Major version
        if (intval($newVersions[0], 10) < intval($origBuilds[0], 10)) {
            throw new Hk_Exception_Runtime("The major version of this update (" . $newVersions[0] . ") is lower than this site's major version (" . $origBuilds[0] . ").  ");
        }

        // Minor Version
        if (intval($newVersions[1], 10) < intval($origBuilds[1], 10)) {
            throw new Hk_Exception_Runtime("The minor version of this update (" . $newVersions[1] . ") is lower than this site's minor version (" . $origBuilds[1] . ").  ");
        }

        // Build Number
        if ($newBuild < intval($origBuilds[2], 10)) {
            throw new Hk_Exception_Runtime("The build number of this update (" . $newBuild . ") is lower than this site's build number (" . $origBuilds[2] . ").  ");
        }

        return $result;
    }

    public static function loadFiles($fileRoot, $filePathName) {

        $result = "";

        $skipDirs = array('.git', 'install');

        self::deleteBakFiles($fileRoot, $skipDirs);

         // Detect guest tracking subunit
        if (is_dir($fileRoot . "house") === FALSE) {
            $skipDirs[] = 'house';
        }
        // Detect volunteer subunit
        if (is_dir($fileRoot . "volunteer") === FALSE) {
            $skipDirs[] = 'volunteer';
        }

        // Renames existing files to *.bak and copies in new versions.
        $result .= self::unzip($filePathName, $skipDirs);

        return $result;
    }

    public static function updateWithSqlStmts(\PDO $dbh, $tfile, $type = '') {

        $message = $type . ' filename is missing.  ';

        if ($tfile != '') {

            $tquery = file_get_contents($tfile);
            $tresult = self::multiQueryPDO($dbh, $tquery);

            if (count($tresult) > 0) {

                $message = '';

                foreach ($tresult as $err) {
                    $message .= $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                }

                return $type . ' update failed:  ' . $message;
            }

            $message = $type . " created... ";

        }

        return $message;

    }

    public static function updateSps(\PDO $dbh, $spFile){

        $message = 'Stored Procedure Filename is missing.  ';

        if ($spFile != '') {

            $spquery = file_get_contents($spFile);
            //$result = multiQuery($mysqli, $spquery, '$$', '-- ;');
            $result = self::multiQueryPDO($dbh, $spquery, '$$', '-- ;');

            if (count($result) > 0) {

                $message = "**Stored Procedures Failed:   ";

                foreach ($result as $err) {
                    $message .= $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                }

            } else {
                $message = "Stored Procedures created.  ";
            }
        }

        return $message;
    }

    public static function loadConfigUpdates($configUpdateFile, Config_Lite $config) {

        if ($configUpdateFile == '') {
            return '';
        }

        $result = "";

        try {
            $cfupdates = new Config_Lite($configUpdateFile);
        } catch (Config_Lite_Exception_Runtime $ex) {
            $result = $ex->getMessage();
            return $result;
        }

        foreach ($cfupdates as $secName => $secArray) {

            foreach ($secArray as $itemName => $val) {

                // Only update if the target file is missing the section or item
                if ($config->has($secName, $itemName) === FALSE || $secName == 'code') {

                    $config->set($secName, $itemName, $val);
                    $result .= $secName . "." . $itemName . " = " . $val . "<br/>";
                }
            }
        }

        try {
            $config->save();

        } catch (Config_Lite_Exception_Runtime $ex) {

            $result .= $ex  . "<br/>";
        }

        return $result;
    }

    public static function deleteBakFiles($directory, array $skipDirs = array(), $oldExtension = 'bak') {

        $fit = new FilesystemIterator($directory, FilesystemIterator::UNIX_PATHS | FilesystemIterator::CURRENT_AS_FILEINFO);

        foreach ($fit as $fileinfo) {

            if ($fileinfo->isDir()) {

                // Not these files
                $flag = FALSE;
                foreach ($skipDirs as $d) {
                    if (stripos($fileinfo->getFilename(), $d) !== false) {
                        $flag = true;
                    }
                }

                if ($flag) {
                    continue;
                }

                self::deleteBakFiles($directory.$fileinfo->getFilename().DS, $skipDirs, $oldExtension);

            } else {

                $fname = $fileinfo->getRealPath();

                if ($fileinfo->getExtension() == $oldExtension) {
                    unlink($fileinfo->getRealPath());
                }
            }
        }
    }

    protected static function unzip($file, array $skipDirs, $rootDir = 'hhk', $oldExtension = 'bak') {

        $result = '';
        $zip = zip_open($file);

        if (is_resource($zip)) {

            $colCounter = 0;
            $table = new HTMLTable();
            $tr = "";

            while (($entry = zip_read($zip)) !== false) {


                if (strpos(zip_entry_name($entry), "/") !== false) {

                    $last = strrpos(zip_entry_name($entry), "/");
                    $dir = substr(zip_entry_name($entry), 0, $last);
                    $file = substr(zip_entry_name($entry), strrpos(zip_entry_name($entry), "/") + 1);


                    // Not these files
                    $flag = FALSE;
                    foreach ($skipDirs as $d) {
                        if (stripos($dir, $d) !== false) {
                            $flag = true;
                        }
                    }

                    if ($flag) {
                        continue;
                    }

                    $relDir = str_ireplace($rootDir, '..', $dir);

                    if (strlen(trim($file)) > 0) {

                        // rename the existing file
                        if (file_exists($relDir . "/" . $file)) {
                            $renamedFile = $relDir . "/" . $file . '.' . $oldExtension;
                            rename($relDir . "/" . $file, $renamedFile);
                        }

                        // copy the new version in
                        $fileSize = file_put_contents($relDir . "/" . $file, zip_entry_read($entry, zip_entry_filesize($entry)));

                        if ($fileSize === false) {
                            throw new Hk_Exception_Runtime("Unable to write file: $relDir/$file");
                        }

                        if ($colCounter >= 2) {
                            $table->addBodyTr($tr);
                            $colCounter = 0;
                            $tr = '';
                        }

                        $tr .= HTMLTable::makeTd($relDir . "/" . $file);
                        $colCounter++;
                    }
                }
            }

            if ($tr != '') {
                $table->addBodyTr($tr);
            }

            $result = $table->generateMarkup();

        } else {
            throw new Hk_Exception_Runtime("Unable to open zip file.  ");
        }

        return $result;
    }

    public static function multiQueryPDO(\PDO $dbh, $query, $delimiter = ";", $splitAt = ';') {
        $msg = array();

        if ($query === FALSE || trim($query) == '') {
            return $msg;
        }

        $qParts = explode($splitAt, $query);

        foreach ($qParts as $q) {

            $q = trim($q);
            if ($q == '' || $q == $delimiter || $q == 'DELIMITER') {
                continue;
            }

            if ($dbh->exec($q) === FALSE) {
                $msg[] = array('error'=>$dbh->errorInfo(), 'errno'=> $dbh->errorCode(), 'query'=> $q );
            }
        }

        return $msg;
    }


}

