<?php

namespace HHK\House\Attribute;

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

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

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
?>