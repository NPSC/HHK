<?php

namespace HHK\Member\Relation;

use HHK\SysConst\RelLinkType;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};

/**
 * Relatives.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017, 2018-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Relatives extends Siblings {

    /**
     * Summary of loadRelCode
     * @return RelationCode
     */
    protected function loadRelCode():RelationCode {

        return new RelationCode(array('Code'=>RelLinkType::Relative, 'Description'=>'Relative'));

    }

    /**
     * Summary of createNewEntry
     * @return string
     */
    protected function createNewEntry():string {
        return HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('New Relative', array('class'=>'hhk-newlink', 'title'=>'Link a new '.$this->relCode->getTitle(), 'colspan'=>'2', 'style'=>'text-align: center;')));
    }

    /**
     * Summary of getHtmlId
     * @return string
     */
    protected function getHtmlId():string {
        return "Relative";
    }

}
?>