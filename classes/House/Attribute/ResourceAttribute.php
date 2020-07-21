<?php

namespace HHK\House\Attribute;

use HHK\SysConst\AttributeTypes;

/**
 * ResourceAttribute.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ResourceAttribute
 *
 * @author Eric
 */
 
class ResourceAttribute extends AbstractObjectAttribute {
    
    public function __construct(\PDO $dbh, $id) {
        
        $this->attributeType = AttributeTypes::Resource;
        parent::__construct($dbh, $id);
    }
    
}
?>