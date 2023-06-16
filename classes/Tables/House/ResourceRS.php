<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};
/**
 * ResourceRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ResourceRS extends AbstractTableRS {

    public $idResource;    // int(11) NOT NULL AUTO_INCREMENT,
    public $idSponsor; // int(11) NOT NULL DEFAULT '0',
    public $Title;    // varchar(45) NOT NULL DEFAULT '',
    public $Utilization_Category;  // VARCHAR(5) NOT NULL DEFAULT ''
    public $Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Background_Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Text_Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Border_Color;    // varchar(15) NOT NULL DEFAULT '',
    public $Type;    // varchar(15) NOT NULL DEFAULT '',
    public $Category;    // varchar(5) NOT NULL DEFAULT '',
    public $Partition_Size;    // varchar(5) NOT NULL DEFAULT '',
    public $Util_Priority;    // varchar(5) NOT NULL DEFAULT '',
    public $Status;    // varchar(5) NOT NULL DEFAULT '',
    public $Rate_Adjust;    // decimal(15,2) NOT NULL DEFAULT '0.00',
    public $Rate_Adjust_Code;    // varchar(15) NOT NULL DEFAULT '',
    public $Retired_At; // datetime DEFAULT NULL;
    public $Updated_By;    // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;    // datetime DEFAULT NULL,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()


    function __construct($TableName = 'resource') {
        $this->idResource = new DB_Field('idResource', 0, new DbIntSanitizer());
        $this->idSponsor = new DB_Field('idSponsor', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Title = new DB_Field('Title', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->Utilization_Category = new DB_Field('Utilization_Category', '', new DbStrSanitizer(5));
        $this->Color = new DB_Field('Color', '', new DbStrSanitizer(15));
        $this->Background_Color = new DB_Field('Background_Color', '', new DbStrSanitizer(15));
        $this->Text_Color = new DB_Field('Text_Color', '', new DbStrSanitizer(15));
        $this->Border_Color = new DB_Field('Border_Color', '', new DbStrSanitizer(15));
        $this->Type = new DB_Field("Type", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Category = new DB_Field("Category", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Partition_Size = new DB_Field("Partition_Size", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Util_Priority = new DB_Field("Util_Priority", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Rate_Adjust = new DB_Field("Rate_Adjust", "", new DbStrSanitizer(15), TRUE, TRUE);
        $this->Rate_Adjust_Code = new DB_Field("Rate_Adjust_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Retired_At = new DB_Field("Retired_At", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE, TRUE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}
?>