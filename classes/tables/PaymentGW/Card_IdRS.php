<?php
namespace HHK\ Tables\PaymentGW;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer, DbDecimalSanitizer};

/**
 * Card_IdRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Card_IdRS
 *
 * @author Eric
 */
class Card_IdRS extends AbstractTableRS {
    
    public $idName;   // int(11) NOT NULL,
    public $idGroup;   // int(11) NOT NULL,
    public $CardID;   // varchar(36) NOT NULL DEFAULT '',
    public $Init_Date;   // datetime DEFAULT NULL,
    public $ReturnCode;   // int(11) NOT NULL DEFAULT '0',
    public $Frequency;   // varchar(9) NOT NULL DEFAULT '',
    public $OperatorID;   // varchar(10) NOT NULL DEFAULT '',
    public $ResponseCode;   // int(11) NOT NULL DEFAULT '0',
    public $Transaction;   // varchar(14) NOT NULL DEFAULT '',
    public $InvoiceNumber;   // varchar(36) NOT NULL DEFAULT '',
    public $Amount;  // DECIMAL(11,2) NOT NULL DEFAULT 0.00,
    public $Merchant;   // varchar(45) NOT NULL DEFAULT '',
    
    
    function __construct($TableName = "card_id") {
        $this->idName = new DB_Field("idName", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGroup = new DB_Field("idGroup", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->CardID = new DB_Field("CardID", "", new DbStrSanitizer(36), TRUE, TRUE);
        $this->Init_Date = new DB_Field("Init_Date", NULL, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->ReturnCode = new DB_Field("ReturnCode", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Frequency = new DB_Field("Frequency", "", new DbStrSanitizer(9), TRUE, TRUE);
        $this->OperatorID = new DB_Field("OperatorID", "", new DbStrSanitizer(10), TRUE, TRUE);
        $this->ResponseCode = new DB_Field("ResponseCode", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->Transaction = new DB_Field("Transaction", "", new DbStrSanitizer(14), TRUE, TRUE);
        $this->InvoiceNumber = new DB_Field("InvoiceNumber", "", new DbStrSanitizer(36), TRUE, TRUE);
        $this->Amount = new DB_Field("Amount", 0, new DbDecimalSanitizer(), TRUE, TRUE);
        $this->Merchnt = new DB_Field("Merchant", "", new DbStrSanitizer(45), TRUE, TRUE);
        
        parent::__construct($TableName);
    }
}
?>