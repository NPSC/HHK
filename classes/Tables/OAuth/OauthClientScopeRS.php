<?php
namespace HHK\Tables\OAuth;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbDateSanitizer, DbStrSanitizer};

/**
 * OauthClientScopeRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class OauthClientScopeRS extends AbstractTableRS {

    public DB_Field $oauth_client;  // VARCHAR(32) NOT NULL
    public DB_Field $oauth_scope;  // VARCHAR(100) NOT NULL
    public DB_Field $Timestamp;  // TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP

    function __construct($TableName = "oauth_client_scopes") {

        $this->oauth_client = new DB_Field("oauth_client", "", new DbStrSanitizer(32), TRUE, TRUE);
        $this->oauth_scope = new DB_Field("oauth_scope", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
