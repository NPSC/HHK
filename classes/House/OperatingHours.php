<?php

namespace HHK\House;
use HHK\Exception\ValidationException;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Session;

/**
 * OperatingHours.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class OperatingHours {

    protected $dbh;
    protected $currentHours = [];
    protected $closedDays = [];
    protected $nonCleaningDays = [];

    public function __construct(\PDO $dbh){
        $this->dbh = $dbh;
        $this->currentHours = $this->loadCurrentHours();
    }

    /**
     * Check if the house was or is scheduled to be closed on the date specified
     *
     * @param \DateTimeInterface $date
     * @return bool
     */
    public function isHouseClosed(\DateTimeInterface $date){
        $dow = $date->format('w');

        // EKC 9/18/2023 Check for null.
        if ( ! is_string($this->currentHours[$dow]["Start_Date"]) || $this->currentHours[$dow]["Start_Date"] == '') {
            return false;  // bypass operating hours.
        }

        $startDate = new \DateTime($this->currentHours[$dow]["Start_Date"]);

        if($date >= $startDate){ //if date falls within current hours
            return ($this->currentHours[$dow]["Closed"] == 1);
        }else{ //find active hours of $date
            $stmt = $this->dbh->prepare("select * from operating_schedules where Day = :dow and DATE(Start_Date) <= DATE(:sdate) and DATE(End_Date) >= DATE(:edate) order by idDay desc limit 1");
            $stmt->execute([
                ":dow"=>$dow,
                ":sdate"=> $date->format('Y-m-d'),
                ":edate"=> $date->format('Y-m-d')
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if(count($rows) == 1 ){
                return ($rows[0]["Closed"] == 1);
            }else{
                return false;
            }
        }

    }

    /**
     * Get an array of dates where the house is closed in the date range specified.
     *
     * @param \DateTimeInterface $stDT
     * @param \DateTimeInterface $enDT
     * @return array<\DateTimeImmutable>
     */
    public function getClosedDatesInRange(\DateTimeInterface $stDT, \DateTimeInterface $enDT){

        $dayInterval = new \DateInterval("P1D");
        $curDT = new \DateTimeImmutable($stDT->format("Y-m-d H:i:s"));
        $closedDates = [];

        while ($curDT <= $enDT){
            if($this->isHouseClosed($curDT)){
                $closedDates[] = $curDT;
            }
            $curDT = $curDT->add($dayInterval);
        }

        return $closedDates;
    }

    public function isNonCleaningDay(\DateTimeInterface $date){
        $dow = $date->format('w');
        $startDate = new \DateTime($this->currentHours[$dow]["Start_Date"]);

        if($date >= $startDate){ //if date falls within current hours
            return ($this->currentHours[$dow]["Non_Cleaning"] == 1);
        }else{ //find active hours of $date
            $stmt = $this->dbh->prepare("select * from operating_schedules where Day = :dow and DATE(Start_Date) <= DATE(:sdate) and DATE(End_Date) >= DATE(:edate) order by idDay desc limit 1");
            $stmt->execute([
                ":dow"=>$dow,
                ":sdate"=> $date->format('Y-m-d'),
                ":edate"=> $date->format('Y-m-d')
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if(count($rows) == 1 ){
                return ($rows[0]["Non_Cleaning"] == 1);
            }else{
                return false;
            }
        }

    }

    public function save(array $post){

        $uS = Session::getInstance();

        //delete old non_cleaning_days
        $stmt = $this->dbh->prepare("delete from gen_lookups where Table_Name = 'Non_Cleaning_Day'");
        $stmt->execute();

        for ($d = 0; $d < 7; $d++) {

            $postedDay = filter_var_array($post['wd'][$d],
            array(
                'Open_At' => array('filter'=>FILTER_VALIDATE_REGEXP, 'options'=>array('regexp'=>"/^((2[0-3]|[01]?[0-9]):([0-5]?[0-9])(:([0-5]?[0-9]))?)?$/")),
                'Closed_At' => array('filter'=>FILTER_VALIDATE_REGEXP, 'options'=>array('regexp'=>"/^((2[0-3]|[01]?[0-9]):([0-5]?[0-9])(:([0-5]?[0-9]))?)?$/")),
                'Non_Cleaning' => FILTER_VALIDATE_BOOL,
                'Closed' =>FILTER_VALIDATE_BOOL
            ));

            if($postedDay['Closed']){
                $postedDay['Open_At'] = '';
                $postedDay['Closed_At'] = '';
            }else if($postedDay["Open_At"] != '' && $postedDay["Closed_At"] != ''){
                $openAt = new \DateTime($postedDay['Open_At']);
                $closeAt = new \DateTime($postedDay["Closed_At"]);
                if($openAt >= $closeAt){
                    throw new ValidationException("Open Time cannot be greater than or equal to Close Time");
                }
            }else{
                throw new ValidationException("Open and Close times are required when house is open (Closed All Day is unchecked).");
            }

            $found = false;
            $changed = false;

            foreach($this->currentHours as $k=>$day){
                if($k == $d){
                    $found = true;

                    //has anything changed?
                    if(
                        $day["Open_At"] != $postedDay["Open_At"] ||
                        $day["Closed_At"] != $postedDay["Closed_At"] ||
                        $day["Non_Cleaning"] != ($postedDay["Non_Cleaning"] ? 1:0) ||
                        $day["Closed"] != ($postedDay["Closed"] ? 1:0)
                    ){
                        $changed = true;
                        $stmt = $this->dbh->prepare("update `operating_schedules` set `End_Date` = :end where `idDay` = " . $day["idDay"] . ";");
                        $stmt->execute([":end"=>(new \DateTime())->format("Y-m-d H:i:s")]);
                    }

                }
            }

            if($found == false || $changed == true){
                //insert new hours
                $stmt = $this->dbh->prepare("INSERT INTO `operating_schedules` (`Day`, `Start_Date`, `End_Date`, `Open_At`, `Closed_At`, `Non_Cleaning`, `Closed`, `Updated_By`) VALUES(:day, :start, :end, :openAt, :closedAt, :nonCleaning, :closed, :updatedby)");
                $stmt->execute([
                    ":day"=>$d,
                    ":start"=>(new \DateTime())->format("Y-m-d H:i:s"),
                    ":end"=>null,
                    ":openAt"=>($postedDay["Open_At"] == '' ? null : $postedDay["Open_At"]),
                    ":closedAt"=>($postedDay["Closed_At"] == '' ? null : $postedDay["Closed_At"]),
                    ":nonCleaning"=>($postedDay["Non_Cleaning"] ? 1:0),
                    ":closed"=>($postedDay["Closed"] ? 1:0),
                    ":updatedby"=>$uS->username
                ]);
            }
        }
        $this->loadCurrentHours();
        return "Operating Hours saved successfully";
    }

    public function getEditMarkup(){
        $wdNames = array('Sun','Mon','Tue','Wed','Thr','Fri','Sat');

        $wdTbl = new HTMLTable();
        $wdTbl->addHeaderTr(HTMLTable::makeTh('Weekday').HTMLTable::makeTh("Open Time").HTMLTable::makeTh("Close Time").HTMLTable::makeTh('Non-Cleaning').HTMLTable::makeTh("Closed All Day"));

        $wdAttrs = array();

        foreach ($wdNames as $k => $d) {

            $wdAttrs[$k]['Open_At'] = array('name'=>'wd['.$k.'][Open_At]', 'type'=>'time');
            $wdAttrs[$k]['Closed_At'] = array('name'=>'wd['.$k.'][Closed_At]', 'type'=>'time');
            $wdAttrs[$k]["Non_Cleaning"] = array('name'=>'wd[' . $k . '][Non_Cleaning]', 'type'=>'checkbox');
            $wdAttrs[$k]['Closed'] = array('name'=>'wd['.$k.'][Closed]', 'type'=>'checkbox');



                foreach($this->currentHours as $day){
                    if($k == $day["Day"]){
                        $wdAttrs[$k]['Open_At']['value'] = $day['Open_At'];
                        $wdAttrs[$k]['Closed_At']['value'] = $day['Closed_At'];
                        if($day['Non_Cleaning']){
                            $wdAttrs[$k]['Non_Cleaning']['checked'] = 'checked';
                        }else{
                            unset($wdAttrs[$k]['Non-Cleaning']['checked']);
                        }
                        if($day['Closed']){
                            $wdAttrs[$k]['Closed']['checked'] = 'checked';
                        }else{
                            unset($wdAttrs[$k]['Closed']['checked']);
                        }
                    }
                }

            $wdTbl->addBodyTr(
                HTMLTable::makeTd($d, array('style'=>'text-align:right;')) .
                HTMLTable::makeTd(HTMLInput::generateMarkup('', $wdAttrs[$k]['Open_At'])) .
                HTMLTable::makeTd(HTMLInput::generateMarkup('', $wdAttrs[$k]['Closed_At'])) .
                HTMLTable::makeTd(HTMLInput::generateMarkup('', $wdAttrs[$k]['Non_Cleaning']), array('style'=>'text-align:center;')) .
                HTMLTable::makeTd(HTMLInput::generateMarkup('', $wdAttrs[$k]['Closed']), array('style'=>'text-align: center;'))
            );
        }
        return HTMLContainer::generateMarkup('h3', 'House Operating Hours', array('style'=>'margin-top:12px;')) . $wdTbl->generateMarkup();
    }

    private function loadCurrentHours(){
        $stmt = $this->dbh->query("select * from vcurrent_operating_hours");
        $currentHours = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach($currentHours as $day){
            if($day["Closed"] == 1){
                $this->closedDays[] = $day["Day"];
            }
            if($day["Non_Cleaning"] == 1){
                $this->nonCleaningDays[] = $day["Day"];
            }
        }
        return $currentHours;
    }

    public function getCurrentHours(){
        return $this->currentHours;
    }

    public function getClosedDays(){
        return $this->closedDays;
    }

    public function getNonCleaningDays(){
        return $this->nonCleaningDays;
    }

}
?>