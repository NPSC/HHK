<?php
namespace HHK\Tables\OAuth;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};
use HHK\Tables\Fields\DbBitSanitizer;

/**
 * OauthClientRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class OauthClientRS extends AbstractTableRS {

    public $client_id;  // VARCHAR(32)
    public $idName;  // VARCHAR(45) NOT NULL,
    public $name; //VARCHAR(45) NOT NULL UNIQUE,
    public $secret;
    public $revoked;  // VARCHAR(45) NOT NULL DEFAULT '',
    public $Updated_at;

    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now(),

    function __construct($TableName = "oauth_clients") {

        $this->client_id = new DB_Field("client_id", "", new DbStrSanitizer(32), TRUE, TRUE);
        $this->idName = new DB_Field("idName", "", new DbIntSanitizer(), TRUE, TRUE);
        $this->name = new DB_Field("name", "", new DbStrSanitizer(45), TRUE, TRUE);
        $this->secret = new DB_Field("secret", "", new DbStrSanitizer(255), TRUE, FALSE);
        $this->revoked = new DB_Field("revoked", 0, new DbBitSanitizer(), TRUE, TRUE);
        $this->Updated_at = new DB_Field("Updated_at", null, new DbDateSanitizer("Y-m-d H:i:s"), TRUE, TRUE);
        $this->Timestamp = new DB_Field("timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
?>