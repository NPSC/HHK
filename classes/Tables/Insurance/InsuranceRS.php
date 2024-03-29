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

    public $Order; // INT(3) NOT NULL DEFAULT 0,

    public $Timestamp; //TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,


    /**
     * Summary of __construct
     * @param mixed $TableName
     */
    function __construct($TableName = "insurance") {

        $this->idInsurance = new DB_Field("idInsurance", 0, new DbIntSanitizer());
        $this->idInsuranceType = new DB_Field("idInsuranceType", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(45));
        $this->Order = new DB_Field("Order", 0, new DbIntSanitizer());

        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);

    }

}
?>