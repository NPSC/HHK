<?php

namespace HHK\Cron;


use HHK\House\Report\GuestVehicleReport;
use HHK\Exception\RuntimeException;
use HHK\sec\Session;

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

    const AVAILABLE_REPORTS = array("vehicles");
    const REQUIRED_PARAMS = array("report", "emailAddress", "inputSet");

    public function tasks(): void{
        $uS = Session::getInstance();
        $emailAddress = (isset($this->params['emailAddress']) ? $this->params['emailAddress'] : '');
        $result = [];

        if(isset($this->params["report"]) && in_array($this->params["report"], EmailReportJob::AVAILABLE_REPORTS)){
            switch($this->params["report"]){
                case 'vehicles':
                    $guestVehiclesReport = new GuestVehicleReport($this->dbh);

                    $subject = $uS->siteName . " Vehicle Report";

                    $result = $guestVehiclesReport->sendEmail("vehicles", $subject, $emailAddress, $this->dryRun);
                    break;
                default:
                    $result['error'] = $this->params["report"] . " is not a valid report option";
            }
        }

        if(isset($result['success'])){
            $this->logMsg = $result['success'];
        }elseif (isset($result['error'])){
            throw new RuntimeException($result['error']);
        }
    }
}
?>