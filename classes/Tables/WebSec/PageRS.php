<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * PageRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PageRS extends AbstractTableRS {
    
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
?>