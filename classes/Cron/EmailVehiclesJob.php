<?php

namespace HHK\Cron;


use HHK\House\Report\GuestVehicleReport;
use HHK\Exception\RuntimeException;
use HHK\sec\Session;

/**
 * EmailVehiclesJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of EmailVehiclesJob
 *
 * @author Will Ireland
 */

class EmailVehiclesJob extends AbstractJob implements JobInterface{

    public function tasks(): void{
        $uS = Session::getInstance();

        $guestVehiclesReport = new GuestVehicleReport($this->dbh);

        $subject = $uS->siteName . " Vehicle Report";
        $emailAddress = ($uS->vehicleReportEmail ? $uS->vehicleReportEmail : "");

        $result = $guestVehiclesReport->sendEmail("vehicles", $subject, $emailAddress, $this->dryRun);

        if(isset($result['success'])){
            $this->logMsg = $result['success'];
        }elseif (isset($result['error'])){
            throw new RuntimeException($result['error']);
        }
    }
}
?>