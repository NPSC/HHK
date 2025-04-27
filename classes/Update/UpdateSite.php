<?php

namespace HHK\Update;

use HHK\SysConst\CodeVersion;


/**
 * Description of UpdateSite
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK

 */
class UpdateSite {

    protected $errorMsg;
    protected $resultAccumulator;

    public function doUpdate(\PDO $dbh) {

        $errorCount = 0;
        $this->errorMsg = '';
        $this->resultAccumulator = '';

        // Log attempt.
        $logText = "Attempt Update.";
        SiteLog::logPatch($dbh, $logText, CodeVersion::GIT_Id);

        try {
            // Update system
            $patch = new Patch();

            // Update Tables
            $this->resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllTables.sql', "Tables");

            foreach ($patch->results as $err) {

//                 if ($err['errno'] == 1091 || $err['errno'] == 1061) {  // key not exist, Duplicate Key name
//                     continue;
//                 }

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

//                     if ($err['errno'] == 1062 || $err['errno'] == 1060) {
//                         continue;
//                     }

                    $this->errorMsg .= 'Update Patch SQL Error: ' . $err['error'] . '; Query=' . $err['query'] . '<br/>**SQL not updated**<br>';
                    $errorCount++;
                }
            }


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
            SiteLog::logPatch($dbh, $logText, CodeVersion::GIT_Id);


        } catch (\Exception $hex) {

            $this->errorMsg .= '***' . $hex->getMessage();
            // Log failure.
            $logText = "Failed Update.". $this->errorMsg;
            SiteLog::logPatch($dbh, $logText, CodeVersion::GIT_Id);

        }

    }

    public function getErrorMsg() {
        return $this->errorMsg;
    }

    public function getResultAccumulator() {
        return $this->resultAccumulator;
    }

}
?>