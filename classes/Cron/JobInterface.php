<?php
namespace HHK\Cron;

/**
 * JobInterface.php
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

Interface JobInterface {

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
     * @property array $paramTemplate
     */


    /**
     * Define the job tasks here
     *
     * Check $dryRun to determine if tasks should actually be completed (for testing/debugging)
     * Be sure to throw exceptions on failure and set $logMsg on success
     *
     */
    function tasks():void;

    function getParamEditMkup():string;
}
?>