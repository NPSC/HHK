<?php

namespace HHK\House\Attribute;

use HHK\SysConst\AttributeTypes;

/**
 * RoomAttribute.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoomAttribute
 *
 * @author Eric
 */

class RoomAttribute extends AbstractObjectAttribute {

    public function __construct(\PDO $dbh, $id) {

        $this->attributeType = AttributeTypes::Room;
        parent::__construct($dbh, $id);
    }

}
?>