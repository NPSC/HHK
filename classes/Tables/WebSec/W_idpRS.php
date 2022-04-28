<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer};
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
    public $LogoPath; // varchar(500),
    public $SSO_URL; // varchar(500)
    public $IdP_EntityId;  // varchar(500),
    public $IdP_SigningCert; // BLOB,
    public $IdP_EncryptionCert; // BLOB,
    public $expectIdPSigning; // BOOL DEFAULT 1,
    public $expectIdPEncryption; // BOOL DEFAULT 1,
    public $IdP_ManageRoles; // BOOL DEFAULT 1,
    public $Status; // varchar(2),

    function __construct($TableName = "w_idp") {
        $this->idIdp = new DB_Field("idIdp", 0, new DbIntSanitizer());
        $this->Name = new DB_Field("Name", "", new DbStrSanitizer(100));
        $this->LogoPath = new DB_Field("LogoPath", "", new DbStrSanitizer(500));
        $this->SSO_URL = new DB_Field("SSO_URL", "", new DbStrSanitizer(500));
        $this->IdP_EntityId = new DB_Field("IdP_EntityId", "", new DbStrSanitizer(500));
        $this->IdP_SigningCert = new DB_Field("IdP_SigningCert", "", new DbBlobSanitizer());
        $this->IdP_EncryptionCert = new DB_Field("IdP_EncryptionCert", "", new DbBlobSanitizer());
        $this->expectIdPSigning = new DB_Field("expectIdPSigning", "1", new DbBitSanitizer());
        $this->expectIdPEncryption = new DB_Field("expectIdPEncryption", "1", new DbBitSanitizer());
        $this->IdP_ManageRoles = new DB_Field("IdP_ManageRoles", "1", new DbBitSanitizer());
        $this->Status = new DB_Field("Status", 'a', new DbStrSanitizer(1));

        parent::__construct($TableName);
    }
}
?>