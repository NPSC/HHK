<?php
namespace HHK\House\Appointment;

use HHK\SysConst\AppointmentType;

/*
 * BlockAppointment.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class BlockAppointment extends AbstractAppointment
{


    public function __construct($idAppointment) {

        parent::__construct($idAppointment);
        $this->type = AppointmentType::Block;
    }
}

