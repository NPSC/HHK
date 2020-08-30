<?php

namespace HHK\House\Attribute;

use HHK\Tables\EditRS;
use HHK\Tables\Attribute\AttributeEntityRS;

/**
 * AbstractObjectAttribute.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of AbstractObjectAttribute
 *
 * @author Eric
 */
 
abstract class AbstractObjectAttribute {
    
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
        while ($a = $rmAtrStmt->fetch(\PDO::FETCH_ASSOC)) {
            
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
?>