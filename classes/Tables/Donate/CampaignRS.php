<?php
namespace Tables\Donate;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDecimalSanitizer, DbDateSanitizer};

/**
 * CampaignRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CampaignRS  extends AbstractTableRS {

    public $idcampaign;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;  // varchar(250) NOT NULL DEFAULT '',
    public $Campaign_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Start_Date;  // date DEFAULT NULL,
    public $End_Date;  // date DEFAULT NULL,
    public $Target;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Min_Donation;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Max_Donation;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Category;  // varchar(15) NOT NULL DEFAULT '',
    public $Campaign_Merge_Code;  //varchar(15) NOT NULL DEFAULT '',
    public $Description;  // text,
    public $Lump_Sum_Cost;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Percent_Cut;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Our_Cut_PerTx;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Campaign_Type;  // varchar(5) NOT NULL DEFAULT '',
    public $Status;  // varchar(5) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,



    function __construct($TableName = "campaign") {

        $this->idcampaign = new DB_Field("idcampaign", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(250), TRUE, TRUE);
        $this->Campaign_Code = new DB_Field("Campaign_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Start_Date = new DB_Field("Start_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->End_Date = new DB_Field("End_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Target = new DB_Field("Target", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Min_Donation = new DB_Field("Min_Donation", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Max_Donation = new DB_Field("Max_Donation", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Campaign_Merge_Code = new DB_Field("Campaign_Merge_Code", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(250), TRUE, TRUE);
        $this->Lump_Sum_Cost = new DB_Field("Lump_Sum_Cost", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Percent_Cut = new DB_Field("Percent_Cut", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Our_Cut_PerTx = new DB_Field("Our_Cut_PerTx", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Campaign_Type = new DB_Field("Campaign_Type", "", new DbStrSanitizer(5), TRUE, TRUE);

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}

