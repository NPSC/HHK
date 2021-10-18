<?php

namespace HHK\House\ReserveData\PSGMember;

use HHK\HTMLControls\HTMLContainer;

/**
 * PSGMemResv.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 **/

/**
 * Description of PSGMemResv
 *
 * @author Eric
 */
 
class PSGMemResv extends PSGMemVisit {
    
    protected $myStayType = 'resv';
    
    public function createStayButton($prefix) {
        
        if (isset($this->index['idReservation']) && isset($this->index['idPsg'])) {
            return HTMLContainer::generateMarkup('a', (isset($this->index['label']) ? $this->index['label'] : 'Reservation')
                , array('href'=>'Reserve.php?idPsg=' . $this->index['idPsg'] . '&rid=' . $this->index['idReservation'] . '&id=' . $this->index['idGuest'], 'class'=>'hhk-stayIndicate'));
        } else {
            return HTMLContainer::generateMarkup('span', $this->index['label'], array('class'=>'hhk-stayIndicate'));
        }
    }
}
?>