<?php
namespace HHK\Tables\Reservation;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer};

/**
 * ReservationInvoiceLineRS.php
 *
 * Links reservation prepayment invoice lines to their reservation.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReservationInvoiceLineRS extends AbstractTableRS {

    public DB_Field $Reservation_Id;  // INT NOT NULL,
    public DB_Field $Invoice_Line_id;  // INT NOT NULL,

    function __construct($TableName = "reservation_invoice_line") {

        $this->Reservation_Id = new DB_Field("Reservation_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Invoice_Line_id = new DB_Field("Invoice_Line_id", 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
