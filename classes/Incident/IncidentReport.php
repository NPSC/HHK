<?php

namespace HHK\Incident;

use HHK\sec\SecurityComponent;
use HHK\Tables\{EditRS, IncidentReportRS};
use HHK\Exception\RuntimeException;

/**
 * IncidentReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of IncidentReport
 *
 * @author Will
 */

class IncidentReport {

    // Report Status
    const ActiveStatus = 'a';
    const ResolvedStatus = 'r';
    const DeletedStatus = 'd';


    // Note record field vars
    protected $idReport = 0;
    protected $title = '';
    protected $category = '';
    protected $reportDate = '';
    protected $resolutionDate = '';
    protected $description = '';
    protected $resolution = '';
    protected $signature = '';
    protected $signatureDate = '';
    protected $author = '';
    protected $guestId = 0;
    protected $psgId = 0;
    protected $status = '';
    protected $lastUpdated = null;
    protected $updatedBy = '';
    protected $timestamp = '';

    private $reportRS;

    /**
     *
     * @param int $idReport
     */
    public function __construct($idReport = 0) {

        $id = intval($idReport, 10);
        $this->idReport = $id;

    }

    /**
     *
     * @param \PDO $dbh
     * @return boolean
     */
    public function loadReport(\PDO $dbh) {

        $response = TRUE;

        if ($this->idReport > 0) {

            $reportRS = new IncidentReportRs();
            $reportRS->idReport->setStoredVal($this->idReport);
            $rows = EditRS::select($dbh, $reportRS, array($reportRS->idReport));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $reportRS);

                $this->setTitle($reportRS->Title->getStoredVal());
                $this->setCategory($reportRS->Category->getStoredVal());
                $this->setReportDate($reportRS->Report_Date->getStoredVal());
                $this->setResolutionDate($reportRS->Resolution_Date->getStoredVal());
                $this->setDescription($reportRS->Description->getStoredVal());
                $this->setResolution($reportRS->Resolution->getstoredVal());
                $this->setSignature($reportRS->Signature->getStoredVal());
                $this->setSignatureDate($reportRS->Signature_Date->getStoredVal());
                $this->setAuthor($reportRS->Author->getStoredVal());
                $this->setGuestId($reportRS->Guest_Id->getStoredVal());
                $this->setPsgId($reportRS->Psg_Id->getStoredVal());
                $this->setLastUpdated($reportRS->Last_Updated->getStoredVal());
                $this->setUpdatedBy($reportRS->Updated_By->getStoredVal());
                $this->setStatus($reportRS->Status->getStoredVal());
                $this->setTimestamp($reportRS->Timestamp->getStoredVal());

                $this->reportRS = $reportRS;

            } else {
                $response =  FALSE;
            }
        }

        return $response;
    }

    /**
     *
     * @param string $reportTitle
     * @param string $reportDate
     * @param string $reportDescription
     * @param string $reportAuthor
     */
    public static function createNew($reportTitle, $reportDate, $reportDescription, $reportAuthor = '', $reportStatus = IncidentReport::ActiveStatus, $reportResolution = '', $reportResolutionDate = '', $reportSignature = '', $signatureDate = '', $guestId = '', $psgId = '' ) {

        if ($reportTitle != '' && $reportAuthor != '') {

            $report = new IncidentReport();

            $report->setTitle($reportTitle);
            $report->setReportDate($reportDate);
            $report->setDescription($reportDescription);
            $report->setAuthor($reportAuthor);
            $report->setStatus($reportStatus);
            $report->setResolution($reportResolution);
            $report->setResolutionDate($reportResolutionDate);
            $report->setSignature($reportSignature);
            $report->setSignatureDate($signatureDate);
            $report->setPsgId($psgId);
            $report->setGuestId($guestId);
            $report->idReport = 0;

        } else {
            throw new RuntimeException('Trying to create an invalid Incident.  ');
        }

        return $report;
    }

    /**
     * Summary of saveNew
     * @param \PDO $dbh
     * @return bool
     */
    public function saveNew(\PDO $dbh) {

        if ($this->isValid() === FALSE) {
            return FALSE;
        }

        // Insert
        $reportRS = new IncidentReportRs();
        $reportRS->Title->setNewVal($this->getTitle());
        $reportRS->Report_Date->setNewVal($this->getReportDate());
        $reportRS->Description->setNewVal($this->getDescription());
        $reportRS->Author->setNewVal($this->getAuthor());
        $reportRS->Status->setNewVal($this->getStatus());
        $reportRS->Resolution->setNewVal($this->getResolution());
        $reportRS->Resolution_Date->setNewVal($this->getResolutionDate());
        $reportRS->Signature->setNewVal($this->getSignature());
        $reportRS->Signature_Date->setNewVal($this->getSignatureDate());
        $reportRS->Guest_Id->setNewVal($this->getGuestId());
        $reportRS->Psg_Id->setNewVal($this->getPsgId());
        $reportRS->Last_Updated->setNewVal($this->getLastUpdated());
        $reportRS->Updated_By->setNewVal($this->getUpdatedBy());

        $this->idReport = EditRS::insert($dbh, $reportRS);
        $reportRS->idReport->setNewVal($this->idReport);
        EditRS::updateStoredVals($reportRS);

        $this->reportRS = $reportRS;

    }

    
    /**
     * Summary of updateContents
     * @param \PDO $dbh
     * @param mixed $reportTitle
     * @param mixed $reportDate
     * @param mixed $reportResolutionDate
     * @param mixed $reportDescription
     * @param mixed $reportResolution
     * @param mixed $reportSignature
     * @param mixed $signatureDate
     * @param mixed $reportStatus
     * @param mixed $updatedBy
     * @return int
     */
    public function updateContents(\PDO $dbh, $reportTitle, $reportDate, $reportResolutionDate, $reportDescription, $reportResolution, $reportSignature, $signatureDate, $reportStatus, $updatedBy) {

        $counter = 0;

        if ($this->getIdReport() > 0 && $this->loadReport($dbh)) {

            if($this->userCanEdit()){
                $this->reportRS->Title->setNewVal($reportTitle);
                $this->reportRS->Report_Date->setNewVal($reportDate);
                $this->reportRS->Description->setNewVal($reportDescription);
            }
            
            $this->reportRS->Resolution_Date->setNewVal($reportResolutionDate);
            $this->reportRS->Resolution->setNewVal($reportResolution);
            $this->reportRS->Signature->setNewVal($reportSignature);
            $this->reportRS->Signature_Date->setNewVal($signatureDate);
            $this->reportRS->Status->setNewVal($reportStatus);
            $this->reportRS->Updated_By->setNewVal($updatedBy);
            $this->reportRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->reportRS, array($this->reportRS->idReport));
            EditRS::updateStoredVals($this->reportRS);
        }

        return $counter;
    }

    public static function userCanEdit(){
        return (SecurityComponent::is_Admin() || SecurityComponent::is_Authorized("guestadmin"));
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $username
     * @return int the number of rows affected
     */
    public function deleteReport(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->getIdReport() > 0 && $this->loadReport($dbh) && $this->userCanEdit()) {

            $this->reportRS->Status->setNewVal(self::DeletedStatus);
            $this->reportRS->Updated_By->setNewVal($username);
            $this->reportRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->reportRS, array($this->reportRS->idReport));
            EditRS::updateStoredVals($this->reportRS);

        }

        return $counter;
    }


    /**
     *
     * @param \PDO $dbh
     * @param string $username
     * @return int the number of rows affected
     */
    public function undoDeleteReport(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->getIdReport() > 0 && $this->loadReport($dbh)) {

            $this->reportRS->Status->setNewVal(self::ActiveStatus);
            $this->reportRS->Updated_By->setNewVal($username);
            $this->reportRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $counter = EditRS::update($dbh, $this->reportRS, array($this->reportRS->idReport));
            EditRS::updateStoredVals($this->reportRS);

        }

        return $counter;
    }

    public function toArray(){
	    return array(
		    "idReport"=>$this->idReport,
		    "title"=>$this->title,
		    "category"=>$this->category,
		    "reportDate"=>date("M j, Y", strtotime($this->reportDate)),
		    "resolutionDate"=>($this->resolutionDate ? date("M j, Y", strtotime($this->resolutionDate)): ""),
		    "description"=>$this->description,
		    "resolution"=>$this->resolution,
		    "signature" =>$this->signature,
		    "signatureDate" =>($this->signatureDate ? date("M j, Y", strtotime($this->signatureDate)): ""),
		    "status"=>$this->status,
		    "createdBy"=>$this->author,
		    "updatedBy"=>$this->updatedBy,
		    "updatedAt"=>($this->lastUpdated ? date("M j, Y h:i a", strtotime($this->lastUpdated)): ""),
	    );
    }

    protected function isValid() {

        return TRUE;
    }

    public function getIdReport() {
        return $this->idReport;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getCategory() {
	    return $this->category;
    }

    public function getReportDate() {
        return $this->reportDate;
    }

    public function getResolutionDate() {
        return $this->resolutionDate;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getResolution(){
	    return $this->resolution;
    }

    public function getSignature(){
	    return $this->signature;
    }

    public function getSignatureDate(){
	    return $this->signatureDate;
    }

    public function getAuthor(){
	    return $this->author;
    }

    public function getGuestId(){
	    return $this->guestId;
    }

    public function getPsgId(){
	    return $this->psgId;
    }

    public function getLastUpdated() {
        return $this->lastUpdated;
    }

    public function getUpdatedBy() {
        return $this->updatedBy;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getTimestamp(){
	    return $this->timestamp;
    }

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function setCategory($category) {
	    $this->category = $category;
	    return $this;
    }

    public function setReportDate($date) {
        $this->reportDate = $date;
        return $this;
    }

    public function setResolutionDate($resolutionDate) {
        $this->resolutionDate = $resolutionDate;
        return $this;
    }

    public function setDescription($description){
	    $this->description = $description;
	    return $this;
    }

    public function setResolution($resolution){
	    $this->resolution = $resolution;
	    return $this;
    }

    public function setSignature($signature){
	    $this->signature = $signature;
	    return $this;
    }

    public function setSignatureDate($signatureDate){
	    $this->signatureDate = $signatureDate;
	    return $this;
    }

    public function setAuthor($username){
	    $this->author = $username;
	    return $this;
    }

    public function setGuestId($guestId){
	    $this->guestId = $guestId;
	    return $this;
    }

    public function setPsgId($psgId){
	    $this->psgId = $psgId;
	    return $this;
    }

    public function setStatus($status) {
        $this->status = $status;
        return $this;
    }

    public function setLastUpdated($lastUpdated) {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function setUpdatedBy($updatedBy) {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function setTimestamp($timestamp) {
	    $this->timestamp = $timestamp;
	    return $this;
    }

}