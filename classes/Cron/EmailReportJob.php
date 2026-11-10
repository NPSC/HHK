<?php

namespace HHK\Cron;


use Composer\Autoload\ClassLoader;
use HHK\Exception\RuntimeException;
use HHK\House\Report\ReportFieldSet;
use HHK\House\Report\ReportInterface;

/**
 * EmailReportJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of EmailReportJob
 *
 * Sends an email of a specified report
 *
 * @author Will Ireland
 */

class EmailReportJob extends AbstractJob implements JobInterface{

    const REPORT_NAMESPACE = 'HHK\\House\\Report\\';

    public array $paramTemplate = [
        "report"=>[
            "label"=>"Report",
            "type"=>"select",
            "values"=>[],   // filled from reportOptions() in the constructor
            "required"=>true
        ],
        "emailAddress"=>[
            "label"=>"Email Address",
            "type" =>"email",
            "required"=>true
        ],
        "subject"=>[
            "label"=>"Subject",
            "type"=>"string",
            "required"=>true
        ],
        "fieldSet"=>[
            "label"=>"Field Set",
            "type"=>"select",
            "values"=>[],
            "required"=>false
        ],
        "filterOpts"=>[
            "label"=>"Filter Options",
            "type"=>"filterOpts",
            "required"=>false
        ]
    ];

    /**
     * @param \PDO $dbh
     * @param int $idJob
     * @param array $params
     * @param bool $dryRun
     */
    public function __construct(\PDO $dbh, int $idJob, array $params = [], bool $dryRun = false){
        parent::__construct($dbh, $idJob, $params, $dryRun);
        $this->paramTemplate["report"]["values"] = self::reportOptions();
    }

    /**
     * Report classes whose getEmailReportTitle() returns a title, found through the Composer classmap
     * (run `composer dump-autoload -o` after adding a report).  This list is the allow-list for the
     * user-supplied report name: only names in it are ever instantiated.
     *
     * @return array<string, class-string<ReportInterface>> short class name => fully qualified class name
     */
    public static function availableReports(): array {
        static $reports = null;

        if ($reports === null) {
            $reports = [];

            foreach (ClassLoader::getRegisteredLoaders() as $loader) {
                foreach ($loader->getClassMap() as $class => $file) {
                    // is_file() skips stale classmap entries, which would otherwise raise include warnings when autoloaded
                    if (str_starts_with($class, self::REPORT_NAMESPACE)
                        && is_file($file)
                        && is_subclass_of($class, ReportInterface::class)
                        && (new \ReflectionClass($class))->isInstantiable()
                        && $class::getEmailReportTitle() !== null) {
                        $reports[substr($class, strlen(self::REPORT_NAMESPACE))] = $class;
                    }
                }
            }

            ksort($reports);
        }

        return $reports;
    }

    /**
     * Report choices formatted for HTMLSelector::doOptionsMkup().  The short class name is the
     * stored job parameter value, so saved jobs keep working.
     *
     * @return array<string, array{0: string, 1: string}> short class name => [short class name, title]
     */
    public static function reportOptions(): array {
        $options = [];

        foreach (self::availableReports() as $name => $class) {
            $title = $class::getEmailReportTitle();
            if ($title !== null) {
                $options[$name] = [$name, $title];
            }
        }

        return $options;
    }

    /**
     * @param string $name short class name, as stored in the job's "report" parameter
     * @return bool
     */
    public static function isAvailableReport(string $name): bool {
        return isset(self::availableReports()[$name]);
    }

    /**
     * Instantiate an emailable report by its short class name.
     *
     * @param string $name short class name, as stored in the job's "report" parameter
     * @param \PDO $dbh
     * @param array $request filter options / request values passed to the report constructor
     * @return ReportInterface|null null if $name is not an emailable report
     */
    public static function makeReport(string $name, \PDO $dbh, array $request = []): ?ReportInterface {
        $class = self::availableReports()[$name] ?? null;

        return ($class === null ? null : new $class($dbh, $request));
    }

    public function tasks(): void{
        $emailAddress = (isset($this->params['emailAddress']) ? $this->params['emailAddress'] : '');
        $subject = (isset($this->params['subject']) ? $this->params['subject'] : '');
        $reportName = (isset($this->params['report']) ? (string) $this->params['report'] : '');

        $request = [];
        if(isset($this->params['fieldSet']) && $this->params['fieldSet'] != ''){
            $fields = $this->getFields(intval($this->params['fieldSet']));
            if(is_array($fields)){
                $request['selFld'] = $fields;
            }
        }

        if(isset($this->params['filterOpts']) && is_array($this->params['filterOpts'])){
            foreach($this->params['filterOpts'] as $k=>$v){
                $request[$k] = "on";
            }
        }

        $report = self::makeReport($reportName, $this->dbh, $request);

        if ($report === null) {
            throw new RuntimeException($reportName . " is not a valid report option");
        }

        $result = $report->sendEmail($this->dbh, $emailAddress, $subject, $this->dryRun);

        if(isset($result['success'])){
            $this->logMsg = $result['success'];
        }elseif (isset($result['error'])){
            throw new RuntimeException($result['error']);
        }
    }

    protected function getFields(int $idFieldSet = 0){
        $fieldSetResponse = ReportFieldSet::getFieldSet($this->dbh, $idFieldSet);
        return (isset($fieldSetResponse["fieldSet"]["Fields"]) ? $fieldSetResponse["fieldSet"]["Fields"]: false);
    }
}
