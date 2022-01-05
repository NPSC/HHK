<?php
namespace HHK\sec;


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
        
         return new Labels();
    }

    
    public static function initLabels(\PDO $dbh){

        $labels = [];
        // get labels form DB
        $rows = $dbh->query("select l.`Key`, l.`Value`, g.`Description` as `Cat` from `labels` l left join gen_lookups g on l.Category = g.Code and g.Table_Name = 'labels_category' order by g.`Order`, l.`Key`")->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach($rows as $row){
            $labels[$row['Cat']][$row['Key']] = $row['Value'];
        }

        return $labels;

    }

    public static function getString($sec, $key, $default = null){
        
        $uS = Session::getInstance();
        
        if(isset($uS->labels) === FALSE || count($uS->labels) < 1){
        	$dbh = initPDO(TRUE);
        	$uS->labels = Labels::initLabels($dbh);
        }

        $sec = ucfirst($sec);
        
        if(isset($uS->labels[$sec][$key])){
            return $uS->labels[$sec][$key];
        }elseif (is_null($default) === FALSE){
            return $default;
        }else{
            return "Label '" . $key . "' not found";
        }
    }

}
?>