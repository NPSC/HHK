<?php
namespace HHK\Tables\Insurance;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * InsuranceRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class InsuranceRS extends AbstractTableRS {

    public $idInsurance;   // INT(11) NOT NULL PRIMARY KEY,
    public $idInsuranceType;   // int(3) NOT NULL DEFAULT 0,
    public $Title; // varchar(45) NOT NULL DEFAULT '',
    public $Status;  // varchar(1) NOT NULL DEFAULT 'a',
    public $Timestamp; //TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "insurance") {

        $this->idInsurance = new DB_Field("idInsurance", 0, new DbIntSanitizer(3));
        $this->idInsuranceType = new DB_Field("idInsuranceType", 0, new DbIntSanitizer(3));
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45));
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(1));

        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}
?>