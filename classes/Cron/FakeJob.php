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
 * Description of FakeJob
 *
 * @author Will Ireland
 */

class FakeJob extends AbstractJob implements JobInterface {

    public function tasks():void{
        $this->logMsg = "FakeJob completed successfully";

        //throw new \ErrorException("FakeJob failed!");
    }
}

?>