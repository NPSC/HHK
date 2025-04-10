<?php
namespace HHK\Tables;

use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer, DbBlobSanitizer};

/**
 * LinkDocRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class LinkDocRS extends AbstractTableRS {

    public $idDocument;  // INT NOT NULL AUTO_INCREMENT,
    public $idGuest;  // VARCHAR(128) NOT NULL,
    public $idPSG;
    public $idReservation;
    public $username;
    public $Timestamp;  // TIMESTAMP NOT NULL DEFAULT now(),

    function __construct($TableName = "link_doc") {

        $this->idDocument = new DB_Field("idDocument", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idGuest = new DB_Field("idGuest", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idPSG = new DB_Field("idPSG", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idReservation = new DB_Field("idReservation", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->username = new DB_Field("username", "", new DbStrSanitizer(100), FALSE);
        $this->Timestamp = new DB_Field("Timestamp", null, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);

        parent::__construct($TableName);
    }

}
?>