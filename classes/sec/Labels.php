<?php
namespace HHK\sec;

use HHK\Common;


/**
 * Labels.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Loads labels from the database and provides a static method to retrieve them.
 */

class Labels {
    
    /**
     *
     * Use this function in place of the Labels constructor.
     *
     * @return \HHK\sec\Labels
     */
    
    public static function getLabels(): Labels{
        
         return new Labels();
    }

    
    public static function initLabels(\PDO $dbh): array{

        $labels = [];
        // get labels form DB
        $rows = $dbh->query("select l.`Key`, l.`Value`, g.`Description` as `Cat` from `labels` l left join gen_lookups g on l.Category = g.Code and g.Table_Name = 'labels_category' order by g.`Order`, l.`Key`")->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach($rows as $row){
            $labels[$row['Cat']][$row['Key']] = $row['Value'];
        }

        return $labels;

    }

    public static function getString(string $sec, string $key, ?string $default = null): string{
        
        $uS = Session::getInstance();
        
        if(isset($uS->labels) === FALSE || count($uS->labels) < 1){
        	$dbh = Common::initPDO(TRUE);
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