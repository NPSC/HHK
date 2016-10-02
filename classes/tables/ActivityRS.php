<?php
/**
 * ActivityRS.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of ActivityRS
 * @author Eric
 */
class ActivityRS extends TableRS {

    public $idActivity;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idName;   // int(11) NOT NULL,
    public $Type;   // varchar(15) NOT NULL DEFAULT '',
    public $Trans_Date;   // datetime DEFAULT NULL,
    public $Effective_Date;   // datetime DEFAULT NULL,
    public $Product_Code;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Other_Code;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Description;   // varchar(245) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Source_System;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Source_Code;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Quantity;   // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Amount;   // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Category;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Units;   // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Thru_Date;   // datetime DEFAULT NULL,
    public $Member_Type;   // varchar(15) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Action_Codes;   // varchar(245) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Pay_Method;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Note;   // text CHARACTER SET latin1,
    public $Batch_Num;   // varchar(25) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Company_ID;   // varchar(15) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Originating_Trans_Num;   // int(11) NOT NULL DEFAULT '0',
    public $Org_Code;   // varchar(5) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Campaign_Code;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Mail_Merge_Code;   // varchar(45) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Solicitation_Text;   // varchar(200) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Solicitor_Id;   // int(11) NOT NULL DEFAULT '0',
    public $Salutation_Code;   // varchar(15) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Status_Code;   // varchar(5) CHARACTER SET latin1 NOT NULL DEFAULT '',
    public $Grace_Period;   // int(11) NOT NULL DEFAULT '0',
    public $Timestamp;   // timestamp NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "activity") {

        $this->idActivity = new DB_Field("idActivity", 0, new DbIntSanitizer());
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(15));
        $this->Trans_Date = new DB_Field("Trans_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Effective_Date = new DB_Field("Effective_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Product_Code = new DB_Field("Product_Code", "", new DbStrSanitizer(45));
        $this->Other_Code = new DB_Field("Other_Code", "", new DbStrSanitizer(45));
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(245));
        $this->Source_System = new DB_Field("Source_System", "", new DbStrSanitizer(45));
        $this->Source_Code = new DB_Field("Source_Code", "", new DbStrSanitizer(45));
        $this->Quantity = new DB_Field("Quantity", "0.00", new DbDecimalSanitizer());
        $this->Amount = new DB_Field("Amount", "0.00", new DbDecimalSanitizer());
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(45));
        $this->Units = new DB_Field("Units", "0.00", new DbDecimalSanitizer());
        $this->Thru_Date = new DB_Field("Thru_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"));
        $this->Member_Type = new DB_Field("Member_Type", "", new DbStrSanitizer(15));
        $this->Action_Codes = new DB_Field("Action_Codes", "", new DbStrSanitizer(245));
        $this->Pay_Method = new DB_Field("Pay_Method", "", new DbStrSanitizer(45));
        $this->Note = new DB_Field("Note", "", new DbStrSanitizer(1045));
        $this->Batch_Num = new DB_Field("Batch_Num", "", new DbStrSanitizer(25));
        $this->Company_ID = new DB_Field("Company_ID", "", new DbStrSanitizer(15));
        $this->Originating_Trans_Num = new DB_Field("Originating_Trans_Num", 0, new DbIntSanitizer());
        $this->Org_Code = new DB_Field("Org_Code", "", new DbStrSanitizer(5));
        $this->Campaign_Code = new DB_Field("Campaign_Code", "", new DbStrSanitizer(45));
        $this->Mail_Merge_Code = new DB_Field("Mail_Merge_Code", "", new DbStrSanitizer(45));
        $this->Solicitation_Text = new DB_Field("Solicitation_Text", "", new DbStrSanitizer(200));
        $this->Solicitor_Id = new DB_Field("Solicitor_Id", 0, new DbIntSanitizer());
        $this->Salutation_Code = new DB_Field("Salutation_Code", "", new DbStrSanitizer(15));
        $this->Status_Code = new DB_Field("Status_Code", "", new DbStrSanitizer(5));
        $this->Grace_Period = new DB_Field("Grace_Period", 0, new DbIntSanitizer());
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }



}

