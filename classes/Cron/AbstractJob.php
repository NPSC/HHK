<?php
namespace HHK\Cron;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLSelector;

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
    const AllowedIntervals = array("hourly", "daily","weekly", "monthly");

    /**
     * Put any log message here
     *
     * @var string
     */
    public string $logMsg = '';

    protected \PDO $dbh;
    public int $idJob;
    public array $params;
    public string $status;
    protected bool $dryRun;

    /**
     * Build your parameter template here for editing in the Job Scheduler
     *
     * Use this format:
     * array(
     *  "<key>"=>[
     *      "label"=>"<label>",
     *      "type"=>"<fieldType (string, email, select)>",
     *      "values"=>"<values formatted for HTMLSelector::doOptionsMkup()>",
     *      "required"=>bool
     *  ],
     *  ...
     * )
     *
     * @var array
     */
    public array $paramTemplate;

    /**
     * @param \PDO $dbh
     * @param int $idJob
     * @param array $params
     * @param bool $dryRun
     */
    public function __construct(\PDO $dbh, int $idJob, array $params = [], bool $dryRun = false){
        $this->dbh = $dbh;
        $this->idJob = $idJob;
        $this->params = $params;
        $this->dryRun = $dryRun;
        $this->status = "notDue";
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

    public function getParamEditMkup():string{
        $tbl = new HTMLTable();

        foreach($this->paramTemplate as $name=>$attrs){
            $val = (isset($this->params[$name]) ? $this->params[$name] : "");
            $required = (isset($attrs['required']) && $attrs['required'] ? "required" : "");

            switch($attrs['type']){
                case "string":
                    $input = HTMLInput::generateMarkup("", array("type"=>"text","value"=>$val, "class"=>"editParam", "data-name"=>$name, "required"=>$required));
                    break;
                case "email":
                    $input = HTMLInput::generateMarkup("", array("type"=>"email", "value"=>$val, "class"=>"editParam", "data-name"=>$name, "required"=>$required));
                    break;
                case "select":
                    $input = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($attrs['values'], $val), array("style"=>"width: 100%", "class"=>"editParam", "data-name"=>$name, "required"=>$required));
                    break;
                default:
                    $input = "";
            }
            $tbl->addBodyTr(HTMLTable::makeTd($attrs['label']) . HTMLTable::makeTd($input));
        }
        return $tbl->generateMarkup();
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
                ':LogText'=>($this->dryRun ? "<strong>Dry Run: </strong>" : '') . substr($this->logMsg, 0,229), // 229 character max length (255 including dry run text)
                ':Status'=>($success ? AbstractJob::SUCCESS:AbstractJob::FAIL)
            ]);

        //Set last successful run time
        if($success == AbstractJob::SUCCESS && $this->dryRun == FALSE){
            $now = new \DateTime();
            $stmt = $this->dbh->prepare('UPDATE `cronjobs` SET `LastRun` = :lastRun where `idJob` = :idJob');
            $stmt->execute([
                ':idJob'=>$this->idJob,
                ':lastRun'=>$now->format("Y-m-d H:i:s")
            ]);
        }
    }

}
?>