<?php
namespace HHK\Tables\Crm;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbDateSanitizer, DbIntSanitizer, DbStrSanitizer};

/**
 * SfTypeMapRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class SfTypeMapRS extends AbstractTableRS {

    public DB_Field $idSf_type_map;  // INT NOT NULL AUTO_INCREMENT
    public DB_Field $List_Name;  // VARCHAR(100) NOT NULL DEFAULT ''
    public DB_Field $SF_Type_Code;  // VARCHAR(100) NULL DEFAULT ''
    public DB_Field $SF_Type_Name;  // VARCHAR(100) NULL DEFAULT ''
    public DB_Field $HHK_Type_Code;  // VARCHAR(100) NULL DEFAULT ''
    public DB_Field $Timestamp;  // TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP

    function __construct($TableName = "sf_type_map") {

        $this->idSf_type_map = new DB_Field("idSf_type_map", 0, new DbIntSanitizer());
        $this->List_Name = new DB_Field("List_Name", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->SF_Type_Code = new DB_Field("SF_Type_Code", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->SF_Type_Name = new DB_Field("SF_Type_Name", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->HHK_Type_Code = new DB_Field("HHK_Type_Code", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
