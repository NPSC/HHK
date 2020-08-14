<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};

/**
 * ResourceUseRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ResourceUseRS extends AbstractTableRS {

    /**
     * @var DB_Field
     */
    public $idResource_use;  // int(11) NOT NULL AUTO_INCREMENT,
    /**
     * @var DB_Field
     */
    public $idResource;  // int(11) NOT NULL DEFAULT '0',
    /**
     * @var DB_Field
     */
    public $idRoom;  // int(11) NOT NULL DEFAULT '0',
    /**
     * @var DB_Field
     */
    public $Start_Date;  // datetime DEFAULT NULL,
    /**
     * @var DB_Field
     */
    public $End_Date;  // datetime DEFAULT NULL,
    /**
     * @var DB_Field
     */
    public $Status;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var DB_Field
     */
    public $OOS_Code;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var DB_Field
     */
    public $Unavail_Code;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var DB_Field
     */
    public $Room_State;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var DB_Field
     */
    public $Room_Availability;  // varchar(5) NOT NULL DEFAULT '',
    /**
     * @var DB_Field
     */
    public $Notes;  // varchar(245) NOT NULL DEFAULT '',
    /**
     * @var DB_Field
     */
    public $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    /**
     * @var DB_Field
     */
    public $Last_Updated;    // datetime DEFAULT NULL,
    /**
     * @var DB_Field
     */
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now()

    function __construct($TableName = "resource_use") {
        $this->idResource_use = new DB_Field("idResource_use", 0, new DbIntSanitizer());
        $this->idResource = new DB_Field("idResource", 0, new DbIntSanitizer());
        $this->idRoom = new DB_Field("idRoom", 0, new DbIntSanitizer());
        $this->Start_Date = new DB_Field("Start_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->End_Date = new DB_Field("End_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->OOS_Code = new DB_Field("OOS_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Unavail_Code = new DB_Field("Unavail_Code", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Room_State = new DB_Field("Room_State", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Room_Availability = new DB_Field("Room_Availability", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Notes = new DB_Field("Notes", "", new DbStrSanitizer(245), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE, TRUE);
        $this->Last_Updated = new DB_Field("Last_Updated", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }
}
?>