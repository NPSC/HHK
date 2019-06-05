<?php

/*
 * The MIT License
 *
 * Copyright 2018 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Description of UpdateSite
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK

 */


require CLASSES . 'Markdownify/Parser.php'; 
require CLASSES . 'Markdownify/Converter.php';

class UpdateSite {

    protected $errorMsg;
    protected $resultAccumulator;

    public function doUpdate(\PDO $dbh) {

        $config = new Config_Lite(ciCFG_FILE);
        $labels = new Config_Lite(LABEL_FILE);
        $errorCount = 0;
        $this->errorMsg = '';
        $this->resultAccumulator = '';

        // Log attempt.
        $logText = "Attempt Update.";
        SiteLog::logPatch($dbh, $logText, $config->getString('code', 'GIT_Id', ''));

        try {
            // Update system
            $patch = new Patch();

            // Update config file
//            $this->resultAccumulator .= $patch->loadConfigUpdates('../patch/patchSite.cfg', $config);
//            $this->resultAccumulator .= $patch->deleteConfigItems('../patch/deleteSiteItems.cfg', $config);

            // Update labels file
            $this->resultAccumulator .= $patch->loadConfigUpdates('../patch/patchLabel.cfg', $labels);
            $this->resultAccumulator .= $patch->deleteConfigItems('../patch/deleteLabelItems.cfg', $labels);


            // Update Tables
            $this->resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllTables.sql', "Tables");

            foreach ($patch->results as $err) {

                if ($err['errno'] == 1091 || $err['errno'] == 1061) {  // key not exist, Duplicate Key name
                    continue;
                }

                $this->errorMsg .= 'Create Table Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
            }


            // Update SPs
            $this->resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllRoutines.sql', 'Stored Procedures', '$$', '-- ;');

            foreach ($patch->results as $err) {
                $this->errorMsg .= 'Update Stored Procedures Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
            }


            // Run SQL patches
            if (file_exists('../patch/patchSQL.sql')) {

                $this->resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../patch/patchSQL.sql', "Updates");

                foreach ($patch->results as $err) {

                    if ($err['errno'] == 1062 || $err['errno'] == 1060) {
                        continue;
                    }

                    $this->errorMsg .= 'Update Patch SQL Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                    $errorCount++;
                }
            }

            // Convert any old agreement or confirmation files.
            //$this->resultAccumulator .= ConvertTxtFiles::doMarkdownify($dbh);


            // Update views
            if ($errorCount < 1) {

                $this->resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllViews.sql', 'Views');

                foreach ($patch->results as $err) {

                    $this->errorMsg .= 'Update Views Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                }
            } else {

                $this->errorMsg .= '**Views not updated**  ';
            }


            // Log update.
            $logText = "Loaded Update.  " . $this->errorMsg;
            SiteLog::logPatch($dbh, $logText, $config->getString('code', 'GIT_Id', ''));


        } catch (Exception $hex) {

            $this->errorMsg .= '***' . $hex->getMessage();
            // Log failure.
            $logText = "Failed Update.". $this->errorMsg;
            SiteLog::logPatch($dbh, $logText, $config->getString('code', 'GIT_Id', ''));

        }

    }

    public function getErrorMsg() {
        return $this->errorMsg;
    }

    public function getResultAccumulator() {
        return $this->resultAccumulator;
    }


}
