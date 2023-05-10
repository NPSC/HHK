<?php
namespace HHK\Tables\Insurance;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/**
 * InsuranceTypeRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class InsuranceTypeRS extends AbstractTableRS {

    public $idInsurance_type;   // INT(3) NOT NULL PRIMARY KEY,
    public $Title;   // varchar(45) NOT NULL DEFAULT '',
    public $Is_Primary; // INT(1) NOT NULL DEFAULT 0,
    public $List_Order; // INT(3) NOT NULL DEFAULT 0,
    public $Status;  // varchar(1) NOT NULL DEFAULT 'a',

    function __construct($TableName = "insurance_type") {

        $this->idInsurance_type = new DB_Field("idInsurance_type", 0, new DbIntSanitizer());
        $this->Title = new DB_Field("Title", '', new DbStrSanitizer(45));
        $this->Is_Primary = new DB_Field("Is_Primary", 0, new DbIntSanitizer());
        $this->List_Order = new DB_Field("List_Order", 0, new DbIntSanitizer());
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(1));

        parent::__construct($TableName);

    }

}
?>