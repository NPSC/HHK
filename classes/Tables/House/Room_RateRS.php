<?php
namespace Tables\House;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDecimalSanitizer, DbDateSanitizer};

/**
 * HouseRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Room_RateRS extends AbstractTableRS {

    public $idRoom_rate;    // int(11) NOT NULL AUTO_INCREMENT,
    public $Title;    // varchar(45) NOT NULL DEFAULT '',
    public $Description;    // varchar(245) NOT NULL DEFAULT '',
    public $FA_Category;    // varchar(2) NOT NULL DEFAULT '',
    public $PriceModel;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Reduced_Rate_1;    // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Reduced_Rate_2;    // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Reduced_Rate_3;    // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Min_Rate;    // decimal(10,4) NOT NULL DEFAULT '0.0000',
    public $Status;    // varchar(4) NOT NULL DEFAULT '',
    public $Updated_By;    // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;    // datetime DEFAULT NULL,
    public $Timestamp;    // timestamp NULL DEFAULT NULL,

    function __construct($TableName = 'room_rate') {
        $this->idRoom_rate = new DB_Field('idRoom_rate', 0, new DbIntSanitizer());
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Description = new DB_Field('Description', '', new DbStrSanitizer(245), TRUE, TRUE);
        $this->FA_Category = new DB_Field('FA_Category', '', new DbStrSanitizer(2), TRUE, TRUE);
        $this->PriceModel = new DB_Field('PriceModel', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Reduced_Rate_1 = new DB_Field('Reduced_Rate_1', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Reduced_Rate_2 = new DB_Field('Reduced_Rate_2', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Reduced_Rate_3 = new DB_Field('Reduced_Rate_3', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Min_Rate = new DB_Field('Min_Rate', 0, new DbDecimalSanitizer(), TRUE, TRUE);

        $this->Status = new DB_Field('Status', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field('Last_Updated', NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field('Timestamp', NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        // This line stays at the end of the function.
        parent::__construct($TableName);
    }

}
?>