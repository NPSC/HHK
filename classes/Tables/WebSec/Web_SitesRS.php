<?php
namespace HHK\ Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};

/**
 * Web_SitesRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Web_SitesRS extends AbstractTableRS {
    
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
?>