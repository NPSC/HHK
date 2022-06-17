<?php

namespace HHK\Cron;

/**
 * FakeJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of JobFactory
 *
 * @author Will Ireland
 */

class JobFactory {

    /**
     * @param \PDO $dbh
     * @param int $idJob
     * @param bool $dryRun
     * @return \HHK\Cron\JobInterface
     */
    public static function make(\PDO $dbh, int $idJob, bool $dryRun = false):JobInterface {
        $stmt = $dbh->prepare("select * from cronjobs where idJob = :idJob");
        $stmt->execute([":idJob"=>$idJob]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if(isset($row['Code'])){
            try{
                $class = '\HHK\Cron\\' . $row['Code'];
                return new $class($dbh, $idJob, $dryRun);
            }catch(\Exception $e){
                return new EmptyJob($dbh, $idJob);
            }
        }else{
            return new EmptyJob($dbh, $idJob);
        }
    }
}

?>