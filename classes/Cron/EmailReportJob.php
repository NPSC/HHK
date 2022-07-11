<?php

namespace HHK\Cron;


use HHK\House\Report\GuestVehicleReport;
use HHK\Exception\RuntimeException;
use HHK\sec\Session;
use HHK\House\Report\ReservationReport;

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

    const AVAILABLE_REPORTS = array("vehicles"=>["vehicles","Vehicles"],"reservation"=>["reservation","Reservations"]);

    public array $paramTemplate = [
        "report"=>[
            "label"=>"Report",
            "type"=>"select",
            "values"=>EmailReportJob::AVAILABLE_REPORTS,
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
        "inputSet"=>[
            "label"=>"Input Set",
            "type"=>"select",
            "values"=>[],
            "required"=>false
        ]
    ];

    public function tasks(): void{
        $uS = Session::getInstance();
        $emailAddress = (isset($this->params['emailAddress']) ? $this->params['emailAddress'] : '');
        $subject = (isset($this->params['subject']) ? $this->params['subject'] : '');
        $result = [];

        if(isset($this->params["report"]) && isset(EmailReportJob::AVAILABLE_REPORTS[$this->params["report"]])){

            switch($this->params["report"]){
                case 'vehicles':
                    $guestVehiclesReport = new GuestVehicleReport($this->dbh);

                    $subject = $uS->siteName . " Vehicle Report";

                    $result = $guestVehiclesReport->sendEmail("vehicles", $subject, $emailAddress, $this->dryRun);
                    break;

                case 'reservation':
                    $reservationsReport = new ReservationReport($this->dbh);
                    $result = $reservationsReport->sendEmail($emailAddress, $subject, $this->dryRun);
                    break;
                default:
                    $result['error'] = $this->params["report"] . " is not a valid report option";
            }

        }else{
            $result['error'] = $this->params["report"] . " is not a valid report option";
        }

        if(isset($result['success'])){
            $this->logMsg = $result['success'];
        }elseif (isset($result['error'])){
            throw new RuntimeException($result['error']);
        }
    }
}
?>