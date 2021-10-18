<?php

namespace HHK\Member\EmergencyContact;

/**
 * EmergencyContactInterface.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


interface EmergencyContactInterface {
    public function getEcNameFirst();
    public function getEcNameLast();
    public function getEcPhone();
    public function getEcAltPhone();
    public function getEcRelationship();
}
?>