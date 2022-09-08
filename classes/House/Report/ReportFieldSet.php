<?php
namespace HHK\House\Report;

use HHK\sec\SecurityComponent;
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

    /**
     * Checks if current user can modify/delete field set
     *
     * @param \PDO $dbh
     * @param int $idFieldSet
     * @return boolean
     */
    private static function isAuthorized(\PDO $dbh, int $idFieldSet){

        $uS = Session::getInstance();
        $uname = $uS->username;
        $admin = SecurityComponent::is_Admin();
        if($idFieldSet > 0){
            //check if current user is authorized
            $query = "SELECT COUNT(`idFieldSet`) as `count` FROM `report_field_sets` where `idFieldSet` = '" . $idFieldSet . "' and (`Created_by` = '" . $uname ."'";
            if($admin){
                $query .= " or `global` = TRUE";
            }
            $query .= ")";
            $stmt = $dbh->query($query);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if($row['count'] > 0){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public static function listFieldSets(\PDO $dbh, string $report, bool $returnSelectorArray = false){
        $uS = Session::getInstance();
        $uname = $uS->username;

        if ($report == '') {
            return array('error' => 'Empty report name.');
        }

        $query = "SELECT `idFieldSet`, `Title`, `Global`, IF(`Global`, 'House sets', 'Personal sets') as `optionGroup` FROM `report_field_sets` WHERE `Report` = :report AND (`Created_by` = :createdBy OR `Global` = TRUE)";
        $stmt = $dbh->prepare($query);
        $stmt->execute([":report"=>$report, ":createdBy"=>$uname]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if($returnSelectorArray){
            $selectorArray = [];
            foreach($rows as $fieldSet){
                $selectorArray[] = [$fieldSet['idFieldSet'], $fieldSet['Title'], $fieldSet['optionGroup']];
            }

            return $selectorArray;
        }else{
            return $rows;
        }
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

            return ['success' => 'success', 'fieldSet'=>$row, 'canEdit'=>self::isAuthorized($dbh, $idFieldSet)];
        }else{
            return false;
        }

    }

    public static function createFieldSet(\PDO $dbh, string $report, string $title, array $fields = [], $global = FALSE){

        $uS = Session::getInstance();
        $uname = $uS->username;
        $admin = SecurityComponent::is_Admin();

        if (count($fields) ==  0) {
            return array('error' => 'Empty fields.');
        }

        if(!$title){
            return array('error' => 'Title field is required.');
        }

        $fieldsJSON = json_encode($fields);

        if(($global && $admin) || !$global){ //only admin users can create global fieldsets

            try{
                $query = "INSERT INTO report_field_sets (`Title`, `Report`, `Fields`, `Global`, `Created_by`) VALUES(:title, :report, :fields, :global, :createdBy);";
                $stmt = $dbh->prepare($query);
                $success = $stmt->execute([":title"=>$title, ":report"=>$report, ":fields"=>$fieldsJSON, ":global"=>$global, ":createdBy"=>$uname]);
                $idFieldSet = $dbh->lastInsertId();
            }catch(\PDOException $e){
                if($e->errorInfo[1] == '1062'){
                    return ['error'=>"Field set '" . $title . "' already exists."];
                }else{
                    return ['error'=>$e->getMessage()];
                }
            }

            if($success){
                if($global){
                    $optGroup = "House sets";
                }else{
                    $optGroup = "Personal sets";
                }
                return ['success'=>"Field set created successfully", 'fieldSet'=>['idFieldSet'=>$idFieldSet, 'title'=>$title, 'Fields'=>$fields, 'global'=>$global, 'optGroup'=>$optGroup]];
            }else{
                return ['error'=>"Could not create field set"];
            }
        }else{
            return ['error'=>"Not authorized to create field set"];
        }

    }

    public static function updateFieldSet(\PDO $dbh, int $idFieldSet, string $title, $fields = []){
        if(self::isAuthorized($dbh, $idFieldSet)){ //authorized
            $uS = Session::getInstance();
            $uname = $uS->username;

            if (!is_array($fields) || count($fields) ==  0) {
                return array('error' => 'Please choose at least one field');
            }

            $fieldsJSON = json_encode($fields);

            $query = "UPDATE `report_field_sets` SET `Title` = :title, `Fields` = :fields, `Updated_by` = :updatedBy where `idFieldSet` = :idFieldSet";
            $stmt = $dbh->prepare($query);
            $success = $stmt->execute([":title"=>$title, ":fields"=>$fieldsJSON, ":updatedBy"=>$uname, ":idFieldSet"=>$idFieldSet]);

            if($success){
                return ['success'=>"Field set updated successfully", 'fieldSet'=>['idFieldSet'=>$idFieldSet, 'title'=>$title]];
            }else{
                return ['error'=>"Could not update field set"];
            }
        }else{
            return ['error'=>"Not authorized to update field set"];
        }
    }

    public static function deleteFieldSet(\PDO $dbh, int $idFieldSet){

        if(self::isAuthorized($dbh, $idFieldSet)){ //authorized
            $stmt  = $dbh->prepare("DELETE FROM `report_field_sets` where `idFieldSet` = :idFieldSet");
            $success = $stmt->execute([":idFieldSet"=>$idFieldSet]);
            if($success){
                return ['success'=>'Field set deleted successfully', 'idFieldSet'=>$idFieldSet];
            }else{
                return ['error'=>'Could not delete field set'];
            }
        }else{
            return ['error'=>"Not authorized to delete this field set"];
        }

    }
}
?>