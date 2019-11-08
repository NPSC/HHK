<?php

/**
 * WebSec.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 * Description of WebSec
 * @package name
 * @author Eric
 */
class W_authRS extends TableRS {

    public $idName;  // int(11) NOT NULL,
    public $Role_Id;  // varchar(3) NOT NULL DEFAULT '',
    public $Organization_Id;  // varchar(3) NOT NULL DEFAULT '',
    public $Policy_id;  // int(11) NOT NULL DEFAULT '0',
    public $User_Name;  // varchar(245) NOT NULL DEFAULT '',
    //public $Ticket;  // char(128) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '0',
    public $Status;  // varchar(2) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "w_auth") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Role_Id = new DB_Field("Role_Id", "", new DbStrSanitizer(3), TRUE, TRUE);
        $this->Organization_Id = new DB_Field("Organization_Id", "", new DbStrSanitizer(3), TRUE, TRUE);
        $this->Policy_id = new DB_Field("Policy_id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(245), TRUE, TRUE);

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(2), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class W_auth_ipRS extends TableRS {
	
	public $IP; // varchar(45) NOT NULL
	
	function __construct($TableName = "w_auth_ip") {
		$this->IP = new DB_Field("IP", "", new DbStrSanitizer(45), TRUE, TRUE);
		parent::__construct($TableName);
	}
}

class W_usersRS extends TableRS {

    public $idName;  // int(11) NOT NULL,
    public $User_Name;  // varchar(100) NOT NULL DEFAULT '',
    public $Enc_PW;  // varchar(100) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    public $Certificate;  // varchar(145) NOT NULL DEFAULT '',
    //public $Cookie;  // char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    //public $Session;  // char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    public $Ip;  // varchar(15) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '',
    public $Verify_Address;  // varchar(4) NOT NULL DEFAULT '',
    public $Last_Login;  // datetime DEFAULT NULL,
    public $Default_Page;  // varchar(100) NOT NULL DEFAULT '',
    public $PW_Change_Date;  // DATETIME NULL
    public $PW_Updated_By;  // VARCHAR(45) NOT NULL DEFAULT ''
    public $Status;  // varchar(4) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "w_users") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->User_Name = new DB_Field("User_Name", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Enc_PW = new DB_Field("Enc_PW", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Certificate = new DB_Field("Certificate", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Default_Page = new DB_Field("Default_Page", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Ip = new DB_Field("Ip", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Verify_Address = new DB_Field("Verify_Address", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Last_Login = new DB_Field("Last_Login", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->PW_Change_Date = new DB_Field("PW_Change_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->PW_Updated_By = new DB_Field("PW_Updated_By", "", new DbStrSanitizer(45), FALSE);

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class W_groupsRS extends TableRS {

    public $Group_Code;  // varchar(5) NOT NULL DEFAULT '',
    public $Title;  // varchar(45) NOT NULL DEFAULT '',
    public $Description;  // varchar(255) NOT NULL DEFAULT '',
    public $Default_Access_Level;  // varchar(5) NOT NULL DEFAULT '',
    public $Max_Level;  // varchar(5) NOT NULL DEFAULT '',
    public $Min_Access_Level;  // varchar(5) NOT NULL DEFAULT '',
    public $Cookie_Restricted;  // bit(1) NOT NULL DEFAULT b'0',
    public $IP_Restricted; // tinyint NOT NULL DEFAULT 0,
    public $Password_Policy;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "w_groups") {

        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(5));
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(255), TRUE, TRUE);
        $this->Default_Access_Level = new DB_Field("Default_Access_Level", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Max_Level = new DB_Field("Max_Level", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Min_Access_Level = new DB_Field("Min_Access_Level", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Cookie_Restricted = new DB_Field("Cookie_Restricted", "", new DbBitSanitizer(), TRUE, TRUE);
        $this->IP_Restricted = new DB_Field("IP_Restricted", "", new DbBitSanitizer(), TRUE, TRUE);
        $this->Password_Policy = new DB_Field("Password_Policy", "", new DbStrSanitizer(5), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class Web_SitesRS extends TableRS {

    public $idweb_sites;  // int(11) NOT NULL AUTO_INCREMENT,
    public $Site_Code;  // varchar(5) NOT NULL,
    public $Description;  // varchar(245) NOT NULL DEFAULT '',
    public $Relative_Address;  // varchar(145) NOT NULL DEFAULT '',
    public $Required_Group_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Path_To_CSS;  // varchar(145) NOT NULL DEFAULT '',
    public $Path_To_JS;  // varchar(145) NOT NULL DEFAULT '',
    public $Default_Page;  // varchar(105) NOT NULL DEFAULT '',
    public $Index_Page;  // varchar(145) NOT NULL DEFAULT '',
    public $HTTP_Host;  // varchar(245) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime DEFAULT NULL,
    public $Updated_By;  // varchar(45) NOT NULL,

    function __construct($TableName = "web_sites") {

        $this->idweb_sites = new DB_Field("idweb_sites", 0, new DbIntSanitizer());
        $this->Site_Code = new DB_Field("Site_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(245), TRUE, TRUE);
        $this->Relative_Address = new DB_Field("Relative_Address", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Required_Group_Code = new DB_Field("Required_Group_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Path_To_CSS = new DB_Field("Path_To_CSS", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Path_To_JS = new DB_Field("Path_To_JS", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Default_Page = new DB_Field("Default_Page", "", new DbStrSanitizer(105), TRUE, TRUE);
        $this->Index_Page = new DB_Field("Index_Page", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->HTTP_Host = new DB_Field("HTTP_Host", "", new DbStrSanitizer(245), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class PageRS extends TableRS {

    public $idPage;  // int(11) NOT NULL AUTO_INCREMENT,
    public $File_Name;  // varchar(65) NOT NULL,
    public $Login_Page_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Title;  // varchar(45) NOT NULL DEFAULT '',
    public $Product_Code;  // VARCHAR(4) NOT NULL DEFAULT '' AFTER `Title`,
    public $Hide;  // INT(1) NOT NULL DEFAULT 0 AFTER `Type`;
    public $Web_Site;  // varchar(5) NOT NULL DEFAULT '',
    public $Menu_Parent;  // varchar(45) NOT NULL DEFAULT '',
    public $Menu_Position;  // varchar(45) NOT NULL DEFAULT '',
    public $Type;  // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;  // datetime NOT NULL,
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "page") {
        $this->idPage = new DB_Field("idPage", 0, new DbIntSanitizer());
        $this->File_Name = new DB_Field("File_Name", "", new DbStrSanitizer(65), TRUE, TRUE);
        $this->Login_Page_Id = new DB_Field("Login_Page_Id", 0, new DbIntSanitizer());
        $this->Hide = new DB_Field("Hide", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Product_Code = new DB_Field("Product_Code", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->Web_Site = new DB_Field("Web_Site", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Menu_Parent = new DB_Field("Menu_Parent", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Menu_Position = new DB_Field("Menu_Position", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(5), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}

class Id_SecurityGroupRS extends TableRS {

    public $idName;  // int(11) NOT NULL,
    public $Group_Code;  // varchar(5) NOT NULL,
    public $Timestamp;  // timestamp NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "id_securitygroup") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer());
        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class Page_SecurityGroupRS extends TableRS {

    public $idPage;  // int(11) NOT NULL,
    public $Group_Code;  // varchar(5) NOT NULL,
    public $Timestamp;  // timestamp NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "page_securitygroup") {
        $this->idPage = new DB_Field("idPage", 0, new DbIntSanitizer());
        $this->Group_Code = new DB_Field("Group_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}

class FbxRS extends TableRS {

    public $fb_id;  // varchar(45) NOT NULL,
    public $idName;  // int(11) NOT NULL,
    public $Status;  // varchar(2) NOT NULL DEFAULT '',
    public $fb_username;  // varchar(145) NOT NULL DEFAULT '',
    public $Approved_By;  // varchar(45) NOT NULL DEFAULT '',
    public $Approved_Date;  // datetime DEFAULT NULL,
    public $Dropped_Date;  // datetime DEFAULT NULL,
//    public $fb_Address_1;  // varchar(145) NOT NULL DEFAULT '',
//    public $fb_Address_2;  // varchar(45) NOT NULL DEFAULT '',
//    public $fb_City;  // varchar(45) NOT NULL DEFAULT '',
//    public $fb_State;  // varchar(45) NOT NULL DEFAULT '',
//    public $fb_Zip;  // varchar(15) NOT NULL DEFAULT '',
    public $fb_First_Name;  // varchar(45) NOT NULL DEFAULT '',
    public $fb_Last_Name;  // varchar(45) NOT NULL DEFAULT '',
    public $fb_Phone;  // varchar(25) NOT NULL DEFAULT '',
    public $fb_Email;  // varchar(145) NOT NULL DEFAULT '',
    public $PIFH_Username;  // varchar(45) NOT NULL DEFAULT '',
    public $Enc_Password;  // varchar(100) NOT NULL DEFAULT '',
    public $Access_Code;  // varchar(45) NOT NULL DEFAULT '',
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "fbx") {

        $this->fb_id = new DB_Field("fb_id", '', new DbStrSanitizer(45));
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(4), TRUE, TRUE);
        $this->fb_username = new DB_Field("fb_username", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->Approved_By = new DB_Field("Approved_By", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Approved_Date = new DB_Field("Approved_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Dropped_Date = new DB_Field("Dropped_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->fb_First_Name = new DB_Field("fb_First_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->fb_Last_Name = new DB_Field("fb_Last_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->fb_Phone = new DB_Field("fb_Phone", "", new DbStrSanitizer(25), TRUE, TRUE);
        $this->fb_Email = new DB_Field("fb_Email", "", new DbStrSanitizer(145), TRUE, TRUE);
        $this->PIFH_Username = new DB_Field("PIFH_Username", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Enc_Password = new DB_Field("Enc_Password", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Access_Code = new DB_Field("Access_Code", "", new DbStrSanitizer(45), TRUE, TRUE);

        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}

