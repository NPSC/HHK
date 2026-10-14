<?php
namespace HHK\Tables\House;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer};

/**
 * ResourceRoomRS.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ResourceRoomRS extends AbstractTableRS {

    public DB_Field $idResource_room;  // INT(11) NOT NULL AUTO_INCREMENT,
    public DB_Field $idResource;  // INT(11) NOT NULL,
    public DB_Field $idRoom;  // INT(11) NOT NULL,

    function __construct($TableName = "resource_room") {

        $this->idResource_room = new DB_Field("idResource_room", 0, new DbIntSanitizer());
        $this->idResource = new DB_Field("idResource", 0, new DbIntSanitizer(), TRUE, TRUE);
        $this->idRoom = new DB_Field("idRoom", 0, new DbIntSanitizer(), TRUE, TRUE);

        parent::__construct($TableName);
    }

}
