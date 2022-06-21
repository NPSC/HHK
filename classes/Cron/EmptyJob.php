<?php

namespace HHK\Cron;

use HHK\Exception\RuntimeException;

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

class EmptyJob extends AbstractJob implements JobInterface {

    public function tasks(): void {
        throw new RuntimeException("This is an empty job, make sure you set your Job Code correctly");
    }

}

?>