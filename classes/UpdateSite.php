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
            $this->resultAccumulator .= $patch->loadConfigUpdates('../patch/patchSite.cfg', $config);
            $this->resultAccumulator .= $patch->deleteConfigItems('../patch/deleteSiteItems.cfg', $config);

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

                    $this->errorMsg .= 'Update Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                    $errorCount++;
                }
            }
            
            // Run forms editor update
            $converter = new Markdownify\Converter;
            
            if (file_exists('../conf/agreement.txt')){
		        $stmt = $dbh->query("select idDocument from document where `Title` = 'Registration Document' and `Category` = 'form' and `Type` = 'md' and `Status` = 'a'");
				$rows = $stmt->fetchAll(PDO::FETCH_NUM);
	        
				if ($stmt->rowCount() == 0) { //if agreement document cannot be found
	            	$htmlcontent = file_get_contents("../conf/agreement.txt");
	            	$mdcontent = $converter->parseString($htmlcontent);
	            	$dbh->exec("INSERT INTO document (`Title`, `Abstract`, `Category`, `Type`, `Doc`, `Status`, `Last_Updated`, `Created_By`) VALUES ('Registration Document', '', 'form', 'md', " . $mdcontent . ", 'a', NOW(), 'admin'),");
	        	}
            }
            
            if (file_exists('../conf/confirmation.txt')){
	            $stmt = $dbh->query("select idDocument from document where `Title` = 'Confirmation Document' and `Category` = 'form' and `Type` = 'md' and `Status` = 'a'");
				$rows = $stmt->fetchAll(PDO::FETCH_NUM);
	        
				if ($stmt->rowCount() == 0) {//if confirmation document cannot be found
	            	$htmlcontent = file_get_contents("../conf/confirmation.txt");
	            	$mdcontent = $converter->parseString($htmlcontent);
	            	$dbh->exec("INSERT INTO document (`Title`, `Abstract`, `Category`, `Type`, `Doc`, `Status`, `Last_Updated`, `Created_By`) VALUES ('Registration Document', '', 'form', 'md', " . $mdcontent . ", 'a', NOW(), 'admin'),");
	        	}
            }
            
            if (file_exists('../conf/survey.txt')){
	            $stmt = $dbh->query("select idDocument from document where `Title` = 'Survey Document' and `Category` = 'form' and `Type` = 'md' and `Status` = 'a'");
				$rows = $stmt->fetchAll(PDO::FETCH_NUM);
	        
				if ($stmt->rowCount() == 0) {//if survey document cannot be found
	            	$htmlcontent = file_get_contents("../conf/survey.txt");
	            	$mdcontent = $converter->parseString($htmlcontent);
	            	$dbh->exec("INSERT INTO document (`Title`, `Abstract`, `Category`, `Type`, `Doc`, `Status`, `Last_Updated`, `Created_By`) VALUES ('Registration Document', '', 'form', 'md', " . $mdcontent . ", 'a', NOW(), 'admin'),");
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


            // Update pay types
//            $cnt = SiteConfig::updatePayTypes($dbh);
//            if ($cnt > 0) {
//                $this->resultAccumulator .= "Pay Types updated.  ";
//            }

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
