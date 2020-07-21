<?php

namespace HHK\House\Attribute;

use HHK\SysConst\AttributeTypes;

/**
 * HospitalAttribute.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of HospitalAttribute
 *
 * @author Eric
 */
 
class HospitalAttribute extends AbstractObjectAttribute {
    
    public function __construct(\PDO $dbh, $id) {
        
        $this->attributeType = AttributeTypes::Hospital;
        parent::__construct($dbh, $id);
    }
    
}
?>