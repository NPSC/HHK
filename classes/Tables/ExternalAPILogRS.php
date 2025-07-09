<?php
namespace HHK\Tables;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbBlobSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * ExternalAPILogRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ExternalAPILogRS extends AbstractTableRS {

    public $Log_Type;
    public $Sub_Type;
    public $requestMethod;
    public $endpoint;
    public $responseCode;
    public $request;
    public $response;
    public $username;
    public $Timestamp;

    function __construct($TableName = "external_api_log") {

        $this->Log_Type = new DB_Field("Log_Type", '', new DbStrSanitizer(45), true, true);
        $this->Sub_Type = new DB_Field("Sub_Type", "", new DbStrSanitizer(45), true, true);
        $this->requestMethod = new DB_Field("requestMethod", "", new DbStrSanitizer(10), true, true);
        $this->endpoint = new DB_Field("endpoint", "", new DbStrSanitizer(512), true, true);
        $this->responseCode = new DB_Field("username", "", new DbStrSanitizer(3), true, true);
        $this->request = new DB_Field("request", "", new DbBlobSanitizer(), true, true);
        $this->response = new DB_Field("response", "", new DbBlobSanitizer(), true, true);
        $this->username = new DB_Field("username", "", new DbStrSanitizer(255), true, true);
        //$this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s.u"), FALSE);
        parent::__construct($TableName);

    }

}