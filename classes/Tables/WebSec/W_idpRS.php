<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};
use HHK\Tables\Fields\DbBlobSanitizer;
use HHK\Tables\Fields\DbBitSanitizer;

/**
 * W_idpRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_idpRS extends AbstractTableRS {

    public $idIdp; // int(11) NOT NULL
    public $Name; // varchar(100) NOT NULL
    public $Logo_URL; // varchar(500),
    public $SSO_URL; // varchar(500)
    public $IdP_EntityId;  // varchar(500),
    public $IdP_Cert; // BLOB,
    public $enableSigning; // BOOL DEFAULT 1,
    public $enableEncryption; // BOOL DEFAULT 1,
    public $Status; // varchar(2),

    function __construct($TableName = "w_idp") {
        $this->idIdp = new DB_Field("idIdp", 0, new DbIntSanitizer());
        $this->Name = new DB_Field("Name", "", new DbStrSanitizer(100));
        $this->Logo_URL = new DB_Field("Logo_URL", "", new DbStrSanitizer(500));
        $this->SSO_URL = new DB_Field("SSO_URL", "", new DbStrSanitizer(500));
        $this->IdP_EntityId = new DB_Field("IdP_EntityId", "", new DbStrSanitizer(500));
        $this->IdP_Cert = new DB_Field("IdP_Cert", "", new DbBlobSanitizer());
        $this->enableSigning = new DB_Field("enableSigning", "1", new DbBitSanitizer());
        $this->enableEncryption = new DB_Field("enableEncryption", "1", new DbBitSanitizer());

        parent::__construct($TableName);
    }
}
?>