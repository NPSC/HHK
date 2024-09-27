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
    /**
     * Summary of getEcNameFirst

     */
    public function getEcNameFirst();
    /**
     * Summary of getEcNameLast

     */
    public function getEcNameLast();
    /**
     * Summary of getEcPhone

     */
    public function getEcPhone();
    /**
     * Summary of getEcAltPhone

     */
    public function getEcAltPhone();
    /**
     * Summary of getEcRelationship

     */
    public function getEcRelationship();
}
?>