<?php

namespace HHK\House\Hospital;

use HHK\TableLog\ReservationLog;
use HHK\Tables\EditRS;
use HHK\Tables\Registration\Hospital_StayRS;
use HHK\House\PSG;

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
    
    protected Hospital_StayRS $hstayRS;
    protected $idReservation = 0;
    
    /**
     * @param \PDO $dbh
     * @param int $idPatient
     * @param int $idHospitalStay
     */
    function __construct(\PDO $dbh, int $idPatient, int $idHospitalStay = 0) {

        $hstay = new Hospital_StayRS();

        if ($idHospitalStay > 0) {

            $stmt = $dbh->prepare("SELECT * FROM `hospital_stay` WHERE `idHospital_stay` = :idHs");
            $stmt->execute([':idHs' => $idHospitalStay]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (count($rows) === 1) {
                EditRS::loadRow($rows[0], $hstay);
            }
            
        }else if ($idPatient > 0) {
        	
            //get hospital stay from most recent reservation
            $stmt = $dbh->prepare("
SELECT `hs`.*, `r`.`idReservation`,
	IFNULL(`r`.`Actual_Arrival`, `r`.`Expected_Arrival`) AS 'arrival',
    (CASE
		WHEN `r`.`Status` = 's' THEN 10
        WHEN `r`.`Status` IN ('c', 'c1', 'c2', 'c3', 'c4', 'ns', 'td') THEN 0
        ELSE 5
    END) AS 'order'
FROM `hospital_stay` `hs`
LEFT JOIN `reservation` `r` ON `hs`.`idHospital_stay` = `r`.`idHospital_stay`

WHERE `hs`.`idPatient` = :idP
ORDER BY `order` DESC, `arrival` DESC LIMIT 1");
            $stmt->execute([':idP' => $idPatient]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (count($rows) === 1) {
                EditRS::loadRow($rows[0], $hstay);
                $this->idReservation = $rows[0]['idReservation'];
                
            }
            
        }
        
        $this->hstayRS = $hstay;
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
    public function save(\PDO $dbh, PSG $psg, $idAgent, $uname, $idResv = -1) {

        if ($psg->getIdPsg() == 0) {
            return;
        }

        $this->hstayRS->idPatient->setNewVal($psg->getIdPatient());
        $this->hstayRS->idPsg->setNewVal($psg->getIdPsg());
        $this->hstayRS->Status->setNewVal('a');
        $this->hstayRS->Updated_By->setNewVal($uname);
        $this->hstayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

        $currentHsId = intval($this->hstayRS->idHospital_stay->getStoredVal(), 10);
        $numReservations = 0;
        $triggerNumResv = ($idResv == 0 ? 0 : 1);

        // who else uses it
        if ($currentHsId > 0) {
	        $stmt = $dbh->prepare("SELECT COUNT(`idReservation`) FROM `reservation` WHERE `idHospital_Stay` = :currentHsId");
	        $stmt->execute([':currentHsId' => $currentHsId]);
	        $resvIds = $stmt->fetchAll(\PDO::FETCH_NUM);
	        $numReservations = $resvIds[0][0];
        }

        if ($currentHsId === 0 || ($numReservations > $triggerNumResv && EditRS::isChanged($this->hstayRS))) {

            // Insert
        	$currentHsId = EditRS::insert($dbh, $this->hstayRS);

        	$this->hstayRS->idHospital_stay->setNewVal($currentHsId);

            $logText = ReservationLog::getInsertText($this->hstayRS);
            ReservationLog::logHospStay($dbh,
            	$currentHsId,
                $psg->getIdPatient(),
                $idAgent,
                $psg->getIdPsg(),
                $logText, 'insert', $uname);

        } else {

            //Update
            $updt = EditRS::update($dbh, $this->hstayRS, array($this->hstayRS->idHospital_stay));
            
            if ($updt == 1) {
                $logText = ReservationLog::getUpdateText($this->hstayRS);
                ReservationLog::logHospStay($dbh,
                    $this->hstayRS->idHospital_stay->getStoredVal(),
                    $psg->getIdPatient(),
                    $idAgent,
                    $psg->getIdPsg(),
                    $logText, 'update', $uname);
            }

        }

        EditRS::updateStoredVals($this->hstayRS);

        return $currentHsId;
    }
    
    public function getIdHospital_Stay() {
        return $this->hstayRS->idHospital_stay->getStoredVal();
    }
    
    public function getIdPatient() {
        return $this->hstayRS->idPatient->getStoredVal();
    }
    
    public function getIdPsg() {
        return $this->hstayRS->idPsg->getStoredVal();
    }
    
    public function setIdPsg($v) {
        $this->hstayRS->idPsg->setNewVal($v);
    }
    
    public function getAgentId() {
        return $this->hstayRS->idReferralAgent->getStoredVal();
    }
    
    public function setAgentId($id) {
        $this->hstayRS->idReferralAgent->setNewVal($id);
    }
    
    public function getHospitalId() {
        return $this->hstayRS->idHospital->getStoredVal();
    }
    
    public function setHospitalId($v) {
        $this->hstayRS->idHospital->setNewVal(intval($v, 10));
    }
    
    public function getAssociationId() {
        return $this->hstayRS->idAssociation->getStoredVal();
    }
    
    public function setAssociationId($v) {
        $this->hstayRS->idAssociation->setNewVal(intval($v, 10));
    }
    
    public function getDoctor() {
        return $this->hstayRS->Doctor->getStoredVal();
    }
    
    public function getDoctorId() {
        return $this->hstayRS->idDoctor->getStoredVal();
    }
    
    public function getReservationId(){
        return $this->idReservation;
    }
    
    public function setDoctorId($id) {
        $this->hstayRS->idDoctor->setNewVal($id);
    }
    
    public function setDoctor($v) {
        $this->hstayRS->Doctor->setNewVal($v);
    }
    
    public function getDiagnosis() {
        return $this->hstayRS->Diagnosis->getStoredVal();
    }
    
    public function setDiagnosis($v) {
        $this->hstayRS->Diagnosis->setNewVal($v);
    }
    
    public function getDiagnosis2() {
        return $this->hstayRS->Diagnosis2->getStoredVal();
    }
    
    public function setDiagnosis2($v) {
        $this->hstayRS->Diagnosis2->setNewVal($v);
    }
    
    public function getDiagnosisCode() {
        return $this->hstayRS->Diagnosis->getStoredVal();
    }
    
    public function setDiagnosisCode($v) {
        $this->hstayRS->Diagnosis->setNewVal($v);
    }
    
    public function getLocationCode() {
        return $this->hstayRS->Location->getStoredVal();
    }
    
    public function setLocationCode($v) {
        $this->hstayRS->Location->setNewVal($v);
    }
    
    public function getArrivalDate() {
        return $this->hstayRS->Arrival_Date->getStoredVal();
    }
    
    public function setArrivalDate($v) {
        $this->hstayRS->Arrival_Date->setNewVal($v);
    }
    
    public function getExpectedDepartureDate() {
        return $this->hstayRS->Expected_Departure->getStoredVal();
    }
    
    public function setExpectedDepartureDate($v) {
        $this->hstayRS->Expected_Departure->setNewVal($v);
    }
    
    public function getRoom() {
    	return $this->hstayRS->Room->getStoredVal();
    }
    
    public function setRoom($v) {
    	$this->hstayRS->Room->setNewVal($v);
    }
    public function getMrn() {
    	return $this->hstayRS->MRN->getStoredVal();
    }
    
    public function setMrn($v) {
    	$this->hstayRS->MRN->setNewVal($v);
    }
}