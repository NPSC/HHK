<?php
/**
 * visitRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *  ReportRS
 */
class ReportRs extends TableRS {

    public $idReport;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;   // VARCHAR(240) NOT NULL ,
    public $Category;   // VARCHAR(5) NOT NULL ,
    public $Report_Date;   // DATETIME NULL ,
    public $Resolution_Date;   // DATETIME NULL ,
    public $Description;  // TEXT NOT NULL DEFAULT '',
    public $Resolution;  // TEXT NOT NULL DEFAULT '',
    public $Signature;   // BLOB NULL ,
    public $Signature_Date;  // DATETIME NULL,
    public $Author;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Guest_Id;   // INT NOT NULL DEFAULT '0' ,
    public $Psg_Id;   // INT NOT NULL DEFAULT '0' ,
    public $Last_Updated;   // DATETIME NULL ,
    public $Updated_By;   // VARCHAR(45) NOT NULL DEFAULT '' ,
    public $Status;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Timestamp;   // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
    
    function __construct($TableName = "report") {

        $this->idReport = new DB_Field("idReport", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(240), TRUE, TRUE);
        $this->Category = new DB_Field("Category", '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Report_Date = new DB_Field("Report_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Resolution_Date = new DB_Field("Resolution_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Description = new DB_Field("Description", '', new DbStrSanitizer(5000), TRUE, TRUE);
        $this->Resolution = new DB_Field("Resolution", '', new DbStrSanitizer(5000), TRUE, TRUE);
        $this->Signature = new DB_Field("Signature", NULL, new DbBlobSanitizer(), TRUE, TRUE);
        $this->Signature_Date = new DB_Field("Signature_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Author = new DB_Field("Author", '', new DbStrSanitizer('45'), TRUE, TRUE);
        $this->Guest_Id = new DB_Field("Guest_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Psg_Id = new DB_Field("Psg_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Last_Updated = new DB_Field('Last_Updated', '', new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", NULL, new DbStrSanitizer(45), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}