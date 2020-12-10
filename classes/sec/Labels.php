<?php
namespace HHK\sec;
use HHK\Config_Lite\Config_Lite;

/**
 * Labels.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Labels Class
 *
 *
 */

class Labels {
    
    /**
     *
     * Use this function in place of the Labels constructor.
     *
     * @return \HHK\sec\Labels
     */
    
    public static function getLabels(){
        $uS = Session::getInstance();
        $labels = new Labels();
        
        if(isset($uS->labels) && count($uS->labels) > 0){
            //continue
        }else{
            $dbh = initPDO(TRUE);
            $labels->initLabels($dbh);
        }
        
        return $labels;
    }
    
    
    public static function initLabels(\PDO $dbh){
        $uS = Session::getInstance();
        $labels = [];
        try{
            // get labels form DB
            $rows = $dbh->query("select l.`Key`, l.`Value`, g.`Description` as `Cat` from `labels` l left join gen_lookups g on l.Category = g.Code and g.Table_Name = 'labels_category' order by g.`Order`, l.`Key`")->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach($rows as $row){
                $labels[$row['Cat']][$row['Key']] = $row['Value'];
            }
        }catch(\Exception $e){
            //skip to Config_lite
        }
            
        try{
            $cLiteLabels = new Config_Lite(LABEL_FILE);
            foreach($cLiteLabels as $section=>$name){
                foreach ($name as $key => $val) {
                    if(!isset($labels[$section][$key])){
                        $labels[ucfirst($section)][$key] = $val;
                    }
                }
            }
        }catch(\Exception $e){
            
        }
        
        $uS->labels = $labels;
    }
    
    public function getString($sec, $key, $default = null){
        
        $uS = Session::getInstance();
        $sec = ucfirst($sec);
        
        if(isset($uS->labels[$sec][$key])){
            return $uS->labels[$sec][$key];
        }elseif ($default){
            return $default;
        }else{
            return "Label '" . $key . "' not found";
        }
    }
    
}
?>