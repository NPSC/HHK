<?php
namespace HHK\ Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/**
 * InvoiceLineTypeRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class InvoiceLineTypeRS extends AbstractTableRS {
    
    public $id;  // INTEGER NOT NULL,
    public $Description;  // VARCHAR(50) NOT NULL,
    public $Order_Position;  // INTEGER NOT NULL,
    
    function __construct($TableName = 'invoice_line_type') {
        $this->id = new DB_Field('id', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Description = new DB_Field('Description', '', new DbStrSanitizer(50), TRUE, TRUE);
        $this->Order_Position = new DB_Field('Order_Position', 0, new DbIntSanitizer(), TRUE, TRUE);
        parent::__construct($TableName);
    }
}
?>