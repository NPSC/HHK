<?php
namespace HHK\House\Report;

use HHK\sec\Session;

/**
 * ReportFieldSet.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ReportFieldSet {
    
    public static function listFieldSets(\PDO $dbh, string $report){
        $uS = Session::getInstance();
        $uname = $uS->username;
        
        if ($report == '') {
            return array('error' => 'Empty report name.');
        }
        
        $query = "SELECT `idFieldSet`, `Title`, `Global` FROM `report_field_sets` WHERE `Report` = :report AND (`Created_by` = :createdBy OR `Global` = TRUE)";
        $stmt = $dbh->prepare($query);
        $stmt->execute([":report"=>$report, ":createdBy"=>$uname]);
        
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        
        return $rows;
    }
    
    public static function getFieldSet(\PDO $dbh, int $idFieldSet){
        
        $uS = Session::getInstance();
        $uname = $uS->username;
        
        if ($idFieldSet == '') {
            return array('error' => 'Empty idFieldSet.');
        }
        
        $query = "SELECT `idFieldSet`, `Title`, `Fields` FROM `report_field_sets` WHERE `idFieldSet` = :idFieldSet AND (`Created_by` = :createdBy OR `Global` = TRUE)";
        $stmt = $dbh->prepare($query);
        $stmt->execute([":idFieldSet"=>$idFieldSet, ":createdBy"=>$uname]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if($row){
            $row["Fields"] = json_decode($row["Fields"], TRUE);
            
            return $row;
        }else{
            return false;
        }
        
    }
    
    public static function createFieldSet(\PDO $dbh, string $report, string $title, array $fields = [], string $global){
        
        $uS = Session::getInstance();
        $uname = $uS->username;
        
        if (count($fields) ==  0) {
            return array('error' => 'Empty fields.');
        }
        
        $fieldsJSON = json_encode($fields);
        
        $query = "INSERT INTO report_field_sets (`Title`, `Report`, `Fields`, `Global`, `Created_by`) VALUES(:title, :report, :fields, :global, :createdBy);";
        $stmt = $dbh->prepare($query);
        $success = $stmt->execute([":title"=>$title, ":report"=>$report, ":fields"=>$fieldsJSON, ":global"=>$global, ":createdBy"=>$uname]);
        
        if($success){
            return ['status'=>"success"];
        }else{
            return ['error'=>"Could not create field set"];
        }
        
    }
}
?>