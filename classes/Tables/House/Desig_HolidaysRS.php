<?php
namespace Tables\House;

use Tables\AbstractTableRS;
use Tables\Fields\{DB_Field, DbIntSanitizer, DbDateSanitizer, DbStrSanitizer};

/**
 * HouseRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Desig_HolidaysRS extends AbstractTableRS {

    public $Year;   // int(11) NOT NULL,
    public $dh1;   // date DEFAULT NULL,
    public $dh2;   // date DEFAULT NULL,
    public $dh3;   // date DEFAULT NULL,
    public $dh4;   // date DEFAULT NULL,
    public $Updated_By;   // varchar(45) NOT NULL DEFAULT '',
    public $Last_Updated;   // datetime DEFAULT NULL,

    function __construct($TableName = "desig_holidays") {

        $this->Year = new DB_Field("Year", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->dh1 = new DB_Field("dh1", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->dh2 = new DB_Field("dh2", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->dh3 = new DB_Field("dh3", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);
        $this->dh4 = new DB_Field("dh4", NULL, new DbDateSanitizer("Y-m-d"), TRUE, TRUE);

        $this->Updated_By = new DB_Field("Updated_By", "", new DbStrSanitizer(45), FALSE);
        $this->Last_Updated = new DB_Field("Last_Updated", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>