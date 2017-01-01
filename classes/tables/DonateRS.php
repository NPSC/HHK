<?php
/**
 * DonateRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of DonateRS
 * @package name
 * @author Eric
 */
class DonationsRS extends TableRS {

    public $iddonations;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Donor_Id;  // int(11) NOT NULL,
    public $Care_Of_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Assoc_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Type;  // varchar(15) NOT NULL DEFAULT '',
    public $Date_Entered;  // datetime DEFAULT NULL,
    public $Trans_Date;  // datetime DEFAULT NULL,
    public $Pay_Type;  // varchar(15) NOT NULL DEFAULT '',
    public $Member_type;  // varchar(15) NOT NULL DEFAULT '',
    public $Donation_Type;  // varchar(15) NOT NULL DEFAULT '',
    public $Amount;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Matching_Amount;  // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Note;  // varchar(255) NOT NULL DEFAULT '',
    public $Salutation_Code;  // varchar(15) NOT NULL DEFAULT '',
    public $Envelope_Code;  // varchar(15) NOT NULL DEFAULT '',
    public $Address_1;  // varchar(145) NOT NULL DEFAULT '',
    public $Address_2;  // varchar(145) NOT NULL DEFAULT '',
    public $City;  // varchar(45) NOT NULL DEFAULT '',
    public $State;  // varchar(5) NOT NULL DEFAULT '',
    public $Postal_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Country;  // varchar(45) NOT NULL DEFAULT '',
    public $Address_Purpose;  // varchar(5) NOT NULL DEFAULT '',
    public $Phone;  // varchar(25) NOT NULL DEFAULT '',
    public $Email;  // varchar(145) NOT NULL DEFAULT '',
    public $Fund_Code;  // varchar(15) NOT NULL DEFAULT '',
    public $Org_Code;  // varchar(15) NOT NULL DEFAULT '',
    public $Campaign_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Activity_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Date_Acknowledged;  // datetime DEFAULT NULL,
    public $Status;  // varchar(15) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,


    function __construct($TableName = "donations") {
        $this->iddonations = new DB_Field("iddonations", 0, new DbIntSanitizer());
        $this->Donor_Id = new DB_Field("Donor_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Care_Of_Id = new DB_Field("Care_Of_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Assoc_Id = new DB_Field("Assoc_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Date_Entered = new DB_Field("Date_Entered", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Trans_Date = new DB_Field("Trans_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Pay_Type = new DB_Field("Pay_Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Member_type = new DB_Field("Member_type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Donation_Type = new DB_Field("Donation_Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Amount = new DB_Field("Amount", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Matching_Amount = new DB_Field("Matching_Amount", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Note = new DB_Field("Note", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Salutation_Code = new DB_Field("Salutation_Code", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Envelope_Code = new DB_Field("Envelope_Code", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Address_1 = new DB_Field("Address_1", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Address_2 = new DB_Field("Address_2", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->City = new DB_Field("City", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->State = new DB_Field("State", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Postal_Code = new DB_Field("Postal_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Country = new DB_Field("Country", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Address_Purpose = new DB_Field("Address_Purpose", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Phone = new DB_Field("Phone", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->Email = new DB_Field("Email", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Fund_Code = new DB_Field("Fund_Code", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Org_Code = new DB_Field("Org_Code", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Campaign_Code = new DB_Field("Campaign_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Activity_Id = new DB_Field("Activity_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Date_Acknowledged = new DB_Field("Date_Acknowledged", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}


class CampaignRS  extends TableRS {

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

