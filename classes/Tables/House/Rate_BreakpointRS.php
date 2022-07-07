<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * CleaningLogRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Rate_BreakpointRS extends AbstractTableRS {

    public $idrate_breakpoint;  // INT NOT NULL AUTO_INCREMENT,
    public $Household_Size;  // INT(4) NOT NULL,
    public $Rate_Category;  // VARCHAR(4) NOT NULL,
    public $Breakpoint;  // INT NOT NULL DEFAULT 0,
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT Current_Timestamp,

    function __construct($TableName = 'rate_breakpoint') {
        $this->idrate_breakpoint = new DB_Field('idrate_breakpoint', 0, new DbIntSanitizer());
        $this->Household_Size = new DB_Field('Household_Size', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Rate_Category = new DB_Field('Rate_Category', '', new DbStrSanitizer(4), TRUE, TRUE);
        $this->Breakpoint = new DB_Field('Breakpoint', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Timestamp = new DB_Field('Timestamp', NULL, new DbDateSanitizer("Y-m-d H:i:s"));

        // This line stays at the end of the function.
        parent::__construct($TableName);
    }
}
?>