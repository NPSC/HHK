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

    public $results;

    public function __construct() {
        $this->results = array();
    }

    public function verifyUpLoad($zipFile, $versionFileName, $origBuild) {

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
            throw new Hk_Exception_Runtime("Patch file not found or empty.  ");
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

    }

    public function loadFiles($fileRoot, $filePathName) {

        $result = "";

        $skipDirs = array('.git', 'install');

        self::deleteBakFiles($fileRoot);

         // Detect guest tracking subunit
        if (is_dir($fileRoot . "house") === FALSE) {
            $skipDirs[] = 'house';
        }
        // Detect volunteer subunit
        if (is_dir($fileRoot . "volunteer") === FALSE) {
            $skipDirs[] = 'volunteer';
        }

        // Renames existing files to *.bak and copies in new versions.
        $result .= $this->unzip($filePathName, $skipDirs);

        return $result;
    }

    public function updateWithSqlStmts(\PDO $dbh, $tfile, $type = '', $delimiter = ';', $splitAt = ';') {

        $this->results = array();

        if ($tfile == '') {
            return $type . ' Filename is missing.  ';
        }

        $tquery = file_get_contents($tfile);

        $tresult = self::multiQueryPDO($dbh, $tquery, $delimiter, $splitAt);

        if (count($tresult) > 0) {

            foreach ($tresult as $err) {
                $this->results[$err['errno']] = $err;
            }

        } else {
            return $type . ' Successful<br/>';
        }
    }

    public function loadConfigUpdates($configUpdateFile, Config_Lite $config) {

        if ($configUpdateFile == '') {
            return '';
        }

        if (file_exists($configUpdateFile) === FALSE) {
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

    public static function deleteConfigItems($configDeleteFile, Config_Lite $config) {

        if ($configDeleteFile == '') {
            return '';
        }

        if (file_exists($configDeleteFile) === FALSE) {
            return '';
        }

        $result = "";

        try {
            $cfdeletes = new Config_Lite($configDeleteFile);
        } catch (Config_Lite_Exception_Runtime $ex) {
            $result = $ex->getMessage();
            return $result;
        }

        foreach ($cfdeletes as $secName => $secArray) {

            foreach ($secArray as $itemName => $val) {

                // Only update if the target file has the section or item
                if ($config->has($secName, $itemName) === TRUE) {

                    $config->remove($secName, $itemName);
                    $result .= $secName . "." . $itemName . " deleted<br/>";
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

    public static function deleteBakFiles($directory, $oldExtension = 'bak') {

        $fit = new FilesystemIterator($directory, FilesystemIterator::UNIX_PATHS | FilesystemIterator::CURRENT_AS_FILEINFO);

        foreach ($fit as $fileinfo) {

            if ($fileinfo->isDir()) {

                self::deleteBakFiles($directory.$fileinfo->getFilename().DS, $oldExtension);

            } else {

                if ($fileinfo->getExtension() == $oldExtension) {
                    unlink($fileinfo->getRealPath());
                }
            }
        }
    }

    public static function deleteDirectory($directory) {

        $fit = new FilesystemIterator($directory, FilesystemIterator::UNIX_PATHS | FilesystemIterator::CURRENT_AS_FILEINFO);

        foreach ($fit as $fileinfo) {

            if ($fileinfo->isDir()) {

                self::deleteDirectory($directory.$fileinfo->getFilename().DS);
                unlink($fileinfo->getRealPath());

            } else {
                unlink($fileinfo->getRealPath());
            }
        }

        // Finally the top directory
        rmdir($directory);

    }

    protected function unzip($file, array $skipDirs, $rootDir = 'hhk', $oldExtension = 'bak') {

        $result = '';
        $this->results = array();

        $zip = zip_open($file);

        if (is_resource($zip)) {

            $colCounter = 0;
            $table = new HTMLTable();
            $tr = "";

            while (($entry = zip_read($zip)) !== FALSE) {


                if (strpos(zip_entry_name($entry), "/") !== FALSE) {

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
                        try {
                            $fileSize = file_put_contents($relDir . "/" . $file, zip_entry_read($entry, zip_entry_filesize($entry)));
                        } catch (Exception $ex) {
                            $this->results[] = array('error'=>"Unable to put file: $relDir/$file" . " Msg: " . $ex->getMessage(), 'errno'=> '', 'query'=> '' );
                            continue;
                        }

                        if ($colCounter >= 2) {
                            $table->addBodyTr($tr);
                            $colCounter = 0;
                            $tr = '';
                        }

                        if ($fileSize === false) {
                            $tr .= HTMLTable::makeTd("File not written: $relDir/$file");
                            $this->results[] = array('error'=>"Unable to write file: $relDir/$file", 'errno'=> '', 'query'=> '' );
                        } else {
                            $tr .= HTMLTable::makeTd($relDir . "/" . $file);
                        }

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
            return $msg[] = array('error'=>'Empty query file ', 'errno'=> '', 'query'=> $query );
        }

        $qParts = explode($splitAt, $query);

        foreach ($qParts as $q) {

            $q = trim($q);
            if ($q == '' || $q == $delimiter || $q == 'DELIMITER') {
                continue;
            }

            try {
                if ($dbh->exec($q) === FALSE) {
                    $msg[] = array('error'=>$dbh->errorInfo(), 'errno'=> $dbh->errorCode(), 'query'=> $q );
                }
            } catch (PDOException $pex) {
                // do nothing
            }
        }

        return $msg;
    }


}

