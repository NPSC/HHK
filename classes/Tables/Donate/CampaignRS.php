<?php
namespace HHK\Tables\Donate;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDecimalSanitizer, DbDateSanitizer};

/**
 * CampaignRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CampaignRS  extends AbstractTableRS {

    public DB_Field $idcampaign;  // int(11) NOT NULL AUTO_INCREMENT,
    public DB_Field $Title;  // varchar(250) NOT NULL DEFAULT '',
    public DB_Field $Campaign_Code;  // varchar(45) NOT NULL DEFAULT '',
    public DB_Field $Start_Date;  // date DEFAULT NULL,
    public DB_Field $End_Date;  // date DEFAULT NULL,
    public DB_Field $Target;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public DB_Field $Min_Donation;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public DB_Field $Max_Donation;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public DB_Field $Category;  // varchar(15) NOT NULL DEFAULT '',
    public DB_Field $Campaign_Merge_Code;  //varchar(15) NOT NULL DEFAULT '',
    public DB_Field $Description;  // text,
    public DB_Field $Lump_Sum_Cost;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public DB_Field $Percent_Cut;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public DB_Field $Our_Cut_PerTx;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public DB_Field $Campaign_Type;  // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Status;  // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Last_Updated;  // datetime DEFAULT NULL,
    public DB_Field $Updated_By;  // varchar(45) DEFAULT '',
    public DB_Field $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,



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

