<?php
namespace HHK\Tables\PaymentGW;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * SsoTokenRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of SsoTokenRS
 *
 * @author Eric
 */
class SsoTokenRS extends AbstractTableRS {
    
    public DB_Field $Token;  // varchar(136) NOT NULL DEFAULT '',
    public DB_Field $idPaymentAuth;  // INT NOT NULL DEFAULT 0
    public DB_Field $idName;  // int(11) NOT NULL,
    public DB_Field $CardHolderName;  // VARCHAR(45) NOT NULL DEFAULT ''
    public DB_Field $idGroup;  // int(11) NOT NULL,
    public DB_Field $InvoiceNumber;  // varchar(36) NOT NULL DEFAULT '',
    public DB_Field $Amount;  // DECIMAL(11,2) NOT NULL DEFAULT 0.00,
    public DB_Field $State;  // varchar(5) NOT NULL DEFAULT '',
    public DB_Field $Updated_By;  // varchar(45) NOT NULL DEFAULT '',
    public DB_Field $Last_Updated;  // datetime DEFAULT NULL,
    public DB_Field $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = 'ssotoken') {
        $this->Token = new DB_Field('Token', '', new DbStrSanitizer(136), TRUE, TRUE);
        $this->idPaymentAuth = new DB_Field('idPaymentAuth', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idName = new DB_Field('idName', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->CardHolderName = new DB_Field('CardHolderName', '', new DbStrSanitizer(45), TRUE, TRUE);
        $this->idGroup = new DB_Field('idGroup', 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->InvoiceNumber = new DB_Field('InvoiceNumber', '', new DbStrSanitizer(36), TRUE, TRUE);
        $this->Amount = new DB_Field('Amount', 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->State = new DB_Field('State', '', new DbStrSanitizer(5), TRUE, TRUE);
        $this->Updated_By = new DB_Field('Updated_By', '', new DbStrSanitizer(45), TRUE, True);
        $this->Last_Updated = new DB_Field('Last_Updated', null, new DbDateSanitizer('Y-m-d H:i:s'), FALSE);
        $this->Timestamp = new DB_Field('Timestamp', null, new DbDateSanitizer('Y-m-d H:i:s'), FALSE);
        
        parent::__construct($TableName);
    }
}
?>