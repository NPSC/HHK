<?php

namespace HHK\House\ReserveData\PSGMember;

use HHK\HTMLControls\HTMLInput;
use HHK\House\ReserveData\ReserveData;
use HHK\SysConst\VolMemberType;
use HHK\sec\Labels;

/**
 * PSGMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 **/

/**
 * Description of PSGMember
 *
 * @author Eric
 */

class PSGMember {

    protected $id;
    protected $prefix;
    protected $role;
    protected $primaryGuest;

    /**
     *
     * @var PSGMemStay
     */
    protected $memStay;

    public function __construct($id, $prefix, $role, $isPrimaryGuest, PSGMemStay $memStay) {

        $this->setId($id);
        $this->setPrefix($prefix);
        $this->setRole($role);
        $this->setPrimaryGuest($isPrimaryGuest);

        $this->memStay = $memStay;
    }

    public function createPrimaryGuestRadioBtn($prefix) {

        $rbPri = array(
            'type'=>'radio',
            'name'=>'rbPriGuest',
            'id'=>$prefix .'rbPri',
            'data-prefix'=>$prefix,
            'title'=>'Click to set this person as ' . Labels::getString('MemberType', 'primaryGuest', 'Primary Guest') . '.',
            'style'=>'margin-left:5px;',
            'class'=>'hhk-rbPri'
        );

        if ($this->isPrimaryGuest()) {
            $rbPri['checked'] = 'checked';
        }

        return HTMLInput::generateMarkup($prefix, $rbPri);

    }


    public function getId() {
        return $this->id;
    }

    public function getPrefix() {
        return $this->prefix;
    }

    public function getRole() {
        return $this->role;
    }

    public function getStay() {
        return $this->memStay->getStay();
    }

    public function getStayObj() {
        return $this->memStay;
    }

    public function isStaying() {
        return $this->memStay->isStaying();
    }

    public function isBlocked() {
        return $this->memStay->isBlocked();
    }

    public function isPrimaryGuest() {
        return $this->primaryGuest;
    }

    public function isPatient() {
        if ($this->getRole() == VolMemberType::Patient) {
            return TRUE;
        }
        return FALSE;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function setPrefix($prefix) {
        $this->prefix = $prefix;
        return $this;
    }

    public function setRole($role) {
        $this->role = $role;
        return $this;
    }

    public function setPrimaryGuest($primaryGuest) {

        if ($primaryGuest == TRUE) {
            $this->primaryGuest = TRUE;
        } else {
            $this->primaryGuest = FALSE;
        }

        return $this;
    }

    public function setStay($stay) {
        $this->memStay->setStay($stay);
        return $this;
    }

    public function setStayObj(PSGMemStay $stay) {
        $this->memStay = $stay;
        return $this;
    }

    public function toArray() {

        return array(
            ReserveData::ID => $this->getId(),
            ReserveData::ROLE => $this->getRole(),
            ReserveData::STAY => $this->memStay->getStay(),
            ReserveData::PRI => ($this->isPrimaryGuest() ? '1' : '0'),
            ReserveData::PREF => $this->getPrefix(),
        );
    }

}
?>