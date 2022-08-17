<?php

namespace HHK\Cron;


use HHK\Exception\RuntimeException;
use HHK\House\Report\ReportInterface;
use HHK\House\Report\ReportFieldSet;

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

    const AVAILABLE_REPORTS = array("CurrentGuestReport"=>["CurrentGuestReport","Current Guests"], "VehiclesReport"=>["VehiclesReport","Vehicles"],"ReservationReport"=>["ReservationReport","Reservations"]);

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
        "fieldSet"=>[
            "label"=>"Field Set",
            "type"=>"select",
            "values"=>[],
            "required"=>false
        ]
    ];

    public function tasks(): void{
        $emailAddress = (isset($this->params['emailAddress']) ? $this->params['emailAddress'] : '');
        $subject = (isset($this->params['subject']) ? $this->params['subject'] : '');
        $result = [];

        $request = [];
        if(isset($this->params['fieldSet']) && $this->params['fieldSet'] != ''){
            $fields = $this->getFields($this->params['fieldSet']);
            if(is_array($fields)){
                $request['selFld'] = $fields;
            }
        }

        if(isset($this->params["report"]) && isset(EmailReportJob::AVAILABLE_REPORTS[$this->params["report"]])){
            try{
                $class = '\HHK\House\\Report\\' . $this->params["report"];
                $report = new $class($this->dbh, $request);
            }catch(\Exception $e){
                $result['error'] = $this->params["report"] . " is not a valid report option";
            }



            if($report instanceof ReportInterface){
                $result = $report->sendEmail($emailAddress, $subject, $this->dryRun);
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

    protected function getFields(int $idFieldSet = 0){
        $fieldSetResponse = ReportFieldSet::getFieldSet($this->dbh, intval($idFieldSet));
        return (isset($fieldSetResponse["fieldSet"]["Fields"]) ? $fieldSetResponse["fieldSet"]["Fields"]: false);
    }
}
?>