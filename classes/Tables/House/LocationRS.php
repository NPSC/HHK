<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * LocationRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class LocationRS extends AbstractTableRS {

    public $idLocation;   // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;   // varchar(45) DEFAULT '',
    public $Description;   // varchar(245) DEFAULT '',
    public $Status;   // varchar(5) DEFAULT '',
    public $Address;   // varchar(145) NOT NULL DEFAULT '',
    public $Merchant;   // varchar(45) NOT NULL DEFAULT '',
    public $Map;   // varchar(45) NOT NULL DEFAULT '',
    public $Owner_Id;   // int(11) NOT NULL DEFAULT '0',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "location") {

        $this->idLocation = new DB_Field("idLocation", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45));
        $this->Description = new DB_Field("Description", "", new DbStrSanitizer(2000));
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5));
        $this->Address = new DB_Field("Address", "", new DbStrSanitizer(145));
        $this->Merchant = new DB_Field("Merchant", "", new DbStrSanitizer(45));
        $this->Map = new DB_Field("Map", "", new DbStrSanitizer(45));
        $this->Owner_Id = new DB_Field("Owner_Id", 0, new DbIntSanitizer());
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}
