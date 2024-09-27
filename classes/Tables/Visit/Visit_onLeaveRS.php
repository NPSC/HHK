<?php
namespace HHK\Tables\Visit;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * Visit_onLeaveRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Visit_onLeaveRS extends AbstractTableRS {
    
    public $idVisit;  // int(11) NOT NULL,
    public $Span;  // int(11) NOT NULL DEFAULT '0',
    public $Pledged_Rate;  // decimal(10,2) NOT NULL DEFAULT '0.00',
    public $Rate_Category;  // varchar(5) NOT NULL DEFAULT '',
    public $idRoom_rate;  // int(11) not null default 0,
    public $Rate_Glide_Credit;  // int(11) NOT NULL DEFAULT '0',
    public $Rate_Adjust;  // decimal(10,4) NOT NULL DEFAULT '0.0000',
    public $idRateAdjust; // varchar(5) NULL DEFAULT '0',
    public $Timestamp;  // timestamp NULL DEFAULT NULL,
    
    function __construct($TableName = 'visit_onleave') {
        
        $this->idVisit = new DB_Field('idVisit', 0, new DbIntSanitizer());
        $this->Span = new DB_Field('Span', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Rate_Category = new DB_Field('Rate_Category', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->idRoom_rate = new DB_Field('idRoom_rate', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Rate_Glide_Credit = new DB_Field('Rate_Glide_Credit', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Rate_Adjust = new DB_Field('Rate_Adjust', "0.00", new DbDecimalSanitizer(), TRUE, TRUE);
        $this->idRateAdjust = new DB_Field('idRateAdjust', '0', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Pledged_Rate = new DB_Field('Pledged_Rate', "0.00", new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Timestamp = new DB_Field('Timestamp', NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>