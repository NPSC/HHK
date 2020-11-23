<?php

namespace HHK\House\Hospital;

use HHK\TableLog\ReservationLog;
use HHK\Tables\EditRS;
use HHK\Tables\Registration\Hospital_StayRS;
use HHK\House\PSG;
use HHK\Tables\Reservation\ReservationRS;
use HHK\Tables\Visit\VisitRS;

/**
 * HospitalStay.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   UI_NCSA
 * @link      https://github.com/NPSC/HHK
 */
/**
 * Description of HospitalStay
 *
 * @author Eric
 */
 
class HospitalStay {
    
    protected $hstayRs;
    protected $idReservation = 0;
    protected $idVisit = 0;
    
    /**
     * @param \PDO $dbh
     * @param number $idPatient
     * @param number $idHospitalStay
     */
    function __construct(\PDO $dbh, $idPatient, $idHospitalStay = 0) {
        
        $hstay = new Hospital_StayRS();
        
        $idP = intval($idPatient);
        $idHs = intval($idHospitalStay);
        
        if ($idHs > 0) {
            
            $stmt = $dbh->query("Select * from hospital_stay where idHospital_stay=$idHs");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (count($rows) === 1) {
                EditRS::loadRow($rows[0], $hstay);
            }
            
        }else if ($idP > 0) {
        	
            //get hospital stay from most recent reservation
            $stmt = $dbh->query("
SELECT hs.*, r.`idReservation`,
	ifnull(r.`Actual_Arrival`, r.`Expected_Arrival`) as 'arrival',
    (case
		when r.`Status` = 's' then 10
        when r.`Status` in ('c', 'c1', 'c2', 'c3', 'c4', 'ns', 'td') then 0
        else 5
    end) as 'order'
from `hospital_stay` hs
JOIN `reservation` r on hs.`idHospital_stay` = r.`idHospital_stay`

where hs.`idPatient` = $idP
order by `order` desc, `arrival` desc limit 1");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (count($rows) === 1) {
                EditRS::loadRow($rows[0], $hstay);
                $this->idReservation = $rows[0]['idReservation'];
                
            }
            
        }
        
        $this->hstayRs = $hstay;
    }
    
    public function getAssocHospNames($hospitalnames) {
        
        $assocTxt = '';
        $hospitalnames[0] = array(0=>0, 1=>'');
        
        if (isset($hospitalnames[$this->getAssociationId()])) {
        
        	if ($hospitalnames[$this->getAssociationId()][1] != '' && $hospitalnames[$this->getAssociationId()][1] != '(None)') {
            	$assocTxt = $hospitalnames[$this->getAssociationId()][1] . '/';
        	}
        }
        
        return $assocTxt . (isset($hospitalnames[$this->getHospitalId()][1]) ? $hospitalnames[$this->getHospitalId()][1] : 'Undefined');
        
    }
    
    /**
     * @param \PDO $dbh
     * @param PSG $psg
     * @param int $idAgent
     * @param string $uname
     */
    public function save(\PDO $dbh, PSG $psg, $idAgent, $uname) {

        if (is_null($psg) || $psg->getIdPsg() == 0) {
            return;
        }

        $this->hstayRs->idPatient->setNewVal($psg->getIdPatient());
        $this->hstayRs->idPsg->setNewVal($psg->getIdPsg());
        $this->hstayRs->Status->setNewVal('a');
        $this->hstayRs->Updated_By->setNewVal($uname);
        $this->hstayRs->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

        $currentHsId = intval($this->hstayRs->idHospital_stay->getStoredVal(), 10);
        $numReservations = 0;

        // who else uses it
        if ($currentHsId > 0) {
	        $stmt = $dbh->query("select count(idReservation) from reservation where idHospital_Stay = ".$currentHsId);
	        $resvIds = $stmt->fetchAll(\PDO::FETCH_NUM);
	        $numReservations = $resvIds[0][0];
        }

        if ($currentHsId === 0 || ($numReservations > 1 && EditRS::isChanged($this->hstayRs))) {

            // Insert
        	$currentHsId = EditRS::insert($dbh, $this->hstayRs);

        	$this->hstayRs->idHospital_stay->setNewVal($currentHsId);

            $logText = ReservationLog::getInsertText($this->hstayRs);
            ReservationLog::logHospStay($dbh,
            	$currentHsId,
                $psg->getIdPatient(),
                $idAgent,
                $psg->getIdPsg(),
                $logText, 'insert', $uname);

        } else {

            //Update
            $updt = EditRS::update($dbh, $this->hstayRs, array($this->hstayRs->idHospital_stay));
            
            if ($updt == 1) {
                $logText = ReservationLog::getUpdateText($this->hstayRs);
                ReservationLog::logHospStay($dbh,
                    $this->hstayRs->idHospital_stay->getStoredVal(),
                    $psg->getIdPatient(),
                    $idAgent,
                    $psg->getIdPsg(),
                    $logText, 'update', $uname);
            }

        }

        EditRS::updateStoredVals($this->hstayRs);

        return $currentHsId;
    }
    
    private function saveLink(\PDO $dbh, $idIns){
    	
    	$flag = FALSE;
    	
            if($this->idReservation > 0){
            	
                $reservRS = new ReservationRS();
                $stmt = $dbh->query("select * from reservation where idReservation = $this->idReservation");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                if(count($rows) == 1){
                    EditRS::loadRow($rows[0], $reservRS);
                    $reservRS->idHospital_Stay->setNewVal($idIns);
                    $updt = EditRS::update($dbh, $reservRS, array($reservRS->idReservation));
                    if($updt > 0){
                        $flag = TRUE;
                    }
                }
            }
            
            if($this->idVisit > 0){
            	
                $visitRS = new VisitRS();
                $stmt = $dbh->query("select * from visit where idVisit = $this->idVisit");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                if(count($rows) == 1){
                	
                    EditRS::loadRow($rows[0], $visitRS);
                    $visitRS->idHospital_stay->setNewVal($idIns);
                    $updt = EditRS::update($dbh, $visitRS, array($visitRS->idVisit));
                    if($updt > 0){
                    	$flag = TRUE;
                    }
                }
            }
            
        return $flag;
    }
    
    
    public function getIdHospital_Stay() {
        return $this->hstayRs->idHospital_stay->getStoredVal();
    }
    
    public function getIdPatient() {
        return $this->hstayRs->idPatient->getStoredVal();
    }
    
    public function getIdPsg() {
        return $this->hstayRs->idPsg->getStoredVal();
    }
    
    public function setIdPsg($v) {
        $this->hstayRs->idPsg->setNewVal($v);
    }
    
    public function getAgentId() {
        return $this->hstayRs->idReferralAgent->getStoredVal();
    }
    
    public function setAgentId($id) {
        $this->hstayRs->idReferralAgent->setNewVal($id);
    }
    
    public function getHospitalId() {
        return $this->hstayRs->idHospital->getStoredVal();
    }
    
    public function setHospitalId($v) {
        $this->hstayRs->idHospital->setNewVal(intval($v, 10));
    }
    
    public function getAssociationId() {
        return $this->hstayRs->idAssociation->getStoredVal();
    }
    
    public function setAssociationId($v) {
        $this->hstayRs->idAssociation->setNewVal(intval($v, 10));
    }
    
    public function getDoctor() {
        return $this->hstayRs->Doctor->getStoredVal();
    }
    
    public function getDoctorId() {
        return $this->hstayRs->idDoctor->getStoredVal();
    }
    
    public function getReservationId(){
        return $this->idReservation;
    }
    
    public function setDoctorId($id) {
        $this->hstayRs->idDoctor->setNewVal($id);
    }
    
    public function setDoctor($v) {
        $this->hstayRs->Doctor->setNewVal($v);
    }
    
    public function getDiagnosis() {
        return $this->hstayRs->Diagnosis->getStoredVal();
    }
    
    public function setDiagnosis($v) {
        $this->hstayRs->Diagnosis->setNewVal($v);
    }
    
    public function getDiagnosisCode() {
        return $this->hstayRs->Diagnosis->getStoredVal();
    }
    
    public function setDiagnosisCode($v) {
        $this->hstayRs->Diagnosis->setNewVal($v);
    }
    
    public function getLocationCode() {
        return $this->hstayRs->Location->getStoredVal();
    }
    
    public function setLocationCode($v) {
        $this->hstayRs->Location->setNewVal($v);
    }
    
    public function getArrivalDate() {
        return $this->hstayRs->Arrival_Date->getStoredVal();
    }
    
    public function setArrivalDate($v) {
        $this->hstayRs->Arrival_Date->setNewVal($v);
    }
    
    public function getExpectedDepartureDate() {
        return $this->hstayRs->Expected_Departure->getStoredVal();
    }
    
    public function setExpectedDepartureDate($v) {
        $this->hstayRs->Expected_Departure->setNewVal($v);
    }
    
    public function getRoom() {
    	return $this->hstayRs->Room->getStoredVal();
    }
    
    public function setRoom($v) {
    	$this->hstayRs->Room->setNewVal($v);
    }
    public function getMrn() {
    	return $this->hstayRs->MRN->getStoredVal();
    }
    
    public function setMrn($v) {
    	$this->hstayRs->MRN->setNewVal($v);
    }
}
?>