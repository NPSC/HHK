<?php
namespace HHK\Cron;

/**
 * AbstractJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of AbstractJob
 *
 * @author Will Ireland
 */

abstract class AbstractJob implements JobInterface {

    const SUCCESS = 's';
    const FAIL = 'f';

    /**
     * Put any log message here
     *
     * @var string
     */
    public string $logMsg = '';

    protected \PDO $dbh;
    public int $idJob;
    public string $status;
    protected bool $dryRun;

    /**
     * @param \PDO $dbh
     * @param int $idJob
     * @param bool $dryRun
     */
    public function __construct(\PDO $dbh, int $idJob, bool $dryRun = false){
        $this->dbh = $dbh;
        $this->idJob = $idJob;
        $this->dryRun = $dryRun;
    }

    /**
     * Executes code defined in tasks() and logs results
     */
    public function run():void{
        try{
            $this->tasks();
            $this->log(true);
        }catch(\Exception $e){
            $this->logMsg = "Job Failed: " . $e->getMessage();
            $this->log(false);
        }
    }

    /**
     * Logs cron results
     *
     * @param bool $success
     */
    protected function log(bool $success = false){
        $this->status = ($success ? AbstractJob::SUCCESS:AbstractJob::FAIL);
        $stmt = $this->dbh->prepare('INSERT INTO `cron_log` (`idJob`, `Log_Text`, `Status`) VALUES (:idJob, :LogText, :Status)');
        $stmt->execute([
                ':idJob'=>$this->idJob,
                ':LogText'=>($this->dryRun ? "Dry Run: " : '') . $this->logMsg,
                ':Status'=>($success ? AbstractJob::SUCCESS:AbstractJob::FAIL)
            ]);

        //Set last successful run time
        if($success == AbstractJob::SUCCESS){
            $now = new \DateTime();
            $stmt = $this->dbh->prepare('UPDATE `cronjobs` SET `LastRun` = :lastRun where `idJob` = :idJob');
            $stmt->execute([
                ':idJob'=>$this->idJob,
                ':lastRun'=>$now->format("Y-m-d h:i:s")
            ]);
        }
    }

}
?>