<?php

namespace HHK\Relation;

use HHK\SysConst\RelLinkType;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};

/**
 * Relatives.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Relatives extends Siblings {
    
    protected function loadRelCode() {
        
        return new RelationCode(array('Code'=>RelLinkType::Relative, 'Description'=>'Relative'));
        
    }
    
    protected function createNewEntry() {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Relative', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
    }
    
    protected function getHtmlId() {
        return "Relative";
    }
    
}
?>