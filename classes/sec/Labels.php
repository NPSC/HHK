<?php
namespace HHK\sec;
use HHK\Exception\RuntimeException;
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
     * Use this function in place of the Labels constructor. If labels exist in the DB, a Labels object is constructed, otherwise, a Config_Lite object is returned.
     *
     * @return \HHK\Config_Lite\Config_Lite|\HHK\sec\Labels
     */
    
    public static function getLabels(){
        $uS = Session::getInstance();
        $labels = new Labels();
        
        if(isset($uS->labels) && count($uS->labels) > 0){
            return $labels;
        }else{
            try{
                $dbh = initPDO(FALSE);
                $labels->initLabels($dbh);
                return $labels;
            }catch(\Exception $e){
                return new Config_Lite(LABEL_FILE);
            }
        }
    }
    
    
    public static function initLabels(\PDO $dbh){
        if(!$dbh->query("select count(*) from `labels`")->fetchColumn() > 0){
            throw new RuntimeException("No Labels found");
        }else{
            // get labels form DB
            $uS = Session::getInstance();
            $labels = [];
            $rows = $dbh->query("select l.`Key`, l.`Value`, g.`Description` as `Cat` from `labels` l left join gen_lookups g on l.Category = g.Code and g.Table_Name = 'labels_category' order by g.`Order`, l.`Key`")->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach($rows as $row){
                $labels[$row['Cat']][$row['Key']] = $row['Value'];
            }
            
            $uS->labels = $labels;
        }
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