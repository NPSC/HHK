<?php
namespace HHK\Tables\Reservation;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer};

/**
 * Reservation_ReferralRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Reservation_ReferralRS extends AbstractTableRS {

    public $Reservation_Id;  // int(11) NOT NULL DEFAULT '0',
    public $Document_Id;  // INT NOT NULL DEFAULT 0 ,

    function __construct($TableName = "reservation_referral") {

        $this->Reservation_Id = new DB_Field("Reservation_Id", 0, new DbIntSanitizer());
        $this->Document_Id = new DB_Field("Document_Id", 0, new DbIntSanitizer());

        parent::__construct($TableName);
    }
}
?>