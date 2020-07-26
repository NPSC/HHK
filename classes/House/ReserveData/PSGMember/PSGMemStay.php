<?php

namespace HHK\House\ReserveData\PSGMember;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\House\ReserveData\ReserveData;

/**
 * PSGMemStay.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 **/

/**
 * Description of PSGMemStay
 *
 * @author Eric
 */
 
class PSGMemStay {
    
    protected $stay;
    protected $myStayType = 'open';
    
    public function __construct($stayIndex) {
        
        if ($stayIndex == ReserveData::STAYING || $stayIndex == ReserveData::NOT_STAYING || $stayIndex == ReserveData::CANT_STAY || $stayIndex == ReserveData::IN_ROOM) {
            $this->stay = $stayIndex;
        } else {
            $this->stay = ReserveData::NOT_STAYING;
        }
    }
    
    public function createStayButton($prefix) {
        
        
        $cbStay = array(
            'type'=>'checkbox',
            'name'=>$prefix .'cbStay',
            'id'=>$prefix .'cbStay',
            'data-prefix'=>$prefix,
            'class' => 'hhk-cbStay',
        );
        
        $lblStay = array(
            'for'=>$prefix . 'cbStay',
            'id' => $prefix . 'lblStay',
            'data-stay' => $this->getStay(),
            'class' => 'hhk-lblStay hhk-stayIndicate',
        );
        
        
        return HTMLContainer::generateMarkup('label', 'Stay', $lblStay)
        . HTMLInput::generateMarkup('', $cbStay);
        
    }
    
    public function isStaying() {
        if ($this->getStay() == ReserveData::STAYING) {
            return TRUE;
        }
        return FALSE;
    }
    
    public function isBlocked() {
        if ($this->getStay() == ReserveData::CANT_STAY) {
            return TRUE;
        }
        return FALSE;
    }
    
    public function getStay() {
        return $this->stay;
    }
    
    public function setStay($s) {
        $this->stay = $s;
    }
    
    public function setBlocked() {
        $this->stay = ReserveData::CANT_STAY;
    }
    
    public function setNotStaying() {
        $this->stay = ReserveData::NOT_STAYING;
    }
    
    public function setStaying() {
        if ($this->isBlocked() === FALSE) {
            $this->stay = ReserveData::STAYING;
        }
    }
    
    public function getMyStayType() {
        return $this->myStayType;
    }
    
}
?>