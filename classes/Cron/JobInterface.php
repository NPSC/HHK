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
     * Define the job tasks here
     *
     * Check $dryRun to determine if tasks should actually be completed (for testing/debugging)
     * Be sure to throw exceptions on failure and set $logMsg on success
     *
     */
    function tasks():void;
}
?>