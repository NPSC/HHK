<?php
namespace HHK\Tables;

use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBlobSanitizer};

/**
 * DocumentRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DocumentRS extends AbstractTableRS {

    public $idDocument;  // INT NOT NULL AUTO_INCREMENT,
    public $Title;  // VARCHAR(128) NOT NULL,
    public $Name;
    public $Category;  // VARCHAR(5) NOT NULL DEFAULT '',
    public $Type;  // VARCHAR(5) NOT NULL DEFAULT '',
    public $Folder;
    public $Language;
    public $Mime_Type;  // VARCHAR(85) NOT NULL DEFAULT '',
    public $Abstract;  // TEXT NULL,
    public $Doc;  // BLOB NULL,
    public $Style; //LONGTEXT NULL,
    public $Status;  // VARCHAR(5) NOT NULL,
    public $Last_Updated;  // DATETIME NULL,
    public $Created_By;
    public $Updated_By;  // VARCHAR(45) NOT NULL DEFAULT '',

    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now(),

    function __construct($TableName = "document") {

        $this->idDocument = new DB_Field("idDocument", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field("Title", "", new DbStrSanitizer(128), TRUE, TRUE);
        $this->Name = new DB_Field("Name", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Language = new DB_Field("Language", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Folder = new DB_Field("Folder", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Abstract = new DB_Field("Abstract", "", new DbStrSanitizer(1000), TRUE, TRUE);
        $this->Doc = new DB_Field("Doc", "", new DbBlobSanitizer(), TRUE, TRUE);
        $this->Style = new DB_Field("Style", "", new DbBlobSanitizer(), TRUE, TRUE);
        $this->Mime_Type = new DB_Field("Mime_Type", "", new DbStrSanitizer(85), TRUE, TRUE);

        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Created_By = new DB_Field("Created_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
?>