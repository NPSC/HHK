<?php
namespace HHK\Tables\Payment;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * PaymentMethodRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentMethodRS extends AbstractTableRS {

    public DB_Field $idPayment_method;  // INT(11) NOT NULL AUTO_INCREMENT,
    public DB_Field $Method_Name;  // VARCHAR(45) NOT NULL DEFAULT '',
    public DB_Field $Gl_Code;  // VARCHAR(45) NOT NULL DEFAULT '',
    public DB_Field $Timestamp;  // TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    function __construct($TableName = "payment_method") {

        $this->idPayment_method = new DB_Field("idPayment_method", 0, new DbIntSanitizer());
        $this->Method_Name = new DB_Field("Method_Name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Gl_Code = new DB_Field("Gl_Code", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
