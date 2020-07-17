<?php
namespace Tables\House;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * Fa_CategoryRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Fa_CategoryRS extends AbstractTableRS {

    public $idFa_category;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idHouse;   // int(11) NOT NULL DEFAULT '0',
    public $HouseHoldSize;   // int(11) NOT NULL DEFAULT '0',
    public $Income_A;   // int(11) NOT NULL DEFAULT '0',
    public $Income_B;   // int(11) NOT NULL DEFAULT '0',
    public $Income_C;   // int(11) NOT NULL DEFAULT '0',
    public $Income_D;   // int(11) NOT NULL DEFAULT '0',
    public $Status;   // varchar(5) NOT NULL DEFAULT '',
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,
    public $Timestamp;   // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "fa_category") {
        $this->idFa_category = new DB_Field("idFa_category", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idHouse = new DB_Field("idHouse", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->HouseHoldSize = new DB_Field("HouseHoldSize", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_A = new DB_Field("Income_A", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_B = new DB_Field("Income_B", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_C = new DB_Field("Income_C", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Income_D = new DB_Field("Income_D", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "", new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }

}
?>