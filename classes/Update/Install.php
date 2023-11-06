<?php
namespace HHK\Update;

class Install{

    public function installDB(\PDO $dbh){
        try {

            $patch = new Patch();
    
            // Update Tables
            $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllTables.sql', "Tables");
            foreach ($patch->results as $err) {
                $errorMsg .= 'Create Table Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
            }
    
    
            $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllViews.sql', 'Views');
            foreach ($patch->results as $err) {
                $errorMsg .= 'Create View Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
            }
    
            $resultAccumulator .= $patch->updateWithSqlStmts($dbh, '../sql/CreateAllRoutines.sql', 'Stored Procedures', '$$', '-- ;');
            foreach ($patch->results as $err) {
                $errorMsg .= 'Create Stored Procedures Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
            }
    
            // Set web_sites table
            $adminDir = str_ireplace('/', '', 'admin');
            $houseDir = str_ireplace('/', '', 'house');
            $volDir = str_ireplace('/', '', 'volunteer');
    
    
            // Admin
            $dbh->exec("update web_sites set Relative_Address = '$adminDir/' where Site_Code = '" . WebSiteCode::Admin . "'");
    
            // House
            if ($houseDir != '') {
                $dbh->exec("update web_sites set Relative_Address = '$houseDir/' where Site_Code = '" . WebSiteCode::House . "'");
            } else {
                $dbh->exec("update web_sites set Relative_Address = '' where Site_Code = '" . WebSiteCode::House . "'");
            }
    
            // Volunteer
            if ($volDir != '') {
                $dbh->exec("update web_sites set Relative_Address = '$volDir/' where Site_Code = '" . WebSiteCode::Volunteer . "'");
            } else {
                $dbh->exec("update web_sites set Relative_Address = '' where Site_Code = '" . WebSiteCode::Volunteer . "'");
            }

            return $resultAccumulator;
    
    
        } catch (\Exception $hex) {
            $errorMsg .= '*** ' . $hex->getMessage();
        }

        return $errorMsg;
    }

}
?>