<?php
/**
 * cron.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class cron {
    
    public $title;
    public $success;
    public $response;
    protected $dbh;
    
    function __construct($dbh) {
        $this->dbh = $dbh;
    }
    
    function action(){
        
    }
    
    function run(){
        try{
            $this->response = $this->action();
            $this->success = true;
        }catch(\Exception $e){
            $this->success = false;
            $this->response = $e->getMessage();
        }
        
        if($this->success){
            SiteLog::writeLog($this->dbh, "Cron", $this->title . " succeeded: ".$this->response, '');
        }else{
            SiteLog::writeLog($this->dbh, "Cron", $this->title . " Failed: " . $this->response , '');
        }
        
        
        return $this;
    }

}