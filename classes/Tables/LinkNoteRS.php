<?php
namespace HHK\Tables;

use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/**
 * LinkNoteRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class LinkNoteRS extends AbstractTableRS {

    public DB_Field $id;  // INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    public DB_Field $idNote;  // INT(11) NOT NULL,
    public DB_Field $linkType;  // VARCHAR(20) DEFAULT NULL,
    public DB_Field $idLink;  // INT(11) DEFAULT NULL,

    function __construct($TableName = "link_note") {

        $this->id = new DB_Field("id", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idNote = new DB_Field("idNote", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->linkType = new DB_Field("linkType", "", new DbStrSanitizer(20), TRUE, TRUE);
        $this->idLink = new DB_Field("idLink", 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
