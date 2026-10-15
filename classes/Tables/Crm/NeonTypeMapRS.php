<?php
namespace HHK\Tables\Crm;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbDateSanitizer, DbIntSanitizer, DbStrSanitizer};

/**
 * NeonTypeMapRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NeonTypeMapRS extends AbstractTableRS {

    public DB_Field $idNeon_type_map;  // INT NOT NULL AUTO_INCREMENT
    public DB_Field $List_Name;  // VARCHAR(45) NOT NULL
    public DB_Field $Neon_Name;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Neon_Type_Code;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Neon_Type_Name;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $HHK_Type_Code;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $Last_Updated;  // DATETIME NULL
    public DB_Field $Timestamp;  // TIMESTAMP NOT NULL DEFAULT NOW()

    function __construct($TableName = "neon_type_map") {

        $this->idNeon_type_map = new DB_Field("idNeon_type_map", 0, new DbIntSanitizer());
        $this->List_Name = new DB_Field("List_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Neon_Name = new DB_Field("Neon_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Neon_Type_Code = new DB_Field("Neon_Type_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Neon_Type_Name = new DB_Field("Neon_Type_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->HHK_Type_Code = new DB_Field("HHK_Type_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
