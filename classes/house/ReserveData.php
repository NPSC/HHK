<?php

/*
 * The MIT License
 *
 * Copyright 2017 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Description of Reservation
 *
 * @author Eric
 */
class ReserveData {

    const RESV_CHOOSER = 'resvChooser';
    const PSG_CHOOSER = 'psgChooser';
    const FAM_SECTION = 'famSection';
    const FULL_NAME = 'fullName';

    const ROLE = 'role';
    const PREF = 'pref';
    const STAY = 'stay';
    const ID = 'id';

    protected $idResv = 0;
    protected $id;
    protected $idPsg = 0;
    protected $idHospitalStay = 0;
    protected $forceNewPsg = FALSE;
    protected $forceNewResv = FALSE;
    protected $fullName = '';
    protected $resvTitle;
    protected $patAsGuestFlag;
    protected $patBirthDateFlag;
    protected $patLabel;
    protected $wlNotesLabel;
    protected $addrPurpose;
    protected $resvEarlyArrDays;
    protected $psgTitle;
    protected $resvChooser;
    protected $psgChooser;
    protected $familySection;
    protected $arrivalDateStr;
    protected $departureDateStr;
    protected $psgMembers;

    function __construct($post) {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);

        if (isset($post['rid'])) {
            $this->setIdResv(intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['id'])) {
            $this->setId(intval(filter_var($post['id'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['idPsg'])) {
            $this->setIdPsg(intval(filter_var($post['idPsg'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['fullName'])) {
            $this->fullName = filter_var($post['fullName'], FILTER_SANITIZE_STRING);
        }

        if (isset($post['gstDate'])) {
            $this->setArrivalDateStr(filter_var($post['gstDate'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['gstCoDate'])) {
            $this->setDepartureDateStr(filter_var($post['gstCoDate'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['mem'])) {
            $this->setMembersFromPost(filter_var_array($post['mem'], FILTER_SANITIZE_STRING));
        }

        $this->resvTitle = $labels->getString('guestEdit', 'reservationTitle', 'Reservation');
        $this->resvEarlyArrDays = $uS->ResvEarlyArrDays;
        $this->patAsGuestFlag = $uS->PatientAsGuest;
        $this->patBirthDateFlag = $uS->PatientBirthDate;
        $this->patLabel = $labels->getString('MemberType', 'patient', 'Patient');
        $this->psgTitle = $labels->getString('statement', 'psgLabel', 'Patient Support Group');
        $this->wlNotesLabel = $labels->getString('referral', 'waitlistNotesLabel', 'Waitlist Notes');
        $this->addrPurpose = '1';
        $this->resvChooser = '';
        $this->psgChooser = '';
        $this->familySection = '';

    }

    protected function setMembersFromPost($postMems) {

        foreach ($postMems as $prefix => $memArray) {

            if ($prefix == '') {
                continue;
            }

            $id = 0;
            $role = '';
            $stay = '0';

            if (isset($memArray[ReserveData::ID])) {
                $id = intval($memArray[ReserveData::ID], 10);
            }
            if (isset($memArray[ReserveData::ROLE])) {
                $role = $memArray[ReserveData::ROLE];
            }
            if (isset($memArray[ReserveData::ID])) {
                $stay = $memArray[ReserveData::STAY];
            }

            $this->setMember(new PSGMember($id, $prefix, $role, $stay));
        }
    }

    public function getMembersArray() {

        $memArray = array();

        foreach ($this->getPsgMembers() as $mem) {

            $ma[ReserveData::ID] = $mem->getId();
            $ma[ReserveData::ROLE] = $mem->getRole();
            $ma[ReserveData::STAY] = $mem->getStay();
            $ma[ReserveData::PREF] = $mem->getPrefix();

            $memArray[$mem->getPrefix()] = $ma;
        }

        return $memArray;
    }

    public function toArray() {

        $rtnData =  array(
            'id' => $this->getId(),
            'rid' => $this->getIdResv(),
            'idPsg' => $this->getIdPsg(),
            'patLabel' => $this->getPatLabel(),
            'resvTitle' => $this->getResvTitle(),
        );

        if ($this->resvChooser != '') {
            $rtnData[ReserveData::RESV_CHOOSER] = $this->resvChooser;
        }

        if ($this->psgChooser != '') {
            $rtnData[ReserveData::PSG_CHOOSER] = $this->psgChooser;
        }

        if ($this->familySection != '') {
            $rtnData[ReserveData::FAM_SECTION] = $this->familySection;
        }

        if ($this->fullName != '') {
            $rtnData[ReserveData::FULL_NAME] = $this->fullName;
        }

        return $rtnData;
    }

    public function getIdResv() {
        return $this->idResv;
    }

    public function getId() {
        return $this->id;
    }

    public function getIdPsg() {
        return $this->idPsg;
    }

    public function getIdHospital_Stay() {
        return $this->idHospitalStay;
    }

    public function getResvTitle() {
        return $this->resvTitle;
    }

    public function getPatAsGuestFlag() {
        return $this->patAsGuestFlag;
    }

    public function getPatBirthDateFlag() {
        return $this->patBirthDateFlag;
    }

    public function getPatLabel() {
        return $this->patLabel;
    }

    public function getWlNotesLabel() {
        return $this->wlNotesLabel;
    }

    public function getResvEarlyArrDays() {
        return $this->resvEarlyArrDays;
    }

    public function getPsgTitle() {
        return $this->psgTitle;
    }

    public function getForceNewPsg() {
        return $this->forceNewPsg;
    }

    public function getForceNewResv() {
        return $this->forceNewResv;
    }

    public function getAddrPurpose() {
        return $this->addrPurpose;
    }

    public function getArrivalDateStr() {
        return $this->arrivalDateStr;
    }

    public function getDepartureDateStr() {
        return $this->departureDateStr;
    }

    public function getPsgMembers() {
        return $this->psgMembers;
    }

    public function getPsgMember($prefix) {

        if (isset($this->psgMembers[$prefix])) {
            return $this->psgMembers[$prefix];
        } else {
            throw new Hk_Exception_Runtime('PSG Member prefix not found.  ');
        }
    }

    public function findMemberById($val) {

        foreach ($this->getPsgMembers() as $mem) {

            if ($mem->getId() === $val) {
                return $mem;
            }
        }

        return NULL;
    }

    public function setMember(PSGMember $mem) {

        $prefix = $mem->getPrefix();

        if ($prefix !== NULL && (String)$prefix != '') {
            $this->psgMembers[$prefix] = $mem;
        }

        return $this;
    }

    public function setIdResv($idResv) {
        if ($idResv < 0) {
            $this->idResv = 0;
            $this->forceNewResv = TRUE;
        } else {
            $this->idResv = $idResv;
            $this->forceNewResv = FALSE;
        }
        return $this;
    }

    public function setIdPsg($idPsg) {

        if($idPsg < 0) {
            $this->forceNewPsg = TRUE;
            $this->idPsg = 0;
        } else {
            $this->idPsg = $idPsg;
            $this->forceNewPsg = FALSE;
        }
        return $this;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function setIdHospital_Stay($id) {
        $this->idHospitalStay = $id;
        return $this;
    }

    public function setResvChooser($resvChooser) {
        $this->resvChooser = $resvChooser;
        return $this;
    }

    public function setPsgChooser($psgChooser) {
        $this->psgChooser = $psgChooser;
        return $this;
    }

    public function setFamilySection($p) {
        $this->familySection = $p;
        return $this;
    }

    public function setArrivalDateStr($arrivalDateStr) {
        $this->arrivalDateStr = $arrivalDateStr;
        return $this;
    }

    public function setDepartureDateStr($departureDateStr) {
        $this->departureDateStr = $departureDateStr;
        return $this;
    }


}

class PSGMember {

    protected $id;
    protected $prefix;
    protected $role;
    protected $stay;

    public function __construct($id, $prefix, $role, $stay) {

        $this->setId($id);
        $this->setPrefix($prefix);
        $this->setRole($role);
        $this->setStay($stay);
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
        return $this->stay;
    }

    public function isStaying() {
        if ($this->getStay() == '1') {
            return TRUE;
        }
        return FALSE;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setPrefix($prefix) {
        $this->prefix = $prefix;
    }

    public function setRole($role) {
        $this->role = $role;
    }

    public function isPatient() {
        if ($this->getRole() == VolMemberType::Patient) {
            return TRUE;
        }
        return FALSE;
    }

    public function setStay($stay) {
        $this->stay = $stay;
    }



}
