-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2021 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
-- -----------------------------------------------------
-- Table `activity`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `activity` (
        `idActivity` INT (11) NOT NULL AUTO_INCREMENT,
        `idName` INT (11) NOT NULL,
        `Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Trans_Date` DATETIME DEFAULT NULL,
        `Effective_Date` DATETIME DEFAULT NULL,
        `Product_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Other_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Description` VARCHAR(245) NOT NULL DEFAULT '',
        `Source_System` VARCHAR(45) NOT NULL DEFAULT '',
        `Source_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Quantity` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Amount` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Category` VARCHAR(45) NOT NULL DEFAULT '',
        `Units` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Thru_Date` DATETIME DEFAULT NULL,
        `Member_Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Action_Codes` VARCHAR(245) NOT NULL DEFAULT '',
        `Pay_Method` VARCHAR(45) NOT NULL DEFAULT '',
        `Note` TEXT,
        `Batch_Num` VARCHAR(25) NOT NULL DEFAULT '',
        `Company_ID` VARCHAR(15) NOT NULL DEFAULT '',
        `Originating_Trans_Num` INT (11) NOT NULL DEFAULT '0',
        `Org_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Campaign_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Mail_Merge_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Solicitation_Text` VARCHAR(200) NOT NULL DEFAULT '',
        `Solicitor_Id` INT (11) NOT NULL DEFAULT '0',
        `Salutation_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Status_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Grace_Period` INT (11) NOT NULL DEFAULT '0',
        `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idActivity`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `api_access_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `api_access_log` (
        `idLog` INT (11) NOT NULL AUTO_INCREMENT,
        `requestPath` VARCHAR(255) NOT NULL,
        `responseCode` VARCHAR(3) NOT NULL DEFAULT '',
        `request` JSON NOT NULL DEFAULT '{}',
        `response` JSON NOT NULL DEFAULT '{}',
        `oauth_client_id` VARCHAR(45) NOT NULL DEFAULT '',
        `oauth_user_id` VARCHAR(45) NOT NULL DEFAULT '',
        `oauth_access_token_id` VARCHAR(100) NOT NULL DEFAULT '',
        `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idLog`),
        INDEX `idx_oauth_client_id` (`oauth_client_id`),
        INDEX `idx_oauth_access_token_id` (`oauth_access_token_id`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 1;

-- -----------------------------------------------------
-- Table `external_api_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `external_api_log` (
        `idLog` INT (11) NOT NULL AUTO_INCREMENT,
        `Log_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Sub_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `requestMethod` VARCHAR(10) NOT NULL DEFAULT '',
        `endpoint` VARCHAR(512) NOT NULL,
        `responseCode` VARCHAR(3) NOT NULL DEFAULT '',
        `request` LONGTEXT NOT NULL DEFAULT '',
        `response` LONGTEXT NOT NULL DEFAULT '',
        `username` VARCHAR(255) DEFAULT '',
        `Timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idLog`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 1;

-- -----------------------------------------------------
-- Table `attribute`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `attribute` (
        `idAttribute` INT (11) NOT NULL AUTO_INCREMENT,
        `Type` VARCHAR(4) NOT NULL,
        `Title` VARCHAR(145) NOT NULL DEFAULT '',
        `Category` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(4) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        PRIMARY KEY (`idAttribute`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 1;

-- -----------------------------------------------------
-- Table `attribute_entity`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `attribute_entity` (
        `idEntity` INT (11) NOT NULL,
        `idAttribute` INT (11) NOT NULL,
        `Type` VARCHAR(4) NOT NULL,
        PRIMARY KEY (`idEntity`, `idAttribute`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `campaign`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `campaign` (
        `idcampaign` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(250) NOT NULL DEFAULT '',
        `Campaign_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Start_Date` DATE DEFAULT NULL,
        `End_Date` DATE DEFAULT NULL,
        `Target` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Min_Donation` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Max_Donation` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Category` VARCHAR(15) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Campaign_Merge_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Description` TEXT,
        `Lump_Sum_Cost` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Percent_Cut` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Our_Cut_PerTx` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Campaign_Type` VARCHAR(5) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(25) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idcampaign`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `card_id`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `card_id` (
        `idName` INT (11) NOT NULL,
        `idGroup` INT (11) NOT NULL,
        `CardID` VARCHAR(136) NOT NULL DEFAULT '',
        `Init_Date` DATETIME DEFAULT NULL,
        `ReturnCode` INT (11) NOT NULL DEFAULT '0',
        `Frequency` VARCHAR(9) NOT NULL DEFAULT '',
        `OperatorID` VARCHAR(10) NOT NULL DEFAULT '',
        `ResponseCode` INT (11) NOT NULL DEFAULT '0',
        `Transaction` VARCHAR(14) NOT NULL DEFAULT '',
        `InvoiceNumber` VARCHAR(36) NOT NULL DEFAULT '',
        `Amount` DECIMAL(11, 2) NOT NULL DEFAULT 0.00,
        `Merchant` VARCHAR(45) NOT NULL DEFAULT '',
        PRIMARY KEY (`idName`, `idGroup`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `cc_hosted_gateway`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `cc_hosted_gateway` (
        `idcc_gateway` INT (11) NOT NULL AUTO_INCREMENT,
        `Gateway_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `cc_name` VARCHAR(45) NOT NULL DEFAULT '',
        `Merchant_Id` VARCHAR(255) NOT NULL DEFAULT '',
        `Password` VARCHAR(245) NOT NULL DEFAULT '',
        `Credit_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `Trans_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `CardInfo_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `Checkout_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `Mobile_CardInfo_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `Mobile_Checkout_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `CheckoutPOS_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `CheckoutPOSiFrame_Url` VARCHAR(255) NOT NULL DEFAULT '',
        `Use_AVS_Flag` BIT (1) NOT NULL DEFAULT 0,
        `Use_Ccv_Flag` BIT (1) NOT NULL DEFAULT 0,
        `Retry_Count` INT (11) NOT NULL DEFAULT '0',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idcc_gateway`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 1;

-- -----------------------------------------------------
-- Table `checklist_item`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `checklist_item` (
        `idChecklist_item` INT (11) NOT NULL AUTO_INCREMENT,
        `Entity_Id` INT (11) NOT NULL,
        `GL_TableName` VARCHAR(45) NOT NULL DEFAULT '',
        `GL_Code` VARCHAR(65) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Value` SMALLINT (4) NOT NULL DEFAULT 0,
        `Value_Date` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idChecklist_item`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 1;

-- -----------------------------------------------------
-- Table `cleaning_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `cleaning_log` (
        `idResource` INT (11) NOT NULL DEFAULT '0',
        `idRoom` INT (11) NOT NULL DEFAULT '0',
        `Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Notes` TEXT NULL,
        `Last_Cleaned` DATETIME DEFAULT NULL,
        `Last_Deep_Clean` DATETIME DEFAULT NULL,
        `Username` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `constraints`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `constraints` (
        `idConstraint` INT (11) NOT NULL AUTO_INCREMENT,
        `Type` VARCHAR(4) NOT NULL DEFAULT '',
        `Title` VARCHAR(145) NOT NULL DEFAULT '',
        `Category` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(4) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        PRIMARY KEY (`idConstraint`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `constraint_attribute`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `constraint_attribute` (
        `idConstraint` INT (11) NOT NULL,
        `idAttribute` INT (11) NOT NULL,
        `Type` VARCHAR(4) NOT NULL DEFAULT '',
        `Operation` VARCHAR(4) NOT NULL DEFAULT '',
        PRIMARY KEY (`idConstraint`, `idAttribute`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `constraint_entity`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `constraint_entity` (
        `idConstraint` INT (11) NOT NULL,
        `idEntity` INT (11) NOT NULL,
        `Type` VARCHAR(4) NOT NULL,
        PRIMARY KEY (`idConstraint`, `idEntity`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `counter`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `counter` (
        `seqn` INT (11) NOT NULL,
        `Table_Name` VARCHAR(75) NOT NULL,
        `Next` INT (11) NOT NULL,
        `Last_Updated` DATETIME DEFAULT NULL,
        PRIMARY KEY (`seqn`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `country_code`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `country_code` (
        `Country_Name` VARCHAR(145) NOT NULL,
        `ISO_3166-1-alpha-2` VARCHAR(5) NOT NULL,
        `External_Id` INT (11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`ISO_3166-1-alpha-2`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `cron_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `cron_log` (
        `idLog` INT NOT NULL AUTO_INCREMENT,
        `idJob` INT NOT NULL DEFAULT 0,
        `Log_Text` VARCHAR(255) NULL,
        `Status` VARCHAR(45) NULL,
        `timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idLog`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `cronjobs`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `cronjobs` (
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
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `desig_holidays`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `desig_holidays` (
        `Year` INT (11) NOT NULL,
        `dh1` DATE DEFAULT NULL,
        `dh2` DATE DEFAULT NULL,
        `dh3` DATE DEFAULT NULL,
        `dh4` DATE DEFAULT NULL,
        `dh5` DATE DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        PRIMARY KEY (`Year`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `document`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `document` (
        `idDocument` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(128) NOT NULL,
        `Name` VARCHAR(45) NOT NULL DEFAULT '',
        `Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Type` VARCHAR(5) NOT NULL DEFAULT '',
        `Mime_Type` VARCHAR(85) NOT NULL DEFAULT '',
        `Folder` VARCHAR(45) NOT NULL DEFAULT '',
        `Language` VARCHAR(5) NOT NULL DEFAULT '',
        `Abstract` TEXT,
        `Doc` MEDIUMBLOB,
        `userData` MEDIUMTEXT NULL,
        `Style` MEDIUMTEXT NULL,
        `Status` VARCHAR(5) NOT NULL,
        `Last_Updated` DATETIME DEFAULT NULL,
        `Created_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idDocument`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `document_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `document_log` (
        `Log_Type` VARCHAR(45) NOT NULL,
        `Sub_Type` VARCHAR(45) NOT NULL,
        `User_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `idName` INT (11) NOT NULL DEFAULT '0',
        `idPsg` INT (11) NOT NULL DEFAULT '0',
        `idDocument` INT (11) NOT NULL DEFAULT '0',
        `idReservation` INT (11) NOT NULL DEFAULT '0',
        `Log_Text` VARCHAR(5000) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `link_doc`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `link_doc` (
        `idDocument` INT (11) NOT NULL,
        `idGuest` INT (11) DEFAULT NULL,
        `idPSG` INT (11) DEFAULT NULL,
        `idReservation` INT (11) DEFAULT 0,
        `username` VARCHAR(100) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idDocument`, `idGuest`, `idPSG`, `idReservation`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `link_note`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `link_note` (
        `id` INT (11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `idNote` INT (11) NOT NULL,
        `linkType` VARCHAR(20) DEFAULT NULL,
        `idLink` INT (11) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `donations`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `donations` (
        `iddonations` INT (11) NOT NULL AUTO_INCREMENT,
        `Donor_Id` INT (11) NOT NULL,
        `Care_Of_Id` INT (11) NOT NULL DEFAULT '0',
        `Assoc_Id` INT (11) NOT NULL DEFAULT '0',
        `Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Date_Entered` DATETIME DEFAULT NULL,
        `Trans_Date` DATETIME DEFAULT NULL,
        `Pay_Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Member_type` VARCHAR(15) NOT NULL DEFAULT '',
        `Donation_Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Amount` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Matching_Amount` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Note` VARCHAR(255) NOT NULL DEFAULT '',
        `Salutation_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Envelope_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Address_1` VARCHAR(145) NOT NULL DEFAULT '',
        `Address_2` VARCHAR(145) NOT NULL DEFAULT '',
        `City` VARCHAR(45) NOT NULL DEFAULT '',
        `State` VARCHAR(5) NOT NULL DEFAULT '',
        `Postal_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Country` VARCHAR(45) NOT NULL DEFAULT '',
        `Address_Purpose` VARCHAR(5) NOT NULL DEFAULT '',
        `Phone` VARCHAR(25) NOT NULL DEFAULT '',
        `Email` VARCHAR(145) NOT NULL DEFAULT '',
        `Fund_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Org_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Campaign_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Activity_Id` INT (11) NOT NULL DEFAULT '0',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Date_Acknowledged` DATETIME DEFAULT NULL,
        `Status` VARCHAR(15) NOT NULL DEFAULT '',
        PRIMARY KEY (`iddonations`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `emergency_contact`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `emergency_contact` (
        `idEmergency_contact` INT (11) NOT NULL AUTO_INCREMENT,
        `idName` INT (11) NOT NULL,
        `Name_Last` VARCHAR(45) NOT NULL DEFAULT '',
        `Name_First` VARCHAR(45) NOT NULL DEFAULT '',
        `Relationship` VARCHAR(5) NOT NULL DEFAULT '',
        `Phone_Home` VARCHAR(15) NOT NULL DEFAULT '',
        `Phone_Mobile` VARCHAR(15) NOT NULL DEFAULT '',
        `Phone_Alternate` VARCHAR(15) NOT NULL DEFAULT '',
        `Notes` TEXT,
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idEmergency_contact`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `fin_application`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `fin_application` (
        `idFin_application` INT (11) NOT NULL AUTO_INCREMENT,
        `idRegistration` INT (11) NOT NULL DEFAULT '0',
        `Monthly_Income` INT (11) NOT NULL DEFAULT '0',
        `HH_Size` INT (11) NOT NULL DEFAULT '0',
        `FA_Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Approved_Id` VARCHAR(45) NOT NULL DEFAULT '',
        `Notes` TEXT,
        `FA_Applied` VARCHAR(2) NOT NULL DEFAULT '',
        `FA_Applied_Date` DATETIME DEFAULT NULL,
        `FA_Status` VARCHAR(5) NOT NULL DEFAULT '',
        `FA_Status_Date` DATETIME DEFAULT NULL,
        `FA_Reason` VARCHAR(445) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idFin_application`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `gateway_transaction`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `gateway_transaction` (
        `idgateway_transaction` INT (11) NOT NULL AUTO_INCREMENT,
        `GwTransCode` VARCHAR(64) NOT NULL DEFAULT '',
        `GwResultCode` VARCHAR(44) NOT NULL DEFAULT '',
        `Amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Vendor_Request` VARCHAR(2000) NOT NULL DEFAULT '',
        `Vendor_Response` VARCHAR(5000) NOT NULL DEFAULT '',
        `AuthCode` VARCHAR(45) NOT NULL DEFAULT '',
        `idPayment_Detail` INT (11) NOT NULL DEFAULT '0',
        `Created_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idgateway_transaction`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `gen_lookups`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `gen_lookups` (
        `Table_Name` VARCHAR(45) NOT NULL,
        `Code` VARCHAR(65) NOT NULL,
        `Description` VARCHAR(255) NOT NULL DEFAULT '',
        `Substitute` VARCHAR(255) NOT NULL DEFAULT '',
        `Attributes` JSON NULL DEFAULT '{}',
        `Type` VARCHAR(4) NOT NULL DEFAULT '',
        `Order` INT NOT NULL DEFAULT 0,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`Table_Name`, `Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `gen_securitygroup`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `gen_securitygroup` (
        `idSec` INT (11) NOT NULL,
        `Group_Code` VARCHAR(5) NOT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idSec`, `Group_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `guest_token`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `guest_token` (
        `idGuest_token` INT (11) NOT NULL AUTO_INCREMENT,
        `idGuest` INT (11) NOT NULL DEFAULT '0',
        `Running_Total` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `idRegistration` INT (11) NOT NULL DEFAULT '0',
        `Token` VARCHAR(100) NOT NULL DEFAULT '',
        `Merchant` VARCHAR(45) NOT NULL DEFAULT '',
        `Granted_Date` DATETIME DEFAULT NULL,
        `LifetimeDays` INT (11) NOT NULL DEFAULT '0',
        `MaskedAccount` VARCHAR(15) NOT NULL DEFAULT '',
        `Frequency` VARCHAR(15) NOT NULL DEFAULT '',
        `Status` VARCHAR(10) NOT NULL DEFAULT '',
        `Response_Code` INT (11) NOT NULL DEFAULT '1',
        `CardHolderName` VARCHAR(132) NOT NULL DEFAULT '',
        `CardType` VARCHAR(45) NOT NULL DEFAULT '',
        `CardUsage` VARCHAR(20) NOT NULL DEFAULT '',
        `ExpDate` VARCHAR(14) NOT NULL DEFAULT '',
        `OperatorID` VARCHAR(10) NOT NULL DEFAULT '',
        `Tran_Type` VARCHAR(14) NOT NULL DEFAULT '',
        `StatusMessage` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idGuest_token`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `hospital`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `hospital` (
        `idHospital` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Description` VARCHAR(245) NOT NULL DEFAULT '',
        `Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(4) NOT NULL DEFAULT '',
        `Hide` TINYINT NOT NULL DEFAULT 0,
        `idLocation` INT (11) NOT NULL DEFAULT '0',
        `idName` INT (11) NOT NULL DEFAULT '0',
        `Reservation_Style` VARCHAR(145) NOT NULL DEFAULT '',
        `Stay_Style` VARCHAR(145) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idHospital`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `hospital_stay`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `hospital_stay` (
        `idHospital_stay` INT (11) NOT NULL AUTO_INCREMENT,
        `idPatient` INT (11) NOT NULL DEFAULT '0',
        `idPsg` INT (11) NOT NULL DEFAULT '0',
        `idHospital` INT (11) NOT NULL DEFAULT '0',
        `idAssociation` INT (11) NOT NULL DEFAULT '0',
        `idReferralAgent` INT (11) NOT NULL DEFAULT '0',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Diagnosis` VARCHAR(245) NOT NULL DEFAULT '',
        `Diagnosis2` VARCHAR(245) NOT NULL DEFAULT '',
        `Location` VARCHAR(5) NOT NULL DEFAULT '',
        `idDoctor` INT (11) NOT NULL DEFAULT '0',
        `idPcDoctor` INT (11) NOT NULL DEFAULT '0',
        `Doctor` VARCHAR(145) NOT NULL DEFAULT '',
        `Room` VARCHAR(45) NOT NULL DEFAULT '',
        `MRN` VARCHAR(45) NOT NULL DEFAULT '',
        `Private_Ins_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Room_Phone` VARCHAR(15) NOT NULL DEFAULT '',
        `Arrival_Date` DATETIME DEFAULT NULL,
        `Expected_Departure` DATETIME DEFAULT NULL,
        `Actual_Departure` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idHospital_stay`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `house`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `house` (
        `idHouse` INT NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Description` VARCHAR(145) NOT NULL DEFAULT '',
        `idLocation` INT NOT NULL DEFAULT 0,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idHouse`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `house_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `house_log` (
        `Log_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Sub_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `User_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `Id1` INT NOT NULL DEFAULT 0,
        `Id2` INT NOT NULL DEFAULT 0,
        `Str1` VARCHAR(45) NOT NULL DEFAULT '',
        `Str2` VARCHAR(45) NOT NULL DEFAULT '',
        `Log_Text` VARCHAR(5000) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `id_securitygroup`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `id_securitygroup` (
        `idName` INT (11) NOT NULL,
        `Group_Code` VARCHAR(5) NOT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `Group_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `insurance`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `insurance` (
        `idInsurance` INT NOT NULL AUTO_INCREMENT,
        `idInsuranceType` INT (3) NOT NULL,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Order` INT (3) NOT NULL DEFAULT 0,
        `Opens_Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Status` VARCHAR(1) NOT NULL DEFAULT 'a',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT now(),
        PRIMARY KEY (`idInsurance`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `insurance_type`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `insurance_type` (
        `idInsurance_type` INT (3) NOT NULL,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Is_Primary` INT (1) NOT NULL DEFAULT '0',
        `List_Order` INT (3) NOT NULL DEFAULT '0',
        `Status` VARCHAR(1) NOT NULL DEFAULT 'a',
        PRIMARY KEY (`idInsurance_type`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `invoice`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `invoice` (
        `idInvoice` INT (11) NOT NULL AUTO_INCREMENT,
        `Delegated_Invoice_Id` INT (11) NOT NULL DEFAULT '0',
        `Invoice_Number` VARCHAR(45) NOT NULL,
        `Billing_Process_Id` INT (11) NOT NULL DEFAULT '0',
        `Deleted` SMALLINT DEFAULT 0 NOT NULL,
        `Amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Sold_To_Id` INT (11) NOT NULL DEFAULT '0',
        `idGroup` INT (11) NOT NULL DEFAULT '0',
        `Invoice_Date` DATETIME DEFAULT NULL,
        `Payment_Attempts` INT (11) NOT NULL DEFAULT '0',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Carried_Amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Balance` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Order_Number` INT (11) NOT NULL DEFAULT 0,
        `Suborder_Number` SMALLINT (6) NOT NULL DEFAULT '0',
        `Due_Date` DATE DEFAULT NULL,
        `In_Process_Payment` TINYINT (4) NOT NULL DEFAULT '0',
        `BillStatus` VARCHAR(5) NOT NULL DEFAULT '',
        `BillDate` DATE NULL,
        `EmailDate` DATETIME NULL,
        `Last_Reminder` DATETIME,
        `Overdue_Step` INTEGER NOT NULL DEFAULT '0',
        `Description` VARCHAR(45) NOT NULL DEFAULT '',
        `Notes` VARCHAR(450) NOT NULL DEFAULT '',
        `tax_exempt` TINYINT NOT NULL DEFAULT 0,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idInvoice`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `invoice_line`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `invoice_line` (
        `idInvoice_Line` INTEGER NOT NULL AUTO_INCREMENT,
        `Invoice_Id` INTEGER NOT NULL DEFAULT '0',
        `Type_Id` INTEGER NOT NULL DEFAULT '0',
        `Amount` DECIMAL(22, 10) NOT NULL,
        `Quantity` DECIMAL(22, 10) NOT NULL DEFAULT '0.00',
        `Price` DECIMAL(22, 10) NOT NULL DEFAULT '0.00',
        `Period_Start` DATETIME,
        `Period_End` DATETIME,
        `Deleted` SMALLINT NOT NULL DEFAULT 0,
        `Item_Id` INTEGER NOT NULL DEFAULT 0,
        `Description` VARCHAR(1000) NOT NULL DEFAULT '',
        `Source_Item_Id` INTEGER NOT NULL DEFAULT 0,
        `Is_Percentage` SMALLINT NOT NULL DEFAULT 0,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idInvoice_Line`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `invoice_line_type`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `invoice_line_type` (
        `id` INTEGER NOT NULL,
        `Description` VARCHAR(50) NOT NULL,
        `Order_Position` INTEGER NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `item`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `item` (
        `idItem` INTEGER NOT NULL AUTO_INCREMENT,
        `Timeout_Days` VARCHAR(50) NOT NULL DEFAULT '',
        `First_Order_Id` INTEGER NOT NULL DEFAULT 0,
        `Last_Order_Id` INTEGER NOT NULL DEFAULT 0,
        `Percentage` DECIMAL(22, 10) NOT NULL DEFAULT '0.00',
        `Deleted` SMALLINT DEFAULT 0 NOT NULL DEFAULT '0',
        `Has_Decimals` SMALLINT DEFAULT 0 NOT NULL DEFAULT '0',
        `Gl_Code` VARCHAR(50) NOT NULL DEFAULT '',
        `Description` VARCHAR(1000) NOT NULL DEFAULT '',
        PRIMARY KEY (`idItem`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `item_item`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `item_item` (
        `idItem` INT NOT NULL,
        `Item_Id` INT NOT NULL,
        PRIMARY KEY (`idItem`, `Item_Id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `item_price`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `item_price` (
        `idItem_price` INT (11) NOT NULL,
        `Item_Id` INT (11) NOT NULL,
        `Currency_Id` INT (11) NOT NULL DEFAULT '0',
        `Price` DECIMAL(12, 2) NOT NULL DEFAULT '0.0',
        `ModelCode` VARCHAR(5) NOT NULL DEFAULT '',
        PRIMARY KEY (`idItem_price`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `item_type`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `item_type` (
        `idItem_Type` INTEGER NOT NULL AUTO_INCREMENT,
        `Category_Type` INTEGER NOT NULL,
        `Type_Description` VARCHAR(100),
        `Internal` BIT NOT NULL,
        `Order_Line_Type_Id` INTEGER NOT NULL,
        PRIMARY KEY (`idItem_Type`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------------------------
-- Table item_type_map
-- -----------------------------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `item_type_map` (
        `Item_Id` INTEGER NOT NULL,
        `Type_Id` INTEGER NOT NULL,
        PRIMARY KEY (`Item_Id`, `Type_Id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `labels`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `labels` (
        `idLabel` INT (11) NOT NULL AUTO_INCREMENT,
        `Key` VARCHAR(25) NOT NULL,
        `Value` VARCHAR(500) NOT NULL DEFAULT '',
        `Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Header` VARCHAR(5) NOT NULL DEFAULT '',
        `Description` VARCHAR(1000) NOT NULL DEFAULT '',
        PRIMARY KEY (`idLabel`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `language`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `language` (
        `idLanguage` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(255) NOT NULL,
        `ISO_639_1` VARCHAR(5) NOT NULL,
        `Display` INT (1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`idLanguage`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `location`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `location` (
        `idLocation` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(45) DEFAULT '',
        `Description` VARCHAR(245) DEFAULT '',
        `Status` VARCHAR(5) DEFAULT '',
        `Address` VARCHAR(145) NOT NULL DEFAULT '',
        `Merchant` VARCHAR(45) NOT NULL DEFAULT '',
        `Map` VARCHAR(510) NOT NULL DEFAULT '',
        `Owner_Id` INT (11) NOT NULL DEFAULT '0',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idLocation`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `lookups`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `lookups` (
        `Category` VARCHAR(45) NOT NULL,
        `Code` VARCHAR(45) NOT NULL,
        `Title` VARCHAR(255) NOT NULL DEFAULT '',
        `Use` VARCHAR(2) NOT NULL DEFAULT '',
        `Show` VARCHAR(4) NOT NULL DEFAULT '',
        `Type` VARCHAR(255) NOT NULL DEFAULT '',
        `Other` VARCHAR(255) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`Category`, `Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `mail_listing`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `mail_listing` (
        `id` INT (11) NOT NULL,
        `mr` VARCHAR(5) NOT NULL DEFAULT '',
        `adr_frag` VARCHAR(200) NOT NULL DEFAULT '',
        `street` VARCHAR(200) NOT NULL DEFAULT '',
        `city` VARCHAR(45) NOT NULL DEFAULT '',
        `state` VARCHAR(45) NOT NULL DEFAULT '',
        `zip` VARCHAR(15) NOT NULL DEFAULT '',
        `sp` INT (11) NOT NULL DEFAULT '0',
        `fm` INT (11) NOT NULL DEFAULT '0',
        `rel` VARCHAR(5) NOT NULL DEFAULT '',
        `cde` VARCHAR(5) NOT NULL DEFAULT ''
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `map_relations`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `map_relations` (
        `idmap_relations` INT (11) NOT NULL AUTO_INCREMENT,
        `PG_Patient` VARCHAR(45) NOT NULL,
        `Guest_Patient` VARCHAR(45) DEFAULT NULL,
        `Patient_PG` VARCHAR(45) DEFAULT NULL,
        `Guest_PG` VARCHAR(45) DEFAULT NULL,
        PRIMARY KEY (`idmap_relations`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `member_history`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `member_history` (
        `idName` INT (11) NOT NULL,
        `Admin_Access_Date` DATETIME DEFAULT NULL,
        `Guest_Access_Date` DATETIME DEFAULT NULL,
        PRIMARY KEY (`idName`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name` (
        `idName` INT (11) NOT NULL AUTO_INCREMENT,
        `Name_First` VARCHAR(45) NOT NULL DEFAULT '',
        `Name_Last` VARCHAR(45) NOT NULL DEFAULT '',
        `Name_Middle` VARCHAR(45) NOT NULL DEFAULT '',
        `Name_Nickname` VARCHAR(45) NOT NULL DEFAULT '',
        `Name_Full` VARCHAR(170) NOT NULL DEFAULT '',
        `Name_Search` TEXT
        GENERATED ALWAYS AS (
        LOWER(
            CONCAT_WS(' ',
            Name_First,
            Name_Last,
            Name_Nickname,
            Company
            )
        )
        ) STORED,
        `Name_Previous` VARCHAR(45) NOT NULL DEFAULT '',
        `Web_Site` VARCHAR(145) NOT NULL DEFAULT '',
        `Member_Since` DATETIME DEFAULT NULL,
        `Member_Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Member_Status` VARCHAR(15) NOT NULL DEFAULT '',
        `Member_Category` VARCHAR(45) NOT NULL DEFAULT '',
        `Preferred_Mail_Address` VARCHAR(5) NOT NULL DEFAULT '',
        `Preferred_Email` VARCHAR(5) NOT NULL DEFAULT '',
        `Preferred_Phone` VARCHAR(5) NOT NULL DEFAULT '',
        `Organization_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `External_Id` VARCHAR(25) NOT NULL DEFAULT '',
        `Company_Id` INT (11) NOT NULL DEFAULT '0',
        `Title` VARCHAR(75) NOT NULL DEFAULT '',
        `Company` VARCHAR(80) NOT NULL DEFAULT '',
        `Company_CareOf` VARCHAR(4) NOT NULL DEFAULT '',
        `Record_Member` BIT(1) NOT NULL DEFAULT 0,
        `Record_Company` BIT(1) NOT NULL DEFAULT 0,
        `Previous_Member_Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Prev_MT_Change_Date` DATETIME DEFAULT NULL,
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Exclude_Directory` BIT(1) NOT NULL DEFAULT 0,
        `Exclude_Mail` BIT(1) NOT NULL DEFAULT 0,
        `Exclude_Email` BIT(1) NOT NULL DEFAULT 0,
        `Exclude_Phone` BIT(1) NOT NULL DEFAULT 0,
        `Date_Added` DATETIME DEFAULT NULL,
        `Gender` VARCHAR(5) NOT NULL DEFAULT '',
        `BirthDate` DATETIME DEFAULT NULL,
        `BirthDayOfYear` INT AS (
            CASE
                WHEN MONTH (BirthDate) = 2
                AND DAY (BirthDate) = 29 THEN 59 -- treat Feb-29 as Feb-28
                ELSE DAYOFYEAR (BirthDate)
            END
        ) STORED,
        `Member_Status_Date` DATETIME DEFAULT NULL,
        `Date_Deceased` DATETIME NULL DEFAULT NULL,
        `Name_Suffix` VARCHAR(10) NOT NULL DEFAULT '',
        `Name_Prefix` VARCHAR(25) NOT NULL DEFAULT '',
        `Name_Last_First` VARCHAR(90) NOT NULL DEFAULT '',
        `Birth_Month` INT (11) NOT NULL DEFAULT '0',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`),
        INDEX (`BirthDayOfYear`),
        FULLTEXT INDEX `ft_name_search` (`Name_Search`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `name_address`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_address` (
        `idName_Address` INT (11) NOT NULL AUTO_INCREMENT,
        `idName` INT (11) NOT NULL,
        `Purpose` VARCHAR(25) NOT NULL DEFAULT '',
        `Address_1` VARCHAR(200) NOT NULL DEFAULT '',
        `Address_2` VARCHAR(45) NOT NULL DEFAULT '',
        `City` VARCHAR(45) NOT NULL DEFAULT '',
        `State_Province` VARCHAR(45) NOT NULL DEFAULT '',
        `Postal_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Country` VARCHAR(45) NOT NULL DEFAULT '',
        `Country_Code` VARCHAR(10) NOT NULL DEFAULT '',
        `Address_Format` BIT (1) NOT NULL DEFAULT 0,
        `County` VARCHAR(45) NOT NULL DEFAULT '',
        `Meters_From_House` INT (11) NULL DEFAULT NULL,
        `DistCalcType` VARCHAR(10) NULL DEFAULT NULL,
        `Mail_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Status` VARCHAR(15) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Set_Incomplete` BIT (1) NOT NULL DEFAULT 0,
        `Last_Verified` DATETIME DEFAULT NULL,
        `Bad_Address` VARCHAR(15) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName_Address`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `name_customer`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_customer` (
        `idName_customer` INT (11) NOT NULL AUTO_INCREMENT,
        `idName` INT (11) NOT NULL,
        `idGroup` INT (11) NOT NULL DEFAULT 0,
        `Auto_Payment_Type` INT (11) NOT NULL DEFAULT 0,
        `Due_Date_Unit_Id` INT (11) NOT NULL DEFAULT 0,
        `Due_Date_Value` INT (11) NOT NULL DEFAULT 0,
        `Current_Order_Id` INT (11) NOT NULL DEFAULT 0,
        `Balance_Type` INT (11) NOT NULL DEFAULT 0,
        `Dynamic_Balance` DECIMAL(12, 6) NOT NULL DEFAULT '0.00',
        `Credit_Limit` DECIMAL(12, 6) NOT NULL DEFAULT '0.00',
        `Auto_Recharage` DECIMAL(12, 6) NOT NULL DEFAULT '0.00',
        `Notes` VARCHAR(1000) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName_customer`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `name_demog`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_demog` (
        `idName` INT (11) NOT NULL,
        `Steering_Committee` TINYINT (1) NOT NULL DEFAULT '0',
        `Newsletter` VARCHAR(5) NOT NULL DEFAULT '',
        `Key_Contact` TINYINT (1) NOT NULL DEFAULT '0',
        `Legislative` TINYINT (1) NOT NULL DEFAULT '0',
        `Photo_Permission` VARCHAR(5) NOT NULL DEFAULT '',
        `Guest_Photo_Id` INT NOT NULL DEFAULT 0,
        `Gen_Notes` TEXT,
        `Contact_Date` DATE DEFAULT NULL,
        `Orientation` TINYINT (1) NOT NULL DEFAULT '0',
        `Orientation_Date` DATE DEFAULT NULL,
        `Confirmed_Date` DATETIME NULL DEFAULT NULL,
        `Media_Source` VARCHAR(5) NOT NULL DEFAULT '',
        `No_Return` VARCHAR(5) NOT NULL DEFAULT '',
        `Age_Bracket` VARCHAR(5) NOT NULL DEFAULT '',
        `Race` VARCHAR(5) NOT NULL DEFAULT '',
        `Ethnicity` VARCHAR(5) NOT NULL DEFAULT '',
        `Income_Bracket` VARCHAR(5) NOT NULL DEFAULT '',
        `Education_Level` VARCHAR(5) NOT NULL DEFAULT '',
        `Special_Needs` VARCHAR(5) NOT NULL DEFAULT '',
        `Covid` VARCHAR(5) NOT NULL DEFAULT '',
        `ADA` VARCHAR(5) NOT NULL DEFAULT '',
        `Gl_Code_Debit` VARCHAR(25) NOT NULL DEFAULT '',
        `Gl_Code_Credit` VARCHAR(25) NOT NULL DEFAULT '',
        `tax_exempt` TINYINT NOT NULL DEFAULT 0,
        `Background_Check_Date` DATE DEFAULT NULL,
        `Is_Minor` TINYINT (4) NOT NULL DEFAULT 0,
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name_email`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_email` (
        `idName` INT (11) NOT NULL,
        `Purpose` VARCHAR(25) NOT NULL DEFAULT '',
        `Email` VARCHAR(140) NOT NULL DEFAULT '',
        `Bad_Address` VARCHAR(15) NOT NULL DEFAULT '',
        `Last_Verified` DATE DEFAULT NULL,
        `Last_Updated` DATE DEFAULT NULL,
        `Updated_By` VARCHAR(45) DEFAULT NULL,
        `Status` VARCHAR(15) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `Purpose`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name_external`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_external` (
        `idName` INT (11) NOT NULL,
        `Service` VARCHAR(25) NOT NULL DEFAULT '',
        `External_Id` VARCHAR(140) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `Service`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name_guest`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_guest` (
        `idName` INT (11) NOT NULL,
        `idPsg` INT (11) NOT NULL,
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Legal_Custody` INT (11) NOT NULL DEFAULT '0',
        `Relationship_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Type` VARCHAR(45) NOT NULL DEFAULT '',
        `External_Id` VARCHAR(45) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `idPsg`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name_insurance`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_insurance` (
        `idName` INT NOT NULL,
        `Insurance_Id` INT NOT NULL,
        `Member_Num` VARCHAR(100) NOT NULL DEFAULT '',
        `Group_Num` VARCHAR(100) NOT NULL DEFAULT '',
        `Primary` INT (1) NOT NULL DEFAULT 0,
        `Status` VARCHAR(4) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `Insurance_Id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name_language`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_language` (
        `idName` INT (11) NOT NULL,
        `Language_Id` INT (11) NOT NULL,
        `Mother_Tongue` INT (1) NOT NULL DEFAULT 0,
        `Proficiency` VARCHAR(4) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `Language_Id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_log` (
        `Date_Time` DATETIME NOT NULL,
        `Log_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Sub_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `WP_User_Id` VARCHAR(45) NOT NULL DEFAULT '',
        `idName` VARCHAR(15) NOT NULL DEFAULT '',
        `Log_Text` VARCHAR(255) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `name_phone`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_phone` (
        `idName` INT (11) NOT NULL,
        `Phone_Num` VARCHAR(45) NOT NULL DEFAULT '',
        `Phone_Extension` VARCHAR(15) NOT NULL DEFAULT '',
        `Phone_Code` VARCHAR(5) NOT NULL,
        `Phone_Search` VARCHAR(25) NOT NULL DEFAULT '',
        `Status` VARCHAR(15) NOT NULL DEFAULT '',
        `is_Mobile` BIT (1) NOT NULL DEFAULT 0,
        `is_Toll_Free` BIT (1) NOT NULL DEFAULT 0,
        `is_International` BIT (1) NOT NULL DEFAULT 0,
        `is_Land_Line` BIT (1) NOT NULL DEFAULT 0,
        `is_Personal` BIT (1) NOT NULL DEFAULT 0,
        `is_Party_Line` BIT (1) NOT NULL DEFAULT 0,
        `SMS_status` VARCHAR(10) NOT NULL DEFAULT '',
        `Carrier` VARCHAR(45) NOT NULL DEFAULT '',
        `Bad_Number` VARCHAR(15) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `Phone_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `name_volunteer2`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `name_volunteer2` (
        `idName` INT (11) NOT NULL,
        `Vol_Category` VARCHAR(15) NOT NULL,
        `Vol_Code` VARCHAR(5) NOT NULL,
        `Vol_Status` VARCHAR(5) NOT NULL,
        `Vol_Availability` VARCHAR(5) NOT NULL DEFAULT '',
        `Vol_Notes` TEXT,
        `Vol_Begin` DATE DEFAULT NULL,
        `Vol_End` DATE DEFAULT NULL,
        `Vol_Check_Date` DATE DEFAULT NULL,
        `Dormant_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(25) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Vol_Rank` VARCHAR(45) NOT NULL DEFAULT '',
        `Vol_Training_Date` DATE DEFAULT NULL,
        `Vol_License` VARCHAR(25) NOT NULL DEFAULT '',
        `Vol_Trainer` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`, `Vol_Category`, `Vol_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `neon_lists`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `neon_lists` (
        `Method` VARCHAR(45) NOT NULL DEFAULT '',
        `List_Name` VARCHAR(45) NOT NULL,
        `List_Item` VARCHAR(45) NOT NULL DEFAULT '',
        `HHK_Lookup` VARCHAR(45) NOT NULL DEFAULT '',
        PRIMARY KEY (`List_Name`)
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `neon_type_map`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `neon_type_map` (
        `idNeon_type_map` INT NOT NULL AUTO_INCREMENT,
        `List_Name` VARCHAR(45) NOT NULL,
        `Neon_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `Neon_Type_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Neon_Type_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `HHK_Type_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT NOW(),
        PRIMARY KEY (`idNeon_type_map`)
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `note`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `note` (
        `idNote` INT NOT NULL AUTO_INCREMENT,
        `User_Name` VARCHAR(45) NOT NULL,
        `Note_Type` VARCHAR(15) NULL,
        `Category` VARCHAR(15) NULL,
        `flag` BOOL DEFAULT FALSE,
        `Title` VARCHAR(145) NULL,
        `Note_Text` TEXT NULL,
        `Updated_By` VARCHAR(45) NULL,
        `Last_Updated` DATETIME NULL,
        `Status` VARCHAR(5) NOT NULL DEFAULT 'a',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idNote`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `note_group`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `note_group` (
        `Note_Id` INT NOT NULL,
        `Group_Id` VARCHAR(5) NOT NULL,
        PRIMARY KEY (`Note_Id`, `Group_Id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `note_category`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `note_category` (
        `Note_Id` INT NOT NULL,
        `Category_Code` VARCHAR(5) NOT NULL,
        PRIMARY KEY (`Note_Id`, `Category_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `notification_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `notification_log` (
        `idLog` INT NOT NULL AUTO_INCREMENT,
        `Log_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Sub_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `username` VARCHAR(45) NOT NULL DEFAULT '',
        `To` VARCHAR(255) NOT NULL DEFAULT '',
        `From` VARCHAR(255) NOT NULL DEFAULT '',
        `Log_Text` VARCHAR(255) NOT NULL DEFAULT '',
        `Log_Details` JSON NOT NULL DEFAULT '{}',
        `Timestamp` TIMESTAMP(5) NOT NULL DEFAULT CURRENT_TIMESTAMP(5),
        PRIMARY KEY (`idLog`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `operating_schedules`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `operating_schedules` (
        `idDay` INT NOT NULL AUTO_INCREMENT,
        `Day` INT NULL,
        `Start_Date` DATETIME NULL,
        `End_Date` DATETIME NULL,
        `Open_At` TIME NULL,
        `Closed_At` TIME NULL,
        `Non_Cleaning` BOOL NOT NULL DEFAULT FALSE,
        `Closed` BOOL NOT NULL DEFAULT FALSE,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idDay`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `page`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `page` (
        `idPage` INT (11) NOT NULL AUTO_INCREMENT,
        `File_Name` VARCHAR(65) NOT NULL,
        `Login_Page_Id` INT (11) NOT NULL DEFAULT '0',
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Product_Code` VARCHAR(4) NOT NULL DEFAULT '',
        `Hide` INT (1) NOT NULL DEFAULT 0,
        `Web_Site` VARCHAR(5) NOT NULL DEFAULT '',
        `Menu_Parent` VARCHAR(45) NOT NULL DEFAULT '',
        `Menu_Position` VARCHAR(45) NOT NULL DEFAULT '',
        `Type` VARCHAR(5) NOT NULL DEFAULT '',
        `Validity_Code` VARCHAR(75) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPage`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 5;

-- -----------------------------------------------------
-- Table `page_securitygroup`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `page_securitygroup` (
        `idPage` INT (11) NOT NULL,
        `Group_Code` VARCHAR(5) NOT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPage`, `Group_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `payment`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `payment` (
        `idPayment` INT (11) NOT NULL AUTO_INCREMENT,
        `Attempt` INT (11) DEFAULT NULL,
        `Amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Balance` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Result` VARCHAR(24) NOT NULL DEFAULT '',
        `Financial_Entity_Id` INT (11) NOT NULL DEFAULT '0',
        `Payment_Date` DATETIME DEFAULT NULL,
        `idPayor` INT (11) NOT NULL DEFAULT '0',
        `idPayment_Method` INT (11) NOT NULL DEFAULT '0',
        `idTrans` INT (11) NOT NULL DEFAULT '0',
        `idToken` INT (11) NOT NULL DEFAULT '0',
        `Is_Refund` TINYINT (4) NOT NULL DEFAULT '0',
        `parent_idPayment` INT (11) NOT NULL DEFAULT '0',
        `Is_Preauth` TINYINT (4) NOT NULL DEFAULT '0',
        `Status_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Data1` VARCHAR(15) NOT NULL DEFAULT '',
        `Data2` VARCHAR(15) NOT NULL DEFAULT '',
        `Notes` TEXT NULL DEFAULT NULL,
        `External_Id` VARCHAR(45) NOT NULL DEFAULT '',
        `Created_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPayment`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 7;

-- -----------------------------------------------------
-- Table `payment_auth`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `payment_auth` (
        `idPayment_auth` INT (11) NOT NULL AUTO_INCREMENT,
        `idPayment` INT (11) NOT NULL DEFAULT '0',
        `idTrans` INT (11) NOT NULL DEFAULT '0',
        `Processor` VARCHAR(45) NOT NULL DEFAULT '',
        `Merchant` VARCHAR(45) NOT NULL DEFAULT '',
        `Approved_Amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Approval_Code` VARCHAR(20) NOT NULL DEFAULT '',
        `Status_Message` VARCHAR(45) NOT NULL DEFAULT '',
        `AVS` VARCHAR(20) NOT NULL DEFAULT '',
        `Invoice_Number` VARCHAR(45) NOT NULL DEFAULT '',
        `Acct_Number` VARCHAR(25) NOT NULL DEFAULT '',
        `Card_Type` VARCHAR(10) NOT NULL DEFAULT '',
        `Cardholder_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `Customer_Id` VARCHAR(45) NOT NULL DEFAULT '',
        `Response_Message` VARCHAR(200) NOT NULL DEFAULT '',
        `Response_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Reference_Num` VARCHAR(45) NOT NULL DEFAULT '',
        `AcqRefData` VARCHAR(200) NOT NULL DEFAULT '',
        `ProcessData` VARCHAR(200) NOT NULL DEFAULT '',
        `Signature_Required` INT (4) NOT NULL DEFAULT 1,
        `PartialPayment` INT (4) NOT NULL DEFAULT 0,
        `CVV` VARCHAR(45) NOT NULL DEFAULT '',
        `Serialized_Details` VARCHAR(1000) NOT NULL DEFAULT '',
        `Status_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `EMVApplicationIdentifier` VARCHAR(200) DEFAULT '',
        `EMVTerminalVerificationResults` VARCHAR(200) DEFAULT '',
        `EMVIssuerApplicationData` VARCHAR(200) DEFAULT '',
        `EMVTransactionStatusInformation` VARCHAR(200) DEFAULT '',
        `EMVApplicationResponseCode` VARCHAR(200) DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPayment_auth`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 7;

-- -----------------------------------------------------
-- Table ``paymentid_externalid`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `paymentid_externalid` (
        `Payment_Id` INT NOT NULL,
        `External_Id` VARCHAR(15) NOT NULL,
        `TimeStamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`Payment_Id`, `External_Id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `payment_info_check`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `payment_info_check` (
        `idpayment_info_Check` INT (11) NOT NULL AUTO_INCREMENT,
        `idPayment` INT (11) NOT NULL,
        `Bank` VARCHAR(45) NOT NULL DEFAULT '',
        `Check_Number` VARCHAR(45) NOT NULL DEFAULT '',
        `Check_Date` DATE DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idpayment_info_Check`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 7;

-- -----------------------------------------------------------------------
-- Table payment_invoice
-- -----------------------------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `payment_invoice` (
        `idPayment_Invoice` INTEGER NOT NULL AUTO_INCREMENT,
        `Payment_Id` INTEGER NOT NULL DEFAULT 0,
        `Invoice_Id` INTEGER NOT NULL DEFAULT 0,
        `Amount` DECIMAL(22, 10) NOT NULL DEFAULT 0.00,
        `Create_Datetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPayment_Invoice`),
        INDEX `ix_uq_payment_inv_map_pa_in` (`Payment_Id`, `Invoice_Id`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 7;

-- -----------------------------------------------------
-- Table `payment_method`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `payment_method` (
        `idPayment_method` INT (11) NOT NULL AUTO_INCREMENT,
        `Method_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `Gl_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPayment_method`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `photo`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `photo` (
        `idPhoto` INT NOT NULL AUTO_INCREMENT,
        `Image_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Image` BLOB NULL,
        `Last_Updated` DATETIME NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPhoto`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 7;

-- -----------------------------------------------------
-- Table `postal_codes`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `postal_codes` (
        `Zip_Code` VARCHAR(9) NOT NULL,
        `City` VARCHAR(45) NOT NULL DEFAULT '',
        `County` VARCHAR(45) NOT NULL DEFAULT '',
        `State` VARCHAR(4) NOT NULL DEFAULT '',
        `Lat` VARCHAR(15) NOT NULL DEFAULT '',
        `Lng` VARCHAR(15) NOT NULL DEFAULT '',
        `Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Acceptable_Cities` VARCHAR(511) NOT NULL DEFAULT '',
        PRIMARY KEY (`Zip_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `psg`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `psg` (
        `idPsg` INT (11) NOT NULL AUTO_INCREMENT,
        `idPatient` INT NOT NULL DEFAULT 0,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Primary_Language` INT NOT NULL DEFAULT 0,
        `Language_Notes` TEXT NULL DEFAULT NULL,
        `Info_Last_Confirmed` DATETIME NULL DEFAULT NULL,
        `Notes` TEXT NULL DEFAULT NULL,
        `Last_Updated` DATETIME NULL DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPsg`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `rate_breakpoint`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `rate_breakpoint` (
        `idrate_breakpoint` INT NOT NULL AUTO_INCREMENT,
        `Household_Size` INT (4) NOT NULL,
        `Rate_Category` VARCHAR(4) NOT NULL,
        `Breakpoint` INT NOT NULL DEFAULT 0,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idrate_breakpoint`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `registration`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `registration` (
        `idRegistration` INT (11) NOT NULL AUTO_INCREMENT,
        `idPsg` INT (11) NOT NULL,
        `Date_Registered` DATETIME DEFAULT NULL,
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Sig_Card` INT (11) NOT NULL DEFAULT '0',
        `Pamphlet` INT (11) NOT NULL DEFAULT '0',
        `Email_Receipt` TINYINT (4) NOT NULL DEFAULT '0',
        `Pref_Token_Id` INT (11) NOT NULL DEFAULT 0,
        `Referral` INT (11) NOT NULL DEFAULT '0',
        `Vehicle` INT (1) NOT NULL DEFAULT '0',
        `Guest_Ident` VARCHAR(45) NOT NULL DEFAULT '',
        `Key_Deposit_Bal` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Lodging_Balance` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Notes` TEXT,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idRegistration`, `idPsg`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `relationship`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `relationship` (
        `idRelationship` INT (11) NOT NULL AUTO_INCREMENT,
        `idName` INT (11) NOT NULL,
        `Target_Id` INT (11) NOT NULL DEFAULT '0',
        `Relation_Type` VARCHAR(5) NOT NULL DEFAULT '',
        `Status` VARCHAR(45) NOT NULL DEFAULT '',
        `Principal` BIT (1) NOT NULL DEFAULT 0,
        `Effective_Date` DATE DEFAULT NULL,
        `Thru_date` DATE DEFAULT NULL,
        `Note` TEXT,
        `Date_Added` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Group_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idRelationship`),
        UNIQUE KEY `idRelationship_UNIQUE` (`idRelationship`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `report`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `incident_report` (
        `idReport` INT (11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(240) NOT NULL DEFAULT '',
        `Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Report_Date` DATETIME DEFAULT NULL,
        `Resolution_Date` DATETIME DEFAULT NULL,
        `Description` TEXT DEFAULT NULL,
        `Resolution` TEXT DEFAULT NULL,
        `Signature` BLOB,
        `Signature_Date` DATETIME DEFAULT NULL,
        `Author` VARCHAR(45) NOT NULL DEFAULT '',
        `Guest_Id` INT (11) NOT NULL DEFAULT '0',
        `Psg_Id` INT (11) NOT NULL DEFAULT '0',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idReport`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `report_field_sets`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `report_field_sets` (
        `idFieldSet` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Report` VARCHAR(45) NOT NULL DEFAULT '',
        `Fields` LONGTEXT,
        `Global` TINYINT (4) DEFAULT '0',
        `Updated_by` VARCHAR(45) DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Created_by` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idFieldSet`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `reservation`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `reservation` (
        `idReservation` INT (11) NOT NULL AUTO_INCREMENT,
        `idRegistration` INT (11) NOT NULL DEFAULT '0',
        `idGuest` INT (11) NOT NULL DEFAULT '0',
        `idHospital_Stay` INT (11) NOT NULL DEFAULT '0',
        `idResource` INT (11) NOT NULL DEFAULT '0',
        `idReferralDoc` INT (11) NOT NULL DEFAULT '0',
        `Resource_Suitable` VARCHAR(4) NOT NULL DEFAULT '',
        `Confirmation` VARCHAR(4) NOT NULL DEFAULT '',
        `Room_Rate_Category` VARCHAR(4) NOT NULL DEFAULT '',
        `Fixed_Room_Rate` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Rate_Adjust` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `idRateAdjust` VARCHAR(5) NOT NULL DEFAULT '0',
        `Visit_Fee` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `idRoom_rate` INT (11) NOT NULL DEFAULT '0',
        `Title` VARCHAR(145) NOT NULL DEFAULT '',
        `Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Expected_Pay_Type` VARCHAR(4) NOT NULL DEFAULT '',
        `Expected_Arrival` DATETIME DEFAULT NULL,
        `Expected_Departure` DATETIME DEFAULT NULL,
        `Actual_Arrival` DATETIME DEFAULT NULL,
        `Actual_Departure` DATETIME DEFAULT NULL,
        `Number_Guests` INT (11) NOT NULL DEFAULT '0',
        `No_Vehicle` TINYINT NOT NULL DEFAULT 0,
        `Add_Room` INT NOT NULL DEFAULT 0,
        `Checkin_Notes` VARCHAR(1000) NOT NULL DEFAULT '',
        `Notes` TEXT,
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idReservation`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `reservation_guest`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `reservation_guest` (
        `idReservation` INT (11) NOT NULL,
        `idGuest` INT (11) NOT NULL,
        `Primary_Guest` VARCHAR(2) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idReservation`, `idGuest`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `reservation_invoice`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `reservation_invoice` (
        `Reservation_Id` INT NOT NULL,
        `Invoice_id` INT NOT NULL,
        PRIMARY KEY (`Reservation_Id`, `Invoice_id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `reservation_invoice_line`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `reservation_invoice_line` (
        `Reservation_Id` INT NOT NULL,
        `Invoice_Line_id` INT NOT NULL,
        PRIMARY KEY (`Reservation_Id`, `Invoice_line_id`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `reservation_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `reservation_log` (
        `Log_Type` VARCHAR(45) NOT NULL,
        `Sub_Type` VARCHAR(45) NOT NULL,
        `User_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `idName` INT (11) NOT NULL DEFAULT '0',
        `idPsg` INT (11) NOT NULL DEFAULT '0',
        `idRegistration` INT (11) NOT NULL DEFAULT '0',
        `idHospital` INT (11) NOT NULL DEFAULT '0',
        `idAgent` INT (11) DEFAULT '0',
        `idHospital_stay` INT (11) NOT NULL DEFAULT '0',
        `idReservation` INT (11) NOT NULL DEFAULT '0',
        `idSpan` INT (11) NOT NULL DEFAULT '0',
        `idRoom_rate` INT (11) NOT NULL DEFAULT '0',
        `idResource` INT (11) NOT NULL DEFAULT '0',
        `Log_Text` VARCHAR(5000) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `reservation_multiple`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `reservation_multiple` (
        `idReservation_multiple` INT NOT NULL AUTO_INCREMENT,
        `Host_Id` INT NOT NULL,
        `Child_Id` INT NOT NULL,
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idReservation_multiple`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `reservation_vehicle`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `reservation_vehicle` (
        `idReservation` INT (11) NOT NULL,
        `idVehicle` INT (11) NOT NULL,
        `idName` INT (11) NOT NULL,
        PRIMARY KEY (`idReservation`, `idVehicle`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `resource`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `resource` (
        `idResource` INT (11) NOT NULL AUTO_INCREMENT,
        `idSponsor` INT (11) NOT NULL DEFAULT '0',
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Utilization_Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Color` VARCHAR(15) NOT NULL DEFAULT '',
        `Background_Color` VARCHAR(15) NOT NULL DEFAULT '',
        `Text_Color` VARCHAR(15) NOT NULL DEFAULT '',
        `Border_Color` VARCHAR(15) NOT NULL DEFAULT '',
        `Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Partition_Size` VARCHAR(5) NOT NULL DEFAULT '',
        `Util_Priority` VARCHAR(5) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Rate_Adjust` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Rate_Adjust_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Retired_At` DATETIME NULL DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idResource`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `resource_room`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `resource_room` (
        `idResource_room` INT (11) NOT NULL AUTO_INCREMENT,
        `idResource` INT (11) NOT NULL,
        `idRoom` INT (11) NOT NULL,
        PRIMARY KEY (`idResource_room`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `resource_use`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `resource_use` (
        `idResource_use` INT (11) NOT NULL AUTO_INCREMENT,
        `idResource` INT (11) NOT NULL DEFAULT '0',
        `idRoom` INT (11) NOT NULL DEFAULT '0',
        `Start_Date` DATETIME DEFAULT NULL,
        `End_Date` DATETIME DEFAULT NULL,
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `OOS_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Room_Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Room_State` VARCHAR(5) NOT NULL DEFAULT '',
        `Room_Availability` VARCHAR(5) NOT NULL DEFAULT '',
        `Notes` VARCHAR(245) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idResource_use`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `room`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `room` (
        `idRoom` INT (11) NOT NULL AUTO_INCREMENT,
        `idHouse` INT (11) NOT NULL DEFAULT '0',
        `Item_Id` INT (11) NOT NULL DEFAULT '0',
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Description` TEXT,
        `Notes` TEXT,
        `Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Report_Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Util_Priority` VARCHAR(5) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `State` VARCHAR(15) NOT NULL DEFAULT '',
        `Availability` VARCHAR(15) NOT NULL DEFAULT '',
        `Max_Occupants` INT (11) NOT NULL DEFAULT '0',
        `Min_Occupants` INT (11) NOT NULL DEFAULT '0',
        `Beds_King` INT (11) NOT NULL DEFAULT '0',
        `Beds_Queen` INT (11) NOT NULL DEFAULT '0',
        `Beds_Utility` INT NOT NULL DEFAULT 0,
        `Beds_Full` INT (11) NOT NULL DEFAULT '0',
        `Beds_Twin` INT (11) NOT NULL DEFAULT '0',
        `Phone` VARCHAR(15) NOT NULL DEFAULT '',
        `Floor` VARCHAR(15) NOT NULL DEFAULT '',
        `idLocation` INT (11) NOT NULL DEFAULT '1',
        `Owner_Id` INT (11) NOT NULL DEFAULT '0',
        `Last_Cleaned` DATETIME DEFAULT NULL,
        `Last_Deep_Clean` DATETIME DEFAULT NULL,
        `Visit_Fee_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Rate_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Default_Rate_Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Key_Deposit` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
        `Key_Deposit_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `Cleaning_Cycle_Code` VARCHAR(5) NOT NULL DEFAULT 'a',
        `Image` BLOB,
        `Image_Tn` BLOB,
        `Display_Colors` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idRoom`, `idHouse`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `room_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `room_log` (
        `idRoom` INT (11) NOT NULL,
        `Date` DATETIME DEFAULT NULL,
        `User` VARCHAR(45) NOT NULL DEFAULT '',
        `Type` VARCHAR(5) NOT NULL DEFAULT '',
        `Activity` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Log_Text` VARCHAR(1000) DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `room_rate`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `room_rate` (
        `idRoom_rate` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Description` VARCHAR(245) NOT NULL DEFAULT '',
        `FA_Category` VARCHAR(2) NOT NULL DEFAULT '',
        `Rate_Breakpoint_Category` VARCHAR(4) NOT NULL DEFAULT '',
        `PriceModel` VARCHAR(5) NOT NULL DEFAULT '',
        `Reduced_Rate_1` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Reduced_Rate_2` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Reduced_Rate_3` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Min_Rate` DECIMAL(10, 4) NOT NULL DEFAULT '0.0000',
        `Status` VARCHAR(4) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idRoom_rate`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `secondary_unit_desig`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `secondary_unit_desig` (
        `Common` VARCHAR(45) NOT NULL,
        `Standard` VARCHAR(6) NOT NULL,
        `Range_Required` BIT (1) NOT NULL,
        `TitleCaps` VARCHAR(6) NOT NULL
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `sf_type_map`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `sf_type_map` (
        `idSf_type_map` INT NOT NULL AUTO_INCREMENT,
        `List_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `SF_Type_Code` VARCHAR(45) NULL DEFAULT '',
        `SF_Type_Name` VARCHAR(45) NULL DEFAULT '',
        `HHK_Type_Code` VARCHAR(45) NULL DEFAULT '',
        `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idSf_type_map`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `ssotoken`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `ssotoken` (
        `Token` VARCHAR(136) NOT NULL,
        `idPaymentAuth` INT NOT NULL DEFAULT 0,
        `idName` INT (11) NOT NULL,
        `CardHolderName` VARCHAR(45) NOT NULL DEFAULT '',
        `idGroup` INT (11) NOT NULL,
        `InvoiceNumber` VARCHAR(36) NOT NULL DEFAULT '',
        `Amount` DECIMAL(11, 2) NOT NULL DEFAULT 0.00,
        `State` VARCHAR(5) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`Token`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `stays`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `stays` (
        `idStays` INT (11) NOT NULL AUTO_INCREMENT,
        `idVisit` INT (11) NOT NULL,
        `Visit_Span` INT (11) NOT NULL,
        `idRoom` INT (11) NOT NULL DEFAULT '0',
        `idName` INT (11) NOT NULL,
        `Checkin_Date` DATETIME DEFAULT NULL,
        `Checkout_Date` DATETIME DEFAULT NULL,
        `Expected_Co_Date` DATETIME DEFAULT NULL,
        `Span_Start_Date` DATETIME DEFAULT NULL,
        `Span_End_Date` DATETIME DEFAULT NULL,
        `Activity_Id` INT (11) NOT NULL DEFAULT '0',
        `On_Leave` INT (11) NOT NULL DEFAULT '0',
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Recorded` INT (1) NOT NULL DEFAULT '0',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idStays`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `street_suffix`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `street_suffix` (
        `Common` VARCHAR(45) DEFAULT NULL,
        `Standard` VARCHAR(5) DEFAULT NULL,
        `TitleCaps` VARCHAR(5) DEFAULT NULL
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `sys_config`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `sys_config` (
        `Key` VARCHAR(25) NOT NULL,
        `Value` VARCHAR(5000) NOT NULL DEFAULT '',
        `Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Category` VARCHAR(5) NOT NULL DEFAULT '',
        `Header` VARCHAR(5) NOT NULL DEFAULT '',
        `Description` VARCHAR(1000) NOT NULL DEFAULT '',
        `GenLookup` VARCHAR(45) NOT NULL DEFAULT '',
        `Show` TINYINT NOT NULL DEFAULT 1,
        PRIMARY KEY (`Key`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `syslog`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `syslog` (
        `Log_Type` VARCHAR(25) NOT NULL,
        `User` VARCHAR(45) NOT NULL,
        `Remote_IP` VARCHAR(45) NOT NULL,
        `Log_Message` VARCHAR(255) NOT NULL,
        `System_Version` VARCHAR(45) NOT NULL,
        `GIT_Id` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `template_tag`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `template_tag` (
        `idTemplate_tag` INT (11) NOT NULL AUTO_INCREMENT,
        `Doc_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `Tag_Title` VARCHAR(25) NOT NULL DEFAULT '',
        `Tag_Name` VARCHAR(25) NOT NULL DEFAULT '',
        `Replacement_Wrapper` VARCHAR(45) NOT NULL DEFAULT '',
        PRIMARY KEY (`idTemplate_tag`),
        UNIQUE INDEX `Unq_Doc_Tag` (`Doc_Name` ASC, `Tag_Name` ASC)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `trans`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `trans` (
        `idTrans` INT (11) NOT NULL AUTO_INCREMENT,
        `Trans_Type` VARCHAR(5) NOT NULL DEFAULT '',
        `Trans_Method` VARCHAR(5) NOT NULL DEFAULT '',
        `Trans_Date` DATETIME DEFAULT NULL,
        `idName` INT (11) NOT NULL DEFAULT 0,
        `Order_Number` VARCHAR(45) NOT NULL DEFAULT '',
        `Invoice_Number` VARCHAR(45) NOT NULL DEFAULT '',
        `Payment_Type` VARCHAR(15) NOT NULL DEFAULT '',
        `Check_Number` VARCHAR(15) NOT NULL DEFAULT '',
        `Check_Bank` VARCHAR(45) NOT NULL DEFAULT '',
        `Card_Number` VARCHAR(4) NOT NULL DEFAULT '',
        `Card_Expire` VARCHAR(15) NOT NULL DEFAULT '',
        `Card_Authorize` VARCHAR(15) NOT NULL DEFAULT '',
        `Card_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `Auth_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `RefNo` VARCHAR(50) NOT NULL DEFAULT '',
        `Process_Code` VARCHAR(15) NOT NULL DEFAULT '',
        `Gateway_Ref` VARCHAR(45) NOT NULL DEFAULT '',
        `Payment_Status` VARCHAR(15) NOT NULL DEFAULT '',
        `Amount` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Amount_Tendered` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Date_Entered` DATETIME DEFAULT NULL,
        `Entered_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idTrans`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `transaction_type`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `transaction_type` (
        `idtransaction_type` INT (11) NOT NULL AUTO_INCREMENT,
        `Title` VARCHAR(45) NOT NULL,
        `Effect` VARCHAR(45) NOT NULL DEFAULT '',
        `Code` VARCHAR(5) NOT NULL DEFAULT '',
        PRIMARY KEY (`idtransaction_type`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `vehicle`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `vehicle` (
        `idVehicle` INT (11) NOT NULL AUTO_INCREMENT,
        `idRegistration` INT (11) NOT NULL,
        `idName` INT (11) NOT NULL DEFAULT 0,
        `Make` VARCHAR(45) NOT NULL DEFAULT '',
        `Model` VARCHAR(45) NOT NULL DEFAULT '',
        `Color` VARCHAR(45) NOT NULL DEFAULT '',
        `State_Reg` VARCHAR(2) NOT NULL DEFAULT '',
        `License_Number` VARCHAR(15) NOT NULL DEFAULT '',
        `No_Vehicle` VARCHAR(4) NOT NULL DEFAULT '',
        `Note` VARCHAR(445) NOT NULL DEFAULT '',
        PRIMARY KEY (`idVehicle`, `idRegistration`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `visit`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `visit` (
        `idVisit` INT (11) NOT NULL AUTO_INCREMENT,
        `Span` INT (11) NOT NULL DEFAULT '0',
        `idRegistration` INT (11) NOT NULL,
        `idReservation` INT (11) NOT NULL DEFAULT '0',
        `idResource` INT (11) NOT NULL DEFAULT '0',
        `idPrimaryGuest` INT (11) NOT NULL DEFAULT '0',
        `idHospital_stay` INT (11) NOT NULL DEFAULT '0',
        `Title` VARCHAR(145) NOT NULL DEFAULT '',
        `Key_Deposit` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Key_Dep_Disposition` VARCHAR(4) NOT NULL DEFAULT '',
        `DepositPayType` VARCHAR(5) NOT NULL DEFAULT '',
        `Arrival_Date` DATETIME DEFAULT NULL,
        `Expected_Departure` DATETIME DEFAULT NULL,
        `Actual_Departure` DATETIME DEFAULT NULL,
        `Span_Start` DATETIME DEFAULT NULL,
        `Span_End` DATETIME DEFAULT NULL,
        `Return_Date` DATETIME DEFAULT NULL,
        `Notice_to_Checkout` DATETIME NULL DEFAULT NULL,
        `Expected_Rate` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Pledged_Rate` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Amount_Per_Guest` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `idRoom_Rate` INT (11) NOT NULL DEFAULT '0',
        `Rate_Category` VARCHAR(5) NOT NULL DEFAULT '',
        `idRateAdjust` VARCHAR(5) NULL DEFAULT '0',
        `Rate_Glide_Credit` INT (11) NOT NULL DEFAULT '0',
        `Ext_Phone_Installed` INT (1) NOT NULL DEFAULT '0',
        `Medical_Cooler` INT (1) NOT NULL DEFAULT '0',
        `Wheel_Chair` INT (1) NOT NULL DEFAULT '0',
        `OverRideMaxOcc` INT (1) NOT NULL DEFAULT '0',
        `Notes` TEXT,
        `Status` VARCHAR(5) NOT NULL DEFAULT '',
        `Recorded` INT (1) NOT NULL DEFAULT '0',
        `Checked_In_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idVisit`, `Span`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `visit_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `visit_log` (
        `Log_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `Sub_Type` VARCHAR(45) NOT NULL DEFAULT '',
        `User_Name` VARCHAR(45) NOT NULL DEFAULT '',
        `idName` INT (11) NOT NULL DEFAULT '0',
        `idPsg` INT (11) NOT NULL DEFAULT '0',
        `idRegistration` INT (11) NOT NULL DEFAULT '0',
        `idVisit` INT (11) NOT NULL DEFAULT '0',
        `Span` INT (11) NOT NULL DEFAULT '0',
        `idStay` INT (11) NOT NULL DEFAULT '0',
        `idRr` INT (11) NOT NULL DEFAULT '0',
        `Status` VARCHAR(15) NOT NULL DEFAULT '',
        `Log_Text` VARCHAR(5000) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `visit_onleave`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `visit_onleave` (
        `idVisit` INT (11) NOT NULL,
        `Span` INT (11) NOT NULL DEFAULT '0',
        `Pledged_Rate` DECIMAL(10, 2) NOT NULL DEFAULT '0.00',
        `Rate_Category` VARCHAR(5) NOT NULL DEFAULT '',
        `idRoom_rate` INT (11) NOT NULL DEFAULT 0,
        `Rate_Glide_Credit` INT (11) NOT NULL DEFAULT '0',
        `Rate_Adjust` DECIMAL(10, 4) NOT NULL DEFAULT '0.0000',
        `idRateAdjust` VARCHAR(5) NULL DEFAULT '0',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idVisit`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `w_auth`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_auth` (
        `idName` INT (11) NOT NULL DEFAULT '0',
        `Role_Id` VARCHAR(3) NOT NULL DEFAULT '',
        `Organization_Id` VARCHAR(3) NOT NULL DEFAULT '',
        `Policy_id` INT (11) NOT NULL DEFAULT '0',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `User_Name` VARCHAR(245) NOT NULL DEFAULT '',
        `Status` VARCHAR(2) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idName`)
    ) ENGINE = InnoDB;

-- Table `w_auth_ip`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_auth_ip` (
        `IP_addr` VARCHAR(45) NOT NULL,
        `cidr` INT (2) NOT NULL DEFAULT 32,
        `Title` VARCHAR(245) NOT NULL,
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`IP_addr`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `w_groups`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_groups` (
        `Group_Code` VARCHAR(5) NOT NULL,
        `Title` VARCHAR(45) NOT NULL DEFAULT '',
        `Description` VARCHAR(255) NOT NULL DEFAULT '',
        `Default_Access_Level` VARCHAR(5) NOT NULL DEFAULT '',
        `Max_Level` VARCHAR(5) NOT NULL DEFAULT '',
        `Min_Access_Level` VARCHAR(5) NOT NULL DEFAULT '',
        `Cookie_Restricted` BIT (1) NOT NULL DEFAULT 0,
        `IP_Restricted` BOOLEAN DEFAULT 0,
        `Password_Policy` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`Group_Code`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `w_group_ip`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_group_ip` (
        `id` INT (11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `Group_Code` VARCHAR(5) NOT NULL DEFAULT '',
        `IP_addr` VARCHAR(45) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `w_idp`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_idp` (
        `idIdp` INT (11) NOT NULL AUTO_INCREMENT,
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
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `w_idp_secgroups`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_idp_secgroups` (
        `idIdpSecGroup` INT (11) NOT NULL AUTO_INCREMENT,
        `idIdp` INT (11) NOT NULL DEFAULT 0,
        `idSecGroup` VARCHAR(5) NOT NULL DEFAULT '',
        PRIMARY KEY (`idIdpSecGroup`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `w_user_log`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_user_log` (
        `Username` VARCHAR(100) NOT NULL,
        `Access_Date` DATETIME NOT NULL,
        `IP` VARCHAR(45) NOT NULL DEFAULT '',
        `Session_Id` VARCHAR(45) NOT NULL DEFAULT '',
        `Page` VARCHAR(45) NOT NULL DEFAULT '',
        `Action` VARCHAR(255) NOT NULL DEFAULT '',
        `Browser` VARCHAR(45) NOT NULL DEFAULT '',
        `OS` VARCHAR(45) NOT NULL DEFAULT ''
    ) ENGINE = MyISAM;

-- -----------------------------------------------------
-- Table `w_user_passwords`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_user_passwords` (
        `idPassword` INT (11) NOT NULL AUTO_INCREMENT,
        `idUser` INT (11) NOT NULL,
        `Enc_PW` VARCHAR(100) NOT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idPassword`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `w_users`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_users` (
        `idName` INT (11) NOT NULL,
        `User_Name` VARCHAR(100) NOT NULL DEFAULT '',
        `Enc_PW` VARCHAR(100) NOT NULL DEFAULT '',
        `PW_Change_Date` DATETIME DEFAULT NULL,
        `Chg_PW` BOOL NOT NULL DEFAULT TRUE,
        `idIdp` INT (11) NOT NULL DEFAULT 0,
        `default2Factor` VARCHAR(4) NULL,
        `totpSecret` VARCHAR(45) NOT NULL DEFAULT '',
        `emailSecret` VARCHAR(45) NOT NULL DEFAULT '',
        `backupSecret` VARCHAR(45) NOT NULL DEFAULT '',
        `pass_rules` BOOL NOT NULL DEFAULT TRUE,
        `PW_Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Status` VARCHAR(4) NOT NULL DEFAULT '',
        `Certificate` VARCHAR(145) NOT NULL DEFAULT '',
        `Cookie` CHAR(32) NOT NULL DEFAULT '',
        `Session` CHAR(32) NOT NULL DEFAULT '',
        `Ip` VARCHAR(15) NOT NULL DEFAULT '',
        `Default_Page` VARCHAR(100) NOT NULL DEFAULT '',
        `Verify_Address` VARCHAR(4) NOT NULL DEFAULT '',
        `Last_Login` DATETIME DEFAULT NULL,
        `Hash_PW` CHAR(32) NOT NULL DEFAULT '',
        `Updated_By` VARCHAR(45) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`User_Name`)
    ) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `w_user_tokens`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `w_user_tokens` (
        `idToken` INT (11) NOT NULL AUTO_INCREMENT,
        `idName` INT (11) NOT NULL,
        `Token` VARCHAR(100) NOT NULL DEFAULT '',
        `Expires` INT (11) NOT NULL DEFAULT 0,
        `IP_Address` VARCHAR(45) NOT NULL DEFAULT '',
        `Timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`idToken`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 10;

-- -----------------------------------------------------
-- Table `oauth_clients`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `oauth_clients` (
        `client_id` VARCHAR(32) NOT NULL,
        `idName` INT NULL,
        `name` VARCHAR(45) NULL,
        `secret` VARCHAR(100) NULL,
        `revoked` TINYINT NULL,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`client_id`),
        INDEX `indx_idName` (`idName` ASC)
    );

-- -----------------------------------------------------
-- Table `oauth_client_scopes`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `oauth_client_scopes` (
        `oauth_client` VARCHAR(32) NOT NULL,
        `oauth_scope` VARCHAR(100) NOT NULL,
        `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`oauth_client`, `oauth_scope`),
        CONSTRAINT `fk_oauth_client` FOREIGN KEY (`oauth_client`) REFERENCES `oauth_clients` (`client_id`) ON DELETE CASCADE ON UPDATE NO ACTION
    );

-- -----------------------------------------------------
-- Table `oauth_access_tokens`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `oauth_access_tokens` (
        `id` VARCHAR(100) NOT NULL,
        `idName` INT NULL,
        `client_id` VARCHAR(32) NOT NULL,
        `name` VARCHAR(45) NULL,
        `scopes` TEXT NULL,
        `revoked` TINYINT NULL,
        `expires_at` DATETIME NULL,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `Timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `indx_idName` (`idName` ASC),
        CONSTRAINT `fk_client_id` FOREIGN KEY (`client_id`) REFERENCES `oauth_clients` (`client_id`) ON DELETE CASCADE ON UPDATE NO ACTION
    );

-- -----------------------------------------------------
-- Table `web_sites`
-- -----------------------------------------------------
CREATE TABLE
    IF NOT EXISTS `web_sites` (
        `idweb_sites` INT (11) NOT NULL AUTO_INCREMENT,
        `Site_Code` VARCHAR(5) NOT NULL,
        `Description` VARCHAR(245) NOT NULL DEFAULT '',
        `Relative_Address` VARCHAR(145) NOT NULL DEFAULT '',
        `Required_Group_Code` VARCHAR(45) NOT NULL DEFAULT '',
        `Path_To_CSS` VARCHAR(145) NOT NULL DEFAULT '',
        `Path_To_JS` VARCHAR(145) NOT NULL DEFAULT '',
        `Last_Updated` DATETIME DEFAULT NULL,
        `Updated_By` VARCHAR(45) NOT NULL,
        `Default_Page` VARCHAR(105) NOT NULL DEFAULT '',
        `Index_Page` VARCHAR(145) NOT NULL DEFAULT '',
        `HTTP_Host` VARCHAR(245) NOT NULL DEFAULT '',
        PRIMARY KEY (`idweb_sites`)
    ) ENGINE = InnoDB AUTO_INCREMENT = 5;

-- -----------------------------------------------------
--
-- Indexes
--
-- -----------------------------------------------------
ALTER TABLE `activity` ADD INDEX IF NOT EXISTS `Index_idName` (`idName` ASC);

ALTER TABLE `campaign` ADD UNIQUE KEY IF NOT EXISTS `Campaign_Code_UNIQUE` (`Campaign_Code`);

ALTER TABLE `checklist_item` ADD UNIQUE INDEX IF NOT EXISTS `Unique_Checklist_Item` (
    `Entity_Id` ASC,
    `GL_TableName` ASC,
    `GL_Code` ASC
);

ALTER TABLE `donations` Add INDEX IF NOT EXISTS `Activity_Id_INDEX` (`Activity_Id`);

ALTER TABLE `donations` ADD INDEX IF NOT EXISTS `Donor_Id_INDEX` (`Donor_Id`);

ALTER TABLE `emergency_contact` ADD INDEX IF NOT EXISTS `Index_idName` (`idName` ASC);

ALTER TABLE `fin_application` ADD INDEX IF NOT EXISTS `Index_idRegistration` (`idRegistration` ASC);

ALTER TABLE `guest_token` ADD INDEX IF NOT EXISTS `Index_idRegistration` (`idRegistration` ASC);

ALTER TABLE `hospital_stay` ADD INDEX IF NOT EXISTS `Index_idPatient` (`idPatient` ASC);

ALTER TABLE `hospital_stay` ADD INDEX IF NOT EXISTS `Index_idPsg` (`idPsg` ASC);

ALTER TABLE `invoice` ADD UNIQUE KEY IF NOT EXISTS `Invoice_Number_UNIQUE` (`Invoice_Number`);

ALTER TABLE `invoice` ADD INDEX IF NOT EXISTS `Index_Order_SO_Number` (`Order_Number` ASC, `Suborder_Number` ASC);

ALTER TABLE `invoice` ADD INDEX IF NOT EXISTS `Index_idGroup` (`idGroup` ASC);

ALTER TABLE `invoice` ADD INDEX IF NOT EXISTS `Index_Date` (`Invoice_Date` ASC);

ALTER TABLE `invoice` ADD INDEX IF NOT EXISTS `Index_SoldToId` (`Sold_To_Id` ASC);

ALTER TABLE `invoice` ADD INDEX IF NOT EXISTS `Index_Delagated` (`Delegated_Invoice_Id` ASC);

ALTER TABLE `invoice_line` ADD INDEX IF NOT EXISTS `ix_invoice_line_invoice_id` (`Invoice_Id` ASC);

ALTER TABLE `labels` ADD UNIQUE INDEX IF NOT EXISTS `Unique_Key_Categeory` (`Key` ASC, `Category` ASC);

CREATE INDEX IF NOT EXISTS `indx_idDocument` ON `link_doc` (`idDocument` ASC);

CREATE INDEX IF NOT EXISTS `indx_idGuest` ON `link_doc` (`idGuest` ASC);

CREATE INDEX IF NOT EXISTS `indx_idPsg` ON `link_doc` (`idPSG` ASC);

CREATE INDEX IF NOT EXISTS `indx_idReservation` ON `link_doc` (`idReservation` ASC);

ALTER TABLE `link_doc` ADD CONSTRAINT `fk_idDocument` FOREIGN KEY IF NOT EXISTS (`idDocument`) REFERENCES `document` (`idDocument`) ON DELETE CASCADE ON UPDATE NO ACTION;

CREATE INDEX IF NOT EXISTS `indx_idNote` ON `link_note` (`idNote`);

CREATE INDEX IF NOT EXISTS `indx_linkType` ON `link_note` (`linkType`);

CREATE INDEX IF NOT EXISTS `indx_idLink` ON `link_note` (`idLink`);

CREATE UNIQUE INDEX IF NOT EXISTS `unq_link` ON `link_note` (`idNote` ASC, `linkType` ASC, `idLink` ASC);

ALTER TABLE `name` ADD INDEX IF NOT EXISTS `Index_Name` (`Name_Last` ASC, `Name_First` ASC);

ALTER TABLE `name` ADD INDEX IF NOT EXISTS `iNameLastFirst` (`Name_Last_First`);

ALTER TABLE `name_address` ADD INDEX IF NOT EXISTS `iNA_ID` (`idName`);

ALTER TABLE `name_guest` ADD INDEX IF NOT EXISTS `INDEX_IdPsg` (`idPsg` ASC);

CREATE INDEX IF NOT EXISTS `INDEX_PHONE_SEARCH` ON name_phone (`Phone_Search`);

CREATE INDEX IF NOT EXISTS `INDEX_USERNAME` ON `note` (`User_Name` ASC);

CREATE INDEX IF NOT EXISTS `INDEX_CATEGORY` ON `note` (`Category` ASC);

ALTER TABLE `payment` ADD INDEX IF NOT EXISTS `Index_Date` (`Payment_Date` ASC);

ALTER TABLE `payment_auth` ADD INDEX IF NOT EXISTS `Index_idPayment` (`idPayment` ASC);

ALTER TABLE `payment_info_check` ADD INDEX IF NOT EXISTS `Index_idPayment` (`idPayment` ASC);

CREATE INDEX IF NOT EXISTS `ix_Payment_Id` ON payment_invoice (Payment_Id);

CREATE INDEX IF NOT EXISTS `ix_Invoice_Id` ON payment_invoice (Invoice_Id);

ALTER TABLE `oauth_access_tokens` ADD INDEX IF NOT EXISTS `fk_client_id_idx` (`client_id` ASC);

ALTER TABLE `oauth_access_tokens` ADD CONSTRAINT `fk_client_id` FOREIGN KEY IF NOT EXISTS (`client_id`) REFERENCES `oauth_clients` (`client_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `psg` ADD UNIQUE INDEX IF NOT EXISTS `idPatient_UNIQUE` (`idPatient` ASC);

ALTER TABLE `registration` ADD INDEX IF NOT EXISTS `Index_idPsg` (`idPsg` ASC);

ALTER TABLE `incident_report` ADD INDEX IF NOT EXISTS `Index_Psg_Id` (`Psg_Id`);

ALTER TABLE `report_field_sets` ADD UNIQUE KEY IF NOT EXISTS `U_INDEX_TRC` (`Title`, `Report`, `Created_by`);

ALTER TABLE `reservation` ADD INDEX IF NOT EXISTS `Index_idregistration` (`idRegistration` ASC);

ALTER TABLE `reservation` ADD INDEX IF NOT EXISTS `Index_idGuest` (`idGuest` ASC);

ALTER TABLE `reservation` ADD INDEX IF NOT EXISTS `Index_Expected_Arrival` (`Expected_Arrival` ASC);

ALTER TABLE `reservation` ADD INDEX IF NOT EXISTS `Index_Expected_Departure` (`Expected_Departure` ASC);

ALTER TABLE `reservation` ADD INDEX IF NOT EXISTS `Index_idHosptial_Stay` (`idHospital_Stay` ASC);

ALTER TABLE `reservation` ADD INDEX IF NOT EXISTS `Index_idReferral_Doc` (`idReferralDoc` ASC);

ALTER TABLE `reservation` ADD INDEX IF NOT EXISTS `Index_Status` (`Status` ASC);

ALTER TABLE `reservation_multiple` ADD INDEX IF NOT EXISTS `host_id_index` (`Host_Id` ASC);

ALTER TABLE `reservation_multiple` ADD UNIQUE INDEX IF NOT EXISTS `Child_Id_UNIQUE` (`Child_Id` ASC);

ALTER TABLE `resource_room` ADD INDEX IF NOT EXISTS `Index_idResource` (`idResource` ASC);

ALTER TABLE `resource_room` ADD INDEX IF NOT EXISTS `Index_idRoom` (`idRoom` ASC);

ALTER TABLE `resource_use` ADD INDEX IF NOT EXISTS `Index_idResource` (`idResource` ASC);

ALTER TABLE `stays` ADD INDEX IF NOT EXISTS `index_idVisit_Span` (`idVisit` ASC, `Visit_Span` ASC);

ALTER TABLE `stays` ADD INDEX IF NOT EXISTS `index_Span_Start` (`Span_Start_Date` ASC);

ALTER TABLE `stays` ADD INDEX IF NOT EXISTS `index_Span_End` (`Span_End_Date` ASC);

ALTER TABLE `stays` ADD INDEX IF NOT EXISTS `index_stay_status` (`Status` ASC);

ALTER TABLE `stays` ADD INDEX IF NOT EXISTS `index_idName` (`idName` ASC);

ALTER TABLE `stays` ADD CONSTRAINT `fk_visit` FOREIGN KEY IF NOT EXISTS (`idVisit`, `Visit_Span`) REFERENCES `visit` (`idVisit`, `Span`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `vehicle` ADD INDEX IF NOT EXISTS `INDEX_LICENSE` (`License_Number` ASC);

ALTER TABLE `vehicle` ADD INDEX IF NOT EXISTS `INDEX_IdNAME` (`idName` ASC);

ALTER TABLE `vehicle` ADD INDEX IF NOT EXISTS `INDEX_REG` (`idRegistration` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_idPrimaryGuest` (`idPrimaryGuest` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_idRegistration` (`idRegistration` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_idHosp_Stay` (`idHospital_stay` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_Span_Start` (`Span_Start` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_Span_End` (`Span_End` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_Exp_Depart` (`Expected_Departure` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_Arrival_Date` (`Arrival_Date` ASC);

ALTER TABLE `visit` ADD INDEX IF NOT EXISTS `Index_idReservation` (`idReservation` ASC);

ALTER TABLE `document` ADD INDEX IF NOT EXISTS `Indx_Status` (`Status` ASC);

ALTER TABLE `document` ADD INDEX IF NOT EXISTS `indx_Type` (`Category` ASC, `Type` ASC);

CREATE INDEX IF NOT EXISTS `idx_document_status_type_category_id`
ON `document` (`Status`, `Type`, `Category`, `idDocument`);

ALTER TABLE `name_log` ADD INDEX IF NOT EXISTS `INDEX_IDNAME` (`idName` ASC);

ALTER TABLE `visit_log` ADD INDEX IF NOT EXISTS `INDX_IDNAME` (`idName` ASC),
ADD INDEX IF NOT EXISTS `INDX_IDVISIT` (`idVisit` ASC, `Span` ASC);

ALTER TABLE `w_idp_secgroups` ADD UNIQUE INDEX IF NOT EXISTS `unq_idp_secgroup` (`idIdp` ASC, `idSecGroup` ASC);

CREATE INDEX IF NOT EXISTS `idx_name_vol_category_code_id`
ON `name_volunteer2` (`Vol_Category`, `Vol_Code`, `idName`); -- optimize queries where filtering billing agents, etc

ALTER TABLE `w_user_log`
ADD INDEX IF NOT EXISTS `idx_access_date` (`Access_Date`); -- optimize manager access log query

ALTER TABLE `reservation_log`
ADD INDEX IF NOT EXISTS `idx_rlog_idName` (`idName`);

ALTER TABLE `visit_log`
ADD INDEX IF NOT EXISTS `idx_vlog_idName` (`idName`),
ADD INDEX IF NOT EXISTS `idx_vlog_idPsg` (`idPsg`);

-- -------Functions-------
--
-- function `dateDefaultNow`
--
CREATE
OR REPLACE FUNCTION `datedefaultnow` (dt DateTime) RETURNS DATETIME DETERMINISTIC NO SQL RETURN case
    when dt is null then now()
    when DATE(dt) < DATE (now()) then now()
    else dt
end;

--
-- function `fiscal_year`
--
CREATE OR REPLACE FUNCTION `fiscal_year`(dt DateTime, adjust int) RETURNS Datetime NO SQL DETERMINISTIC RETURN DATE_ADD(dt, INTERVAL adjust MONTH);