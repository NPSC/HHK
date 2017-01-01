<?php
/**
 * Attributes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Attributes
 *
 * @author Eric
 */
class Attributes {

    protected $attributes;
    protected $attributeTypes;
    protected $id;


    public function __construct(\PDO $dbh) {

        $this->attributes = array();
        $this->attributeTypes = array();

        $stmt = $dbh->query("Select * from attribute where `Status` = 'a' order by `Type`");

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $this->attributes[$r['idAttribute']] = $r;
        }


        $this->attributeTypes = readGenLookupsPDO($dbh, 'Attribute_Type');

    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function getAttributesByType($type) {

        $atypes = array();
        foreach ($this->attributes as $a) {
            if ($a['Type'] == $type) {
                $atypes[$a['idAttribute']] = $a;
            }
        }
        return $atypes;
    }

    public function getAttributeTypes() {
        return $this->attributeTypes;
    }

}

abstract class ObjectAttributes {

    protected $attributes;
    protected $id;
    protected $attributeType;

    public function __construct(\PDO $dbh, $id) {

        $this->id = $id;
        $this->attributes = $this->loadAttributes($dbh);

    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function getActiveAttributes() {

        $roomAttrs = array();
        foreach ($this->attributes as $k => $a) {
            if ($a['isActive'] > 0) {
                $roomAttrs[$k] = $a;
            }
        }

        return $roomAttrs;
    }

    protected function loadAttributes(\PDO $dbh) {

        $attrs = array();

        $rmAtrStmt = $dbh->prepare("select a.idAttribute, a.Title, case when ar.idEntity is null then 0 else 1 end as `isActive`
from attribute a left join attribute_entity ar on a.idAttribute = ar.idAttribute and ar.idEntity = :id
where a.Type = :typ and a.`Status` = 'a' ORDER by a.idAttribute");

        $rmAtrStmt->execute(array(':id'=>$this->id, ':typ'=>$this->attributeType));
        while ($a = $rmAtrStmt->fetch(PDO::FETCH_ASSOC)) {

            $attrs[$a['idAttribute']] = $a;
        }

        return $attrs;
    }

    public function saveAttributes(\PDO $dbh, $capturedAttributes) {

        foreach ($this->attributes as $k => $v) {

            if (isset($capturedAttributes[$k]) && $v['isActive'] == 0) {
                // set new attribute
                $rmAt = new AttributeEntityRS();
                $rmAt->idEntity->setNewVal($this->id);
                $rmAt->idAttribute->setNewVal($k);
                $rmAt->Type->setNewVal($this->attributeType);
                EditRS::insert($dbh, $rmAt);

            } else if (isset($capturedAttributes[$k]) === FALSE && $v['isActive'] != 0) {
                // remove attribute
                $rmAt = new AttributeEntityRS();
                $rmAt->idEntity->setStoredVal($this->id);
                $rmAt->idAttribute->setStoredVal($k);
                EditRS::delete($dbh, $rmAt, array($rmAt->idEntity, $rmAt->idAttribute));

            }
        }

        $this->attributes = $this->loadAttributes($dbh);
    }
}


class RoomAttributes extends ObjectAttributes {

    public function __construct(\PDO $dbh, $id) {

        $this->attributeType = Attribute_Types::Room;
        parent::__construct($dbh, $id);
    }

}

class ResourceAttributes extends ObjectAttributes {

    public function __construct(\PDO $dbh, $id) {

        $this->attributeType = Attribute_Types::Resource;
        parent::__construct($dbh, $id);
    }

}

class HospitalAttributes extends ObjectAttributes {

    public function __construct(\PDO $dbh, $id) {

        $this->attributeType = Attribute_Types::Hospital;
        parent::__construct($dbh, $id);
    }

}
