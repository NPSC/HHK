<?php

namespace HHK\House\ReserveData\PSGMember;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\House\ReserveData\ReserveData;
use HHK\SysConst\VisitStatus;

/**
 * PSGMemVisit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 **/

/**
 * Description of PSGMemVisit
 *
 * @author Eric
 */

class PSGMemVisit extends PSGMemStay {

    protected $index = array();
    protected $myStayType = 'visit';

    public function __construct($index) {

        parent::__construct(ReserveData::NOT_STAYING);

        $this->index = $index;
        $this->setNotStaying();
    }

    public function createStayButton($prefix) {

        if (isset($this->index['idVisit']) && isset($this->index['Visit_Span'])) {
            $stIcon = '';

            if ($this->index['status'] == VisitStatus::CheckedOut) {
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-extlink', 'style'=>'float: right; margin-right:.3em;', 'title'=>'Checked Out'));
            } else if ($this->index['status'] == VisitStatus::ChangeRate) {
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-tag', 'style'=>'float: right; margin-right:.3em;', 'title'=>'Changed Room Rate'));
            } else if ($this->index['status'] == VisitStatus::NewSpan) {
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-newwin', 'style'=>'float: right; margin-right:.3em;', 'title'=>'Changed Rooms'));
            }

            return HTMLInput::generateMarkup($this->index['room'], array('type'=>'button', 'class'=>'hhk-getVDialog hhk-stayIndicate', 'data-vid'=>$this->index['idVisit'], 'data-span'=>$this->index['Visit_Span'])) . $stIcon;

        } else {
            $this->setStay(ReserveData::IN_ROOM);
            return HTMLContainer::generateMarkup('span', 'In Room', array('class'=>'hhk-stayIndicate'));
        }
    }

}
?>