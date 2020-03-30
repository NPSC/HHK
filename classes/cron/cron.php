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

    public $success;
    public $response;
    
    function __construct() {
        
    }
    
    function action(){
        
    }
    
    function run(){
        try{
            self::action();
            $this->success = true;
        }catch(\Exception $e){
            $this->success = false;
            $this->response = $e->getMessage();
        }
        return $this;
    }

}