<?php
/**
 * Note.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Report
 *
 * @author Will
 */

class Note {

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
    protected function loadNote(\PDO $dbh) {

        $response = TRUE;

        if ($this->idReport > 0) {

            $reportRS = new ReportRs();
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
    public static function createNew($reportTitle, $reportDate, $reportDescription, $reportAuthor = '', $reportStatus = Report::ActiveStatus ) {

        if ($reportTitle != '' && $reportAuthor != '' && $reportDescription != '') {

            $report = new Report();

            $report->setTitle($reportTitle);
            $report->setReportDate($reportDate);
            $report->setDescription($reportDescription);
            $report->setAuthor($reportAuthor);
            $report->setStatus($reportStatus);
            $report->idReport = 0;

        } else {
            throw new Hk_Exception_Runtime('Trying to create an invalid note.  ');
        }

        return $report;
    }

    public function saveNew(\PDO $dbh) {

        if ($this->isValid() === FALSE) {
            return FALSE;
        }

        // Insert
        $reportRS = new ReportRs();
        $reportRS->Title->setNewVal($this->getTitle());
        $reportRS->Report_Date->setNewVal($this->getReportDate());
        $reportRS->Description->setNewVal($this->getDescription());
        $reportRS->Author->setNewVal($this->getAuthor());
        $reportRS->Status->setNewVal($this->getStatus());
        $reportRS->Last_Updated->setNewVal($this->getLastUpdated());
        $reportRS->Updated_By->setNewVal($this->getUpdatedBy());

        $this->idReport = EditRS::insert($dbh, $reportRS);
        $reportRS->idReport->setNewVal($this->idReport);
        EditRS::updateStoredVals($reportRS);

        $this->reportRS = $reportRS;

    }

    /**
     *
     * @param \PDO $dbh
     * @param string $noteText
     * @param string $updatedBy
     * @return int the number of records updated.
     */
    public function updateContents(\PDO $dbh, $reportTitle, $reportDate, $reportResolutionDate = '', $reportDescription, $reportResolution = '' $reportSignature = '' $reportSignatureDate = '', $reportAuthor = '', $reportStatus = Report::ActiveStatus, $updatedBy) {

        $counter = 0;

        if ($this->getIdReport() > 0 && $this->loadReport($dbh)) {

            $this->reportRS->Title->setNewVal($reportTitle);
            $this->reportRS->Report_Date->setNewVal($reportDate);
            $this->reportRS->Resolution_Date->setNewVal($reportResolutionDate);
            $this->reportRS->Description->setNewVal($reportDescription);
            $this->reportRS->Resolution->setNewVal($reportResolution);
            $this->reportRS->Signature->setNewVal($reportSignature);
            $this->reportRS->Signature_Date->setNewVal($reportSignatureDate);
            $this->reportRS->Author->setNewVal($reportAuthor);
            $this->reportRS->Status->setNewVal(self::ActiveStatus);
            $this->reportRS->Updated_By->setNewVal($updatedBy);
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
    public function deleteReport(\PDO $dbh, $username) {

        $counter = 0;

        if ($this->getIdReport() > 0 && $this->loadReport($dbh)) {

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

    protected function isValid() {

        return TRUE;
    }

    public function getIdReport() {
        return $this->idReport;
    }

    public function getReportTitle() {
        return $this->title;
    }

    public function getReportDate() {
        return $this->reportDate;
    }

    public function getResolutionDate() {
        return $this->resolutionDate;
    }

    public function getReportDescription() {
        return $this->description;
    }
    
    public function getReportResolution(){
	    return $this->resolution;
    }
    
    public function getReportSignature(){
	    return $this->signature;
    }
    
    public function getReportSignatureDate(){
	    return $this->signatureDate;
    }
    
    public function getReportAuthor(){
	    return $this->author;
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

    public function setReportTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function setReportDate($date) {
        $this->reportDate = $date;
        return $this;
    }

    public function setReportResolutionDate($resolutionDate) {
        $this->resolutionDate = $resolutionDate;
        return $this;
    }
    
    public function setReportDescription($description){
	    $this->description = $description;
	    return $this;
    }
    
    public function setReportResolution($resolution){
	    $this->resolution = $resolution;
	    return $this;
    }
    
    public function setReportSignature($signature){
	    $this->signature = $signature;
	    return $this;
    }
    
    public function setReportSignatureDate($signatureDate){
	    $this->signatureDate = $signatureDate;
	    return $this;
    }
    
    public function setReportAuthor($username){
	    $this->author = $username;
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

}