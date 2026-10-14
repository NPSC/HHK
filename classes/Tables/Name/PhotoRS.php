<?php
namespace HHK\Tables\Name;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBlobSanitizer};

/**
 * PhotoRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PhotoRS extends AbstractTableRS {

    public DB_Field $idPhoto;  // INT NOT NULL AUTO_INCREMENT,
    public DB_Field $Image_Type;  // VARCHAR(45) NOT NULL DEFAULT '',
    public DB_Field $Category;  // VARCHAR(5) NOT NULL DEFAULT '',
    public DB_Field $Image;  // BLOB NULL,
    public DB_Field $Last_Updated;  // DATETIME NULL,
    public DB_Field $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '',
    public DB_Field $timestamp;  // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "photo") {

        $this->idPhoto = new DB_Field("idPhoto", 0, new DbIntSanitizer());
        $this->Image_Type = new DB_Field("Image_Type", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Image = new DB_Field("Image", null, new DbBlobSanitizer(), TRUE, TRUE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->timestamp = new DB_Field("timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
