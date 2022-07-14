-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2021 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK

-- -----------------------------------------------------
-- Table `activity`
-- -----------------------------------------------------
CREATE TABLE if not exists `activity` (
  `idActivity` int(11) NOT NULL AUTO_INCREMENT,
  `idName` int(11) NOT NULL,
  `Type` varchar(15) NOT NULL DEFAULT '',
  `Trans_Date` datetime DEFAULT NULL,
  `Effective_Date` datetime DEFAULT NULL,
  `Product_Code` varchar(45) NOT NULL DEFAULT '',
  `Other_Code` varchar(45) NOT NULL DEFAULT '',
  `Description` varchar(245) NOT NULL DEFAULT '',
  `Source_System` varchar(45) NOT NULL DEFAULT '',
  `Source_Code` varchar(45) NOT NULL DEFAULT '',
  `Quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Category` varchar(45) NOT NULL DEFAULT '',
  `Units` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Thru_Date` datetime DEFAULT NULL,
  `Member_Type` varchar(15) NOT NULL DEFAULT '',
  `Action_Codes` varchar(245) NOT NULL DEFAULT '',
  `Pay_Method` varchar(45) NOT NULL DEFAULT '',
  `Note` text,
  `Batch_Num` varchar(25) NOT NULL DEFAULT '',
  `Company_ID` varchar(15) NOT NULL DEFAULT '',
  `Originating_Trans_Num` int(11) NOT NULL DEFAULT '0',
  `Org_Code` varchar(5) NOT NULL DEFAULT '',
  `Campaign_Code` varchar(45) NOT NULL DEFAULT '',
  `Mail_Merge_Code` varchar(45) NOT NULL DEFAULT '',
  `Solicitation_Text` varchar(200) NOT NULL DEFAULT '',
  `Solicitor_Id` int(11) NOT NULL DEFAULT '0',
  `Salutation_Code` varchar(15) NOT NULL DEFAULT '',
  `Status_Code` varchar(5) NOT NULL DEFAULT '',
  `Grace_Period` int(11) NOT NULL DEFAULT '0',
  `Timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idActivity`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `attribute`
-- -----------------------------------------------------
CREATE TABLE if not exists `attribute` (
  `idAttribute` int(11) NOT NULL AUTO_INCREMENT,
  `Type` varchar(4) NOT NULL,
  `Title` varchar(145) NOT NULL DEFAULT '',
  `Category` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(4) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  PRIMARY KEY (`idAttribute`)
) ENGINE=InnoDB AUTO_INCREMENT=1;


-- -----------------------------------------------------
-- Table `attribute_entity`
-- -----------------------------------------------------
CREATE TABLE if not exists `attribute_entity` (
  `idEntity` int(11) NOT NULL,
  `idAttribute` int(11) NOT NULL,
  `Type` varchar(4) NOT NULL,
  PRIMARY KEY (`idEntity`,`idAttribute`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `campaign`
-- -----------------------------------------------------
CREATE TABLE if not exists `campaign` (
  `idcampaign` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(250) NOT NULL DEFAULT '',
  `Campaign_Code` varchar(45) NOT NULL DEFAULT '',
  `Start_Date` date DEFAULT NULL,
  `End_Date` date DEFAULT NULL,
  `Target` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Min_Donation` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Max_Donation` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Category` varchar(15) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Campaign_Merge_Code` varchar(15) NOT NULL DEFAULT '',
  `Description` text,
  `Lump_Sum_Cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Percent_Cut` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Our_Cut_PerTx` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Campaign_Type` varchar(5) NOT NULL DEFAULT '',
  `Updated_By` varchar(25) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idcampaign`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `card_id`
-- -----------------------------------------------------
CREATE TABLE if not exists `card_id` (
  `idName` int(11) NOT NULL,
  `idGroup` int(11) NOT NULL,
  `CardID` varchar(136) NOT NULL DEFAULT '',
  `Init_Date` datetime DEFAULT NULL,
  `ReturnCode` int(11) NOT NULL DEFAULT '0',
  `Frequency` varchar(9) NOT NULL DEFAULT '',
  `OperatorID` varchar(10) NOT NULL DEFAULT '',
  `ResponseCode` int(11) NOT NULL DEFAULT '0',
  `Transaction` varchar(14) NOT NULL DEFAULT '',
  `InvoiceNumber` varchar(36) NOT NULL DEFAULT '',
  `Amount` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `Merchant` VARCHAR(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`idName`,`idGroup`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `cc_hosted_gateway`
-- -----------------------------------------------------
CREATE TABLE if not exists `cc_hosted_gateway` (
  `idcc_gateway` int(11) NOT NULL AUTO_INCREMENT,
  `Gateway_Name` VARCHAR(45) NOT NULL DEFAULT '',
  `cc_name` varchar(45) NOT NULL DEFAULT '',
  `Merchant_Id` varchar(45) NOT NULL DEFAULT '',
  `Password` varchar(245) NOT NULL DEFAULT '',
  `Credit_Url` varchar(145) NOT NULL DEFAULT '',
  `Trans_Url` varchar(145) NOT NULL DEFAULT '',
  `CardInfo_Url` varchar(145) NOT NULL DEFAULT '',
  `Checkout_Url` varchar(145) NOT NULL DEFAULT '',
  `Mobile_CardInfo_Url` varchar(145) NOT NULL DEFAULT '',
  `Mobile_Checkout_Url` varchar(145) NOT NULL DEFAULT '',
  `CheckoutPOS_Url` varchar(145) NOT NULL DEFAULT '',
  `CheckoutPOSiFrame_Url` varchar(145) NOT NULL DEFAULT '',
  `Use_AVS_Flag` bit(1) NOT NULL DEFAULT b'0',
  `Use_Ccv_Flag` bit(1) NOT NULL DEFAULT b'0',
  `Retry_Count` int(11) NOT NULL DEFAULT '0',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idcc_gateway`)
) ENGINE=InnoDB AUTO_INCREMENT=1;



-- -----------------------------------------------------
-- Table `cleaning_log`
-- -----------------------------------------------------
CREATE TABLE if not exists `cleaning_log` (
  `idResource` int(11) NOT NULL DEFAULT '0',
  `idRoom` int(11) NOT NULL DEFAULT '0',
  `Type` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Notes` TEXT NULL ,
  `Last_Cleaned` datetime DEFAULT NULL,
  `Last_Deep_Clean` datetime DEFAULT NULL,
  `Username` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM ;



-- -----------------------------------------------------
-- Table `constraints`
-- -----------------------------------------------------
CREATE TABLE if not exists `constraints` (
  `idConstraint` int(11) NOT NULL AUTO_INCREMENT,
  `Type` varchar(4) NOT NULL DEFAULT '',
  `Title` varchar(145) NOT NULL DEFAULT '',
  `Category` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(4) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  PRIMARY KEY (`idConstraint`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `constraint_attribute`
-- -----------------------------------------------------
CREATE TABLE if not exists `constraint_attribute` (
  `idConstraint` int(11) NOT NULL,
  `idAttribute` int(11) NOT NULL,
  `Type` varchar(4) NOT NULL DEFAULT '',
  `Operation` varchar(4) NOT NULL DEFAULT '',
  PRIMARY KEY (`idConstraint`,`idAttribute`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `constraint_entity`
-- -----------------------------------------------------
CREATE TABLE if not exists `constraint_entity` (
  `idConstraint` int(11) NOT NULL,
  `idEntity` int(11) NOT NULL,
  `Type` varchar(4) NOT NULL,
  PRIMARY KEY (`idConstraint`,`idEntity`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `counter`
-- -----------------------------------------------------
CREATE TABLE if not exists `counter` (
  `seqn` int(11) NOT NULL,
  `Table_Name` varchar(75) NOT NULL,
  `Next` int(11) NOT NULL,
  `Last_Updated` datetime DEFAULT NULL,
  PRIMARY KEY (`seqn`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `country_code`
-- -----------------------------------------------------
CREATE TABLE if not exists `country_code` (
  `Country_Name` varchar(145) NOT NULL,
  `ISO_3166-1-alpha-2` varchar(5) NOT NULL,
  `External_Id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ISO_3166-1-alpha-2`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `cron_log`
-- -----------------------------------------------------
CREATE TABLE if not exists `cron_log` (
  `idLog` INT NOT NULL AUTO_INCREMENT,
  `idJob` INT NOT NULL DEFAULT 0,
  `Log_Text` VARCHAR(255) NULL,
  `Status` VARCHAR(45) NULL,
  `timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idLog`)
  ) ENGINE=InnoDB;
  
  -- -----------------------------------------------------
-- Table `cronjobs`
-- -----------------------------------------------------
CREATE TABLE if not exists `cronjobs` (
  `idJob` INT NOT NULL AUTO_INCREMENT,
  `Title` VARCHAR(45) NOT NULL DEFAULT '',
  `Code` VARCHAR(45) NOT NULL DEFAULT '',
  `Params` MEDIUMTEXT NOT NULL DEFAULT '{}',
  `Interval` VARCHAR(45) NOT NULL DEFAULT '',
  `Day` VARCHAR(10) NOT NULL DEFAULT '',
  `Hour` VARCHAR(2) NOT NULL DEFAULT '',
  `Minute` VARCHAR(2) NOT NULL DEFAULT '',
  `Status` VARCHAR(45) NOT NULL DEFAULT '',
  `LastRun` TIMESTAMP NULL,
  `timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idJob`)
  ) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `desig_holidays`
-- -----------------------------------------------------
CREATE TABLE if not exists `desig_holidays` (
  `Year` int(11) NOT NULL,
  `dh1` date DEFAULT NULL,
  `dh2` date DEFAULT NULL,
  `dh3` date DEFAULT NULL,
  `dh4` date DEFAULT NULL,
  `dh5` date DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  PRIMARY KEY (`Year`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `document`
-- -----------------------------------------------------
CREATE TABLE if not exists `document` (
  `idDocument` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(128) NOT NULL,
  `Name` varchar(45) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Type` varchar(5) NOT NULL DEFAULT '',
  `Mime_Type` VARCHAR(85) NOT NULL DEFAULT '',
  `Folder` varchar(45) NOT NULL DEFAULT '',
  `Language` varchar(5) NOT NULL DEFAULT '',
  `Abstract` TEXT,
  `Doc` MEDIUMBLOB,
  `userData` MEDIUMTEXT NULL,
  `Style` MEDIUMTEXT NULL,
  `Status` varchar(5) NOT NULL,
  `Last_Updated` datetime DEFAULT NULL,
  `Created_By` varchar(45) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idDocument`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `staff_note`
-- -----------------------------------------------------
CREATE TABLE if not exists `staff_note` (
  `Link_Id` INT NOT NULL,
  `Note_Id` INT NOT NULL,
  PRIMARY KEY (`Note_Id`)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `doc_note`
-- -----------------------------------------------------
CREATE TABLE if not exists `doc_note` (
  `Doc_Id` INT NOT NULL,
  `Note_Id` INT NOT NULL,
  PRIMARY KEY (`Doc_Id`, `Note_Id`)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `link_doc`
-- -----------------------------------------------------
CREATE TABLE if not exists `link_doc` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `idDocument` int(11) NOT NULL,
  `idGuest` int(11) DEFAULT NULL,
  `idPSG` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `donations`
-- -----------------------------------------------------
CREATE TABLE if not exists `donations` (
  `iddonations` int(11) NOT NULL AUTO_INCREMENT,
  `Donor_Id` int(11) NOT NULL,
  `Care_Of_Id` int(11) NOT NULL DEFAULT '0',
  `Assoc_Id` int(11) NOT NULL DEFAULT '0',
  `Type` varchar(15) NOT NULL DEFAULT '',
  `Date_Entered` datetime DEFAULT NULL,
  `Trans_Date` datetime DEFAULT NULL,
  `Pay_Type` varchar(15) NOT NULL DEFAULT '',
  `Member_type` varchar(15) NOT NULL DEFAULT '',
  `Donation_Type` varchar(15) NOT NULL DEFAULT '',
  `Amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Matching_Amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Note` varchar(255) NOT NULL DEFAULT '',
  `Salutation_Code` varchar(15) NOT NULL DEFAULT '',
  `Envelope_Code` varchar(15) NOT NULL DEFAULT '',
  `Address_1` varchar(145) NOT NULL DEFAULT '',
  `Address_2` varchar(145) NOT NULL DEFAULT '',
  `City` varchar(45) NOT NULL DEFAULT '',
  `State` varchar(5) NOT NULL DEFAULT '',
  `Postal_Code` varchar(45) NOT NULL DEFAULT '',
  `Country` varchar(45) NOT NULL DEFAULT '',
  `Address_Purpose` varchar(5) NOT NULL DEFAULT '',
  `Phone` varchar(25) NOT NULL DEFAULT '',
  `Email` varchar(145) NOT NULL DEFAULT '',
  `Fund_Code` varchar(15) NOT NULL DEFAULT '',
  `Org_Code` varchar(15) NOT NULL DEFAULT '',
  `Campaign_Code` varchar(45) NOT NULL DEFAULT '',
  `Activity_Id` int(11) NOT NULL DEFAULT '0',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Date_Acknowledged` datetime DEFAULT NULL,
  `Status` varchar(15) NOT NULL DEFAULT '',
  PRIMARY KEY (`iddonations`)
) ENGINE=InnoDB AUTO_INCREMENT=10;




-- -----------------------------------------------------
-- Table `dormant_schedules`
-- -----------------------------------------------------
CREATE TABLE if not exists `dormant_schedules` (
  `iddormant_schedules` int(11) NOT NULL AUTO_INCREMENT,
  `Begin_Active` date DEFAULT NULL,
  `End_Active` date DEFAULT NULL,
  `Begin_Dormant` date DEFAULT NULL,
  `End_Dormant` date DEFAULT NULL,
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Code` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Description` text,
  `Updated_by` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`iddormant_schedules`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `emergency_contact`
-- -----------------------------------------------------
CREATE TABLE if not exists `emergency_contact` (
  `idEmergency_contact` int(11) NOT NULL AUTO_INCREMENT,
  `idName` int(11) NOT NULL,
  `Name_Last` varchar(45) NOT NULL DEFAULT '',
  `Name_First` varchar(45) NOT NULL DEFAULT '',
  `Relationship` varchar(5) NOT NULL DEFAULT '',
  `Phone_Home` varchar(15) NOT NULL DEFAULT '',
  `Phone_Mobile` varchar(15) NOT NULL DEFAULT '',
  `Phone_Alternate` varchar(15) NOT NULL DEFAULT '',
  `Notes` text,
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idEmergency_contact`)
) ENGINE=InnoDB;




-- -----------------------------------------------------
-- Table `fbx`
-- -----------------------------------------------------
CREATE TABLE if not exists `fbx` (
  `fb_id` varchar(45) NOT NULL,
  `idName` int(11) NOT NULL,
  `Status` varchar(2) NOT NULL DEFAULT '',
  `fb_username` varchar(145) NOT NULL DEFAULT '',
  `Approved_By` varchar(45) NOT NULL DEFAULT '',
  `Approved_Date` datetime DEFAULT NULL,
  `Dropped_Date` datetime DEFAULT NULL,
  `fb_Address_1` varchar(145) NOT NULL DEFAULT '',
  `fb_Address_2` varchar(45) NOT NULL DEFAULT '',
  `fb_City` varchar(45) NOT NULL DEFAULT '',
  `fb_State` varchar(45) NOT NULL DEFAULT '',
  `fb_Zip` varchar(15) NOT NULL DEFAULT '',
  `fb_First_Name` varchar(45) NOT NULL DEFAULT '',
  `fb_Last_Name` varchar(45) NOT NULL DEFAULT '',
  `fb_Phone` varchar(25) NOT NULL DEFAULT '',
  `fb_Email` varchar(145) NOT NULL DEFAULT '',
  `PIFH_Username` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Enc_Password` varchar(100) NOT NULL DEFAULT '',
  `Access_Code` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`fb_id`)
) ENGINE=InnoDB;




-- -----------------------------------------------------
-- Table `fin_application`
-- -----------------------------------------------------
CREATE TABLE if not exists `fin_application` (
  `idFin_application` int(11) NOT NULL AUTO_INCREMENT,
  `idRegistration` int(11) NOT NULL DEFAULT '0',
  `Monthly_Income` int(11) NOT NULL DEFAULT '0',
  `HH_Size` int(11) NOT NULL DEFAULT '0',
  `FA_Category` varchar(5) NOT NULL DEFAULT '',
  `Approved_Id` varchar(45) NOT NULL DEFAULT '',
  `Notes` text,
  `FA_Applied` varchar(2) NOT NULL DEFAULT '',
  `FA_Applied_Date` datetime DEFAULT NULL,
  `FA_Status` varchar(5) NOT NULL DEFAULT '',
  `FA_Status_Date` datetime DEFAULT NULL,
  `FA_Reason` varchar(445) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idFin_application`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `gateway_transaction`
-- -----------------------------------------------------
CREATE TABLE if not exists `gateway_transaction` (
  `idgateway_transaction` int(11) NOT NULL AUTO_INCREMENT,
  `GwTransCode` varchar(64) NOT NULL DEFAULT '',
  `GwResultCode` varchar(44) NOT NULL DEFAULT '',
  `Amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Vendor_Request` varchar(2000) NOT NULL DEFAULT '',
  `Vendor_Response` varchar(5000) NOT NULL DEFAULT '',
  `AuthCode` varchar(45) NOT NULL DEFAULT '',
  `idPayment_Detail` int(11) NOT NULL DEFAULT '0',
  `Created_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idgateway_transaction`)
) ENGINE=InnoDB;





-- -----------------------------------------------------
-- Table `gen_lookups`
-- -----------------------------------------------------
CREATE TABLE if not exists `gen_lookups` (
  `Table_Name` varchar(45) NOT NULL,
  `Code` varchar(65) NOT NULL,
  `Description` varchar(255) NOT NULL DEFAULT '',
  `Substitute` varchar(255) NOT NULL DEFAULT '',
  `Type` varchar(4) NOT NULL DEFAULT '',
  `Order` INT NOT NULL DEFAULT 0,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Table_Name`,`Code`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `gen_securitygroup`
-- -----------------------------------------------------
CREATE TABLE if not exists `gen_securitygroup` (
  `idSec` int(11) NOT NULL,
  `Group_Code` varchar(5) NOT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSec`,`Group_Code`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `guest_token`
-- -----------------------------------------------------
CREATE TABLE if not exists `guest_token` (
  `idGuest_token` int(11) NOT NULL AUTO_INCREMENT,
  `idGuest` int(11) NOT NULL DEFAULT '0',
  `Running_Total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `idRegistration` int(11) NOT NULL DEFAULT '0',
  `Token` varchar(100) NOT NULL DEFAULT '',
  `Merchant` VARCHAR(45) NOT NULL DEFAULT '',
  `Granted_Date` datetime DEFAULT NULL,
  `LifetimeDays` int(11) NOT NULL DEFAULT '0',
  `MaskedAccount` varchar(15) NOT NULL DEFAULT '',
  `Frequency` varchar(15) NOT NULL DEFAULT '',
  `Status` varchar(10) NOT NULL DEFAULT '',
  `Response_Code` int(11) NOT NULL DEFAULT '1',
  `CardHolderName` varchar(132) NOT NULL DEFAULT '',
  `CardType` varchar(45) NOT NULL DEFAULT '',
  `CardUsage` varchar(20) NOT NULL DEFAULT '',
  `ExpDate` varchar(14) NOT NULL DEFAULT '',
  `OperatorID` varchar(10) NOT NULL DEFAULT '',
  `Tran_Type` varchar(14) NOT NULL DEFAULT '',
  `StatusMessage` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idGuest_token`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `hospital`
-- -----------------------------------------------------
CREATE TABLE if not exists `hospital` (
  `idHospital` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Description` varchar(245) NOT NULL DEFAULT '',
  `Type` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(4) NOT NULL DEFAULT '',
  `idLocation` int(11) NOT NULL DEFAULT '0',
  `idName` int(11) NOT NULL DEFAULT '0',
  `Reservation_Style` varchar(145) NOT NULL DEFAULT '',
  `Stay_Style` varchar(145) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idHospital`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `hospital_stay`
-- -----------------------------------------------------
CREATE TABLE if not exists `hospital_stay` (
  `idHospital_stay` int(11) NOT NULL AUTO_INCREMENT,
  `idPatient` int(11) NOT NULL DEFAULT '0',
  `idPsg` int(11) NOT NULL DEFAULT '0',
  `idHospital` int(11) NOT NULL DEFAULT '0',
  `idAssociation` int(11) NOT NULL DEFAULT '0',
  `idReferralAgent` int(11) NOT NULL DEFAULT '0',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Diagnosis` varchar(245) NOT NULL DEFAULT '',
  `Diagnosis2` varchar(245) NOT NULL DEFAULT '',
  `Location` VARCHAR(5) NOT NULL DEFAULT '',
  `idDoctor` int(11) NOT NULL DEFAULT '0',
  `idPcDoctor` int(11) NOT NULL DEFAULT '0',
  `Doctor` varchar(145) NOT NULL DEFAULT '',
  `Room` varchar(45) NOT NULL DEFAULT '',
  `MRN` varchar(45) NOT NULL DEFAULT '',
  `Private_Ins_Code` VARCHAR(5) NOT NULL DEFAULT '',
  `Room_Phone` varchar(15) NOT NULL DEFAULT '',
  `Arrival_Date` datetime DEFAULT NULL,
  `Expected_Departure` datetime DEFAULT NULL,
  `Actual_Departure` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idHospital_stay`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `house`
-- -----------------------------------------------------
CREATE  TABLE if not exists `house` (
  `idHouse` INT NOT NULL AUTO_INCREMENT ,
  `Title` VARCHAR(45) NOT NULL DEFAULT '' ,
  `Description` VARCHAR(145) NOT NULL DEFAULT '' ,
  `idLocation` INT NOT NULL DEFAULT 0 ,
  `Timestamp` TIMESTAMP NOT NULL DEFAULT  CURRENT_TIMESTAMP ,
  PRIMARY KEY (`idHouse`)
  ) ENGINE = InnoDB;



-- -----------------------------------------------------
-- Table `house_log`
-- -----------------------------------------------------
CREATE  TABLE if not exists `house_log` (
  `Log_Type` VARCHAR(45) NOT NULL DEFAULT '' ,
  `Sub_Type` VARCHAR(45) NOT NULL DEFAULT '' ,
  `User_Name` VARCHAR(45) NOT NULL DEFAULT '' ,
  `Id1` INT NOT NULL DEFAULT 0 ,
  `Id2` INT NOT NULL DEFAULT 0 ,
  `Str1` VARCHAR(45) NOT NULL DEFAULT '' ,
  `Str2` VARCHAR(45) NOT NULL DEFAULT '' ,
  `Log_Text` VARCHAR(5000) NOT NULL DEFAULT '' ,
  `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
 ) ENGINE=MyISAM;



-- -----------------------------------------------------
-- Table `id_securitygroup`
-- -----------------------------------------------------
CREATE TABLE if not exists `id_securitygroup` (
  `idName` int(11) NOT NULL,
  `Group_Code` varchar(5) NOT NULL,
  `Timestamp` timestamp Not NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`,`Group_Code`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `insurance`
-- -----------------------------------------------------
CREATE TABLE if not exists `insurance` (
  `idInsurance` INT NOT NULL AUTO_INCREMENT COMMENT '',
  `idInsuranceType` INT(3) NOT NULL COMMENT '',
  `Title` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '',
  `Order` INT(3) NOT NULL DEFAULT 0 COMMENT '',
  `Opens_Type` VARCHAR(15) NOT NULL DEFAULT '' COMMENT '',
  `Status` VARCHAR(1) NOT NULL DEFAULT 'a' COMMENT '',
  `Timestamp` TIMESTAMP NOT NULL DEFAULT now() COMMENT '',
  PRIMARY KEY (`idInsurance`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `insurance_type`
-- -----------------------------------------------------
CREATE TABLE if not exists `insurance_type` (
  `idInsurance_type` INT(3) NOT NULL COMMENT '',
  `Title` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '',
  `Is_Primary` INT(1) NOT NULL DEFAULT 0 COMMENT '',
  `List_Order` INT(3) NOT NULL DEFAULT 0 COMMENT '',
  `Status` VARCHAR(1) NOT NULL DEFAULT 'a',
  PRIMARY KEY (`idInsurance_type`)
  ) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `invoice`
-- -----------------------------------------------------
CREATE TABLE if not exists `invoice` (
  `idInvoice` int(11) NOT NULL AUTO_INCREMENT,
  `Delegated_Invoice_Id` int(11) NOT NULL DEFAULT '0',
  `Invoice_Number` varchar(45) NOT NULL,
  `Billing_Process_Id` int(11) NOT NULL DEFAULT '0',
  `Deleted` SMALLINT default 0 NOT NULL,
  `Amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Sold_To_Id` int(11) NOT NULL DEFAULT '0',
  `idGroup` int(11) NOT NULL DEFAULT '0',
  `Invoice_Date` datetime DEFAULT NULL,
  `Payment_Attempts` int(11) NOT NULL DEFAULT '0',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Carried_Amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Order_Number` int(11) NOT NULL DEFAULT 0,
  `Suborder_Number` smallint(6) NOT NULL DEFAULT '0',
  `Due_Date` date DEFAULT NULL,
  `In_Process_Payment` tinyint(4) NOT NULL DEFAULT '0',
  `BillStatus` VARCHAR(5) NOT NULL DEFAULT '',
  `BillDate` DATE NULL,
  `Last_Reminder` DATETIME,
  `Overdue_Step` INTEGER NOT NULL DEFAULT '0',
  `Description` varchar(45) NOT NULL DEFAULT '',
  `Notes` varchar(450) NOT NULL DEFAULT '',
  `tax_exempt` tinyint NOT NULL DEFAULT 0,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idInvoice`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `invoice_line`
-- -----------------------------------------------------
CREATE TABLE if not exists `invoice_line`
(
    `idInvoice_Line` INTEGER NOT NULL AUTO_INCREMENT,
    `Invoice_Id` INTEGER NOT NULL DEFAULT '0',
    `Type_Id` INTEGER NOT NULL DEFAULT '0',
    `Amount` DECIMAL(22,10) NOT NULL,
    `Quantity` DECIMAL(22,10) NOT NULL DEFAULT '0.00',
    `Price` DECIMAL(22,10) NOT NULL DEFAULT '0.00',
    `Period_Start` DATETIME,
    `Period_End` DATETIME,
    `Deleted` SMALLINT NOT NULL default 0,
    `Item_Id` INTEGER NOT NULL default 0,
    `Description` VARCHAR(1000) NOT NULL default '',
    `Source_Item_Id` INTEGER NOT NULL default 0,
    `Is_Percentage` SMALLINT NOT NULL default 0,
    `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`idInvoice_Line`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `invoice_line_type`
-- -----------------------------------------------------
CREATE TABLE if not exists `invoice_line_type`
(
    `id` INTEGER NOT NULL,
    `Description` VARCHAR(50) NOT NULL,
    `Order_Position` INTEGER NOT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `item`
-- -----------------------------------------------------
CREATE TABLE if not exists `item`
(
    `idItem` INTEGER NOT NULL AUTO_INCREMENT,
    `Timeout_Days` VARCHAR(50) NOT NULL default '',
    `First_Order_Id` INTEGER NOT NULL DEFAULT 0,
    `Last_Order_Id` INTEGER NOT NULL DEFAULT 0,
    `Percentage` DECIMAL(22,10) NOT NULL DEFAULT '0.00',
    `Deleted` SMALLINT default 0 NOT NULL DEFAULT '0',
    `Has_Decimals` SMALLINT default 0 NOT NULL DEFAULT '0',
    `Gl_Code` VARCHAR(50) NOT NULL default '',
    `Description` VARCHAR(1000) NOT NULL default '',
    PRIMARY KEY(`idItem`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `item_item`
-- -----------------------------------------------------
CREATE TABLE if not exists `item_item` (
  `idItem` INT NOT NULL,
  `Item_Id` INT NOT NULL,
  PRIMARY KEY (`idItem`, `Item_Id`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `item_price`
-- -----------------------------------------------------
CREATE TABLE if not exists `item_price` (
  `idItem_price` int(11) NOT NULL,
  `Item_Id` int(11) NOT NULL,
  `Currency_Id` int(11) NOT NULL DEFAULT '0',
  `Price` decimal(12,2) NOT NULL DEFAULT '0.0',
  `ModelCode` VARCHAR(5) NOT NULL DEFAULT '',
  PRIMARY KEY (`idItem_price`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `item_type`
-- -----------------------------------------------------
CREATE TABLE if not exists `item_type`
(
    `idItem_Type` INTEGER NOT NULL AUTO_INCREMENT,
    `Category_Type` INTEGER NOT NULL,
    `Type_Description` VARCHAR(100),
    `Internal` BIT NOT NULL,
    `Order_Line_Type_Id` INTEGER NOT NULL,
    PRIMARY KEY(`idItem_Type`)
) ENGINE=InnoDB;


-- -----------------------------------------------------------------------
-- Table item_type_map
-- -----------------------------------------------------------------------
CREATE TABLE if not exists `item_type_map`
(
    `Item_Id` INTEGER,
    `Type_Id` INTEGER
) ENGINE=MyISAM;

-- -----------------------------------------------------
-- Table `labels`
-- -----------------------------------------------------
CREATE TABLE if not exists `labels` (
  `idLabel` int(11) NOT NULL AUTO_INCREMENT,
  `Key` varchar(25) NOT NULL,
  `Value` varchar(500) NOT NULL DEFAULT '',
  `Type` varchar(15) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Header` VARCHAR(5) NOT NULL DEFAULT '',
  `Description` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`idLabel`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `language`
-- -----------------------------------------------------
CREATE TABLE if not exists `language` (
  `idLanguage` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) NOT NULL,
  `ISO_639_1` varchar(5) NOT NULL,
  `Display` INT(1) NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`idLanguage`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `location`
-- -----------------------------------------------------
CREATE TABLE if not exists `location` (
  `idLocation` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(45) DEFAULT '',
  `Description` varchar(245) DEFAULT '',
  `Status` varchar(5) DEFAULT '',
  `Address` varchar(145) NOT NULL DEFAULT '',
  `Merchant` varchar(45) NOT NULL DEFAULT '',
  `Map` varchar(510) NOT NULL DEFAULT '',
  `Owner_Id` int(11) NOT NULL DEFAULT '0',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idLocation`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `lookups`
-- -----------------------------------------------------
CREATE TABLE if not exists `lookups` (
  `Category` varchar(45) NOT NULL,
  `Code` varchar(45) NOT NULL,
  `Title` varchar(255) NOT NULL DEFAULT '',
  `Use` varchar(2) NOT NULL DEFAULT '',
  `Show` varchar(4) NOT NULL DEFAULT '',
  `Type` varchar(255) NOT NULL DEFAULT '',
  `Other` varchar(255) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Category`,`Code`)
) ENGINE=InnoDB;





-- -----------------------------------------------------
-- Table `mail_listing`
-- -----------------------------------------------------
CREATE TABLE if not exists `mail_listing` (
  `id` int(11) NOT NULL,
  `mr` varchar(5) NOT NULL DEFAULT '',
  `adr_frag` varchar(200) NOT NULL DEFAULT '',
  `street` varchar(200) NOT NULL DEFAULT '',
  `city` varchar(45) NOT NULL DEFAULT '',
  `state` varchar(45) NOT NULL DEFAULT '',
  `zip` varchar(15) NOT NULL DEFAULT '',
  `sp` int(11) NOT NULL DEFAULT '0',
  `fm` int(11) NOT NULL DEFAULT '0',
  `rel` varchar(5) NOT NULL DEFAULT '',
  `cde` varchar(5) NOT NULL DEFAULT ''
) ENGINE=MyISAM;


-- -----------------------------------------------------
-- Table `map_relations`
-- -----------------------------------------------------
CREATE TABLE if not exists `map_relations` (
  `idmap_relations` int(11) NOT NULL AUTO_INCREMENT,
  `PG_Patient` varchar(45) NOT NULL,
  `Guest_Patient` varchar(45) DEFAULT NULL,
  `Patient_PG` varchar(45) DEFAULT NULL,
  `Guest_PG` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idmap_relations`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `mcalendar`
-- -----------------------------------------------------
CREATE TABLE if not exists `mcalendar` (
  `idmcalendar` int(11) NOT NULL AUTO_INCREMENT,
  `E_Id` varchar(100) NOT NULL DEFAULT '',
  `idName` int(11) NOT NULL DEFAULT '0',
  `idName2` int(11) NOT NULL DEFAULT '0',
  `E_Title` varchar(45) NOT NULL DEFAULT '',
  `E_Start` datetime NOT NULL,
  `E_End` datetime DEFAULT NULL,
  `E_URL` varchar(145) NOT NULL DEFAULT '',
  `E_ClassName` varchar(45) NOT NULL DEFAULT '',
  `E_Editable` bit(1) NOT NULL DEFAULT b'0',
  `E_Source` varchar(244) NOT NULL DEFAULT '',
  `E_Description` text,
  `E_AllDay` bit(1) NOT NULL DEFAULT b'0',
  `E_Vol_Category` varchar(45) NOT NULL DEFAULT '',
  `E_Vol_Code` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(100) NOT NULL DEFAULT '',
  `E_Status` varchar(4) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `E_Take_Overable` bit(1) NOT NULL DEFAULT b'0',
  `E_Fixed_In_Time` bit(1) NOT NULL DEFAULT b'0',
  `E_Shell` bit(1) NOT NULL DEFAULT b'0',
  `E_Locked` bit(1) NOT NULL DEFAULT b'0',
  `E_Shell_Id` int(11) NOT NULL DEFAULT '0',
  `E_Rpt_Id` int(11) NOT NULL DEFAULT '0',
  `E_Show_All` bit(1) NOT NULL DEFAULT b'0',
  PRIMARY KEY (`idmcalendar`),
  KEY `idName_INDEX` (`idName`)
) ENGINE=InnoDB;




-- -----------------------------------------------------
-- Table `member_history`
-- -----------------------------------------------------
CREATE TABLE if not exists `member_history` (
  `idName` int(11) NOT NULL,
  `Admin_Access_Date` datetime DEFAULT NULL,
  `Guest_Access_Date` datetime DEFAULT NULL,
  PRIMARY KEY (`idName`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `name`
-- -----------------------------------------------------
CREATE TABLE if not exists `name` (
  `idName` int(11) NOT NULL AUTO_INCREMENT,
  `Name_First` varchar(45) NOT NULL DEFAULT '',
  `Name_Last` varchar(45) NOT NULL DEFAULT '',
  `Name_Middle` varchar(45) NOT NULL DEFAULT '',
  `Name_Nickname` varchar(45) NOT NULL DEFAULT '',
  `Name_Full` varchar(170) NOT NULL DEFAULT '',
  `Name_Previous` varchar(45) NOT NULL DEFAULT '',
  `Web_Site` varchar(145) NOT NULL DEFAULT '',
  `Member_Since` datetime DEFAULT NULL,
  `Member_Type` varchar(15) NOT NULL DEFAULT '',
  `Member_Status` varchar(15) NOT NULL DEFAULT '',
  `Member_Category` varchar(45) NOT NULL DEFAULT '',
  `Preferred_Mail_Address` varchar(5) NOT NULL DEFAULT '',
  `Preferred_Email` varchar(5) NOT NULL DEFAULT '',
  `Preferred_Phone` varchar(5) NOT NULL DEFAULT '',
  `Organization_Code` varchar(15) NOT NULL DEFAULT '',
  `External_Id` VARCHAR(25) NOT NULL DEFAULT '',
  `Company_Id` int(11) NOT NULL DEFAULT '0',
  `Title` varchar(75) NOT NULL DEFAULT '',
  `Company` varchar(80) NOT NULL DEFAULT '',
  `Company_CareOf` varchar(4) NOT NULL DEFAULT '',
  `Record_Member` bit(1) NOT NULL DEFAULT b'0',
  `Record_Company` bit(1) NOT NULL DEFAULT b'0',
  `Previous_Member_Type` varchar(15) NOT NULL DEFAULT '',
  `Prev_MT_Change_Date` datetime DEFAULT NULL,
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Exclude_Directory` bit(1) NOT NULL DEFAULT b'0',
  `Exclude_Mail` bit(1) NOT NULL DEFAULT b'0',
  `Exclude_Email` bit(1) NOT NULL DEFAULT b'0',
  `Exclude_Phone` bit(1) NOT NULL DEFAULT b'0',
  `Date_Added` datetime DEFAULT NULL,
  `Gender` varchar(5) NOT NULL DEFAULT '',
  `BirthDate` datetime DEFAULT NULL,
  `Member_Status_Date` datetime DEFAULT NULL,
  `Date_Deceased` DATETIME NULL DEFAULT NULL,
  `Name_Suffix` varchar(10) NOT NULL DEFAULT '',
  `Name_Prefix` varchar(25) NOT NULL DEFAULT '',
  `Name_Last_First` varchar(90) NOT NULL DEFAULT '',
  `Birth_Month` int(11) NOT NULL DEFAULT '0',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`)
) ENGINE=InnoDB AUTO_INCREMENT=10;





-- -----------------------------------------------------
-- Table `name_address`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_address` (
  `idName_Address` int(11) NOT NULL AUTO_INCREMENT,
  `idName` int(11) NOT NULL,
  `Purpose` varchar(25) NOT NULL DEFAULT '',
  `Address_1` varchar(200) NOT NULL DEFAULT '',
  `Address_2` varchar(45) NOT NULL DEFAULT '',
  `City` varchar(45) NOT NULL DEFAULT '',
  `State_Province` varchar(45) NOT NULL DEFAULT '',
  `Postal_Code` varchar(15) NOT NULL DEFAULT '',
  `Country` varchar(45) NOT NULL DEFAULT '',
  `Country_Code` varchar(10) NOT NULL DEFAULT '',
  `Address_Format` bit(1) NOT NULL DEFAULT b'0',
  `County` varchar(45) NOT NULL DEFAULT '',
  `Mail_Code` varchar(5) NOT NULL DEFAULT '',
  `Status` varchar(15) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Set_Incomplete` bit(1) NOT NULL DEFAULT b'0',
  `Last_Verified` datetime DEFAULT NULL,
  `Bad_Address` varchar(15) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName_Address`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `name_crypto`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_crypto` (
  `idname_crypto` int(11) NOT NULL AUTO_INCREMENT,
  `idName` varchar(45) NOT NULL,
  `Gen_Notes` text,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Contact_Date` date DEFAULT NULL,
  `Created_On` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idname_crypto`),
  KEY `name_Indx` (`idName`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `name_customer`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_customer` (
  `idName_customer` int(11) NOT NULL AUTO_INCREMENT,
  `idName` int(11) NOT NULL,
  `idGroup`  int(11) NOT NULL DEFAULT 0,
  `Auto_Payment_Type` int(11) NOT NULL DEFAULT 0,
  `Due_Date_Unit_Id` int(11) NOT NULL DEFAULT 0,
  `Due_Date_Value` int(11) NOT NULL DEFAULT 0,
  `Current_Order_Id` int(11) NOT NULL DEFAULT 0,
  `Balance_Type` int(11) NOT NULL DEFAULT 0,
  `Dynamic_Balance` decimal(12,6) NOT NULL DEFAULT '0.00',
  `Credit_Limit` decimal(12,6) NOT NULL DEFAULT '0.00',
  `Auto_Recharage` decimal(12,6) NOT NULL DEFAULT '0.00',
  `Notes` varchar(1000) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName_customer`)
) ENGINE=InnoDB;




-- -----------------------------------------------------
-- Table `name_demog`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_demog` (
  `idName` int(11) NOT NULL,
  `Steering_Committee` tinyint(1) NOT NULL DEFAULT '0',
  `Newsletter` varchar(5) NOT NULL DEFAULT '',
  `Key_Contact` tinyint(1) NOT NULL DEFAULT '0',
  `Legislative` tinyint(1) NOT NULL DEFAULT '0',
  `Photo_Permission` varchar(5) NOT NULL DEFAULT '',
  `Guest_Photo_Id` INT NOT NULL DEFAULT 0,
  `Gen_Notes` text,
  `Contact_Date` date DEFAULT NULL,
  `Orientation` tinyint(1) NOT NULL DEFAULT '0',
  `Orientation_Date` date DEFAULT NULL,
  `Confirmed_Date` DATETIME NULL DEFAULT NULL,
  `Media_Source` varchar(5) NOT NULL DEFAULT '',
  `No_Return` VARCHAR(5) NOT NULL DEFAULT '',
  `Age_Bracket` varchar(5) NOT NULL DEFAULT '',
  `Race` varchar(5) NOT NULL DEFAULT '',
  `Ethnicity` varchar(5) NOT NULL DEFAULT '',
  `Income_Bracket` varchar(5) NOT NULL DEFAULT '',
  `Education_Level` varchar(5) NOT NULL DEFAULT '',
  `Special_Needs` varchar(5) NOT NULL DEFAULT '',
  `Covid` varchar(5) NOT NULL DEFAULT '',
  `Gl_Code_Debit` VARCHAR(25) NOT NULL DEFAULT '' ,
  `Gl_Code_Credit` VARCHAR(25) NOT NULL DEFAULT '',
  `tax_exempt` TINYINT NOT NULL DEFAULT 0,
  `Background_Check_Date` DATE DEFAULT NULL,
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`)
) ENGINE=InnoDB;




-- -----------------------------------------------------
-- Table `name_email`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_email` (
  `idName` int(11) NOT NULL,
  `Purpose` varchar(25) NOT NULL DEFAULT '',
  `Email` varchar(140) NOT NULL DEFAULT '',
  `Bad_Address` varchar(15) NOT NULL DEFAULT '',
  `Last_Verified` date DEFAULT NULL,
  `Last_Updated` date DEFAULT NULL,
  `Updated_By` varchar(45) DEFAULT NULL,
  `Status` varchar(15) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`,`Purpose`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `name_guest`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_guest` (
  `idName` int(11) NOT NULL,
  `idPsg` int(11) NOT NULL,
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Legal_Custody` int(11) NOT NULL DEFAULT '0',
  `Relationship_Code` varchar(5) NOT NULL DEFAULT '',
  `Type` varchar(45) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`,`idPsg`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `name_insurance`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_insurance` (
  `idName` INT NOT NULL COMMENT '',
  `Insurance_Id` INT NOT NULL COMMENT '',
  `Member_Num` VARCHAR(100) NOT NULL DEFAULT '',
  `Group_Num` VARCHAR(100) NOT NULL DEFAULT '',
  `Primary` INT(1) NOT NULL DEFAULT 0,
  `Status` varchar(4) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '',
  PRIMARY KEY (`idName`, `Insurance_Id`)
) ENGINE=InnoDB;




-- -----------------------------------------------------
-- Table `name_language`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_language` (
  `idName` INT(11) NOT NULL,
  `Language_Id` INT(11) NOT NULL,
  `Mother_Tongue` INT(1) NOT NULL DEFAULT 0,
  `Proficiency` varchar(4) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`,`Language_Id`)
) ENGINE=InnoDB;




-- -----------------------------------------------------
-- Table `name_log`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_log` (
  `Date_Time` datetime NOT NULL,
  `Log_Type` varchar(45) NOT NULL DEFAULT '',
  `Sub_Type` varchar(45) NOT NULL DEFAULT '',
  `WP_User_Id` varchar(45) NOT NULL DEFAULT '',
  `idName` varchar(15) NOT NULL DEFAULT '',
  `Log_Text` varchar(255) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM;




-- -----------------------------------------------------
-- Table `name_phone`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_phone` (
  `idName` int(11) NOT NULL,
  `Phone_Num` varchar(45) NOT NULL DEFAULT '',
  `Phone_Extension` varchar(15) NOT NULL DEFAULT '',
  `Phone_Code` varchar(5) NOT NULL,
  `Phone_Search` varchar(25) NOT NULL DEFAULT '',
  `Status` varchar(15) NOT NULL DEFAULT '',
  `is_Mobile` bit(1) NOT NULL DEFAULT b'0',
  `is_Toll_Free` bit(1) NOT NULL DEFAULT b'0',
  `is_International` bit(1) NOT NULL DEFAULT b'0',
  `is_Land_Line` bit(1) NOT NULL DEFAULT b'0',
  `is_Personal` bit(1) NOT NULL DEFAULT b'0',
  `is_Party_Line` bit(1) NOT NULL DEFAULT b'0',
  `is_SMS` bit(1) NOT NULL DEFAULT b'0',
  `Carrier` varchar(45) NOT NULL DEFAULT '',
  `Bad_Number` varchar(15) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`,`Phone_Code`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `name_volunteer2`
-- -----------------------------------------------------
CREATE TABLE if not exists `name_volunteer2` (
  `idName` int(11) NOT NULL,
  `Vol_Category` varchar(15) NOT NULL,
  `Vol_Code` varchar(5) NOT NULL,
  `Vol_Status` varchar(5) NOT NULL,
  `Vol_Availability` varchar(5) NOT NULL DEFAULT '',
  `Vol_Notes` text,
  `Vol_Begin` date DEFAULT NULL,
  `Vol_End` date DEFAULT NULL,
  `Vol_Check_Date` date DEFAULT NULL,
  `Dormant_Code` varchar(45) NOT NULL DEFAULT '',
  `Updated_By` varchar(25) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Vol_Rank` varchar(45) NOT NULL DEFAULT '',
  `Vol_Training_Date` date DEFAULT NULL,
  `Vol_License` varchar(25) NOT NULL DEFAULT '',
  `Vol_Trainer` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`,`Vol_Category`,`Vol_Code`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `neon_lists`
-- -----------------------------------------------------
CREATE TABLE if not exists `neon_lists` (
  `Method` VARCHAR(45) NOT NULL DEFAULT '',
  `List_Name` VARCHAR(45) NOT NULL,
  `List_Item` VARCHAR(45) NOT NULL DEFAULT '',
  `HHK_Lookup` VARCHAR(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`List_Name`)
) ENGINE = MyISAM;


-- -----------------------------------------------------
-- Table `neon_type_map`
-- -----------------------------------------------------
CREATE TABLE if not exists `neon_type_map` (
  `idNeon_type_map` INT NOT NULL AUTO_INCREMENT,
  `List_Name` VARCHAR(45) NOT NULL,
  `Neon_Name` VARCHAR(45) NOT NULL DEFAULT '',
  `Neon_Type_Code` VARCHAR(45) NOT NULL DEFAULT '',
  `Neon_Type_Name` VARCHAR(45) NOT NULL DEFAULT '',
  `HHK_Type_Code` VARCHAR(45) NOT NULL DEFAULT '',
  `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
  `Last_Updated` DATETIME NULL,
  `Timestamp` TIMESTAMP NOT NULL DEFAULT now(),
  PRIMARY KEY (`idNeon_type_map`)
 ) ENGINE=MyISAM;


-- -----------------------------------------------------
-- Table `note`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `note` (
  `idNote` INT NOT NULL AUTO_INCREMENT,
  `User_Name` VARCHAR(45) NOT NULL,
  `Note_Type` VARCHAR(15) NULL,
  `flag` BOOL DEFAULT FALSE,
  `Title` VARCHAR(145) NULL,
  `Note_Text` TEXT NULL,
  `Updated_By` VARCHAR(45) NULL,
  `Last_Updated` DATETIME NULL,
  `Status` VARCHAR(5) NOT NULL DEFAULT 'a',
  `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idNote`)
 ) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `note_group`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `note_group` (
  `Note_Id` INT NOT NULL,
  `Group_Id` VARCHAR(5) NOT NULL,
  PRIMARY KEY (`Note_Id`, `Group_Id`)
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `note_category`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `note_category` (
  `Note_Id` INT NOT NULL,
  `Category_Code` VARCHAR(5) NOT NULL,
  PRIMARY KEY (`Note_Id`, `Category_Code`)
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `reservation_note`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservation_note` (
  `Reservation_Id` INT NOT NULL,
  `Note_Id` INT NOT NULL,
  PRIMARY KEY (`Reservation_Id`, `Note_Id`)
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `psg_note`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `psg_note` (
  `Psg_Id` INT NOT NULL,
  `Note_Id` INT NOT NULL,
  PRIMARY KEY (`Psg_Id`, `Note_Id`)
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `member_note`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `member_note` (
  `idName` INT NOT NULL,
  `Note_Id` INT NOT NULL,
  PRIMARY KEY (`idName`, `Note_Id`)
) ENGINE = InnoDB;



-- -----------------------------------------------------
-- Table `page`
-- -----------------------------------------------------
CREATE TABLE if not exists `page` (
  `idPage` int(11) NOT NULL AUTO_INCREMENT,
  `File_Name` varchar(65) NOT NULL,
  `Login_Page_Id` int(11) NOT NULL DEFAULT '0',
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Product_Code` VARCHAR(4) NOT NULL DEFAULT '',
  `Hide` INT(1) NOT NULL DEFAULT 0,
  `Web_Site` varchar(5) NOT NULL DEFAULT '',
  `Menu_Parent` varchar(45) NOT NULL DEFAULT '',
  `Menu_Position` varchar(45) NOT NULL DEFAULT '',
  `Type` varchar(5) NOT NULL DEFAULT '',
  `Validity_Code` varchar(75) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPage`)
) ENGINE=InnoDB AUTO_INCREMENT=5;




-- -----------------------------------------------------
-- Table `page_securitygroup`
-- -----------------------------------------------------
CREATE TABLE if not exists `page_securitygroup` (
  `idPage` int(11) NOT NULL,
  `Group_Code` varchar(5) NOT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPage`,`Group_Code`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `payment`
-- -----------------------------------------------------
CREATE TABLE if not exists `payment` (
  `idPayment` int(11) NOT NULL AUTO_INCREMENT,
  `Attempt` int(11) DEFAULT NULL,
  `Amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Result` varchar(24) NOT NULL DEFAULT '',
  `Financial_Entity_Id` int(11) NOT NULL DEFAULT '0',
  `Payment_Date` datetime DEFAULT NULL,
  `idPayor` int(11) NOT NULL DEFAULT '0',
  `idPayment_Method` int(11) NOT NULL DEFAULT '0',
  `idTrans` int(11) NOT NULL DEFAULT '0',
  `idToken` int(11) NOT NULL DEFAULT '0',
  `Is_Refund` tinyint(4) NOT NULL DEFAULT '0',
  `Is_Preauth` tinyint(4) NOT NULL DEFAULT '0',
  `Status_Code` varchar(5) NOT NULL DEFAULT '',
  `Data1` varchar(15) NOT NULL DEFAULT '',
  `Data2` varchar(15) NOT NULL DEFAULT '',
  `Notes` TEXT NULL DEFAULT NULL,
  `External_Id` VARCHAR(45) NOT NULL DEFAULT '',
  `Created_By` varchar(45) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPayment`)
) ENGINE=InnoDB AUTO_INCREMENT=7;



-- -----------------------------------------------------
-- Table `payment_auth`
-- -----------------------------------------------------
CREATE TABLE if not exists `payment_auth` (
  `idPayment_auth` int(11) NOT NULL AUTO_INCREMENT,
  `idPayment` int(11) NOT NULL DEFAULT '0',
  `idTrans` int(11) NOT NULL DEFAULT '0',
  `Processor` varchar(45) NOT NULL DEFAULT '',
  `Merchant` VARCHAR(45) NOT NULL DEFAULT '',
  `Approved_Amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Approval_Code` varchar(20) NOT NULL DEFAULT '',
  `Status_Message` varchar(45) NOT NULL DEFAULT '',
  `AVS` varchar(20) NOT NULL DEFAULT '',
  `Invoice_Number` varchar(45) NOT NULL DEFAULT '',
  `Acct_Number` VARCHAR(25) NOT NULL DEFAULT '',
  `Card_Type` VARCHAR(10) NOT NULL DEFAULT '',
  `Cardholder_Name` VARCHAR(45) NOT NULL DEFAULT '',
  `Customer_Id` varchar(45) NOT NULL DEFAULT '',
  `Response_Message` varchar(200) NOT NULL DEFAULT '',
  `Response_Code` varchar(45) NOT NULL DEFAULT '',
  `Reference_Num` varchar(45) NOT NULL DEFAULT '',
  `AcqRefData` varchar(200) NOT NULL DEFAULT '',
  `ProcessData` varchar(200) NOT NULL DEFAULT '',
  `Signature_Required` INT(4) NOT NULL DEFAULT 1,
  `PartialPayment` INT(4) NOT NULL DEFAULT 0,
  `CVV` varchar(45) NOT NULL DEFAULT '',
  `Serialized_Details` varchar(1000) NOT NULL DEFAULT '',
  `Status_Code` varchar(5) NOT NULL DEFAULT '',
  `EMVApplicationIdentifier` varchar(200) DEFAULT '',
  `EMVTerminalVerificationResults` varchar(200) DEFAULT '',
  `EMVIssuerApplicationData` varchar(200) DEFAULT '',
  `EMVTransactionStatusInformation` varchar(200) DEFAULT '',
  `EMVApplicationResponseCode` varchar(200) DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPayment_auth`)
) ENGINE=InnoDB AUTO_INCREMENT=7;



-- -----------------------------------------------------
-- Table ``paymentid_externalid`
-- -----------------------------------------------------
CREATE TABLE if not exists `paymentid_externalid` (
  `Payment_Id` INT NOT NULL,
  `External_Id` VARCHAR(15) NOT NULL,
  `TimeStamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Payment_Id`, `External_Id`)
 ) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `payment_info_check`
-- -----------------------------------------------------
CREATE TABLE if not exists `payment_info_check` (
  `idpayment_info_Check` int(11) NOT NULL AUTO_INCREMENT,
  `idPayment` int(11) NOT NULL,
  `Bank` varchar(45) NOT NULL DEFAULT '',
  `Check_Number` varchar(45) NOT NULL DEFAULT '',
  `Check_Date` date DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idpayment_info_Check`)
) ENGINE=InnoDB  AUTO_INCREMENT=7;



-- -----------------------------------------------------------------------
-- Table payment_invoice
-- -----------------------------------------------------------------------
CREATE TABLE if not exists `payment_invoice`
(
    `idPayment_Invoice` INTEGER NOT NULL AUTO_INCREMENT,
    `Payment_Id` INTEGER NOT NULL DEFAULT 0,
    `Invoice_Id` INTEGER NOT NULL DEFAULT 0,
    `Amount` DECIMAL(22,10) NOT NULL DEFAULT 0.00,
    `Create_Datetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`idPayment_Invoice`),
    INDEX ix_uq_payment_inv_map_pa_in (Payment_Id, Invoice_Id)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `payment_method`
-- -----------------------------------------------------
CREATE TABLE if not exists `payment_method` (
  `idPayment_method` int(11) NOT NULL AUTO_INCREMENT,
  `Method_Name` varchar(45) NOT NULL DEFAULT '',
  `Gl_Code` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPayment_method`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `photo`
-- -----------------------------------------------------
CREATE TABLE if not exists `photo` (
  `idPhoto` INT NOT NULL AUTO_INCREMENT,
  `Image_Type` VARCHAR(45) NOT NULL DEFAULT '',
  `Category` VARCHAR(5) NOT NULL DEFAULT '',
  `Image` BLOB NULL,
  `Last_Updated` DATETIME NULL,
  `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPhoto`)
  ) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `postal_codes`
-- -----------------------------------------------------
CREATE  TABLE if not exists `postal_codes` (
  `Zip_Code` VARCHAR(9) NOT NULL ,
  `City` VARCHAR(45) NOT NULL DEFAULT '' ,
  `County` VARCHAR(45) NOT NULL DEFAULT '' ,
  `State` VARCHAR(4) NOT NULL DEFAULT '' ,
  `Lat` VARCHAR(15) NOT NULL DEFAULT '' ,
  `Lng` VARCHAR(15) NOT NULL DEFAULT '' ,
  `Type` VARCHAR(15) NOT NULL DEFAULT '' ,
  `Acceptable_Cities` VARCHAR(511) NOT NULL DEFAULT '' ,
  PRIMARY KEY (`Zip_Code`) 
  ) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `psg`
-- -----------------------------------------------------
CREATE TABLE if not exists `psg` (
  `idPsg` INT(11) NOT NULL AUTO_INCREMENT ,
  `idPatient` INT NOT NULL DEFAULT 0 ,
  `Title` VARCHAR(45) NOT NULL DEFAULT '' ,
  `Status` VARCHAR(5) NOT NULL DEFAULT '' ,
  `Primary_Language` INT NOT NULL DEFAULT 0 COMMENT '',
  `Language_Notes` TEXT NULL DEFAULT NULL COMMENT '' ,
  `Info_Last_Confirmed` DATETIME NULL DEFAULT NULL,
  `Notes` TEXT NULL DEFAULT NULL ,
  `Last_Updated` DATETIME NULL DEFAULT NULL ,
  `Updated_By` VARCHAR(45) NOT NULL DEFAULT '' ,
  `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  PRIMARY KEY (`idPsg`) )
ENGINE = InnoDB AUTO_INCREMENT = 10;



-- -----------------------------------------------------
-- Table `rate_breakpoint`
-- -----------------------------------------------------
CREATE TABLE if not exists `rate_breakpoint` (
  `idrate_breakpoint` INT NOT NULL AUTO_INCREMENT,
  `Household_Size` INT(4) NOT NULL,
  `Rate_Category` VARCHAR(4) NOT NULL,
  `Breakpoint` INT NOT NULL DEFAULT 0,
  `Timestamp` TIMESTAMP NOT NULL DEFAULT Current_Timestamp,
  PRIMARY KEY (`idrate_breakpoint`))
ENGINE = InnoDB AUTO_INCREMENT = 10;



-- -----------------------------------------------------
-- Table `registration`
-- -----------------------------------------------------
CREATE TABLE if not exists `registration` (
  `idRegistration` int(11) NOT NULL AUTO_INCREMENT,
  `idPsg` int(11) NOT NULL,
  `Date_Registered` datetime DEFAULT NULL,
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Sig_Card` int(11) NOT NULL DEFAULT '0',
  `Pamphlet` int(11) NOT NULL DEFAULT '0',
  `Email_Receipt` tinyint(4) NOT NULL DEFAULT '0',
  `Pref_Token_Id` INT(11) NOT NULL DEFAULT 0,
  `Referral` int(11) NOT NULL DEFAULT '0',
  `Vehicle` int(1) NOT NULL DEFAULT '0',
  `Guest_Ident` varchar(45) NOT NULL DEFAULT '',
  `Key_Deposit_Bal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Lodging_Balance` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `Notes` text,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idRegistration`,`idPsg`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `relationship`
-- -----------------------------------------------------
CREATE TABLE if not exists `relationship` (
  `idRelationship` int(11) NOT NULL AUTO_INCREMENT,
  `idName` int(11) NOT NULL,
  `Target_Id` int(11) NOT NULL DEFAULT '0',
  `Relation_Type` varchar(5) NOT NULL DEFAULT '',
  `Status` varchar(45) NOT NULL DEFAULT '',
  `Principal` bit(1) NOT NULL DEFAULT b'0',
  `Effective_Date` date DEFAULT NULL,
  `Thru_date` date DEFAULT NULL,
  `Note` text,
  `Date_Added` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Group_Code` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idRelationship`),
  UNIQUE KEY `idRelationship_UNIQUE` (`idRelationship`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `report`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `report` (
  `idReport` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Title` varchar(240) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Report_Date` datetime DEFAULT NULL,
  `Resolution_Date` datetime DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Resolution` text DEFAULT NULL,
  `Signature` blob,
  `Signature_Date` datetime DEFAULT NULL,
  `Author` varchar(45) NOT NULL DEFAULT '',
  `Guest_Id` int(11) NOT NULL DEFAULT '0',
  `Psg_Id` int(11) NOT NULL DEFAULT '0',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idReport`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `report_field_sets`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_field_sets` (
  `idFieldSet` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Report` varchar(45) NOT NULL DEFAULT '',
  `Fields` longtext,
  `Global` tinyint(4) DEFAULT '0',
  `Updated_by` varchar(45) DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Created_by` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idFieldSet`)
) ENGINE=InnoDB;
  

-- -----------------------------------------------------
-- Table `reservation`
-- -----------------------------------------------------
CREATE TABLE if not exists `reservation` (
  `idReservation` int(11) NOT NULL AUTO_INCREMENT,
  `idRegistration` int(11) NOT NULL DEFAULT '0',
  `idGuest` int(11) NOT NULL DEFAULT '0',
  `idHospital_Stay` int(11) NOT NULL DEFAULT '0',
  `idResource` int(11) NOT NULL DEFAULT '0',
  `idReferralDoc` INT(11) NOT NULL DEFAULT '0',
  `Resource_Suitable` VARCHAR(4) NOT NULL DEFAULT '',
  `Confirmation` varchar(4) NOT NULL DEFAULT '',
  `Room_Rate_Category` varchar(4) NOT NULL DEFAULT '',
  `Fixed_Room_Rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Rate_Adjust` decimal(10,2) NOT NULL DEFAULT '0.00',
  `idRateAdjust` varchar(5) NOT NULL DEFAULT '0',
  `Visit_Fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `idRoom_rate` int(11) NOT NULL DEFAULT '0',
  `Title` varchar(145) NOT NULL DEFAULT '',
  `Type` varchar(45) NOT NULL DEFAULT '',
  `Expected_Pay_Type` varchar(4) NOT NULL DEFAULT '',
  `Expected_Arrival` datetime DEFAULT NULL,
  `Expected_Departure` datetime DEFAULT NULL,
  `Actual_Arrival` datetime DEFAULT NULL,
  `Actual_Departure` datetime DEFAULT NULL,
  `Number_Guests` int(11) NOT NULL DEFAULT '0',
  `Add_Room` INT NOT NULL DEFAULT 0,
  `Checkin_Notes` varchar(1000) NOT NULL DEFAULT '',
  `Notes` text,
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idReservation`)
) ENGINE=InnoDB AUTO_INCREMENT=10;


-- -----------------------------------------------------
-- Table `reservation_guest`
-- -----------------------------------------------------
CREATE TABLE if not exists `reservation_guest` (
  `idReservation` int(11) NOT NULL,
  `idGuest` int(11) NOT NULL,
  `Primary_Guest` varchar(2) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idReservation`,`idGuest`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `reservation_log`
-- -----------------------------------------------------
CREATE TABLE if not exists `reservation_log` (
  `Log_Type` varchar(45) NOT NULL,
  `Sub_Type` varchar(45) NOT NULL,
  `User_Name` varchar(45) NOT NULL DEFAULT '',
  `idName` int(11) NOT NULL DEFAULT '0',
  `idPsg` int(11) NOT NULL DEFAULT '0',
  `idRegistration` int(11) NOT NULL DEFAULT '0',
  `idHospital` int(11) NOT NULL DEFAULT '0',
  `idAgent` int(11) DEFAULT '0',
  `idHospital_stay` int(11) NOT NULL DEFAULT '0',
  `idReservation` int(11) NOT NULL DEFAULT '0',
  `idSpan` int(11) NOT NULL DEFAULT '0',
  `idRoom_rate` int(11) NOT NULL DEFAULT '0',
  `idResource` int(11) NOT NULL DEFAULT '0',
  `Log_Text` varchar(5000) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM;



-- -----------------------------------------------------
-- Table `resource`
-- -----------------------------------------------------
CREATE TABLE if not exists `resource` (
  `idResource` int(11) NOT NULL AUTO_INCREMENT,
  `idSponsor` int(11) NOT NULL DEFAULT '0',
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Utilization_Category` VARCHAR(5) NOT NULL DEFAULT '',
  `Color` varchar(15) NOT NULL DEFAULT '',
  `Background_Color` varchar(15) NOT NULL DEFAULT '',
  `Text_Color` varchar(15) NOT NULL DEFAULT '',
  `Border_Color` varchar(15) NOT NULL DEFAULT '',
  `Type` varchar(15) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Partition_Size` varchar(5) NOT NULL DEFAULT '',
  `Util_Priority` varchar(5) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Rate_Adjust` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Rate_Adjust_Code` varchar(15) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idResource`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `resource_room`
-- -----------------------------------------------------
CREATE TABLE if not exists `resource_room` (
  `idResource_room` int(11) NOT NULL AUTO_INCREMENT,
  `idResource` int(11) NOT NULL,
  `idRoom` int(11) NOT NULL,
  PRIMARY KEY (`idResource_room`)
) ENGINE=InnoDB AUTO_INCREMENT = 10;



-- -----------------------------------------------------
-- Table `resource_use`
-- -----------------------------------------------------
CREATE TABLE if not exists `resource_use` (
  `idResource_use` int(11) NOT NULL AUTO_INCREMENT,
  `idResource` int(11) NOT NULL DEFAULT '0',
  `idRoom` int(11) NOT NULL DEFAULT '0',
  `Start_Date` datetime DEFAULT NULL,
  `End_Date` datetime DEFAULT NULL,
  `Status` varchar(5) NOT NULL DEFAULT '',
  `OOS_Code` varchar(5) NOT NULL DEFAULT '',
  `Room_Status` varchar(5) NOT NULL DEFAULT '',
  `Room_State` varchar(5) NOT NULL DEFAULT '',
  `Room_Availability` varchar(5) NOT NULL DEFAULT '',
  `Notes` varchar(245) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idResource_use`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `room`
-- -----------------------------------------------------
CREATE TABLE if not exists `room` (
  `idRoom` int(11) NOT NULL AUTO_INCREMENT,
  `idHouse` int(11) NOT NULL DEFAULT '0',
  `Item_Id` int(11) NOT NULL DEFAULT '0',
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Description` text,
  `Notes` text,
  `Type` varchar(15) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Report_Category` varchar(5) NOT NULL DEFAULT '',
  `Util_Priority` varchar(5) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `State` varchar(15) NOT NULL DEFAULT '',
  `Availability` varchar(15) NOT NULL DEFAULT '',
  `Max_Occupants` int(11) NOT NULL DEFAULT '0',
  `Min_Occupants` int(11) NOT NULL DEFAULT '0',
  `Beds_King` int(11) NOT NULL DEFAULT '0',
  `Beds_Queen` int(11) NOT NULL DEFAULT '0',
  `Beds_Utility` INT NOT NULL DEFAULT 0,
  `Beds_Full` int(11) NOT NULL DEFAULT '0',
  `Beds_Twin` int(11) NOT NULL DEFAULT '0',
  `Phone` varchar(15) NOT NULL DEFAULT '',
  `Floor` varchar(15) NOT NULL DEFAULT '',
  `idLocation` int(11) NOT NULL DEFAULT '1',
  `Owner_Id` int(11) NOT NULL DEFAULT '0',
  `Last_Cleaned` datetime DEFAULT NULL,
  `Last_Deep_Clean` datetime DEFAULT NULL,
  `Visit_Fee_Code` varchar(5) NOT NULL DEFAULT '',
  `Rate_Code` varchar(5) NOT NULL DEFAULT '',
  `Default_Rate_Category` VARCHAR(5) NOT NULL DEFAULT '',
  `Key_Deposit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `Key_Deposit_Code` varchar(5) NOT NULL DEFAULT '',
  `Cleaning_Cycle_Code` VARCHAR(5) NOT NULL DEFAULT 'a',
  `Image` blob,
  `Image_Tn` blob,
  `Display_Colors` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idRoom`,`idHouse`)
) ENGINE=InnoDB AUTO_INCREMENT=10;




-- -----------------------------------------------------
-- Table `room_log`
-- -----------------------------------------------------
CREATE TABLE if not exists `room_log` (
  `idRoom` int(11) NOT NULL,
  `Date` datetime DEFAULT NULL,
  `User` varchar(45) NOT NULL DEFAULT '',
  `Type` varchar(5) NOT NULL DEFAULT '',
  `Activity` varchar(45) NOT NULL DEFAULT '',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Log_Text` varchar(1000) DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM;




-- -----------------------------------------------------
-- Table `room_rate`
-- -----------------------------------------------------
CREATE TABLE if not exists `room_rate` (
  `idRoom_rate` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Description` varchar(245) NOT NULL DEFAULT '',
  `FA_Category` varchar(2) NOT NULL DEFAULT '',
  `Rate_Breakpoint_Category` varchar(4) NOT NULL DEFAULT '',
  `PriceModel` VARCHAR(5) NOT NULL DEFAULT '',
  `Reduced_Rate_1` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Reduced_Rate_2` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Reduced_Rate_3` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Min_Rate` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `Status` varchar(4) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idRoom_rate`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `secondary_unit_desig`
-- -----------------------------------------------------
CREATE TABLE if not exists `secondary_unit_desig` (
  `Common` varchar(45) NOT NULL,
  `Standard` varchar(6) NOT NULL,
  `Range_Required` bit(1) NOT NULL,
  `TitleCaps` varchar(6) NOT NULL
) ENGINE=MyISAM;




-- -----------------------------------------------------
-- Table `shell_events`
-- -----------------------------------------------------
CREATE TABLE if not exists `shell_events` (
  `idShell` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Description` varchar(145) NOT NULL DEFAULT '',
  `Vol_Cat` varchar(45) NOT NULL DEFAULT '',
  `Vol_Code` varchar(45) NOT NULL DEFAULT '',
  `Time_Start` time DEFAULT NULL,
  `Time_End` time DEFAULT NULL,
  `Date_Start` date DEFAULT NULL,
  `Duration_Code` varchar(4) NOT NULL DEFAULT '',
  `Sun` bit(1) NOT NULL DEFAULT b'0',
  `Mon` bit(1) NOT NULL DEFAULT b'0',
  `Tue` bit(1) NOT NULL DEFAULT b'0',
  `Wed` bit(1) NOT NULL DEFAULT b'0',
  `Thu` bit(1) NOT NULL DEFAULT b'0',
  `Fri` bit(1) NOT NULL DEFAULT b'0',
  `Sat` bit(1) NOT NULL DEFAULT b'0',
  `Skip_Holidays` bit(1) NOT NULL DEFAULT b'0',
  `AllDay` bit(1) NOT NULL DEFAULT b'0',
  `Class_Name` varchar(45) NOT NULL DEFAULT '',
  `URL` varchar(145) NOT NULL DEFAULT '',
  `Status` varchar(4) NOT NULL DEFAULT '',
  `Shell_Color` varchar(45) NOT NULL DEFAULT '',
  `Fixed_In_Time` bit(1) NOT NULL DEFAULT b'0',
  `Take_Overable` bit(1) NOT NULL DEFAULT b'0',
  `Locked` bit(1) NOT NULL DEFAULT b'0',
  PRIMARY KEY (`idShell`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `ssotoken`
-- -----------------------------------------------------
CREATE TABLE if not exists `ssotoken` (
  `Token` varchar(136) NOT NULL,
  `idPaymentAuth` INT NOT NULL DEFAULT 0,
  `idName` int(11) NOT NULL,
  `CardHolderName` VARCHAR(45) NOT NULL DEFAULT '',
  `idGroup` int(11) NOT NULL,
  `InvoiceNumber` varchar(36) NOT NULL DEFAULT '',
  `Amount` DECIMAL(11,2) NOT NULL DEFAULT 0.00,
  `State` varchar(5) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Token`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `stays`
-- -----------------------------------------------------
CREATE TABLE if not exists `stays` (
  `idStays` int(11) NOT NULL AUTO_INCREMENT,
  `idVisit` int(11) NOT NULL,
  `Visit_Span` int(11) NOT NULL,
  `idRoom` int(11) NOT NULL DEFAULT '0',
  `idName` int(11) NOT NULL,
  `Checkin_Date` datetime DEFAULT NULL,
  `Checkout_Date` datetime DEFAULT NULL,
  `Expected_Co_Date` datetime DEFAULT NULL,
  `Span_Start_Date` datetime DEFAULT NULL,
  `Span_End_Date` datetime DEFAULT NULL,
  `Activity_Id` int(11) NOT NULL DEFAULT '0',
  `On_Leave` int(11) NOT NULL DEFAULT '0',
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Recorded` INT(1) NOT NULL DEFAULT '0',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idStays`)
) ENGINE=InnoDB AUTO_INCREMENT = 10;



-- -----------------------------------------------------
-- Table `street_suffix`
-- -----------------------------------------------------
CREATE TABLE if not exists `street_suffix` (
  `Common` varchar(45) DEFAULT NULL,
  `Standard` varchar(5) DEFAULT NULL,
  `TitleCaps` varchar(5) DEFAULT NULL
) ENGINE=MyISAM;




-- -----------------------------------------------------
-- Table `sys_config`
-- -----------------------------------------------------
CREATE TABLE if not exists `sys_config` (
  `Key` varchar(25) NOT NULL,
  `Value` varchar(5000) NOT NULL DEFAULT '',
  `Type` varchar(15) NOT NULL DEFAULT '',
  `Category` varchar(5) NOT NULL DEFAULT '',
  `Header` VARCHAR(5) NOT NULL DEFAULT '',
  `Description` varchar(1000) NOT NULL DEFAULT '',
  `GenLookup` VARCHAR(45) NOT NULL DEFAULT '',
  `Show` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`Key`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `syslog`
-- -----------------------------------------------------
CREATE TABLE if not exists `syslog` (
  `Log_Type` varchar(25) NOT NULL,
  `User` varchar(45)  NOT NULL,
  `Remote_IP` varchar(45)  NOT NULL,
  `Log_Message` varchar(255)  NOT NULL,
  `System_Version` varchar(45)  NOT NULL,
  `GIT_Id` varchar(45)  NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = MyISAM;


-- -----------------------------------------------------
-- Table `template_tag`
-- -----------------------------------------------------
CREATE TABLE if not exists `template_tag` (
  `idTemplate_tag` int(11) NOT NULL AUTO_INCREMENT,
  `Doc_Name` varchar(45) NOT NULL DEFAULT '',
  `Tag_Title` varchar(25) NOT NULL DEFAULT '',
  `Tag_Name` varchar(25) NOT NULL DEFAULT '',
  `Replacement_Wrapper` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`idTemplate_tag`),
  UNIQUE INDEX `Unq_Doc_Tag` (`Doc_Name` ASC, `Tag_Name` ASC)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `trans`
-- -----------------------------------------------------
CREATE TABLE if not exists `trans` (
  `idTrans` int(11) NOT NULL AUTO_INCREMENT,
  `Trans_Type` varchar(5) NOT NULL DEFAULT '',
  `Trans_Method` varchar(5) NOT NULL DEFAULT '',
  `Trans_Date` datetime DEFAULT NULL,
  `idName` int(11) NOT NULL DEFAULT 0,
  `Order_Number` varchar(45) NOT NULL DEFAULT '',
  `Invoice_Number` varchar(45) NOT NULL DEFAULT '',
  `Payment_Type` varchar(15) NOT NULL DEFAULT '',
  `Check_Number` varchar(15) NOT NULL DEFAULT '',
  `Check_Bank` varchar(45) NOT NULL DEFAULT '',
  `Card_Number` varchar(4) NOT NULL DEFAULT '',
  `Card_Expire` varchar(15) NOT NULL DEFAULT '',
  `Card_Authorize` varchar(15) NOT NULL DEFAULT '',
  `Card_Name` varchar(45) NOT NULL DEFAULT '',
  `Auth_Code` varchar(45) NOT NULL DEFAULT '',
  `RefNo` varchar(25) NOT NULL DEFAULT '',
  `Process_Code` varchar(15) NOT NULL DEFAULT '',
  `Gateway_Ref` varchar(45) NOT NULL DEFAULT '',
  `Payment_Status` varchar(15) NOT NULL DEFAULT '',
  `Amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Date_Entered` datetime DEFAULT NULL,
  `Entered_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idTrans`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `transaction_type`
-- -----------------------------------------------------
CREATE TABLE if not exists `transaction_type` (
  `idtransaction_type` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(45) NOT NULL,
  `Effect` varchar(45) NOT NULL DEFAULT '',
  `Code` varchar(5) NOT NULL DEFAULT '',
  PRIMARY KEY (`idtransaction_type`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `vehicle`
-- -----------------------------------------------------
CREATE TABLE if not exists `vehicle` (
  `idVehicle` int(11) NOT NULL AUTO_INCREMENT,
  `idRegistration` int(11) NOT NULL,
  `idName` INT(11) NOT NULL DEFAULT 0 COMMENT '',
  `Make` varchar(45) NOT NULL DEFAULT '',
  `Model` varchar(45) NOT NULL DEFAULT '',
  `Color` varchar(45) NOT NULL DEFAULT '',
  `State_Reg` varchar(2) NOT NULL DEFAULT '',
  `License_Number` varchar(15) NOT NULL DEFAULT '',
  `No_Vehicle` varchar(4) NOT NULL DEFAULT '',
  `Note` VARCHAR(445) NOT NULL DEFAULT '' COMMENT '',
  PRIMARY KEY (`idVehicle`,`idRegistration`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `visit`
-- -----------------------------------------------------
CREATE TABLE if not exists `visit` (
  `idVisit` int(11) NOT NULL AUTO_INCREMENT,
  `Span` int(11) NOT NULL DEFAULT '0',
  `idRegistration` int(11) NOT NULL,
  `idReservation` int(11) NOT NULL DEFAULT '0',
  `idResource` int(11) NOT NULL DEFAULT '0',
  `idPrimaryGuest` int(11) NOT NULL DEFAULT '0',
  `idHospital_stay` int(11) NOT NULL DEFAULT '0',
  `Title` varchar(145) NOT NULL DEFAULT '',
  `Key_Deposit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Key_Dep_Disposition` varchar(4) NOT NULL DEFAULT '',
  `DepositPayType` VARCHAR(5) NOT NULL DEFAULT '',
  `Arrival_Date` datetime DEFAULT NULL,
  `Expected_Departure` datetime DEFAULT NULL,
  `Actual_Departure` datetime DEFAULT NULL,
  `Span_Start` datetime DEFAULT NULL,
  `Span_End` datetime DEFAULT NULL,
  `Return_Date` datetime DEFAULT NULL,
  `Notice_to_Checkout` DATETIME NULL DEFAULT NULL,
  `Expected_Rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Pledged_Rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Amount_Per_Guest` decimal(10,2) NOT NULL DEFAULT '0.00',
  `idRoom_Rate` int(11) NOT NULL DEFAULT '0',
  `Rate_Category` varchar(5) NOT NULL DEFAULT '',
  `Rate_Glide_Credit` int(11) not null default '0',
  `Ext_Phone_Installed` int(1) NOT NULL DEFAULT '0',
  `Medical_Cooler` int(1) NOT NULL DEFAULT '0',
  `Wheel_Chair` int(1) NOT NULL DEFAULT '0',
  `OverRideMaxOcc` int(1) NOT NULL DEFAULT '0',
  `Notes` text,
  `Status` varchar(5) NOT NULL DEFAULT '',
  `Recorded` INT(1) NOT NULL DEFAULT '0',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idVisit`,`Span`)
) ENGINE=InnoDB AUTO_INCREMENT=10;



-- -----------------------------------------------------
-- Table `visit_log`
-- -----------------------------------------------------
CREATE TABLE if not exists `visit_log` (
  `Log_Type` varchar(45) NOT NULL DEFAULT '',
  `Sub_Type` varchar(45) NOT NULL DEFAULT '',
  `User_Name` varchar(45) NOT NULL DEFAULT '',
  `idName` int(11) NOT NULL DEFAULT '0',
  `idPsg` int(11) NOT NULL DEFAULT '0',
  `idRegistration` int(11) NOT NULL DEFAULT '0',
  `idVisit` int(11) NOT NULL DEFAULT '0',
  `Span` int(11) NOT NULL DEFAULT '0',
  `idStay` int(11) NOT NULL DEFAULT '0',
  `idRr` int(11) NOT NULL DEFAULT '0',
  `Status` varchar(15) NOT NULL DEFAULT '',
  `Log_Text` varchar(5000) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `visit_onleave`
-- -----------------------------------------------------
CREATE TABLE if not exists `visit_onleave` (
  `idVisit` int(11) NOT NULL,
  `Span` int(11) NOT NULL DEFAULT '0',
  `Pledged_Rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `Rate_Category` varchar(5) NOT NULL DEFAULT '',
  `idRoom_rate` int(11) not null default 0,
  `Rate_Glide_Credit` int(11) NOT NULL DEFAULT '0',
  `Rate_Adjust` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idVisit`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `volunteer_hours`
-- -----------------------------------------------------
CREATE TABLE if not exists `volunteer_hours` (
  `idVolunteer_hours` int(11) NOT NULL AUTO_INCREMENT,
  `idmcalendar` int(11) NOT NULL DEFAULT '0',
  `idName` int(11) NOT NULL,
  `idName2` int(11) NOT NULL DEFAULT '0',
  `idCompany` int(11) NOT NULL DEFAULT '0',
  `Org` varchar(45) NOT NULL DEFAULT '',
  `Hours` decimal(10,3) NOT NULL DEFAULT '0.000',
  `E_Start` datetime DEFAULT NULL,
  `E_End` datetime DEFAULT NULL,
  `Logged_By` varchar(45) NOT NULL DEFAULT '',
  `Date_Logged` datetime DEFAULT NULL,
  `Verified_By` varchar(45) NOT NULL DEFAULT '',
  `Date_Verified` datetime DEFAULT NULL,
  `E_Vol_Category` varchar(45) NOT NULL DEFAULT '',
  `E_Vol_Code` varchar(45) NOT NULL DEFAULT '',
  `E_Status` varchar(5) NOT NULL DEFAULT '',
  `Type` varchar(45) NOT NULL DEFAULT '',
  `idHouse` int(11) NOT NULL DEFAULT '0',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idVolunteer_hours`)
) ENGINE=InnoDB;



-- -----------------------------------------------------
-- Table `w_auth`
-- -----------------------------------------------------
CREATE TABLE if not exists `w_auth` (
  `idName` int(11) NOT NULL DEFAULT '0',
  `Role_Id` varchar(3) NOT NULL DEFAULT '',
  `Organization_Id` varchar(3) NOT NULL DEFAULT '',
  `Policy_id` int(11) NOT NULL DEFAULT '0',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `User_Name` varchar(245) NOT NULL DEFAULT '',
  `Status` varchar(2) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idName`)
) ENGINE=InnoDB;


-- Table `w_auth_ip`
-- -----------------------------------------------------
CREATE TABLE if not exists `w_auth_ip` (
  `IP_addr` varchar(45) NOT NULL,
  `cidr` int(2) NOT NULL DEFAULT 32,
  `Title` varchar(245) NOT NULL,
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`IP_addr`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `w_groups`
-- -----------------------------------------------------
CREATE TABLE if not exists `w_groups` (
  `Group_Code` varchar(5) NOT NULL DEFAULT '',
  `Title` varchar(45) NOT NULL DEFAULT '',
  `Description` varchar(255) NOT NULL DEFAULT '',
  `Default_Access_Level` varchar(5) NOT NULL DEFAULT '',
  `Max_Level` varchar(5) NOT NULL DEFAULT '',
  `Min_Access_Level` varchar(5) NOT NULL DEFAULT '',
  `Cookie_Restricted` bit(1) NOT NULL DEFAULT b'0',
  `IP_Restricted` boolean DEFAULT 0,
  `Password_Policy` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Group_Code`)
) ENGINE=InnoDB;

-- Table `w_group_ip`
-- -----------------------------------------------------
CREATE TABLE if not exists `w_group_ip` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `Group_Code` varchar(5) NOT NULL DEFAULT '',
  `IP_addr` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- Table `w_idp`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `w_idp` (
	`idIdp` INT(11) NOT NULL AUTO_INCREMENT,
    `Name` VARCHAR(100) NOT NULL,
    `LogoPath` VARCHAR(500),
    `SSO_URL` VARCHAR(500),
    `IdP_EntityId` VARCHAR(500),
    `IdP_SigningCert` BLOB,
    `IdP_SigningCert2` BLOB,
    `IdP_EncryptionCert` BLOB,
    `IdP_EncryptionCert2` BLOB,
    `expectIdPSigning` BOOL DEFAULT 1,
    `expectIdPEncryption` BOOL DEFAULT 1,
    `enableSPSigning` BOOL DEFAULT 1,
    `IdP_ManageRoles` BOOL DEFAULT 1,
    `Status` VARCHAR(2) NOT NULL DEFAULT 'a',
    PRIMARY KEY (`idIdp`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `w_idp_secgroups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `w_idp_secgroups` (
	`idIdpSecGroup` INT(11) NOT NULL AUTO_INCREMENT,
    `idIdp` INT(11) NOT NULL DEFAULT 0,
    `idSecGroup` VARCHAR(5) NOT NULL DEFAULT '',
    PRIMARY KEY (`idIdpSecGroup`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `w_user_log`
-- -----------------------------------------------------
CREATE TABLE if not exists `w_user_log` (
  `Username` VARCHAR(100) NOT NULL COMMENT '',
  `Access_Date` DATETIME NOT NULL COMMENT '',
  `IP` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '',
  `Session_Id` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '',
  `Page` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '',
  `Action` VARCHAR(45) NOT NULL DEFAULT '',
  `Browser` VARCHAR(45) NOT NULL DEFAULT '',
  `OS` VARCHAR(45) NOT NULL DEFAULT ''
) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `w_user_passwords`
-- -----------------------------------------------------
CREATE TABLE if not exists`w_user_passwords` (
  `idPassword` int(11) NOT NULL AUTO_INCREMENT,
  `idUser` int(11) NOT NULL,
  `Enc_PW` varchar(100) NOT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPassword`)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table `w_users`
-- -----------------------------------------------------
CREATE TABLE if not exists `w_users` (
  `idName` int(11) NOT NULL,
  `User_Name` varchar(100) NOT NULL DEFAULT '',
  `Enc_PW` varchar(100) NOT NULL DEFAULT '',
  `PW_Change_Date` DATETIME DEFAULT NULL,
  `Chg_PW` BOOL NOT NULL DEFAULT true,
  `idIdp` int(11) NOT NULL DEFAULT 0,
  `default2Factor` VARCHAR(4) NULL,
  `totpSecret` VARCHAR(45) NOT NULL DEFAULT '',
  `emailSecret` VARCHAR(45) NOT NULL DEFAULT '',
  `backupSecret` VARCHAR(45) NOT NULL DEFAULT '',
  `pass_rules` BOOL NOT NULL DEFAULT true,
  `PW_Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
  `Status` varchar(4) NOT NULL DEFAULT '',
  `Certificate` varchar(145) NOT NULL DEFAULT '',
  `Cookie` char(32) NOT NULL DEFAULT '',
  `Session` char(32) NOT NULL DEFAULT '',
  `Ip` varchar(15) NOT NULL DEFAULT '',
  `Default_Page` VARCHAR(100) NOT NULL DEFAULT '',
  `Verify_Address` varchar(4) NOT NULL DEFAULT '',
  `Last_Login` datetime DEFAULT NULL,
  `Hash_PW` char(32) NOT NULL DEFAULT '',
  `Updated_By` varchar(45) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`User_Name`)
) ENGINE=InnoDB;

CREATE TABLE if not exists `w_user_tokens` (
	`idToken` INT(11) NOT NULL AUTO_INCREMENT,
	`idName` INT(11) NOT NULL,
    `Token` VARCHAR(100) NOT NULL DEFAULT '',
    `Expires` INT(11) NOT NULL DEFAULT 0,
    `IP_Address` VARCHAR(45) NOT NULL DEFAULT '',
    `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`idToken`)
) ENGINE=InnoDB;
    

-- -----------------------------------------------------
-- Table `web_sites`
-- -----------------------------------------------------
CREATE TABLE if not exists `web_sites` (
  `idweb_sites` int(11) NOT NULL AUTO_INCREMENT,
  `Site_Code` varchar(5) NOT NULL,
  `Description` varchar(245) NOT NULL DEFAULT '',
  `Relative_Address` varchar(145) NOT NULL DEFAULT '',
  `Required_Group_Code` varchar(45) NOT NULL DEFAULT '',
  `Path_To_CSS` varchar(145) NOT NULL DEFAULT '',
  `Path_To_JS` varchar(145) NOT NULL DEFAULT '',
  `Last_Updated` datetime DEFAULT NULL,
  `Updated_By` varchar(45) NOT NULL,
  `Default_Page` varchar(105) NOT NULL DEFAULT '',
  `Index_Page` varchar(145) NOT NULL DEFAULT '',
  `HTTP_Host` varchar(245) NOT NULL DEFAULT '',
  PRIMARY KEY (`idweb_sites`)
) ENGINE=InnoDB AUTO_INCREMENT=5;


-- -----------------------------------------------------
--
-- Indexes
--
-- -----------------------------------------------------

ALTER TABLE `activity`
    ADD INDEX IF NOT EXISTS `Index_idName` (`idName` ASC);
    
ALTER TABLE `campaign`
	ADD UNIQUE KEY IF NOT EXISTS `Campaign_Code_UNIQUE` (`Campaign_Code`);

ALTER TABLE `donations`
	Add INDEX IF NOT EXISTS `Activity_Id_INDEX` (`Activity_Id`);
ALTER TABLE `donations`
	ADD INDEX IF NOT EXISTS `Donor_Id_INDEX` (`Donor_Id`);
	

ALTER TABLE `emergency_contact`
    ADD INDEX IF NOT EXISTS `Index_idName` (`idName` ASC);

ALTER TABLE `fin_application`
    ADD INDEX IF NOT EXISTS `Index_idRegistration` (`idRegistration` ASC);

ALTER TABLE `guest_token`
    ADD INDEX IF NOT EXISTS `Index_idRegistration` (`idRegistration` ASC);

ALTER TABLE `hospital_stay`
    ADD INDEX IF NOT EXISTS `Index_idPatient` (`idPatient` ASC);
ALTER TABLE `hospital_stay`
    ADD INDEX IF NOT EXISTS `Index_idPsg` (`idPsg` ASC);

ALTER TABLE `invoice`
  	ADD UNIQUE KEY IF NOT EXISTS `Invoice_Number_UNIQUE` (`Invoice_Number`);
ALTER TABLE `invoice`
    ADD INDEX IF NOT EXISTS `Index_Order_SO_Number` (`Order_Number` ASC, `Suborder_Number` ASC);
ALTER TABLE `invoice`
    ADD INDEX IF NOT EXISTS `Index_idGroup` (`idGroup` ASC);
ALTER TABLE `invoice`
    ADD INDEX IF NOT EXISTS `Index_Date` (`Invoice_Date` ASC);
ALTER TABLE `invoice` 
    ADD INDEX IF NOT EXISTS `Index_SoldToId` (`Sold_To_Id` ASC);
ALTER TABLE `invoice` 
    ADD INDEX IF NOT EXISTS `Index_Delagated` (`Delegated_Invoice_Id` ASC);

ALTER TABLE `invoice_line`
    ADD INDEX IF NOT EXISTS `ix_invoice_line_invoice_id` (`Invoice_Id` ASC);

ALTER TABLE `labels` 
	ADD UNIQUE INDEX IF NOT EXISTS `Unique_Key_Categeory` (`Key` ASC, `Category` ASC);

ALTER TABLE `name`
    ADD INDEX IF NOT EXISTS `Index_Name` (`Name_Last` ASC, `Name_First` ASC);
ALTER TABLE `name`
    ADD INDEX IF NOT EXISTS `iNameLastFirst` (`Name_Last_First`);
      
ALTER TABLE `name_address`
	ADD INDEX IF NOT EXISTS `iNA_ID` (`idName`);

ALTER TABLE `name_guest`
    ADD INDEX IF NOT EXISTS `INDEX_IdPsg` (`idPsg` ASC);
    
CREATE INDEX  IF NOT EXISTS `INDEX_PHONE_SEARCH` ON name_phone(`Phone_Search`);
    
CREATE INDEX IF NOT EXISTS `INDEX_USERNAME` ON `note`(`User_Name` ASC);

ALTER TABLE `payment`
    ADD INDEX IF NOT EXISTS `Index_Date` (`Payment_Date` ASC);

ALTER TABLE `payment_auth`
    ADD INDEX IF NOT EXISTS `Index_idPayment` (`idPayment` ASC);

ALTER TABLE `payment_info_check`
    ADD INDEX IF NOT EXISTS `Index_idPayment` (`idPayment` ASC);

CREATE INDEX IF NOT EXISTS `ix_Payment_Id` ON payment_invoice(Payment_Id);
CREATE INDEX IF NOT EXISTS `ix_Invoice_Id` ON payment_invoice(Invoice_Id);


ALTER TABLE `psg`
    ADD UNIQUE INDEX IF NOT EXISTS `idPatient_UNIQUE` (`idPatient` ASC);

ALTER TABLE `registration`
    ADD INDEX IF NOT EXISTS `Index_idPsg` (`idPsg` ASC);

ALTER TABLE `report`
	ADD  INDEX IF NOT EXISTS `Index_Psg_Id` (`Psg_Id`);

ALTER TABLE `report_field_sets`
	ADD UNIQUE KEY IF NOT EXISTS `U_INDEX_TRC` (`Title`,`Report`,`Created_by`);
	
ALTER TABLE `reservation`
    ADD INDEX IF NOT EXISTS `Index_idregistration` (`idRegistration` ASC);
ALTER TABLE `reservation`
    ADD INDEX IF NOT EXISTS `Index_idGuest` (`idGuest` ASC);
ALTER TABLE `reservation`
    ADD INDEX IF NOT EXISTS `Index_Expected_Arrival` (`Expected_Arrival` ASC);
ALTER TABLE `reservation`
    ADD INDEX IF NOT EXISTS `Index_Expected_Departure` (`Expected_Departure` ASC);
ALTER TABLE `reservation`
    ADD INDEX IF NOT EXISTS `Index_idHosptial_Stay` (`idHospital_Stay` ASC);

ALTER TABLE `resource_room`
    ADD INDEX IF NOT EXISTS `Index_idResource` (`idResource` ASC);

ALTER TABLE `resource_use`
    ADD INDEX IF NOT EXISTS `Index_idResource` (`idResource` ASC);

ALTER TABLE `stays`
    ADD INDEX IF NOT EXISTS `index_idVisit_Span` (`idVisit` ASC, `Visit_Span` ASC);
ALTER TABLE `stays`
    ADD INDEX IF NOT EXISTS `index_Span_Start` (`Span_Start_Date` ASC);
ALTER TABLE `stays`
    ADD INDEX IF NOT EXISTS `index_Span_End` (`Span_End_Date` ASC);
ALTER TABLE `stays`
    ADD INDEX IF NOT EXISTS `index_idName` (`idName` ASC);

ALTER TABLE `vehicle`
    ADD INDEX IF NOT EXISTS `INDEX_LICENSE` (`License_Number` ASC);
ALTER TABLE `vehicle`
    ADD INDEX IF NOT EXISTS `INDEX_IdNAME` (`idName` ASC);
ALTER TABLE `vehicle`
    ADD INDEX IF NOT EXISTS `INDEX_REG` (`idRegistration` ASC);

ALTER TABLE `visit`
    ADD INDEX IF NOT EXISTS `Index_idPrimaryGuest` (`idPrimaryGuest` ASC);
ALTER TABLE `visit`
    ADD INDEX IF NOT EXISTS `Index_idRegistration` (`idRegistration` ASC);
ALTER TABLE `visit`
    ADD INDEX IF NOT EXISTS `Index_idHosp_Stay` (`idHospital_stay` ASC);
ALTER TABLE `visit`
    ADD INDEX IF NOT EXISTS `Index_Span_Start` (`Span_Start` ASC);
ALTER TABLE `visit`
    ADD INDEX IF NOT EXISTS `Index_Span_End` (`Span_End` ASC);
ALTER TABLE `visit`
    ADD INDEX IF NOT EXISTS `Index_Exp_Depart` (`Expected_Departure` ASC);
ALTER TABLE `visit`
    ADD INDEX IF NOT EXISTS `Index_Arrival_Date` (`Arrival_Date` ASC);
ALTER TABLE `visit` 
    ADD INDEX IF NOT EXISTS `Index_idReservation` (`idReservation` ASC);


ALTER TABLE `volunteer_hours`
    ADD INDEX IF NOT EXISTS `Index_idName` (`idName` ASC);

ALTER TABLE `name_log`
    ADD INDEX IF NOT EXISTS `INDEX_IDNAME` (`idName` ASC);

ALTER TABLE `visit_log`
    ADD INDEX IF NOT EXISTS `INDX_IDNAME` (`idName` ASC),
    ADD INDEX IF NOT EXISTS `INDX_IDVISIT` (`idVisit` ASC, `Span` ASC);
    
    
    
-- -------Functions-------
    
--
-- function `dateDefaultNow`
--
DROP function IF EXISTS `datedefaultnow`; -- ;

CREATE FUNCTION `datedefaultnow` (dt DateTime)
RETURNS DATETIME
DETERMINISTIC NO SQL
RETURN case when dt is null then now() when DATE(dt) < DATE(now()) then now() else dt end  -- ;


--
-- function `fiscal_year`
--
DROP function IF EXISTS `fiscal_year`; --;

CREATE FUNCTION `fiscal_year` (dt DateTime, adjust int)
RETURNS Datetime
NO SQL DETERMINISTIC
RETURN DATE_ADD(dt, INTERVAL adjust MONTH)  --;


