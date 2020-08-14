<?php
namespace HHK\ Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbDecimalSanitizer, DbDateSanitizer};

/**
 * PaymentInvoiceRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentInvoiceRS extends AbstractTableRS {
    
    public $idPayment_Invoice;   // INTEGER NOT NULL,
    public $Payment_Id;   // INTEGER,
    public $Invoice_Id;   // INTEGER,
    public $Amount;   // DECIMAL(22,10),
    public $Create_Datetime;   // TIMESTAMP NOT NULL,
    
    function __construct($TableName = "payment_invoice") {
        $this->idPayment_Invoice = new DB_Field("idPayment_Invoice", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Payment_Id = new DB_Field("Payment_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Invoice_Id = new DB_Field("Invoice_Id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Create_Datetime = new DB_Field("Create_Datetime", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        
        parent::__construct($TableName);
    }
    
}
?>